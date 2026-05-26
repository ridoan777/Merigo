<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Helpers\{Errors\ExceptionHandling, UidGenerator, FileHelpers\FileManagement};
use App\Http\Controllers\Controller;
use App\Models\Users\{User, UserVerification};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Socialite;
use Throwable;

class WebSocialAuthController extends Controller
{
    public function socialRedirect(Request $request, string $provider)
    {
        abort(403, "Unauthorized");

        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            return redirect()->route('login')->with('errors', "Invalid provider");
        }
        try {
            return Socialite::driver($provider)->with(['prompt' => 'select_account consent'])->redirect();

        } catch (Throwable $e) {
            return ExceptionHandling::handle("attempting social login via {$provider}", $e);
        }
    }

    public function socialCallback(Request $request, string $provider)
    {
        abort(403, "Unauthorized");
        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            return redirect()->route('login')->with('errors', "Invalid provider");
        }
        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = User::updateOrCreate([
                'social_provider_id' => $socialUser->id,
                'social_provider_name' => $provider,
            ], [
                'user_uid' => UidGenerator::uniqueName($socialUser->name),
                'user_role' => 'student',
                'email_verified_at' => now(),
                'name' => $socialUser->name,
                'email' => $socialUser->email,
                'phone' => $socialUser->phone ?? null,
                // 'avatar' => $socialUser->avatar ?? null,
                // 'username' => '',
                'social_token' => $socialUser?->refreshToken ? encrypt($socialUser?->refreshToken) : null,
                'social_refresh_token' => $socialUser?->social_refresh_token ? encrypt($socialUser?->social_refresh_token) : null,
            ]);
            
            // ------------------ AVATAR STORING -------------------
            $FILE_FOLDER = $user['user_role'] === 'admin' ? "user-avatars/admins" : "user-avatars/";
            $DISK_FOLDER = config('filesystems.default');
            
            // ----------------------- FILE HANDLING ---------------------------
            DB::beginTransaction();
            $existingAvatar = $user?->avatar ?? null;
            if ($socialUser?->avatar) {
                $avatar = FileManagement::socialLoginAvatar(
                    $socialUser->avatar,
                    $existingAvatar,
                    $user->name ?? 'no-name',
                    $FILE_FOLDER,
                    $DISK_FOLDER,
                );
            }
            if ($avatar) {
                $user->avatar = $avatar['path'];
                $user->save();
            }
            DB::commit();
            // ------------------ AVATAR STORING -------------------
            Auth::login($user);

            return redirect()->route('dashboard');

        } catch (Throwable $e) {
            DB::rollBack();
            return ExceptionHandling::handle("attempting social login via {$provider}", $e);
        }
    }
}
