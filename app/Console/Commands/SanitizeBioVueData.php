<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SanitizeBioVueData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biovue:sanitize-data {--dry-run : Run without updating the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sanitize historical corrupted hydration and habit logs without modifying schemas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $this->info($isDryRun ? "Starting BioVue Data Sanitization (DRY RUN)..." : "Starting BioVue Data Sanitization...");

        // 1. Sanitize Hydration Logs (Jackie's 800 oz / 100 glasses issue)
        $hydrationLogs = DB::table('hydration_logs')
            ->where('water_glasses', '>', 30)
            ->orWhere('water_oz', '>', 250)
            ->get();

        $this->info("Found {$hydrationLogs->count()} suspicious hydration records.");

        $fixedHydration = 0;
        foreach ($hydrationLogs as $log) {
            $rawGlasses = (float)$log->water_glasses;
            $rawOz = isset($log->water_oz) ? (float)$log->water_oz : 0;

            $newOz = $rawOz;
            $newGlasses = $rawGlasses;

            if ($rawGlasses > 30) {
                $newOz = $rawGlasses;
                $newGlasses = (int)round($newOz / 8);
            } elseif ($rawOz > 250 && abs($rawOz - ($rawGlasses * 8)) < 1 && $rawGlasses > 20) {
                $newOz = $rawGlasses;
                $newGlasses = (int)round($newOz / 8);
            }

            if ($newGlasses != $rawGlasses || $newOz != $rawOz) {
                $fixedHydration++;
                $this->line("Log #{$log->id} (User {$log->user_id}): glasses {$rawGlasses} -> {$newGlasses}, oz {$rawOz} -> {$newOz}");
                if (!$isDryRun) {
                    DB::table('hydration_logs')
                        ->where('id', $log->id)
                        ->update([
                            'water_glasses' => $newGlasses,
                            'water_oz' => $newOz,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        // 2. Clean disconnected orphan pivot rows
        $orphanConnections = DB::table('connect_user_proffesions')
            ->whereNotIn('user_id', DB::table('users')->pluck('id'))
            ->orWhereNotIn('profession_id', DB::table('users')->pluck('id'))
            ->count();

        if ($orphanConnections > 0) {
            $this->warn("Found {$orphanConnections} orphaned connection rows.");
            if (!$isDryRun) {
                DB::table('connect_user_proffesions')
                    ->whereNotIn('user_id', DB::table('users')->pluck('id'))
                    ->orWhereNotIn('profession_id', DB::table('users')->pluck('id'))
                    ->delete();
                $this->info("Cleaned orphaned connection rows.");
            }
        }

        $this->info("Data sanitization completed. {$fixedHydration} hydration records normalized.");
        return Command::SUCCESS;
    }
}
