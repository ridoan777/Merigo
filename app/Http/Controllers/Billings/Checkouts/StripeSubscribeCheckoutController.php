<?php

namespace App\Http\Controllers\Billings\Checkouts;

use App\Http\Controllers\Controller;
use App\Helpers\{ApiJsonReturnHelper,Errors\ExceptionHandling};
use App\Helpers\PaymentHelpers\{CentsConversion,SubscriptionAuthCheck};
use App\Models\Billings\Subscriptions\{SubscribedUserWeb, SubscriptionTier};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\{Http\Request, Validation\Rule};
use App\Models\Workflows\Projects\Project;
use App\Helpers\Settings\SettingsGatekeeping;
use Stripe\Subscription as StripeSubscription;
use Stripe\Stripe;
use Throwable;

class StripeSubscribeCheckoutController extends Controller
{
   public function checkout(Request $request)
   {
      // ------------- PSEUDO-FORM VALIDATION -------------
      $validated = $request->validate([
         'tier_id' => ['required', 'integer', Rule::exists('subscription_tiers', 'id')->where('platform', 'web')],
      ]);
      // ------------- PSEUDO-FORM VALIDATION -------------
      try {
         $user = $request->user();
         $tier = SubscriptionTier::findOrFail($validated['tier_id']);
         $project = Project::findOrFail($tier?->service_id);
         
         DB::beginTransaction();
         // -------------------------- PREVENT MUTATION --------------------------
         if($existingSubscription = SubscriptionAuthCheck::noDuplicationGate($user->id, $tier->id, $project->id)){
            return $existingSubscription;
         }
         // -------------------------- PREVENT MUTATION --------------------------

         // -------------------------- UNBLOCK CHECKING --------------------------
         if($existingSubscription = SettingsGatekeeping::unblockChecking($user->id, $project, $tier->id)){
            return $existingSubscription;
         }
         // -------------------------- UNBLOCK CHECKING --------------------------
         $subscribedUser = SubscribedUserWeb::firstOrCreate(
            [
               'user_id' => $user->id,
               'tier_id' => $tier->id,
               'service_id' => $project->id,
            ],
            [
               'payment_status' => 'pending',
               'sub_status' => 'requested',
               'amount' => round($tier->final_price, 2),
               'duration' => $tier->duration,
               'status' => 0,
               'note' => 'Awaiting payment',
            ]
         );

         $checkout = $user->newSubscription('default', $tier->stripe_price_id)
            ->checkout([
               'metadata' => [ // (A) sent to checkout.session
                  'subscribed_user_row_id' => $subscribedUser->id,
                  'user_id' => $user->id,
               ],
               'subscription_data' => [ // (B) sent to subscription + invoice events
                  'metadata' => [
                     'subscribed_user_row_id' => $subscribedUser->id,
                     'user_id' => $user->id,
                     'tier_id' => $tier->id,
                     'service_id' => $project->id,
                     'user_email' => $user->email,
                  ]
               ],
               'success_url' => route('checkout_success') . '?session_id={CHECKOUT_SESSION_ID}',
               'cancel_url' => route('checkout_cancel') . '?session_id={CHECKOUT_SESSION_ID}',
            ]);

         Log::info('Checkout session created from checkout()', ['session_url' => $checkout->url]);

         DB::commit();

         return ApiJsonReturnHelper::handle(true, 200, 'Checkout session created successfully.', [
            'checkout_url' => $checkout->url,
         ]);
      } catch (Throwable $e) {
         DB::rollBack();
         return ExceptionHandling::handle('checking out.', $e);
      }
   }
   // ------------------------------------------------

