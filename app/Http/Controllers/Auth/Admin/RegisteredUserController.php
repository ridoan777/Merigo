<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Helpers\UidGenerator;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('Auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // /*
            return redirect()->back()->with('error', 'You are not authorized to perform this action. Contact Admins!');
        // */
        // /*
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try{
            $user = User::create([
                'user_uid' => UidGenerator::uniqueName($request->name, 9, 4),
                'name' => $request->name,
                'birth_date' => '1991-01-25',
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_role' => "admin",
            ]);

            event(new Registered($user));
    
            Auth::login($user);
    
            return redirect(route('dashboard', absolute: false));
        } catch (TransportExceptionInterface $e)
        {
            return back()->with('error', "Oops! Email verification process failed. Please try again.");
        }
        // */

    }
}
