(function () {
    'use strict';

    const card = document.getElementById('voiceCard');
    if (!card) return;

    const startBtn = document.getElementById('startVoice');
    const stopBtn = document.getElementById('stopVoice');
    const againBtn = document.getElementById('recordAgainVoice');
    const removeBtn = document.getElementById('removeVoice');
    const player = document.getElementById('voicePlayer');
    const timer = document.getElementById('voiceTimer');
    const status = document.getElementById('voiceStatus');
    const errorBox = document.getElementById('voiceError');
    const visibility = document.getElementById('voiceVisibility');
    const wave = document.getElementById('voiceWave');
    const micCircle = document.getElementById('voiceMicCircle');

    let recorder = null;
    let stream = null;
    let chunks = [];
    let seconds = 0;
    let timerId = null;
    let recordedBlob = null;
    let recordedDuration = 0;
    let mimeType = '';

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message || '';
        errorBox.classList.toggle('d-none', !message);
    }

    function formatTime(value) {
        const mins = Math.floor(value / 60).toString().padStart(2, '0');
        const secs = (value % 60).toString().padStart(2, '0');
        return mins + ':' + secs;
    }

    function updateTimer() {
        timer.textContent = formatTime(seconds) + ' / 00:30';
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }

    function stopClock() {
        if (timerId) window.clearInterval(timerId);
        timerId = null;
    }

    function resetRecordingUI() {
        stopClock();
        stopStream();
        startBtn.classList.remove('d-none');
        stopBtn.classList.add('d-none');
        againBtn.classList.add('d-none');
        wave.classList.remove('is-recording');
        micCircle.classList.remove('is-recording');
        updateTimer();
    }

    function chooseMimeType() {
        const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];
        return candidates.find(type => window.MediaRecorder && MediaRecorder.isTypeSupported(type)) || '';
    }

    async function startRecording() {
        showError('');
        recordedBlob = null;
        recordedDuration = 0;

        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            showError('Microphone access requires a secure HTTPS connection.');
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            showError('Voice recording is not supported by this browser. Please use a modern Chrome, Edge, Firefox or Safari browser.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mimeType = chooseMimeType();
            recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
            chunks = [];
            seconds = 0;
            updateTimer();

            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) chunks.push(event.data);
            };

            recorder.onstop = function () {
                recordedBlob = new Blob(chunks, { type: recorder.mimeType || mimeType || 'audio/webm' });
                recordedDuration = Math.max(1, Math.min(30, seconds));
                const url = URL.createObjectURL(recordedBlob);
                player.src = url;
                player.classList.remove('d-none');
                status.textContent = 'Recording ready. Play it back, then Save & Continue to keep it.';
                resetRecordingUI();
                againBtn.classList.remove('d-none');
            };

            recorder.start();
            startBtn.classList.add('d-none');
            stopBtn.classList.remove('d-none');
            againBtn.classList.add('d-none');
            wave.classList.add('is-recording');
            micCircle.classList.add('is-recording');
            status.textContent = 'Recording… speak naturally. You have up to 30 seconds.';

            timerId = window.setInterval(function () {
                seconds += 1;
                updateTimer();
                if (seconds >= 30) stopRecording();
            }, 1000);
        } catch (error) {
            stopStream();
            if (error && error.name === 'NotAllowedError') {
                showError('Microphone access was blocked. Allow microphone permission for this site, then try again.');
            } else if (error && error.name === 'NotFoundError') {
                showError('No microphone was found on this device.');
            } else if (error && error.name === 'NotReadableError') {
                showError('The microphone is currently in use by another application.');
            } else {
                showError('Unable to start the microphone. Please check browser permission and try again.');
            }
        }
    }

    function stopRecording() {
        stopClock();
        if (recorder && recorder.state !== 'inactive') recorder.stop();
    }

    function saveVoiceBeforeContinue() {
        return new Promise(function (resolve) {
            if (!recordedBlob) {
                resolve(true);
                return;
            }

            const formData = new FormData();
            const extension = (recordedBlob.type || '').includes('ogg') ? 'ogg' : 'webm';
            formData.append('voice', recordedBlob, 'voice.' + extension);
            formData.append('duration_seconds', String(recordedDuration));
            formData.append('visibility', visibility.value);
            formData.append('action', 'save');

            const saveButton = document.querySelector('button[name="save_step5"]');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.classList.add('disabled');
            }
            status.textContent = 'Saving your voice introduction…';

            fetch('save_voice.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to save voice introduction.');
                    resolve(true);
                })
                .catch(error => {
                    showError(error.message || 'Unable to save voice introduction.');
                    status.textContent = 'Your recording is still available above.';
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.classList.remove('disabled');
                    }
                    resolve(false);
                });
        });
    }

    startBtn.addEventListener('click', startRecording);
    stopBtn.addEventListener('click', stopRecording);
    againBtn.addEventListener('click', function () {
        player.pause();
        player.removeAttribute('src');
        player.classList.add('d-none');
        recordedBlob = null;
        recordedDuration = 0;
        seconds = 0;
        updateTimer();
        status.textContent = 'Ready for a new recording.';
        againBtn.classList.add('d-none');
        startBtn.classList.remove('d-none');
        showError('');
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (!window.confirm('Remove your voice introduction?')) return;
            removeBtn.disabled = true;
            fetch('save_voice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'action=remove',
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to remove voice introduction.');
                    player.pause();
                    player.removeAttribute('src');
                    player.load();
                    player.classList.add('d-none');
                    status.textContent = 'Voice introduction removed. You can record a new one anytime.';
                    removeBtn.classList.add('d-none');
                    againBtn.classList.add('d-none');
                    startBtn.classList.remove('d-none');
                    showError('');
                })
                .catch(error => {
                    showError(error.message);
                    removeBtn.disabled = false;
                });
        });
    }

    visibility.addEventListener('change', function () {
        if (recordedBlob) status.textContent = 'Recording ready. Save & Continue to apply the new visibility setting.';
    });

    // Video Introduction
    const videoCard = document.getElementById('videoCard');
    let videoRecorder = null;
    let videoStream = null;
    let videoChunks = [];
    let videoSeconds = 0;
    let videoTimerId = null;
    let videoBlob = null;
    let videoDuration = 0;
    let videoMimeType = '';
    let videoCanvas = null;
    let videoCanvasContext = null;
    let videoDrawFrame = null;
    let recordingStream = null;

    function initVideo() {
        if (!videoCard) return;
        const start = document.getElementById('startVideo');
        const stop = document.getElementById('stopVideo');
        const again = document.getElementById('recordAgainVideo');
        const remove = document.getElementById('removeVideo');
        const player = document.getElementById('videoPreview');
        const placeholder = document.getElementById('videoPlaceholder');
        const badge = document.getElementById('videoRecordingBadge');
        const timerEl = document.getElementById('videoTimer');
        const statusEl = document.getElementById('videoStatus');
        const errorEl = document.getElementById('videoError');
        const visibilityEl = document.getElementById('videoVisibility');
        let livePreviewCanvas = null;
        let livePreviewContext = null;
        let livePreviewFrame = null;

        function error(message) {
            if (!errorEl) return;
            errorEl.textContent = message || '';
            errorEl.classList.toggle('d-none', !message);
        }
        function format(value) {
            return String(Math.floor(value / 60)).padStart(2, '0') + ':' + String(value % 60).padStart(2, '0');
        }
        function updateTimer() { timerEl.textContent = format(videoSeconds) + ' / 00:30'; }
        function stopStream() {
            if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
        }
        function stopClock() { if (videoTimerId) clearInterval(videoTimerId); videoTimerId = null; }
        function mime() {
            const candidates = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
            return candidates.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
        }
        function resetUI() {
            stopClock(); stopStream();
            start.classList.remove('d-none'); stop.classList.add('d-none'); badge.classList.add('d-none');
            updateTimer();
        }
        function startRawLivePreview() {
            if (!livePreviewCanvas) {
                livePreviewCanvas = document.createElement('canvas');
                livePreviewCanvas.className = 'video-preview-live-canvas';
                livePreviewCanvas.setAttribute('aria-hidden', 'true');
                livePreviewCanvas.style.width = '100%';
                livePreviewCanvas.style.height = '100%';
                livePreviewCanvas.style.objectFit = 'cover';
                livePreviewCanvas.style.display = 'block';
                livePreviewCanvas.style.background = '#eaf4f0';
                player.parentNode.insertBefore(livePreviewCanvas, player.nextSibling);
                livePreviewContext = livePreviewCanvas.getContext('2d', { alpha: false });
            }

            player.style.display = 'none';
            livePreviewCanvas.style.display = 'block';

            if (livePreviewFrame) window.cancelAnimationFrame(livePreviewFrame);
            const draw = function () {
                if (!livePreviewContext || !player.videoWidth || !player.videoHeight) {
                    livePreviewFrame = window.requestAnimationFrame(draw);
                    return;
                }

                const vw = player.videoWidth;
                const vh = player.videoHeight;
                const targetRatio = 16 / 9;
                let sx = 0, sy = 0, sw = vw, sh = vh;
                const sourceRatio = vw / vh;
                if (sourceRatio > targetRatio) {
                    sw = Math.round(vh * targetRatio);
                    sx = Math.floor((vw - sw) / 2);
                } else if (sourceRatio < targetRatio) {
                    sh = Math.round(vw / targetRatio);
                    sy = Math.floor((vh - sh) / 2);
                }

                const rect = livePreviewCanvas.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;
                const cw = Math.max(1, Math.round(rect.width * dpr));
                const ch = Math.max(1, Math.round(rect.height * dpr));
                if (livePreviewCanvas.width !== cw || livePreviewCanvas.height !== ch) {
                    livePreviewCanvas.width = cw;
                    livePreviewCanvas.height = ch;
                }

                // The browser's front-camera stream is displayed mirrored on the live preview.
                // Flip only this canvas rendering so the live preview is visually non-mirrored.
                // The recorded-video canvas below remains unchanged.
                livePreviewContext.setTransform(-1, 0, 0, 1, cw, 0);
                livePreviewContext.drawImage(player, sx, sy, sw, sh, 0, 0, cw, ch);
                livePreviewFrame = window.requestAnimationFrame(draw);
            };
            livePreviewFrame = window.requestAnimationFrame(draw);
        }

        function stopRawLivePreview() {
            if (livePreviewFrame) window.cancelAnimationFrame(livePreviewFrame);
            livePreviewFrame = null;
            if (livePreviewCanvas) livePreviewCanvas.style.display = 'none';
            player.style.display = '';
        }

        async function startRecording() {
            error(''); videoBlob = null; videoDuration = 0;
            const existingSrc = player.getAttribute('src') || '';
            if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                error('Camera and microphone access requires a secure HTTPS connection.'); return;
            }
            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                error('Video recording is not supported by this browser. Please use a modern browser.'); return;
            }
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true });
                videoMimeType = mime();
                videoChunks = []; videoSeconds = 0; updateTimer();
                player.srcObject = videoStream;
                // Front-facing camera previews are normally mirrored by the browser.
                // Unmirror only the live recording preview; the recorded file logic stays unchanged.
                player.style.setProperty('transform', 'none', 'important');
                player.muted = true;
                player.autoplay = true;
                player.playsInline = true;
                player.setAttribute('autoplay', 'autoplay');
                player.setAttribute('playsinline', 'playsinline');
                player.controls = false;
                player.classList.remove('d-none');
                placeholder.classList.add('d-none');
                try { await player.play(); } catch (previewError) {
                    player.onloadedmetadata = () => { player.play().catch(() => {}); };
                }
                startRawLivePreview();

                // Flip the canvas horizontally so the saved video is NOT mirrored.
                // The camera preview itself remains untouched; only the MediaRecorder input is corrected.
                await new Promise(function (resolve) {
                    if (player.readyState >= 2 && player.videoWidth && player.videoHeight) {
                        resolve();
                        return;
                    }
                    const onReady = function () {
                        player.removeEventListener('loadedmetadata', onReady);
                        resolve();
                    };
                    player.addEventListener('loadedmetadata', onReady, { once: true });
                });

                videoCanvas = document.createElement('canvas');
                videoCanvas.width = player.videoWidth || 1280;
                videoCanvas.height = player.videoHeight || 720;
                videoCanvasContext = videoCanvas.getContext('2d', { alpha: false });
                if (!videoCanvasContext || !videoCanvas.captureStream) {
                    throw new Error('This browser does not support the video recording format required for this feature.');
                }

                const drawVideoFrame = function () {
                    if (!videoCanvasContext || !videoCanvas) return;
                    videoCanvasContext.save();
                    videoCanvasContext.translate(videoCanvas.width, 0);
                    videoCanvasContext.scale(-1, 1);
                    videoCanvasContext.drawImage(player, 0, 0, videoCanvas.width, videoCanvas.height);
                    videoCanvasContext.restore();
                    videoDrawFrame = window.requestAnimationFrame(drawVideoFrame);
                };
                drawVideoFrame();

                recordingStream = videoCanvas.captureStream(30);
                videoStream.getAudioTracks().forEach(function (track) {
                    recordingStream.addTrack(track);
                });

                videoRecorder = videoMimeType ? new MediaRecorder(recordingStream, { mimeType: videoMimeType }) : new MediaRecorder(recordingStream);
                videoRecorder.ondataavailable = e => { if (e.data?.size) videoChunks.push(e.data); };
                videoRecorder.onstop = function () {
                    videoBlob = new Blob(videoChunks, { type: videoRecorder.mimeType || videoMimeType || 'video/webm' });
                    videoDuration = Math.max(1, Math.min(30, videoSeconds));
                    if (videoDrawFrame) {
                        window.cancelAnimationFrame(videoDrawFrame);
                        videoDrawFrame = null;
                    }
                    if (recordingStream) {
                        recordingStream.getTracks().forEach(function (track) { track.stop(); });
                        recordingStream = null;
                    }
                    videoCanvas = null;
                    videoCanvasContext = null;
                    stopRawLivePreview();
                    if (player.srcObject) player.srcObject = null;
                    player.style.transform = '';
                    player.src = URL.createObjectURL(videoBlob); player.muted = false; player.autoplay = false; player.controls = true; player.classList.remove('d-none');
                    statusEl.textContent = 'Recording ready. Play it back, then Save & Continue to keep it.';
                    again.classList.remove('d-none'); resetUI();
                };
                videoRecorder.start(); start.classList.add('d-none'); stop.classList.remove('d-none'); badge.classList.remove('d-none');
                statusEl.textContent = 'Recording… speak naturally. You have up to 30 seconds.';
                videoTimerId = setInterval(() => { videoSeconds++; updateTimer(); if (videoSeconds >= 30) stopRecording(); }, 1000);
            } catch (e) {
                stopRawLivePreview();
                stopStream();
                if (videoDrawFrame) {
                    window.cancelAnimationFrame(videoDrawFrame);
                    videoDrawFrame = null;
                }
                if (recordingStream) {
                    recordingStream.getTracks().forEach(function (track) { track.stop(); });
                    recordingStream = null;
                }
                videoCanvas = null;
                videoCanvasContext = null;
                player.pause();
                player.srcObject = null;
                player.removeAttribute('src');
                if (existingSrc) {
                    player.setAttribute('src', existingSrc);
                    player.controls = true;
                    player.classList.remove('d-none');
                    placeholder.classList.add('d-none');
                    player.load();
                } else {
                    player.classList.add('d-none');
                    placeholder.classList.remove('d-none');
                }
                start.classList.remove('d-none');
                stop.classList.add('d-none');
                badge.classList.add('d-none');
                statusEl.textContent = existingSrc ? 'Your current video introduction is ready to play.' : 'Optional — record up to 30 seconds.';
                updateTimer();
                if (e && e.name === 'NotAllowedError') {
                    error('Camera and microphone access was blocked. Allow permissions for this site, then try again.');
                } else if (e && e.name === 'NotFoundError') {
                    error('A camera and microphone are required for video recording.');
                } else if (e && e.name === 'NotReadableError') {
                    error('The camera or microphone is currently in use by another application.');
                } else {
                    error('Unable to start video recording. Please check browser permissions and try again.');
                }
            }
        }
        function stopRecording() { stopClock(); if (videoRecorder && videoRecorder.state !== 'inactive') videoRecorder.stop(); }
        start.addEventListener('click', startRecording); stop.addEventListener('click', stopRecording);
        again.addEventListener('click', function () {
            stopRawLivePreview();
            player.pause(); player.removeAttribute('src'); player.srcObject = null;
            if (videoDrawFrame) { window.cancelAnimationFrame(videoDrawFrame); videoDrawFrame = null; }
            if (recordingStream) { recordingStream.getTracks().forEach(function (track) { track.stop(); }); recordingStream = null; }
            videoCanvas = null; videoCanvasContext = null;
            videoBlob = null; videoDuration = 0; videoSeconds = 0; updateTimer(); again.classList.add('d-none'); start.classList.remove('d-none');
            placeholder.classList.remove('d-none'); statusEl.textContent = 'Ready for a new recording.'; error('');
        });
        if (remove) {
            remove.addEventListener('click', function () {
                if (!confirm('Remove your video introduction?')) return;
                remove.disabled = true;
                fetch('save_video.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:'action=remove', credentials:'same-origin' })
                .then(r => r.json()).then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to remove video introduction.');
                    stopRawLivePreview();
                    player.pause(); player.removeAttribute('src'); player.srcObject = null; player.style.transform = ''; player.classList.add('d-none'); placeholder.classList.remove('d-none');
                    videoBlob = null; again.classList.add('d-none'); start.classList.remove('d-none'); statusEl.textContent = 'Video introduction removed. You can record a new one anytime.'; remove.classList.add('d-none'); error('');
                }).catch(e => { error(e.message); remove.disabled = false; });
            });
        }
        updateTimer();
    }
    initVideo();

    function saveVideoBeforeContinue() {
        return new Promise(function (resolve) {
            if (!videoBlob) { resolve(true); return; }
            const fd = new FormData();
            const ext = (videoBlob.type || '').includes('mp4') ? 'mp4' : 'webm';
            fd.append('video', videoBlob, 'video.' + ext); fd.append('duration_seconds', String(videoDuration));
            fd.append('visibility', document.getElementById('videoVisibility').value); fd.append('action', 'save');
            fetch('save_video.php', { method:'POST', body:fd, credentials:'same-origin' })
                .then(r => r.json()).then(data => { if (!data.success) throw new Error(data.message || 'Unable to save video introduction.'); resolve(true); })
                .catch(e => { const box=document.getElementById('videoError'); box.textContent=e.message; box.classList.remove('d-none'); resolve(false); });
        });
    }

    // Accessibility checks — Step 5
    // Keep these checks on this page only; they persist the pass state through
    // the existing accessibility_check.php endpoint.
    const hearingPlay = document.getElementById('playHearingCheck');
    const hearingReplay = document.getElementById('replayHearingCheck');
    const hearingAnswer = document.getElementById('hearingCheckAnswer');
    const hearingVerify = document.getElementById('verifyHearingCheck');
    const hearingStatus = document.getElementById('hearingCheckStatus');
    const hearingCard = document.getElementById('hearingCheckCard');
    const hearingPrompt = document.getElementById('hearingCheckPrompt');

    const visionImage = document.getElementById('visualCaptchaImage');
    const visionRefresh = document.getElementById('refreshVisualCaptcha');
    const visionAnswer = document.getElementById('visionCheckAnswer');
    const visionVerify = document.getElementById('verifyVisionCheck');
    const visionStatus = document.getElementById('visionCheckStatus');
    const visionCard = document.getElementById('visionCheckCard');

    function setCheckBusy(button, busy) {
        if (!button) return;
        button.disabled = busy;
        button.classList.toggle('disabled', busy);
    }

    function speakHearingCode() {
        if (!hearingPrompt) return;
        const code = hearingPrompt.dataset.code || '';
        if (!code) return;

        if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
            if (hearingStatus) hearingStatus.textContent = 'Audio playback is not supported by this browser.';
            return;
        }

        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(code.split('').join(' '));
        utterance.lang = 'en-US';
        utterance.rate = 0.8;
        utterance.pitch = 1;
        utterance.volume = 1;
        utterance.onstart = function () {
            if (hearingStatus && !hearingCard.classList.contains('is-passed')) {
                hearingStatus.textContent = 'Playing the four-digit code…';
            }
        };
        utterance.onend = function () {
            if (hearingStatus && !hearingCard.classList.contains('is-passed')) {
                hearingStatus.textContent = 'Enter the four digits you heard, then verify.';
            }
        };
        window.speechSynthesis.speak(utterance);
        if (hearingPlay) hearingPlay.classList.add('d-none');
        if (hearingReplay) hearingReplay.classList.remove('d-none');
    }

    async function verifyAccessibilityCheck(check, answer, button, status, card) {
        const value = (answer?.value || '').trim();
        if (!value) {
            if (status) status.textContent = 'Please enter your answer.';
            answer?.focus();
            return;
        }

        setCheckBusy(button, true);
        try {
            const body = new URLSearchParams();
            body.set('check', check);
            body.set('answer', value);

            const response = await fetch('accessibility_check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Unable to verify this check.');

            if (status) status.textContent = '✓ ' + data.message;
            if (card) card.classList.add('is-passed');
            if (answer) answer.disabled = true;
            if (button) button.classList.add('d-none');
        } catch (error) {
            if (status) status.textContent = error.message || 'Unable to verify this check.';
        } finally {
            if (button && !button.classList.contains('d-none')) setCheckBusy(button, false);
        }
    }

    if (hearingPlay) hearingPlay.addEventListener('click', speakHearingCode);
    if (hearingReplay) hearingReplay.addEventListener('click', speakHearingCode);
    if (hearingVerify) hearingVerify.addEventListener('click', function () {
        verifyAccessibilityCheck('hearing', hearingAnswer, hearingVerify, hearingStatus, hearingCard);
    });
    if (hearingAnswer) hearingAnswer.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            hearingVerify?.click();
        }
    });

    if (visionRefresh && visionImage) {
        visionRefresh.addEventListener('click', function () {
            setCheckBusy(visionRefresh, true);
            visionImage.src = 'accessibility_captcha.php?' + Date.now();
            if (visionAnswer) visionAnswer.value = '';
            if (visionStatus && !visionCard.classList.contains('is-passed')) visionStatus.textContent = 'New visual code generated.';
            window.setTimeout(function () { setCheckBusy(visionRefresh, false); }, 250);
        });
    }
    if (visionVerify) visionVerify.addEventListener('click', function () {
        verifyAccessibilityCheck('vision', visionAnswer, visionVerify, visionStatus, visionCard);
    });
    if (visionAnswer) visionAnswer.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            visionVerify?.click();
        }
    });

    const form = document.querySelector('.step5-form');
    if (form) {
        let mediaSaveInProgress = false;

        async function submitStep5AndContinue() {
            /*
             * Submit through the browser after media uploads finish.
             * HTMLFormElement.prototype.submit() bypasses this submit listener,
             * while the hidden marker below lets step5.php enter its save block.
             * This also lets the browser follow PHP's Location: step6.php
             * redirect normally instead of relying on fetch()'s final URL.
             */
            let marker = form.querySelector('input[data-step5-submit-marker="1"]');
            if (!marker) {
                marker = document.createElement('input');
                marker.type = 'hidden';
                marker.name = 'save_step5';
                marker.value = '1';
                marker.setAttribute('data-step5-submit-marker', '1');
                form.appendChild(marker);
            }

            HTMLFormElement.prototype.submit.call(form);
        }

        form.addEventListener('submit', function (event) {
            const submitter = event.submitter;
            if (!submitter || submitter.name !== 'save_step5' || mediaSaveInProgress) return;
            if (!recordedBlob && !videoBlob) return;

            event.preventDefault();
            mediaSaveInProgress = true;
            submitter.disabled = true;
            submitter.classList.add('disabled');

            saveVoiceBeforeContinue()
                .then(ok => ok ? saveVideoBeforeContinue() : false)
                .then(ok => {
                    if (!ok) {
                        mediaSaveInProgress = false;
                        submitter.disabled = false;
                        submitter.classList.remove('disabled');
                        return;
                    }

                    submitStep5AndContinue();
                })
                .catch(error => {
                    const message = error.message || 'Unable to continue. Please try again.';
                    const voiceError = document.getElementById('voiceError');
                    const videoError = document.getElementById('videoError');
                    if (voiceError) {
                        voiceError.textContent = message;
                        voiceError.classList.remove('d-none');
                    } else if (videoError) {
                        videoError.textContent = message;
                        videoError.classList.remove('d-none');
                    }
                    mediaSaveInProgress = false;
                    submitter.disabled = false;
                    submitter.classList.remove('disabled');
                });
        });
    }

})();
