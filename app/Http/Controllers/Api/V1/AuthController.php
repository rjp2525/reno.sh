<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Same message for unknown user, bad password and non-admin: don't leak which.
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->isAdmin()) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return response()->json(['data' => [
            'token' => $user->createToken($data['device_name'], ['admin'])->plainTextToken,
            'user' => $this->user($user),
        ]], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->user($request->user())]);
    }

    public function revoke(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function user(User $user): array
    {
        return ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
