<?php

namespace App\Http\Controllers\ProgramsSet;

use App\Http\Controllers\Controller;
use App\Models\ProgramSet;
use Illuminate\Http\Request;
use App\Notifications\ProgramAssignedNotification;
use Illuminate\Support\Facades\DB;

class ProgramsSetController extends Controller
{
    /**
     * List programs (only logged-in trainer's programs)
     */
    public function index()
    {
        try {
            $programs = ProgramSet::where('profession_id', auth()->id())
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'data' => $programs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch program sets. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store program
     */
    private function normalizeWorkoutTypes(Request $request)
    {
        if ($request->has('workout_types') && is_string($request->workout_types)) {
            $decoded = json_decode($request->workout_types, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['workout_types' => $decoded]);
            } else {
                $types = array_values(array_filter(array_map('trim', explode(',', $request->workout_types))));
                $request->merge(['workout_types' => $types]);
            }
        }
    }

    public function store(Request $request)
    {
        $this->normalizeWorkoutTypes($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
            'workout_types' => 'nullable|array',
            'calories' => 'nullable|integer',
            'protein' => 'nullable|integer',
            'carbs' => 'nullable|integer',
            'fat' => 'nullable|integer',
            'supplement_recommendation' => 'nullable|array',
            'supplement' => 'nullable|array',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'weekly_targets' => 'nullable|array',
        ]);

        try {
            $programSet = ProgramSet::create([
                'profession_id' => auth()->id(), // ✅ FIXED
                'name' => $request->name,
                'duration' => $request->duration,
                'primary_goal' => $request->primary_goal,
                'target_intensity' => $request->target_intensity,
                'habit_focus_areas' => $request->habit_focus_areas,
                'program_focus' => $request->program_focus,
                'focus_areas' => $request->focus_areas,
                'habit_focus' => $request->habit_focus,
                'workout_types' => $request->workout_types,
                'calories' => $request->calories,
                'protein' => $request->protein,
                'carbs' => $request->carbs,
                'fat' => $request->fat,
                'supplement_recommendation' => $request->supplement_recommendation,
                'supplement' => $request->supplement,
                'description' => $request->description,
                'notes' => $request->notes,
                'weekly_targets' => $request->weekly_targets,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Program Set created successfully.',
                'data' => $programSet
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create Program Set. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single program
     */
    public function show($id)
    {
        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            return response()->json([
                'status' => true,
                'data' => $program
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Program not found.'
            ], 404);
        }
    }

    /**
     * Update program
     */
    public function update(Request $request, $id)
    {
        $this->normalizeWorkoutTypes($request);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
            'workout_types' => 'nullable|array',
            'calories' => 'nullable|integer',
            'protein' => 'nullable|integer',
            'carbs' => 'nullable|integer',
            'fat' => 'nullable|integer',
            'supplement_recommendation' => 'nullable|array',
            'supplement' => 'nullable|array',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'weekly_targets' => 'nullable|array',
        ]);

        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            $program->update($request->only([
                'name','duration','primary_goal','target_intensity',
                'habit_focus_areas','program_focus','focus_areas','habit_focus',
                'workout_types',
                'calories','protein','carbs','fat',
                'supplement_recommendation','supplement',
                'description','notes','weekly_targets'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Program updated successfully.',
                'data' => $program
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Update failed. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program
     */
    public function destroy($id)
    {
        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            $program->delete();

            return response()->json([
                'status' => true,
                'message' => 'Program deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Delete failed.'
            ], 500);
        }
    }

    
    /**
 * Assign users to a program set with profession_id in pivot
 */
public function assignUsers(Request $request)
{
    $request->validate([
        'program_set_id' => 'required|integer',
        'user_ids' => 'required|array',
        'user_ids.*' => 'integer|exists:users,id',
    ]);

    try {
        // Ensure the logged-in user owns this program
        $program = ProgramSet::where('id', $request->program_set_id)
            ->where('profession_id', auth()->id())
            ->firstOrFail();

        // Prepare pivot data for each user
        $pivotData = [];
        foreach ($request->user_ids as $userId) {
            $pivotData[$userId] = ['profession_id' => auth()->id()];
        }

        // Sync users with pivot data
        $program->users()->sync($pivotData);

        // Fetch assigned users
        $users = $program->users()->select('users.id','users.name','users.email')->get();

        // Send notifications
        foreach ($users as $user) {
            $user->notify(new ProgramAssignedNotification($program));
        }

        return response()->json([
            'status' => true,
            'message' => 'Users assigned successfully.',
            'data' => $users
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Assignment failed. Error: '.$e->getMessage()
        ], 500);
    }
}
    /**
     * Unread notifications
     */
    public function unread(Request $request)
    {
        $user = $request->user();
        $unread = $user->unreadNotifications->map(function ($notification) use ($user) {
            $notificationController = app(\App\Http\Controllers\Notification\NotificationController::class);
            $url = $notificationController->resolveNotificationUrl($notification, $user);

            $data = $notification->data ?? [];
            $data['url'] = $url;
            $data['action_url'] = $url;
            $data['link'] = $url;
            $notification->data = $data;
            $notification->setAttribute('url', $url);
            $notification->setAttribute('action_url', $url);
            $notification->setAttribute('link', $url);
            return $notification;
        });

        return response()->json([
            'status' => true,
            'data' => $unread
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => true]);
    }

     public function getProgramContext(Request $request, $userId = null)
    {
        try {
            $loggedInUser = auth()->user();

            $targetUserId = $userId ?: $loggedInUser->id;

            if ($targetUserId != $loggedInUser->id) {
                $isConnected = \DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetUserId)
                    ->exists();

                if (!$isConnected && $loggedInUser->user_type !== 'admin') {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Unauthorized: This client is not connected to you.'
                    ], 403);
                }
            }

            $programData = \DB::table('connect_to_professions')
                ->where('connect_to_professions.user_id', $targetUserId)
                ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                ->select(
                    'programs_sets.id as program_id',
                    'programs_sets.name as program_name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity as intensity',
                    'programs_sets.description',
                    'programs_sets.notes',
                    'programs_sets.workout_types',
                    'programs_sets.calories',
                    'programs_sets.protein',
                    'programs_sets.carbs',
                    'programs_sets.fat'
                )
                ->latest('connect_to_professions.created_at')
                ->first();

            if (!$programData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No program found for this user.'
                ], 404);
            }

            $adjustProgram = \App\Models\AdjustProgram::where('user_id', $targetUserId)->first();
            $targetGoal = \App\Models\TargetGoal::where('user_id', $targetUserId)->where('is_active', true)->first();
            $trainerNotes = \DB::table('profession_notes')
                ->where('user_id', $targetUserId)
                ->select('id', 'note', 'created_at')
                ->latest()
                ->take(5)
                ->get();

            $workoutTypes = !empty($programData->workout_types) 
                ? (is_array($programData->workout_types) ? $programData->workout_types : json_decode($programData->workout_types, true)) 
                : [];

            return response()->json([
                'success' => true,
                'data' => [
                    'program_id'        => $programData->program_id,
                    'program_name'      => $programData->program_name ?? 'N/A',
                    'duration'          => ($programData->duration ?? 0) . ' weeks',
                    'primary_goal'      => $programData->primary_goal ?? 'N/A',
                    'intensity'         => $programData->intensity ?? 'Moderate',
                    'description'       => $programData->description ?? null,
                    'workout_types'     => $workoutTypes,
                    'notes'             => $programData->notes ?? null,
                    'personalized_suggestions' => [
                        'trainer_note'            => $adjustProgram?->note,
                        'custom_workout_programs' => $adjustProgram?->programs,
                        'primary_focus_area'      => $adjustProgram?->primary_focus_area,
                        'target_macros'           => [
                            'calories' => $adjustProgram?->calories ?? $programData->calories,
                            'protein'  => $adjustProgram?->protein ?? $programData->protein,
                            'carbs'    => $adjustProgram?->carbs ?? $programData->carbs,
                            'fat'      => $adjustProgram?->fat ?? $programData->fat,
                        ],
                        'supplement_suggestions'  => $targetGoal?->supplement_recommendation ?? [],
                        'recent_trainer_notes'    => $trainerNotes,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getUsersInProgram($programSetId)
    {
        try {
            $loggedInUser = auth()->user();

            $program = DB::table('programs_sets')->where('id', $programSetId)->first();
            if (!$program) {
                return response()->json(['success' => false, 'message' => 'Program not found'], 404);
            }

            $connectedUsers = DB::table('connect_to_professions')
                ->where('program_set_id', $programSetId)
                ->join('users', 'connect_to_professions.user_id', '=', 'users.id')
                ->leftJoin('user_profiles as profiles', 'users.id', '=', 'profiles.user_id')
                ->select(
                    'users.id', 
                    'users.name', 
                    'users.email', 
                    'profiles.image as profile_image',
                    'connect_to_professions.created_at as enrolled_at'
                )
                ->get();

            return response()->json([
                'success' => true,
                'program_name' => $program->name,
                'total_users' => $connectedUsers->count(),
                'users' => $connectedUsers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getUserPrograms($userId = null)
    {
        try {
            $loggedInUser = auth()->user();
            
            $targetUserId = $userId ?: $loggedInUser->id;

            if ($targetUserId != $loggedInUser->id) {
                $isConnected = DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetUserId)
                    ->exists();

                if (!$isConnected && $loggedInUser->user_type !== 'admin') {
                    return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
                }
            }

            $adjustProgram = \App\Models\AdjustProgram::where('user_id', $targetUserId)->first();

            $userPrograms = DB::table('connect_to_professions')
                ->where('connect_to_professions.user_id', $targetUserId)
                ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                ->join('users as creators', 'programs_sets.profession_id', '=', 'creators.id') 
                ->select(
                    'programs_sets.id as program_id',
                    'programs_sets.name as program_name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity',
                    'programs_sets.description',
                    'programs_sets.description as workout_routine',
                    'programs_sets.notes',
                    'programs_sets.workout_types',
                    'programs_sets.calories',
                    'programs_sets.protein',
                    'programs_sets.carbs',
                    'programs_sets.fat',
                    'programs_sets.supplement_recommendation',
                    'programs_sets.weekly_targets',
                    'creators.name as created_by',
                    'creators.email as trainer_email',
                    'connect_to_professions.created_at as assigned_date'
                )
                ->get()
                ->map(function($program) use ($adjustProgram) {
                    $program->assigned_date = \Carbon\Carbon::parse($program->assigned_date)->format('M d, Y');
                    if (!empty($program->workout_types) && is_string($program->workout_types)) {
                        $decoded = json_decode($program->workout_types, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $program->workout_types = $decoded;
                        }
                    }
                    if (!empty($program->supplement_recommendation) && is_string($program->supplement_recommendation)) {
                        $decodedSupp = json_decode($program->supplement_recommendation, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $program->supplement_recommendation = $decodedSupp;
                        }
                    }
                    if (!empty($program->weekly_targets) && is_string($program->weekly_targets)) {
                        $decodedTargets = json_decode($program->weekly_targets, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $program->weekly_targets = $decodedTargets;
                        }
                    }

                    // Fallback to client personalized adjustments if program fields are empty
                    if (empty($program->workout_routine) && !empty($adjustProgram?->programs)) {
                        $program->workout_routine = is_string($adjustProgram->programs)
                            ? $adjustProgram->programs
                            : json_encode($adjustProgram->programs, JSON_PRETTY_PRINT);
                    }
                    if (empty($program->notes) && !empty($adjustProgram?->note)) {
                        $program->notes = $adjustProgram->note;
                    }
                    if (empty($program->calories) && !empty($adjustProgram?->calories)) {
                        $program->calories = $adjustProgram->calories;
                        $program->protein = $adjustProgram->protein;
                        $program->carbs = $adjustProgram->carbs;
                        $program->fat = $adjustProgram->fat;
                    }

                    return $program;
                });
            $recentNotes = \DB::table('profession_notes')
                ->where('user_id', $targetUserId)
                ->select('id', 'note', 'created_at')
                ->latest()
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'total_connected_programs' => $userPrograms->count(),
                'data' => $userPrograms,
                'personalized_suggestions' => [
                    'custom_notes'            => $adjustProgram?->note,
                    'custom_workout_programs' => $adjustProgram?->programs,
                    'primary_focus_area'      => $adjustProgram?->primary_focus_area,
                    'target_macros'           => [
                        'calories' => $adjustProgram?->calories,
                        'protein'  => $adjustProgram?->protein,
                        'carbs'    => $adjustProgram?->carbs,
                        'fat'      => $adjustProgram?->fat,
                    ],
                    'trainer_notes'           => $recentNotes,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}