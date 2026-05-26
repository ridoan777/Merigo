<?php

namespace App\Http\Controllers\Communication;

use App\Events\MessageSendEvent;
use App\Helpers\Communications\BlocklistHelper;
use App\Helpers\{FileHelpers\FileManagement, IconPack, UidGenerator};
use App\Http\Controllers\Controller;
use App\Models\Communication\Chatting\{ChatBlocklist, ChatGallery, ChatMessage};
use App\Models\System\Dispute_Management\DisputeReport;
use App\Models\Users\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, Log};
use Illuminate\Support\Str;
use Throwable;

class WebChatController extends Controller
{
	// --------------------- MESSENGER ---------------------
	public function messengerIndex(Request $request)
	{
		try {
			$USER = $request->user();
			$receiver = null;
			$messages = null;

			$allUsers = User::status(1)
				->where('id', '!=', $USER->id)
				->withMax(
					[
						'chatMessages as last_message_id' => function ($q) use ($USER) {
							$q->where('sender_id', $USER->id)
								->orWhere('receiver_id', $USER->id);
						}
					],
					'id'
				)
				->orderByDesc('last_message_id')
				->get();

			$allUsers->each(function ($u) use ($USER) {
				$u->setRelation('userRelationWith_lastChatMessage', $u->lastChatMessageWithUser($USER->id)->first());
			});

			if ($request->filled('receiver_id')) {
				$receiverId = (int)$request->receiver_id;

				$receiver = User::status(1)->where('id', $receiverId)->firstOrFail();

				$messages = ChatMessage::with([
					'chatSenderRelationWith_User',
					'chatReceiverRelationWith_User',
					'chatMessageRelationWith_Gallery' => function ($q) {
						$q->whereNull('chat_group_id');
					},
				])
					->whereNull('chat_group_id')
					->where(function ($q) use ($USER, $receiverId) {
						$q->where(function ($q2) use ($USER, $receiverId) {
							$q2->where('sender_id', $USER->id)->where('receiver_id', $receiverId);
						})->orWhere(function ($q2) use ($USER, $receiverId) {
							$q2->where('sender_id', $receiverId)->where('receiver_id', $USER->id);
						});
					})
					->orderBy('id')
					->get();
			}

			$currentUser = $USER;

			return view('Admin.sidebar.Communications.Chatting.messenger', compact('allUsers', 'currentUser', 'receiver', 'messages'));
		} catch (Throwable $e) {
			Log::error('message index failed', ['error' => $e->getMessage()]);
			return view('Admin.sidebar.Communications.Chatting.messenger')->with('error', 'Action error: ' . $e->getMessage());
		}
	}

	public function send(Request $request)
	{
		try {
			$SENDER = $request->user();
			$RECEIVER = User::find($request->receiver_id);

			// ------ BLOCK CHECK ------
			if (BlocklistHelper::checkBlockedUser($SENDER->id, $request->receiver_id)) {
				return response()->json([
					'status' => false,
					'message' => 'You can no longer message in this conversation.',
				], 401);
			}
			// ------------ VALIDATION ------------
			// Log::info($request->all());

			$validated = $request->validate([
				'receiver_id' => 'required|integer|exists:users,id',
				'message' => 'nullable|string|required_without:file',
				'file' => 'nullable|array|required_without:message',
				'file.*' => 'file|max:102400',
			]);


			if (!$RECEIVER) {
				return response()->json([
					'status' => false,
					'message' => 'Receiver not found.',
				], 404);
			}

			// ------------ CREATE MESSAGE ------------
			$sentMessage = ChatMessage::create([
				'sender_id' => $SENDER->id,
				'receiver_id' => $validated['receiver_id'],
				'text' => $validated['message'] ?? null,
			]);

			// ------------ FILE HANDLING ------------
			$files = $request->file('file');
			if ($files) {
				$files = is_array($files) ? $files : [$files];

				foreach ($files as $uploadedFile) {
					$fileHandled = FileManagement::handleFile(
						$uploadedFile,
						"CHAT_{$sentMessage->id}",
						null,
						"Communications/MessengerChats/{$SENDER->id}",
						config('filesystems.default'),
						false
					);

					ChatGallery::create([
						'message_id' => $sentMessage->id,
						'sender_id' => $SENDER->id,
						'receiver_id' => $validated['receiver_id'],
						'file' => $fileHandled['path'],
						'metadata' => $fileHandled,
						'status' => 1,
					]);
				}
			}

			// ------------ LOAD EVERYTHING FOR UI ------------
			$sentMessage->load([
				'chatSenderRelationWith_User',
				'chatReceiverRelationWith_User',
				'chatMessageRelationWith_Gallery',
			]);

			broadcast(new MessageSendEvent($sentMessage));

			return response()->json([
				'status' => true,
				'data' => $sentMessage,
			]);
		} catch (Throwable $e) {
			Log::error('message sending failed', ['error' => $e->getMessage()]);
			return response()->json([
				'status' => false,
				'error' => $e->getMessage(),
			], 500);
		}
	}

