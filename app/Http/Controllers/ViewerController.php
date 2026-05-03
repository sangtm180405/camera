<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Trang xem stream (subscriber) — poll trạng thái + WebRTC answer.
 */
class ViewerController extends Controller
{
    public function index(): View
    {
        return view('viewer', [
            'iceServers' => config('webrtc.ice_servers'),
        ]);
    }
}
