<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TotpSetupController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.security', ['user' => $request->user()]);
    }

    public function create(Request $request)
    {
        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey();
        $request->session()->put('totp_setup_secret', $secret);

        $otpUrl = $g2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd(),
        );
        $qrSvg = (new Writer($renderer))->writeString($otpUrl);

        return view('profile.totp-setup', [
            'qrSvg' => $qrSvg,
            'secret' => $secret,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $secret = $request->session()->get('totp_setup_secret');
        if (! $secret) {
            return redirect('/profile/security')->withErrors(['code' => 'Session expired. Start again.']);
        }

        $g2fa = new Google2FA();
        if (! $g2fa->verifyKey($secret, $request->input('code'), 1)) {
            return back()->withErrors(['code' => 'Invalid code. Scan the QR and try again.']);
        }

        $codes = $this->generateRecoveryCodes();
        $user = $request->user();
        $user->totp_secret = $secret;
        $user->totp_confirmed_at = now();
        $user->totp_recovery_codes = array_map(fn ($c) => Hash::make($c), $codes);
        $user->save();

        $request->session()->forget('totp_setup_secret');
        $request->session()->put('totp_verified', true);

        return view('profile.totp-recovery-codes', ['codes' => $codes]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->totp_secret = null;
        $user->totp_confirmed_at = null;
        $user->totp_recovery_codes = null;
        $user->save();

        $request->session()->forget('totp_verified');

        return redirect('/profile/security')->with('status', 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $codes = $this->generateRecoveryCodes();
        $user = $request->user();
        $user->totp_recovery_codes = array_map(fn ($c) => Hash::make($c), $codes);
        $user->save();

        return view('profile.totp-recovery-codes', ['codes' => $codes]);
    }

    protected function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(fn () => strtolower(Str::random(10)), range(1, $count));
    }
}
