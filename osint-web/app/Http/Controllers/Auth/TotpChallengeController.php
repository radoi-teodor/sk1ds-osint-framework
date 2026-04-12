<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TotpChallengeController extends Controller
{
    public function showChallenge(Request $request)
    {
        if (! $request->session()->has('mfa_user_id')) {
            return redirect('/login');
        }
        return view('auth.totp-challenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:20'],
        ]);

        $userId = $request->session()->get('mfa_user_id');
        if (! $userId) {
            return redirect('/login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->hasTotpEnabled()) {
            $request->session()->forget(['mfa_user_id', 'mfa_remember']);
            return redirect('/login');
        }

        $code = trim($request->input('code'));
        $g2fa = new Google2FA();

        // Try TOTP code first
        if (strlen($code) === 6 && ctype_digit($code)) {
            $valid = $g2fa->verifyKey($user->totp_secret, $code, 1);
            if ($valid) {
                return $this->completeLogin($request, $user);
            }
        }

        // Try recovery code
        $recoveryCodes = $user->totp_recovery_codes ?? [];
        foreach ($recoveryCodes as $i => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($recoveryCodes[$i]);
                $user->totp_recovery_codes = array_values($recoveryCodes);
                $user->save();
                return $this->completeLogin($request, $user);
            }
        }

        return back()->withErrors(['code' => 'Invalid code. Try again or use a recovery code.']);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(['mfa_user_id', 'mfa_remember']);
        return redirect('/login');
    }

    protected function completeLogin(Request $request, User $user)
    {
        $remember = $request->session()->get('mfa_remember', false);
        $request->session()->forget(['mfa_user_id', 'mfa_remember']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('totp_verified', true);
        return redirect()->intended('/projects');
    }
}
