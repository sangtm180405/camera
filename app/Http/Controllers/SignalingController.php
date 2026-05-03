<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Bộ điều phối tín hiệu WebRTC qua HTTP polling + Laravel Cache (file driver).
 * Không dùng WebSocket: camera/viewer gửi SDP/ICE lên server và poll để nhận phía đối diện.
 */
class SignalingController extends Controller
{
    /** TTL (giây) cho offer/answer và danh sách ICE trong cache */
    private const SIGNAL_TTL = 60;

    /** TTL (giây) cho trạng thái camera online (heartbeat gia hạn) */
    private const CAMERA_STATUS_TTL = 10;

    /** Khóa cache cố định theo yêu cầu */
    private const KEY_OFFER = 'signal_offer';

    private const KEY_ANSWER = 'signal_answer';

    private const KEY_SESSION = 'signal_session_id';

    /**
     * Xóa toàn bộ dữ liệu signaling của một phiên (khi có offer mới).
     * Không xóa camera_status để viewer vẫn biết camera đang mở trang.
     */
    private function resetSignalingSession(): void
    {
        Cache::forget(self::KEY_ANSWER);
        Cache::forget('signal_ice_camera');
        Cache::forget('signal_ice_viewer');
        Cache::forget(self::KEY_SESSION);
    }

    /**
     * Chuẩn hoá SDP (CRLF, bỏ ký tự điều khiển) để tránh lỗi parse trên Android/Chrome khi lưu/cache.
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
     * Lưu SDP offer từ camera (Android).
     * Bắt đầu phiên mới: reset answer + ICE hai phía, gán session_id mới.
     */
    public function sendOffer(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'sdp' => ['required', 'string'],
            'type' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $this->resetSignalingSession();

        $sessionId = (string) Str::uuid();
        Cache::put(self::KEY_SESSION, $sessionId, self::SIGNAL_TTL);

        $payload = [
            'type' => $request->input('type', 'offer'),
            'sdp' => $this->normalizeSdp($request->input('sdp')),
        ];
        Cache::put(self::KEY_OFFER, $payload, self::SIGNAL_TTL);

        return response()->json([
            'ok' => true,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Lưu SDP answer từ viewer.
     */
    public function sendAnswer(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'sdp' => ['required', 'string'],
            'type' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $payload = [
            'type' => $request->input('type', 'answer'),
            'sdp' => $this->normalizeSdp($request->input('sdp')),
        ];
        Cache::put(self::KEY_ANSWER, $payload, self::SIGNAL_TTL);

        return response()->json(['ok' => true]);
    }

    /**
     * Nhận một ICE candidate; append vào mảng theo role (camera | viewer).
     */
    public function sendIce(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'role' => ['required', 'in:camera,viewer'],
            'candidate' => ['required', 'array'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $role = $request->input('role');
        $key = 'signal_ice_'.$role;
        $list = Cache::get($key, []);
        $list[] = $request->input('candidate');
        Cache::put($key, $list, self::SIGNAL_TTL);

        return response()->json(['ok' => true, 'count' => count($list)]);
    }

    /**
     * Poll: camera nhận answer + ICE từ viewer; viewer nhận offer + ICE từ camera.
     * Query: ?role=camera|viewer
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
        $sessionId = Cache::get(self::KEY_SESSION);

        $body = [
            'ok' => true,
            'session_id' => $sessionId,
        ];

        if ($role === 'camera') {
            $body['answer'] = Cache::get(self::KEY_ANSWER);
            $body['ice'] = Cache::get('signal_ice_viewer', []);
        } else {
            $body['offer'] = Cache::get(self::KEY_OFFER);
            $body['ice'] = Cache::get('signal_ice_camera', []);
        }

        return response()->json($body);
    }

    /**
     * Heartbeat: camera báo online/offline; online lưu khóa camera_status TTL 10s.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $online = $request->boolean('online', true);
        if ($online) {
            Cache::put('camera_status', true, self::CAMERA_STATUS_TTL);
        } else {
            Cache::forget('camera_status');
        }

        return response()->json(['ok' => true, 'online' => $online]);
    }

    /**
     * Viewer hỏi camera có đang heartbeat (online) không.
     */
    public function getStatus(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'online' => Cache::has('camera_status'),
        ]);
    }
}
