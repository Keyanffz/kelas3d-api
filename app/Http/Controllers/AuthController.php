<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login web.
     *
     * Panggil `GET /sanctum/csrf-cookie` dulu. Sesi cuma dibuat untuk request dari domain di `SANCTUM_STATEFUL_DOMAINS`;
     * selain itu dibalas 400. Respons sama dengan `GET /me`.
     */
    public function login(LoginRequest $request): UserResource
    {
        abort_unless($request->hasSession(), 400, 'Login web harus dari domain frontend (cek header Origin atau Referer).');

        if (! Auth::guard('web')->attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();

        return $this->profile(Auth::guard('web')->user());
    }

    /**
     * Login web lewat link dari agent.
     *
     * Untuk peserta dan ketua kelas, tanpa password. `token` diambil dari URL yang dibuat
     * `POST /agent/web-link`, berlaku sekali. Syarat domain frontend sama dengan login biasa.
     */
    public function loginWithLink(LinkLoginRequest $request): UserResource
    {
        abort_unless($request->hasSession(), 400, 'Login web harus dari domain frontend (cek header Origin atau Referer).');

        $userId = Cache::pull('web_login:'.$request->validated('token'));

        if (! $userId) {
            throw ValidationException::withMessages(['token' => 'Link masuk tidak valid atau sudah kedaluwarsa. Minta link baru dari agent.']);
        }

        $user = User::findOrFail($userId);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->profile($user);
    }

    /**
     * Logout web.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * User yang sedang login.
     *
     * Beserta kelas yang diikuti dan role-nya di tiap kelas. Koordinator tidak jadi anggota kelas mana pun.
     */
    public function me(Request $request): UserResource
    {
        return $this->profile($request->user());
    }

    private function profile(User $user): UserResource
    {
        return new UserResource($user->load('memberships.classroom'));
    }
}
