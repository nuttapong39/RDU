<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HisAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private HisAuthService $hisAuthService) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = null;

        // Primary: authenticate against HOSxP officer table (when HOSxP DB is configured)
        if ($this->hisAuthService->isAvailable()) {
            $user = $this->hisAuthService->attempt($request->username, $request->password);
        }

        // Fallback: local users table — used during initial setup before HOSxP is configured
        if (! $user) {
            $local = User::where('username', $request->username)->first();
            if ($local && Hash::check($request->password, $local->password)) {
                $user = $local;
            }
        }

        if (! $user) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (! $this->hisAuthService->isAvailable()) {
            return redirect()->route('admin.database.show')
                ->with('info', 'กรุณาตั้งค่าการเชื่อมต่อ HOSxP ก่อนเริ่มใช้งาน');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
