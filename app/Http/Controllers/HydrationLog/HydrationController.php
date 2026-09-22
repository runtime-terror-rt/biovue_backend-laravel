<?php

namespace App\Http\Controllers\HydrationLog;

use App\Http\Controllers\Controller;
use App\Models\HydrationLog;
use App\Notifications\ReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HydrationController extends Controller
{
    public function index()
    {
        $logs = HydrationLog::where('user_id', Auth::id())
            ->orderBy('log_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'log_date'      => 'required|date',
            'water_oz'      => 'nullable|numeric|min:0',
            'water_glasses' => 'nullable|numeric|min:0',
            'unit'          => 'nullable|string',
            'weight'        => 'nullable|numeric|min:0',
            'daily_steps'   => 'nullable|integer|min:0',
            'sleep_hours'   => 'nullable|numeric|min:0|max:24',
        ]);

        $waterOz = isset($validated['water_oz']) ? (float)$validated['water_oz'] : null;
        $waterGlasses = isset($validated['water_glasses']) ? (float)$validated['water_glasses'] : null;

        if ($waterOz !== null && $waterGlasses === null) {
            $waterGlasses = round($waterOz / 8, 1);
        } elseif ($waterGlasses !== null && $waterOz === null) {
            if ($waterGlasses > 30) {
                // If a user entered 64 or 100 ounces into glasses input, treat as ounces
                $waterOz = $waterGlasses;
                $waterGlasses = round($waterOz / 8, 1);
            } else {
                $waterOz = round($waterGlasses * 8, 1);
            }
        }

        $validated['water_oz'] = $waterOz;
        $validated['water_glasses'] = $waterGlasses !== null ? (int)round($waterGlasses) : 0;
        $validated['unit'] = $validated['unit'] ?? 'oz';

        $activity = HydrationLog::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'log_date' => $validated['log_date']
            ],
            $validated
        );

        $status = $activity->wasRecentlyCreated ? 201 : 200;
        $message = $activity->wasRecentlyCreated ? 'Hydration log created' : 'Hydration log updated';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $activity
        ], $status);
    }

    public function show($id)
    {
        $log = HydrationLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Hydration log not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    public function destroy($id)
    {
        $log = HydrationLog::where('user_id', Auth::id())->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Hydration log not found'
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log deleted successfully'
        ]);
    }
}
