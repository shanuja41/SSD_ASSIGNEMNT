<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * "Sign in with Google" via OpenID Connect / OAuth 2.0 Authorization Code Flow.
 *
 * Google is used purely as an external identity assertion (a verified email).
 * The local `users` table stays the single source of truth for account
 * existence, role, school_id and status — this controller only ever
 * authenticates an EXISTING local user, never creates or elevates one.
 */
class GoogleAuthController extends Controller
{
    /**
     * Step 1 — redirect the browser to Google's authorization endpoint.
     * Socialite generates and stores a random `state` value in the session
     * (CSRF protection for the OAuth flow) and requests it back in step 2.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    /**
     * Step 2 — Google redirects back here with a one-time authorization
     * `code`. Socialite exchanges it server-side (with the client secret)
     * for tokens and the verified OIDC identity; the code/tokens never
     * pass through the browser as user-controlled input we trust directly.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // The `state` round-trip didn't match — possible CSRF/replay
            // attempt, or the sign-in link was reused/expired.
            return redirect()->route('login')->withErrors([
                'email' => 'Your Google sign-in request expired or was invalid. Please try again.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Unable to complete Google sign-in. Please try again.',
            ]);
        }

        // Google's userinfo response marks whether the email is verified.
        // Refuse identities Google itself hasn't verified.
        $raw = $googleUser->getRaw();
        $emailVerified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;
        $email = $googleUser->getEmail();

        if (! $email || ! $emailVerified) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your Google account email could not be verified.',
            ]);
        }

        // Look up the EXISTING local account by the Google-verified email.
        // We never create an account here — Google only proves identity,
        // it does not grant access by itself.
        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'No account is registered for this Google email address. Please contact the administrator.',
            ]);
        }

        // Mirror the account-status enforcement that would normally gate
        // access; the standard email/password path does not currently
        // check this either, so this is a defensive addition specific to
        // the external-identity path.
        if ($user->status !== 'active') {
            return redirect()->route('login')->withErrors([
                'email' => 'This account is not active. Please contact the administrator.',
            ]);
        }

        Auth::login($user);

        // Prevent session fixation now that the session's privilege level changed.
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        activity()
            ->causedBy($user)
            ->withProperties([
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method'     => 'google-oidc',
            ])
            ->log('User logged in via Google');

        return redirect()->route('dashboard');
    }
}
