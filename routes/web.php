<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function () {
    try {
        Artisan::call('optimize:clear');
        
        $output = Artisan::output();
        
        return response()->json([
            'success' => true,
            'message' => 'Optimization cache cleared successfully!',
            'output'  => nl2br($output) 
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to clear cache: ' . $e->getMessage()
        ], 500);
    }
})->name('admin.clearCache');

Route::get('/supplier/weekly-summary-preview', function () {
    return new \App\Mail\SupplierWeeklySummaryMail(
        'Apex Nutrition Labs',
        \Carbon\Carbon::now()->subDays(7)->format('M d') . ' - ' . \Carbon\Carbon::now()->format('M d, Y'),
        [
            'published_products' => 14,
            'product_clicks'     => 142,
            'ai_matches'         => 89,
            'new_messages'       => 12,
        ],
        [
            ['name' => 'Whey Protein Isolate - Vanilla', 'category' => 'Protein & Recovery', 'matches' => 38, 'price' => '$54.99'],
            ['name' => 'Pure Ultra Omega-3 EPA/DHA', 'category' => 'Cardiovascular & Joint', 'matches' => 29, 'price' => '$29.99'],
            ['name' => 'Magnesium Glycinate Recovery Complex', 'category' => 'Sleep & Relaxation', 'matches' => 22, 'price' => '$24.99'],
        ],
        [
            ['client_name' => 'Labib', 'topic' => 'Inquired about post-workout protein recommendation', 'date' => 'Yesterday'],
            ['client_name' => 'Sarah Jenkins', 'topic' => 'Requested dosage details on Omega-3', 'date' => '3 days ago'],
        ]
    );
});