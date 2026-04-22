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

        if ($authUser->user_type !== 'professional' || $authUser->profession_type !== 'trainer_coach') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only trainer coaches can set goals for clients.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'             => 'required|exists:users,id',
            'target_weight'       => 'nullable|numeric|between:0,999.99',
            'weekly_workout_goal' => 'nullable|integer|min:1|max:7',
            'daily_step_goal'     => 'nullable|integer|min:0',
            'sleep_target'        => 'nullable|numeric|between:0,24',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'water_target'        => 'nullable|integer|min:0|max:20',
            'supplement_recommendation' => 'nullable|array',
        ]);

        $goal = TargetGoal::updateOrCreate(
            ['user_id' => $validated['user_id']],
            array_merge($validated, [
                'profession_id' => $authUserId,
                'is_active'     => true
            ])
        );

        $client = User::find($validated['user_id']);

        $client->sendUserNotify('goal_updates', [
            'title'   => 'New Goal Set',
            'message' => 'Your coach has updated your fitness targets.',
            'url'     => '/goals'
        ]);

        $client->notify(new GoalUpdateNotification('New Goal Set', 'Your coach has updated your fitness targets.', 'goal_message'));

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

                if (!$isConnected && $loggedInUser->user_type !== 'professional') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have permission to view this goal.'
                    ], 403);
                }
            }

            $goals = TargetGoal::where('user_id', $targetId)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $goals
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

        $loggedInUser->notify(new MilestoneNotification('New Milestone', 'Your target Is Complete','milestone_message'));

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

