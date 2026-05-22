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
});