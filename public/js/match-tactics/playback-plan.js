(function (window) {
    'use strict';

    if (window.MatchTacticsPlaybackPlan) {
        return;
    }

    function clamp(value, minValue, maxValue) {
        return Math.max(minValue, Math.min(maxValue, value));
    }

    function normalizePlaybackRate(rate, fallbackRate, defaultSlowRate) {
        var fallback = Number(fallbackRate);
        if (!Number.isFinite(fallback) || fallback <= 0) {
            fallback = Number(defaultSlowRate);
        }
        if (!Number.isFinite(fallback) || fallback <= 0) {
            fallback = 0.5;
        }

        var value = Number(rate);
        if (!Number.isFinite(value) || value <= 0) {
            value = fallback;
        }

        return clamp(value, 0.1, 2);
    }

    function normalizePlaybackMode(mode, normalMode, normalSlowMode, slowOnlyMode) {
        var resolvedNormalMode = String(normalMode || 'normal');
        var resolvedNormalSlowMode = String(normalSlowMode || 'normal_slow');
        var resolvedSlowOnlyMode = String(slowOnlyMode || 'slow_only');
        var value = String(mode || '').trim();

        if (value === resolvedNormalMode) {
            return resolvedNormalMode;
        }
        if (value === resolvedSlowOnlyMode) {
            return resolvedSlowOnlyMode;
        }

        return resolvedNormalSlowMode;
    }

    function formatPlaybackRateLabel(rate, defaultSlowRate) {
        var normalized = normalizePlaybackRate(rate, 1, defaultSlowRate);
        return normalized.toFixed(2).replace(/\.?0+$/, '') + 'x';
    }

    function normalizePlanDuration(durationMs, normalizeDuration) {
        if (typeof normalizeDuration === 'function') {
            return normalizeDuration(durationMs);
        }

        var duration = Number(durationMs);
        if (!Number.isFinite(duration) || duration <= 0) {
            return 1000;
        }

        return Math.max(1, Math.round(duration));
    }

    function buildPlaybackPlan(config) {
        var source = config && typeof config === 'object' ? config : {};
        var normalMode = String(source.normalMode || 'normal');
        var normalSlowMode = String(source.normalSlowMode || 'normal_slow');
        var slowOnlyMode = String(source.slowOnlyMode || 'slow_only');
        var defaultSlowRate = Number(source.defaultSlowRate);
        if (!Number.isFinite(defaultSlowRate) || defaultSlowRate <= 0) {
            defaultSlowRate = 0.5;
        }

        var mode = normalizePlaybackMode(source.mode, normalMode, normalSlowMode, slowOnlyMode);
        var slowRate = normalizePlaybackRate(source.slowRate, defaultSlowRate, defaultSlowRate);
        var durationMs = normalizePlanDuration(source.durationMs, source.normalizeDuration);

        var segments = [];
        if (mode === slowOnlyMode) {
            segments.push({
                id: 'slow-1',
                label: 'Slowmo',
                rate: slowRate,
                fromMs: 0,
                toMs: durationMs
            });
        } else {
            segments.push({
                id: 'normal-1',
                label: 'Normaal',
                rate: 1,
                fromMs: 0,
                toMs: durationMs
            });

            if (mode === normalSlowMode) {
                segments.push({
                    id: 'slow-1',
                    label: 'Slowmo',
                    rate: slowRate,
                    fromMs: 0,
                    toMs: durationMs
                });
            }
        }

        var elapsedCursor = 0;
        segments.forEach(function (segment) {
            var sourceDuration = Math.max(0, segment.toMs - segment.fromMs);
            var playbackDuration = sourceDuration / segment.rate;

            segment.sourceDurationMs = sourceDuration;
            segment.playbackDurationMs = playbackDuration;
            segment.startElapsedMs = elapsedCursor;
            segment.endElapsedMs = elapsedCursor + playbackDuration;

            elapsedCursor = segment.endElapsedMs;
        });

        return {
            mode: mode,
            slowRate: slowRate,
            durationMs: durationMs,
            segments: segments,
            totalElapsedMs: elapsedCursor
        };
    }

    function resolvePlaybackMomentAtElapsed(plan, elapsedMs) {
        if (!plan || !Array.isArray(plan.segments) || plan.segments.length === 0) {
            return {
                elapsedMs: 0,
                segmentIndex: 0,
                segment: null,
                localTimeMs: 0,
                isComplete: true
            };
        }

        var clampedElapsed = Number(elapsedMs);
        if (!Number.isFinite(clampedElapsed)) {
            clampedElapsed = 0;
        }
        clampedElapsed = clamp(clampedElapsed, 0, plan.totalElapsedMs);

        var chosenIndex = plan.segments.length - 1;
        for (var i = 0; i < plan.segments.length; i += 1) {
            if (clampedElapsed <= plan.segments[i].endElapsedMs) {
                chosenIndex = i;
                break;
            }
        }

        var chosenSegment = plan.segments[chosenIndex];
        var elapsedInSegment = clampedElapsed - chosenSegment.startElapsedMs;
        elapsedInSegment = clamp(elapsedInSegment, 0, chosenSegment.playbackDurationMs);

        var localTimeMs = chosenSegment.fromMs + (elapsedInSegment * chosenSegment.rate);
        localTimeMs = clamp(localTimeMs, chosenSegment.fromMs, chosenSegment.toMs);

        return {
            elapsedMs: clampedElapsed,
            segmentIndex: chosenIndex,
            segment: chosenSegment,
            localTimeMs: localTimeMs,
            isComplete: clampedElapsed >= plan.totalElapsedMs
        };
    }

    function resolveElapsedFromLocalTime(plan, localTimeMs, preferredSegmentIndex) {
        if (!plan || !Array.isArray(plan.segments) || plan.segments.length === 0) {
            return 0;
        }

        var index = Number(preferredSegmentIndex);
        if (!Number.isFinite(index)) {
            index = 0;
        }
        index = clamp(Math.round(index), 0, plan.segments.length - 1);

        var segment = plan.segments[index];
        var clampedLocalTime = clamp(Number(localTimeMs) || 0, segment.fromMs, segment.toMs);
        var elapsed = segment.startElapsedMs + ((clampedLocalTime - segment.fromMs) / segment.rate);

        return clamp(elapsed, 0, plan.totalElapsedMs);
    }

    window.MatchTacticsPlaybackPlan = {
        normalizePlaybackRate: normalizePlaybackRate,
        normalizePlaybackMode: normalizePlaybackMode,
        formatPlaybackRateLabel: formatPlaybackRateLabel,
        buildPlaybackPlan: buildPlaybackPlan,
        resolvePlaybackMomentAtElapsed: resolvePlaybackMomentAtElapsed,
        resolveElapsedFromLocalTime: resolveElapsedFromLocalTime
    };
}(window));
