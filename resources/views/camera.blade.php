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
     * 2) Mỗi viewer có UUID: server hàng đợi pending_viewers; camera tạo RTCPeerConnection + offer / viewer.
     * 3) Poll GET /signal/poll?role=camera&viewers=id1,id2 để nhận answer + ICE từng viewer.
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

    // --- Trạng thái WebRTC: nhiều viewer, mỗi viewer một RTCPeerConnection ---
    var localStream = null;
    var broadcastId = null;
    /** @type {Map<string, object>} viewerId -> { pc, remoteAnswerApplied, pendingRemoteIce, seenIceKeys } */
    var peers = new Map();
    var pollTimer = null;
    var hbTimer = null;

    var elVideo = document.getElementById('localVideo');
    var elLoad = document.getElementById('loadingMask');
    var elRec = document.getElementById('recBadge');
    var elFps = document.getElementById('fpsBadge');
    var elDot = document.getElementById('statusDot');
    var elStatus = document.getElementById('statusText');
    var elViewerMeta = document.getElementById('viewerMeta');

    var rtcConfig = {
        iceServers: @json($iceServers),
        iceCandidatePoolSize: 10
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

    function updateViewerCountFromStats() {
        var n = 0;
        peers.forEach(function (st) {
            if (st.pc && st.pc.connectionState === 'connected') n++;
        });
        elViewerMeta.textContent = 'Viewer: ' + n;
    }
    setInterval(updateViewerCountFromStats, 1000);

    function heartbeat() {
        var body = { online: true };
        if (broadcastId) body.broadcast_id = broadcastId;
        fetch('/signal/status', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(body)
        }).catch(function () {});
    }

    function sendLocalIce(candidate, viewerId) {
        if (!candidate || !candidate.candidate) return;
        fetch('/signal/ice', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({
                role: 'camera',
                viewer_id: viewerId,
                candidate: candidate.toJSON()
            })
        }).catch(function () {});
    }

    function iceKeyStr(c) {
        return (c.candidate || '') + '|' + (c.sdpMid || '') + '|' + (c.sdpMLineIndex != null ? c.sdpMLineIndex : '');
    }

    function flushPendingIceFor(st) {
        if (!st.pc || !st.pc.remoteDescription) return;
        while (st.pendingRemoteIce.length) {
            var raw = st.pendingRemoteIce.shift();
            st.pc.addIceCandidate(new RTCIceCandidate(raw)).catch(function () {});
        }
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /** Chờ connectionState disconnected trước khi gỡ peer (rung/sóng yếu hay tự hồi) */
    var DISCONNECT_GRACE_MS = 20000;

    function removePeer(viewerId) {
        var st = peers.get(viewerId);
        if (!st) return;
        if (st.disconnectTimer) {
            clearTimeout(st.disconnectTimer);
            st.disconnectTimer = null;
        }
        if (st.iceDisconnectTimer) {
            clearTimeout(st.iceDisconnectTimer);
            st.iceDisconnectTimer = null;
        }
        if (st.failedRemoveTimer) {
            clearTimeout(st.failedRemoveTimer);
            st.failedRemoveTimer = null;
        }
        if (st.pc) {
            st.pc.onicecandidate = null;
            st.pc.oniceconnectionstatechange = null;
            st.pc.onconnectionstatechange = null;
            st.pc.close();
        }
        peers.delete(viewerId);
    }

    function teardownAllPeers() {
        stopPoll();
        peers.forEach(function (_, vid) { removePeer(vid); });
        peers.clear();
    }

    function makePeerState() {
        return {
            pc: null,
            remoteAnswerApplied: false,
            pendingRemoteIce: [],
            seenIceKeys: {},
            disconnectTimer: null,
            iceDisconnectTimer: null,
            failedRemoveTimer: null,
            lastIceRestart: 0
        };
    }

    /** Gửi lại offer (iceRestart) khi ICE failed/disconnected lâu — rung mạnh / Android hay làm ICE tụt */
    function tryIceRestartForViewer(viewerId) {
        var st = peers.get(viewerId);
        if (!st || !st.pc || st.pc.signalingState === 'closed') return;
        var now = Date.now();
        if (st.lastIceRestart && (now - st.lastIceRestart) < 6000) return;
        st.lastIceRestart = now;
        console.log('[Camera] ICE restart / offer mới cho viewer', viewerId);

        if (typeof st.pc.restartIce === 'function') {
            try { st.pc.restartIce(); } catch (e) { console.warn('[Camera] restartIce', e); }
        }

        st.remoteAnswerApplied = false;
        st.seenIceKeys = {};
        st.pendingRemoteIce = [];

        var p = st.pc.createOffer({ iceRestart: true });
        p = p.catch(function () { return st.pc.createOffer(); });

        p.then(function (offer) { return st.pc.setLocalDescription(offer).then(function () { return offer; }); })
            .then(function (offer) {
                return fetch('/signal/offer', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ viewer_id: viewerId, type: offer.type, sdp: offer.sdp })
                });
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error('offer');
            })
            .catch(function (e) {
                console.warn('[Camera] ICE restart thất bại', viewerId, e);
            });
    }

    function attachTrackEndedHandler(stream) {
        stream.getVideoTracks().forEach(function (track) {
            track.onended = function () {
                console.warn('[Camera] Track video kết thúc (rung/OS) — mở lại camera…');
                reopenCameraTracks();
            };
        });
    }

    function reopenCameraTracks() {
        if (!localStream) return;
        tryGetUserMedia('environment')
            .catch(function () { return tryGetUserMedia('user'); })
            .then(function (stream) {
                localStream.getTracks().forEach(function (t) { t.stop(); });
                localStream = stream;
                elVideo.srcObject = stream;
                attachTrackEndedHandler(stream);
                peers.forEach(function (st) {
                    if (!st.pc) return;
                    var vt = stream.getVideoTracks()[0];
                    if (!vt) return;
                    st.pc.getSenders().forEach(function (sender) {
                        if (sender.track && sender.track.kind === 'video') {
                            sender.replaceTrack(vt).catch(function () {});
                        }
                    });
                });
            })
            .catch(function (e) {
                console.error('[Camera] Không mở lại camera:', e);
                showErr('Camera ngắt sau rung — mở lại quyền camera.');
            });
    }

    function createPeerForViewer(viewerId) {
        if (!localStream || peers.has(viewerId)) return;
        var st = makePeerState();
        st.pc = new RTCPeerConnection(rtcConfig);
        peers.set(viewerId, st);

        localStream.getTracks().forEach(function (t) {
            st.pc.addTrack(t, localStream);
        });

        st.pc.onicecandidate = function (ev) {
            if (ev.candidate) sendLocalIce(ev.candidate, viewerId);
        };

        st.pc.oniceconnectionstatechange = function () {
            var ice = st.pc.iceConnectionState;
            if (ice === 'connected' || ice === 'completed') {
                if (st.iceDisconnectTimer) {
                    clearTimeout(st.iceDisconnectTimer);
                    st.iceDisconnectTimer = null;
                }
            }
            if (ice === 'failed') {
                tryIceRestartForViewer(viewerId);
            }
            if (ice === 'disconnected') {
                if (st.iceDisconnectTimer) clearTimeout(st.iceDisconnectTimer);
                st.iceDisconnectTimer = setTimeout(function () {
                    st.iceDisconnectTimer = null;
                    if (!peers.has(viewerId)) return;
                    var st2 = peers.get(viewerId);
                    if (!st2 || !st2.pc) return;
                    var i2 = st2.pc.iceConnectionState;
                    if (i2 === 'disconnected' || i2 === 'failed') {
                        tryIceRestartForViewer(viewerId);
                    }
                }, 4000);
            }
        };

        st.pc.onconnectionstatechange = function () {
            var s = st.pc.connectionState;
            if (s === 'connected') {
                if (st.disconnectTimer) {
                    clearTimeout(st.disconnectTimer);
                    st.disconnectTimer = null;
                }
                if (st.failedRemoveTimer) {
                    clearTimeout(st.failedRemoveTimer);
                    st.failedRemoveTimer = null;
                }
                setConnUi('P2P: ' + peers.size + ' viewer', true);
                elRec.classList.remove('off');
            }
            if (s === 'failed') {
                if (st.disconnectTimer) {
                    clearTimeout(st.disconnectTimer);
                    st.disconnectTimer = null;
                }
                tryIceRestartForViewer(viewerId);
                if (st.failedRemoveTimer) clearTimeout(st.failedRemoveTimer);
                st.failedRemoveTimer = setTimeout(function () {
                    st.failedRemoveTimer = null;
                    if (!peers.has(viewerId)) return;
                    var st3 = peers.get(viewerId);
                    if (!st3 || !st3.pc) return;
                    if (st3.pc.connectionState === 'failed' || st3.pc.connectionState === 'closed') {
                        removePeer(viewerId);
                        if (peers.size === 0) elRec.classList.add('off');
                        updateViewerCountFromStats();
                    }
                }, 8000);
            }
            if (s === 'closed') {
                if (st.disconnectTimer) {
                    clearTimeout(st.disconnectTimer);
                    st.disconnectTimer = null;
                }
                if (st.failedRemoveTimer) {
                    clearTimeout(st.failedRemoveTimer);
                    st.failedRemoveTimer = null;
                }
                removePeer(viewerId);
                if (peers.size === 0) elRec.classList.add('off');
                updateViewerCountFromStats();
            }
            if (s === 'disconnected') {
                if (st.disconnectTimer) clearTimeout(st.disconnectTimer);
                st.disconnectTimer = setTimeout(function () {
                    st.disconnectTimer = null;
                    if (!peers.has(viewerId)) return;
                    var st2 = peers.get(viewerId);
                    if (!st2 || !st2.pc) return;
                    var s2 = st2.pc.connectionState;
                    if (s2 === 'disconnected' || s2 === 'failed') {
                        removePeer(viewerId);
                        if (peers.size === 0) elRec.classList.add('off');
                        updateViewerCountFromStats();
                    }
                }, DISCONNECT_GRACE_MS);
            }
        };

        st.pc.createOffer()
            .then(function (offer) { return st.pc.setLocalDescription(offer).then(function () { return offer; }); })
            .then(function (offer) {
                console.log('[Camera] Gửi offer cho viewer', viewerId);
                return fetch('/signal/offer', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ viewer_id: viewerId, type: offer.type, sdp: offer.sdp })
                });
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error('Offer bị từ chối');
                console.log('[Offer] OK', viewerId, data);
            })
            .catch(function (e) {
                console.error('[Camera] Lỗi offer', viewerId, e);
                removePeer(viewerId);
                showErr('Lỗi offer viewer ' + viewerId.slice(0, 8) + '…');
            });
    }

    function pollCamera() {
        if (!localStream) return;
        var ids = Array.from(peers.keys());
        var qs = ids.length ? ('?role=camera&viewers=' + encodeURIComponent(ids.join(','))) : '?role=camera&viewers=';
        fetch('/signal/poll' + qs)
            .then(function (r) { return r.json(); })
            .then(function (j) {
                (j.pending_viewers || []).forEach(function (vid) {
                    var st = peers.get(vid);
                    if (st && st.pc) {
                        var cs = st.pc.connectionState;
                        if (cs === 'failed' || cs === 'closed') {
                            removePeer(vid);
                        }
                    }
                    if (!peers.has(vid)) createPeerForViewer(vid);
                });
                var answers = j.answers || {};
                Object.keys(answers).forEach(function (vid) {
                    var ans = answers[vid];
                    var st = peers.get(vid);
                    if (!st || !ans) return;
                    if (st.remoteAnswerApplied) return;
                    st.remoteAnswerApplied = true;
                    st.pc.setRemoteDescription(new RTCSessionDescription(ans))
                        .then(function () { flushPendingIceFor(st); })
                        .catch(function (e) {
                            showErr('Answer: ' + (e && e.message));
                        });
                });
                var iceMap = j.ice_viewer || {};
                Object.keys(iceMap).forEach(function (vid) {
                    var st = peers.get(vid);
                    if (!st) return;
                    (iceMap[vid] || []).forEach(function (raw) {
                        var k = iceKeyStr(raw);
                        if (st.seenIceKeys[k]) return;
                        st.seenIceKeys[k] = true;
                        if (!st.pc.remoteDescription) st.pendingRemoteIce.push(raw);
                        else st.pc.addIceCandidate(new RTCIceCandidate(raw)).catch(function () {});
                    });
                });
            })
            .catch(function () {});
    }

    /** Bắt đầu phát đa viewer: broadcast_id mới, poll hàng đợi + ICE/answer theo từng viewer_id */
    function beginSession() {
        teardownAllPeers();
        if (!localStream) return;
        broadcastId = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        setConnUi('Đang chờ viewer…', true);
        elRec.classList.add('off');
        heartbeat();
        pollTimer = setInterval(pollCamera, 300);
        pollCamera();
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
            attachTrackEndedHandler(stream);
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
