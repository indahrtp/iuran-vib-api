<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi salah.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi pengelola.'], 403);
        }

        $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addHours(12);
        $device    = Str::limit($request->userAgent() ?: 'Perangkat tidak dikenal', 100, '');

        $token = $user->createToken($device, ['*'], $expiresAt);
        $user->forceFill(['last_login_at' => now()])->save();
        $user->loadMissing('roles.permissions');

        return response()->json([
            'data' => [
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user'       => new UserResource($user),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('roles.permissions'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}