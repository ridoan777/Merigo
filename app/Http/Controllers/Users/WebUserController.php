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
use App\Helpers\Auth\{DeleteUserAuth, SuperAdminOverride, VerifyEmailUpdate};
use Spatie\Permission\Models\Role;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Exports\UsersExport;
use App\Helpers\Errors\ExceptionHandling;
use App\Http\Requests\Users\WebUserStoreRequest;
use Exception;
use Throwable;

class WebUserController extends Controller implements HasMiddleware
{
	use AuthorizesRequests;
	private int $OTP_EXPIRY_DURATION = 15;
	public static function middleware(): array
	{
		return [
			new Middleware(PermissionMiddleware::using(['user_index',]), only: ['all_users', 'indexAdminUsers', 'indexBlackListedUsers', 'indexPendingUsers']),
			new Middleware(PermissionMiddleware::using(['user_show']), only: ['single_user']),
			new Middleware(PermissionMiddleware::using(['user_create', 'user_update']), only: ['store']),
			new Middleware(PermissionMiddleware::using(['user_delete']), only: ['deleteUser', 'bulkDeleteUser']),
		];
	}
	// --------------- USERS ---------------

	public function index()
	{
		return view('Admin.sidebar.Users.all_users');
	}


	public function show(User $user)
	{
		$roles = Role::all();
		$verification = UserVerification::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
		$settingsPreference = UserAppPreference::where('user_id', $user->id)->first();

		return view('Admin.sidebar.Users.single_user', compact('user', 'verification', 'roles', 'settingsPreference'));
	}


