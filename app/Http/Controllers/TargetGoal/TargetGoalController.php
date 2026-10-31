<?php

namespace App\Http\Controllers\TargetGoal;
use App\Http\Controllers\Controller;

use App\Models\TargetGoal;
use App\Models\User;
use App\Notifications\GoalUpdateNotification;
use App\Notifications\MilestoneNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class TargetGoalController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $authUserId = Auth::id();
        $authUser = Auth::user();

        $isAuthorized = ($authUser->user_type === 'professional' || $authUser->user_type === 'admin');
        if (!$isAuthorized) {
            $isAuthorized = DB::table('connect_user_proffesions')
                ->where('profession_id', $authUserId)
                ->where('user_id', $request->input('user_id'))
                ->exists();
        }

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only trainer coaches and connected professionals can set goals for clients.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'                   => 'required|exists:users,id',
            'target_weight'             => 'nullable|numeric|between:0,999.99',
            'weekly_workout_goal'       => 'nullable|numeric|min:1|max:7',
            'daily_step_goal'           => 'nullable|numeric|min:0',
            'sleep_target'              => 'nullable|numeric|between:0,24',
            'start_date'                => 'nullable|date',
            'end_date'                  => 'nullable|date',
            'water_target'              => 'nullable|numeric|min:0',
            'supplement_recommendation' => 'nullable|array',
            'calories'                  => 'nullable|numeric|min:0',
            'protein'                   => 'nullable|numeric|min:0',
            'carbs'                     => 'nullable|numeric|min:0',
            'fat'                       => 'nullable|numeric|min:0',
        ]);

        $goalFields = collect($validated)->except(['calories', 'protein', 'carbs', 'fat'])->toArray();

        if (empty($goalFields['start_date'])) {
            $goalFields['start_date'] = now()->toDateString();
        }
        if (empty($goalFields['end_date'])) {
            $goalFields['end_date'] = now()->addMonths(3)->toDateString();
        }

        $goal = TargetGoal::updateOrCreate(
            ['user_id' => $validated['user_id']],
            array_merge($goalFields, [
                'profession_id' => $authUserId,
                'is_active'     => true
            ])
        );

        // Sync client macros, workout routine, and notes to AdjustProgram if provided
        $adjustFields = array_filter([
            'calories'      => $request->input('calories') ?? $request->input('target_calories'),
            'protein'       => $request->input('protein') ?? $request->input('protein_target'),
            'carbs'         => $request->input('carbs') ?? $request->input('carbs_target'),
            'fat'           => $request->input('fat') ?? $request->input('fat_target'),
            'programs'      => $request->input('workout_routine') ?? $request->input('programs'),
            'note'          => $request->input('supplement_notes') ?? $request->input('note'),
        ], fn($v) => !is_null($v));

        if (!empty($adjustFields)) {
            $adjustData = array_merge($adjustFields, [
                'profession_id' => $authUserId ?: ($goal->profession_id ?? null),
            ]);
            \App\Models\AdjustProgram::updateOrCreate(
                ['user_id' => $validated['user_id']],
                $adjustData
            );
        }

        $client = User::find($validated['user_id']);

        if ($client) {
            \App\Services\NotificationService::notifyUser(
                $client,
                new GoalUpdateNotification('New Goal Set', 'Your coach has updated your fitness targets.', 'goal_message'),
                'goal_updates'
            );
        }

        $goal->macros = !empty($macroData) ? $macroData : \App\Models\AdjustProgram::where('user_id', $validated['user_id'])->select('calories', 'protein', 'carbs', 'fat')->first();

        return response()->json([
            'success' => true,
            'message' => 'Target goals updated successfully for the client',
            'data'    => $goal
        ], 200);
    }

    public function getGoal($userId = null)
    {
        try {
            $loggedInUser = auth()->user();

            $targetId = $userId ?: $loggedInUser->id;

            if ($targetId != $loggedInUser->id) {
                
                $isConnected = DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetId)
                    ->exists();

                if (!$isConnected && $loggedInUser->user_type !== 'professional' && $loggedInUser->user_type !== 'admin') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have permission to view this goal.'
                    ], 403);
                }
            }

            $goals = TargetGoal::with('trainer:id,name,email,image')
                ->where('user_id', $targetId)
                ->orderByDesc('is_active')
                ->latest()
                ->get();

            $adjust = \App\Models\AdjustProgram::where('user_id', $targetId)->first();

            // If TargetGoal is empty but AdjustProgram has targets configured, synthesize/sync
            if ($goals->isEmpty() && $adjust) {
                $trainerId = $adjust->profession_id ?: DB::table('connect_user_proffesions')
                    ->where('user_id', $targetId)
                    ->value('profession_id');

                $sleepVal = 8;
                if (!empty($adjust->sleep_target_range)) {
                    $parts = explode('-', $adjust->sleep_target_range);
                    $sleepVal = is_numeric($parts[0]) ? (float) $parts[0] : 8;
                }

                $newGoal = TargetGoal::updateOrCreate(
                    ['user_id' => $targetId],
                    [
                        'profession_id'       => $trainerId,
                        'target_weight'       => $adjust->target_weight,
                        'weekly_workout_goal' => is_numeric($adjust->weekly_workouts) ? (int)$adjust->weekly_workouts : 3,
                        'daily_step_goal'     => 10000,
                        'sleep_target'        => $sleepVal,
                        'water_target'        => $adjust->hydration_target ? round((float)$adjust->hydration_target * 4) : 8,
                        'calories'            => $adjust->calories,
                        'protein'             => $adjust->protein,
                        'carbs'               => $adjust->carbs,
                        'fat'                 => $adjust->fat,
                        'supplement_notes'    => $adjust->note,
                        'is_active'           => true,
                        'start_date'          => $adjust->created_at ?? now(),
                        'end_date'            => $adjust->updated_at ? $adjust->updated_at->addMonths(3) : now()->addMonths(3),
                    ]
                );

                $goals = TargetGoal::with('trainer:id,name,email,image')
                    ->where('id', $newGoal->id)
                    ->get();
            }

            $macros = $adjust ? (object)[
                'calories' => $adjust->calories,
                'protein'  => $adjust->protein,
                'carbs'    => $adjust->carbs,
                'fat'      => $adjust->fat,
            ] : null;

            $connectedTrainer = null;
            $trainerUserId = DB::table('connect_user_proffesions')
                ->where('user_id', $targetId)
                ->value('profession_id')
                ?: DB::table('connect_to_professions')
                    ->where('connect_to_professions.user_id', $targetId)
                    ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                    ->value('programs_sets.profession_id');

            if ($trainerUserId) {
                $connectedTrainer = \App\Models\User::where('id', $trainerUserId)
                    ->select('id', 'name', 'email', 'image')
                    ->first();
            }

            $goals->transform(function ($g) use ($macros, $adjust, $connectedTrainer) {
                if (!$g->macros && $macros) {
                    $g->macros = $macros;
                }
                if (!$g->trainer && $connectedTrainer) {
                    $g->trainer = $connectedTrainer;
                }
                if ($adjust) {
                    if (!$g->workout_routine && !empty($adjust->programs)) {
                        $g->workout_routine = $adjust->programs;
                    }
                    if (!$g->supplement_notes && !empty($adjust->note)) {
                        $g->supplement_notes = $adjust->note;
                    }
                }
                return $g;
            });

            return response()->json([
                'success' => true,
                'data'    => $goals,
                'macros'  => $macros
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Something went wrong while fetching goals.'
            ], 500);
        }
    }