   public function success(Request $request)
   {
      try {
         $sessionId = $request->get('session_id');
         // $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
         $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
         $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription', 'customer']]);

         $subscription = $session->subscription ?? null;
         $invoice = null;
         $billDetails = null;
         $card = null;

         // ---------------- INVOICE DETAILS ----------------
         if ($subscription && isset($subscription->latest_invoice)) {
            $invoiceId = $subscription->latest_invoice;
            $invoice = $stripe->invoices->retrieve($invoiceId);
         }
         // ---------------- INVOICE DETAILS ----------------

         // ---------------- CARD DETAILS ----------------
         if ($subscription && isset($subscription->default_payment_method)) {
            $paymentMethodId = $subscription->default_payment_method;
            $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);

            $billDetails = $paymentMethod->billing_details ?? null;
            $card = $paymentMethod->card ?? null;
         }
         // ---------------- CARD DETAILS ----------------

         return ApiJsonReturnHelper::handle(true, 200, 'Payment success callback.', [
            'stripe_session_id' => $session->id,
            'subscription_id' => $subscription ? $subscription->id : null,
            'payment' => [
               'currency' => $session->currency,
               'total_amount' => (float) round(CentsConversion::convertCentsToFullAmount($session, $session->amount_total), 2) ?? "N/A",
               'payment_status' => $session->payment_status,
            ],
            'customer' => [
               'id' => $session->customer->id,
               'email' => $session->customer->email,
               'name' => $session->customer->name,
            ],
            'invoice' => [
               'id' => $invoice->id ?? null,
               'number' => $invoice->number ?? null,
               'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
               'invoice_pdf' => $invoice->invoice_pdf ?? null,
            ],
            'card' => [
               'payer' => $billDetails?->name ?? null,
               'last4' => $card?->last4 ?? null,
               'brand' => $card?->brand ?? null,
               'funding' => $card?->funding ?? null,
               'expiry' => ($card && isset($card->exp_month, $card->exp_year)) ? $card->exp_month . "/" . $card->exp_year : null,
               'message' => "No sensitive card data are stored. Only publicly shareable information are displayed."
            ],
            'stripe_confidentials' => [
               // 'invoice' => $invoice,	// never expose
               // 'stripe' => $session,	// never expose
            ],
         ]);
      } catch (Throwable $e) {
         DB::rollBack();
         return ExceptionHandling::handle('making a payment.', $e);
      }
   }
   // ------------------------------------------------

   public function cancel(Request $request)
   {
      try {
         // $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
         $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

         $sessionId = $request->get('session_id');

         $session = $stripe->checkout->sessions->retrieve($sessionId);
         $meta = $session->metadata ?? [];
         $subscribedUser = SubscribedUserWeb::find($meta->subscribed_user_row_id) ?? null;

         if ($subscribedUser) {
            $subscribedUser->update([
               'stripe_checkout_session_id' => $session->id,
               'payment_status' => $session->payment_status ?? "unknown",
               'sub_status' => "incomplete",
               'note' => 'Payment rejected by User',
               'status' => 0,
            ]);
         }

         return response()->json([
            'status' => true,
            'message' => 'Payment attempt is cancelled!',
            'stripe_session_id' => $session->id,
            'subscription_id' => $session->subscription ? $session->subscription->id : null,
            'payment' => [
               'currency' => $session->currency,
               'total_amount' => (float) round(CentsConversion::convertCentsToFullAmount($session, $session->amount_total), 2) ?? "N/A",
               'payment_status' => $session->payment_status,
            ],
            'metadata' => [
               'subscribed_user_row_id' => $meta->subscribed_user_row_id ?? null,
               'user_id' => $meta->user_id ?? null,
            ],
            'stripe_confidential' => [
               // 'stripe' => $session,
            ],
            'code' => 200
         ], 200);
      } catch (Throwable $e) {
         DB::rollBack();
         return ExceptionHandling::handle('cancelling a payment.', $e);
      }
   }
   // ------------------------------------------------

   public function cancelSubscription($sub_id)
   {
      try {
         $user = Auth::user();
         DB::beginTransaction();
         $subscribedUser = SubscribedUserWeb::where('user_id', $user->id)->where('stripe_subscription_id', $sub_id)->latest()->first();

         if (!$subscribedUser || !$subscribedUser->stripe_subscription_id) {
            return response()->json(['error' => 'Subscription not found'], 404);
         }

         // Stripe::setApiKey(env('STRIPE_SECRET'));
         $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

         $subscription = StripeSubscription::retrieve($subscribedUser->stripe_subscription_id);

         $subscription->cancel();

         $subscribedUser->update([
            'note' => 'Subscription cancellation attempt'
         ]);

         DB::commit();
         return ApiJsonReturnHelper::handle(true, 200, 'Subscription cancelled successfully.', [
            'subscription' => $subscription,
         ]);
      } catch (Throwable $e) {
         DB::rollBack();
         return ExceptionHandling::handle('cancelling subscription.', $e);
      }
   }
}
