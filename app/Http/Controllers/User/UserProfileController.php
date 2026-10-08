<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PlanPayment;
use App\Models\ProjectionCredit;
use App\Models\User;
use App\Models\UserMedicalHistory;
use App\Models\UserProfile;
use App\Notifications\InsightNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function index()
    {
        $projectionCredits = ProjectionCredit::all()->keyBy('user_id');
        $profiles = UserProfile::with('user', 'user.medicalHistory')->get();
        foreach ($profiles as $profile) {
            $profile->user->projection_limit = $projectionCredits->get($profile->user->id)->projection_limit ?? 0;
        }
        return response()->json($profiles);
    }

    public function storeAndUpdate(Request $request)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'name'    => 'nullable|string|max:255',
            'user_type'       => 'required|in:individual,professional',
            'profession_type' => 'nullable|string|in:trainer_coach,nutritionist,supplement_supplier',
            'unit'             => 'nullable|string',
            'age'              => 'nullable|integer',
            'sex'              => 'nullable|string|max:20',
            'image' => [
                'nullable',
                'max:15360',
                function ($attribute, $value, $fail) {
                    $allowedMimes = [
                        'image/jpeg', 'image/png', 'image/gif',
                        'image/webp', 'image/avif', 'image/bmp',
                        'image/svg+xml', 'image/heic', 'image/heif',
                    ];
                    
                    if (!in_array($value->getMimeType(), $allowedMimes)) {
                        $fail('The ' . $attribute . ' must be a valid image format.');
                    }
                },
            ],
            'height'           => 'nullable',
            'weight'           => 'nullable',
            'location'         => 'nullable|string|max:255',
            'zipcode'          => 'nullable|string|max:20',
            'bio'              => 'nullable|string',
            'specialties'      => 'nullable|array',
            'services'         => 'nullable|array',
            'experience_years' => 'nullable|integer',
            'prof_service_type' => 'nullable|string',
            'diabetes'            => 'nullable|boolean',
            'high_blood_pressure' => 'nullable|boolean',
            'high_cholesterol'    => 'nullable|boolean',
            'heart_disease'       => 'nullable|boolean',
            'asthma'              => 'nullable|boolean',
            'athritis'            => 'nullable|boolean',
            'depression'          => 'nullable|boolean',
            'anxiety'             => 'nullable|boolean',
            'sleep_apnea'         => 'nullable|boolean',
            'thyroid_issue'       => 'nullable|boolean',
            'current_medication'  => 'nullable|string',
            'smoking_status'       => 'nullable|boolean',
            'alcohol_consumption' => 'nullable|boolean',
            'stress_level'         => 'nullable|string',
            'daily_step'          => 'nullable|integer',
            'sleep_hour'          => 'nullable|numeric',
            'water_consumption_week'   => 'nullable|numeric',
            'overall_diet_quality' => 'nullable|string',
            'fast_food_frequency' => 'nullable|string',
            'strength_training_week' => 'nullable|string',
            'workout_week' => 'nullable|string',
            'is_athletic' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'curvy_fit' => 'nullable|boolean',
            'muscular' => 'nullable|boolean',
            'lean' => 'nullable|boolean',
            'toned' => 'nullable|boolean',
            'current_image' => 'nullable|string',
            'body_fat' => 'nullable|string|max:20',
        ]);

        $user = User::find($validated['user_id']);
        $user->update([
            'name'            => $validated['name'] ?? $user->name,
            'user_type'       => $validated['user_type'],
            'profession_type' => $validated['profession_type'] ?? $user->profession_type,
        ]);

        if ($request->hasFile('image')) {
            $oldProfile = UserProfile::where('user_id', $validated['user_id'])->first();
            if ($oldProfile && $oldProfile->image) {
                Storage::disk('public')->delete($oldProfile->image);
            }
            $validated['image'] = \App\Services\ImageOptimizerService::storeOptimized($request->file('image'), 'profiles');
        }

        $medicalFields = [
            'diabetes', 'high_blood_pressure', 'high_cholesterol', 'heart_disease',
            'asthma', 'athritis', 'depression', 'anxiety', 'sleep_apnea',
            'thyroid_issue', 'current_medication'
        ];
        $medicalData = collect($validated)->only($medicalFields)->toArray();

        $profileData = collect($validated)->except(array_merge(['user_id', 'name', 'user_type', 'profession_type'], $medicalFields))->toArray();

        UserProfile::updateOrCreate(['user_id' => $validated['user_id']], $profileData);

        UserMedicalHistory::updateOrCreate(['user_id' => $validated['user_id']], $medicalData);

        $fullUser = User::with(['profile', 'medicalHistory'])->find($user->id);

        $fullImageUrl = $fullUser->profile && $fullUser->profile->image
            ? asset('storage/' . $fullUser->profile->image)
            : null;

        $user->notify(new InsightNotification('AI Insight', 'New AI Insight available','insight_msg'));


        return response()->json([
            'success' => true,
            'message' => 'Profile and Medical History updated successfully',
            'data' => [
                'user' => [
                    'id'              => $fullUser->id,
                    'name'            => $fullUser->name,
                    'email'           => $fullUser->email,
                    'user_type'       => $fullUser->user_type,
                    'profession_type' => $fullUser->profession_type,
                ],
                'profile'         => $fullUser->profile,
                'medical_history' => $fullUser->medicalHistory
            ]
        ]);
    }

    public function showByUserId($userId)
    {
        $user = User::with('profile', 'medicalHistory')->findOrFail($userId);

        // If user has an unpaid trial session with Stripe, attempt auto-sync
        if (!$user->trial_ends_at && !$user->plan_id) {
            $pendingPayment = PlanPayment::where('user_id', $userId)
                ->where('is_trial', true)
                ->where('status', 'unpaid')
                ->where('transaction_id', 'like', 'cs_%')
                ->latest()
                ->first();

            if ($pendingPayment) {
                try {
                    $planPaymentCtrl = app(\App\Http\Controllers\Payment\PlanPaymentController::class);
                    if ($planPaymentCtrl->syncSessionById($pendingPayment->transaction_id)) {
                        $user->refresh();
                        $user->load(['profile', 'medicalHistory']);
                    }
                } catch (\Throwable $e) {
                    // silently proceed if Stripe is not reachable
                }
            }
        }

        // Fallback: if user table trial_ends_at is null, check PlanPayment or Subscription
        if (!$user->trial_ends_at) {
            $latestTrial = PlanPayment::where('user_id', $userId)
                ->where('is_trial', true)
                ->whereNotNull('trial_ends_at')
                ->latest()
                ->first();

            if ($latestTrial) {
                $user->trial_ends_at = $latestTrial->trial_ends_at;
            } else {
                $sub = \App\Models\Subscription::where('user_id', $userId)
                    ->whereNotNull('trial_ends_at')
                    ->latest()
                    ->first();
                if ($sub) {
                    $user->trial_ends_at = $sub->trial_ends_at;
                }
            }
        }

        $projectionCredits = ProjectionCredit::where('user_id', $userId)->first();

        $user->projection_limit = $projectionCredits ? $projectionCredits->projection_limit : 0;

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        $profile = UserProfile::findOrFail($id);
        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profile deleted successfully'
        ]);
    }


    public function updateCurrentImage(Request $request)
    {
        $request->validate([
            'current_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', 
        ]);

        try {
            $user = auth()->user();
            
            $profile = UserProfile::firstOrNew(['user_id' => $user->id]);

            if ($request->hasFile('current_image')) {
                if ($profile->current_image && Storage::disk('public')->exists($profile->current_image)) {
                    Storage::disk('public')->delete($profile->current_image);
                }

                $file = $request->file('current_image');
                $fileName = 'current_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('projections/current_lifestyle', $fileName, 'public');

                $profile->current_image = $path;
                $profile->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Current lifestyle image updated successfully!',
                    'image_url' => asset('storage/' . $path)
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCurrentImage()
    {
        try {
            $user = auth()->user();
            
            $profile = UserProfile::where('user_id', $user->id)->first();

            if (!$profile || !$profile->current_image) {
                return response()->json([
                    'success' => false,
                    'message' => 'No lifestyle image found.',
                    'image_url' => null
                ], 404);
            }

            $imageUrl = asset('storage/' . $profile->current_image);

            return response()->json([
                'success' => true,
                'name' => $user->name,
                'image_url' => $imageUrl
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProjectionLimitAndExpiredAt()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
    
        $latestPayment = PlanPayment::where('user_id', $user->id)
            ->whereNotNull('status')
            ->latest()
            ->first();

        $userPlan = $user->plan;
        $planName = strtolower($userPlan?->name ?? 'free trial');
        $isFreeTrial = str_contains($planName, 'free') || ($latestPayment && $latestPayment->is_trial);

        $projectionCredit = ProjectionCredit::firstOrCreate(
            ['user_id' => $user->id],
            [
                'projection_limit' => $userPlan?->projection_limit ?? 1,
                'member_limit'     => 0,
                'expiry_date'      => $user->trial_ends_at ?: now()->addDays(7),
            ]
        );

        $activePaidSub = $latestPayment 
            && in_array($latestPayment->status, ['paid', 'active']) 
            && !$latestPayment->is_trial 
            && !in_array($latestPayment->status, ['pending_cancellation', 'cancelled']);

        $isCancelled = $latestPayment && in_array($latestPayment->status, ['pending_cancellation', 'cancelled']);

        $countdownExpiredAt = null;
        $showCountdown = false;

        if ($isCancelled && $latestPayment->end_date) {
            // For canceled subscription, countdown reflects remaining access period (monthly/yearly)
            $countdownExpiredAt = $latestPayment->end_date->toIso8601String();
            $showCountdown = true;
        } elseif ($activePaidSub) {
            // Active paid membership: MUST NOT display expiration countdown clock
            $countdownExpiredAt = null;
            $showCountdown = false;
        } elseif ($isFreeTrial || !$userPlan || str_contains($planName, 'free')) {
            // Free plan / Free trial: show countdown clock
            $trialEnd = $user->trial_ends_at ?: $projectionCredit->expiry_date ?: ($user->created_at ? $user->created_at->addDays(7) : null);
            $countdownExpiredAt = $trialEnd ? \Carbon\Carbon::parse($trialEnd)->toIso8601String() : null;
            $showCountdown = true;
        }

        // Feature permissions by plan
        $isPremium = str_contains($planName, 'premium');
        $isPlus    = str_contains($planName, 'plus');
        $allowedTimeframes = ['1_year'];
        if ($isPlus) {
            $allowedTimeframes = ['6_month', '1_year'];
        } elseif ($isPremium) {
            $allowedTimeframes = ['6_month', '1_year', '5_year'];
        }

        $projectionsCount = \App\Models\ProjectionData::where('user_id', $user->id)->count();
        $latestProjection = \App\Models\ProjectionData::where('user_id', $user->id)->latest()->first();
    
        return response()->json([
            'success'              => true,
            'projection_limit'     => $projectionCredit->projection_limit,
            'member_limit'         => $projectionCredit->member_limit,
            'expired_at'           => $countdownExpiredAt,
            'show_countdown'       => $showCountdown,
            'is_cancelled'         => $isCancelled,
            'is_trial'             => $isFreeTrial,
            'has_active_paid'      => $activePaidSub,
            'plan_name'            => $userPlan?->name ?? 'Free Trial',
            'allowed_timeframes'   => $allowedTimeframes,
            'has_projections'      => $projectionsCount > 0,
            'total_projections'    => $projectionsCount,
            'latest_projection_id' => $latestProjection?->id,
        ]);
    }
}
