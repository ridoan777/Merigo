<?php

namespace App\Http\Controllers\Auth\Api;

use App\Enums\TokenAbility;
use App\Helpers\{ApiJsonReturnHelper, Auth\SocialAuthVerify, Errors\ExceptionHandling, UidGenerator, FileHelpers\FileManagement, Notifications\InAppNotificationHelper, System\UserPreferenceHelper};
use App\Helpers\Ui\ConvertDynamicTimezone;
use App\Http\Controllers\Controller;
use App\Models\System\Settings\UserAppPreference;
use App\Models\Users\{User, UserVerification};
use App\Notifications\SendOTPNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, Storage};
use Throwable;

class ApiAuthRefreshController extends Controller
{
	private int $OTP_EXPIRY_DURATION = 15; // in minutes
	// ----------------- REGISTRATION -----------------

	public function register(Request $request)
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			// 'user_role' => 'required|string|in:user',
			'email' => 'required|email|unique:users,email',
			'password' => 'required|confirmed|string|min:8',
		], [
			'email.unique' => 'The email has already been taken. Or, wasn\'t verified yet. In that case, try resending OTP.',
		]);
		try {
			DB::beginTransaction();

			$user = User::updateOrCreate(
				[
					'email' => $validated['email'],
				],
				[
					'user_uid' => UidGenerator::uniqueName($request->name),
					'name' => $validated['name'],
					'user_role' => $validated['user_role'] ?? 'user',
					'password' => Hash::make($validated['password']),
					'status' => 0, // inactive until verified
				]
			);

			// Generate and send OTP
			$otp = UserVerification::generateOTP();

			UserVerification::create([
				'user_id' => $user->id,
				'type' => 'email_verification',
				'otp' => $otp,
				'expires_at' => Carbon::now()->addMinutes($this->OTP_EXPIRY_DURATION),
			]);

			$user->notify(new SendOTPNotification($otp, 'email_verification', $this->OTP_EXPIRY_DURATION));

			DB::commit();

			return ApiJsonReturnHelper::handle(true, 201, 'Registration successful. Please check your email for OTP.', [
				'user_uid' => $user->user_uid ?? null,
				'email' => $user->email,
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

		if ($user->email_verified_at) {
			return ApiJsonReturnHelper::handle(false, 409, 'Email already verified.', null);
		}

		$verification = UserVerification::where('user_id', $user->id)
			->where('type', 'email_verification')
			->where('otp', $validated['otp'])
			->first();

		if (!$verification) {
			return ApiJsonReturnHelper::handle(false, 422, 'Invalid OTP.', null);
		}

		if ($verification->isExpired()) {
			return ApiJsonReturnHelper::handle(false, 409, 'OTP has expired.', null);
		}

		$user->forceFill([
			'email_verified_at' => Carbon::now(),
			'status' => 1,
		])->save();

		$verification->delete();

		// ----------------- SAVE SETTINGS PREFERENCE -----------------
		UserPreferenceHelper::newRegistration($user);
		// ----------------- SAVE SETTINGS PREFERENCE -----------------

		return ApiJsonReturnHelper::handle(true, 200, 'Email verified successfully.', null);
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
			'expires_at' => Carbon::now()->addMinutes(10),
		]);

		$user->notify(new SendOTPNotification($otp, $validated['type'], $this->OTP_EXPIRY_DURATION));

		return ApiJsonReturnHelper::handle(true, 200, 'OTP sent successfully.', null);
	}

	// ----------------- LOGIN -----------------
	public function login(Request $request)
	{
		$validated = $request->validate([
			'login' => 'required|string', // can be email or phone
			'password' => 'required|string',
		]);
		$zones = ConvertDynamicTimezone::handle();

		// return ApiJsonReturnHelper::handle(false, 403, 'Just checking...', $validated);

		$loginType = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

		$user = User::where($loginType, $validated['login'])->first();

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

		// ------------------------ TOKEN MANAGEMENT ------------------------
		$tokens = $this->tokenManagement($user, $device);
		// ------------------------ TOKEN MANAGEMENT ------------------------

		// ------------------------ IN-APP NOTIFICATION ------------------------
		$IAN = InAppNotificationHelper::createInAppNotify($user, 'api_user_login', 'login', 'alert', 'Your account was logged-in at ' . Carbon::now('UTC')->setTimezone($user->timezone ?? 'UTC')->toDayDateTimeString() . " from a device {$device}", $user->name, $user->avatar_url, null, null, true);
		// ------------------------ IN-APP NOTIFICATION ------------------------

		return $this->respondWithTokens($request, $user, $tokens['access_token'], $tokens['refresh_token'], $device, 'Login successful');
	}

	// ----------------- REFRESH_TOKEN -----------------
	public function refreshToken(Request $request)
	{
		// $user = Auth::user();
		$user = $request->user();

		if (!$request->user()->currentAccessToken()->can(TokenAbility::REFRESH_ACCESS_TOKEN->value)) {
			return ApiJsonReturnHelper::handle(false, 403, 'Invalid token type for refresh.', null);
		}

		$device = $request->header('User-Agent', 'Unknown Device') . '_' . $request->ip() ?? 'unknown-device';

		// $saveAccessToken = $user->currentAccessToken();

		$tokens = $this->tokenManagement($user, $device);

		return $this->respondWithTokens($request, $user, $tokens['access_token'], $tokens['refresh_token'], $device, 'Token refreshed successfully');
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
			$user->tokens()->where('tokenable_id', $user->id)->where('device', $device)->delete();

			return ApiJsonReturnHelper::handle(true, 200, "{$user_role} {$name} Logged out successfully", $device)->withCookie(cookie()->forget('refresh_token_cookie'));
		} catch (Throwable $e) {
			return ExceptionHandling::handle('logging out. Check network or try again.', $e);
		}
	}

	public function logoutAllDevices(Request $request)
	{
		$name = '';
		try {
			$request->validate([
				'password' => 'required'
			]);

			$user = $request->user();
			$device = $request->header('User-Agent', 'Unknown Device') . '_' . $request->ip() ?? 'unknown-device';

			$name = $request->user()->name ?? "Unknown User";

			if (!Hash::check($request->password, $user->password)) {
				return ApiJsonReturnHelper::handle(false, 403, 'Invalid password', null);
			}

			$user->tokens()->delete();

			return ApiJsonReturnHelper::handle(true, 200, "User '{$name}' logged out of all devices successfully", $device)->withCookie(cookie()->forget('refresh_token_cookie'));
		} catch (Throwable $e) {
			return ExceptionHandling::handle('logging out of all devices. Check network or try again.', $e);
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
		$lastOtp = UserVerification::where('user_id', $user->id)->where('type', 'password_reset')->latest('created_at')->first();

		if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
			return ApiJsonReturnHelper::handle(false, 429, 'Please wait a minute before requesting a new OTP.', null);
		}
		// ------------- OTP Spam Handling ------------- 

		// Delete old password reset OTPs
		UserVerification::where('user_id', $user->id)->where('type', 'password_reset')->delete();

		$otp = UserVerification::generateOTP();

		UserVerification::create([
			'user_id' => $user->id,
			'type' => 'password_reset',
			'otp' => $otp,
			'expires_at' => Carbon::now()->addMinutes(10),
		]);

		$user->notify(new SendOTPNotification($otp, 'password_reset', $this->OTP_EXPIRY_DURATION));

		return ApiJsonReturnHelper::handle(true, 200, 'Password reset OTP sent to your email.', null);
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
				'password' => 'required|confirmed|string|min:8',
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

			$user->tokens()->delete();	// deletes all sessions. Must not change

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

	// ----------------- HELPERS -----------------

	protected function tokenManagement($user, $device)
	{
		// $user->currentAccessToken()->delete();
		$user->tokens()->where('tokenable_id', $user->id)->where('device', $device)->delete();

		$access_token = $user->createToken($user->name, [TokenAbility::ACCESS_PROTECTED_API->value], Carbon::now()->addMinutes(config('sanctum.access_token_expiration')));

		$refresh_token = $user->createToken($user->name, [TokenAbility::REFRESH_ACCESS_TOKEN->value], Carbon::now()->addMinutes(config('sanctum.refresh_token_expiration')));

		$access_token->accessToken->update([
			'token_type' => "access-token",
			'device' => $device,
		]);

		$refresh_token->accessToken->update([
			'token_type' => "refresh-token",
			'device' => $device,
		]);

		return [
			'access_token' => $access_token,
			'refresh_token' => $refresh_token,
		];
	}

	protected function respondWithTokens($request, $user, $access_token, $refresh_token, $device, $message)
	{
		$isMobile = $request->expectsJson() && !$request->hasCookie('refresh_token_cookie');

		$response = [
			'status' => true,
			'message' => $message,
			'device' => $device,
			'access_token' => $access_token->plainTextToken,
			'user' => $user,
			'code' => 200
		];

		if ($isMobile) {
			$response['refresh_token'] = $refresh_token->plainTextToken;
		}

		return response()->json($response, 200)->cookie(
			'refresh_token_cookie',		// cookie name key
			$refresh_token->plainTextToken,	// value
			config('refresh_token_expiration'),	// cookie TTL in minutes
			'/',	// which route/path can access this cookie. '/' means entire site
			null,	// domain. Already handled by SESSION_DOMAIN=127.0.0.1. null = currentDomain
			false,	// secure. only send over https? true = production
			true,		// httpOnly. JS cannot access cookie, XSS protection
			false,	// raw. whether to encode value.
			'Lax'		// sameSite, Cross-site sending. Lax=safe default, Strict, None = only https
		);
	}

}
