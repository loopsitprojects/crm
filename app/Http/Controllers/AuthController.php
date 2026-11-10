<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Setting;
use App\Models\User;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    public function showLogin()
    {
        $mode = (int) Setting::get('maintenance_mode', 0);

        if (Auth::check()) {
            $user = Auth::user();

            // If system is in maintenance mode, check if logged in user is authorized
            if ($mode == 1 && !($user->hasRole('IT Admin') || $user->hasAdminPrivileges())) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
                return redirect()->route('maintenance');
            }

            if ($mode == 2 && !$user->hasRole('IT Admin')) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
                return redirect()->route('maintenance');
            }

            return redirect()->route('dashboard');
        }

        return view('auth.login', compact('mode'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $mode = (int) Setting::get('maintenance_mode', 0);

        // If maintenance mode is active, check if user is authorized before processing
        if ($mode == 1 || $mode == 2) {
            $targetUser = User::where('email', $credentials['email'])->first();

            $isAllowed = false;
            if ($targetUser) {
                if ($mode == 1 && ($targetUser->hasRole('IT Admin') || $targetUser->hasAdminPrivileges())) {
                    $isAllowed = true;
                } elseif ($mode == 2 && $targetUser->hasRole('IT Admin')) {
                    $isAllowed = true;
                }
            }

            // If user is not authorized during maintenance mode, immediately show maintenance page
            if (!$isAllowed) {
                return redirect()->route('maintenance');
            }
        }

        // Default remember to true so mobile PWA and desktop sessions persist across app restarts
        $remember = $request->boolean('remember', true);

        if (Auth::attempt($credentials, $remember)) {
            // Extra safety verification post-auth
            if ($mode == 1 || $mode == 2) {
                $user = Auth::user();
                $isAllowed = false;
                if ($mode == 1 && ($user->hasRole('IT Admin') || $user->hasAdminPrivileges())) {
                    $isAllowed = true;
                } elseif ($mode == 2 && $user->hasRole('IT Admin')) {
                    $isAllowed = true;
                }

                if (!$isAllowed) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('maintenance');
                }
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Get Google OAuth configuration from services, env, or database settings.
     */
    protected function getGoogleConfig(): array
    {
        $dbClientId = class_exists(Setting::class) ? Setting::get('google_client_id') : null;
        $clientId = !empty($dbClientId) ? $dbClientId : (config('services.google.client_id') ?: env('GOOGLE_CLIENT_ID'));

        $dbClientSecret = class_exists(Setting::class) ? Setting::get('google_client_secret') : null;
        $clientSecret = !empty($dbClientSecret) ? $dbClientSecret : (config('services.google.client_secret') ?: env('GOOGLE_CLIENT_SECRET'));

        $host = request()->getHost();

        // When running locally on 127.0.0.1 or localhost, dynamically use the current local host & port
        if (in_array($host, ['localhost', '127.0.0.1'])) {
            $redirect = request()->getSchemeAndHttpHost() . '/auth/google/callback';
        } else {
            $dbRedirect = class_exists(Setting::class) ? Setting::get('google_redirect_uri') : null;
            $envRedirect = env('GOOGLE_REDIRECT_URI') ?: config('services.google.redirect');

            if (!empty($dbRedirect)) {
                $redirect = $dbRedirect;
            } elseif (!empty($envRedirect)) {
                $redirect = $envRedirect;
            } else {
                $redirect = url('/auth/google/callback');
            }

            // Google OAuth strictly forbids HTTP for custom domain names; ensure https:// is used
            $parsedHost = parse_url($redirect, PHP_URL_HOST);
            if (!in_array($parsedHost, ['localhost', '127.0.0.1'])) {
                $redirect = preg_replace('/^http:/i', 'https:', $redirect);
            }
        }

        return [
            'client_id' => $clientId ? trim((string)$clientId) : null,
            'client_secret' => $clientSecret ? trim((string)$clientSecret) : null,
            'redirect' => $redirect ? trim((string)$redirect) : url('/auth/google/callback'),
        ];
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle()
    {
        $config = $this->getGoogleConfig();

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication is not yet configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env file or CRM Settings.',
            ]);
        }

        config([
            'services.google.client_id' => $config['client_id'],
            'services.google.client_secret' => $config['client_secret'],
            'services.google.redirect' => $config['redirect'],
        ]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->has('error')) {
            $errorDesc = $request->get('error_description') ?: $request->get('error');
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in was cancelled or encountered an error: ' . $errorDesc,
            ]);
        }

        $config = $this->getGoogleConfig();

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication is not configured.',
            ]);
        }

        config([
            'services.google.client_id' => $config['client_id'],
            'services.google.client_secret' => $config['client_secret'],
            'services.google.redirect' => $config['redirect'],
        ]);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (\Throwable $ex) {
                Log::warning('Google Auth fallback error: ' . $ex->getMessage());
                return redirect()->route('login')->withErrors([
                    'email' => 'Google authentication session expired. Please try again.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Google Auth callback exception: ' . $e->getMessage());
            return redirect()->route('login')->withErrors([
                'email' => 'Unable to authenticate with Google. Please try again or sign in with your email and password.',
            ]);
        }

        $email = strtolower(trim((string)$googleUser->getEmail()));
        $googleId = (string)$googleUser->getId();
        $avatar = $googleUser->getAvatar();

        if (empty($email)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google did not return an email address for your account.',
            ]);
        }

        // Match user by google_id first, then email
        $user = User::where('google_id', $googleId)->first();
        if (!$user) {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => "No CRM account is associated with {$email}. Please contact your administrator to set up an account.",
            ]);
        }

        // Check maintenance mode authorization
        $mode = (int) Setting::get('maintenance_mode', 0);
        if ($mode == 1 || $mode == 2) {
            $isAllowed = false;
            if ($mode == 1 && ($user->hasRole('IT Admin') || $user->hasAdminPrivileges())) {
                $isAllowed = true;
            } elseif ($mode == 2 && $user->hasRole('IT Admin')) {
                $isAllowed = true;
            }

            if (!$isAllowed) {
                return redirect()->route('maintenance');
            }
        }

        // Update google_id and avatar if changed or missing
        $updateData = [];
        if (empty($user->google_id) || $user->google_id !== $googleId) {
            $updateData['google_id'] = $googleId;
        }
        if (!empty($avatar) && $user->avatar !== $avatar) {
            $updateData['avatar'] = $avatar;
        }
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $this->logAction('Logged in via Google', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.confirmed' => 'The new password and confirmation do not match.',
            'password.min' => 'The new password must be at least 8 characters long.',
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        $this->logAction('Reset password', $user);

        return redirect()->back()->with('success', 'Your password has been updated successfully!');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.exists' => 'No user account was found with that email address.',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        // Generate 6-digit OTP
        $otp = sprintf('%06d', mt_rand(100000, 999999));

        // Save OTP to password_reset_tokens
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => \Illuminate\Support\Facades\Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Send Email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PasswordResetOtpMail($otp, $user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Password reset OTP email failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'Unable to send OTP email at this time. Please check your mail settings.'])->withInput();
        }

        return redirect()->route('password.otp', ['email' => $request->email])
            ->with('success', 'A 6-digit verification code (OTP) has been sent to your email address.');
    }

    public function showOtpForm(Request $request)
    {
        return view('auth.reset-password-otp', [
            'email' => $request->query('email', '')
        ]);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'numeric', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.exists' => 'No user account found with this email.',
            'otp.required' => 'Please enter the 6-digit OTP code sent to your email.',
            'otp.digits' => 'The OTP code must be exactly 6 digits.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'Your new password must be at least 8 characters long.',
        ]);

        $tokenRecord = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$tokenRecord) {
            return back()->withErrors(['otp' => 'No password reset request found or the request has expired. Please request a new OTP.'])->withInput();
        }

        // Check 15 minute expiration
        if (now()->diffInMinutes($tokenRecord->created_at) > 15) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['otp' => 'The OTP code has expired. Please request a new OTP.'])->withInput();
        }

        // Check OTP match
        if (!\Illuminate\Support\Facades\Hash::check($request->otp, $tokenRecord->token) && $request->otp !== $tokenRecord->token) {
            return back()->withErrors(['otp' => 'The 6-digit OTP code entered is incorrect.'])->withInput();
        }

        // Update User Password
        $user = \App\Models\User::where('email', $request->email)->first();
        $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $user->save();

        // Delete token record
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        $this->logAction('Reset password', $user, [], $user->id);

        return redirect()->route('login')->with('success', 'Your password has been reset successfully! You can now log in with your new password.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
