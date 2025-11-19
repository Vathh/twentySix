<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController
{

    public function __construct()
    {
    }

    public function login(Request $request)
    {
        $creedentials = $request->validate([
           'password' => ['required|max:6'],
        ]);

        $user = Auth::user();

        $user->tokens()->delete();

        $token = $user->createToken('counter-app-token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}
