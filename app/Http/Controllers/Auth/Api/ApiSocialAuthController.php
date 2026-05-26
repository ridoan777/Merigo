<?php

namespace App\Http\Controllers\Auth\Api;

use App\Helpers\{Errors\ExceptionHandling, UidGenerator, FileHelpers\FileManagement};
use App\Helpers\Auth\SocialAuthVerify;
use App\Http\Controllers\Controller;
use App\Models\Users\{User, UserVerification};
use Illuminate\Http\Request;
use Throwable;

class ApiSocialAuthController extends Controller
{
	public function mobileSocialLogin(Request $request, string $provider)
	{
		$supported = ['google', 'apple', 'facebook', 'github', 'x', 'linkedin'];
		if (!in_array($provider, $supported)) {
			return response()->json([
				'status' => false,
				'message' => 'Unsupported provider',
			], 400);
		}
		
		$request->validate([
			'id_token' => 'required|string',
			'user_role' => 'required|string',
		]);

		try {
			$socialUser = SocialAuthVerify::verifyProviderToken($provider, $request->id_token);

			$user = User::updateOrCreate(
				[
					'social_provider_name' => $provider,
					'social_provider_id' => $socialUser['id'],
				],
				[
					'user_uid' => UidGenerator::uniqueName($socialUser['name']),
					'user_role' => $request->user_role ?? 'user',
					'name' => $socialUser['name'] ?? null,
					'email' => $socialUser['email'] ?? null,
					'avatar' => $socialUser['avatar'] ?? null,
					'email_verified_at' => now(),
				]
			);

			$token = $user->createToken('mobile')->plainTextToken;

			return response()->json([
				'status' => true,
				'token' => $token,
				'user' => $user,
			]);
		} catch (Throwable $e) {
			return ExceptionHandling::handle('login via social', $e);
		}
	}
}
