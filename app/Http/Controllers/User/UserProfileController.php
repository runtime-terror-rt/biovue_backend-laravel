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
            'unit'             => 'nullable|string|in:metric,imperial',
            'age'              => 'nullable|integer',
            'sex'              => 'nullable|string|max:20',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
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
            $validated['image'] = $request->file('image')->store('profiles', 'public');
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

    public function getProjectionLimitAndExoiredAt()
    {
        $user = auth()->user();
        
        $projectionCredit = ProjectionCredit::where('user_id', $user->id)->first();
        
        $latestPayment = PlanPayment::where('user_id', $user->id)
                                    ->latest() 
                                    ->first();
        if (!$projectionCredit) {
            return response()->json([
                'success' => true,
                'projection_limit' => 0,
                'expired_at' => $latestPayment ? $latestPayment->end_date : null,
            ]);
        }

        return response()->json([
            'success' => true,
            'projection_limit' => $projectionCredit->projection_limit,
            'expired_at' => $latestPayment ? $latestPayment->end_date : null,
        ]);
    }
}
