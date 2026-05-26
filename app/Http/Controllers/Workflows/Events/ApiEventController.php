<?php

namespace App\Http\Controllers\Workflows\Events;

use App\Domain\Bars\Actions\EventSaveAction;
use App\Helpers\{UidGenerator, ApiJsonReturnHelper, FileHelpers\FileManagement, FileHelpers\GalleryManagement};
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\EventValidateRequest;
use App\Models\Workflows\Bar\{Bar, Deal, Event, EventGallery};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Illuminate\Support\Carbon;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;

class ApiEventController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware('verify_role_key:admin,bar_admin', only: ['myEventsAdmin']),
            new Middleware(PermissionMiddleware::using(['event_index']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['event_create', 'event_update']), only: ['store']),
        ];
    }

    // ------------------------------------------

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'nullable|date'
            ]);

            $queryDate = !empty($validated['date']) ? strtolower(Carbon::createFromFormat('d-m-Y', $validated['date'])->format('l')) : null;
            $today = strtolower(now()->format('l'));
            if($queryDate)
                $selectedDay = $queryDate;
            else
                $selectedDay = $today;

            $myEvents = Event::with([
                'eventRelatingBackTo_Bar' => function ($q) {
                    $q->with([
                        'barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status'
                    ]);
                },
                'eventRelationWith_Deals',
                'eventRelationWith_Gallery:id,event_id,filename,filepath,status'
            ])->status(1)->eventDay($selectedDay)->paginate(10);

            $myEvents->transform(function ($item) {
                $item->bar_details = $item->eventRelatingBackTo_Bar;
                $item->bar_admin_details = $item->bar_details->barRelatingBackTo_User?->only(['id', 'user_uid', 'username', 'user_role', 'name', 'email', 'phone', 'avatar', 'city', 'status']);
                $item->deals_details = $item->eventRelationWith_Deals;
                $item->event_gallery = $item->eventRelationWith_Gallery;

                $item->unsetRelation('eventRelatingBackTo_Bar');
                $item->bar_details->unsetRelation('barRelatingBackTo_User');
                $item->unsetRelation('eventRelationWith_Deals');
                $item->unsetRelation('eventRelationWith_Gallery');

                return $item;
            });

            return ApiJsonReturnHelper::handle(true, 200, 'All my events have been fetched successfully!', $myEvents);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching events', $e);
        }
    }
    // ------------------------------------------

    public function myEventsAdmin(Request $request)
    {
        try {
            $USER = $request->user();

            $myEvents = Event::with([
                'eventRelatingBackTo_Bar' => function ($q) {
                    $q->with([
                        'barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status'
                    ]);
                },
                'eventRelationWith_Deals',
                'eventRelationWith_Gallery:id,event_id,filename,filepath,status'
            ])
                ->whereHas('eventRelatingBackTo_Bar', fn($q) => $q->where('bar_admin_id', $USER->id))
                ->get();

            $myEvents->transform(function ($item) {
                $item->bar_details = $item->eventRelatingBackTo_Bar;
                $item->bar_admin_details = $item->bar_details->barRelatingBackTo_User?->only(['id', 'user_uid', 'username', 'user_role', 'name', 'email', 'phone', 'avatar', 'city', 'status']);
                $item->deals_details = $item->eventRelationWith_Deals;
                $item->event_gallery = $item->eventRelationWith_Gallery;

                $item->unsetRelation('eventRelatingBackTo_Bar');
                $item->bar_details->unsetRelation('barRelatingBackTo_User');
                $item->unsetRelation('eventRelationWith_Deals');
                $item->unsetRelation('eventRelationWith_Gallery');

                return $item;
            });

            return ApiJsonReturnHelper::handle(true, 200, "All my events have been fetched successfully!", $myEvents);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching all my events', $e);
        }
    }
    // ------------------------------------------

    public function show(Event $event)
    {
        try {
            $event->load([
                'eventRelatingBackTo_Bar.barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status',
                'eventRelationWith_Deals',
                'eventRelationWith_Gallery:id,event_id,filename,filepath,status'
            ]);

            if ($event->status != 1) {
                return ApiJsonReturnHelper::handle(false, 404, "Event not found!", []);
            }

            $event->bar_details = $event->eventRelatingBackTo_Bar;
            $event->bar_admin_details = $event->eventRelatingBackTo_Bar?->barRelatingBackTo_User?->only(['id', 'user_uid', 'username', 'user_role', 'name', 'email', 'phone', 'avatar', 'city', 'status', 'avatar_url']);

            $event->deal_details = $event->eventRelationWith_Deals;
            $event->event_gallery = $event->eventRelationWith_Gallery;

            unset($event->bar_details->barRelatingBackTo_User);
            unset($event->eventRelatingBackTo_Bar);
            unset($event->eventRelationWith_Deals);
            unset($event->eventRelationWith_Gallery);

            return ApiJsonReturnHelper::handle(true, 200, "An event detail has been fetched successfully!", $event);

        } catch (Throwable $e) {

            return ExceptionHandling::handle('fetching an event details', $e);
        }
    }
    // ------------------------------------------

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
                $this->authorize('event_update');
                $existingEvent = Event::findOrFail((int) $validated['id']);
                $EVENT_UID = $existingEvent->event_uid;

            } else {
                $this->authorize('event_create');
                $EVENT_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
                $isNewItem = true;
            }

            $targetBar = Bar::findOrFail((int) $validated['bar_id']);
            OwnershipAuthCheck::ownerVsOwner($targetBar->bar_admin_id, $USER->id);
            // ------------ CHECKING EXISTANCE + UID ------------

            $event = $action->execute($validated, $EVENT_UID, $existingEvent, $USER, $isNewItem);

            $IMAGE_FOLDER = 'Events/' . $event->event_uid;
            $FILE_NAME = "EVENT-GALLERY-" . ($event?->event_uid);
            $gallery = GalleryManagement::handle($validated, EventGallery::class, $event, 'event_id', $IMAGE_FOLDER, $FILE_NAME);


            return ApiJsonReturnHelper::handle(true, 200, "An event '{$event->name}' is saved successfully!", [
                'event_details' => $event,
                'gallery_details' => $gallery
            ]);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('saving an event', $e);
        }
    }

    public function delete(Request $request, Event $event)
    {
        $imageFolder = null;
        try {
            $USER = $request->user();
            $name = $event?->name ?? $event?->event_uid ?? null;

            OwnershipAuthCheck::ownerInList([$event?->creator_id, $event?->eventRelatingBackTo_Bar?->barRelatingBackTo_User?->id], $USER->id, "delete method");

            if ($event?->eventRelationWith_Gallery()?->first()) {
                $DISK_FOLDER = config('filesystems.default');
                $imageFolder = dirname($event?->eventRelationWith_Gallery()?->first()?->filepath);
                FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
            }

            $event->delete();

            return ApiJsonReturnHelper::handle(true, 200, 'Bar deleted successfully!', null);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('deleting bar', $e);
        }
    }
}
