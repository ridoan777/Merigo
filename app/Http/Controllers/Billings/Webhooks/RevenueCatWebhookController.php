<?php

namespace App\Http\Controllers\Billings\Webhooks;

use App\Helpers\ApiJsonReturnHelper;
use App\Helpers\Errors\ExceptionHandling;
use App\Helpers\PaymentHelpers\SetCurrentPlanInUser;
use App\Http\Controllers\Controller;
use App\Models\Billings\Subscriptions\SubscribedUserApp;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

class RevenueCatWebhookController extends Controller
{
	public function handleWebhook(Request $request)
	{
		if (app()->environment('local', 'staging')) {
			Log::info('--------------------------------------------------------------');
			Log::info('RevenueCat: handleWebhook() all requests = ', $request->all());
			Log::info('--------------------------------------------------------------');
		}
		// --------- Authorization --------
		$incoming = trim($request->header('Authorization', ''));
		$expected = trim(config('services.revenuecat.webhook_secret') ?: env('REVENUECAT_AUTH_TOKEN', ''));

		if ($expected === '' || $incoming === '' || !hash_equals($expected, $incoming)) {
			return ApiJsonReturnHelper::handle(false, 401, 'Unauthorized! Rvc bearer token mismatched or missing!', [
				'secret_mnessage' => "Rvc bearer token mismatched or missing!",
				'incoming' => app()->environment('local') ? $incoming : 'Bearer token only visible in development!',
			]);
		}

		// Payload
		$payload = $request->json()->all();
		$event = $payload['event'] ?? null;

		if (is_array($event) && (!isset($event['id']) || empty($event['id']))) {
			// dedupe key to prevents duplicates even if event.id missing
			$event['id'] = hash('sha256', json_encode([
				$event['type'] ?? null,
				$event['app_user_id'] ?? null,
				$event['product_id'] ?? null,
				$event['purchased_at_ms'] ?? null,
				$event['expiration_at_ms'] ?? null,
				$event['transaction_id'] ?? null,
				$event['original_transaction_id'] ?? null,
			], JSON_UNESCAPED_SLASHES));
		}

		if (!empty($event['id'])) {
			$existing = SubscribedUserApp::where('rc_event_id', $event['id'])->first();
			if ($existing && $existing->processing_status === 'applied') {
				return $this->successResponse('Event already applied');
			}
		}

		if (!is_array($event)) {
			return ApiJsonReturnHelper::handle(false, 400, 'Invalid payload: missing event', null);
		}

		$eventType = $event['type'] ?? null;
		$appUserId = $event['app_user_id'] ?? null;

		if (!$eventType || !$appUserId) {
			return ApiJsonReturnHelper::handle(false, 400, 'Invalid payload: missing type or app_user_id', [
				'type' => $eventType,
				'app_user_id' => $appUserId,
			]);
		}

		$userId = $this->resolveUserId($appUserId);

		if (!$userId) {
			return ApiJsonReturnHelper::handle(true, 200, 'User could not be resolved', [
				'app_user_id' => $appUserId,
			]);
		}

		$user = User::find($userId);

		if ($eventType !== 'TEST' && !$user) {
			return ApiJsonReturnHelper::handle(true, 200, 'User not found', ['user_id' => $userId,]);
		}

		try {
			return DB::transaction(function () use ($request, $eventType, $event, $user) {
				if (!empty($event['id'])) {
					$locked = SubscribedUserApp::where('rc_event_id', $event['id'])->lockForUpdate()->first();

					if ($locked && $locked->processing_status === 'applied') {
						return $this->successResponse('Event already applied');
					}
				}
				switch ($eventType) {
					case 'INITIAL_PURCHASE':
						return $this->handleInitialPurchase($event, $user);

					case 'RENEWAL':
						return $this->handleRenewal($event, $user);

					case 'UNCANCELLATION':
						return $this->handleUncancellation($event, $user);

					case 'SUBSCRIPTION_PAUSED':
						return $this->handlePaused($event, $user);

					case 'NON_RENEWING_PURCHASE':
						return $this->handleNonRenewingPurchase($event, $user);

					case 'PRODUCT_CHANGE':
						return $this->handleProductChange($event, $user);

					case 'SUBSCRIPTION_EXTENDED':
						return $this->handleSubscriptionExtended($event, $user);

					case 'TRANSFER':
						return $this->handleTransfer($event, $user);

					case 'CANCELLATION':
						return $this->handleCancellation($event, $user);

					case 'EXPIRATION':
						return $this->handleExpiration($event, $user);

					case 'BILLING_ISSUE':
						return $this->handleBillingIssue($event, $user);

					case 'TEST':
						return $this->handleTestEvent($request);

					case 'TEMPORARY_ENTITLEMENT_GRANT':
					case 'REFUND_REVERSED':
					case 'INVOICE_ISSUANCE':	// not official
					case 'VIRTUAL_CURRENCY_TRANSACTION':	// not official
					case 'EXPERIMENT_ENROLLMENT':	// not official
					case 'GRACE_PERIOD':	// not official
						return $this->successResponse("Event '{$eventType}' acknowledged (no action)");

					default:
						Log::info('RevenueCat Webhook: ignored event', ['type' => $eventType]);
						return $this->successResponse("Event '{$eventType}' ignored");
				}
			});
		} catch (Throwable $e) {
			Log::error('RevenueCat Webhook Exception', ['error' => $e->getMessage(),]);
			if (!empty($event['id'])) {
				SubscribedUserApp::where('rc_event_id', $event['id'])->update(['processing_status' => 'failed']);
			}
			return ExceptionHandling::handle('Internal server error', $e);
		}
	}

