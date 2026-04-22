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
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
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
        $request->validate([
            'name' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
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
        return response()->json([
            'status' => true,
            'data' => $request->user()->unreadNotifications
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
                    'programs_sets.name as program_name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity as intensity'
                )
                ->latest('connect_to_professions.created_at')
                ->first();

            if (!$programData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No program found for this user.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'program_name' => $programData->program_name ?? 'N/A',
                    'duration'     => ($programData->duration ?? 0) . ' weeks',
                    'primary_goal' => $programData->primary_goal ?? 'N/A',
                    'intensity'    => $programData->intensity ?? 'Moderate'
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

            $userPrograms = DB::table('connect_to_professions')
                ->where('connect_to_professions.user_id', $targetUserId)
                ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                ->select(
                    'programs_sets.id as program_id',
                    'programs_sets.name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity',
                    'connect_to_professions.created_at as assigned_date'
                )
                ->get();

            return response()->json([
                'success' => true,
                'total_connected_programs' => $userPrograms->count(),
                'data' => $userPrograms
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}