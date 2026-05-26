<?php

namespace App\Http\Controllers\Communication;

use App\Actions\Communications\ChatGroupSaveAction;
use App\Helpers\{ApiJsonReturnHelper, Auth\OwnershipAuthCheck, Communications\ChatGroupAuthCheck, FileHelpers\FileManagement, UidGenerator};

use App\Http\Requests\Communications\ChatMessageRequest;
use App\Models\Communication\Chatting\{ChatMessage, ChatBlocklist, ChatGallery, ChatGroup, ChatGroupMember};
use App\Models\System\Dispute_Management\DisputeReport;
use Illuminate\Support\Str;
use App\Helpers\Communications\BlocklistHelper;
use App\Http\Controllers\Controller;
use App\Events\MessageSendEvent;
use App\Helpers\Auth\UserAceesChecking;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Http\Request;
use App\Models\Users\User;
use Throwable;

class ApiChatController extends Controller
{
	public $MAX_GROUP_NUMBERS = 5; // A user cannot create more than five groups
	public $MAX_GROUP_MEMBERS = 5; // A user cannot add more than five group members
	// --------------------- INTERFACE ---------------------

	public function allusers(Request $request)
	{
		try {
			$USER = $request->user();

			$allUsers = User::status(1)
				->where('id', '!=', $USER->id)
				->select(['id', 'user_uid', 'user_role', 'name', 'email', 'phone', 'avatar', 'timezone', 'status'])
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

				$lastMessage = ChatMessage::where(function ($q) use ($USER, $u) {
					$q->where('sender_id', $USER->id)
						->where('receiver_id', $u->id);
				})->orWhere(function ($q) use ($USER, $u) {
					$q->where('sender_id', $u->id)
						->where('receiver_id', $USER->id);
				})
					->with([
						'chatMessageRelationWith_Gallery' => function ($q) {
							$q->latest('id')->limit(1);
						}
					])
					->orderByDesc('id')
					->first();

				if ($lastMessage) {
					$lastMessage->gallery_details = $lastMessage->chatMessageRelationWith_Gallery;

					if ($lastMessage->gallery_details && $lastMessage->gallery_details->isNotEmpty()) {
						$g = $lastMessage->gallery_details->first();

						$lastMessage->gallery_details = [
							[
								'id' => $g->id,
								'chat_group_id' => $g->chat_group_id,
								'message_id' => $g->message_id,
								'sender_id' => $g->sender_id,
								'receiver_id' => $g->receiver_id,

								'size_mb' => data_get($g, 'metadata.meta.size_mb'),
								'file_name' => data_get($g, 'metadata.file_name'),

								'status' => $g->status,
								'created_at' => $g->created_at,
								'updated_at' => $g->updated_at,
								'chat_file_url' => $g->chat_file_url,
							]
						];
					}
					unset($lastMessage->chatMessageRelationWith_Gallery);
				}


				$u->last_message = $lastMessage;
			});

