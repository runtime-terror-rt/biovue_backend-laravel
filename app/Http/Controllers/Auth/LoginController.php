<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendOtpEmail;
use Illuminate\Support\Carbon;


class LoginController extends Controller
{
 public function login(Request $request)
{
    try {
        // ✅ Validation
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:5',
        ], [
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password field is required.',
            'password.string' => 'Password must be a string.',
            'password.min' => 'Password must be at least 5 characters.',
        ]);

        // ✅ Find user by email
        $user = User::where('email', $request->email)->first();

        // ✅ Check credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // ✅ Check email verification
        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        $profileStatus = $user->profile()->exists() ? 'Your profile is complete.' : 'Your profile is incomplete. Please complete your profile to access all features.';
        // ✅ Generate token
        $token = $user->createToken('auth_token_' . $user->id)->plainTextToken;
        // ✅ Get plan duration in integer days
        $planDuration = $this->getPlanDuration($user);
        $projectionCredits = $user->projectionCredits ? $user->projectionCredits->projection_limit : 0;
        // ✅ Return success response
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first() ?? null,
                    'plan_id' => $user->plan_id,
                    'plan_name' => $user->plan->name?? null,
                    'plan_duration' => $planDuration, // integer days
                     'user_type' => $user->user_type ?? null,          // <-- added
                    'profession_type' => $user->profession_type ?? null, // <-- added
                    'projection_credits' => $projectionCredits,
                    'is_profile_completed' => $profileStatus,
                        
                   // ✅ Add timestamps
                        'created_at' => $user->created_at->toDateTimeString(),
                        'updated_at' => $user->updated_at->toDateTimeString(),
                ],
               
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // ✅ Validation error: first message only
        $firstError = collect($e->errors())->flatten()->first();

        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Login Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong. Please try again.',
        ], 500);
    }
}






/**
 * Protected helper to calculate remaining plan duration (in days)
 */
protected function getPlanDuration($user)
{
    $plan = $user->plan;
    if (!$plan) return null;

    $latestPayment = $user->planPayments()
        ->where('plan_id', $plan->id)
        ->where('status', 'paid')
        ->latest()
        ->first();

    if (!$latestPayment) return null;

    $startDate = $latestPayment->created_at;

    // ✅ Use billing from DB (NOT plan table)
    $billing = $latestPayment->billing;

    $durationDays = 0;

    if ($billing === 'annual') {
        $durationDays = 365;
    } elseif ($billing === 'monthly') {
        $durationDays = 30;
    } else {
        $durationDays = (int)($plan->duration ?? 0);
    }

    $endDate = $startDate->copy()->addDays($durationDays);
    $remainingDays = now()->diffInDays($endDate, false);

    return $remainingDays > 0 ? (int)$remainingDays : 0;
}



//resend otp
 public function resendOtp(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found.'
            ], 404);
        }

        // ❗ Only allow resend if user NOT verified
        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Your email is already verified.'
            ], 403);
        }

        $otp = random_int(10000, 99999);

        $user->update([
            'otp' => $otp,
            'otp_expire_at' => Carbon::now()->addMinutes(5),
        ]);

        SendOtpEmail::dispatch($user->id, 'verify', $otp);

        return response()->json([
            'success' => true,
            'message' => 'A new OTP has been sent to your email.',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        $firstError = collect($e->errors())->flatten()->first();
        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Resend OTP Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to resend OTP. Please try again.',
        ], 500);
    }
}


    /**
     * Logout user (current token)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Logout Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout. Please try again.',
            ], 500);
        }
    }
}
