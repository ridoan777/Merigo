<?php

namespace App\Http\Controllers\Workflows\Ads;

use App\Domain\Ads\Actions\AdSaveAction;
use App\Helpers\Ui\DatatableHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\AdValidateRequest;
use App\Models\Workflows\Ads\Ad;
use App\Models\Workflows\Bar\Bar;
use App\Models\Workflows\Bar\EventGallery;
use Illuminate\Support\{Str, Carbon};
use App\Helpers\{FileHelpers\FileManagement, IconPack, Notifications\PushNotificationHelper, UidGenerator};
use Illuminate\Support\Facades\{Log, Storage, DB};
use Illuminate\Http\Request;
use App\Models\Workflows\Bar\Event;
use Exception;
use Throwable;

class WebAdsController extends Controller
{
	public function index()
	{
		return view('Admin.sidebar.Ads.index');
	}

	public function create()
	{
		return view('Admin.sidebar.Ads.create');
	}

	public function show(Ad $singleAd)
	{
		return view('Admin.sidebar.Ads.show', compact('singleAd'));
	}

	public function store(AdValidateRequest $request, AdSaveAction $action)
	{
		try {
			$validated = $request->validated();
			$AD_UID = null;
			$existingAd = null;

			// ------------ CHECKING EXISTANCE + UID ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingAd = Ad::findOrFail((int)$validated['id']);
				$AD_UID = $existingAd->ad_uid;
			} else {
				$AD_UID = UidGenerator::uniqueULID($validated['title'], 8, 14, 4);
			}
			// ------------ CHECKING EXISTANCE + UID ------------

			$ad = $action->execute($validated, $AD_UID, $existingAd);

			return redirect()->back()->with('success', "Ad '{$ad->title}' is saved successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}

	public function toggle(Ad $singleAd)
	{
		try {
			$singleAd->update([
				'status' => !$singleAd->status,
			]);

			return response()->json(['success' => true]);
		} catch (Exception $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function delete(Ad $singleAd)
	{
		try {
			$name = $singleAd->title ?? $singleAd->ad_uid;
			$DISK_FOLDER = config('filesystems.default');

			if ($singleAd->image) {
				$imageFolder = 'Ads/' . $singleAd->ad_uid;
				FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
			}

			$singleAd->delete();

			return redirect()->back()->with('success', "Ad '{$name}' has been deleted successfully.");
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function bulkDelete(Request $request)
	{
		$request->validate([
			'ids' => 'required|array',
			'ids.*' => 'integer|exists:bar_events,id',
		]);

		$ids = $request->ids;
		$events = Event::whereIn('id', $ids)->get();

		if ($events->isEmpty()) {
			return back()->with('error', 'No valid events selected.');
		}

		$MESSAGE = "Selected events are deleted.";
		$DISK_FOLDER = config('filesystems.default');
		$deleted = [];

		DB::beginTransaction();

		try {
			foreach ($events as $event) {
				if ($event instanceof Event && $event->eventRelationWith_Gallery()->exists()) {
					$imageFolder = 'Events/' . $event->event_uid;
					FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
				}
				$event->delete();
				$deleted[] = $event->name ?? $event->event_uid;
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

	public function adsDatatable(Request $request) 
	{
		try {
			$columns = [null, null, 'id', 'ad_uid', 'title', 'description', null, 'schedule_day', 'status', 'created_at', null];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);

			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'created_at';

			$orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

			$query = Ad::select(['id', 'ad_uid', 'title', 'description', 'ad_link', 'image', 'schedule_day', 'status', 'created_at']);

			if ($search = $request->input('search.value')) {

				$query->where(function ($q) use ($search) {

					$q->where('ad_uid', 'like', "%{$search}%")
						->orWhere('title', 'like', "%{$search}%")
						->orWhere('description', 'like', "%{$search}%")
						->orWhere('schedule_day', 'like', "%{$search}%")
						->orWhere('ad_link', 'like', "%{$search}%");
				});
			}

			if ($orderCol) {
				$query->orderBy($orderCol, $orderDir);
			}

			$recordsTotal = Ad::count();
			$recordsFiltered = $query->count();

			$data = $query->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;
				$row->view_url = route('backend_ads_show', $row->id);

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;

				$row->title_block = '
					<div class="flex flex-col text-left">
						<span class="font-semibold text-gray-800">' . e($row->title) . '</span>
						<span class="text-xs text-indigo-500 break-all">' . e($row->ad_link ?? 'No Link') . '</span>
					</div>
				';

				$row->description_block = '
					<div class="text-sm text-gray-600 leading-relaxed">
						' . e(Str::limit(strip_tags($row->description), 80)) . '
					</div>
				';

				$row->image_block = $row->image
					? '<img src="' . e($row->image_url) . '" class="w-16 h-16 object-cover rounded-lg border mx-auto">'
					: '<span class="text-xs text-gray-400">No Image</span>';

				$row->schedule_block = '
					<div class="flex flex-col text-center">
						<span class="font-medium text-gray-700">' . e(ucfirst($row->schedule_day ?? 'N/A')) . '</span>
					</div>
				';

				$row->status_label = DatatableHelper::phaseColor($row->status);

				$row->created_at_formatted = $row->created_at
					? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_ads_toggle', 'backend_ads_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Throwable $e) {

			return DatatableHelper::handleDatatableError($request, $e, true, "Ads Datatable Error");
		}
	}
}
