<?php

namespace App\Http\Controllers\Billings\PointWallets;

use App\Domain\Merchandises\Actions\MercRequestSaveAction;
use App\Domain\Merchandises\Enums\MercRequestPhaseEnums;
use App\Domain\Wallets\Enums\{PointTransactionEnums, PointTransactionPhaseEnums};
use App\Domain\Wallets\Services\{CDTimeChecker, CreateOrGetBarWalletService, CreateOrGetMerigoWalletService, TransactionWalletReconcileService};
use App\Helpers\{UidGenerator, ApiJsonReturnHelper, FileHelpers\FileManagement, Notifications\PushNotificationHelper};
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\MercRedeemValidateRequest;
use App\Models\Workflows\Bar\{Bar, Deal};
use App\Models\Workflows\Merchandises\Merchandise;
use App\Notifications\PushNotifications\BarCheckinPushNotify;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;

class ApiTransactionController extends Controller implements HasMiddleware
{
	use AuthorizesRequests;
	// --------------------- Middleware ---------------------
	public static function middleware(): array
	{
		return [
			new Middleware('verify_role_key:student', only: ['checkIn']),
		];
	}
	// ------------------------------------------

	public function checkIn(Request $request, CreateOrGetBarWalletService $barWalletService, TransactionWalletReconcileService $reconcile)
	{
		try {
			$validated = $request->validate([
				'bar_id' => 'required|integer|exists:bars,id',
			]);

			$USER = $request->user();

			$result = Cache::lock("checkin:{$USER->id}:{$validated['bar_id']}", 10)->block(5, function () use ($validated, $USER, $barWalletService, $reconcile) {

				return DB::transaction(function () use ($validated, $USER, $barWalletService, $reconcile) {
					$targetBar = Bar::findorfail((int)$validated['bar_id']);

					$cdTime = new CDTimeChecker();
					$cdTime->handle($targetBar, $USER);

					$name = $targetBar?->name ?? " an unknown bar";
					$wallet = $barWalletService->handle($validated, $targetBar?->id, $USER);

					$transaction = $wallet->walletTransactions()->create([
						'transaction_uid' => UidGenerator::uniqueULID("TRX-CHKN", 10, 26, 4),
						'user_id' => $USER->id,
						'transaction_type' => PointTransactionEnums::CHECKIN?->value,
						'amount' => $targetBar?->earning_points ?? 0,
						'phase' => PointTransactionPhaseEnums::COMPLETED?->value,
						'note' => "Points earned by check-in into {$name}",
						'status' => 1,
					]);

					$wallet = $reconcile->handle($transaction, $wallet);

					// ------------------------- PUSH NOTIFTCATION -------------------------
					PushNotificationHelper::handle(false, new BarCheckinPushNotify(false, $targetBar, $USER), $USER);
					PushNotificationHelper::handle(false, new BarCheckinPushNotify(false, $targetBar, $USER), $targetBar->barRelatingBackTo_User);
					// ------------------------- PUSH NOTIFTCATION -------------------------
					
					return [
						'transaction' => $transaction,
						'wallet' => $wallet,
					];
				});
			});


			return ApiJsonReturnHelper::handle(true, 200, "Transaction recorded successfully!", $result);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('recording a transaction', $e);
		}
	}
	// ------------------------------------------

	public function deal(Request $request, CreateOrGetBarWalletService $barWalletService, TransactionWalletReconcileService $reconcile)
	{
		try {
			$validated = $request->validate([
				'deal_id' => 'required|integer|exists:bar_deals,id',
			]);

			$USER = $request->user();

			$result = Cache::lock("checkin:{$USER->id}:{$validated['deal_id']}", 10)->block(5, function () use ($validated, $USER, $barWalletService, $reconcile) {

				return DB::transaction(function () use ($validated, $USER, $barWalletService, $reconcile) {
					$targetDeal = Deal::findorfail((int)$validated['deal_id']);
					$targetBar = Bar::findorfail((int)$targetDeal?->bar_id);

					$name = $targetDeal?->name ?? " an unknown deal";

					$wallet = $barWalletService->handle($validated, $targetBar?->id, $USER, true);

					if ($wallet->balance < $targetDeal?->point_cost) {
						ExceptionHandling::bailout(409, "Insufficient wallet balance", [
							'deal_cost' => $targetDeal?->point_cost,
							'wallet_balance' => $wallet->balance,
							'points_short' => ($targetDeal?->point_cost - $wallet->balance),
						]);
					}

					$transaction = $wallet->walletTransactions()->create([
						'transaction_uid' => UidGenerator::uniqueULID("TRX-DEAL", 10, 26, 4),
						'user_id' => $USER->id,
						'transaction_type' => PointTransactionEnums::DEAL?->value,
						'amount' => $targetDeal?->point_cost ?? 0,
						'phase' => PointTransactionPhaseEnums::COMPLETED?->value,
						'note' => "Points deducted for reedeming {$name}",
						'status' => 1,
					]);

					$wallet = $reconcile->handle($transaction, $wallet);
					return [
						'transaction_details' => $transaction,
						'wallet_details' => $wallet,
					];
				});
			});

			return ApiJsonReturnHelper::handle(true, 200, "Transaction recorded successfully!", $result);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('recording a transaction', $e);
		}
	}
	// ------------------------------------------

	public function mercRequest(MercRedeemValidateRequest $request, MercRequestSaveAction $action, CreateOrGetMerigoWalletService $merigoWalletService, TransactionWalletReconcileService $reconcile)
	{
		try {
			$validated = $request->validated();
			$USER = $request->user();

			$result = Cache::lock("checkin:{$USER->id}:{$validated['merc_id']}", 10)->block(5, function () use ($validated, $action, $USER, $merigoWalletService, $reconcile) {

				return DB::transaction(function () use ($validated, $action, $USER, $merigoWalletService, $reconcile) {
					$targetMarchandise = Merchandise::findorfail((int)$validated['merc_id']);

					$name = $targetMarchandise?->name ?? " a merchandise";

					$wallet = $merigoWalletService->handle(null, $USER, false);

					if ($wallet->balance < $targetMarchandise?->points_cost) {
						ExceptionHandling::bailout(409, "Insufficient wallet balance", [
							'item_cost' => $targetMarchandise?->points_cost,
							'wallet_balance' => $wallet->balance,
							'points_short' => ($targetMarchandise?->points_cost - $wallet->balance),
						]);
					}

					$transaction = $wallet->walletTransactions()->create([
						'transaction_uid' => UidGenerator::uniqueULID("TRX-MERC", 10, 26, 4),
						'user_id' => $USER->id,
						'transaction_type' => PointTransactionEnums::MERCHANDISE?->value,
						'amount' => $targetMarchandise?->points_cost ?? 0,
						'phase' => PointTransactionPhaseEnums::COMPLETED?->value,
						'note' => "Merigo points deducted for requesting {$name}",
						'status' => 1,
					]);

					$wallet = $reconcile->handle($transaction, $wallet);

					$mercReqSave = $action->execute($validated, $USER, $targetMarchandise?->points_cost, MercRequestPhaseEnums::PENDING?->value);

					return [
						'merchandise_request' => $mercReqSave,
						'transaction_details' => $transaction,
						'wallet_details' => $wallet,
					];
				});
			});

			return ApiJsonReturnHelper::handle(true, 200, "Transaction recorded successfully!", $result);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('recording a transaction', $e);
		}
	}
}
