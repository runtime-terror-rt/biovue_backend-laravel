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
                    'workout_routine'     => $validated['programs'] ?? null,
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

    public function getClientMacros(Request $request, $id = null)
    {
        $userId = $id ?: ($request->query('user_id') ?: ($request->query('client_id') ?: auth()->id()));

        $program = AdjustProgram::where('user_id', $userId)->first();
        $targetGoal = \App\Models\TargetGoal::where('user_id', $userId)->where('is_active', true)->first();
        $recentNotes = \DB::table('profession_notes')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $cal = (int)($program?->calories ?? ($targetGoal?->calories ?? 0));
        $pro = (int)($program?->protein ?? ($targetGoal?->protein ?? 0));
        $crb = (int)($program?->carbs ?? ($targetGoal?->carbs ?? 0));
        $fat = (int)($program?->fat ?? ($targetGoal?->fat ?? 0));

        $proPct = $cal > 0 ? round((($pro * 4) / $cal) * 100, 1) : 0;
        $crbPct = $cal > 0 ? round((($crb * 4) / $cal) * 100, 1) : 0;
        $fatPct = $cal > 0 ? round((($fat * 9) / $cal) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'user_id'            => (int)$userId,
                'calories'           => $cal,
                'protein'            => $pro,
                'carbs'              => $crb,
                'fat'                => $fat,
                'protein_percentage' => $proPct,
                'carbs_percentage'   => $crbPct,
                'fat_percentage'     => $fatPct,
                'note'               => $program?->note ?? ($targetGoal?->supplement_notes ?? null),
                'programs'           => $program?->programs ?? ($targetGoal?->workout_routine ?? null),
                'recent_notes'       => $recentNotes,
            ]
        ], 200);
    }

    public function calculateMacros(Request $request)
    {
        $calories = (float)$request->input('calories', 0);
        $proPct   = (float)($request->input('protein_percentage') ?? $request->input('protein_pct', 0));
        $crbPct   = (float)($request->input('carbs_percentage') ?? $request->input('carbs_pct', 0));
        $fatPct   = (float)($request->input('fat_percentage') ?? $request->input('fat_pct', 0));

        $proGrams = (float)$request->input('protein', 0);
        $crbGrams = (float)$request->input('carbs', 0);
        $fatGrams = (float)$request->input('fat', 0);

        if ($calories > 0 && ($proPct > 0 || $crbPct > 0 || $fatPct > 0)) {
            $proGrams = round(($calories * ($proPct / 100)) / 4);
            $crbGrams = round(($calories * ($crbPct / 100)) / 4);
            $fatGrams = round(($calories * ($fatPct / 100)) / 9);
        } elseif ($calories > 0 && ($proGrams > 0 || $crbGrams > 0 || $fatGrams > 0)) {
            $proPct = round((($proGrams * 4) / $calories) * 100, 1);
            $crbPct = round((($crbGrams * 4) / $calories) * 100, 1);
            $fatPct = round((($fatGrams * 9) / $calories) * 100, 1);
        } elseif ($proGrams > 0 || $crbGrams > 0 || $fatGrams > 0) {
            $calories = round(($proGrams * 4) + ($crbGrams * 4) + ($fatGrams * 9));
            $proPct = $calories > 0 ? round((($proGrams * 4) / $calories) * 100, 1) : 0;
            $crbPct = $calories > 0 ? round((($crbGrams * 4) / $calories) * 100, 1) : 0;
            $fatPct = $calories > 0 ? round((($fatGrams * 9) / $calories) * 100, 1) : 0;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'calories'           => (int)round($calories),
                'protein'            => (int)round($proGrams),
                'carbs'              => (int)round($crbGrams),
                'fat'                => (int)round($fatGrams),
                'protein_percentage' => $proPct,
                'carbs_percentage'   => $crbPct,
                'fat_percentage'     => $fatPct,
            ]
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
            'calories'           => 'nullable|integer|min:0',
            'protein'            => 'nullable|integer|min:0',
            'carbs'              => 'nullable|integer|min:0',
            'fat'                => 'nullable|integer|min:0',
            'protein_percentage' => 'nullable|numeric|min:0|max:100',
            'carbs_percentage'   => 'nullable|numeric|min:0|max:100',
            'fat_percentage'     => 'nullable|numeric|min:0|max:100',
            'protein_pct'        => 'nullable|numeric|min:0|max:100',
            'carbs_pct'          => 'nullable|numeric|min:0|max:100',
            'fat_pct'            => 'nullable|numeric|min:0|max:100',
            'note'               => 'nullable|string',
            'programs'           => 'nullable|string',
        ]);

        $cal = isset($validated['calories']) ? (int)$validated['calories'] : null;

        // Auto-calculate grams if percentages were submitted
        $proPct = $validated['protein_percentage'] ?? ($validated['protein_pct'] ?? null);
        $crbPct = $validated['carbs_percentage'] ?? ($validated['carbs_pct'] ?? null);
        $fatPct = $validated['fat_percentage'] ?? ($validated['fat_pct'] ?? null);

        if ($cal && $cal > 0) {
            if ($proPct !== null && !isset($validated['protein'])) {
                $validated['protein'] = (int)round(($cal * ($proPct / 100)) / 4);
            }
            if ($crbPct !== null && !isset($validated['carbs'])) {
                $validated['carbs'] = (int)round(($cal * ($crbPct / 100)) / 4);
            }
            if ($fatPct !== null && !isset($validated['fat'])) {
                $validated['fat'] = (int)round(($cal * ($fatPct / 100)) / 9);
            }
        }

        $saveData = collect($validated)->only(['calories', 'protein', 'carbs', 'fat', 'note', 'programs'])->filter(fn($val) => !is_null($val))->toArray();
        $saveData['profession_id'] = $authUser->id;

        $program = AdjustProgram::updateOrCreate(
            ['user_id' => $userId],
            $saveData
        );

        // Keep TargetGoal in sync with coach macros
        try {
            \App\Models\TargetGoal::updateOrCreate(
                ['user_id' => $userId],
                [
                    'profession_id'    => $authUser->id,
                    'calories'         => $program->calories,
                    'protein'          => $program->protein,
                    'carbs'            => $program->carbs,
                    'fat'              => $program->fat,
                    'supplement_notes' => $program->note,
                    'workout_routine'  => $program->programs,
                    'is_active'        => true,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Could not sync TargetGoal from setClientMacros: ' . $e->getMessage());
        }

        $client = \App\Models\User::find($userId);
        if ($client) {
            $client->sendUserNotify('goal_updates', [
                'title'   => 'Nutrition Macros Updated',
                'message' => 'Your coach has updated your daily macro targets.',
                'url'     => '/nutrition-details'
            ]);
        }

        $calVal = (int)($program->calories ?? 0);
        $pVal   = (int)($program->protein ?? 0);
        $cVal   = (int)($program->carbs ?? 0);
        $fVal   = (int)($program->fat ?? 0);
        $computedProPct = $calVal > 0 ? round((($pVal * 4) / $calVal) * 100, 1) : 0;
        $computedCrbPct = $calVal > 0 ? round((($cVal * 4) / $calVal) * 100, 1) : 0;
        $computedFatPct = $calVal > 0 ? round((($fVal * 9) / $calVal) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'message' => 'Client macros updated successfully.',
            'data'    => [
                'user_id'            => (int)$userId,
                'calories'           => $calVal,
                'protein'            => $pVal,
                'carbs'              => $cVal,
                'fat'                => $fVal,
                'protein_percentage' => $computedProPct,
                'carbs_percentage'   => $computedCrbPct,
                'fat_percentage'     => $computedFatPct,
                'note'               => $program->note,
                'programs'           => $program->programs,
            ]
        ], 200);
    }
}

