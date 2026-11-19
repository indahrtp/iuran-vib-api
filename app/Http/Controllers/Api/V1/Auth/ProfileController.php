<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user->loadMissing('roles.permissions'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->password]);

        // Sesi lain dicabut, sesi saat ini tetap aktif
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user    = $request->user();
        $current = $user->currentAccessToken()->id;

        $data = $user->tokens()->latest('last_used_at')->get()->map(fn ($t) => [
            'id'           => $t->id,
            'device'       => $t->name,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at'   => $t->created_at->toIso8601String(),
            'is_current'   => $t->id === $current,
        ]);

        return response()->json(['data' => $data]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Berhasil keluar dari perangkat lain.']);
    }
}