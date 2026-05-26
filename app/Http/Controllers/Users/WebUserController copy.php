<?php
namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Helpers\{FileHelpers\FileManagement, UidGenerator};
use App\Imports\UsersImport;
use App\Models\System\Settings\UserAppPreference;
use App\Models\Users\{User, UserVerification};
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, Storage};
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Helpers\Auth\{SuperAdminOverride,VerifyEmailUpdate};
use App\Helpers\Ui\DatatableHelper;
use Spatie\Permission\Models\Role;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Exports\UsersExport;
use Carbon\Carbon;
use Exception;
use Throwable;

class WebUserController extends Controller implements HasMiddleware
{
	use AuthorizesRequests;
	private int $OTP_EXPIRY_DURATION = 15;
	public static function middleware(): array
	{
		return [
			// new Middleware('verify_role_key:admin', only: ['allProjects']),
			new Middleware(PermissionMiddleware::using(['user_index', 'user_show']), only: ['create']),
			new Middleware(PermissionMiddleware::using(['user_create', 'user_update']), only: ['store']),
		];
	}
	// --------------- USERS ---------------

	public function single_user(User $user)
	{
		$roles = Role::all();
		$verification = UserVerification::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
		$settingsPreference = UserAppPreference::where('user_id', $user->id)->first();

		return view('Admin.sidebar.Users.single_user', compact('user', 'verification', 'roles', 'settingsPreference'));
	}


	public function all_users(Request $request)
	{
		return view('Admin.sidebar.Users.all_users');
	}


	public function deleteUser(User $user)
	{
		try {
			if (strtolower($user->user_role) === 'admin' && strtolower($user->email) === 'alpha@test.com') {
				return redirect()->back()->with('error', '403 Aborting! Attempt to delete a master admin.');
			}
			$name = $user->name;

			$DISK_FOLDER = config('filesystems.default');
			FileManagement::deleteFile($user->avatar, $DISK_FOLDER);
			$user->delete();

			return redirect()->back()->with('success', "User '{$name}' deleted successfully!");
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}


	public function bulkDeleteUser(Request $request, $flag)
	{
		try {
			$ids = $request->ids ?? [];

			if (empty($ids)) {
				return redirect()->back()->with('error', 'No users selected.');
			}

			$MESSAGE = "Selected users deleted.";
			$DISK_FOLDER = config('filesystems.default');

			foreach ($ids as $id) {
				$user = User::find($id);

				if (!$user)
					continue;

				if (strtolower($user->user_role) === 'admin') {
					$MESSAGE = "Selected users deleted (admins cannot be deleted by bulk action)";
					continue;
				}

				if (!empty($user->avatar)) {
					FileManagement::deleteFile($user->avatar, $DISK_FOLDER);
				}

				$user->delete();
			}

			return redirect()->back()->with('success', $MESSAGE);
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Action error: ' . $e->getMessage());
		}
	}


	public function manualVerifyUserToggle(User $user)
	{
		try {
			if (strtolower(Auth::user()->user_role) !== 'admin') {
				return redirect()->back()->with('error', 'You are not authorized to verify/unverify another user.');
			}

			$name = $user->name;

			$user->email_verified_at = $user->email_verified_at ? null : now();
			$user->status = $user->email_verified_at ? 1 : 0;
			$user->save();

			return redirect()->back()->with('success', "User '{$name}' verification status has been " .
				($user->email_verified_at ? 'verified' : 'unverified ⚠️') . '.', );
		} catch (Exception $e) {
			Log::error('Verification toggle failed: ' . $e->getMessage(), ['user_id' => $user->id ?? null,]);
			return redirect()->back()->with('error', "Verification toggle failed. Please try again later.");
		}
	}

	public function all_users_dataTable(Request $request)
	{
		try {
			$columns = ['id', 'user_uid', 'avatar', 'name', 'user_role', 'email', 'created_at', 'status'];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

			$query = User::select($columns)->with('roles:id,name');

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
						->orWhere('email', 'like', "%{$search}%")
						->orWhere('user_uid', 'like', "%{$search}%")
						->orWhereHas('roles', function ($q) use ($search) {
							$q->where('name', 'like', "%{$search}%");
						});
				});
			}

