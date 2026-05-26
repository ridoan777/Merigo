<?php

namespace App\Http\Controllers\Auth\Api;

use App\Domain\Referrals\Services\ReferralManagementService;
use App\Helpers\{ApiJsonReturnHelper, Auth\SocialAuthVerify, UidGenerator, FileHelpers\FileManagement, Notifications\InAppNotificationHelper, System\UserPreferenceHelper};
use App\Helpers\Errors\ExceptionHandling;
use App\Helpers\Ui\ConvertDynamicTimezone;
use App\Http\Controllers\Controller;
use App\Models\System\Settings\UserAppPreference;
use App\Models\Users\{User, UserVerification};
use App\Notifications\SendOTPNotification;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, Storage};
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Throwable;

class ApiAuthController extends Controller
{
	private int $OTP_EXPIRY_DURATION = 15; // in minutes
	// ----------------- REGISTRATION -----------------

	public function register(Request $request)
	{
		// dd($request->all());
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'user_role' => ['required', 'string', Rule::in(['guest', 'student', 'bar_admin'])],
			'email' => 'required|email|unique:users,email',
			'password' => ['required', 'confirmed', 'min:8'],
			'phone' => ['nullable', 'string', 'min:4'],
			'city' => ['required', 'string', 'min:3'],
			'referral_code' => ['nullable', 'string', 'exists:users,own_referral_code'],
			'gender' => ['required', 'string', 'in:male,female,prefer_not'],
		], [
			'email.unique' => "The email has already been taken. Or, wasn\'t verified yet. In that case, try resending OTP.",
			'user_role.in' => "Failed to assign a role to the user. Please try again or contact support.",
		]);
		try {
			$SELECTED_ROLE = strtolower($request->user_role) ?? 'guest';
			$role = Role::where('role_key', $SELECTED_ROLE)->firstOrFail();

			DB::beginTransaction();

			$user = User::updateOrCreate(
				[
					'email' => $validated['email'],
				],
				[
					'created_by' => null,
					'user_uid' => UidGenerator::uniqueULID($validated['name'], 8, 15, 4),
					'username' => UidGenerator::uniqueUsername($validated['name'], 8, 4),
					'name' => $validated['name'],
					'user_role' => $role?->name ?? 'guest',	// won't sync untill verified
					'password' => Hash::make($validated['password']),
					'phone' => $validated['phone'] ?? null,
					'gender' => $validated['gender'] ?? "prefer_not",
					'city' => $validated['city'],
					'own_referral_code' => UidGenerator::uniqueULID("REFF", 5, 15, 4),
					'invited_referral_code' => $validated['referral_code'],
					'status' => 0, // inactive until verified
				]
			);

			$otp = UserVerification::generateOTP();

			UserVerification::create([
				'user_id' => $user->id,
				'type' => 'email_verification',
				'otp' => $otp,
				'expiry_duration' => $this->OTP_EXPIRY_DURATION ?? null,
				'expires_at' => Carbon::now()->addMinutes($this->OTP_EXPIRY_DURATION),
			]);

			$user->notify(new SendOTPNotification($otp, 'email_verification', $this->OTP_EXPIRY_DURATION));

			DB::commit();

			return ApiJsonReturnHelper::handle(true, 201, 'Registration successful. Please check your email for OTP.', [
				'user_uid' => $user->user_uid ?? null,
				'email' => $user->email,
				'otp_visibility' => $this->otpVisibility($otp),
			]);
		} catch (Throwable $e) {
			DB::rollBack();
			return ExceptionHandling::handle('registering a user', $e);
		}
	}

	// ----------------- VERIFY EMAIL -----------------

	public function verifyEmail(Request $request)
	{
		$validated = $request->validate([
			'email' => 'required|email|exists:users,email',
			'otp' => 'required|string|size:4',
		]);

		$user = User::where('email', $validated['email'])->first();
		if (!$user) {
			ExceptionHandling::bailout(404, "You have not registered yet!");
		}

		$SELECTED_ROLE = $user?->user_role ? strtolower($user?->user_role) : 'guest';
		$role = Role::where('role_key', $SELECTED_ROLE)->firstOrFail();

		if ($user->email_verified_at) {
			return ApiJsonReturnHelper::handle(false, 409, 'Email already verified.', null);
		}

		$verification = UserVerification::where('user_id', $user->id)->type('email_verification')->otp($validated['otp'])->first();

		if (!$verification) {
			return ApiJsonReturnHelper::handle(false, 422, 'Invalid OTP.', null);
		}

		if ($verification->isExpired()) {
			return ApiJsonReturnHelper::handle(false, 409, 'OTP has expired.', null);
		}

		// ----------------- REFERRAL SYSTEM -----------------
		$refer = new ReferralManagementService();
		logger('1-verify email, proceeding referral');
		$referral = $refer->handle($user, $user->invited_referral_code) ?? null;
		// ----------------- REFERRAL SYSTEM -----------------
		// /*
		$user->forceFill([
			'email_verified_at' => Carbon::now(),
			'user_role' => strtolower($role?->role_key),
			'own_referral_code' => UidGenerator::uniqueULID("REFF", 5, 15, 4),
			'status' => 1,
		])->save();

		$user->syncRoles([$role?->name]);

		$verification->delete();

		// ----------------- SAVE SETTINGS PREFERENCE -----------------
		UserPreferenceHelper::newRegistration($user);
		// ----------------- SAVE SETTINGS PREFERENCE -----------------
		// */


		return ApiJsonReturnHelper::handle(true, 200, 'Email verified successfully.', [
			'referral' => empty($user->invited_referral_code) ? null : ($referral ? "Your referral bonus has been added to your Merigo wallet." : "Failed to use the referral. Try again or use a valid/new refer code.")
		]);
	}

	// ----------------- RESEND OTP -----------------

	public function resendOTP(Request $request)
	{
		$validated = $request->validate([
			'email' => 'required|email|exists:users,email',
			'type' => 'required|in:email_verification,password_reset',
		]);

		$user = User::where('email', $validated['email'])->first();

		if ($validated['type'] === 'email_verification' && $user->email_verified_at) {
			return ApiJsonReturnHelper::handle(false, 409, 'Email already verified.', null);
		}

		// ------------- OTP Spam Handling ------------- 
		$lastOtp = UserVerification::where('user_id', $user->id)->where('type', $validated['type'])->latest('created_at')->first();

		if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
			return ApiJsonReturnHelper::handle(false, 429, 'Please wait a minute before requesting a new OTP.', null);
		}
		// ------------- OTP Spam Handling ------------- 


		// Delete old OTPs
		UserVerification::where('user_id', $user->id)->where('type', $validated['type'])->delete();

		// Generate new OTP
		$otp = UserVerification::generateOTP();

		UserVerification::create([
			'user_id' => $user->id,
			'type' => $validated['type'],
			'otp' => $otp,
			'expiry_duration' => $this->OTP_EXPIRY_DURATION ?? null,
			'expires_at' => Carbon::now()->addMinutes($this->OTP_EXPIRY_DURATION),
		]);

		$user->notify(new SendOTPNotification($otp, $validated['type'], $this->OTP_EXPIRY_DURATION));

		return ApiJsonReturnHelper::handle(true, 200, 'OTP sent successfully.', $this->otpVisibility($otp));
	}

	// ----------------- LOGIN -----------------

	public function login(Request $request)
	{
		$validated = $request->validate([
			'login' => 'required|string', // can be email or phone or username
			'password' => 'required|string',
		]);
		$zones = ConvertDynamicTimezone::handle();

		$user = null;
		$login = $validated['login'];
		$loginType = null;

		if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
			$user = User::where('email', $login)->first();
			$loginType = "email";
		} else {
			$user = User::where(
				fn($q) =>
				$q->where('phone', $login)
					->orWhere('username', $login)
			)->first();
			$loginType = 'phone or username';
		}

		// do not use user not found.
		if (!$user || !Hash::check($validated['password'], $user->password)) {
			return ApiJsonReturnHelper::handle(false, 401, 'Invalid credentials.', null);
		}

		if (!$user->email_verified_at) {
			return ApiJsonReturnHelper::handle(false, 409, 'Please verify your email first', null);
		}

		if (!$user->status) {
			return ApiJsonReturnHelper::handle(false, 403, 'Your account is inactive. Contact Support.', null);
		}

		$device = $request->header('User-Agent', 'Unknown Device') . '_' . $request->ip() ?? 'unknown-device';

		// Delete any previous token for this device
		$user->tokens()->where('name', $user->name)->where('device', $device)->delete();
		$avatarUrl = $user->avatar ? Storage::url($user->avatar) : null;

		// $token = $user->createToken($user->name);
		$token = $user->createToken($user->name, ['*'], Carbon::now()->addMinutes(config('sanctum.expiration')));

		$token->accessToken->update([
			'device' => $device,
		]);
		// ------------------------ IN-APP NOTIFICATION ------------------------
		$IAN = InAppNotificationHelper::createInAppNotify($user, 'api_user_login', 'login', 'alert', 'Your account was logged-in at ' . Carbon::now('UTC')->setTimezone($user->timezone ?? 'UTC')->toDayDateTimeString(), $user->name, $user->avatar_url, null, null);
		// ------------------------ IN-APP NOTIFICATION ------------------------

		return response()->json([
			'status' => true,
			'message' => 'Login successful',
			'device' => $device,
			'token' => $token->plainTextToken,
			'login_type' => $loginType,
			'user' => $user,
			'avatarUrl' => $avatarUrl,
			'i_a_n' => $IAN,
			'code' => 200
		], 200);
	}

	// ----------------- LOGOUT -----------------

	public function logout(Request $request)
	{
		$name = '';
		$user_role = '';
		try {
			$user = $request->user();
			$device = $request->header('User-Agent', 'Unknown Device') . '_' . $request->ip() ?? 'unknown-device';

			// $request->user()->tokens()->where('name', $request->user()->name)->delete();
			$name = $request->user()->name ?? "Unknown User";
			$user_role = ucfirst($request->user()?->user_role ?? '');

			// $request->user()->currentAccessToken()->delete();

			/** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
			if ($token = $request->user()->currentAccessToken())
				$token->delete();

			return ApiJsonReturnHelper::handle(true, 200, "{$user_role} {$name} Logged out successfully", $device);
		} catch (Throwable $e) {
			Log::error('Logout failed: ' . $e->getMessage(), [
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString(),
			]);
			return ExceptionHandling::handle('logging out. Check network or try again.', $e);
		}
	}

	// ----------------- FORGOT PASSWORD -----------------
	public function forgotPassword(Request $request)
	{
		$validated = $request->validate([
			'email' => 'required|email|exists:users,email',
		]);

		$user = User::where('email', $validated['email'])->whereNotNull('email_verified_at')->first();

		if (!$user) {
			return ApiJsonReturnHelper::handle(false, 403, 'The user either does not exist or this account is not verified.', null);
		}

		// ------------- OTP Spam Handling ------------- 
		$lastOtp = UserVerification::where('user_id', $user->id)
			->where('type', 'password_reset')
			->latest('created_at')
			->first();

		if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
			return ApiJsonReturnHelper::handle(false, 429, 'Please wait a minute before requesting a new OTP.', null);
		}
		// ------------- OTP Spam Handling ------------- 

		UserVerification::where('user_id', $user->id)
			->where('type', 'password_reset')
			->delete();

		$otp = UserVerification::generateOTP();

		UserVerification::create([
			'user_id' => $user->id,
			'type' => 'password_reset',
			'otp' => $otp,
			'expiry_duration' => $this->OTP_EXPIRY_DURATION ?? null,
			'expires_at' => Carbon::now()->addMinutes($this->OTP_EXPIRY_DURATION),
		]);

		$user->notify(new SendOTPNotification($otp, 'password_reset', $this->OTP_EXPIRY_DURATION));

		return ApiJsonReturnHelper::handle(true, 200, 'Password reset OTP sent to your email.', $this->otpVisibility($otp));
	}

	// ----------------- VERIFY PASSWORD RESET OTP -----------------
	public function verifyPasswordResetOTP(Request $request)
	{
		$validated = $request->validate([
			'email' => 'required|email|exists:users,email',
			'otp' => 'required|string|size:4',
		]);

		$user = User::where('email', $validated['email'])->whereNotNull('email_verified_at')->first();
		if (!$user) {
			return ApiJsonReturnHelper::handle(false, 403, 'The user either does not exist or account is not verified.', null);
		}

		$verification = UserVerification::where('user_id', $user->id)->where('type', 'password_reset')
			->where('otp', $validated['otp'])->first();

		if (!$verification) {
			return ApiJsonReturnHelper::handle(false, 422, 'Invalid OTP.', null);
		}

		if ($verification->isExpired()) {
			return ApiJsonReturnHelper::handle(false, 409, 'OTP has expired.', null);
		}

		return ApiJsonReturnHelper::handle(true, 200, 'OTP verified successfully.', null);
	}

	// ----------------- RESET PASSWORD -----------------
	public function resetPassword(Request $request)
	{
		try {
			$validated = $request->validate([
				'email' => 'required|email|exists:users,email',
				'otp' => 'required|string|size:4',
				'password' => [
					'required',
					'confirmed',
					'string',
					'min:8',
					'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'
				],
			], [
				'password.min' => 'Password must be at least 8 characters long.',
				'password.regex' => 'Password must contain at least one letter and one number.',
			]);

			$user = User::where('email', $validated['email'])->whereNotNull('email_verified_at')->first();
			if (!$user) {
				return ApiJsonReturnHelper::handle(false, 403, 'The user either does not exist or account is not verified.', null);
			}

			$verification = UserVerification::where('user_id', $user->id)
				->where('type', 'password_reset')
				->where('otp', $validated['otp'])
				->first();

			if (!$verification) {
				return ApiJsonReturnHelper::handle(false, 422, 'Invalid OTP.', null);
			}

			if ($verification->isExpired()) {
				return ApiJsonReturnHelper::handle(false, 409, 'OTP has expired.', null);
			}

			$user->update(['password' => $validated['password']]);

			$verification->delete();

			$user->tokens()->delete();

			// ------------------------ IN-APP NOTIFICATION ------------------------
			$IAN = InAppNotificationHelper::createInAppNotify($user, 'apiAuth_password_reset', 'password', 'alert', 'Your password was reset at ' . now()->toDayDateTimeString(), $user->name, $user->avatar_url, null, null);

			// ------------------------ IN-APP NOTIFICATION ------------------------


			return ApiJsonReturnHelper::handle(true, 200, 'Password reset successfully.', null);
		} catch (Throwable $e) {
			Log::error('Password reset failed: ' . $e->getMessage(), [
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString(),
			]);
			return ExceptionHandling::handle('resetting your password', $e);
		}
	}

	// ----------------- HELPER METHODS -----------------
	private function otpVisibility($otp = null)
	{
		return [
			'otp' => app()->environment(['local', 'staging']) ? $otp : null,
			'otp_expiry_duration' => $this->OTP_EXPIRY_DURATION ? $this->OTP_EXPIRY_DURATION . " min" : null
		];
	}
}
