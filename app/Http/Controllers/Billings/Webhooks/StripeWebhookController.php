<?php

namespace App\Http\Controllers\Billings\Webhooks;

use App\Helpers\PaymentHelpers\CentsConversion;
use App\Models\Billings\Subscriptions\SubscribedUserWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, DB};
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Carbon\Carbon;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
	public function handleWebhook(Request $request)
	{
		$payload = json_decode($request->getContent(), true);
		$this->showLog(['local', 'staging'], 'handleWebhook():', $request);

		try {
			return parent::handleWebhook($request);
		} catch (Throwable $e) {
			return response()->json(['error' => $e->getMessage()], 500);
		}
	}

	// -------------- 1) CHECKOUT SESSION COMPLETED --------------
	// -------------- checkout.session.completed (required) --------------

	protected function handleCheckoutSessionCompleted(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleCheckoutSessionCompleted():', []);

		return DB::transaction(function () use ($payload) {
			$session = $payload['data']['object'] ?? null;
			// $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
			$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

			$subscriptionId = $session['subscription'] ?? null;
			$invoiceId = $session['invoice'] ?? null;

			$validUntil = null;
			$invoiceNum = null;
			$invoicePdf = null;
			$payer = null;
			$last4 = null;
			$brand = null;
			$expiry = null;

			if (!$session) {
				Log::warning('handleCheckoutSessionCompleted: No session data');
				return $this->successMethod();
			}

			$subscribedUserId = $session['metadata']['subscribed_user_row_id'] ?? null;
			if (!$subscribedUserId) {
				Log::error('handleCheckoutSessionCompleted: Missing subscribed_user_row_id');
				return $this->successMethod();
			}

			$subscribedUser = SubscribedUserWeb::find($subscribedUserId);
			if (!$subscribedUser || !$subscribedUser->subscriberWebRelatingBackTo_Tier) {
				Log::error('handleCheckoutSessionCompleted: SubscribedUser or Tier not found');
				return $this->successMethod();
			}

			try {
				// -------------- EXPIRY + CARD DETAILS --------------
				if ($subscriptionId) {
					$subscription = $stripe->subscriptions->retrieve($subscriptionId);
					$validUntil = $this->calculateValidUntil($subscriptionId);
					$defaultPaymentMethodId = $subscription->default_payment_method ?? null;

					if ($defaultPaymentMethodId) {
						$paymentMethod = $stripe->paymentMethods->retrieve($defaultPaymentMethodId);

						$payer = $paymentMethod->billing_details->name ?? null;
						$last4 = $paymentMethod->card->last4 ?? null;
						$brand = $paymentMethod->card->brand ?? null;

						if (isset($paymentMethod->card->exp_month, $paymentMethod->card->exp_year)) {
							$expiry = $paymentMethod->card->exp_month . "/" . $paymentMethod->card->exp_year;
						}
					}
				}
				// -------------- EXPIRY + CARD DETAILS --------------

				// -------------- INVOICE DETAILS --------------
				if ($invoiceId) {
					$invoice = $stripe->invoices->retrieve($invoiceId);
					$invoiceNum = $invoice->number ?? null;
					$invoicePdf = $invoice->invoice_pdf ?? null;
				}
				// -------------- INVOICE DETAILS --------------

			} catch (Throwable $e) {
				Log::error('Failed to retrieve Stripe data', ['error' => $e->getMessage()]);
			}

			// ------- NEXT RENEWAL CALCULATION (INITIAL SUBSCRIPTION)

			$subscribedUser->update([
				'payment_status' => 'paid',
				'sub_status' => 'running',
				'amount' => CentsConversion::convertCentsToFullAmount($session, $session['amount_total']) ?? null,
				'note' => 'Payment successful',

				'next_renewal_at' => $validUntil,
				// 'renewed_at'      => now(),
				'payer' => $payer,
				'last4' => $last4,
				'brand' => $brand,
				'expiry' => $expiry,

				'status' => 1,
				'stripe_checkout_session_id' => $session['id'],
				'stripe_customer_id' => $session['customer'] ?? null,
				'stripe_subscription_id' => $subscriptionId,
				'invoice_id' => $invoiceId,
				'invoice_num' => $invoiceNum,
				'pdf_1st' => $invoicePdf,
			]);

			return $this->successMethod();
		});
	}
	// -------------- 2) INVOICE COMPLETED (PAYMENT SUCCESS) --------------

	protected function handleInvoicePaid(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleInvoicePaid():');

		return DB::transaction(function () use ($payload) {

			$invoice = $payload['data']['object'] ?? null;

			$this->showLog(['local', 'staging'], 'handleInvoicePaid():');

			if (!$invoice) {
				$this->showLog(['local', 'staging'], 'handleInvoicePaid(): No invoice data');
				return $this->successMethod();
			}
			$billingReason = $invoice['billing_reason'] ?? null;
			if (!in_array($billingReason, ['subscription_create', 'subscription_cycle', 'subscription_update'])) {
				return $this->successMethod();
			}

			$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

			$this->showLog(['local', 'staging'], 'handleInvoicePaid():', [$stripe, $payload, $invoice]);

			$line = $invoice['lines']['data'][0] ?? null;

			if (!$line) {
				return $this->successMethod();
			}

			$metadata = $line['metadata'] ?? null;

			if (!$metadata) {
				$this->showLog(['local', 'staging'], 'handleInvoicePaid(): Missing metadata');
				return $this->successMethod();
			}

			$subscribedUser = SubscribedUserWeb::findOrFail((int)$metadata['subscribed_user_row_id']);

			$timeFrame = $this->setTimeFrame($payload, $invoice, $line, $stripe);

			$invoiceFresh = $stripe->invoices->retrieve($invoice['id']);
			$paidAmount = isset($invoiceFresh->amount_paid) ? $invoiceFresh->amount_paid / 100 : 0;

			$subscribedUser->update([
				'paid_amount' => $paidAmount,
				'duration' => $timeFrame['duration'] ?? null,
				'valid_until' => $timeFrame['validUntil'] ?? null,
				'billing_reason' => $billingReason ?? null,
				'status' => 1,
				'status_note' => ($billingReason && strtolower($billingReason) === 'subscription_cycle') ? 'sub_renewed' : 'paid',
				'last_transaction' => $timeFrame['lastTransaction'] ?? null,
				'stripe_subscription_id' => $timeFrame['subscription_id'] ?? null,
				'last_invoice' => $invoice['invoice_pdf'] ?? null,
			]);

			return $this->successMethod();
		});
	}

	protected function handleInvoicePaymentSucceeded(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleInvoicePaymentSucceeded():', []);

		return DB::transaction(function () use ($payload) {
			$invoice = $payload['data']['object'] ?? null;

			if (!$invoice) {
				return $this->successMethod();
			}

			$subscriptionId = $invoice['subscription'] ?? null;
			if (!$subscriptionId) {
				Log::info('handleInvoicePaymentSucceeded: No subscription ID (likely first payment, handled by checkout.session.completed)');
				return $this->successMethod();
			}

			// -------------- HANDLE FIRST PAYMENT --------------
			$isFirstPayment = ($invoice['billing_reason'] ?? '') === 'subscription_create';
			if ($isFirstPayment) {
				Log::info('handleInvoicePaymentSucceeded: First payment (already handled by checkout.session.completed)');
				return $this->successMethod();
			}
			// -------------- HANDLE FIRST PAYMENT --------------

			// -------------- HANDLES RENEWALS HERE --------------
			$subscribedUser = SubscribedUserWeb::where('stripe_subscription_id', $subscriptionId)->latest()->first();
			if (!$subscribedUser) {
				Log::warning('handleInvoicePaymentSucceeded: SubscribedUser not found for renewal', [
					'subscription_id' => $subscriptionId
				]);
				return $this->successMethod();
			}

			$cancelArray = ["canceled", "cancelled", "incomplete"];

			if (in_array($subscribedUser->sub_status, $cancelArray) || ($invoice['status'] ?? '') !== 'paid') {
				Log::info('handleInvoicePaymentSucceeded: Subscription canceled or not paid, skipping renewal', []);
				return $this->successMethod();
			}
			// -------------- HANDLES RENEWALS HERE --------------

			$validUntil = $this->calculateValidUntil($subscriptionId);
			$subscribedUser->update([
				'status' => 1,
				'next_renewal_at' => $validUntil,
				'renewed_at' => !$isFirstPayment ?? now(),
				'note' => 'Subscription renewed',
				'pdf_latest' => $invoice['invoice_pdf'] ?? null,
			]);

			return $this->successMethod();
		});
	}
	// ---------------- 3.1) PAYMENT INTENT FAILED ----------------

	protected function handlePaymentIntentPaymentFailed(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handlePaymentIntentPaymentFailed():', []);

		return DB::transaction(function () use ($payload) {
			$paymentIntent = $payload['data']['object'] ?? null;

			if (!$paymentIntent) {
				return $this->successMethod();
			}

			$meta = $paymentIntent['metadata'] ?? [];
			$subscribedUserId = $meta['subscribed_user_row_id'] ?? null;

			if (!$subscribedUserId) {
				return $this->successMethod();
			}

			$subscribedUser = SubscribedUserWeb::find($subscribedUserId);

			if ($subscribedUser && $subscribedUser->status == 0) {
				$subscribedUser->update([
					'note' => 'Payment failed'
				]);

				Log::warning('handlePaymentIntentPaymentFailed: Payment intent failed', [
					'subscribed_user_row_id' => $subscribedUserId,
					'payment_intent_id' => $paymentIntent['id']
				]);
			}

			return $this->successMethod();
		});
	}
	// -------------- 3.2) INVOICE PAYMENT FAILED (CRITICAL!) --------------

	protected function handleInvoicePaymentFailed(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleInvoicePaymentFailed():', []);

		return DB::transaction(function () use ($payload) {
			$invoice = $payload['data']['object'] ?? null;

			if (!$invoice) {
				return $this->successMethod();
			}

			$subscriptionId = $invoice['subscription'] ?? null;

			if (!$subscriptionId) {
				return $this->successMethod();
			}

			$subscribedUser = SubscribedUserWeb::where('stripe_subscription_id', $subscriptionId)
				->latest()
				->first();

			if ($subscribedUser) {
				$attemptCount = $invoice['attempt_count'];
				$subscribedUser->update([
					'note' => "Payment failed (attempt {$attemptCount})"
				]);

				Log::warning('handleInvoicePaymentFailed: Invoice payment failed', [
					'subscribed_user_row_id' => $subscribedUser->id,
					'user_id' => $subscribedUser->user_id,
					'attempt_count' => $attemptCount
				]);
			}

			return $this->successMethod();
		});
	}
	// ------------- 4) CANCEL ATTEMPT -------------

	protected function handleCustomerSubscriptionUpdated(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleCustomerSubscriptionUpdated():', []);

		return DB::transaction(function () use ($payload) {

			$subscription = $payload['data']['object'] ?? null;
			if (!$subscription)
				return $this->successMethod();

			$this->showLog(['local', 'staging'], 'subscription:', [$payload, $subscription]);

			$subscriptionId = $subscription['id'] ?? null;
			if (!$subscriptionId)
				return $this->successMethod();

			$fetchTime = $subscription['canceled_at'] ?? null;

			$subscribedUser = SubscribedUserWeb::where('stripe_subscription_id', $subscriptionId)->first();
			if (!$subscribedUser)
				return $this->successMethod();

			// user requested cancel (end of period)
			if (!empty($subscription['cancel_at_period_end'])) {
				$periodEnd = $subscription['items']['data'][0]['current_period_end'] ?? null;

				$subscribedUser->update([
					'status_note' => 'cancel_scheduled',
					'valid_until' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : $subscribedUser->valid_until,
					'cancel_req_at' => $fetchTime ? Carbon::createFromTimestamp($fetchTime) : now(),
				]);
			}
			return $this->successMethod();
		});
	}

	// -------------- 5.1) SUBSCRIPTION DELETED --------------

	protected function handleCustomerSubscriptionDeleted(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleCustomerSubscriptionDeleted():', []);

		parent::handleCustomerSubscriptionDeleted($payload);

		return DB::transaction(function () use ($payload) {
			$subscription = $payload['data']['object'] ?? null;

			if (!$subscription) {
				return $this->successMethod();
			}

			$subscriptionId = $subscription['id'] ?? null;
			if (!$subscriptionId) {
				return $this->successMethod();
			}

			SubscribedUserWeb::where('stripe_subscription_id', $subscriptionId)
				->update([
					'sub_status' => 'cancelled',
					'status' => 0,
					'note' => 'Subscription cancelled',
					'valid_until' => now()
				]);

			Log::info('Subscription cancelled', ['subscription_id' => $subscriptionId]);

			return $this->successMethod();
		});
	}
	// -------------- 5.2) SESSION EXPIRED --------------

	protected function handleCheckoutSessionExpired(array $payload)
	{
		$this->showLog(['local', 'staging'], 'handleCheckoutSessionExpired():', []);

		return DB::transaction(function () use ($payload) {
			$session = $payload['data']['object'] ?? null;

			if (!$session) {
				return $this->successMethod();
			}

			$meta = $session['metadata'] ?? [];
			$subscribedUserId = $meta['subscribed_user_row_id'] ?? null;

			if (!$subscribedUserId) {
				return $this->successMethod();
			}

			$subscribedUser = SubscribedUserWeb::find($subscribedUserId);

			if ($subscribedUser && $subscribedUser->status == 0) {
				$subscribedUser->update([
					'note' => 'Checkout session expired'
				]);

				Log::info('handleCheckoutSessionExpired: Checkout session expired', [
					'subscribed_user_row_id' => $subscribedUserId
				]);
			}

			return $this->successMethod();
		});
	}
	// -------------- HELPER METHODS --------------

	protected function calculateValidUntil(?string $subscriptionId): ?Carbon
	{
		if (!$subscriptionId) {
			return null;
		}

		try {
			// $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
			$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
			$subscription = $stripe->subscriptions->retrieve($subscriptionId);
			$subscriptionArray = $subscription->toArray();

			// Get current_period_end from subscription items (not from subscription root)
			$currentPeriodEnd = $subscriptionArray['items']['data'][0]['current_period_end'] ?? null;

			if ($currentPeriodEnd) {
				return Carbon::createFromTimestamp($currentPeriodEnd)
					->timezone(config('app.timezone'));
			}
		} catch (Throwable $e) {
			Log::error('calculateValidUntil: Failed to calculate', [
				'subscription_id' => $subscriptionId,
				'error' => $e->getMessage()
			]);
		}
		return null;
	}

	protected function setTimeFrame($payload, $invoice, $line, $stripe)
	{
		$interval = null;
		$intervalCount = null;
		$validUntil = null;
		$duration = null;
		$subscription_id = null;
		$fetchTime = null;
		$lastTransaction = null;

		if ($invoice && $line && $stripe) {
			$subscription_id = $invoice['parent']['subscription_details']['subscription'] ?? $invoice['lines']['data'][0]['parent']['subscription_item_details']['subscription'] ?? $invoice['subscription'] ?? null;

			if (!$subscription_id) {
				return [
					'subscription_id' => null,
					'duration' => null,
					'validUntil' => null,
				];
			}

			try {
				$subscription = $stripe->subscriptions->retrieve($subscription_id);
				// $this->showLog(['local', 'staging'], 'subscription:', [$subscription]);
			} catch (Throwable $e) {
				return [
					'subscription_id' => $subscription_id,
					'duration' => null,
					'validUntil' => null,
				];
			}

			$interval = $subscription->items->data[0]->price->recurring->interval;
			$intervalCount = $subscription->items->data[0]->price->recurring->interval_count;

			$duration = $intervalCount . '_' . $interval;

			$validUntil = Carbon::createFromTimestamp($line['period']['end']);

			$fetchTime = $invoice['created'] ?? ($payload['created'] ?? null);
			$lastTransaction = $fetchTime ? Carbon::createFromTimestamp($fetchTime) : now();
		}

		return [
			'subscription_id' => $subscription_id,
			'duration' => $duration,
			'validUntil' => $validUntil,
			'lastTransaction' => $lastTransaction,
		];
	}

	protected function showLog($env = ['local'], $methodName, $logBody = [])
	{
		if (app()->environment($env)) {
			Log::info("Custom WebhookController: {$methodName}", $logBody);
		}
	}
}

/*
USE CASE:
	$this->showLog(['local', 'staging'], 'handleWebhook():', $request);

*/
