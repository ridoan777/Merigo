<?php

namespace App\Http\Controllers\Workflows\Bars;

use App\Domain\Bars\Actions\BarSaveAction;
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Helpers\Ui\DatatableHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\BarSaveRequest;
use App\Models\Users\User;
use App\Models\Workflows\Bar\Bar;
use App\Models\Workflows\Bar\Deal;
use App\Models\Workflows\Bar\Event;
use Illuminate\Support\{Str, Carbon};
use App\Helpers\{FileHelpers\FileManagement, IconPack, Notifications\PushNotificationHelper, UidGenerator};
use Illuminate\Support\Facades\{Log, Storage, DB};
use Illuminate\Http\Request;
use Exception;
use Throwable;

class WebBarController extends Controller
{
	public function create()
	{
		$users = User::whereHas('roles', fn($q) => $q->where('role_key', 'bar_admin'))->get();
		return view('Admin.sidebar.Bars.create', compact('users'));
	}

	public function index()
	{
		return view('Admin.sidebar.Bars.index');
	}

	public function show(Bar $bar)
	{
		$users = User::where('user_role', 'bar_admin')->status(1)->get();
		$events = Event::with('eventRelatingBackTo_Bar', 'eventRelationWith_Gallery', 'eventRelationWith_Deals')->barId($bar->id)->get();
		$deals = Deal::barId($bar->id)->get();

		return view('Admin.sidebar.Bars.show', compact('bar', 'users', 'events', 'deals'));
	}

	public function store(BarSaveRequest $request, BarSaveAction $action)
	{
		try {
			$validated = $request->validated();
			$USER = $request->user();

			$BAR_UID = null;

			// ------------ CHECKING EXISTANCE + UID ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingBar = Bar::find($validated['id']);
				$BAR_UID = $existingBar->bar_uid;
			} else {
				$BAR_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
			}
			// ------------ CHECKING EXISTANCE + UID ------------

			$bar = $action->execute($request, $validated, $existingBar, $BAR_UID, $USER);

			return redirect()->back()->with('success', "Bar '{$bar->name}' is saved successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}

	public function toggle(Bar $bar)
	{
		try {
			$bar->update([
				'status' => !$bar->status,
			]);

			return response()->json(['success' => true]);
		} catch (Exception $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function destroy(Bar $bar)
	{
		$name = null;
		try {
			$name = $bar?->name ?? null;
			$DISK_FOLDER = config('filesystems.default');

			if ($bar?->image) {
				FileManagement::deleteFile($bar->image, $DISK_FOLDER);
			}

			$bar->delete();

			return redirect()->back()->with('success', "Bar '{$name}' has been deleted successfully.");
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function bulkDelete(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:bars,id',
		]);

		$ids = $request->ids;

		$bars = Bar::whereIn('id', $ids)->get();

		if ($bars->isEmpty()) {
			return back()->with('error', 'No valid bars selected.');
		}

		$MESSAGE = "Selected bars are deleted.";
		$DISK_FOLDER = config('filesystems.default');
		$SKIPPED = [];
		$deleted = [];

		DB::beginTransaction();

		try {
			foreach ($bars as $bar) {

				if (strtolower($bar->phase) === 'disputed') {
					$SKIPPED[] = $bar->title;
					continue;
				}

				$imageFolder = dirname($bar?->image);
				if ($imageFolder) {
					FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
				}
				$bar->delete();

				$deleted[] = $bar->name;
			}

			DB::commit();

			$MESSAGE = $MESSAGE . "success: [" . count($deleted) . "], dispute-skipped: [" . count($SKIPPED) . "].";

			return redirect()->back()->with('success', $MESSAGE);
		} catch (Throwable $e) {
			DB::rollBack();

			Log::error('Project bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

			return back()->with('error', 'Bulk delete failed. No changes were made.');
		}
	}

	public function barDatatable(Request $request)
	{
		try {
			$columns = ['id', 'bar_uid', 'bar_admin_id', 'name', 'earning_points', 'contact', 'address', 'city', 'image', 'status', 'created_at'];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

			$query = Bar::select(['id', 'bar_uid', 'bar_admin_id', 'name', 'earning_points', 'cd_time', 'contact', 'address', 'city', 'image', 'status', 'created_at'])
				->with('barRelationWith_Deal')
				->with('barRelatingBackTo_User:id,name,user_uid,email,avatar');

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
						->orWhere('contact', 'like', "%{$search}%")
						->orWhere('earning_points', 'like', "%{$search}%")
						->orWhere('address', 'like', "%{$search}%")
						->orWhere('city', 'like', "%{$search}%")
						->orWhereHas('barRelatingBackTo_User', function ($q) use ($search) {
							$q->where(function ($q) use ($search) {
								$q->where('name', 'like', "%{$search}%")
									->orWhere('user_uid', 'like', "%{$search}%")
									->orWhere('email', 'like', "%{$search}%")
									->orWhere('username', 'like', "%{$search}%");
							});
						});
				});
			}

			$recordsTotal = Bar::count();
			$recordsFiltered = $query->count();

			$data = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;
				$row->view_url = route('backend_bar_show', $row->id);

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;
				$row->bar_id = $row->id;

				// --------- Points ---------
				$row->earning_point = '
					<p class="px-2 py-1 bg-blue-300 dark:text-black rounded-lg"> +' . $row->earning_points . '</p>
					<p class="mt-2 py-1 bg-rose-300 text-2xs dark:text-black rounded-lg">CD: ' . $row->cd_time . ' hours</p>
				';

				// --------- Manager ---------
				$row->manager = DatatableHelper::userBlock($row->barRelatingBackTo_User);

				// --------- Address ---------
				$row->address_formatted = $row->address . ', ' . $row->city;

				// --------- Address ---------
				$row->deals = $row?->barRelationWith_Deal?->count() ?? 0;

				// --------- Status ---------
				$row->status_label = DatatableHelper::phaseColor($row->status);

				// --------- Created date ---------
				$row->created_at_formatted = $row->created_at
					? Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				// --------- Image ---------
				$row->image_preview = $row->image
					? '<img src="' . $row->image_url . '" class="w-14 h-14 rounded object-cover mx-auto" />'
					: '<span class="text-gray-400 italic">No image</span>';

				// --------- Actions ---------
				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_bar_toggle', 'backend_bar_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Exception $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "Bars Datatable Error");
		}
	}
}