//   public function update(Request $request, $id)
//    {
//        $goal = TargetGoal::find($id);
//
//        if (!$goal) {
//            return response()->json(['success' => false, 'message' => 'Goal not found'], 404);
//        }
//
//        $loggedInUser = Auth::user();
//
//        $isOwner = $goal->user_id == $loggedInUser->id;
//
//        $isConnectedTrainer = false;
//        if (in_array($loggedInUser->user_type, ['professional', 'trainer', 'coach', 'trainer_coach'])) {
//            $isConnectedTrainer = DB::table('connect_user_proffesions')
//                ->where('profession_id', $loggedInUser->id)
//                ->where('user_id', $goal->user_id)
//                ->exists();
//        }
//
//        if ($isOwner || $isConnectedTrainer) {
//
//            $validated = $request->validate([
//                'target_weight'       => 'nullable|numeric|between:0,999.99',
//                'weekly_workout_goal' => 'nullable|integer|min:1|max:7',
//                'daily_step_goal'     => 'nullable|integer|min:0',
//                'sleep_target'        => 'nullable|numeric|between:0,24',
//                'start_date'          => 'nullable|date',
//                'end_date'            => 'nullable|date|after_or_equal:start_date',
//            ]);
//
//            $goal->update($validated);
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Target goal updated successfully',
//                'data'    => $goal
//            ]);
//        }
//
//        return response()->json([
//            'success' => false,
//            'message' => 'Unauthorized: You are not connected to this client.'
//        ], 403);
//    }


    public function update(Request $request, $id)
    {
        $goal = TargetGoal::find($id);

        if (!$goal) {
            return response()->json(['success' => false, 'message' => 'Goal not found'], 404);
        }

        $loggedInUser = Auth::user();

        $isOwner = $goal->user_id == $loggedInUser->id;

        $isConnectedTrainer = false;
        if (in_array($loggedInUser->user_type, ['professional', 'trainer', 'coach', 'trainer_coach'])) {
            $isConnectedTrainer = DB::table('connect_user_proffesions')
                ->where('profession_id', $loggedInUser->id)
                ->where('user_id', $goal->user_id)
                ->exists();
        }

        if ($isOwner || $isConnectedTrainer) {

            $validated = $request->validate([
                'target_weight'       => 'nullable|numeric|between:0,999.99',
                'weekly_workout_goal' => 'nullable|integer|min:1|max:7',
                'daily_step_goal'     => 'nullable|integer|min:0',
                'sleep_target'        => 'nullable|numeric|between:0,24',
                'start_date'          => 'nullable|date',
                'end_date'            => 'nullable|date|after_or_equal:start_date',
                'supplement_recommendation' => 'nullable|array'
            ]);

            $goal->update($validated);

            // If client updated their own goal, notify connected trainers
            if ($isOwner) {
                \App\Services\NotificationService::notifyConnectedTrainers(
                    $loggedInUser,
                    new MilestoneNotification(
                        'Goal Updated',
                        "{$loggedInUser->name} has updated their fitness goal.",
                        'goal_updates'
                    ),
                    'goal_updates'
                );
            } elseif ($isConnectedTrainer) {
                // If coach updated client's goal, notify the client
                if ($client = \App\Models\User::find($goal->user_id)) {
                    \App\Services\NotificationService::notifyUser(
                        $client,
                        new GoalUpdateNotification(
                            'Goal Updated',
                            'Your coach has updated your fitness targets.',
                            'goal_message'
                        ),
                        'goal_updates'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Target goal updated successfully',
                'data'    => $goal
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: You are not connected to this client.'
        ], 403);
    }
}

