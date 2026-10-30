(() => {
    // Keep enough segments for stable playback, then discard excessive live lag.
    const config = {
        lowLatencyMode: true,
        liveSyncDurationCount: 3,
        liveMaxLatencyDurationCount: 6,
        maxLiveSyncPlaybackRate: 1.1,
        enableWorker: true,
        backBufferLength: 20,
    };

    function watch(video, hls = null) {
        let details = null;
        let lastSeek = -Infinity;
        const levelEvent = window.Hls?.Events.LEVEL_UPDATED;
        const update = (_event, data) => { details = data.details; };
        if (hls && levelEvent) hls.on(levelEvent, update);

        function sync() {
            // Do not override an intentional pause or a seek by the operator.
            if (document.hidden || video.paused || video.seeking ||
                video.readyState < 2 || !video.seekable.length) return;
            if (hls && !details?.live) return;
            if (!hls && Number.isFinite(video.duration)) return;

            const ranges = video.seekable;
            const start = ranges.start(ranges.length - 1);
            const end = ranges.end(ranges.length - 1);
            const segment = Number(details?.targetduration) || 1;
            const edge = hls ? Number(details.edge) : end;
            if (!Number.isFinite(edge)) return;
            const delay = Math.max(3, segment * 3);
            const maxDelay = Math.max(8, segment * 6);
            const target = Math.min(edge - delay, end - 0.25);
            if (target <= start || target <= video.currentTime + 1) return;
            if (edge - video.currentTime <= maxDelay) return;
            const now = performance.now();
            if (now - lastSeek < 8000) return;
            // Seek only inside the latest available range, never into a gap.
            video.currentTime = target;
            lastSeek = now;
        }

        const timer = setInterval(sync, 2000);
        video.addEventListener('playing', sync);
        document.addEventListener('visibilitychange', sync);
        return () => {
            clearInterval(timer);
            video.removeEventListener('playing', sync);
            document.removeEventListener('visibilitychange', sync);
            if (hls && levelEvent) hls.off(levelEvent, update);
        };
    }

    window.MecaCuyCameraLive = { config, watch };
})();
