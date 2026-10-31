<?php

namespace App\Http\Controllers\AdjustProgram;

use App\Http\Controllers\Controller;

use App\Models\AdjustProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdjustProgramController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $authUser = Auth::user();

        if ($authUser->user_type !== 'professional' || $authUser->profession_type !== 'trainer_coach') {
            return response()->json(['message' => 'Unauthorized. Only coaches can adjust programs.'], 403);
        }

        $validated = $request->validate([
            'user_id'                => 'required|exists:users,id',
            'target_weight'          => 'nullable|integer',
            'weekly_workouts'        => 'nullable|string',
            'sleep_target_range'     => 'nullable|string',
            'hydration_target'       => 'nullable|numeric',
            'show_program_goals'     => 'boolean',
            'show_personal_targets'  => 'boolean',
            'show_progress_graphs'   => 'boolean',
            'show_ai_insights'       => 'boolean',
            'primary_focus_area'     => 'nullable|string',
            'note'                   => 'nullable|string',
            'programs'               => 'nullable|string',
            'calories'               => 'nullable|integer',
            'protein'                => 'nullable|integer',
            'carbs'                  => 'nullable|integer',
            'fat'                    => 'nullable|integer',
        ]);

        $program = AdjustProgram::updateOrCreate(
            ['user_id' => $validated['user_id']],
            array_merge($validated, [
                'profession_id' => $authUser->id,
            ])
        );

        // Keep TargetGoal in sync so client dashboard displays the updated goals
        try {
            $parts = !empty($validated['sleep_target_range']) ? explode('-', $validated['sleep_target_range']) : [];
            $sleepNum = isset($parts[0]) && is_numeric($parts[0]) ? (float)$parts[0] : 8;

            \App\Models\TargetGoal::updateOrCreate(
                ['user_id' => $validated['user_id']],
                [
                    'profession_id'       => $authUser->id,
                    'target_weight'       => $validated['target_weight'] ?? null,
                    'weekly_workout_goal' => is_numeric($validated['weekly_workouts'] ?? null) ? (int)$validated['weekly_workouts'] : null,
                    'sleep_target'        => $sleepNum,
                    'water_target'        => isset($validated['hydration_target']) ? round((float)$validated['hydration_target'] * 4) : null,
                    'calories'            => $validated['calories'] ?? null,
                    'protein'             => $validated['protein'] ?? null,
                    'carbs'               => $validated['carbs'] ?? null,
                    'fat'                 => $validated['fat'] ?? null,
                    'supplement_notes'    => $validated['note'] ?? null,
                    'is_active'           => true,
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Could not sync TargetGoal from AdjustProgram: ' . $e->getMessage());
        }

        $client = \App\Models\User::find($validated['user_id']);
        if ($client) {
            $client->sendUserNotify('goal_updates', [
                'title' => 'Program Adjusted',
                'message' => 'Your coach has updated your fitness targets and goals.',
                'url' => '/user-dashboard/goals',
                'action_url' => '/user-dashboard/goals'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Program adjusted successfully',
            'data'    => $program
        ]);
    }

    public function show($id)
    {
        $program = AdjustProgram::where('user_id', $id)->first();

        if (!$program) {
            return response()->json(['message' => 'No program found for this user.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $program
        ]);
    }

    public function setClientMacros(Request $request, $id = null)
    {
        $authUser = Auth::user();

        if ($authUser->user_type !== 'professional' && $authUser->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only professionals can set client macros.'], 403);
        }

        $userId = $id ?: $request->input('user_id');

        if (!$userId || !\App\Models\User::where('id', $userId)->exists()) {
            return response()->json(['message' => 'A valid client user_id is required.'], 422);
        }

        $validated = $request->validate([
            'calories' => 'nullable|integer|min:0',
            'protein'  => 'nullable|integer|min:0',
            'carbs'    => 'nullable|integer|min:0',
            'fat'      => 'nullable|integer|min:0',
            'note'     => 'nullable|string',
        ]);

        $program = AdjustProgram::updateOrCreate(
            ['user_id' => $userId],
            array_filter($validated, fn($val) => !is_null($val))
        );

        $client = \App\Models\User::find($userId);
        if ($client) {
            $client->sendUserNotify('goal_updates', [
                'title'   => 'Nutrition Macros Updated',
                'message' => 'Your coach has updated your daily macro targets.',
                'url'     => '/nutrition-details'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Client macros updated successfully.',
            'data'    => [
                'user_id'  => (int)$userId,
                'calories' => $program->calories,
                'protein'  => $program->protein,
                'carbs'    => $program->carbs,
                'fat'      => $program->fat,
                'note'     => $program->note,
            ]
        ], 200);
    }
}