			return ApiJsonReturnHelper::handle(true, 200, 'All available users have been fetched successfully!', $allUsers);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching all users', $e);
		}
	}

	public function searchUser(Request $request)
	{
		try {
			$USER = $request->user();
			$q = trim($request->query('q'));

			$users = User::status(1)
				->where('id', '!=', $USER->id)
				->where(function ($qq) use ($q) {
					if ($q) {
						$qq->where('name', 'like', "%{$q}%")
							->orWhere('email', 'like', "%{$q}%")
							->orWhere('user_uid', 'like', "%{$q}%");
					}
				})
				->select(['id', 'user_uid', 'user_role', 'name', 'email', 'phone', 'avatar', 'timezone', 'status'])
				->withMax(
					[
						'chatMessages as last_message_id' => function ($q2) use ($USER) {
							$q2->where('sender_id', $USER->id)->orWhere('receiver_id', $USER->id);
						}
					],
					'id'
				)
				->orderByDesc('last_message_id')
				->get();

			$users->each(function ($u) use ($USER) {

				$lastMessage = ChatMessage::where(function ($q) use ($USER, $u) {
					$q->where('sender_id', $USER->id)->where('receiver_id', $u->id);
				})->orWhere(function ($q) use ($USER, $u) {
					$q->where('sender_id', $u->id)->where('receiver_id', $USER->id);
				})
					->with(['chatMessageRelationWith_Gallery' => fn($q) => $q->latest('id')->limit(1)])
					->orderByDesc('id')
					->first();

				if ($lastMessage) {
					$lastMessage->gallery_details = $lastMessage->chatMessageRelationWith_Gallery;
					if ($lastMessage->gallery_details && $lastMessage->gallery_details->isNotEmpty()) {
						$g = $lastMessage->gallery_details->first();
						$lastMessage->gallery_details = [
							[
								'id' => $g->id,
								'chat_group_id' => $g->chat_group_id,
								'message_id' => $g->message_id,
								'sender_id' => $g->sender_id,
								'receiver_id' => $g->receiver_id,
								'size_mb' => data_get($g, 'metadata.meta.size_mb'),
								'file_name' => data_get($g, 'metadata.file_name'),
								'status' => $g->status,
								'created_at' => $g->created_at,
								'updated_at' => $g->updated_at,
								'chat_file_url' => $g->chat_file_url,
							]
						];
					}
					unset($lastMessage->chatMessageRelationWith_Gallery);
				}

				$u->last_message = $lastMessage;
			});

			return ApiJsonReturnHelper::handle(true, 200, 'User search results fetched successfully!', [
				'searchParam' => $q,
				'users' => $users
			]);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('searching users', $e);
		}
	}

	public function messages(Request $request, User $friend)
	{
		try {
			$authId = $request->user()->id;

			$detailedMessages = ChatMessage::query()
				->where(function ($query) use ($friend, $authId) {
					$query->where('sender_id', $authId)->where('receiver_id', $friend->id);
				})
				->orWhere(function ($query) use ($friend, $authId) {
					$query->where('sender_id', $friend->id)->where('receiver_id', $authId);
				})
				->with(['chatSenderRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatReceiverRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatMessageRelationWith_Gallery'])->orderBy('id', 'asc')->get();

			// ------------------- TRANSFORMATION -------------------
			$detailedMessages->transform(function ($item) {
				$item->sender_details = $item->chatSenderRelationWith_user;
				$item->receiver_details = $item->chatReceiverRelationWith_user;
				$item->gallery_details = $item->chatMessageRelationWith_Gallery;
				unset($item->chatSenderRelationWith_user);
				unset($item->chatReceiverRelationWith_user);
				unset($item->chatMessageRelationWith_Gallery);
				return $item;
			});
			// ------------------- TRANSFORMATION -------------------

			// ------------------- BLOCK CHECKING -------------------
			$blocked = BlocklistHelper::checkBlockedUser($authId, $friend->id);
			// ------------------- BLOCK CHECKING -------------------

			return response()->json([
				'status' => true,
				'message' => "All messages in this chat thread has been fetched successfully!",
				'data' => [
					'detailedMessages' => $detailedMessages,
					'blockMessage' => $blocked ? "you can no longer send messages to this person." : null,
				],
				'code' => 200
			]);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching chat messages.', $e);
		}
	}

	public function send(ChatMessageRequest $request)
	{
		try {
			$SENDER = $request->user();
			$RECEIVER = User::find($request->receiver_id);
			$DISK_FOLDER = config('filesystems.default');
			$GALLERY = [];
			$validated = $request->validated();

			// ------------------- BLOCK CHECKING -------------------
			// BlocklistHelper::checkBlockedUser($SENDER->id, $request->receiver_id);

			// ------------------- USER ACCESS CHECKING -------------------
			// UserAceesChecking::chatBan($SENDER->id);

			// ------------------- NO SELF-MESSAGE -------------------
			if ((int)$SENDER->id === (int)$RECEIVER->id) {
				return ApiJsonReturnHelper::handle(false, 409, 'You cannot message yourself.', null);
			}

			$sentMessage = ChatMessage::create([
				'sender_id' => $SENDER->id ?? null,
				'receiver_id' => $validated['receiver_id'],
				'text' => $validated['message'],
			]);
			// ------------------- FILE HANDLING -------------------
			if ($request->hasFile('file')) {

				$CHAT_FOLDER = "Communications/MessengerChats/{$SENDER->id}-{$SENDER->user_uid}";

				foreach ($request->file('file') as $index => $uploadedFile) {

					$fileHandled = FileManagement::handleFile(
						$uploadedFile,
						"CHAT[{$sentMessage->id}]-INDEX[" . ($index + 1) . "]-" . Str::before($SENDER->name, ' ') . "-TO-" . Str::before($RECEIVER->name, ' '),
						null,
						$CHAT_FOLDER,
						$DISK_FOLDER,
						false
					);

					$GALLERY[] = ChatGallery::create([
						'chat_group_id' => $sentMessage->chat_group_id ?? null,
						'message_id' => $sentMessage->id,
						'sender_id' => $SENDER->id,
						'receiver_id' => $validated['receiver_id'],
						'file' => $fileHandled['path'] ?? null,
						'metadata' => $fileHandled,
						'status' => 1,
					]);
				}
			}
			// ------------------- FILE HANDLING -------------------

			$sentMessage->load(['chatSenderRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatReceiverRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatMessageRelationWith_Gallery']);

			// ------------------- TRANSFORMATION -------------------
			$sentMessage->sender_details = $sentMessage->chatSenderRelationWith_user;
			$sentMessage->receiver_details = $sentMessage->chatReceiverRelationWith_user;
			$sentMessage->gallery_details = $sentMessage->chatMessageRelationWith_Gallery;

			unset($sentMessage->chatSenderRelationWith_user);
			unset($sentMessage->chatReceiverRelationWith_user);
			unset($sentMessage->chatMessageRelationWith_Gallery);
			// ------------------- TRANSFORMATION -------------------

			// ------------------- BROADCAST THE EVENT -------------------
			$result = broadcast(new MessageSendEvent($sentMessage));
			// ------------------- BROADCAST THE EVENT -------------------

			return ApiJsonReturnHelper::handle(true, 200, 'Message sent successfully!', $sentMessage);
		} catch (Throwable $e) {
			logger()->error('CHAT SEND FAILED', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
			return ExceptionHandling::handle('fetching chat messages.', $e);
		}
	}

	public function show(Request $request, ChatMessage $message)
	{
		try {
			$message->load(['chatSenderRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatReceiverRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatMessageRelationWith_Gallery']);

			$SENDER = $message->chatSenderRelationWith_user;
			$RECEIVER = $message->chatReceiverRelationWith_user;
			$USER = $request->user();

			// ---------------------- CHAT AUTHORIZION GUARD ----------------------
			if ((int)$USER->id !== (int)$SENDER->id && (int)$USER->id !== (int)$RECEIVER->id) {
				return ApiJsonReturnHelper::handle(false, 403, "Unuthorized access to view the message. Aborting!", null);
			}
			// ---------------------- CHAT AUTHORIZION GUARD ----------------------

			// ------------------- TRANSFORMATION -------------------
			$message->sender_details = $message->chatSenderRelationWith_user;
			$message->receiver_details = $message->chatReceiverRelationWith_user;
			$message->gallery_details = $message->chatMessageRelationWith_Gallery;

			unset($message->chatSenderRelationWith_user);
			unset($message->chatReceiverRelationWith_user);
			unset($message->chatMessageRelationWith_Gallery);
			// ------------------- TRANSFORMATION -------------------

			return ApiJsonReturnHelper::handle(true, 200, "A single message sent by '{$SENDER->name}' to {$RECEIVER->name}' has been fetched successfully!", $message);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching a single chat message.', $e);
		}
	}

	public function delete(Request $request, ChatMessage $message)
	{
		try {
			$USER = $request->user();
			$SENDER = $message->chatSenderRelationWith_user;
			$DISK_FOLDER = config('filesystems.default');

			// ---------------------- CHAT AUTHORIZATION GUARD ----------------------
			if ($USER->id !== $SENDER->id) {
				return ApiJsonReturnHelper::handle(false, 403, "Unauthorized to delete this message", null);
			}
			// ---------------------- CHAT AUTHORIZATION GUARD ----------------------

			// ---------------------- DELETE ATTACHED FILES ----------------------
			foreach ($message->chatMessageRelationWith_Gallery as $gallery) {
				if ($gallery->file) {
					FileManagement::deleteFile($gallery->file, $DISK_FOLDER);
				}
				$gallery->delete();
			}
			// ---------------------- DELETE ATTACHED FILES ----------------------

			$message->delete();

			return ApiJsonReturnHelper::handle(true, 200, "Message deleted successfully", null);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('deleting chat message.', $e);
		}
	}
	// --------------------- INTERFACE ---------------------

	// --------------------- GROUP-MESSAGE ---------------------

	public function indexAllGroups()
	{
		try {
			$allAvailableGroups = ChatGroup::status(1)->get();

			return ApiJsonReturnHelper::handle(true, 200, "All chat groups have been fetched sucessfully!", $allAvailableGroups);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('all chat groups', $e);
		}
	}

	public function indexMyGroups(Request $request)
	{
		try {
			$USER = $request->user();

			$myGroupIds = ChatGroupMember::member($USER->id)->status(1)->pluck('chat_group_id');
			$allMyGroups = ChatGroup::whereIn('id', $myGroupIds)->get();

			return ApiJsonReturnHelper::handle(true, 200, "All my chat groups have been fetched sucessfully!", $allMyGroups);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('all my chat groups', $e);
		}
	}

	public function showGroup(Request $request, ChatGroup $chatGroup)
	{
		try {
			$USER = $request->user();

			// ----------------- AUTHORIZATION -----------------
			ChatGroupAuthCheck::isUserMember($chatGroup->id, $USER->id, null);
			// ----------------- AUTHORIZATION -----------------

			$messages = $chatGroup->chatGroupRelationWith_Message()->with(['chatSenderRelationWith_user:id,user_uid,name,user_role,email,avatar,timezone,status', 'chatMessageRelationWith_Gallery'])->orderBy('id', 'asc')->paginate(20);

			$messages->getCollection()->transform(function ($message) {
				$message->setAttribute('message_sender_detail', $message->chatSenderRelationWith_user);
				$message->setAttribute('chat_message_gallery', $message->chatMessageRelationWith_Gallery);

				$message->unsetRelation('chatMessageRelationWith_Gallery');
				$message->unsetRelation('chatSenderRelationWith_user');
				return $message;
			});

			$groupCreator = $chatGroup->chatGroupCreatorRelatingBackTo_User->only(['id', 'user_uid', 'name', 'user_role', 'email', 'avatar', 'timezone', 'status', 'avatar_url']);

			$chatGroup->setAttribute('chat_group_messages', $messages);
			$chatGroup->unsetRelation('chatGroupRelationWith_Message');
			$chatGroup->unsetRelation('chatGroupCreatorRelatingBackTo_User');

			return ApiJsonReturnHelper::handle(true, 200, "Chat group '{$chatGroup->title}' fetched successfully!", [
				'chat_group' => $chatGroup,
				'chat_group_owner' => $groupCreator,
			]);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching a chat group.', $e);
		}
	}

	public function storeGroup(Request $request, ChatGroupSaveAction $action)
	{
		try {
			$CREATOR = $request->user();
			$DISK_FOLDER = config('filesystems.default');
			$chat_group_uid = "";
			$existingChatGroup = null;
			$maxChatGroupId = ChatGroup::withoutGlobalScopes()->orderByDesc('id')->value('id') ?? 0;
			$incrementChatGroupId = $maxChatGroupId + 1;

			$validated = $request->validate([
				'id' => 'nullable|integer|exists:chat_groups,id',
				'title' => 'required|string|max:60',
				'description' => 'nullable|string|max:200',
				'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5000',
				'remove_image' => 'nullable|boolean',
			]);

			// --------------------- LIMIT CHECKING ---------------------

			if (ChatGroup::creator($CREATOR->id)->count() >= $this->MAX_GROUP_NUMBERS) {
				return ApiJsonReturnHelper::handle(false, 409, "Cannot create more than {$this->MAX_GROUP_NUMBERS} chat groups!", null);
			}
			// --------------------- LIMIT CHECKING ---------------------

			// ------------ CHECKING EXISTANCE + UID ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingChatGroup = ChatGroup::find($validated['id']);
				OwnershipAuthCheck::ownerVsOwner($CREATOR->id, $existingChatGroup->creator_id);
				$chat_group_uid = $existingChatGroup->chat_group_uid;
				$incrementChatGroupId = $existingChatGroup->id;
			} else {
				$chat_group_uid = UidGenerator::uniqueName($validated['title'], 12, 4);
			}
			// ------------ CHECKING EXISTANCE + UID ------------

			// ------------ FILE HANDLING ------------
			$IMAGE_FOLDER = 'Communications/Chatrooms/' . $incrementChatGroupId . "-" . $chat_group_uid;

			if ($request->hasFile('image') || $request->boolean('remove_image')) {
				$validated['image'] = FileManagement::handleFile(
					$request->file('image'),
					"0-COVER-" . $chat_group_uid,
					$existingChatGroup->image ?? null,
					$IMAGE_FOLDER,
					$DISK_FOLDER,
					$request->boolean('remove_image')
				);
			} else {
				$validated['image'] = ['path' => $existingChatGroup->image ?? null];
			}
			// ------------ FILE HANDLING ------------

			// --------------------- SAVE ---------------------
			$chatGroup = $action->execute($validated, $chat_group_uid, $CREATOR);
			// --------------------- SAVE ---------------------

			return ApiJsonReturnHelper::handle(true, 200, "Chat group has been saved sucessfully!", $chatGroup);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('saving a chat group', $e);
		}
	}

	public function sendGroupMessage(Request $request)
	{
		try {
			$SENDER = $request->user();
			$DISK_FOLDER = config('filesystems.default');
			$CHAT_GROUP = ChatGroup::whereId($request->chat_group_id)->first();
			$GALLERY = [];

			// ------------------- BLOCK CHECKING -------------------
			// $blocked = BlocklistHelper::checkBlockedUser($SENDER->id, $request->receiver_id);
			// if ($blocked) {
			// 	return ApiJsonReturnHelper::handle(false, 401, 'You can no longer message in this conversation.', null);
			// }
			// ------------------- BLOCK CHECKING -------------------

			// ------------------- USER ACCESS CHECKING -------------------
			UserAceesChecking::chatBan($SENDER->id);

			$validated = $request->validate([
				'message' => 'required|string',
				'chat_group_id' => 'nullable|integer|exists:chat_groups,id',
				'file' => 'nullable|array',
				'file.*' => [
					'file',
					'max:102400',
					'mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg,video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska,application/pdf,text/plain,text/csv,application/csv,application/json,text/json,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/rtf,text/rtf,application/x-rtf',
					function ($attribute, $value, $fail) {
						$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'm4a', 'wav', 'ogg', 'mp4', 'mov', 'avi', 'mkv', 'webm', 'pdf', 'txt', 'csv', 'json', 'doc', 'docx', 'xls', 'xlsx', 'rtf'];
						if (!in_array(strtolower($value->getClientOriginalExtension()), $allowedExts)) {
							$fail('Only these file types are allowed: jpeg, png, gif, webp images; mp3, m4a, wav audio; mp4, mov, avi, webm videos; pdf, txt, csv, json, doc, docx, xls, xlsx, rtf documents.');
						}
					},
				],
			]);

			$sentMessage = ChatMessage::create([
				'chat_group_id' => $validated['chat_group_id'] ?? null,
				'sender_id' => $SENDER->id ?? null,
				'text' => $validated['message'],
			]);

			// ------------------- FILE HANDLING -------------------
			if ($request->hasFile('file')) {

				$CHAT_FOLDER = "Communications/MessengerChats/{$SENDER->id}-{$SENDER->user_uid}";

				foreach ($request->file('file') as $index => $uploadedFile) {

					$fileHandled = FileManagement::handleFile(
						$uploadedFile,
						"CHAT_ID[{$sentMessage->id}]-" . ($index + 1) . "-{$CHAT_GROUP->title}",
						null,
						$CHAT_FOLDER,
						$DISK_FOLDER,
						false
					);

					$GALLERY[] = ChatGallery::create([
						'chat_group_id' => $sentMessage->chat_group_id ?? null,
						'message_id' => $sentMessage->id,
						'sender_id' => $SENDER->id,
						'file' => $fileHandled['path'] ?? null,
						'metadata' => $fileHandled,
						'status' => 1,
					]);
				}
			}
			// ------------------- FILE HANDLING -------------------
			// $sentMessage->load(['chatSenderRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatReceiverRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatMessageRelationWith_Gallery']);
			$sentMessage->load(['chatSenderRelationWith_user:id,user_uid,user_role,name,email,phone,avatar,timezone,status', 'chatMessageRelationWith_Gallery']);

			// ------------------- BROADCAST THE EVENT -------------------
			$result = broadcast(new MessageSendEvent($sentMessage));
			// ------------------- BROADCAST THE EVENT -------------------

			return ApiJsonReturnHelper::handle(true, 200, 'Message sent to group chat successfully!', $sentMessage);
		} catch (Throwable $e) {
			logger()->error('CHAT SEND FAILED', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
			return ExceptionHandling::handle('sending group chat messages.', $e);
		}
	}

	public function deleteGroup(Request $request, ChatGroup $chatGroup)
	{
		$name = null;
		$imageFolder = null;
		try {
			$USER_ID = $request->user()->id;
			OwnershipAuthCheck::ownerVsOwner($chatGroup->creator_id, $USER_ID);

			$name = $chatGroup?->title ?? null;
			$imageFolder = dirname($chatGroup->image);
			$DISK_FOLDER = config('filesystems.default');

			FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
			$chatGroup->delete();

			return ApiJsonReturnHelper::handle(true, 200, "Chat group '{$name}' is deleted sucessfully!", null);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('deleting a chat group', $e);
		}
	}

	public function addNewMember(Request $request)
	{
		try {
			$USER = $request->user();
			$validated = $request->validate([
				'group_id' => 'required|integer|exists:chat_groups,id',
				'member_id' => 'required|array',
				'member_id.*' => 'required|integer|exists:users,id',
			]);

			// ----------------- AUTHORIZATION -----------------
			ChatGroupAuthCheck::isUserMember($validated['group_id'], $USER->id, $validated['member_id'], $this->MAX_GROUP_MEMBERS);
			// ----------------- AUTHORIZATION -----------------

			$newMembers = collect();
			foreach ($validated['member_id'] as $member) {
				if ((int)$member === (int)$USER->id)
					continue;
				$newMembers->push(
					ChatGroupMember::updateOrCreate(
						[
							'chat_group_id' => (int)$validated['group_id'],
							'member_id' => (int)$member,
						],
						[
							'adder_id' => (int)$USER->id,
							'member_role' => "member"
						]
					)
				);
			}
			$newMembers->each->load('chatGroupMemberRelatingBackTo_User:id,user_uid,name,email,avatar,status');
			$newMembers->each(function ($member) {
				$member->member_details = $member->chatGroupMemberRelatingBackTo_User;
				unset($member->chatGroupMemberRelatingBackTo_User);
			});

			// ---------------------- CHAT GROUP MEMBER COUNT ----------------------
			$chatGroup = ChatGroup::groupId((int)$validated['group_id'])->first();
			$chatGroup->total_members = $chatGroup->count();
			$chatGroup->save();
			// ---------------------- CHAT GROUP MEMBER COUNT ----------------------

			return ApiJsonReturnHelper::handle(true, 200, "The selected users have been added to the group chat sucessfully!", $newMembers);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('adding users', $e);
		}
	}

	public function removeMember(Request $request, ChatGroup $group, $member)
	{
		$name = '';
		$flaged = '';
		$flagging = '';
		try {
			$USER = $request->user();

			$flaged = (int)$USER->id === (int)$member ? "has left" : "has been removed";
			$flagging = (int)$USER->id === (int)$member ? "leaving" : "removing";
			$name = User::whereId($member)->value('name') ?? null;

			// ----------------- AUTHORIZATION -----------------
			$groupMember = ChatGroupAuthCheck::isMemberRemovable($group, $USER, $member);
			// ----------------- AUTHORIZATION -----------------

			// $groupMember = ChatGroupMember::groupId($group->id)->member($member)->first();
			// dd($groupMember);
			$groupMember->delete();
			// ---------------------- CHAT GROUP MEMBER COUNT ----------------------
			$group->total_members = ChatGroupMember::groupId((int)$group->id)->count();
			$group->save();
			// ---------------------- CHAT GROUP MEMBER COUNT ----------------------

			return ApiJsonReturnHelper::handle(true, 200, "Member '{$name}' {$flaged} from the group chat sucessfully!", null);

		} catch (Throwable $e) {
			return ExceptionHandling::handle("{$flagging} from the group chat", $e);
		}
	}
	// --------------------- GROUP-MESSAGE ---------------------


	// --------------------- BLACKLISTING MANAGEMENT ---------------------

	public function block(Request $request, User $user)
	{
		try {
			$VICTIM = $request->user();
			$validated = $request->validate([
				'reason' => 'nullable|string|max:255'
			]);

			if ((int)$VICTIM->id === (int)$user->id) {
				return ApiJsonReturnHelper::handle(false, 422, 'You cannot block yourself.', null);
			}
			// ------------------- BLOCK CHECKING -------------------
			$blocked = BlocklistHelper::checkBlockedUser($VICTIM->id, $user->id);
			if ($blocked) {
				return ApiJsonReturnHelper::handle(false, 422, 'This use was already blocked by you.', $user);
			}
			// ------------------- BLOCK CHECKING -------------------

			$compositeA = $VICTIM->id . '-' . $user->id;
			$compositeB = $user->id . '-' . $VICTIM->id;

			$blocked = ChatBlocklist::updateOrCreate(
				[
					'victim_id' => $VICTIM->id,
					'blocked_id' => $user->id,
				],
				[
					'composite_a' => $compositeA,
					'composite_b' => $compositeB,
					'reason' => $validated['reason'] ?? null,
					'is_blocked' => 1,
					'status' => 1
				]
			);

			return ApiJsonReturnHelper::handle(true, 200, "User: {$user->email} has been blocked from messaging the user: {$VICTIM->email} successfully!", $blocked);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('blocking a user in chat message.', $e);
		}
	}

	public function unblock(Request $request, User $user)
	{
		try {
			$VICTIM = $request->user();
			$composite = $VICTIM->id . '-' . $user->id;

			$blocked = ChatBlocklist::with(['victimRelationWith_User', 'blockedRelationWith_User'])
				->where('is_blocked', 1)->status(1)->where('victim_id', $VICTIM->id)
				->where(function ($q) use ($composite) {
					$q->where('composite_a', $composite)->orWhere('composite_b', $composite);
				})
				->first();

			if ($blocked) {
				$blocked->update([
					'is_blocked' => 0
				]);

				return ApiJsonReturnHelper::handle(true, 200, "You have successfully unblocked the user '{$user->name}'!", $blocked);
			} else {
				return ApiJsonReturnHelper::handle(false, 403, "The user '{$user->name}' is either not blocked or you don't have the permission to unblock!", null);
			}
		} catch (Throwable $e) {
			return ExceptionHandling::handle('unblocking a user in chat message.', $e);
		}
	}

	public function myBlocklist(Request $request)
	{
		try {
			$USER = $request->user();
			$blockedUsers = ChatBlocklist::with('blockedRelationWith_User:id,user_role,name,email,avatar,status')->victim($USER->id)->get();

			return ApiJsonReturnHelper::handle(true, 200, "All users blocked by me have been fetched sucessfully!", $blockedUsers);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching blocked users', $e);
		}
	}
	// --------------------- BLACKLISTING MANAGEMENT ---------------------


	// --------------------- DISPUTE MANAGEMENT ---------------------

	public function report(Request $request, ChatMessage $chat)
	{
		try {
			dd($request->all());
			$USER = $request->user();
			// ---------------------- CHAT AUTHORIZION GUARD ----------------------
			if (empty($chat->chat_group_id) && ($USER->id !== $chat->receiver_id)) {
				$message = "Unuthorized access. This content doesn't belong to you. Aborting!";
				if ($USER->id === $chat->sender_id)
					$message = "You cannot report your own content. Aborting!";

				return ExceptionHandling::bailout(403, $message);
			}
			// ---------------------- CHAT AUTHORIZION GUARD ----------------------

			$validated = $request->validate([
				'issue_label' => 'required|string|max:255',
				'description' => 'required|string|max:255'
			]);

			// ---------------------- MORPHING ----------------------
			$reportable = match ('chat_message') {
				'chat_message' => ChatMessage::whereId($chat->id)->firstOrFail(),
			};

			// ---------------------- MORPHING ----------------------

			$accused = $chat->chatSenderRelationWith_user->only(['id', 'name', 'user_role', 'email', 'avatar', 'timezone', 'avatar_url']);

			$chat->load(['chatMessageRelationWith_Gallery']);
			$chat->unsetRelation('chatSenderRelationWith_user');
			$chat->unsetRelation('chatReceiverRelationWith_user');


			// ---------------------- EXISITANCE CHECKING ----------------------
			$alreadyReported = DisputeReport::where('dispute_report_type', ChatMessage::class)->where('dispute_report_id', $chat->id)->where('victim_id', $USER->id)->exists();

			if ($alreadyReported) {
				return ApiJsonReturnHelper::handle(false, 409, 'You have already reported this content.', null);
			}
			// ---------------------- EXISITANCE CHECKING ----------------------

			$report = DisputeReport::create([
				'ticket' => UidGenerator::uniqueName("CHAT-{$USER->name}", 12, 6),

				'dispute_report_id' => $chat->id,
				'dispute_report_type' => ChatMessage::class,

				'issue_section' => 'Chatting',
				'issue_label' => $validated['issue_label'],
				'description' => $validated['description'] ?? null,

				'original_id' => $chat->id ?? null,
				'original_content' => $chat->text ?? null,

				'victim_id' => $USER->id,
				'accused_id' => $chat->sender_id,

				'is_resolved' => 0,
				'status' => 1,
			]);

			return ApiJsonReturnHelper::handle(true, 200, 'The chat has been reported to the Admin(s) for verification.', [
				'report' => $report,
				'reporter' => $USER->only(['id', 'name', 'user_role', 'email', 'avatar', 'timezone', 'avatar_url']),
				'accused' => $accused,
				'chat' => $chat
			]);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('reporting a chat message.', $e);
		}
	}
	// --------------------- DISPUTE MANAGEMENT ---------------------
}
