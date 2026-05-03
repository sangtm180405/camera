<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Camera — Homecam</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            background: #0a0a0c;
            color: #e8e8ec;
            font-family: "Courier New", Courier, monospace;
            overflow-x: hidden;
        }
        .wrap {
            max-width: 520px;
            margin: 0 auto;
            padding: 12px;
        }
        h1 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #00e5ff;
            margin: 0 0 10px;
            text-transform: uppercase;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }
        .video-shell {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #111;
            border: 1px solid #1f1f28;
            border-radius: 4px;
            overflow: hidden;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: #000;
        }
        .scanlines {
            pointer-events: none;
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                to bottom,
                rgba(0, 229, 255, 0.03) 0px,
                rgba(0, 229, 255, 0.03) 1px,
                transparent 2px,
                transparent 4px
            );
            mix-blend-mode: overlay;
            opacity: 0.35;
        }
        .badge {
            position: absolute;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 2px;
            font-weight: 700;
            letter-spacing: 0.06em;
            z-index: 3;
        }
        .badge-rec {
            top: 8px;
            left: 8px;
            background: rgba(255, 61, 113, 0.92);
            color: #fff;
            animation: blink 1.2s step-end infinite;
        }
        .badge-fps {
            top: 8px;
            right: 8px;
            background: rgba(10, 10, 12, 0.85);
            color: #00e5ff;
            border: 1px solid #00e5ff;
        }
        .badge-rec.off { opacity: 0.35; animation: none; }
        @keyframes blink { 50% { opacity: 0.2; } }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border: 1px solid #2a2a34;
            border-radius: 999px;
            font-size: 12px;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ff3d71;
        }
        .dot.online {
            background: #00ff88;
            box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.7);
            animation: pulse 1.6s ease-out infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.55); }
            70% { box-shadow: 0 0 0 10px rgba(0, 255, 136, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0); }
        }
        .qr-box {
            background: #fff;
            padding: 12px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 8px;
        }
        .btn {
            appearance: none;
            border: 1px solid #00e5ff;
            background: transparent;
            color: #00e5ff;
            font-family: inherit;
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:active { opacity: 0.85; }
        .btn.danger { border-color: #ff3d71; color: #ff3d71; }
        .meta {
            font-size: 11px;
            color: #9a9aa8;
            line-height: 1.5;
            word-break: break-all;
        }
        .loading-mask {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(10, 10, 12, 0.72);
            z-index: 4;
        }
        .loading-mask.hidden { display: none; }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 229, 255, 0.25);
            border-top-color: #00e5ff;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .err {
            color: #ff3d71;
            font-size: 12px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Surveillance / Camera</h1>

    <div class="row">
        <div class="status-pill" id="connPill">
            <span class="dot" id="statusDot"></span>
            <span id="statusText">Khởi tạo…</span>
        </div>
        <span class="meta" id="viewerMeta">Viewer: 0</span>
    </div>

    <div class="video-shell">
        <div class="loading-mask" id="loadingMask">
            <div class="spinner" aria-label="Đang mở camera"></div>
        </div>
        <video id="localVideo" playsinline autoplay muted></video>
        <div class="scanlines"></div>
        <div class="badge badge-rec off" id="recBadge">● REC</div>
        <div class="badge badge-fps" id="fpsBadge">FPS —</div>
    </div>
    <div class="err" id="errBox" style="display:none;"></div>

    <p class="meta" style="margin-top:12px;">Quét QR để mở trang xem trên điện thoại khác:</p>
    <div class="qr-box"><div id="qrcode"></div></div>
    <div class="row" style="margin-top:10px;">
        <button type="button" class="btn" id="btnCopy">Sao chép link /viewer</button>
        <button type="button" class="btn" id="btnQrPng">Tải QR PNG</button>
    </div>
    <p class="meta" id="linkPreview"></p>
</div>

<script>
(function () {
    'use strict';

    /**
     * Trang CAMERA (publisher) — luồng chính:
     * 1) Tự động getUserMedia (ưu tiên camera sau → fallback trước) với ràng buộc nhẹ cho Android cũ.
     * 2) Tạo RTCPeerConnection + addTrack, createOffer → POST /signal/offer (server reset phiên signaling cũ).
     * 3) Poll GET /signal/poll?role=camera mỗi 300ms để nhận answer + ICE từ viewer.
     * 4) Gửi ICE local qua POST /signal/ice với role=camera.
     * 5) Heartbeat POST /signal/status mỗi 5s để viewer biết camera còn sống (TTL cache 10s).
     * 6) Theo dõi connectionState: failed/disconnected → reconnect sau 3s (tạo offer mới).
     */

    // URL trang viewer — cùng origin với trang camera (đúng khi truy cập qua IP/LAN)
    const viewerUrl = window.location.origin + '/viewer';
    document.getElementById('linkPreview').textContent = viewerUrl;

    // --- Tiện ích CSRF + fetch JSON ---
    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function jsonHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        };
    }
    function showErr(msg) {
        var el = document.getElementById('errBox');
        el.style.display = 'block';
        el.textContent = msg;
    }

    // --- Trạng thái WebRTC ---
    var localStream = null;
    var pc = null;
    var pollTimer = null;
    var hbTimer = null;
    var reconnectTimer = null;
    var sessionIdSeen = null;
    var seenIceKeys = {};
    var pendingRemoteIce = [];
    var remoteAnswerApplied = false;

    var elVideo = document.getElementById('localVideo');
    var elLoad = document.getElementById('loadingMask');
    var elRec = document.getElementById('recBadge');
    var elFps = document.getElementById('fpsBadge');
    var elDot = document.getElementById('statusDot');
    var elStatus = document.getElementById('statusText');
    var elViewerMeta = document.getElementById('viewerMeta');

    var rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ]
    };

    function setConnUi(text, online) {
        elStatus.textContent = text;
        elDot.className = 'dot' + (online ? ' online' : '');
    }

    // --- FPS: ưu tiên requestVideoFrameCallback (Chrome), fallback RAF ---
    var fpsFrames = 0;
    var fpsLast = performance.now();
    function tickFpsFallback() {
        fpsFrames++;
        var now = performance.now();
        if (now - fpsLast >= 1000) {
            elFps.textContent = 'FPS ' + Math.round(fpsFrames * 1000 / (now - fpsLast));
            fpsFrames = 0;
            fpsLast = now;
        }
        requestAnimationFrame(tickFpsFallback);
    }
    function startFpsMeter() {
        if (elVideo.requestVideoFrameCallback) {
            function onFrame() {
                fpsFrames++;
                var now = performance.now();
                if (now - fpsLast >= 1000) {
                    elFps.textContent = 'FPS ' + Math.round(fpsFrames * 1000 / (now - fpsLast));
                    fpsFrames = 0;
                    fpsLast = now;
                }
                elVideo.requestVideoFrameCallback(onFrame);
            }
            elVideo.requestVideoFrameCallback(onFrame);
        } else {
            requestAnimationFrame(tickFpsFallback);
        }
    }

    // --- Số viewer (P2P một kênh): 1 nếu kết nối ICE/DTLS thành công và có gửi RTP ---
    function updateViewerCountFromStats() {
        if (!pc) {
            elViewerMeta.textContent = 'Viewer: 0';
            return;
        }
        pc.getStats(null).then(function (stats) {
            var ok = pc.connectionState === 'connected';
            var sending = false;
            stats.forEach(function (r) {
                if (r.type === 'outbound-rtp' && r.kind === 'video') {
                    if (r.bytesSent > 0 || r.packetsSent > 0) sending = true;
                }
            });
            elViewerMeta.textContent = 'Viewer: ' + ((ok && sending) ? 1 : 0);
        }).catch(function () {
            elViewerMeta.textContent = 'Viewer: —';
        });
    }
    setInterval(updateViewerCountFromStats, 1000);

    // --- Heartbeat online (TTL phía server 10s) ---
    function heartbeat() {
        fetch('/signal/status', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ online: true })
        }).catch(function () { /* mạng lỗi — lần sau thử lại */ });
    }

    // --- Gửi ICE lên server (role=camera) ---
    function sendLocalIce(candidate) {
        if (!candidate || !candidate.candidate) return;
        fetch('/signal/ice', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ role: 'camera', candidate: candidate.toJSON() })
        }).catch(function () {});
    }

    function iceKey(c) {
        return (c.candidate || '') + '|' + (c.sdpMid || '') + '|' + (c.sdpMLineIndex != null ? c.sdpMLineIndex : '');
    }

    function flushPendingIce() {
        if (!pc || !pc.remoteDescription) return;
        while (pendingRemoteIce.length) {
            var raw = pendingRemoteIce.shift();
            var c = new RTCIceCandidate(raw);
            pc.addIceCandidate(c).catch(function () {});
        }
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function teardownPeer() {
        stopPoll();
        remoteAnswerApplied = false;
        pendingRemoteIce = [];
        seenIceKeys = {};
        sessionIdSeen = null;
        if (pc) {
            pc.onicecandidate = null;
            pc.onconnectionstatechange = null;
            pc.close();
            pc = null;
        }
    }

    function scheduleReconnect(reason) {
        if (reconnectTimer) return;
        setConnUi('Mất kết nối — thử lại sau 3s…', false);
        elRec.classList.add('off');
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null;
            beginSession();
        }, 3000);
    }

    // --- Tạo offer, đăng ký poll answer + ICE viewer ---
    function beginSession() {
        // Hủy reconnect chờ sẵn nếu ta chủ động tạo phiên mới (tránh gọi trùng sau khi đã hồi phục)
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        teardownPeer();
        if (!localStream) return;

        setConnUi('Đang tạo phiên WebRTC…', true);
        pc = new RTCPeerConnection(rtcConfig);

        localStream.getTracks().forEach(function (t) {
            pc.addTrack(t, localStream);
        });

        pc.onicecandidate = function (ev) {
            if (ev.candidate) sendLocalIce(ev.candidate);
        };

        pc.onconnectionstatechange = function () {
            var s = pc.connectionState;
            if (s === 'connected') {
                setConnUi('P2P: đã kết nối', true);
                elRec.classList.remove('off');
            }
            if (s === 'failed' || s === 'disconnected') {
                scheduleReconnect(s);
            }
        };

        pc.createOffer()
            .then(function (offer) { return pc.setLocalDescription(offer).then(function () { return offer; }); })
            .then(function (offer) {
                console.log('[Camera] Đang gửi offer lên server...');
                return fetch('/signal/offer', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ type: offer.type, sdp: offer.sdp })
                });
            })
            .then(function (r) {
                console.log('[Offer] HTTP status:', r.status);
                return r.json();
            })
            .then(function (data) {
                console.log('[Offer] Server trả về:', JSON.stringify(data));
                if (!data.ok) throw new Error('Offer bị từ chối');
                sessionIdSeen = data.session_id || null;
                setConnUi('Đã gửi offer — chờ viewer…', true);

                pollTimer = setInterval(function () {
                    fetch('/signal/poll?role=camera')
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (!pc) return;
                            if (j.session_id && sessionIdSeen && j.session_id !== sessionIdSeen) {
                                // Phiên signaling đổi — bắt đầu lại
                                scheduleReconnect('session');
                                return;
                            }
                            if (j.answer && !remoteAnswerApplied) {
                                remoteAnswerApplied = true;
                                pc.setRemoteDescription(new RTCSessionDescription(j.answer))
                                    .then(flushPendingIce)
                                    .catch(function (e) {
                                        showErr('Không áp dụng được answer: ' + (e && e.message));
                                    });
                            }
                            if (j.ice && j.ice.length) {
                                j.ice.forEach(function (raw) {
                                    var k = iceKey(raw);
                                    if (seenIceKeys[k]) return;
                                    seenIceKeys[k] = true;
                                    if (!pc.remoteDescription) pendingRemoteIce.push(raw);
                                    else pc.addIceCandidate(new RTCIceCandidate(raw)).catch(function () {});
                                });
                            }
                        })
                        .catch(function () {});
                }, 300);
            })
            .catch(function (e) {
                showErr('Lỗi tạo offer / mạng: ' + (e && e.message));
                setConnUi('Lỗi', false);
            });
    }

    // --- Xin quyền camera: ưu tiên sau (environment), fallback trước ---
    var videoConstraints = {
        width: { ideal: 640, max: 1280 },
        height: { ideal: 480, max: 720 },
        frameRate: { ideal: 15, max: 24 }
    };

    function tryGetUserMedia(facing) {
        var c = { video: Object.assign({}, videoConstraints, { facingMode: facing }), audio: false };
        return navigator.mediaDevices.getUserMedia(c);
    }

    function startCamera() {
        elLoad.classList.remove('hidden');
        console.log('[Camera] Bắt đầu xin quyền camera...');
        var p = tryGetUserMedia('environment').catch(function () {
            console.log('[Camera] Camera sau thất bại, thử camera trước...');
            return tryGetUserMedia('user');
        });
        p.then(function (stream) {
            console.log('[Camera] getUserMedia thành công!', stream);
            localStream = stream;
            elVideo.srcObject = stream;
            elLoad.classList.add('hidden');
            startFpsMeter();
            console.log('[Camera] Bắt đầu beginSession...');
            beginSession();
            heartbeat();
            if (hbTimer) clearInterval(hbTimer);
            hbTimer = setInterval(heartbeat, 5000);
        }).catch(function (e) {
            console.error('[Camera] getUserMedia THẤT BẠI:', e);
            elLoad.classList.add('hidden');
            showErr('Không mở được camera: ' + (e && e.message));
            setConnUi('Không có camera', false);
        });
    }

    // --- QR + nút ---
    function setupQr() {
        var el = document.getElementById('qrcode');
        el.innerHTML = '';
        if (typeof QRCode !== 'undefined') {
            new QRCode(el, { text: viewerUrl, width: 180, height: 180 });
        }
    }
    document.getElementById('btnCopy').addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(viewerUrl).then(function () {
                setConnUi('Đã sao chép link', true);
            }).catch(function () {
                showErr('Không sao chép được (clipboard).');
            });
        } else {
            showErr('Trình duyệt không hỗ trợ clipboard API.');
        }
    });
    document.getElementById('btnQrPng').addEventListener('click', function () {
        var img = document.querySelector('#qrcode img');
        var canvas = document.querySelector('#qrcode canvas');
        var url = null;
        if (canvas && canvas.toDataURL) url = canvas.toDataURL('image/png');
        else if (img && img.src) url = img.src;
        if (!url) {
            showErr('Chưa có ảnh QR để tải.');
            return;
        }
        var a = document.createElement('a');
        a.href = url;
        a.download = 'viewer-qr.png';
        a.click();
    });

    setupQr();
    startCamera();
})();
</script>
</body>
</html>
