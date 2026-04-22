<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Kreait\Firebase\Auth as FirebaseAuth;

class FirebaseAuthController extends Controller
{
    public function loginWithFirebase(Request $request, FirebaseAuth $firebaseAuth)
    {
        $request->validate([
            'firebase_token' => 'required|string',
        ]);

        $firebaseToken = $request->firebase_token;

        // verify firebase token
        $verifiedToken = $firebaseAuth->verifyIdToken($firebaseToken);

        $firebaseUserId = $verifiedToken->claims()->get('sub');
        $email = $verifiedToken->claims()->get('email');
        $name = $verifiedToken->claims()->get('name');

        // create or update user
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name ?? 'No Name',
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(16)),
            ]
        );

        // create token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
