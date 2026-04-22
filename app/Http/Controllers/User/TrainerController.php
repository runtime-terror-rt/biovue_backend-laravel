<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invitation;
use App\Mail\InvitationMail;
use App\Models\ProjectionCredit;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class TrainerController extends Controller
{
    public function indexProfessionals($id)
    {
        try {
            $trainer = User::whereIn('user_type', ['professional'])
                ->with('profile') 
                ->find($id);

            if (!$trainer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainer not found'
                ], 404);
            }

            $profile = $trainer->profile;

            return response()->json([
                'success' => true,
                'data' => [
                    'id'               => $trainer->id,
                    'name'             => $trainer->name,
                    'email'            => $trainer->email,
                    'user_type'        => $trainer->user_type,
                    'bio'              => $profile?->bio ?? null,
                    'experience'       => ($profile?->experience_years ?? 0) . " years",
                    'specialties'      => $profile?->specialties ?? [], 
                    'services'         => $profile?->services ?? [],
                    'profile_image'    => $profile?->image ? asset('storage/' . $profile->image) : null,
                    'created_at'       => $trainer->created_at->format('Y-m-d')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

   public function professionalClientCard()
    {
        try {
            $user = auth()->user();
            $userId = $user->id;

            $profile = $user->profile; 
            $primaryGoalTitle = "General Fitness";
            $programDuration = 0;

            if ($profile) {
                $goalKeys = ['is_athletic' => 'Athletic', 'toned' => 'Toned', 'lean' => 'Lean', 'muscular' => 'Muscular', 'curvy_fit' => 'Curvy Fit'];
                foreach ($goalKeys as $key => $label) {
                    if ($profile->$key) { $primaryGoalTitle = $label; break; }
                }
            }

            $targetGoal = \App\Models\TargetGoal::where('user_id', $userId)->where('is_active', true)->first();
            if ($targetGoal && $targetGoal->start_date && $targetGoal->end_date) {
                $programDuration = \Carbon\Carbon::parse($targetGoal->start_date)->diffInWeeks($targetGoal->end_date);
            }

            $userSession = \DB::table('sessions')->where('user_id', $userId)->orderBy('last_activity', 'desc')->first();
            
            $lastActiveTime = "No activity";
            if ($userSession) {
                $lastActiveTime = \Carbon\Carbon::createFromTimestamp($userSession->last_activity)->diffForHumans();
            }

            $consistencyScore = 0;
            if ($targetGoal && $targetGoal->daily_step_goal > 0) {
                $avgSteps = \App\Models\ActivityLog::where('user_id', $userId)->avg('daily_steps');
                if ($avgSteps) {
                    $consistencyScore = round(($avgSteps / $targetGoal->daily_step_goal) * 100);
                }
            }
            $trendStatus = ($user->status == 'on_track') ? "Improving" : "Struggling";

            $projectionsCount = \App\Models\Projection::where('user_id', $userId)
                                ->whereMonth('created_at', now()->month)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'primary_goal' => [
                        'title' => $primaryGoalTitle,
                        'subtitle' => "Program duration {$programDuration} weeks"
                    ],
                    'current_trend' => [
                        'status' => $trendStatus,
                        'meta' => 'Based on your current track status'
                    ],
                    'last_activity' => [
                        'time' => $lastActiveTime == "No activity" ? $lastActiveTime : "Logged " . $lastActiveTime,
                        'meta' => "Status: " . ucfirst($user->status)
                    ],
                    'consistency_score' => [
                        'score' => min($consistencyScore, 100) . '%',
                        'meta' => 'Habits adherence (Average)'
                    ],
                    'projection_usage' => [
                        'used' => "{$projectionsCount}/10",
                        //'reset_days' => "Next reset: " . now()->endOfMonth()->diffInDays(now()) . " days"
                        'reset_days' => "Next reset: 18 days"
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function storeTrainerNote(Request $request)
    {
        $request->validate([
            'id'      => 'nullable|integer|exists:profession_notes,id',
            'user_id' => 'required|exists:users,id',
            'note'    => 'required|string',
        ]);

        $professionId = auth()->id();

        $isConnected = DB::table('connect_user_proffesions')
            ->where('profession_id', $professionId)
            ->where('user_id', $request->user_id)
            ->exists();

        if (!$isConnected && auth()->user()->user_type !== 'admin') {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized: You are not connected to this user.'
            ], 403);
        }

        try {
            if ($request->filled('id')) {
                DB::table('profession_notes')
                    ->where('id', $request->id)
                    ->where('profession_id', $professionId) 
                    ->update([
                        'note'       => $request->note,
                        'updated_at' => now(),
                    ]);

                $message = 'Note updated successfully';
                $noteId  = $request->id;
            } else {
                $noteId = DB::table('profession_notes')->insertGetId([
                    'profession_id' => $professionId,
                    'user_id'       => $request->user_id,
                    'note'          => $request->note,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $message = 'Note added successfully';
            }

            return response()->json([
                'success' => true, 
                'message' => $message, 
                'note_id' => $noteId
            ], $request->filled('id') ? 200 : 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

   
    public function indexTrainerNotes($userId = null)
    {
        $loggedInUser = auth()->user();

        $targetId = $userId ?: $loggedInUser->id;

        $query = DB::table('profession_notes')
            ->join('users as professionals', 'profession_notes.profession_id', '=', 'professionals.id')
            ->where('profession_notes.user_id', $targetId) 
            ->select('profession_notes.*', 'professionals.name as profession_name');

        if ($loggedInUser->user_type === 'professional') {
            $query->where('profession_id', $loggedInUser->id);
        } elseif ($loggedInUser->user_type === 'individual' && $loggedInUser->id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $notes = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $notes]);
    }

    public function destroyTrainerNote($id)
    {
        $note = DB::table('profession_notes')->where('id', $id)->first();

        if (!$note) {
            return response()->json(['success' => false, 'message' => 'Note not found'], 404);
        }

        if ($note->profession_id != auth()->id() && auth()->user()->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        DB::table('profession_notes')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Note deleted successfully']);
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $trainer = auth()->user();
        $token = \Illuminate\Support\Str::random(40);

        $invitation = Invitation::create([
            'trainer_id' => $trainer->id, 
            'email'      => $request->email,
            'token'      => $token,
            'status'     => 'pending'
        ]);

        \Illuminate\Support\Facades\Mail::to($request->email)
            ->send(new \App\Mail\InvitationMail($trainer, $token));

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully!',
            'token' => $token, 
        ]);
    }
    public function acceptInvitation($token)
    {
        $invitation = \App\Models\Invitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        $user = \App\Models\User::where('email', $invitation->email)->first();

        if ($user) {
            \Illuminate\Support\Facades\DB::table('connect_user_proffesions')->updateOrInsert([
                'profession_id' => $invitation->trainer_id,
                'user_id'       => $user->id,
            ], [
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $invitation->update(['status' => 'accepted']);

            return redirect()->to('https://biovuedigitalwellness.com/user-dashboard');
        }

        return redirect()->to('https://biovuedigitalwellness.com/register?email=' . $invitation->email);
    }

    public function giftCredit(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
        ]);

        $trainer = auth()->user();
        $amount = $request->amount;

        try {
            return DB::transaction(function () use ($trainer, $request, $amount) {
                
                $trainerCredit = ProjectionCredit::where('user_id', $trainer->id)->lockForUpdate()->first();

                if (!$trainerCredit || $trainerCredit->projection_limit < $amount) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Insufficient projection credits.'
                    ], 400);
                }

                $receiverCredit = ProjectionCredit::firstOrCreate(
                    ['user_id' => $request->receiver_id],
                    ['projection_limit' => 0]
                );

                $trainerCredit->decrement('projection_limit', $amount);
                $receiverCredit->increment('projection_limit', $amount);

                return response()->json([
                    'success' => true,
                    'message' => "Successfully gifted $amount credit(s).",
                    'remaining_credit' => $trainerCredit->fresh()->projection_limit
                ]);
            });

        } catch (\Exception $e) {
            \Log::error("Credit Gift Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Something went wrong during the transfer.'
            ], 500);
        }
    }
}