	public function delete(Request $request, User $user)
	{
		try {
			$CURRENT_USER = $request->user();

			$canDelete = DeleteUserAuth::canIDelete($user, $CURRENT_USER);
			if (!$canDelete['canDelete']) {
				ExceptionHandling::bailout($canDelete['statusCode'], $canDelete['message']);
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


	public function bulkDelete(Request $request, $flag)
	{
		try {
			$CURRENT_USER = $request->user();
			$ids = $request->ids ?? [];

			if (empty($ids)) {
				return redirect()->back()->with('error', 'No users selected.');
			}

			$MESSAGE = "Selected users deleted.";
			$DISK_FOLDER = config('filesystems.default');
			$SKIPPED = [];
			$deleted = 0;

			DB::beginTransaction();
			foreach ($ids as $id) {
				$user = User::find($id);

				if (!$user) {
					$SKIPPED[] = "ID: {$id}";
					continue;
				}

				// --------------- AUTH CHECKING ---------------
				$canDelete = DeleteUserAuth::canIDelete($user, $CURRENT_USER);
				if (!$canDelete['canDelete']) {
					$SKIPPED[] = $user->name;
					continue;
				}
				// --------------- AUTH CHECKING ---------------

				if (!empty($user->avatar)) {
					FileManagement::deleteFile($user->avatar, $DISK_FOLDER);
				}

				$user->delete();
				$deleted += 1;
			}
			DB::commit();

			$MESSAGE = $MESSAGE . "success: [{$deleted}], dispute/skipped: [" . count($SKIPPED) . "], SKIPPED Users: [" . implode(', ', $SKIPPED) . "]";

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
	// --------------- USERS ---------------


	// --------------- CREATE NEW USERS ---------------

	public function create()
	{
		$roles = Role::all();
		return view('Admin.sidebar.Users.create_user', compact('roles'));
	}


	public function store(WebUserStoreRequest $request)
	{
		try {
			$validated = $request->validated();
			$DISK_FOLDER = config('filesystems.default');
			$SELECTED_ROLE = null;
			$USER_UID = null;
			$USERNAME = null;

			$CURRENT_USER = $request->user();
			$TARGET_USER = null;
			$isTargetRoleRestricted = null;

			// ----------------------- EXISTANCE CHECKING -----------------------
			if (!empty($validated['id'])) {
				$TARGET_USER = User::find((int)$validated['id']);
				$USER_UID = $TARGET_USER?->user_uid;
				$USERNAME = $validated['username'] ?? $TARGET_USER?->username;

				$isTargetRoleRestricted = $TARGET_USER->hasRoleKey('admin', 'super_admin');
				$this->authorize('user_update', $CURRENT_USER);
			} else {
				$this->authorize('user_create', $CURRENT_USER);
				$USER_UID = UidGenerator::uniqueULID($validated['name'], 8, 15, 4);
				$USERNAME = UidGenerator::uniqueUsername($validated['name'], 8, 4);
			}
			$isSameUser = $TARGET_USER ? ((int)$CURRENT_USER->id === (int)$TARGET_USER->id) : null;
			// ----------------------- EXISTANCE CHECKING -----------------------

			// ----------------------- SUPER PERMISSIONS -----------------------
			$amIsuperAdmin = $CURRENT_USER ? $CURRENT_USER->hasRoleKey('super_admin') : false;

			$CAN_CHANGE_OWN_EMAIL = $isSameUser && config('app_permission_overridding.permissions.allowed_changing_own_email');
			$CAN_CHANGE_OWN_PASSWORD = $isSameUser && config('app_permission_overridding.permissions.allowed_changing_own_password');
			// ----------------------- SUPER PERMISSIONS -----------------------

			// ----------------------- ROLE ASSIGNMENT -----------------------
			$superAdminOverride = SuperAdminOverride::prevention($request, $CURRENT_USER, $TARGET_USER);

			if ($superAdminOverride['lockdownTrigger'] || (!$CURRENT_USER->hasRoleKey('super_admin') && $TARGET_USER?->hasRoleKey('super_admin'))) {
				return back()->with('error', !empty($superAdminOverride['message']) ? $superAdminOverride['message'] : "You don't have permission to modify a super admin!");
			}

			$SELECTED_ROLE = strtolower($request->user_role) ?? null;
			$isTargetRoleRestricted = $TARGET_USER ? $TARGET_USER->hasRoleKey('admin', 'super_admin') : (in_array($SELECTED_ROLE, ['admin', 'super_admin']));
			// ----------------------- ROLE ASSIGNMENT -----------------------

			DB::beginTransaction();
			// ----------------------- FILE HANDLING ---------------------------
			$FILE_FOLDER = $isTargetRoleRestricted ? "user-avatars/admins" : "user-avatars/";
			if (($request->hasFile('avatar') || $request->boolean('remove_image')) && (!$superAdminOverride['lockdownTrigger'] || $amIsuperAdmin)) {
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
			$emailChanged = null;
			$emailToChange = $TARGET_USER ? $TARGET_USER?->email : $validated['email'];
			$emailChangedCheck = !empty($validated['email']) && (!$TARGET_USER || ($validated['email'] !== $TARGET_USER->email));

			$passwordToStore = $TARGET_USER?->password;
			$passwordChanged = !empty($validated['password']);

			// ------------------- PASSWORD CLEARANCE -------------------
			if ($passwordChanged) {
				if ($amIsuperAdmin || $CAN_CHANGE_OWN_PASSWORD) {
					$passwordToStore = Hash::make($validated['password']);
					// i am the user or super_admin
				} else if (($TARGET_USER === null || !$isTargetRoleRestricted) || (!$TARGET_USER?->hasRoleKey('super_admin') && $CURRENT_USER->can('admin_password'))) {
					$passwordToStore = Hash::make($validated['password']);
					// new user * non-admins * can change admin-pass
				} else {
					return back()->with('error', "Only Super Admin & Admins with admin_password permission may change other admins' passwords.");
				}
			}
			// ------------------- PASSWORD CLEARANCE -------------------

			// ------------------- EMAIL CLEARANCE -------------------
			if ($emailChangedCheck) {
				if ($amIsuperAdmin || $CAN_CHANGE_OWN_EMAIL) {
					$emailToChange = $validated['email'];
					// i am the user or super_admin
				} else if ($isTargetRoleRestricted && $CURRENT_USER->can('admin_email')) {
					$emailChanged = VerifyEmailUpdate::updateEmail($TARGET_USER, $validated['email'], $this->OTP_EXPIRY_DURATION);
					// change admin
				} else if ($TARGET_USER === null || !$isTargetRoleRestricted) {
					$emailToChange = $validated['email'];
					// change non-admins
				} else {
					return back()->with('error', "only Super Admin & Admins with admin_email permissions may change other admins' emails.");
				}
			}
			// ------------------- EMAIL CLEARANCE -------------------

			// ----------------------- SAVE USER -------------------------------
			$ROLE = Role::where('role_key', $SELECTED_ROLE)->first();
			if (!$ROLE) {
				return back()->with('error', 'Invalid role selected.');
			}
			
			$SAVED_USER = User::updateOrCreate(
				[
					'id' => $validated['id'] ?? null
				],
				[
					'created_by' => $TARGET_USER ? $TARGET_USER->created_by : $CURRENT_USER->id,
					'user_uid' => $USER_UID,
					'username' => $USERNAME,
					'name' => $validated['name'],
					'user_role' => $ROLE->role_key,
					'email' => $emailToChange,
					'email_verified_at' => $emailChanged ? now() : ($TARGET_USER ? $TARGET_USER->email_verified_at : now()),
					'phone' => $validated['phone'] ?? null,
					'password' => $passwordToStore,
					'gender' => $validated['gender'] ?? null,

					'avatar' => $validated['avatar']['path'] ?? null,
					'status' => $validated['status'] ?? 1,
				]
			);
			// ----------------------- SAVE USER -------------------------------

			$SAVED_USER->syncRoles([$ROLE->name]);
			if (!$SAVED_USER->hasRole($ROLE->name)) {
				return back()->with('error', "The user role has not been synced yet. Select the role manually & try again!");
			}

			$message = $emailChanged ? "Profile updated. Please verify your new email using the OTP sent to {$validated['email']}." : "User '{$SAVED_USER->name}' is saved successfully!";

			DB::commit();
			return back()->with(['success' => $message, 'emailChanged' => $emailChanged]);
		} catch (Throwable $e) {
			DB::rollBack();
			return back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
		}
	}
	// --------------- CREATE NEW USERS ---------------


	// --------------- ALL ADMINS ---------------

	public function indexAdminUsers()
	{
		return view('Admin.sidebar.Users.all_admins');
	}
	// --------------- ALL ADMINS ---------------


	// --------------- BLACKLISTS ---------------

	public function indexBlackListedUsers(Request $request)
	{
		return view('Admin.sidebar.Users.blacklist_users');
	}


	public function toggle(Request $request, User $user)
	{
		try {
			$CURRENT_USER = $request->user();
			$isSameUser = $user ? ((int)$CURRENT_USER->id === (int)$user->id) : null;

			if ($isSameUser || ($user->hasRoleKey('admin', 'super_admin') && !$CURRENT_USER->hasRoleKey('super_admin'))) {
				return ExceptionHandling::bailout(403, "You are not authorized to toggle that user!");
			}
			$user->update(['status' => !$user->status]);
			return response()->json(['success' => true]);
		} catch (Exception $e) {
			Log::error('Blacklist toggle failed: ' . $e->getMessage(), ['user_id' => $user->id ?? null]);
			return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
		}
	}
	// --------------- BLACKLISTS ---------------


	// --------------- PENDING ---------------

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

	// --------------- PENDING---------------


	// ---------------CULL UNATTENDED USERS---------------


	/**
 * @method void deleteUser(\App\Models\Users\User $user)
 */
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
					app(static::class)->delete($user);
				}

				Log::info("Cull complete: Deleted {$count} unverified users older than {$TIME} minutes.");
				return [
					'flag' => 1,
					'message' => "Cull completed: Deleted {$count} unverified users older than {$TIME} minutes."
				];
			}

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
	// --------------- CULL UNATTENDED USERS ---------------


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