			$recordsTotal = User::count();
			$recordsFiltered = $query->count();

			$users = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

			$users->transform(function ($row) use (&$sn, $zones) {

				$row->DT_RowId = 'row_' . $row->id;
				$row->view_url = route('backend_single_user', $row->id); // clickable row
				$row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

				$row->SN = ++$sn;

				// Avatar
				$userAvatar = $row->avatar ? Storage::url($row->avatar) : asset('site_assets/dummies/dummy_man.webp');
				$row->avatar_img = '<img src="' . $userAvatar . '" class="w-12 h-12 rounded-lg mx-auto">';

				// Created date
				$row->created_at_formatted = $row->created_at ? Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')' : 'N/A';

				// Role badge
				$row->role = DatatableHelper::roleColor($row->roles->pluck('name')->first());

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_user_toggle', 'backend_user_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $users);
		} catch (Throwable $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "All users datatable error");
		}
	}
	// ---------------USERS---------------


	// ---------------CREATE NEW USERS---------------

	public function create()
	{
		$roles = Role::all();
		return view('Admin.sidebar.Users.create_user', compact('roles'));
	}


	public function store(Request $request)
	{
		$validated = $request->validate([
			'id' => 'nullable|integer|exists:users,id',
			'name' => 'required|string|max:255',
			'role' => 'required|string',	// keep role for RBAC matching. don't change to user_role

			'email' => 'required|email|max:255|unique:users,email,' . $request->id,
			'phone' => 'nullable|string|max:20',

			'password' => $request->id
				? 'nullable|string|min:6|confirmed'
				: 'required|string|min:6|confirmed',

			'avatar' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
			'remove_image' => 'nullable|boolean',
			'status' => 'nullable|boolean',
		], [
			'email.unique' => "Another user already uses this email.",
			'password.confirmed' => "Passwords do not match.",
		]);

		try {
			$DISK_FOLDER = config('filesystems.default');
			$restrictedRoles = ['super_admin', 'admin'];
			$SELECTED_ROLE = null;
			$USER_UID = null;
			$superAdminChangeTrigerred = false;

			$CURRENT_USER = $request->user();
			$TARGET_USER = null;
			$hasSuperAccess = $CURRENT_USER?->hasRoleKey('super_admin') || $CURRENT_USER?->canAny(['admin_create']);
			$isTargetUserRoleRestricted = null;

			// ----------------------- EXISTANCE CHECKING -----------------------
			if (!empty($validated['id'])) {
				$TARGET_USER = User::find((int)$validated['id']);
				$USER_UID = $TARGET_USER?->user_uid;
				$isTargetUserRoleRestricted = $TARGET_USER->hasRoleKey('admin', 'super_admin');
				$this->authorize('user_update', $TARGET_USER);
			} else {
				$this->authorize('user_create', $TARGET_USER);
				$USER_UID = UidGenerator::uniqueUsername($request->name, 8, 4);
			}
			$isSameUser = $TARGET_USER ? ((int)$CURRENT_USER->id === (int)$TARGET_USER->id) : null;
			// ----------------------- EXISTANCE CHECKING -----------------------

			// ----------------------- SUPER PERMISSIONS -----------------------
			$CURRENT_USER_CAN_CHANGE_ADMIN_EMAIL = config('app_permission_overridding.permissions.admin_allowed_for_admin_email') && $CURRENT_USER?->can('admin_email');

			$CURRENT_USER_CAN_CHANGE_ADMIN_PASS = config('app_permission_overridding.permissions.admin_allowed_for_admin_password') && $CURRENT_USER?->can('admin_password');

			$CAN_CHANGE_OWN_EMAIL = $isSameUser && config('app_permission_overridding.permissions.allowed_changing_own_email');
			$CAN_CHANGE_OWN_PASSWORD = $isSameUser && config('app_permission_overridding.permissions.allowed_changing_own_password');

			dd($CURRENT_USER_CAN_CHANGE_ADMIN_EMAIL,$CURRENT_USER_CAN_CHANGE_ADMIN_PASS,$CAN_CHANGE_OWN_EMAIL,$CAN_CHANGE_OWN_PASSWORD);
			// ----------------------- SUPER PERMISSIONS -----------------------

			// ----------------------- ROLE ASSIGNMENT -----------------------
			$superAdminOverride = SuperAdminOverride::prevention($request, $CURRENT_USER, $TARGET_USER);
			if ($superAdminOverride['lockdownTrigger']) {
				return back()->with('error', $superAdminOverride['message']);
			}
			$SELECTED_ROLE = strtolower($request->role) ?? null;
			// ----------------------- ROLE ASSIGNMENT -----------------------

			DB::beginTransaction();
			// ----------------------- FILE HANDLING ---------------------------
			$FILE_FOLDER = $isTargetUserRoleRestricted ? "user-avatars/admins" : "user-avatars/";
			if (($request->hasFile('avatar') || $request->boolean('remove_image')) && !$superAdminOverride['lockdownTrigger']) {
				$validated['avatar'] = FileManagement::handleFile(
					$request->file('avatar'),
					$validated['name'] ?? 'no-name',
					$TARGET_USER->avatar ?? null,
					$FILE_FOLDER,
					$DISK_FOLDER,
					$request->boolean('remove_image')
				);
			} else {
				$validated['avatar'] = ['path' => $TARGET_USER->avatar ?? null];
			}
			// ----------------------- FILE HANDLING ---------------------------

			// ----------------------- PASSWORD + EMAIL -----------------------
			$isTargetRestricted = $TARGET_USER?->hasRoleKey(...$restrictedRoles);
			$emailChanged = null;
			$emailToChange = $TARGET_USER ? $TARGET_USER?->email : $validated['email'];
			$passwordToStore = $TARGET_USER?->password;

			$passwordChanged = !empty($validated['password']);
			$emailChangedCheck = !empty($validated['email']) && (!$TARGET_USER || ($validated['email'] !== $TARGET_USER->email));

			$variablePermission = ($passwordChanged && $CURRENT_USER_CAN_CHANGE_ADMIN_PASS) || ($emailChangedCheck && $CURRENT_USER_CAN_CHANGE_ADMIN_EMAIL);

			$adminTriggerMessage = null;
			if ((!$CAN_CHANGE_OWN_EMAIL && !$CAN_CHANGE_OWN_PASSWORD) && ($passwordChanged || $emailChangedCheck) && $isTargetRestricted && !$hasSuperAccess && !$variablePermission) {
				$superAdminChangeTrigerred = true;
				$adminTriggerMessage = "Action aborted! Only Super Admin can change other admins' email & password";
			}

			$noPasswordChanged = null;
			if (!$superAdminChangeTrigerred) {
				if ($passwordChanged && (!$isTargetUserRoleRestricted || $CURRENT_USER_CAN_CHANGE_ADMIN_PASS)) {
					// dd(1);
					$passwordToStore = Hash::make($validated['password']);
				} else {
					// dd(2);
					$noPasswordChanged = " However, only Super Admin & Admins with admin_password permission may change other admins' password.";
				}
				// dd(3);

				if ($emailChangedCheck && (!$isTargetUserRoleRestricted || $CURRENT_USER_CAN_CHANGE_ADMIN_EMAIL)) {
					if (!$CAN_CHANGE_OWN_EMAIL && $TARGET_USER && !$hasSuperAccess) {
						// only super_admin can bypass. Admins with 'admin_email' permission still need to verify email.
						$emailChanged = VerifyEmailUpdate::updateEmail($TARGET_USER, $validated['email'], $this->OTP_EXPIRY_DURATION);
						} else {
						$emailToChange = $validated['email'];
					}
				}
			}
			// ----------------------- PASSWORD + EMAIL -----------------------

			if ($superAdminChangeTrigerred) {
				return back()->with('error', $adminTriggerMessage ?? "Action prevented!");
			}

			// ----------------------- SAVE USER -------------------------------
			$TARGET_USER = User::updateOrCreate(
				[
					'id' => $validated['id'] ?? null
				],
				[
					'created_by' => (int)1,
					'user_uid' => $USER_UID,
					'name' => $validated['name'],
					'user_role' => $SELECTED_ROLE,
					'email' => $emailToChange,
					'email_verified_at' => now(),
					'phone' => $validated['phone'] ?? null,
					'password' => $passwordToStore,

					'avatar' => $validated['avatar']['path'] ?? null,
					'status' => $validated['status'] ?? 1,
				]
			);
			// ----------------------- SAVE USER -------------------------------

			$TARGET_USER->syncRoles([$SELECTED_ROLE]);
			if (!$TARGET_USER->hasRole($SELECTED_ROLE)) {
				return back()->with('error', "The user role has not been synced yet. Select the role manually & try again!");
			}

			$message = $emailChanged ? "Profile updated. Please verify your new email using the OTP sent to {$validated['email']}. {$noPasswordChanged}" : "User '{$TARGET_USER->name}' is saved successfully!";

			DB::commit();
			return back()->with(['success' => $message, 'emailChanged' => $emailChanged]);
		} catch (Throwable $e) {
			DB::rollBack();
			return back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}
	// ---------------CREATE NEW USERS---------------


	// ---------------BLACKLISTS---------------

	public function indexBlackListedUsers(Request $request)
	{
		return view('Admin.sidebar.Users.blacklist_users');
	}


	public function blacklisted_users_dataTable(Request $request)
	{
		try {
			$columns = ['id', 'user_uid', 'avatar', 'name', 'user_role', 'email', 'created_at', 'status'];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

			$query = User::select($columns)->with('roles:id,name')->where('status', 0)->whereNotNull('email_verified_at');

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
						->orWhere('email', 'like', "%{$search}%")
						->orWhere('user_uid', 'like', "%{$search}%")
						->orWhereHas('roles', function ($q) use ($search) {
							$q->where('name', 'like', "%{$search}%");
						});
				});
			}

			$recordsTotal = User::where('status', 0)->whereNotNull('email_verified_at')->count();
			$recordsFiltered = $query->count();

			$users = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();
			$users->transform(function ($row) use (&$sn, $zones) {
				$row->SN = ++$sn;
				$row->view_url = route('backend_single_user', $row->id); // clickable row

				$userAvatar = $row->avatar ? Storage::url($row->avatar) : asset('site_assets/dummies/dummy_man.webp');
				$row->avatar_img = '<img src="' . $userAvatar . '" class="w-8 h-8 rounded-full mx-auto">';

				$row->created_at_formatted = $row->created_at ? Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')' : 'N/A';

				// Role badge
				Log::info('', [$row->roles->pluck('name')->first()]);
				$row->role = DatatableHelper::roleColor($row->roles->pluck('name')->first());

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_user_toggle', 'backend_user_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $users);
		} catch (Throwable $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "All users datatable error");
		}
	}


	public function blacklistUserToggle(User $user)
	{
		try {
			if (strtolower($user->user_role) === 'admin') {
				return response()->json(['success' => false, 'error' => '403 Aborting! Cannot blacklist an admin.'], 403);
			}
			$user->update(['status' => !$user->status]);
			return response()->json(['success' => true]);
		} catch (Exception $e) {
			Log::error('Blacklist toggle failed: ' . $e->getMessage(), ['user_id' => $user->id ?? null]);
			return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
		}
	}
	// ---------------BLACKLISTS---------------


	// ---------------PENDING---------------

	public function indexPendingUsers(Request $request)
	{
		$cullStatus = null;
		$cullMessage = null;

		$cull = self::cullPendingUsers(360);	// pass minutes
		if ($cull['flag'] == 1) {
			$cullStatus = 'success';
		} else {
			$cullStatus = 'error';
		}
		;
		$cullMessage = $cull['message'];

		return view('Admin.sidebar.Users.pending_users')->with([$cullStatus => $cullMessage]);
	}


	public function pending_users_dataTable(Request $request)
	{
		try {
			$columns = ['id', 'user_uid', 'avatar', 'name', 'user_role', 'email', 'created_at', 'status'];

			$draw = (int)$request->input('draw');
			$start = (int)$request->input('start', 0);
			$length = (int)$request->input('length', 25);
			$orderColIndex = (int)$request->input('order.0.column', 0);
			$orderCol = $columns[$orderColIndex] ?? 'id';
			$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

			$query = User::select($columns)->with('roles:id,name')->where('status', 0)->whereNull('email_verified_at');

			if ($search = $request->input('search.value')) {
				$query->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
						->orWhere('email', 'like', "%{$search}%")
						->orWhere('user_uid', 'like', "%{$search}%")
						->orWhereHas('roles', function ($q) use ($search) {
							$q->where('name', 'like', "%{$search}%");
						});
				});
			}

			$recordsTotal = User::where('status', 0)->whereNull('email_verified_at')->count();
			$recordsFiltered = $query->count();

			$users = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

			$sn = $start;
			$zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();
			$users->transform(function ($row) use (&$sn, $zones) {
				$row->SN = ++$sn;
				$row->view_url = route('backend_single_user', $row->id); // clickable row

				$userAvatar = $row->avatar ? Storage::url($row->avatar) : asset('site_assets/dummies/dummy_man.webp');
				$row->avatar_img = '<img src="' . $userAvatar . '" class="w-8 h-8 rounded-full mx-auto">';

				$row->created_at_formatted = $row->created_at ? Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')' : 'N/A';

				// Role badge
				$row->role = DatatableHelper::roleColor($row->roles->pluck('name')->first());

				$row->actions = DatatableHelper::datatableAction($row, true, 'backend_user_toggle', 'backend_user_delete');

				return $row;
			});

			return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $users);
		} catch (Throwable $e) {
			return DatatableHelper::handleDatatableError($request, $e, true, "All users datatable error");
		}
	}
	// ---------------PENDING---------------


	// ---------------CULL UNATTENDED USERS---------------

	private static function cullPendingUsers($TIME = 360)	// minutes
	{
		try {
			$cutoffTime = now()->subMinutes($TIME);

			// Fetch all unverified + inactive users older than $TIME minutes
			$usersToDelete = User::whereNull('email_verified_at')
				->where('status', 0)
				->where('created_at', '<', $cutoffTime)
				->get();

			if ($usersToDelete->isNotEmpty()) {
				$count = $usersToDelete->count();

				// Using existing deleteUser() method for each user
				foreach ($usersToDelete as $user) {
					app(static::class)->deleteUser($user);
				}

				Log::info("Cull complete: Deleted {$count} unverified users older than {$TIME} minutes.");
				return [
					'flag' => 1,
					'message' => "Cull completed: Deleted {$count} unverified users older than {$TIME} minutes."
				];
			}

			Log::info('Cull check: No unverified users found to delete.');
			return [
				'flag' => 1,
				'message' => "Cull check: No unverified users found to delete."
			];
		} catch (Exception $e) {
			Log::error('❌ Error while culling pending users: ' . $e->getMessage(), [
				'line' => $e->getLine(),
				'file' => $e->getFile(),
			]);

			return [
				'flag' => 0,
				'message' => "Error while culling pending users"
			];
		}
	}
	// ---------------CULL UNATTENDED USERS---------------


	// --------------- EXPORTS/IMPORTS ---------------

	public function exportExcelAllUsers($fileType)
	{
		switch ($fileType) {
			case 'xlsx':
				return Excel::download(new UsersExport, 'user-sheet.xlsx', \Maatwebsite\Excel\Excel::XLSX);

			case 'csv':
				return Excel::download(new UsersExport, 'user-sheet.csv', \Maatwebsite\Excel\Excel::CSV);

			default:
				abort(400, 'Invalid file type.');
		}
	}

	public function importExcelAllUsers(Request $request)
	{
		try {
			Excel::import(new UsersImport, request()->file('file'));

			return redirect()->route('backend_all_user')->with('success', 'Data imported successfully!');
		} catch (Throwable $e) {
			return redirect()->back()->with('error', "Failed to import." . $e->getMessage());
		}
	}
}
