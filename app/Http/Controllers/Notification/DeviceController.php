<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserDevice;

class DeviceController extends Controller
{
    public function updateToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type'  => 'nullable|string|in:android,ios,web',
        ]);

        $user = $request->user();

        UserDevice::updateOrCreate(
            [
                'user_id'      => $user->id,
                'device_token' => $request->device_token
            ],
            [
                'device_type'  => $request->device_type ?? 'unknown'
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully.'
        ], 200);
    }
}