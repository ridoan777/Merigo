<?php

namespace App\Http\Controllers\Billings\PointWallets;

use App\Domain\Wallets\Enums\{PointTransactionEnums, PointTransactionPhaseEnums};
use App\Domain\Wallets\Services\{CDTimeChecker, CreateOrGetBarWalletService, TransactionWalletReconcileService};
use App\Helpers\{UidGenerator, ApiJsonReturnHelper, FileHelpers\FileManagement};
use App\Http\Controllers\Controller;
use App\Models\Billings\Wallets\BarWallet;
use App\Models\Billings\Wallets\MerigoWallet;
use App\Models\Billings\Wallets\PointsTransaction;
use App\Models\Workflows\Bar\{Bar, Deal};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;

class ApiWalletController extends Controller implements HasMiddleware
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

	public function index(Request $request)
	{
		try {
			$USER = $request->user();

			// $transactions = PointsTransaction::with(['walletMorphed:id,bar_id,balance,total_earnings,total_spent'])
			$transactions = PointsTransaction::with(['walletMorphed'])
				->whereHasMorph('walletMorphed', '*', function ($query) use ($USER) {
					$query->where('user_id', $USER->id);
				})
				->latest()
				->paginate(20);

			$transactions->getCollection()->transform(function ($item) {
				$item->wallet_details = $item->toArray()['wallet_morphed'] ?? null;

				$item->makeHidden('walletMorphed');
				$item->makeHidden('wallet_morphed');

				return $item;
			});

			return ApiJsonReturnHelper::handle(true, 200, "All my transactions have been fetched successfully!", $transactions);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching wallets', $e);
		}
	}
	// ------------------- BAR WALLETS -----------------------

	public function indexBarWallets(Request $request)
	{
		try {
			$USER = $request->user();

			$wallets = BarWallet::with(['walletRelatingBackTo_Bar:id,bar_uid,bar_admin_id,name,image,address,status'])->userId($USER->id)->get();

			$wallets->transform(function ($item) {
				$item->bar_details = $item->walletRelatingBackTo_Bar;
				$item->unsetRelation('walletRelatingBackTo_Bar');

				return $item;
			});

			return ApiJsonReturnHelper::handle(true, 200, "All my wallets have been fetched sucessfully!", $wallets);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching wallets', $e);
		}
	}
	// ------------------------------------------

	public function indexBarTrx(Request $request)
	{
		try {
			$USER = $request->user();

			$wallets = BarWallet::with([
				'walletRelatingBackTo_Bar:id,bar_uid,bar_admin_id,name,image,address,status',
				'walletTransactions' => fn($q) => $q->latest()
			])->userId($USER->id)->get();

			$wallets->transform(function ($item) {
				$item->bar_details = $item->walletRelatingBackTo_Bar;
				$item->transaction_details = $item->walletTransactions;
				$item->unsetRelation('walletRelatingBackTo_Bar');
				$item->unsetRelation('walletTransactions');

				return $item;
			});

			return ApiJsonReturnHelper::handle(true, 200, "All transactions have been fetched sucessfully!", $wallets);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching transactions', $e);
		}
	}
	// ------------------- MERIGO WALLETS -----------------------

	public function indexMerigoWallets()
	{
		try {
			$wallets = MerigoWallet::with(['walletRelatingBackTo_User:id,name,user_uid,email,avatar'])->paginate(20);

			$wallets->getCollection()->transform(function ($item) {
				$item->user_details = $item->walletRelatingBackTo_User;
				$item->unsetRelation('walletRelatingBackTo_User');

				return $item;
			});

			return ApiJsonReturnHelper::handle(true, 200, "All Merigo wallets have been fetched sucessfully!", $wallets);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching Merigo wallets', $e);
		}
	}
	// ------------------------------------------

	public function showMerigo(Request $request)
	{
		try {
			$USER = $request->user();

			$wallets = MerigoWallet::with(['walletRelatingBackTo_User:id,name,user_uid,email,avatar,own_referral_code,invited_referral_code'])->userId($USER->id)->first();

			if ($wallets) {
				$wallets->user_details = $wallets->walletRelatingBackTo_User;
				$wallets->unsetRelation('walletRelatingBackTo_User');
			}

			return ApiJsonReturnHelper::handle(true, 200, "My Merigo wallet has been fetched sucessfully!", $wallets);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching Merigo wallets', $e);
		}
	}
	// ------------------------------------------
}