	// -------------------- EVENT HANDLERS --------------------

	protected function handleInitialPurchase(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleInitialPurchase():', []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$this->applyCommon($subscribedUser, $event, [
			'payment_status' => 'paid',
			'sub_status' => 'running',
			'duration' => $this->calculatePackageDuration($event),
			'note' => 'INITIAL PURCHASE processed.',
			'status' => 1,
		]);

		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();
		SetCurrentPlanInUser::setUserPlanPaid($user, $event);	// adding current plan to users table

		return $this->successResponse('INITIAL PURCHASE processed successfully!');
	}

	protected function handleRenewal(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleRenewal():', []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$this->applyCommon($subscribedUser, $event, [
			'payment_status' => 'paid',
			'sub_status' => 'running',
			'duration' => $this->calculatePackageDuration($event),
			'note' => 'Subscription is RENEWED.',
			'status' => 1,
		]);
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();
		SetCurrentPlanInUser::setUserPlanPaid($user, $event);	// adding current plan to users table

		$this->showLog(['local', 'staging'], "handleRenewal() success", ['subscribedUser' => $subscribedUser]);
		return $this->successResponse('Subscription is RENEWED.');
	}

	protected function handleUncancellation(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleUncancellation():', []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$expiration = $this->carbonFromMs($event['expiration_at_ms'] ?? null);

		$subscribedUser->sub_status = 'running';
		$subscribedUser->status = 1;
		$subscribedUser->renewal_at = $expiration ?: $subscribedUser->renewal_at;
		$subscribedUser->note = 'Cancelled subscription was resumed!';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		SetCurrentPlanInUser::setUserPlanPaid($user, $event);	// adding current plan to users table

		$this->showLog(['local', 'staging'], "handleUncancellation() success", ['subscribedUser' => $subscribedUser, 'expiration' => $expiration ?? "N/A"]);

		return $this->successResponse('Cancelled subscription was resumed!');
	}

	protected function handleNonRenewingPurchase(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleNonRenewingPurchase():', []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$this->applyCommon($subscribedUser, $event, [
			'payment_status' => 'paid',
			'sub_status' => 'non_renewing',
			'status' => 1,
			'note' => 'Non-renewing purchase',
		]);
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		SetCurrentPlanInUser::setUserPlanPaid($user, $event);	// adding current plan to users table

		return $this->successResponse('NON_RENEWING_PURCHASE processed');
	}

	protected function handleProductChange(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleProductChange():', []);

		$oldPackage = $this->findRecord($event, $user);
		$message = "PRODUCT CHANGE scheduled to new_product_id";
		if (isset($event['new_product_id']) && !empty($event['new_product_id'])) {
			$message = "PRODUCT CHANGE scheduled to {$event['new_product_id']}";
		}

		if ($oldPackage) {
			$oldPackage->sub_status = 'changing';
			$oldPackage->note = "PRODUCT CHANGE scheduled to {$message}";
			$oldPackage->save();
		}

		$newEvent = $event;
		$newEvent['product_id'] = $event['new_product_id'];

		$subscribedUser = $this->findOrCreate($newEvent, $user);

		$this->applyCommon($subscribedUser, $newEvent, [
			'payment_status' => 'pending',
			'sub_status' => 'scheduled',
			'status' => 0,
			'note' => 'Product change scheduled; awaiting renewal',
		]);
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		return $this->successResponse('Subscription change scheduled');
	}

	protected function handleSubscriptionExtended(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleSubscriptionExtended():', []);

		$subscribedUser = $this->findRecord($event, $user) ?? $this->findOrCreate($event, $user);
		$subscribedUser->renewal_at = $this->carbonFromMs($event['expiration_at_ms'] ?? null)
			?? $subscribedUser->renewal_at;

		$subscribedUser->note = 'Subscription EXTENDED by store';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		return $this->successResponse('SUBSCRIPTION_EXTENDED processed');
	}

	protected function handleTransfer(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], 'handleTransfer():', []);

		$subscribedUser = SubscribedUserApp::where('user_id', $user->id)->where('provider', 'revenuecat')->latest('id')->first();

		if (!$subscribedUser) {
			return $this->successResponse('TRANSFER processed (no existing subscription found)');
		}

		$subscribedUser->note = 'TRANSFERRED';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		return $this->successResponse('TRANSFER processed successfully!');
	}

	protected function handleRefund(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], "handleRefund():", []);

		$subscribedUser = $this->findRecord($event, $user) ?? $this->findOrCreate($event, $user);

		$subscribedUser->sub_status = 'refunded';
		$subscribedUser->status = 0;
		$subscribedUser->note = 'Subscription refunded/revoked.';
		$subscribedUser->save();

		// $this->setUserPlanFree($user);	// setting current plan free to users table

		return $this->successResponse('REFUND processed');
	}
	// ---------------------- ISSUE EVENTS ----------------------

	protected function handlePaused(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], "handlePaused():", []);

		$subscribedUser = $this->findRecord($event, $user) ?? $this->findOrCreate($event, $user);

		$subscribedUser->sub_status = 'running';
		$subscribedUser->payment_status = 'paid';
		$subscribedUser->status = 1;

		$resumeAt = $this->carbonFromMs($event['auto_resume_at_ms'] ?? null);
		if ($resumeAt) {
			$subscribedUser->renewal_at = $resumeAt;
		}

		$subscribedUser->note = 'Subscription PAUSE SCHEDULED at period end';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		return $this->successResponse('SUBSCRIPTION_PAUSED acknowledged (access retained)');
	}

	protected function handleCancellation(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], "handleCancellation():", []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$isRefund =
			($event['cancel_reason'] ?? null) === 'CUSTOMER_SUPPORT'
			&& isset($event['price_in_purchased_currency'])
			&& $event['price_in_purchased_currency'] < 0;

		if ($isRefund) {
			$subscribedUser->sub_status = 'refunded';
			$subscribedUser->note = 'Subscription REFUNDED by customer support';
		} else {
			$subscribedUser->sub_status = 'cancelled';
			$subscribedUser->note = 'Subscription CANCELLED by user';
		}

		$subscribedUser->cancelled_at = Carbon::now();
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		$this->showLog(['local', 'staging'], "handleCancellation() success", ['subscribedUser' => $subscribedUser, 'isRefund' => $isRefund ?? "N/A"]);

		return $this->successResponse(
			$isRefund ? 'REFUND processed' : 'Subscription was CANCELLED by user.'
		);
	}

	protected function handleExpiration(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], "handleExpiration():", []);

		$subscribedUser = $this->findRecord($event, $user);

		if (!$subscribedUser) {
			return $this->successResponse('EXPIRATION with no matching record. Or, users not found!');
		}

		$expiration = $this->carbonFromMs($event['expiration_at_ms'] ?? null);

		$subscribedUser->sub_status = 'expired';
		$subscribedUser->status = 0;
		$subscribedUser->renewal_at = $expiration;
		$subscribedUser->note = 'Subscription EXPIRED';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		$hasAnotherActiveSubscription = SubscribedUserApp::where('user_id', $user->id)
			->where('processing_status', 'applied')->where('payment_status', 'paid')
			->whereIn('sub_status', ['running', 'changed', 'cancelled', 'billing_issue', 'refunded'])
			->whereNotNull('renewal_at')->where('renewal_at', '>', now())->exists();

		// if (!$hasAnotherActiveSubscription) {
		// 	SetCurrentPlanInUser::setUserPlanFree($user); 	// setting current plan free to users table
		// }

		Log::info('Expiration IGNORED downgrading. Another active subscription exists', [
			'user_id' => $user->id,
			'user_email' => $user->email ?? null,
			'expired_subscription' => $subscribedUser->rc_subscription_id,
		]);

		return $this->successResponse('EXPIRATION processed');
	}

	protected function handleBillingIssue(array $event, User $user)
	{
		$this->showLog(['local', 'staging'], "handleBillingIssue():", []);

		$subscribedUser = $this->findOrCreate($event, $user);

		$subscribedUser->sub_status = 'billing_issue';
		$subscribedUser->status = 0;
		$subscribedUser->note = 'Payment failed; billing issue reported by store.';
		$subscribedUser->processing_status = 'applied';
		$subscribedUser->save();

		return $this->successResponse('BILLING_ISSUE processed');
	}
	// ---------------------- ISSUE EVENTS ----------------------

	public function handleTestEvent(Request $request)
	{
		$this->showLog(['local', 'staging'], "RevenueCat: handleTestEvent():", []);

		$incoming = trim($request->header('Authorization', ''));
		$expected = trim(config('services.revenuecat.webhook_secret') ?: env('REVENUECAT_AUTH_TOKEN', ''));

		if ($expected === '' || $incoming === '' || !hash_equals($expected, $incoming)) {
			return ApiJsonReturnHelper::handle(false, 401, 'Unauthorized', null);
		}

		$payload = $request->json()->all();
		$event = $payload['event'] ?? null;

		if (!is_array($event) || ($event['type'] ?? null) !== 'TEST') {
			return ApiJsonReturnHelper::handle(false, 400, 'This endpoint only accepts TEST events', null);
		}

		// Store or log test event
		$this->showLog(['local', 'staging'], "RevenueCat: handleTestEvent():", [$event]);

		return ApiJsonReturnHelper::handle(true, 200, 'TEST event received', [
			'event_id' => $event['id'] ?? null,
		]);
	}
	// -------------------- EVENT HANDLERS --------------------


	// -------------------- HELPERS --------------------

	protected function showLog($env = ['local'], $methodName, $logBody = [])
	{
		if (app()->environment($env)) {
			Log::info("Custom WebhookController: {$methodName}", $logBody);
			// USE CASE: $this->showLog(['local', 'staging'], 'handleWebhook():', $request);
		}
	}

	protected function successResponse(string $message)
	{
		return ApiJsonReturnHelper::handle(true, 200, $message, null);
	}

	protected function resolveUserId(string $appUserId): ?int
	{
		$digits = preg_replace('/\D/', '', $appUserId);
		return $digits !== '' ? (int)$digits : null;
	}

	protected function findRecord(array $event, User $user): ?SubscribedUserApp
	{
		$rcId = $this->rcSubscriptionId($event);

		if (!$rcId) {
			Log::warning('findRecord called without rc_subscription_id', [
				'event' => $event,
				'user_id' => $user->id,
			]);
			return null;  // No guessing by product_id
		}

		return SubscribedUserApp::where('user_id', $user->id)->where('provider', 'revenuecat')->where('rc_subscription_id', $rcId)->latest('id')->first();
	}

	protected function findOrCreate(array $event, User $user): SubscribedUserApp
	{
		$existing = $this->findRecord($event, $user);
		if ($existing)
			return $existing;

		$subscribedUser = new SubscribedUserApp();
		$subscribedUser->user_id = $user->id;
		$subscribedUser->entitlement_id = $this->getFirstEntitlement($event);
		$subscribedUser->provider = 'revenuecat';
		$subscribedUser->store = $event['store'] ?? null;
		$subscribedUser->country = $event['country_code'] ?? null;
		$subscribedUser->currency = $event['currency'] ?? null;
		$subscribedUser->rc_product_id = $event['product_id'] ?? null;
		$subscribedUser->rc_subscription_id = $this->rcSubscriptionId($event);
		$subscribedUser->rc_purchase_token = $event['transaction_id'] ?? null;
		$subscribedUser->rc_event_id = $event['id'] ?? null;
		$subscribedUser->sub_status = 'requested';
		$subscribedUser->payment_status = 'pending';

		try {
			$subscribedUser->save();
		} catch (\Illuminate\Database\QueryException $e) {	// preventing race-condition
			if (($e->errorInfo[0] ?? null) === '23000' && $subscribedUser->rc_event_id) {
				$dupe = SubscribedUserApp::where('rc_event_id', $subscribedUser->rc_event_id)->first();
				if ($dupe)
					return $dupe;
			}
			throw $e;
		}
		return $subscribedUser;
	}

	protected function applyCommon(SubscribedUserApp $subscribedUser, array $event, array $overrides)
	{
		$subscribedUser->rc_product_id = $event['product_id'] ?? $subscribedUser->rc_product_id;
		$subscribedUser->rc_subscription_id = $this->rcSubscriptionId($event) ?? $subscribedUser->rc_subscription_id;
		$subscribedUser->rc_purchase_token = $event['transaction_id'] ?? $subscribedUser->rc_purchase_token;
		$subscribedUser->rc_event_id = $event['id'] ?? $subscribedUser->rc_event_id;

		$subscribedUser->country = $event['country_code'] ?? $subscribedUser->country;
		$subscribedUser->currency = $event['currency'] ?? $subscribedUser->currency;
		$subscribedUser->amount = $event['price_in_purchased_currency'] ?? $subscribedUser->amount;

		$subscribedUser->entitlement_id = $this->getFirstEntitlement($event);

		$subscribedUser->purchased_at = $this->carbonFromMs($event['purchased_at_ms'] ?? null);

		$subscribedUser->renewal_at = $this->carbonFromMs($event['expiration_at_ms'] ?? null)
			?? $subscribedUser->renewal_at;

		foreach ($overrides as $key => $value) {
			$subscribedUser->{$key} = $value;
		}
	}

	protected function carbonFromMs(?int $milliSeconds): ?Carbon
	{
		if (!$milliSeconds)
			return null;
		return Carbon::createFromTimestamp((int)($milliSeconds / 1000));
	}

	protected function rcSubscriptionId(array $event): ?string
	{
		if (!empty($event['original_transaction_id']))
			return (string)$event['original_transaction_id'];

		if (!empty($event['transaction_id']))
			return (string)$event['transaction_id'];

		return null;
	}

	protected function getFirstEntitlement(array $event): ?string
	{
		if (!empty($event['entitlement_ids']) && is_array($event['entitlement_ids'])) {
			return $event['entitlement_ids'][0] ?? null;
		}
		return $event['entitlement_id'] ?? null;
	}

	protected function calculatePackageDuration(array $event): ?int
	{
		$purchasedMs = $event['purchased_at_ms'] ?? null;
		$expiresMs = $event['expiration_at_ms'] ?? null;

		$durationDays = 0;	// days

		if ($purchasedMs && $expiresMs) {
			$durationDays = floor(($expiresMs - $purchasedMs) / 1000 / 60 / 60 / 24);
		}
		return $durationDays;
	}
	// -------------------- HELPERS --------------------
}
