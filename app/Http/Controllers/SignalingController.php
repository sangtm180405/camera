<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Signaling WebRTC đa viewer: mỗi viewer có viewer_id (UUID), camera tạo một offer / một RTCPeerConnection cho mỗi id.
 */
class SignalingController extends Controller
{
    private const SIGNAL_TTL = 60;

    private const CAMERA_STATUS_TTL = 10;

    private const PENDING_VIEWERS_KEY = 'signal_pending_viewers';

    private function offerKey(string $viewerId): string
    {
        return 'signal_offer_'.$viewerId;
    }

    private function answerKey(string $viewerId): string
    {
        return 'signal_answer_'.$viewerId;
    }

    private function iceKey(string $role, string $viewerId): string
    {
        return 'signal_ice_'.$role.'_'.$viewerId;
    }

    /**
     * Viewer báo muốn xem — thêm vào hàng đợi để camera tạo offer riêng.
     */
    public function join(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'viewer_id' => ['required', 'uuid'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $id = $request->input('viewer_id');
        $pending = Cache::get(self::PENDING_VIEWERS_KEY, []);
        if (! in_array($id, $pending, true)) {
            $pending[] = $id;
            if (count($pending) > 100) {
                $pending = array_slice($pending, -100);
            }
        }
        Cache::put(self::PENDING_VIEWERS_KEY, $pending, self::SIGNAL_TTL * 3);

        return response()->json(['ok' => true]);
    }

    /**
     * Chuẩn hoá SDP (CRLF, bỏ ký tự điều khiển).
     */
    private function normalizeSdp(string $sdp): string
    {
        if ($sdp === '') {
            return $sdp;
        }
        if (str_contains($sdp, '\\n') && ! str_contains($sdp, "\n")) {
            $sdp = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", "\n"], $sdp);
        }
        $lines = preg_split('/\r\n|\r|\n/', $sdp);
        $out = [];
        foreach ($lines as $line) {
            $line = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $line);
            $line = rtrim($line, " \t");
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return implode("\r\n", $out)."\r\n";
    }

    /**
     * Camera gửi offer cho một viewer cụ thể.
     */
    public function sendOffer(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'viewer_id' => ['required', 'uuid'],
            'sdp' => ['required', 'string'],
            'type' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $viewerId = $request->input('viewer_id');

        $pending = Cache::get(self::PENDING_VIEWERS_KEY, []);
        $pending = array_values(array_filter($pending, fn ($x) => $x !== $viewerId));
        Cache::put(self::PENDING_VIEWERS_KEY, $pending, self::SIGNAL_TTL * 3);

        Cache::forget($this->answerKey($viewerId));
        Cache::forget($this->iceKey('camera', $viewerId));
        Cache::forget($this->iceKey('viewer', $viewerId));

        $payload = [
            'type' => $request->input('type', 'offer'),
            'sdp' => $this->normalizeSdp($request->input('sdp')),
        ];
        Cache::put($this->offerKey($viewerId), $payload, self::SIGNAL_TTL);

        return response()->json(['ok' => true, 'viewer_id' => $viewerId]);
    }

    /**
     * Viewer gửi answer (kèm viewer_id).
     */
    public function sendAnswer(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'viewer_id' => ['required', 'uuid'],
            'sdp' => ['required', 'string'],
            'type' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $viewerId = $request->input('viewer_id');
        $payload = [
            'type' => $request->input('type', 'answer'),
            'sdp' => $this->normalizeSdp($request->input('sdp')),
        ];
        Cache::put($this->answerKey($viewerId), $payload, self::SIGNAL_TTL);

        return response()->json(['ok' => true]);
    }

    /**
     * ICE trickle — bắt buộc kèm viewer_id để tách từng cặp P2P.
     */
    public function sendIce(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'role' => ['required', 'in:camera,viewer'],
            'viewer_id' => ['required', 'uuid'],
            'candidate' => ['required', 'array'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $role = $request->input('role');
        $viewerId = $request->input('viewer_id');
        $key = $this->iceKey($role, $viewerId);
        $list = Cache::get($key, []);
        $list[] = $request->input('candidate');
        Cache::put($key, $list, self::SIGNAL_TTL);

        return response()->json(['ok' => true, 'count' => count($list)]);
    }

    /**
     * Camera: ?role=camera&viewers=uuid1,uuid2 — trả pending_viewers, answers, ice_viewer theo từng id.
     * Viewer: ?role=viewer&viewer_id=uuid — trả offer + ice từ camera.
     */
    public function poll(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'role' => ['required', 'in:camera,viewer'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $role = $request->query('role');

        if ($role === 'viewer') {
            $v2 = Validator::make($request->query(), [
                'viewer_id' => ['required', 'uuid'],
            ]);
            if ($v2->fails()) {
                return response()->json(['ok' => false, 'errors' => $v2->errors()], 422);
            }
            $viewerId = $request->query('viewer_id');

            return response()->json([
                'ok' => true,
                'broadcast_id' => Cache::get('camera_broadcast_id'),
                'offer' => Cache::get($this->offerKey($viewerId)),
                'ice' => Cache::get($this->iceKey('camera', $viewerId), []),
            ]);
        }

        $raw = $request->query('viewers', '');
        $activeIds = array_values(array_filter(array_map('trim', explode(',', $raw)), function ($id) {
            return is_string($id) && Str::isUuid($id);
        }));

        $answers = [];
        $iceViewer = [];
        foreach ($activeIds as $vid) {
            $answers[$vid] = Cache::get($this->answerKey($vid));
            $iceViewer[$vid] = Cache::get($this->iceKey('viewer', $vid), []);
        }

        return response()->json([
            'ok' => true,
            'broadcast_id' => Cache::get('camera_broadcast_id'),
            'pending_viewers' => Cache::get(self::PENDING_VIEWERS_KEY, []),
            'answers' => $answers,
            'ice_viewer' => $iceViewer,
        ]);
    }

    /**
     * Heartbeat camera; broadcast_id để viewer biết camera vừa tải lại trang.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $online = $request->boolean('online', true);
        if ($online) {
            Cache::put('camera_status', true, self::CAMERA_STATUS_TTL);
            $bid = $request->input('broadcast_id');
            if (is_string($bid) && Str::isUuid($bid)) {
                Cache::put('camera_broadcast_id', $bid, self::CAMERA_STATUS_TTL);
            }
        } else {
            Cache::forget('camera_status');
            Cache::forget('camera_broadcast_id');
        }

        return response()->json(['ok' => true, 'online' => $online]);
    }

    public function getStatus(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'online' => Cache::has('camera_status'),
            'broadcast_id' => Cache::get('camera_broadcast_id'),
        ]);
    }
}
