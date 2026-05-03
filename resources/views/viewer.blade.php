<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Viewer — Homecam</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            height: 100%;
            background: #080810;
            color: #e6e6ef;
            font-family: "Courier New", Courier, monospace;
            overflow-x: hidden;
        }
        .stage {
            position: relative;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
            display: block;
        }
        .stage.fullscreen video {
            object-fit: contain;
            height: 100vh;
        }
        .overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .overlay > * { pointer-events: auto; }
        .hud {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            padding: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(to bottom, rgba(0,0,0,0.65), transparent);
            opacity: 1;
            transition: opacity 0.35s ease;
        }
        .hud.hide { opacity: 0; }
        .badge-live {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #ff3d71;
            border: 1px solid #ff3d71;
            padding: 4px 10px;
            border-radius: 2px;
            display: none;
        }
        .badge-live.on { display: inline-block; animation: blink 1.2s step-end infinite; }
        @keyframes blink { 50% { opacity: 0.25; } }
        .stats {
            font-size: 12px;
            color: #00e5ff;
            text-shadow: 0 0 8px rgba(0, 229, 255, 0.25);
        }
        .bottom-bar {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 14px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            opacity: 1;
            transition: opacity 0.35s ease;
        }
        .bottom-bar.hide { opacity: 0; }
        .fs-btn {
            border: 1px solid #00e5ff;
            background: rgba(8, 8, 16, 0.6);
            color: #00e5ff;
            font-family: inherit;
            font-size: 12px;
            padding: 10px 14px;
            border-radius: 4px;
            cursor: pointer;
        }
        .offline {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            background: #080810;
            z-index: 5;
        }
        .offline.hidden { display: none; }
        .offline-icon {
            width: 72px;
            height: 72px;
            border: 3px solid #3a3a48;
            border-radius: 12px;
            position: relative;
            margin-bottom: 16px;
        }
        .offline-icon::before {
            content: '';
            position: absolute;
            left: 8px;
            right: 8px;
            top: 50%;
            height: 3px;
            background: #ff3d71;
            transform: translateY(-50%) rotate(-35deg);
            border-radius: 2px;
        }
        .blink {
            animation: blinkText 1.4s ease-in-out infinite;
        }
        @keyframes blinkText { 50% { opacity: 0.35; } }
        .loading {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(8, 8, 16, 0.88);
            z-index: 4;
        }
        .loading.on { display: flex; }
        .spinner {
            width: 44px;
            height: 44px;
            border: 3px solid rgba(0, 229, 255, 0.2);
            border-top-color: #00e5ff;
            border-radius: 50%;
            animation: spin 0.85s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .state-text {
            margin-top: 14px;
            font-size: 13px;
            color: #00e5ff;
        }
    </style>
</head>
<body>
<div class="stage" id="stage">
    <video id="remoteVideo" playsinline autoplay></video>

    <div class="offline" id="offlineScreen">
        <div class="offline-icon" aria-hidden="true"></div>
        <div style="font-size:14px;color:#00e5ff;margin-bottom:8px;">Camera chưa trực tuyến</div>
        <div class="blink meta" style="color:#9a9aa8;font-size:12px;max-width:320px;">
            Mở trang /camera trên thiết bị gửi hình. Trang này sẽ tự kết nối khi có tín hiệu heartbeat.
        </div>
    </div>

    <div class="loading" id="loadingLayer">
        <div class="spinner"></div>
        <div class="state-text blink" id="loadingText">Đang chờ camera…</div>
    </div>

    <div class="overlay" id="overlayRoot">
        <div class="hud" id="topHud">
            <div>
                <span class="badge-live" id="liveBadge">LIVE</span>
            </div>
            <div class="stats" id="statsLine">FPS — · Độ trễ — · Đã xem 00:00</div>
        </div>
        <div class="bottom-bar" id="bottomHud">
            <button type="button" class="fs-btn" id="fsBtn">Toàn màn hình</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /**
     * Trang VIEWER — luồng tóm tắt:
     * 1) Poll GET /signal/status mỗi 2s → biết camera có heartbeat hay không.
     * 2) Khi online: tạo RTCPeerConnection (không addTransceiver — SDP tự negotiate), poll GET /signal/poll?role=viewer mỗi 300ms.
     * 3) Nhận offer → setRemoteDescription → createAnswer → POST /signal/answer.
     * 4) ICE trickle: gửi POST /signal/ice (role=viewer), nhận ICE camera qua poll và addIceCandidate (hàng đợi nếu chưa có remote SDP).
     * 5) ontrack gán MediaStream vào <video>; đếm FPS và RTT ước lượng từ getStats.
     * 6) Mất kết nối → tự reconnect sau 5 giây nếu camera vẫn online.
     */

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

    var rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ]
    };

    var elStage = document.getElementById('stage');
    var elVideo = document.getElementById('remoteVideo');
    var elOffline = document.getElementById('offlineScreen');
    var elLoad = document.getElementById('loadingLayer');
    var elLoadText = document.getElementById('loadingText');
    var elLive = document.getElementById('liveBadge');
    var elStats = document.getElementById('statsLine');
    var elTop = document.getElementById('topHud');
    var elBot = document.getElementById('bottomHud');
    var elFs = document.getElementById('fsBtn');

    var pc = null;
    var pollSigTimer = null;
    var statusTimer = null;
    var reconnectTimer = null;
    var uiHideTimer = null;

    var cameraOnline = false;
    var sessionId = null;
    var seenIce = {};
    var pendingIce = [];
    var remoteSet = false;
    var watchStartedAt = null;
    var lastFpsDisplay = null;

    // --- Ẩn HUD sau 3s, hiện khi hover/chạm ---
    function showHud() {
        elTop.classList.remove('hide');
        elBot.classList.remove('hide');
        if (uiHideTimer) clearTimeout(uiHideTimer);
        uiHideTimer = setTimeout(function () {
            elTop.classList.add('hide');
            elBot.classList.add('hide');
        }, 3000);
    }
    elStage.addEventListener('mousemove', showHud);
    elStage.addEventListener('touchstart', showHud, { passive: true });
    showHud();

    function formatWatch(ms) {
        var s = Math.floor(ms / 1000);
        var m = Math.floor(s / 60);
        s = s % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    /** Bắt đầu đồng hồ “đã xem”; thời gian được đọc trong vòng lặp stats. */
    function startWatch() {
        if (watchStartedAt) return;
        watchStartedAt = Date.now();
    }

    function stopWatch() {
        watchStartedAt = null;
    }

    // --- FPS: requestVideoFrameCallback hoặc setInterval ---
    var fpsCount = 0;
    var fpsLast = performance.now();
    function startFps() {
        fpsCount = 0;
        fpsLast = performance.now();
        if (elVideo.requestVideoFrameCallback) {
            function cb() {
                fpsCount++;
                elVideo.requestVideoFrameCallback(cb);
            }
            elVideo.requestVideoFrameCallback(cb);
        } else {
            // Android cũ: đếm khung qua requestAnimationFrame (nhẹ hơn setInterval(0))
            function rafTick() {
                fpsCount++;
                requestAnimationFrame(rafTick);
            }
            requestAnimationFrame(rafTick);
        }
    }

    function iceKey(c) {
        return (c.candidate || '') + '|' + (c.sdpMid || '') + '|' + (c.sdpMLineIndex != null ? c.sdpMLineIndex : '');
    }

    /**
     * Chuẩn hoá SDP cho WebRTC (đặc biệt Android/Chrome cũ):
     * - Bắt buộc kết thúc dòng CRLF
     * - Gỡ ký tự điều khiển / khoảng thừa cuối dòng
     * - Trường hợp SDP bị lưu dạng literal "\\n" thay vì xuống dòng thật
     */
    function normalizeSdpForWebRTC(sdp) {
        if (!sdp || typeof sdp !== 'string') return sdp;
        sdp = sdp.replace(/^\ufeff/, '');
        if (sdp.indexOf('\\n') !== -1 && sdp.indexOf('\n') === -1) {
            sdp = sdp.replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n').replace(/\\r/g, '\n');
        }
        var lines = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        var out = [];
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i]
                .replace(/\u00a0/g, ' ')
                .replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '')
                .replace(/\s+$/g, '');
            if (line.length) out.push(line);
        }
        return out.join('\r\n') + '\r\n';
    }

    function sendIce(role, candidate) {
        fetch('/signal/ice', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ role: role, candidate: candidate.toJSON() })
        }).catch(function () {});
    }

    function flushIceQueue() {
        if (!pc || !pc.remoteDescription) return;
        while (pendingIce.length) {
            var raw = pendingIce.shift();
            pc.addIceCandidate(new RTCIceCandidate(raw)).catch(function () {});
        }
    }

    function stopSigPoll() {
        if (pollSigTimer) {
            clearInterval(pollSigTimer);
            pollSigTimer = null;
        }
    }

    function teardown() {
        stopSigPoll();
        stopWatch();
        remoteSet = false;
        sessionId = null;
        seenIce = {};
        pendingIce = [];
        elLive.classList.remove('on');
        if (pc) {
            pc.ontrack = null;
            pc.onicecandidate = null;
            pc.onconnectionstatechange = null;
            pc.close();
            pc = null;
        }
        elVideo.srcObject = null;
    }

    function setUiState(mode) {
        // mode: 'offline' | 'waiting' | 'connecting' | 'live'
        if (mode === 'offline') {
            elOffline.classList.remove('hidden');
            elLoad.classList.remove('on');
            elLoadText.textContent = 'Đang chờ camera…';
        } else {
            elOffline.classList.add('hidden');
        }
        if (mode === 'waiting') {
            elLoad.classList.add('on');
            elLoadText.textContent = 'Đang chờ camera…';
            elLoadText.classList.add('blink');
        }
        if (mode === 'connecting') {
            elLoad.classList.add('on');
            elLoadText.textContent = 'Đang kết nối WebRTC…';
            elLoadText.classList.add('blink');
        }
        if (mode === 'live') {
            elLoad.classList.remove('on');
            elLoadText.classList.remove('blink');
        }
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null;
            teardown();
            if (cameraOnline) startHandshake();
        }, 5000);
    }

    function updateStatsLine(fps, latencyMs) {
        var watch = watchStartedAt ? (Date.now() - watchStartedAt) : 0;
        var latStr = latencyMs != null && !isNaN(latencyMs) ? (Math.round(latencyMs) + ' ms') : '—';
        elStats.textContent = 'FPS ' + (fps != null ? fps : '—') + ' · Độ trễ ~' + latStr + ' · Đã xem ' + formatWatch(watch);
    }

    setInterval(function () {
        var now = performance.now();
        if (now - fpsLast >= 1000) {
            lastFpsDisplay = Math.round(fpsCount * 1000 / (now - fpsLast));
            fpsCount = 0;
            fpsLast = now;
        }
    }, 200);

    setInterval(function () {
        if (!pc) {
            updateStatsLine(lastFpsDisplay, null);
            return;
        }
        pc.getStats(null).then(function (stats) {
            var rtt = null;
            stats.forEach(function (r) {
                if (r.type === 'candidate-pair' && r.state === 'succeeded' && r.currentRoundTripTime != null) {
                    rtt = r.currentRoundTripTime * 1000;
                }
            });
            updateStatsLine(lastFpsDisplay, rtt);
        }).catch(function () {
            updateStatsLine(lastFpsDisplay, null);
        });
    }, 500);

    // --- Bắt đầu handshake khi đã biết camera online ---
    function startHandshake() {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
        teardown();
        setUiState('connecting');
        elLoadText.textContent = 'Đang kết nối…';

        pc = new RTCPeerConnection(rtcConfig);

        // KHÔNG dùng addTransceiver — để SDP tự negotiate

        pc.onicecandidate = function (ev) {
            if (ev.candidate) sendIce('viewer', ev.candidate);
        };

        pc.ontrack = function (ev) {
            console.log('[Viewer] ontrack:', ev.track.kind, ev.streams);
            if (ev.streams && ev.streams[0]) {
                elVideo.srcObject = ev.streams[0];
            } else {
                var ms = elVideo.srcObject instanceof MediaStream
                    ? elVideo.srcObject
                    : new MediaStream();
                ms.addTrack(ev.track);
                elVideo.srcObject = ms;
            }
            startFps();
            startWatch();
            setUiState('live');
            elLive.classList.add('on');
        };

        pc.onconnectionstatechange = function () {
            var s = pc.connectionState;
            console.log('[Viewer] connectionState:', s);
            if (s === 'failed' || s === 'disconnected' || s === 'closed') {
                elLive.classList.remove('on');
                scheduleReconnect();
            }
            if (s === 'connected') {
                console.log('[Viewer] P2P kết nối thành công!');
            }
        };

        pollSigTimer = setInterval(function () {
            fetch('/signal/poll?role=viewer')
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!pc) return;

                    if (j.session_id && sessionId && j.session_id !== sessionId && remoteSet) {
                        scheduleReconnect();
                        return;
                    }
                    if (j.session_id) {
                        sessionId = j.session_id;
                    }

                    if (j.offer && !remoteSet) {
                        remoteSet = true;
                        console.log('[Viewer] Nhận offer, setRemoteDescription...');

                        var rawOffer = j.offer;
                        var offerDesc = {
                            type: (rawOffer.type || 'offer').toLowerCase(),
                            sdp: normalizeSdpForWebRTC(rawOffer.sdp || '')
                        };

                        pc.setRemoteDescription(new RTCSessionDescription(offerDesc))
                            .then(function () {
                                console.log('[Viewer] setRemoteDescription OK, tạo answer...');
                                flushIceQueue();
                                return pc.createAnswer();
                            })
                            .then(function (answer) {
                                console.log('[Viewer] createAnswer OK');
                                return pc.setLocalDescription(answer).then(function () { return answer; });
                            })
                            .then(function (answer) {
                                console.log('[Viewer] Gửi answer lên server...');
                                return fetch('/signal/answer', {
                                    method: 'POST',
                                    headers: jsonHeaders(),
                                    body: JSON.stringify({ type: answer.type, sdp: answer.sdp })
                                });
                            })
                            .then(function (r) {
                                console.log('[Viewer] Answer gửi xong, status:', r.status);
                            })
                            .catch(function (e) {
                                console.error('[Viewer] Lỗi handshake:', e);
                                scheduleReconnect();
                            });
                    }

                    if (j.ice && j.ice.length) {
                        j.ice.forEach(function (raw) {
                            var k = iceKey(raw);
                            if (seenIce[k]) return;
                            seenIce[k] = true;
                            if (!pc.remoteDescription) pendingIce.push(raw);
                            else pc.addIceCandidate(new RTCIceCandidate(raw)).catch(function () {});
                        });
                    }
                })
                .catch(function () {});
        }, 300);
    }

    // --- Poll trạng thái camera mỗi 2 giây ---
    function checkCameraStatus() {
        fetch('/signal/status')
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var on = !!(j && j.online);
                if (on && !cameraOnline) {
                    cameraOnline = true;
                    setUiState('connecting');
                    startHandshake();
                } else if (!on && cameraOnline) {
                    cameraOnline = false;
                    teardown();
                    setUiState('offline');
                } else if (!on) {
                    setUiState('offline');
                }
            })
            .catch(function () {
                /* lỗi mạng — giữ trạng thái cũ */
            });
    }

    statusTimer = setInterval(checkCameraStatus, 2000);
    checkCameraStatus();

    // --- Fullscreen ---
    elFs.addEventListener('click', function () {
        if (!document.fullscreenElement) {
            elStage.requestFullscreen().catch(function () {});
            elStage.classList.add('fullscreen');
        } else {
            document.exitFullscreen();
            elStage.classList.remove('fullscreen');
        }
    });
    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) elStage.classList.remove('fullscreen');
    });
})();
</script>
</body>
</html>
