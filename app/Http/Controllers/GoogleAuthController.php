<?php


namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // 1. Try to find the user by their google_id
            $user = User::where('google_id', $googleUser->getId())->first();

            if ($user) {
                // User exists with this google_id, update their name and avatar if changed
                $user->update([
                    'name' => $googleUser->getName(),
                    'avatar' => $googleUser->getAvatar(),
                    // Optionally, you could update the email if it can change and you handle uniqueness
                    // 'email' => $googleUser->getEmail(),
                ]);
            } else {
                // No user found with this google_id. Let's check if a user with this email already exists.
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    // User exists with this email, but no google_id was set.
                    // Update this existing user to link their google_id and refresh details.
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'name' => $googleUser->getName(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                } else {
                    // No user found by google_id or by email, so create a new user.
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        // 'password' column should be nullable (as per our migration earlier)
                        // or set a default random password if not nullable:
                        // 'password' => Hash::make(Str::random(24)) 
                    ]);
                }
            }

            Auth::login($user, true); // Log in the user

            return redirect()->route('liquor.index'); // Redirect to the liquor inventory page

        } catch (Exception $e) {
            Log::error('Google Login Callback Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Provide a more user-friendly message but log the details
            return redirect('/')->with('error', 'Login with Google failed due to an issue. Please try again or contact support if the problem persists.');
        }
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have been logged out successfully.');
    }
}