	public function deleteMyMessage(Request $request, ChatMessage $message)
	{
		try {
			$name = $message?->chatSenderRelationWith_user?->name ?? null;
			$userId = $request->user()->id;

			$DISK_FOLDER = config('filesystems.default');

			// ------------ AUTH CHECK ------------
			if ($message->sender_id !== $userId) {
				abort(403);
			}
			// ------------ AUTH CHECK ------------

			foreach ($message->chatMessageRelationWith_Gallery as $gallery) {
				if (!empty($gallery->file)) {
					FileManagement::deleteFile($gallery->file, $DISK_FOLDER);
				}
				$gallery->delete();
			}

			$message->delete();

			return back()->with('success', "A message of '{$name}' is deleted successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	// --------------------- MESSENGER ---------------------

	public function blockIndex()
	{
		return view('Admin.sidebar.Communications.Chatting.blocklist_index');
	}

	public function blockToggle(ChatBlocklist $block)
	{
		try {
			$block->update([
				'status' => !$block->status,
			]);

			return response()->json(['success' => true]);
		} catch (Exception $e) {
			return response()->json(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	public function blockDelete(ChatBlocklist $block)
	{
		$name = null;
		try {
			$victim = $block->victimRelationWith_User->name ?? null;
			$culprit = $block->blockedRelationWith_User->name ?? null;

			$block->delete();

			return redirect()->back()->with('success', "Chat blockage between '{$victim}' & '{$culprit}' has been deleted successfully. Now they can send messages again.");
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}

	public function blocklistDatatable(Request $request)
	{
		try {
			$columns = [
				'id',
				'victim_id',
				'blocked_id',
				'composite_a',
				'composite_b',
				'reason',
				'is_blocked',
				'status',
				'created_at',
			];

			$draw = intval($request->input('draw'));
			$start = intval($request->input('start', 0));
			$length = intval($request->input('length', 25));
			$orderColIndex = intval($request->input('order.0.column', 0));
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = in_array(strtolower($request->input('order.0.dir')), ['asc', 'desc'])
				? $request->input('order.0.dir')
				: 'asc';

			$query = ChatBlocklist::with([
				'victimRelationWith_User',
				'blockedRelationWith_User',
			])->select([
						'id',
						'victim_id',
						'blocked_id',
						'composite_a',
						'composite_b',
						'reason',
						'is_blocked',
						'status',
						'created_at',
					]);

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q
						->where('composite_a', 'like', "%{$search}%")
						->orWhere('composite_b', 'like', "%{$search}%")
						->orWhere('reason', 'like', "%{$search}%");
				});
			}

			$recordsTotal = ChatBlocklist::count();
			$recordsFiltered = $query->count();

			$data = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

			$sn = $start;

			$data->transform(function ($row) use (&$sn) {
				$row->DT_RowId = 'row_' . $row->id;
				// $row->view_url = route('backend_chat_blocklist_index', $row->id);
				$row->SN = ++$sn;

				// Victim (Blocker)
				$victim = $row->victimRelationWith_User;
				$victimAvatar = $victim->avatar ?? null;

				$row->victim = '
					<div class="flex items-center space-x-3">
						<img src="' . ($victimAvatar
					? Storage::url($victimAvatar)
					: Storage::url('site_assets/dummies/dummy_man.webp')) . '"
							class="w-10 h-10 rounded-full object-cover" />
						<div>
							<div class="text-xs text-gray-400">DB ID: ' . $victim->id . '</div>
							<div class="text-sm font-medium">' . e($victim->name) . '</div>
							<div class="text-xs text-gray-400">' . e($victim->email) . '</div>
						</div>
					</div>
				';

				// Blocked (Accused)
				$blocked = $row->blockedRelationWith_User;
				$blockedAvatar = $blocked->avatar ?? null;

				$row->blocked = '
					<div class="flex items-center space-x-3">
						<img src="' . ($blockedAvatar
					? Storage::url($blockedAvatar)
					: asset('site_assets/dummies/dummy_man.webp')) . '"
							class="w-10 h-10 rounded-full object-cover" />
						<div>
							<div class="text-xs text-gray-400">DB ID: ' . $blocked->id . '</div>
							<div class="text-sm font-medium">' . e($blocked->name) . '</div>
							<div class="text-xs text-gray-400">' . e($blocked->email) . '</div>
						</div>
					</div>
				';

				// Composites
				$row->composites = '
					<div class="text-xs">
						<span>[ ' . e($row->composite_a) . ' ],</span>
						<span>[ ' . e($row->composite_b) . ' ]</span>
					</div>
				';

				// Phase (Blocked / Unblocked)
				$row->phase = $row->is_blocked
					? '<span class="px-3 py-1 rounded-lg bg-red-400 bg-opacity-25 text-xs">Blocked</span>'
					: '<span class="px-3 py-1 rounded-lg bg-cyan-400 bg-opacity-25 text-xs">Unblocked</span>';

				$row->reason = $row->reason ?? '<span class="italic text-gray-400">—</span>';

				$row->status_label = $row->status
					? '<span class="text-green-600 font-medium">Active</span>'
					: '<span class="text-red-600 font-medium">Inactive</span>';

				// Actions (UNCHANGED STRUCTURE)

				$row->actions = '
					<div class="flex justify-center items-center space-x-2">
						<form action="' . route('backend_chat_block_toggle', $row->id) . '" method="POST" class="toggleForm">
							' . csrf_field() . '
							<button type="submit" class="text-white p-2 rounded">
								' . ($row->status
					? IconPack::toggleOn(['class' => 'iconPackItem w-8 h-8 text-green-500'])
					: IconPack::toggleOff(['class' => 'iconPackItem w-8 h-8 text-red-500'])) . '
							</button>
						</form>

						<form action="' . route('backend_chat_block_delete', $row->id) . '" method="POST" class="deleteForm">
							' . csrf_field() . method_field('DELETE') . '
							<button type="submit" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded" data-confirm="Are you sure you want to delete this record? This is not an \'unblock\' feature. It will remove the blockage record completely & blocked messages will be opened again between the parties.">
								' . IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']) . '
							</button>
						</form>
					</div>
				';

				$row->created_at_formatted = $row->created_at
					? $row->created_at->format('d M Y, H:i')
					: 'N/A';

				return $row;
			});

			return response()->json([
				'draw' => $draw,
				'recordsTotal' => $recordsTotal,
				'recordsFiltered' => $recordsFiltered,
				'data' => $data,
			]);
		} catch (\Throwable $e) {
			return response()->json([
				'draw' => intval($request->input('draw')),
				'recordsTotal' => 0,
				'recordsFiltered' => 0,
				'data' => [],
				'error' => config('app.debug') ? $e->getMessage() : 'An error occurred.',
			], 500);
		}
	}

	// --------------------- DISPUTE MANAGEMENT ---------------------
	public function reportIndex()
	{
		return view('Admin.sidebar.Communications.Reports.index');
	}


	public function chatReportsDatatable(Request $request)
	{
		try {
			$columns = ['id', 'dispute_report_type', 'dispute_report_id', 'issue_section', 'victim_id', 'accused_id', 'original_content', 'issue_label', 'description', 'is_resolved', 'status', 'created_at'];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = in_array($request->input('order.0.dir'), ['asc', 'desc']) ? $request->input('order.0.dir') : 'asc';

			$query = DisputeReport::with(['victimRelationWith_User', 'accusedRelationWith_User'])
				->select(['id', 'dispute_report_type', 'dispute_report_id', 'issue_section', 'victim_id', 'accused_id', 'original_content', 'issue_label', 'description', 'is_resolved', 'status', 'created_at'])->issueSection('Chatting');

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q
						->where('issue_label', 'like', "%{$search}%")
						->orWhere('description', 'like', "%{$search}%")
						->orWhere('dispute_report_id', 'like', "%{$search}%")
						->orWhere('dispute_report_type', 'like', "%{$search}%")
						->orWhere('original_content', 'like', "%{$search}%")
						->orWhere('is_resolved', 'like', "%{$search}%")
						->orWhere('status', 'like', "%{$search}%");
				});
			}

			$recordsTotal = DisputeReport::count();
			$recordsFiltered = $query->count();

			$data = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();
			$sn = $start;

			$data->transform(function ($row) use (&$sn) {
				$row->DT_RowId = 'row_' . $row->id;
				$row->view_url = route('backend_displute_report_show', $row->id);
				$row->SN = ++$sn;

				$row->section = $row->issue_section;
				$row->content = '<span class="text-gray-400 dark:text-gray-600">[ ' . $row->dispute_report_id . ' ] </span>' . Str::limit($row->original_content ?? '—', 30);

				$victim = $row->victimRelationWith_User;
				$accused = $row->accusedRelationWith_User;

				$row->victim = '
					<div class="flex items-center space-x-3">
						<img src="' . ($victim->avatar ? Storage::url($victim->avatar) : asset('site_assets/dummies/dummy_man.webp')) . '" class="w-10 h-10 rounded-full object-cover" />

						<div>
							<div class="text-xs text-gray-400">DB ID: ' . $victim->id . '</div>
							<div class="text-sm font-medium">' . e($victim->name) . '</div>
							<div class="text-xs text-gray-400">' . e($victim->email) . '</div>
						</div>
					</div>';

				$row->accused = '
					<div class="flex items-center space-x-3">
						<img src="' . ($accused->avatar ? Storage::url($accused->avatar) : asset('site_assets/dummies/dummy_man.webp')) . '" class="w-10 h-10 rounded-full object-cover" />
						<div>
							<div class="text-xs text-gray-400">DB ID: ' . $accused->id . '</div>
							<div class="text-sm font-medium">' . e($accused->name) . '</div>
							<div class="text-xs text-gray-400">' . e($accused->email) . '</div>
						</div>
					</div>';

				$row->phase = $row->is_resolved
					? '<span class="px-3 py-1 rounded-lg bg-green-400 bg-opacity-25 text-xs">Resolved</span>'
					: '<span class="px-3 py-1 rounded-lg bg-orange-300 bg-opacity-25 text-xs">Disputed</span>';

				$row->issue_label = e($row->issue_label);
				$row->description = Str::limit($row->description ?? '-', 30);

				$row->status_label = $row->status
					? '<span class="text-green-600 font-medium">Active</span>'
					: '<span class="text-red-600 font-medium">Inactive</span>';

				$row->actions = '
					<div class="flex justify-center items-center space-x-2">
					
						<form action="' . route('backend_displute_report_toggle', $row->id) . '" method="POST" class="toggleForm">
							' . csrf_field() . '
							<button type="submit" class="text-white p-2 rounded">
								' . ($row->status
					? IconPack::toggleOn(['class' => 'iconPackItem w-8 h-8 text-green-500'])
					: IconPack::toggleOff(['class' => 'iconPackItem w-8 h-8 text-red-500']))
					. '
							</button>
						</form>

						<form action="' . route('backend_displute_report_delete', $row->id) . '" method="POST" class="deleteForm">
							' . csrf_field() . method_field('DELETE') . '
							<button type="submit" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded">
								' . IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']) . '
							</button>
						</form>
					</div>
				';

				$row->created_at_formatted = $row->created_at?->format('d M Y, H:i') ?? 'N/A';

				return $row;
			});

			return response()->json([
				'draw' => $draw,
				'recordsTotal' => $recordsTotal,
				'recordsFiltered' => $recordsFiltered,
				'data' => $data,
			]);
		} catch (Throwable $e) {
			Log::error('Chat report datatable error', ['error' => $e->getMessage()]);
			return response()->json([
				'draw' => $draw,
				'recordsTotal' => 0,
				'recordsFiltered' => 0,
				'data' => [],
				'error' => config('app.debug') ? $e->getMessage() : 'An error occurred.',
			], 500);
		}
	}
	// --------------------- DISPUTE MANAGEMENT ---------------------
}
