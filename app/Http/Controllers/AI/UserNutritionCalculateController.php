<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\UserNutritionCalculate;

class UserNutritionCalculateController extends Controller
{
    private function resolveLogDate(Request $request): string
    {
        if ($request->filled('log_date')) {
            return \Carbon\Carbon::parse($request->input('log_date'))->toDateString();
        }

        if ($request->filled('date')) {
            return \Carbon\Carbon::parse($request->input('date'))->toDateString();
        }

        $userTimezone = $request->header('X-Timezone') 
            ?? $request->input('timezone') 
            ?? $request->header('Timezone');

        if ($userTimezone) {
            try {
                return now($userTimezone)->toDateString();
            } catch (\Throwable $e) {
                // Fallback to default server date if timezone identifier is invalid
            }
        }

        return now()->toDateString();
    }

    /**
     * Store nutrition calculation for a user.
     * Always inserts a new row, then returns the AGGREGATED total
     * for that user + log_date (i.e. all rows of that day combined).
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'foods' => 'required|array|min:1'
        ]);

        try {
            $userId   = $request->user_id;
            $newFoods = $request->foods;
            $logDate  = $this->resolveLogDate($request);

            // Call AI Nutrition API (trailing slash required by FastAPI/Uvicorn to avoid 307 redirect)
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withOptions(['allow_redirects' => true])
                ->post('https://ai.biovuedigitalwellness.com/api/v1/habits/nutritions/calculate/', [
                    'foods' => $newFoods,
                    'user_id' => (string) $userId,
                ]);

            if ($response->failed() || empty($response->json())) {
                return response()->json([
                    'message' => 'Nutrition API failed',
                    'error' => $response->json() ?? $response->body()
                ], 500);
            }

            $data = $response->json();

            $caloriesValue = (float) ($data['nutrition']['calories']['value'] ?? 0);
            $caloriesUnit  = $data['nutrition']['calories']['unit'] ?? 'kcal';

            $proteinValue = (float) ($data['nutrition']['macros']['protein']['value'] ?? 0);
            $proteinUnit  = $data['nutrition']['macros']['protein']['unit'] ?? 'g';

            $carbsValue = (float) ($data['nutrition']['macros']['carbs']['value'] ?? 0);
            $carbsUnit  = $data['nutrition']['macros']['carbs']['unit'] ?? 'g';

            $fatValue = (float) ($data['nutrition']['macros']['fat']['value'] ?? 0);
            $fatUnit  = $data['nutrition']['macros']['fat']['unit'] ?? 'g';

            $total = $caloriesValue + $proteinValue + $carbsValue + $fatValue;

            // Always insert — this call's foods as its own row
            UserNutritionCalculate::create([
                'user_id'        => $userId,
                'foods'          => $newFoods,
                'calories_value' => $caloriesValue,
                'calories_unit'  => $caloriesUnit,
                'protein_value'  => $proteinValue,
                'protein_unit'   => $proteinUnit,
                'carbs_value'    => $carbsValue,
                'carbs_unit'     => $carbsUnit,
                'fat_value'      => $fatValue,
                'fat_unit'       => $fatUnit,
                'total'          => $total,
                'log_date'       => $logDate,
            ]);

            // Notify connected trainers about client nutrition log
            if ($clientUser = \App\Models\User::find($userId)) {
                \App\Services\NotificationService::notifyConnectedTrainers(
                    $clientUser,
                    new \App\Notifications\ClientActivityNotification(
                        "Nutrition Logged: {$clientUser->name}",
                        "{$clientUser->name} has logged nutrition intake for {$logDate}.",
                        'client_activity_log',
                        ['client_id' => $clientUser->id, 'log_date' => $logDate, 'type' => 'nutrition']
                    ),
                    'program_milestone_updates'
                );
            }

            // Return the combined total of ALL rows for this user + date
            $aggregated = $this->aggregateForDate($userId, $logDate);

            return response()->json($aggregated, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show authenticated user's nutrition calculation for a given date (default: today)
     */
    public function show(Request $request)
    {
        $loggedInUser = $request->user();
        $targetUserId = $request->query('user_id') 
            ?? $request->query('client_id') 
            ?? $request->input('user_id') 
            ?? $loggedInUser->id;

        if ($targetUserId != $loggedInUser->id) {
            $isConnected = \Illuminate\Support\Facades\DB::table('connect_user_proffesions')
                ->where('profession_id', $loggedInUser->id)
                ->where('user_id', $targetUserId)
                ->exists();

            if (!$isConnected && $loggedInUser->user_type !== 'admin' && $loggedInUser->user_type !== 'professional') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view this client food log.'
                ], 403);
            }
        }

        $requestedDateSpecified = $request->filled('date') || $request->filled('log_date');
        $logDate = $this->resolveLogDate($request);
        $aggregated = $this->aggregateForDate($targetUserId, $logDate);

        // If today has no logged meals and no specific date was requested, fallback to the client's most recent logged date
        $isFallback = false;
        if ($aggregated === null && !$requestedDateSpecified) {
            $latestEntry = UserNutritionCalculate::where('user_id', $targetUserId)
                ->orderByRaw('COALESCE(log_date, created_at) DESC')
                ->first();

            if ($latestEntry) {
                $latestDate = $latestEntry->log_date 
                    ? \Carbon\Carbon::parse($latestEntry->log_date)->toDateString() 
                    : \Carbon\Carbon::parse($latestEntry->created_at)->toDateString();
                $aggregated = $this->aggregateForDate($targetUserId, $latestDate);
                $isFallback = true;
            }
        }

        if ($aggregated === null) {
            return response()->json([
                'success' => true,
                'message' => 'No nutrition data found for this user on ' . $logDate,
                'data' => [
                    'log_date'  => $logDate,
                    'nutrition' => [
                        'calories' => ['value' => 0, 'unit' => 'kcal'],
                        'macros'   => [
                            'protein' => ['value' => 0, 'unit' => 'g'],
                            'carbs'   => ['value' => 0, 'unit' => 'g'],
                            'fat'     => ['value' => 0, 'unit' => 'g'],
                        ],
                        'total'    => 0,
                        'foods'    => []
                    ]
                ]
            ], 200);
        }

        if ($isFallback && isset($aggregated['data'])) {
            $aggregated['data']['is_latest_fallback'] = true;
            $aggregated['data']['requested_today_empty'] = true;
        }

        return response()->json($aggregated, 200);
    }

    /**
     * Delete a single food item from the day's nutrition log
     */
    public function deleteFoodItem(Request $request)
    {
        $request->validate([
            'food' => 'required|string',
            'log_date' => 'nullable|date',
            'date' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $userId = $request->user_id ?? auth()->id();
        $logDate = $this->resolveLogDate($request);
        $foodToRemove = trim($request->food);

        $records = UserNutritionCalculate::where('user_id', $userId)
            ->where(function($q) use ($logDate) {
                $q->whereDate('log_date', $logDate)
                  ->orWhere(function ($sub) use ($logDate) {
                      $sub->whereNull('log_date')
                          ->whereDate('created_at', $logDate);
                  });
            })
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No nutrition records found for this date.'
            ], 404);
        }

        $removed = false;
        foreach ($records as $record) {
            $foods = is_array($record->foods) ? $record->foods : (json_decode($record->foods, true) ?? []);
            
            $newFoods = [];
            $foundInRecord = false;
            foreach ($foods as $f) {
                if (is_array($f)) {
                    $foodName = trim((string)($f['food'] ?? $f['name'] ?? ''));
                } else {
                    $foodName = trim((string)$f);
                }

                $isMatch = (!empty($foodName) && !empty($foodToRemove)) && (
                    strcasecmp($foodName, $foodToRemove) === 0 
                    || stripos($foodName, $foodToRemove) !== false 
                    || stripos($foodToRemove, $foodName) !== false
                );

                if (!$foundInRecord && $isMatch) {
                    $foundInRecord = true;
                    $removed = true;
                    continue; // Skip this one instance
                }
                $newFoods[] = $f;
            }

            if ($foundInRecord) {
                if (empty($newFoods)) {
                    $record->delete();
                } else {
                    $oldCount = max(1, count($foods));
                    $newCount = count($newFoods);
                    $ratio = $newCount / $oldCount;

                    $record->update([
                        'foods'          => array_values($newFoods),
                        'calories_value' => round($record->calories_value * $ratio, 2),
                        'protein_value'  => round($record->protein_value * $ratio, 2),
                        'carbs_value'    => round($record->carbs_value * $ratio, 2),
                        'fat_value'      => round($record->fat_value * $ratio, 2),
                        'total'          => round($record->total * $ratio, 2),
                    ]);
                }
                break;
            }
        }

        $aggregated = $this->aggregateForDate($userId, $logDate);
        if ($aggregated === null) {
            $aggregated = [
                'log_date' => $logDate,
                'nutrition' => [
                    'calories' => ['value' => 0, 'unit' => 'kcal'],
                    'macros' => [
                        'protein' => ['value' => 0, 'unit' => 'g'],
                        'carbs'   => ['value' => 0, 'unit' => 'g'],
                        'fat'     => ['value' => 0, 'unit' => 'g'],
                    ],
                    'total' => 0,
                    'foods' => []
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $removed ? "Removed '{$foodToRemove}' successfully." : "Food item '{$foodToRemove}' not found.",
            'data'    => $aggregated
        ], 200);
    }

    /**
     * Combine (sum) all rows for a given user + log_date into one response.
     */
    private function aggregateForDate($userId, $logDate)
    {
        $normalizedDate = \Carbon\Carbon::parse($logDate)->toDateString();

        $nutritions = UserNutritionCalculate::where('user_id', $userId)
            ->where(function ($q) use ($normalizedDate) {
                $q->whereDate('log_date', $normalizedDate)
                  ->orWhere(function ($sub) use ($normalizedDate) {
                      $sub->whereNull('log_date')
                          ->whereDate('created_at', $normalizedDate);
                  });
            })
            ->get();

        if ($nutritions->isEmpty()) {
            return null;
        }

        $caloriesValue = $nutritions->sum('calories_value');
        $proteinValue  = $nutritions->sum('protein_value');
        $carbsValue    = $nutritions->sum('carbs_value');
        $fatValue      = $nutritions->sum('fat_value');
        $total         = $nutritions->sum('total');

        $allFoods = $nutritions->flatMap(function ($item) {
            return is_array($item->foods)
                ? $item->foods
                : (json_decode($item->foods, true) ?? []);
        })->values();

        return [
            'log_date' => $logDate,
            'nutrition' => [
                'calories' => [
                    'value' => $caloriesValue,
                    'unit' => $nutritions->first()->calories_unit
                ],
                'macros' => [
                    'protein' => [
                        'value' => $proteinValue,
                        'unit' => $nutritions->first()->protein_unit
                    ],
                    'carbs' => [
                        'value' => $carbsValue,
                        'unit' => $nutritions->first()->carbs_unit
                    ],
                    'fat' => [
                        'value' => $fatValue,
                        'unit' => $nutritions->first()->fat_unit
                    ]
                ],
                'total' => $total,
                'foods' => $allFoods
            ]
        ];
    }
}