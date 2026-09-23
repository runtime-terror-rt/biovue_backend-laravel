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

        // Auto-correct any user/client input mixup (e.g. 100 oz entered as glasses, or multiplied to 800)
        if ($waterGlasses !== null && $waterGlasses > 30) {
            $waterOz = $waterGlasses;
            $waterGlasses = round($waterOz / 8, 1);
        } elseif ($waterOz !== null && $waterGlasses === null) {
            $waterGlasses = round($waterOz / 8, 1);
        } elseif ($waterGlasses !== null && $waterOz === null) {
            $waterOz = round($waterGlasses * 8, 1);
        } elseif ($waterOz !== null && $waterGlasses !== null) {
            if ($waterOz > 250 && abs($waterOz - ($waterGlasses * 8)) < 0.1 && $waterGlasses > 20) {
                // Clearly multiplied ounces by 8 (e.g. 100 entered as glasses, then 800 oz generated)
                $waterOz = $waterGlasses;
                $waterGlasses = round($waterOz / 8, 1);
            }
        }

        $validated['water_oz'] = $waterOz;
        $validated['water_glasses'] = $waterGlasses !== null ? (int)round($waterGlasses) : 0;
        $validated['unit'] = $validated['unit'] ?? 'oz';

        $matchConditions = ['user_id' => Auth::id(), 'log_date' => $validated['log_date']];
        if ($request->filled('id')) {
            $matchConditions = ['user_id' => Auth::id(), 'id' => $request->id];
        }

        $activity = HydrationLog::updateOrCreate(
            $matchConditions,
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
