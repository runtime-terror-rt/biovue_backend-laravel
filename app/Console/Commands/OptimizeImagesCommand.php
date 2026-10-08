<?php

namespace App\Console\Commands;

use App\Services\ImageOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizeImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize existing images in storage preserving aspect ratio and high fidelity.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseDir = storage_path('app/public');
        if (!File::exists($baseDir)) {
            $this->error("Directory {$baseDir} does not exist.");
            return 1;
        }

        $files = File::allFiles($baseDir);
        $totalFiles = count($files);
        $optimizedCount = 0;
        $savedBytes = 0;

        $this->info("Found {$totalFiles} files in storage/app/public.");

        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            $origSize = $file->getSize();
            $path = $file->getRealPath();

            $success = ImageOptimizerService::optimizeInPlace($path, 1920, 1920, 85);
            if ($success) {
                clearstatcache(true, $path);
                $newSize = filesize($path);
                $diff = $origSize - $newSize;
                $savedBytes += $diff;
                $optimizedCount++;

                $origKb = round($origSize / 1024, 2);
                $newKb = round($newSize / 1024, 2);
                $this->line("Optimized: {$file->getFilename()} ({$origKb} KB -> {$newKb} KB)");
            }
        }

        $savedMb = round($savedBytes / (1024 * 1024), 2);
        $this->info("Completed! Optimized {$optimizedCount} images. Total storage saved: {$savedMb} MB.");

        return 0;
    }
}
