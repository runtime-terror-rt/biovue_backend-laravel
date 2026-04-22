<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use App\Jobs\SendOtpEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class SignUpController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validation
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role' => 'required|string',
            'terms_accepted' => 'required|accepted',
            'user_type' => 'required|string|in:individual,professional',
            'g-recaptcha-response' => 'required',
        ];

        if (app()->environment('local')) {
            unset($rules['g-recaptcha-response']);
        }

        $request->validate($rules, [
            'g-recaptcha-response.required' => 'Security check (reCAPTCHA) is required.',
        ]);

        // 2. Verify reCAPTCHA (Only if not local)
        if (!app()->environment('local')) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => config('services.recaptcha.secret'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();
            
            if (!$result['success'] || $result['score'] < 0.5) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Security verification failed or suspicious activity detected.'
                ], 422);
            }
        }

        // 3. Database Transaction
        try {
            DB::beginTransaction();

            // Verify Role
            if (!\Spatie\Permission\Models\Role::where('name', $request->role)->exists()) {
                return response()->json(['success' => false, 'message' => 'Specified role does not exist.'], 404);
            }

            // Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'terms_accepted' => true,
                'user_type' => $request->user_type,
                'profession_type' => $request->profession_type ?? null,
            ]);

            $user->assignRole($request->role);

            // OTP
            $otp = random_int(10000, 99999);
            $user->update([
                'otp' => $otp,
                'otp_expire_at' => Carbon::now()->addMinutes(5),
            ]);

            // Email & Notify
            SendOtpEmail::dispatch($user->id, 'verify', $otp);
            
            $admin = User::find(1);
            if ($admin) {
                $admin->notify(new AdminNotification('New User Registration', "$user->name has joined.", 'registration_message'));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify the OTP sent to your email.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }


    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        try {
            // ✅ Validation with custom messages
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|digits:5',
            ], [
                'email.required' => 'Email field is required.',
                'email.email' => 'Please enter a valid email address.',
                'otp.required' => 'OTP field is required.',
                'otp.digits' => 'OTP must be 5 digits.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.'
                ], 404);
            }

            if (!$user->otp || !$user->otp_expire_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OTP found. Please request a new OTP.'
                ], 400);
            }

            $otpExpire = Carbon::parse($user->otp_expire_at);

            if ($otpExpire->lt(Carbon::now())) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            if ((string)$user->otp !== (string)$request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.'
                ], 400);
            }

            // OTP is valid → verify email and clear OTP
            $user->update([
                'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
                'otp' => null,
                'otp_expire_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ Show first validation error as message
            $firstError = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Verify OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please try again.',
            ], 500);
        }
    }

}
