<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Jobs\SendOtpEmail;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
   public function forgotPassword(Request $request)
{
    try {
        // ✅ Validation with custom message
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

        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email not verified. Please verify your email first.'
            ], 403);
        }

        // ✅ Generate 5-digit OTP
        $otp = random_int(10000, 99999);

        $user->update([
            'otp' => $otp,
            'otp_expire_at' => now()->addMinutes(5),
        ]);

        SendOtpEmail::dispatch($user->id, 'forgot', $otp);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email for password reset.'
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // ✅ Show first validation error as message
        $firstError = collect($e->errors())->flatten()->first();

        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Forgot Password Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
        ], 500);
    }
}


  /**
 * Reset Password (No OTP)
 */
public function resetPassword(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
        ], [
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.exists' => 'Email not found.',
            'new_password.required' => 'New password is required.',
            'new_password.confirmed' => 'New password confirmation does not match.',
        ]);

        $user = User::where('email', $request->email)->first();

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        SendOtpEmail::dispatch($user->id, 'reset_success');

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        $firstError = collect($e->errors())->flatten()->first();

        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Reset Password Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to reset password. Please try again.',
        ], 500);
    }
}


    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password'     => 'required|min:8|confirmed', 
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $user = auth()->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password does not match'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    
}
