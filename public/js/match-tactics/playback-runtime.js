(function (window) {
    'use strict';

    if (window.MatchTacticsPlaybackRuntime) {
        return;
    }

    function createRuntime() {
        var currentSession = null;

        function stop(reason) {
            if (!currentSession || typeof currentSession.stop !== 'function') {
                return;
            }

            currentSession.stop(true, reason || 'manual-stop');
        }

        function hasActive() {
            return currentSession !== null;
        }

        function run(plan, options) {
            var config = options && typeof options === 'object' ? options : {};
            var onFrame = typeof config.onFrame === 'function' ? config.onFrame : function () {};
            var onStop = typeof config.onStop === 'function' ? config.onStop : function () {};

            if (!plan || !Array.isArray(plan.segments) || plan.segments.length === 0) {
                onStop({
                    cancelled: true,
                    reason: 'empty-plan',
                    moment: null,
                    plan: plan
                });
                return null;
            }

            var planApi = window.MatchTacticsPlaybackPlan;
            if (!planApi || typeof planApi.resolvePlaybackMomentAtElapsed !== 'function') {
                onStop({
                    cancelled: true,
                    reason: 'missing-playback-plan-api',
                    moment: null,
                    plan: plan
                });
                return null;
            }

            stop('replace');

            var startElapsed = Number(config.startElapsedMs);
            if (!Number.isFinite(startElapsed)) {
                startElapsed = 0;
            }
            startElapsed = Math.max(0, Math.min(plan.totalElapsedMs, startElapsed));

            var session = {
                plan: plan,
                startElapsedMs: startElapsed,
                rafId: null,
                startStamp: null,
                finished: false,
                lastMoment: planApi.resolvePlaybackMomentAtElapsed(plan, startElapsed)
            };

            function finish(cancelled, reason) {
                if (session.finished) {
                    return;
                }
                session.finished = true;

                if (session.rafId !== null) {
                    window.cancelAnimationFrame(session.rafId);
                    session.rafId = null;
                }

                if (currentSession === session) {
                    currentSession = null;
                }

                onStop({
                    cancelled: !!cancelled,
                    reason: reason || '',
                    moment: session.lastMoment,
                    plan: plan
                });
            }

            session.stop = finish;
            currentSession = session;

            onFrame(session.lastMoment);

            if (plan.totalElapsedMs <= 0) {
                finish(false, 'complete');
                return session;
            }

            function tick(timestamp) {
                if (session.finished) {
                    return;
                }

                if (session.startStamp === null) {
                    session.startStamp = timestamp;
                }

                var elapsed = session.startElapsedMs + (timestamp - session.startStamp);
                if (elapsed >= plan.totalElapsedMs) {
                    elapsed = plan.totalElapsedMs;
                }

                session.lastMoment = planApi.resolvePlaybackMomentAtElapsed(plan, elapsed);
                onFrame(session.lastMoment);

                if (elapsed >= plan.totalElapsedMs) {
                    finish(false, 'complete');
                    return;
                }

                session.rafId = window.requestAnimationFrame(tick);
            }

            session.rafId = window.requestAnimationFrame(tick);
            return session;
        }

        return {
            run: run,
            stop: stop,
            hasActive: hasActive
        };
    }

    window.MatchTacticsPlaybackRuntime = {
        createRuntime: createRuntime
    };
}(window));
