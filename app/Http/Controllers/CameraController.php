<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Trang phát camera (publisher) — WebRTC offer + heartbeat.
 */
class CameraController extends Controller
{
    public function index(): View
    {
        return view('camera', [
            'iceServers' => config('webrtc.ice_servers'),
        ]);
    }
}
