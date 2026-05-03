<?php

/**
 * ICE (STUN/TURN) cho WebRTC.
 *
 * PC–PC trên LAN đôi khi chỉ cần STUN; mobile (4G/WiFi NAT khác) thường cần TURN relay.
 * Đặt WEBRTC_ICE_SERVERS trong .env = JSON mảng hợp lệ để thay toàn bộ danh sách.
 */
$fromEnv = env('WEBRTC_ICE_SERVERS');
$decoded = is_string($fromEnv) && $fromEnv !== '' ? json_decode($fromEnv, true) : null;

$default = [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
    ['urls' => 'stun:stun2.l.google.com:19302'],
    [
        'urls' => [
            'turn:openrelay.metered.ca:80',
            'turn:openrelay.metered.ca:443',
            'turn:openrelay.metered.ca:443?transport=tcp',
        ],
        'username' => 'openrelayproject',
        'credential' => 'openrelayproject',
    ],
];

return [
    'ice_servers' => is_array($decoded) && $decoded !== [] ? $decoded : $default,
];
