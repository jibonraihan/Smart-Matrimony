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
            showError('Microphone access was not granted. Please allow microphone access and try again.');
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

        function error(message) {
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
        async function startRecording() {
            error(''); videoBlob = null; videoDuration = 0;
            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                error('Video recording is not supported by this browser. Please use a modern browser.'); return;
            }
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true });
                videoMimeType = mime();
                videoRecorder = videoMimeType ? new MediaRecorder(videoStream, { mimeType: videoMimeType }) : new MediaRecorder(videoStream);
                videoChunks = []; videoSeconds = 0; updateTimer();
                player.srcObject = videoStream;
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
                videoRecorder.ondataavailable = e => { if (e.data?.size) videoChunks.push(e.data); };
                videoRecorder.onstop = function () {
                    videoBlob = new Blob(videoChunks, { type: videoRecorder.mimeType || videoMimeType || 'video/webm' });
                    videoDuration = Math.max(1, Math.min(30, videoSeconds));
                    if (player.srcObject) player.srcObject = null;
                    player.src = URL.createObjectURL(videoBlob); player.muted = false; player.autoplay = false; player.controls = true; player.classList.remove('d-none');
                    statusEl.textContent = 'Recording ready. Play it back, then Save & Continue to keep it.';
                    again.classList.remove('d-none'); resetUI();
                };
                videoRecorder.start(); start.classList.add('d-none'); stop.classList.remove('d-none'); badge.classList.remove('d-none');
                statusEl.textContent = 'Recording… speak naturally. You have up to 30 seconds.';
                videoTimerId = setInterval(() => { videoSeconds++; updateTimer(); if (videoSeconds >= 30) stopRecording(); }, 1000);
            } catch (e) { stopStream(); error('Camera and microphone access was not granted. Please allow access and try again.'); }
        }
        function stopRecording() { stopClock(); if (videoRecorder && videoRecorder.state !== 'inactive') videoRecorder.stop(); }
        start.addEventListener('click', startRecording); stop.addEventListener('click', stopRecording);
        again.addEventListener('click', function () {
            player.pause(); player.removeAttribute('src'); player.srcObject = null; player.classList.add('d-none');
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
                    player.pause(); player.removeAttribute('src'); player.srcObject = null; player.classList.add('d-none'); placeholder.classList.remove('d-none');
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
