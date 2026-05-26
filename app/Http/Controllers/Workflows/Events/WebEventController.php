<?php

namespace App\Http\Controllers\Workflows\Events;

use App\Domain\Bars\Actions\BarSaveAction;
use App\Domain\Bars\Actions\EventSaveAction;
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Helpers\FileHelpers\GalleryManagement;
use App\Helpers\Ui\DatatableHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\BarSaveRequest;
use App\Http\Requests\Workflow\EventValidateRequest;
use App\Models\Users\User;
use App\Models\Workflows\Bar\Bar;
use App\Models\Workflows\Bar\EventGallery;
use Illuminate\Support\{Str, Carbon};
use App\Helpers\{FileHelpers\FileManagement, IconPack, Notifications\PushNotificationHelper, UidGenerator};
use Illuminate\Support\Facades\{Log, Storage, DB};
use Illuminate\Http\Request;
use App\Models\Workflows\Bar\Event;
use Exception;
use Throwable;

class WebEventController extends Controller
{
	public function create()
	{
		$bars = Bar::status(1)->get();
		return view('Admin.sidebar.Events.create', compact('bars'));
	}

	public function index()
	{
		return view('Admin.sidebar.Events.index');
	}

	public function show(Event $event)
	{
		$bars = Bar::status(1)->get();
		$galleries = EventGallery::eventId($event?->id)->status(1)->get();

		return view('Admin.sidebar.Events.show', compact('bars', 'event', 'galleries'));
	}

	public function store(EventValidateRequest $request, EventSaveAction $action)
	{
		try {
			$validated = $request->validated();
			// dd($validated);
			$USER = $request->user();

			$EVENT_UID = null;
			$existingEvent = null;
			$isNewItem = false;

			// ------------ CHECKING EXISTANCE + UID ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingEvent = Event::findOrFail((int)$validated['id']);
				$EVENT_UID = $existingEvent->event_uid;

			} else {
				$EVENT_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
				$isNewItem = true;
			}

			$targetBar = Bar::findOrFail((int)$validated['bar_id']);
			// ------------ CHECKING EXISTANCE + UID ------------

			$event = $action->execute($validated, $EVENT_UID, $existingEvent, $USER, $isNewItem);

			$IMAGE_FOLDER = 'Events/' . $event->event_uid;
			$FILE_NAME = "EVENT-GALLERY-" . ($event?->event_uid);
			$gallery = GalleryManagement::handle($validated, EventGallery::class, $event, 'event_id', $IMAGE_FOLDER, $FILE_NAME);

			return redirect()->back()->with('success', "Event '{$event->name}' is saved successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}


	public function toggle(Event $event)
	{
		try {
			$event->update([
				'status' => !$event->status,
			]);

			return response()->json(['success' => true]);
		} catch (Exception $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function destroy(Event $event)
	{
		try {
			$name = $event->name ?? $event->event_uid;
			$DISK_FOLDER = config('filesystems.default');

			if ($event->eventRelationWith_Gallery()->exists()) {
				$imageFolder = 'Events/' . $event->event_uid;
				FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
			}

			$event->delete();

			return redirect()->back()->with('success', "Event '{$name}' has been deleted successfully.");
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

	public function eventDatatable(Request $request)
	{
		try {
			$columns = [null, null, 'id', 'event_uid', 'name', 'bar_id', null, 'event_day', 'points_giveaway', 'creator_id', 'status', 'created_at', null];


			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'created_at';

			$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

			$query = Event::select(['id', 'event_uid', 'bar_id', 'creator_id', 'name', 'description', 'event_day', 'points_giveaway', 'expiry', 'status', 'created_at'])
				->with([
					'eventRelatingBackTo_Bar:id,bar_uid,name,city,image',
					'eventRelationWith_Gallery' => function ($q) {
						$q->where('status', 1);
					},
					'eventRelatingBackTo_Creator:id,user_uid,name,email,avatar'
				]);

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
						->orWhere('event_uid', 'like', "%{$search}%")
						->orWhere('description', 'like', "%{$search}%")
						->orWhere('event_day', 'like', "%{$search}%")
						->orWhereHas('eventRelatingBackTo_Bar', function ($q) use ($search) {
							$q->where('name', 'like', "%{$search}%")
								->orWhere('address', 'like', "%{$search}%")
								->orWhere('city', 'like', "%{$search}%")
								->orWhere('bar_uid', 'like', "%{$search}%")
								->orWhereHas('barRelatingBackTo_User', function ($uq) use ($search) {
									$uq->where('name', 'like', "%{$search}%")
										->orWhere('email', 'like', "%{$search}%");
								});
						})
						->orWhereHas('eventRelatingBackTo_Creator', function ($q) use ($search) {
							$q->where('name', 'like', "%{$search}%")
								->orWhere('user_uid', 'like', "%{$search}%")
								->orWhere('username', 'like', "%{$search}%")
								->orWhere('email', 'like', "%{$search}%");
						});
				});
			}

			if ($orderCol) {
				$query->orderBy($orderCol, $orderDir);
			}

			$recordsTotal = Event::count();
			$recordsFiltered = $query->count();

			// $data = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();
			$data = $query->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$data->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;
				$row->view_url = route('backend_event_show', $row->id);

				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;

				// --------- Event ---------
				$row->event_details = '
					<div class="flex flex-col text-left">
						<span class="font-semibold text-center text-gray-800">' . e($row->name) . '</span>
						<span class="text-xs text-gray-400 text-center">' . e(Str::limit($row->description, 40)) . '</span>
					</div>
				';

				// --------- Bar ---------
				$row->bar_details = DatatableHelper::detailBlock($row->eventRelatingBackTo_Bar, true, $row->eventRelatingBackTo_Bar?->bar_uid, true, $row->eventRelatingBackTo_Bar?->image);

				// --------- Galleries ---------
				$row->galleries = DatatableHelper::galleryBlock($row->eventRelationWith_Gallery, 3);

				// --------- Schedule ---------
				$row->schedule = '
					<div class="flex flex-col text-center">
						<span class="font-medium text-gray-700">' . e(ucfirst($row->event_day ?? 'N/A')) . '</span>
						<span class="text-xs text-gray-400">Expiry: ' . ($row->expiry ? Carbon::parse($row->expiry)->format('d M Y') : 'No expiry') . '</span>
					</div>
				';

				// --------- Points ---------
				$row->points = '<span class="font-bold text-indigo-600">+' . number_format($row->points_giveaway) . ' pts</span>';

				// --------- Creator ---------
				$row->creator = DatatableHelper::userBlock($row->eventRelatingBackTo_Creator);

				// --------- Status ---------
				$row->status_label = DatatableHelper::phaseColor($row->status);

				// --------- Created date ---------
				$row->created_at_formatted = $row->created_at
					? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
					: 'N/A';

				// --------- Actions ---------
				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_event_toggle', 'backend_event_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

		} catch (Throwable $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "Events Datatable Error");
		}
	}
}
