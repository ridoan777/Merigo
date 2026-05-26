<?php

namespace App\Http\Controllers\Billings\PointWallets;

use App\Helpers\Ui\DatatableHelper;
use App\Http\Controllers\Controller;
use App\Models\Billings\Wallets\{BarWallet,MerigoWallet,PointsTransaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Throwable;

class WebWalletController extends Controller
{
	// -------------------- BAR WALLET --------------------
	public function barWalletIndex()
	{
		return view('Admin.sidebar.Wallets.bar_wallet');
	}

	public function toggleBar(BarWallet $barWallet)
	{
		try {
			$barWallet->update([
				'status' => !$barWallet->status,
			]);

			return response()->json(['success' => true]);
		} catch (Throwable $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function deleteBar(BarWallet $barWallet)
	{
		try {
			$name = $barWallet->bar_wallet_uid;
			$barWallet->delete();

			return redirect()->back()->with('success', "Bar wallet '{$name}' has been deleted successfully.");
		} catch (Throwable $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function bulkDeleteBar(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:wallet_bar_points,id',
		]);

		$ids = $request->ids;
		$wallets = BarWallet::whereIn('id', $ids)->get();

		if ($wallets->isEmpty()) {
			return back()->with('error', 'No valid bar wallets selected.');
		}

		$MESSAGE = "Selected bar wallets are deleted.";
		$deleted = [];

		DB::beginTransaction();
		try {
			foreach ($wallets as $wallet) {
				$deleted[] = $wallet->bar_wallet_uid;
				/** @var BarWallet $wallet */
				$wallet->delete();
			}

			DB::commit();

			$MESSAGE = "Selected events [" . count($deleted) . "] have been deleted successfully.";

			return redirect()->back()->with('success', $MESSAGE);
		} catch (Throwable $e) {
			DB::rollBack();
			Log::error('Event bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

			return back()->with('error', 'Bulk delete failed. No changes were made.');
		}
	}

	public function barWalletDatatable(Request $request)
	{
		try {
			$columns = [null, null, 'id', 'bar_wallet_uid', null, null, 'balance', null, 'status', 'created_at', null];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);

			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'created_at';

			$orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

			$query = BarWallet::with([
				'walletRelatingBackTo_User:id,user_uid,name,email,avatar',
				'walletRelatingBackTo_Bar:id,bar_uid,name,city,image',
				'walletTransactions' => fn($q) => $q->latest()->limit(1),
			])->select(['id', 'bar_wallet_uid', 'user_id', 'bar_id', 'balance', 'total_earnings', 'total_spent', 'status', 'created_at']);

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {

					$q->where('bar_wallet_uid', 'like', "%{$search}%")

						->orWhereHas('walletRelatingBackTo_User', function ($uq) use ($search) {
							$uq->where('name', 'like', "%{$search}%")
								->orWhere('email', 'like', "%{$search}%")
								->orWhere('user_uid', 'like', "%{$search}%");
						})

						->orWhereHas('walletRelatingBackTo_Bar', function ($bq) use ($search) {
							$bq->where('name', 'like', "%{$search}%")
								->orWhere('city', 'like', "%{$search}%")
								->orWhere('bar_uid', 'like', "%{$search}%");
						});
				});
			}

			if ($orderCol) {
				$query->orderBy($orderCol, $orderDir);
			}

			$recordsTotal = BarWallet::count();
			$recordsFiltered = $query->count();

			$data = $query->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;

				// $row->view_url = route('backend_bar_wallet_show', $row->id);

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;

				$row->user_block = DatatableHelper::userBlock($row->walletRelatingBackTo_User);

				$bar = $row->walletRelatingBackTo_Bar;
				$row->bar_block = DatatableHelper::detailBlock($bar, true, $bar->bar_uid, true, $bar?->image);

				$row->wallet_block = '
					<div class="flex flex-col text-center">
						<span class="font-bold text-indigo-600">Balance: ' . number_format($row->balance) . '</span>
						<span class="text-xs text-green-600">Earned: +' . number_format($row->total_earnings) . '</span>
						<span class="text-xs text-red-500">Spent: -' . number_format($row->total_spent) . '</span>
					</div>
				';

				$lastTrx = $row->walletTransactions->first();

				$row->last_transaction = $lastTrx
					? '
					<div class="w-32 flex flex-col text-center">
						<span class="font-medium text-gray-700">' . e($lastTrx->transaction_type?->value ?? 'N/A') . '</span>
						<span class="text-xs text-indigo-500">' . number_format($lastTrx->amount) . ' pts</span>
						<span class="text-xs text-gray-400">' . $lastTrx->created_at->timezone($zones['zone'])->format('d M Y, H:i') . '</span>
					</div>
				'
					: '<span class="text-xs text-gray-400">No Transaction</span>';

				$row->status_label = DatatableHelper::phaseColor($row->status);

				$row->created_at_formatted = $row->created_at
					? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_bar_wallet_toggle', 'backend_bar_wallet_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Throwable $e) {

			return DatatableHelper::handleDatatableError($request, $e, true, "Bar Wallet Datatable Error");
		}
	}

	// -------------------- MERIGO WALLET --------------------
	public function merigoWalletIndex()
	{
		return view('Admin.sidebar.Wallets.merigo_wallet');
	}

	public function toggleMerigo(MerigoWallet $merigoWallet)
	{
		try {
			$merigoWallet->update([
				'status' => !$merigoWallet->status,
			]);

			return response()->json(['success' => true]);
		} catch (Throwable $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function deleteMerigo(MerigoWallet $merigoWallet)
	{
		try {
			$name = $merigoWallet->merigo_wallet_uid;
			$merigoWallet->delete();

			return redirect()->back()->with('success', "Merigo wallet '{$name}' has been deleted successfully.");
		} catch (Throwable $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function bulkDeleteMerigo(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:wallet_merigo_points,id',
		]);

		$ids = $request->ids;
		$wallets = MerigoWallet::whereIn('id', $ids)->get();

		if ($wallets->isEmpty()) {
			return back()->with('error', 'No valid Merigo wallets selected.');
		}

		$MESSAGE = "Selected Merigo wallets are deleted.";
		$deleted = [];

		DB::beginTransaction();
		try {
			foreach ($wallets as $wallet) {
				$deleted[] = $wallet->merigo_wallet_uid;
				/** @var MerigoWallet $wallet */
				$wallet->delete();
			}

			DB::commit();

			$MESSAGE = "Selected events [" . count($deleted) . "] have been deleted successfully.";

			return redirect()->back()->with('success', $MESSAGE);
		} catch (Throwable $e) {
			DB::rollBack();
			Log::error('Merigo wallet bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

			return back()->with('error', 'Bulk delete failed. No changes were made.');
		}
	}

	public function merigoWalletDatatable(Request $request)
	{
		try {

			$columns = [null, null, 'id', 'merigo_wallet_uid', null, 'balance', null, 'status', 'created_at', null];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);

			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'created_at';
			$orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

			$query = MerigoWallet::with([
				'walletRelatingBackTo_User:id,user_uid,name,email,avatar',
				'walletTransactions' => fn($q) => $q->latest()->limit(1),
			])->select(['id', 'merigo_wallet_uid', 'user_id', 'total_referrals', 'balance', 'total_earnings', 'total_spent', 'status', 'created_at']);

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('merigo_wallet_uid', 'like', "%{$search}%")
						->orWhereHas('walletRelatingBackTo_User', function ($uq) use ($search) {
							$uq->where('name', 'like', "%{$search}%")
								->orWhere('email', 'like', "%{$search}%")
								->orWhere('user_uid', 'like', "%{$search}%");
						});
				});
			}

			if ($orderCol)
				$query->orderBy($orderCol, $orderDir);

			$recordsTotal = MerigoWallet::count();
			$recordsFiltered = $query->count();

			$data = $query->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;

				$row->user_block = DatatableHelper::userBlock($row->walletRelatingBackTo_User);

				$row->wallet_block = '
					<div class="flex flex-col text-center">
						<span class="font-bold text-indigo-600">Balance: ' . number_format($row->balance) . '</span>
						<span class="text-xs text-green-600">Earned: +' . number_format($row->total_earnings) . '</span>
						<span class="text-xs text-red-500">Spent: -' . number_format($row->total_spent) . '</span>
						<span class="text-xs text-amber-500">Referrals: ' . number_format($row->total_referrals) . '</span>
					</div>
				';

				$lastTrx = $row->walletTransactions->first();

				$row->last_transaction = $lastTrx
					? '<div class="w-32 flex flex-col text-center">
						<span class="font-medium text-gray-700">' . e($lastTrx->transaction_type?->value ?? 'N/A') . '</span>
						<span class="text-xs text-indigo-500">' . number_format($lastTrx->amount) . ' pts</span>
						<span class="text-xs text-gray-400">' . $lastTrx->created_at->timezone($zones['zone'])->format('d M Y, H:i') . '</span>
				   </div>'
					: '<span class="text-xs text-gray-400">No Transaction</span>';

				$row->status_label = DatatableHelper::phaseColor($row->status);

				$row->created_at_formatted = $row->created_at
					? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_merigo_wallet_toggle', 'backend_merigo_wallet_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Throwable $e) {

			return DatatableHelper::handleDatatableError($request, $e, true, "Merigo Wallet Datatable Error");
		}
	}

	// -------------------- WALLET TRANSACTIONS --------------------
	public function walletTrxIndex()
	{
		return view('Admin.sidebar.Wallets.transactions');
	}

	public function toggleTrx(PointsTransaction $singleTrx)
	{
		try {
			$singleTrx->update([
				'status' => !$singleTrx->status,
			]);

			return response()->json(['success' => true]);
		} catch (Throwable $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function deleteTrx(PointsTransaction $singleTrx)
	{
		try {
			$name = $singleTrx->transaction_uid;
			$singleTrx->delete();

			return redirect()->back()->with('success', "Wallet transactions '{$name}' has been deleted successfully.");
		} catch (Throwable $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function bulkDeleteTrx(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:wallet_point_transactions,id',
		]);

		$ids = $request->ids;
		$walletTrx = PointsTransaction::whereIn('id', $ids)->get();

		if ($walletTrx->isEmpty()) {
			return back()->with('error', 'No valid wallet transactions selected.');
		}

		$MESSAGE = "Selected wallet transactions are deleted.";
		$deleted = [];

		DB::beginTransaction();
		try {
			foreach ($walletTrx as $trx) {
				$deleted[] = $trx->transaction_uid;
				/** @var PointsTransaction $trx */
				$trx->delete();
			}
			DB::commit();

			$MESSAGE = "Selected wallet transactions [" . count($deleted) . "] have been deleted successfully.";

			return redirect()->back()->with('success', $MESSAGE);
		} catch (Throwable $e) {
			DB::rollBack();
			Log::error('Wallet transactions bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

			return back()->with('error', 'Bulk delete failed. No changes were made.');
		}
	}

	public function walletTrxDatatable(Request $request)
	{
		try {
			$columns = [null, null, 'id', 'transaction_uid', null, null, 'transaction_type', 'amount', 'phase', 'note', 'status', 'created_at', null];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);

			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'created_at';
			$orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

			$query = PointsTransaction::with([
				'walletMorphed',
				'walletMorphed' => function ($morphTo) {
					$morphTo->morphWith([
						MerigoWallet::class => ['walletRelatingBackTo_User'],
						BarWallet::class => ['walletRelatingBackTo_User'],
					]);
				},
			])->select(['id', 'transaction_uid', 'wallet_morphed_id', 'wallet_morphed_type', 'user_id', 'transaction_type', 'amount', 'phase', 'note', 'status', 'created_at'])->orderBy('created_at', 'desc');

			if ($search = $request->input('search.value')) {

				$query->where(function ($q) use ($search) {

					$q->where('transaction_uid', 'like', "%{$search}%")
						->orWhere('transaction_type', 'like', "%{$search}%")
						->orWhere('phase', 'like', "%{$search}%")
						->orWhere('note', 'like', "%{$search}%")

						->orWhereHasMorph(
							'walletMorphed',
							['App\Models\Billings\Wallets\BarWallet', 'App\Models\Billings\Wallets\MerigoWallet'],
							function ($mq) use ($search) {
								$mq->whereHas('walletRelatingBackTo_User', function ($uq) use ($search) {
									$uq->where('name', 'like', "%{$search}%")
										->orWhere('email', 'like', "%{$search}%")
										->orWhere('user_uid', 'like', "%{$search}%");
								});
							}
						);
				});
			}

			if ($orderCol) {
				$query->orderBy($orderCol, $orderDir);
			}

			$recordsTotal = PointsTransaction::count();
			$recordsFiltered = $query->count();

			$data = $query->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;
				$row->uid = '<span class="text-xs text-wrap text-gray-600">' . $row->transaction_uid . '</span>';


				$wallet = $row->walletMorphed;
				$user = $wallet ? $wallet->walletRelatingBackTo_User : null;
				
				$row->user_block = $user ? DatatableHelper::userBlock($user) : '<span class="text-xs text-gray-400">No User</span>';

				$walletType = class_basename($row->wallet_morphed_type);

				$row->wallet_block = '
					<div class="flex flex-col text-center">
						<span class="font-semibold text-gray-700">' . e($walletType) . '</span>
						<span class="text-xs text-indigo-500">Balance: ' . number_format($wallet?->balance ?? 0) . '</span>
					</div>
				';

				$row->transaction_type_label = '<span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-700">' . e($row->transaction_type?->value ?? 'N/A') . '</span>';

				$row->amount_label = '
					<span class="font-bold ' . (($row->amount ?? 0) >= 0 ? 'text-green-600' : 'text-red-500') . '">
						' . number_format($row->amount ?? 0) . ' pts
					</span>
				';

				$row->phase_label = DatatableHelper::phaseColor($row->phase);

				$row->status_label = DatatableHelper::phaseColor($row->status);

				$row->created_at_formatted = $row->created_at
					? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_wallet_trx_toggle', 'backend_wallet_trx_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Throwable $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "Wallet Transaction Datatable Error");
		}
	}
}