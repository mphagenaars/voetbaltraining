(function (window) {
    'use strict';

    if (window.MatchTacticsAnimationAuthoring) {
        return;
    }

    function create(config) {
        var options = config && typeof config === 'object' ? config : {};
        var board = options.board;
        if (!board || typeof board.getMainLayer !== 'function' || typeof board.getStage !== 'function') {
            console.error('MatchTacticsAnimationAuthoring requires a valid board instance.');
            return null;
        }

        var tacticsMainEl = options.tacticsMainEl || null;
        var editorShellEl = options.editorShellEl || null;

        var setStatus = typeof options.setStatus === 'function'
            ? options.setStatus
            : function () {};
        var notifyContentChanged = typeof options.onContentChanged === 'function'
            ? options.onContentChanged
            : function () {};

        var resolveTitle = typeof options.getTitle === 'function'
            ? options.getTitle
            : function () { return ''; };
        var resolveCsrfToken = typeof options.getCsrfToken === 'function'
            ? options.getCsrfToken
            : function () { return ''; };
        var resolveMatchId = typeof options.getMatchId === 'function'
            ? options.getMatchId
            : function () { return 0; };
        var resolveExportContext = typeof options.getExportContext === 'function'
            ? options.getExportContext
            : function () { return {}; };

        var exportEndpoint = typeof options.exportEndpoint === 'string'
            ? String(options.exportEndpoint).trim()
            : '/matches/tactics/export-video';
        if (exportEndpoint === '') {
            exportEndpoint = '/matches/tactics/export-video';
        }

        var ANIMATION_TIME_STEP_MS = 1000;
        var ANIMATION_KEYFRAME_EPSILON_MS = 200;
        var DEFAULT_ANIMATION_DURATION_MS = 1000;
        var MAX_ANIMATION_DURATION_MS = 60000;
        var DEFAULT_SLOW_MOTION_RATE = 0.5;
        var PLAYBACK_MODE_NORMAL = 'normal';
        var PLAYBACK_MODE_NORMAL_AND_SLOW = 'normal_slow';
        var PLAYBACK_MODE_SLOW_ONLY = 'slow_only';
        var ANIMATION_TRACK_PROPERTY_POSITION = 'position';
        var ANIMATION_TRACK_PROPERTY_HIGHLIGHT = 'highlight';
        var EXPORT_RECORDING_FPS = 30;
        var EXPORT_VIDEO_BITS_PER_SECOND = 2500000;
        var EXPORT_SERVER_UPLOAD_HARD_LIMIT_BYTES = 100 * 1024 * 1024;
        var EXPORT_CONSERVATIVE_UPLOAD_LIMIT_BYTES = 7 * 1024 * 1024;
        var EXPORT_MIME_CANDIDATES = [
            'video/mp4;codecs=hvc1',
            'video/mp4;codecs=avc1',
            'video/mp4',
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm'
        ];
        var animationTrackSequence = 0;
        var defaultAnimationDurationLabel = (DEFAULT_ANIMATION_DURATION_MS / 1000).toFixed(1) + 's';

        var animationPanelApi = window.MatchTacticsAnimationPanel;
        if (!animationPanelApi || typeof animationPanelApi.createPanel !== 'function') {
            console.error('MatchTactics animation panel module is required.');
            return null;
        }

        var animationPanel = animationPanelApi.createPanel({
            tacticsMainEl: tacticsMainEl,
            editorShellEl: editorShellEl,
            defaultDurationLabel: defaultAnimationDurationLabel,
            defaultDurationMs: DEFAULT_ANIMATION_DURATION_MS,
            timeStepMs: ANIMATION_TIME_STEP_MS,
            playbackModeNormal: PLAYBACK_MODE_NORMAL,
            playbackModeNormalAndSlow: PLAYBACK_MODE_NORMAL_AND_SLOW,
            playbackModeSlowOnly: PLAYBACK_MODE_SLOW_ONLY
        });
        var animationToggleBtn = animationPanel.refs.toggleBtn;
        var animationPlayBtn = animationPanel.refs.playBtn;
        var animationRestartBtn = animationPanel.refs.restartBtn;
        var animationPrevFrameBtn = animationPanel.refs.prevFrameBtn;
        var animationNextFrameBtn = animationPanel.refs.nextFrameBtn;
        var animationHighlightBtn = animationPanel.refs.highlightBtn;
        var animationDeleteKeyframeBtn = animationPanel.refs.deleteKeyframeBtn;
        var animationModeEl = animationPanel.refs.modeEl;
        var animationSlowRateEl = animationPanel.refs.slowRateEl;
        var animationExportBtn = animationPanel.refs.exportBtn;
        var animationRangeEl = animationPanel.refs.rangeEl;
        var animationTimeEl = animationPanel.refs.timeEl;
        var setAnimationButtonIcon = animationPanel.setButtonIcon;

        function createEmptyAnimationTimeline() {
            return {
                enabled: false,
                durationMs: DEFAULT_ANIMATION_DURATION_MS,
                fps: 60,
                tracks: []
            };
        }

        var animationTimeline = createEmptyAnimationTimeline();
        var animationCurrentTimeMs = 0;
        var animationIsPlaying = false;
        var animationIsExporting = false;
        var animationPlaybackMode = PLAYBACK_MODE_NORMAL_AND_SLOW;
        var animationSlowMotionRate = DEFAULT_SLOW_MOTION_RATE;
        var animationPlaybackElapsedMs = 0;
        var animationPlaybackSegmentIndex = 0;
        var animationPlaybackRate = 1;
        var activePlaybackPlan = null;
        var animationApplyingFrame = false;
        var animationHighlightOverlaysByItemId = {};
        var playbackPlanApi = window.MatchTacticsPlaybackPlan;
        var playbackRuntimeApi = window.MatchTacticsPlaybackRuntime;
        var videoExportApi = window.MatchTacticsVideoExport;
        if (
            !playbackPlanApi ||
            !playbackRuntimeApi ||
            !videoExportApi ||
            typeof playbackRuntimeApi.createRuntime !== 'function' ||
            typeof videoExportApi.createExporter !== 'function'
        ) {
            console.error('MatchTactics playback/export modules are required.');
            return null;
        }
        var playbackRuntime = playbackRuntimeApi.createRuntime();

        function nextAnimationTrackId() {
            animationTrackSequence += 1;
            return 'track-' + animationTrackSequence + '-' + Math.floor(Math.random() * 100000);
        }

        function clampAnimationTime(timeMs) {
            var value = Number(timeMs);
            if (!Number.isFinite(value)) {
                return 0;
            }
            return Math.max(0, Math.min(animationTimeline.durationMs, Math.round(value)));
        }

        function roundAnimationTime(timeMs) {
            var value = clampAnimationTime(timeMs);
            return Math.round(value / ANIMATION_TIME_STEP_MS) * ANIMATION_TIME_STEP_MS;
        }

        function normalizeAnimationDuration(durationMs) {
            var duration = Number(durationMs);
            if (!Number.isFinite(duration) || duration <= 0) {
                duration = DEFAULT_ANIMATION_DURATION_MS;
            }

            duration = Math.round(duration / ANIMATION_TIME_STEP_MS) * ANIMATION_TIME_STEP_MS;
            return Math.max(ANIMATION_TIME_STEP_MS, Math.min(MAX_ANIMATION_DURATION_MS, duration));
        }

        function normalizeAnimationPositionValue(value) {
            var source = value && typeof value === 'object' ? value : {};
            var x = Number(source.x);
            var y = Number(source.y);
            if (!Number.isFinite(x) || !Number.isFinite(y)) {
                return null;
            }
            return { x: x, y: y };
        }

        function normalizeAnimationHighlightValue(value) {
            if (typeof value === 'boolean') {
                return { active: value };
            }

            if (value && typeof value === 'object') {
                if (typeof value.active === 'boolean') {
                    return { active: value.active };
                }
                if (typeof value.enabled === 'boolean') {
                    return { active: value.enabled };
                }
                if (typeof value.on === 'boolean') {
                    return { active: value.on };
                }
                if (typeof value.highlight === 'boolean') {
                    return { active: value.highlight };
                }
            }

            return null;
        }

        function normalizeAnimationTrackProperty(property) {
            if (String(property || '').trim() === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return ANIMATION_TRACK_PROPERTY_HIGHLIGHT;
            }
            return ANIMATION_TRACK_PROPERTY_POSITION;
        }

        function normalizeAnimationTrackValue(property, value) {
            if (property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return normalizeAnimationHighlightValue(value);
            }
            return normalizeAnimationPositionValue(value);
        }

        function sanitizeAnimationTimeline(raw) {
            var source = raw && typeof raw === 'object' ? raw : {};
            var duration = normalizeAnimationDuration(source.durationMs);

            var tracks = Array.isArray(source.tracks) ? source.tracks : [];
            var normalizedTracks = tracks
                .filter(function (track) {
                    return track && typeof track === 'object';
                })
                .map(function (track) {
                    var itemId = String(track.itemId || '').trim();
                    var property = normalizeAnimationTrackProperty(track.property);
                    var keyframes = Array.isArray(track.keyframes) ? track.keyframes : [];
                    var normalizedKeyframes = keyframes
                        .filter(function (frame) {
                            return frame && typeof frame === 'object';
                        })
                        .map(function (frame) {
                            var value = normalizeAnimationTrackValue(property, frame.value);
                            if (!value) {
                                return null;
                            }
                            var t = Number(frame.t);
                            if (!Number.isFinite(t)) {
                                return null;
                            }
                            return {
                                t: Math.max(0, Math.min(duration, Math.round(t))),
                                value: value
                            };
                        })
                        .filter(function (frame) {
                            return frame !== null;
                        });

                    normalizedKeyframes.sort(function (a, b) {
                        return a.t - b.t;
                    });

                    return {
                        id: String(track.id || '').trim() || nextAnimationTrackId(),
                        itemId: itemId,
                        property: property,
                        keyframes: normalizedKeyframes
                    };
                })
                .filter(function (track) {
                    return (
                        track.itemId !== '' &&
                        (
                            track.property === ANIMATION_TRACK_PROPERTY_POSITION ||
                            track.property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT
                        ) &&
                        track.keyframes.length > 0
                    );
                });

            normalizedTracks.forEach(function (track) {
                normalizeHighlightTrackKeyframes(track);
            });
            normalizedTracks = normalizedTracks.filter(function (track) {
                return track.keyframes.length > 0;
            });

            return {
                enabled: source.enabled === true,
                durationMs: duration,
                fps: 60,
                tracks: normalizedTracks
            };
        }

        function buildItemNodeMap() {
            var nodeMap = {};
            board.getMainLayer().find('.item').forEach(function (node) {
                var itemId = String(node.getAttr('itemId') || '').trim();
                if (itemId !== '') {
                    nodeMap[itemId] = node;
                }
            });
            return nodeMap;
        }

        function removeStaleAnimationTracks() {
            var nodeMap = buildItemNodeMap();
            animationTimeline.tracks = animationTimeline.tracks.filter(function (track) {
                return !!nodeMap[track.itemId];
            });
        }

        function hasAnyAnimationKeyframes() {
            return animationTimeline.tracks.some(function (track) {
                return Array.isArray(track.keyframes) && track.keyframes.length > 0;
            });
        }

        function findAnimationMaxKeyframeTime() {
            var maxT = 0;
            animationTimeline.tracks.forEach(function (track) {
                if (!Array.isArray(track.keyframes)) {
                    return;
                }
                track.keyframes.forEach(function (frame) {
                    var t = Number(frame.t);
                    if (Number.isFinite(t) && t > maxT) {
                        maxT = t;
                    }
                });
            });
            return maxT;
        }

        function syncAnimationDurationToKeyframes() {
            if (!hasAnyAnimationKeyframes()) {
                animationTimeline.durationMs = DEFAULT_ANIMATION_DURATION_MS;
                animationCurrentTimeMs = clampAnimationTime(animationCurrentTimeMs);
                return;
            }

            var maxKeyframeTime = findAnimationMaxKeyframeTime();
            var targetDuration = Math.max(ANIMATION_TIME_STEP_MS, maxKeyframeTime + ANIMATION_TIME_STEP_MS);
            animationTimeline.durationMs = normalizeAnimationDuration(targetDuration);
            animationCurrentTimeMs = clampAnimationTime(animationCurrentTimeMs);
        }

        function ensureTimelineTrack(itemId, property) {
            var normalizedItemId = String(itemId || '').trim();
            if (normalizedItemId === '') {
                return null;
            }

            var normalizedProperty = normalizeAnimationTrackProperty(property);
            var existing = null;
            for (var i = 0; i < animationTimeline.tracks.length; i += 1) {
                if (
                    animationTimeline.tracks[i].itemId === normalizedItemId &&
                    animationTimeline.tracks[i].property === normalizedProperty
                ) {
                    existing = animationTimeline.tracks[i];
                    break;
                }
            }

            if (existing) {
                return existing;
            }

            var created = {
                id: nextAnimationTrackId(),
                itemId: normalizedItemId,
                property: normalizedProperty,
                keyframes: []
            };
            animationTimeline.tracks.push(created);
            return created;
        }

        function upsertTrackKeyframe(track, timeMs, value) {
            if (!track) {
                return;
            }

            var property = normalizeAnimationTrackProperty(track.property);
            var normalizedValue = normalizeAnimationTrackValue(property, value);
            if (!normalizedValue) {
                return;
            }

            var t = roundAnimationTime(timeMs);
            var updated = false;
            for (var i = 0; i < track.keyframes.length; i += 1) {
                if (Math.abs(track.keyframes[i].t - t) <= ANIMATION_KEYFRAME_EPSILON_MS) {
                    track.keyframes[i].t = t;
                    track.keyframes[i].value = normalizedValue;
                    updated = true;
                    break;
                }
            }

            if (!updated) {
                track.keyframes.push({
                    t: t,
                    value: normalizedValue
                });
            }

            track.keyframes.sort(function (a, b) {
                return a.t - b.t;
            });
        }

        function normalizeHighlightTrackKeyframes(track) {
            if (!track || normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return;
            }

            var normalizedFrames = [];
            var previousState = null;

            track.keyframes.forEach(function (frame) {
                var active = !!(frame.value && frame.value.active === true);
                if (previousState === active) {
                    return;
                }

                normalizedFrames.push({
                    t: frame.t,
                    value: { active: active }
                });
                previousState = active;
            });

            while (normalizedFrames.length > 0 && normalizedFrames[0].value.active !== true) {
                normalizedFrames.shift();
            }

            track.keyframes = normalizedFrames;
        }

        function getTrackValueAtTime(track, timeMs) {
            if (!track || !Array.isArray(track.keyframes) || track.keyframes.length === 0) {
                return null;
            }

            var property = normalizeAnimationTrackProperty(track.property);
            var t = clampAnimationTime(timeMs);

            if (property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                var active = false;
                for (var i = 0; i < track.keyframes.length; i += 1) {
                    var keyframe = track.keyframes[i];
                    if (t < keyframe.t) {
                        break;
                    }
                    active = !!(keyframe.value && keyframe.value.active === true);
                }
                return { active: active };
            }

            if (track.keyframes.length === 1) {
                return track.keyframes[0].value;
            }

            if (t <= track.keyframes[0].t) {
                return track.keyframes[0].value;
            }

            var last = track.keyframes[track.keyframes.length - 1];
            if (t >= last.t) {
                return last.value;
            }

            for (var i = 0; i < track.keyframes.length - 1; i += 1) {
                var from = track.keyframes[i];
                var to = track.keyframes[i + 1];
                if (t < from.t || t > to.t) {
                    continue;
                }

                if (to.t === from.t) {
                    return to.value;
                }

                var ratio = (t - from.t) / (to.t - from.t);
                return {
                    x: from.value.x + ((to.value.x - from.value.x) * ratio),
                    y: from.value.y + ((to.value.y - from.value.y) * ratio)
                };
            }

            return null;
        }

        function formatAnimationTime(timeMs) {
            return (Math.max(0, Number(timeMs) || 0) / 1000).toFixed(1) + 's';
        }

        function countAnimationKeyframes() {
            return animationTimeline.tracks.reduce(function (total, track) {
                return total + track.keyframes.length;
            }, 0);
        }

        function getSelectedAnimationItemNode() {
            var uiLayer = board.getUiLayer();
            if (!uiLayer || typeof uiLayer.find !== 'function') {
                return null;
            }

            var transformers = uiLayer.find('Transformer');
            if (!transformers || transformers.length === 0) {
                return null;
            }

            var selectedNodes = typeof transformers[0].nodes === 'function'
                ? transformers[0].nodes()
                : [];
            if (!Array.isArray(selectedNodes) || selectedNodes.length !== 1) {
                return null;
            }

            var selectedNode = selectedNodes[0];
            if (!selectedNode || typeof selectedNode.hasName !== 'function' || !selectedNode.hasName('item')) {
                return null;
            }

            return selectedNode;
        }

        function getSelectedAnimationItemId() {
            var selectedNode = getSelectedAnimationItemNode();
            if (!selectedNode) {
                return '';
            }
            return String(selectedNode.getAttr('itemId') || '').trim();
        }

        function isItemHighlightedAtTime(itemId, timeMs) {
            var normalizedItemId = String(itemId || '').trim();
            if (normalizedItemId === '') {
                return false;
            }

            for (var i = 0; i < animationTimeline.tracks.length; i += 1) {
                var track = animationTimeline.tracks[i];
                if (
                    track.itemId !== normalizedItemId ||
                    normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT
                ) {
                    continue;
                }

                var value = getTrackValueAtTime(track, timeMs);
                return !!(value && value.active === true);
            }

            return false;
        }

        function clearHighlightOverlays() {
            Object.keys(animationHighlightOverlaysByItemId).forEach(function (itemId) {
                var overlay = animationHighlightOverlaysByItemId[itemId];
                if (overlay && typeof overlay.destroy === 'function') {
                    overlay.destroy();
                }
            });
            animationHighlightOverlaysByItemId = {};
        }

        function upsertHighlightOverlay(itemId, node) {
            if (!node) {
                return;
            }

            var box = node.getClientRect({ relativeTo: board.getMainLayer() });
            if (
                !Number.isFinite(box.x) ||
                !Number.isFinite(box.y) ||
                !Number.isFinite(box.width) ||
                !Number.isFinite(box.height)
            ) {
                return;
            }

            var centerX = box.x + (box.width / 2);
            var centerY = box.y + (box.height / 2);
            var radius = Math.max(15, (Math.max(box.width, box.height) / 2) + 8);

            var overlay = animationHighlightOverlaysByItemId[itemId];
            if (!overlay) {
                overlay = new Konva.Circle({
                    x: centerX,
                    y: centerY,
                    radius: radius,
                    stroke: '#ffd54f',
                    strokeWidth: 3,
                    fill: 'rgba(255, 213, 79, 0.18)',
                    shadowColor: 'rgba(255, 193, 7, 0.42)',
                    shadowBlur: 16,
                    shadowOpacity: 1,
                    listening: false,
                    name: 'animation-highlight-overlay'
                });
                board.getUiLayer().add(overlay);
                animationHighlightOverlaysByItemId[itemId] = overlay;
            } else {
                overlay.setAttrs({
                    x: centerX,
                    y: centerY,
                    radius: radius
                });
                if (typeof overlay.show === 'function') {
                    overlay.show();
                }
            }

            if (typeof overlay.moveToBottom === 'function') {
                overlay.moveToBottom();
            }
        }

        function refreshHighlightOverlaysAtTime(timeMs) {
            if (!animationTimeline.enabled) {
                clearHighlightOverlays();
                board.getUiLayer().batchDraw();
                return;
            }

            var nodeMap = buildItemNodeMap();
            var activeItemMap = {};
            animationTimeline.tracks.forEach(function (track) {
                if (normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                    return;
                }

                var value = getTrackValueAtTime(track, timeMs);
                if (!(value && value.active === true)) {
                    return;
                }

                if (!nodeMap[track.itemId]) {
                    return;
                }

                activeItemMap[track.itemId] = true;
            });

            Object.keys(animationHighlightOverlaysByItemId).forEach(function (itemId) {
                if (activeItemMap[itemId]) {
                    return;
                }

                var overlay = animationHighlightOverlaysByItemId[itemId];
                if (overlay && typeof overlay.destroy === 'function') {
                    overlay.destroy();
                }
                delete animationHighlightOverlaysByItemId[itemId];
            });

            Object.keys(activeItemMap).forEach(function (itemId) {
                upsertHighlightOverlay(itemId, nodeMap[itemId]);
            });

            board.getUiLayer().batchDraw();
        }

        function normalizePlaybackRate(rate, fallbackRate) {
            return playbackPlanApi.normalizePlaybackRate(rate, fallbackRate, DEFAULT_SLOW_MOTION_RATE);
        }

        function normalizePlaybackMode(mode) {
            return playbackPlanApi.normalizePlaybackMode(
                mode,
                PLAYBACK_MODE_NORMAL,
                PLAYBACK_MODE_NORMAL_AND_SLOW,
                PLAYBACK_MODE_SLOW_ONLY
            );
        }

        function formatPlaybackRateLabel(rate) {
            return playbackPlanApi.formatPlaybackRateLabel(rate, DEFAULT_SLOW_MOTION_RATE);
        }

        function getPlaybackModeLabel(mode) {
            var normalizedMode = normalizePlaybackMode(mode);
            if (normalizedMode === PLAYBACK_MODE_NORMAL) {
                return 'alleen normaal';
            }
            if (normalizedMode === PLAYBACK_MODE_SLOW_ONLY) {
                return 'alleen slowmo';
            }
            return 'normaal + slowmo';
        }

        function buildPlaybackPlan(mode, slowRate) {
            return playbackPlanApi.buildPlaybackPlan({
                durationMs: animationTimeline.durationMs,
                mode: mode,
                slowRate: slowRate,
                normalMode: PLAYBACK_MODE_NORMAL,
                normalSlowMode: PLAYBACK_MODE_NORMAL_AND_SLOW,
                slowOnlyMode: PLAYBACK_MODE_SLOW_ONLY,
                defaultSlowRate: DEFAULT_SLOW_MOTION_RATE,
                normalizeDuration: normalizeAnimationDuration
            });
        }

        function resolvePlaybackMomentAtElapsed(plan, elapsedMs) {
            return playbackPlanApi.resolvePlaybackMomentAtElapsed(plan, elapsedMs);
        }

        function resolveElapsedFromLocalTime(plan, localTimeMs, preferredSegmentIndex) {
            return playbackPlanApi.resolveElapsedFromLocalTime(plan, localTimeMs, preferredSegmentIndex);
        }

        function stopPlaybackSession(reason) {
            playbackRuntime.stop(reason || 'manual-stop');
        }

        function runPlaybackPlan(plan, options) {
            return playbackRuntime.run(plan, options);
        }

        function updatePlaybackCursorForPlanMoment(moment) {
            if (!moment) {
                return;
            }

            animationPlaybackElapsedMs = moment.elapsedMs;
            animationPlaybackSegmentIndex = moment.segmentIndex;
            animationPlaybackRate = moment.segment && Number.isFinite(Number(moment.segment.rate))
                ? Number(moment.segment.rate)
                : 1;
        }

        function syncPlaybackCursorToCurrentTime(preferredSegmentIndex) {
            var plan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            activePlaybackPlan = plan;
            animationPlaybackElapsedMs = resolveElapsedFromLocalTime(plan, animationCurrentTimeMs, preferredSegmentIndex);
            var moment = resolvePlaybackMomentAtElapsed(plan, animationPlaybackElapsedMs);
            updatePlaybackCursorForPlanMoment(moment);
        }

        function loadDrawingState(drawingJson) {
            var raw = drawingJson || '';
            board.loadDrawingData(raw);
            stopAnimationPlayback();
            animationTimeline = extractAnimationTimelineFromDrawing(raw);
            animationCurrentTimeMs = 0;
            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
        }

        function getActivePreviewPlan() {
            activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            return activePlaybackPlan;
        }

        function updateAnimationControls() {
            if (!animationToggleBtn || !animationPlayBtn || !animationRestartBtn || !animationPrevFrameBtn || !animationNextFrameBtn || !animationDeleteKeyframeBtn || !animationRangeEl || !animationTimeEl) {
                return;
            }

            animationToggleBtn.classList.toggle('is-enabled', animationTimeline.enabled);
            animationToggleBtn.setAttribute('aria-label', animationTimeline.enabled ? 'Animatie uitzetten' : 'Animatie aanzetten');
            animationToggleBtn.setAttribute('title', animationTimeline.enabled ? 'Animatie uitzetten' : 'Animatie aanzetten');

            var controlsDisabled = !animationTimeline.enabled || animationIsExporting;
            animationPlayBtn.disabled = controlsDisabled;
            animationRestartBtn.disabled = controlsDisabled;
            animationPrevFrameBtn.disabled = controlsDisabled;
            animationNextFrameBtn.disabled = controlsDisabled;
            animationDeleteKeyframeBtn.disabled = controlsDisabled;
            animationRangeEl.disabled = controlsDisabled;

            var selectedHighlightItemId = getSelectedAnimationItemId();
            var hasSingleSelectedItem = selectedHighlightItemId !== '';
            var selectedItemHighlighted = hasSingleSelectedItem && isItemHighlightedAtTime(selectedHighlightItemId, animationCurrentTimeMs);

            if (animationHighlightBtn) {
                animationHighlightBtn.disabled = controlsDisabled || !hasSingleSelectedItem;
                animationHighlightBtn.classList.toggle('is-active', selectedItemHighlighted);
                animationHighlightBtn.setAttribute(
                    'aria-label',
                    selectedItemHighlighted ? 'Highlight uitzetten' : 'Highlight aanzetten'
                );
                animationHighlightBtn.setAttribute(
                    'title',
                    hasSingleSelectedItem
                        ? (selectedItemHighlighted ? 'Highlight uitzetten' : 'Highlight aanzetten')
                        : 'Selecteer 1 speler om te highlighten'
                );
            }

            if (animationModeEl) {
                animationModeEl.disabled = animationIsPlaying || animationIsExporting;
                animationModeEl.value = animationPlaybackMode;
            }

            if (animationSlowRateEl) {
                animationSlowRateEl.disabled = animationIsPlaying || animationIsExporting;
                animationSlowRateEl.value = String(animationSlowMotionRate);
            }

            if (animationExportBtn) {
                animationExportBtn.disabled = animationIsExporting || !animationTimeline.enabled || countAnimationKeyframes() === 0;
                animationExportBtn.setAttribute(
                    'aria-label',
                    animationIsExporting ? 'Exporteren bezig' : 'Exporteer video (' + getPlaybackModeLabel(animationPlaybackMode) + ')'
                );
                animationExportBtn.setAttribute(
                    'title',
                    animationIsExporting ? 'Exporteren bezig' : 'Exporteer video (' + getPlaybackModeLabel(animationPlaybackMode) + ')'
                );
            }

            setAnimationButtonIcon(animationPlayBtn, animationIsPlaying ? 'pause' : 'play');
            animationPlayBtn.setAttribute('aria-label', animationIsPlaying ? 'Pauzeren' : 'Afspelen');
            animationPlayBtn.setAttribute('title', animationIsPlaying ? 'Pauzeren' : 'Afspelen');
            animationRangeEl.max = String(animationTimeline.durationMs);
            animationRangeEl.value = String(clampAnimationTime(animationCurrentTimeMs));

            var playbackPlan = activePlaybackPlan && Array.isArray(activePlaybackPlan.segments) && activePlaybackPlan.segments.length > 0
                ? activePlaybackPlan
                : getActivePreviewPlan();
            var segmentLabel = '';

            if (playbackPlan && playbackPlan.segments.length > 0) {
                var maxSegmentIndex = playbackPlan.segments.length - 1;
                var displaySegmentIndex = Math.max(0, Math.min(maxSegmentIndex, animationPlaybackSegmentIndex));
                var displaySegment = playbackPlan.segments[displaySegmentIndex];
                if (displaySegment) {
                    segmentLabel = ' · run ' + (displaySegmentIndex + 1) + '/' + playbackPlan.segments.length + ' @' + formatPlaybackRateLabel(displaySegment.rate);
                }
            }

            animationTimeEl.textContent = formatAnimationTime(animationCurrentTimeMs) + ' / ' + formatAnimationTime(animationTimeline.durationMs) + segmentLabel + ' · ' + countAnimationKeyframes() + ' keyframes';
        }

        function stopAnimationPlayback() {
            stopPlaybackSession('manual-stop');
            if (!playbackRuntime.hasActive()) {
                animationIsPlaying = false;
                updateAnimationControls();
            }
        }

        function applyAnimationFrameAt(timeMs) {
            var clampedTime = clampAnimationTime(timeMs);
            animationCurrentTimeMs = clampedTime;

            if (!animationTimeline.enabled) {
                refreshHighlightOverlaysAtTime(clampedTime);
                updateAnimationControls();
                return;
            }

            var nodeMap = buildItemNodeMap();
            animationApplyingFrame = true;
            animationTimeline.tracks.forEach(function (track) {
                if (normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_POSITION) {
                    return;
                }

                var node = nodeMap[track.itemId];
                if (!node) {
                    return;
                }

                var value = getTrackValueAtTime(track, clampedTime);
                if (!value) {
                    return;
                }

                node.x(value.x);
                node.y(value.y);
            });
            animationApplyingFrame = false;

            board.getMainLayer().batchDraw();
            refreshHighlightOverlaysAtTime(clampedTime);
            updateAnimationControls();
        }

        function startAnimationPlayback() {
            if (!animationTimeline.enabled || animationIsExporting) {
                return;
            }

            var previewPlan = getActivePreviewPlan();
            if (!previewPlan || previewPlan.segments.length === 0) {
                return;
            }

            if (animationPlaybackElapsedMs >= previewPlan.totalElapsedMs - 1) {
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = previewPlan.segments[0].rate;
                applyAnimationFrameAt(0);
            }

            if (animationIsPlaying) {
                return;
            }

            animationIsPlaying = true;
            updateAnimationControls();

            runPlaybackPlan(previewPlan, {
                startElapsedMs: animationPlaybackElapsedMs,
                onFrame: function (moment) {
                    updatePlaybackCursorForPlanMoment(moment);
                    applyAnimationFrameAt(moment.localTimeMs);
                },
                onStop: function (result) {
                    animationIsPlaying = false;
                    if (result && result.moment) {
                        updatePlaybackCursorForPlanMoment(result.moment);
                    }

                    if (result && result.cancelled !== true) {
                        animationPlaybackElapsedMs = previewPlan.totalElapsedMs;
                        animationPlaybackSegmentIndex = previewPlan.segments.length - 1;
                        animationPlaybackRate = previewPlan.segments[previewPlan.segments.length - 1].rate;
                        applyAnimationFrameAt(previewPlan.durationMs);
                    } else {
                        updateAnimationControls();
                    }
                }
            });
        }

        function stepAnimationFrame(deltaMs) {
            if (!animationTimeline.enabled) {
                return;
            }

            stopAnimationPlayback();

            var nextTime = animationCurrentTimeMs + deltaMs;
            if (deltaMs > 0 && nextTime > animationTimeline.durationMs && animationTimeline.durationMs < MAX_ANIMATION_DURATION_MS) {
                animationTimeline.durationMs = normalizeAnimationDuration(animationTimeline.durationMs + ANIMATION_TIME_STEP_MS);
            }

            applyAnimationFrameAt(nextTime);
            if (deltaMs > 0) {
                captureCurrentMomentForAllItems();
                return;
            }
            syncPlaybackCursorToCurrentTime(0);
        }

        function finalizeExportStateToSafeIdle() {
            animationIsExporting = false;
            animationIsPlaying = false;
            animationPlaybackElapsedMs = 0;
            animationPlaybackSegmentIndex = 0;
            animationPlaybackRate = 1;
            activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            applyAnimationFrameAt(0);
            updateAnimationControls();
        }

        var videoExporter = videoExportApi.createExporter({
            recordingFps: EXPORT_RECORDING_FPS,
            videoBitsPerSecond: EXPORT_VIDEO_BITS_PER_SECOND,
            serverUploadHardLimitBytes: EXPORT_SERVER_UPLOAD_HARD_LIMIT_BYTES,
            conservativeUploadLimitBytes: EXPORT_CONSERVATIVE_UPLOAD_LIMIT_BYTES,
            mimeCandidates: EXPORT_MIME_CANDIDATES,
            transcodeEndpoint: exportEndpoint,
            setStatus: setStatus,
            getTitle: function () {
                return resolveTitle();
            },
            getCsrfToken: function () {
                return resolveCsrfToken();
            },
            getMatchId: function () {
                return resolveMatchId();
            },
            getExportContext: function () {
                return resolveExportContext();
            },
            getState: function () {
                return {
                    isExporting: animationIsExporting,
                    isAnimationEnabled: animationTimeline.enabled,
                    keyframeCount: countAnimationKeyframes(),
                    slowRate: animationSlowMotionRate,
                    playbackMode: animationPlaybackMode
                };
            },
            getExportMode: function () {
                return animationPlaybackMode;
            },
            getStage: function () {
                return board.getStage();
            },
            buildPlaybackPlan: function (mode, slowRate) {
                return buildPlaybackPlan(mode, slowRate);
            },
            stopAnimationPlayback: stopAnimationPlayback,
            beforeExportStart: function (context) {
                var exportPlan = context && context.exportPlan ? context.exportPlan : null;
                if (!exportPlan || !Array.isArray(exportPlan.segments) || exportPlan.segments.length === 0) {
                    return;
                }

                animationIsExporting = true;
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = exportPlan.segments[0].rate;
                activePlaybackPlan = exportPlan;
                board.clearSelection();
                updateAnimationControls();
            },
            runPlaybackPlan: runPlaybackPlan,
            onPlaybackMoment: function (moment) {
                updatePlaybackCursorForPlanMoment(moment);
                applyAnimationFrameAt(moment.localTimeMs);
            },
            onFinalizeExport: finalizeExportStateToSafeIdle
        });

        function exportAnimationVideo() {
            videoExporter.exportVideo();
        }

        function handleBoardContentChange(reason) {
            if (
                !animationTimeline ||
                !animationTimeline.enabled ||
                animationIsExporting ||
                animationIsPlaying ||
                animationApplyingFrame
            ) {
                notifyContentChanged(reason || 'board');
                return;
            }

            captureCurrentMomentForAllItems();
        }

        function captureCurrentMomentForAllItems() {
            var nodes = board.getMainLayer().find('.item');
            if (nodes.length === 0) {
                removeStaleAnimationTracks();
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
                notifyContentChanged('animation-keyframe');
                return;
            }

            var t = roundAnimationTime(animationCurrentTimeMs);
            nodes.forEach(function (node) {
                var itemId = String(node.getAttr('itemId') || '').trim();
                if (itemId === '') {
                    return;
                }

                var track = ensureTimelineTrack(itemId, ANIMATION_TRACK_PROPERTY_POSITION);
                upsertTrackKeyframe(track, t, { x: node.x(), y: node.y() });
            });

            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            updateAnimationControls();
            notifyContentChanged('animation-keyframe');
        }

        function toggleHighlightForSelectedItemAtCurrentMoment() {
            var selectedItemId = getSelectedAnimationItemId();
            if (selectedItemId === '') {
                updateAnimationControls();
                return;
            }

            var t = roundAnimationTime(animationCurrentTimeMs);
            var nextState = !isItemHighlightedAtTime(selectedItemId, t);
            var track = ensureTimelineTrack(selectedItemId, ANIMATION_TRACK_PROPERTY_HIGHLIGHT);
            upsertTrackKeyframe(track, t, { active: nextState });
            normalizeHighlightTrackKeyframes(track);

            animationTimeline.tracks = animationTimeline.tracks.filter(function (existingTrack) {
                return existingTrack.keyframes.length > 0;
            });

            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
            notifyContentChanged('animation-highlight');
        }

        function removeCurrentMomentFromTimeline() {
            var t = roundAnimationTime(animationCurrentTimeMs);
            animationTimeline.tracks.forEach(function (track) {
                track.keyframes = track.keyframes.filter(function (frame) {
                    return Math.abs(frame.t - t) > ANIMATION_KEYFRAME_EPSILON_MS;
                });
                normalizeHighlightTrackKeyframes(track);
            });

            animationTimeline.tracks = animationTimeline.tracks.filter(function (track) {
                return track.keyframes.length > 0;
            });

            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
            notifyContentChanged('animation-delete-keyframe');
        }

        function setAnimationEnabled(isEnabled) {
            if (animationIsExporting) {
                return;
            }

            animationTimeline.enabled = !!isEnabled;
            stopAnimationPlayback();

            if (animationTimeline.enabled) {
                removeStaleAnimationTracks();
                if (animationTimeline.tracks.length === 0) {
                    animationCurrentTimeMs = 0;
                    captureCurrentMomentForAllItems();
                }
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);
                applyAnimationFrameAt(animationCurrentTimeMs);
            } else {
                animationCurrentTimeMs = 0;
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = 1;
                activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
                refreshHighlightOverlaysAtTime(0);
            }

            updateAnimationControls();
            notifyContentChanged('animation-toggle');
        }

        function parseLayerDrawing(raw) {
            if (typeof raw !== 'string' || raw.trim() === '') {
                return null;
            }
            try {
                var parsed = JSON.parse(raw);
                if (parsed && parsed.className === 'Layer') {
                    return parsed;
                }
            } catch (error) {
                return null;
            }
            return null;
        }

        function extractAnimationTimelineFromDrawing(raw) {
            var layer = parseLayerDrawing(raw);
            if (!layer || !layer.attrs || typeof layer.attrs !== 'object') {
                return createEmptyAnimationTimeline();
            }

            var doc = layer.attrs.vt_document && typeof layer.attrs.vt_document === 'object'
                ? layer.attrs.vt_document
                : null;
            if (!doc || !doc.animations || typeof doc.animations !== 'object') {
                return createEmptyAnimationTimeline();
            }

            return sanitizeAnimationTimeline(doc.animations);
        }

        function embedAnimationTimelineInDrawing(raw, timeline) {
            var layer = parseLayerDrawing(raw);
            if (!layer) {
                return raw;
            }

            layer.attrs = layer.attrs && typeof layer.attrs === 'object' ? layer.attrs : {};
            var doc = layer.attrs.vt_document && typeof layer.attrs.vt_document === 'object'
                ? layer.attrs.vt_document
                : {};

            var layout = board.getLayout();
            doc.kind = typeof doc.kind === 'string' && doc.kind !== ''
                ? doc.kind
                : String(window.KonvaSharedCore.DOC_MODEL_KIND || 'vt.konva.document');
            doc.version = Number.isFinite(Number(doc.version))
                ? Number(doc.version)
                : Number(window.KonvaSharedCore.DOC_MODEL_VERSION || 1);
            doc.field = doc.field && typeof doc.field === 'object'
                ? doc.field
                : {
                    key: layout.key,
                    width: layout.width,
                    height: layout.height
                };
            doc.animations = sanitizeAnimationTimeline(timeline);

            layer.attrs.vt_document = doc;
            layer.attrs.vt_document_kind = doc.kind;
            layer.attrs.vt_document_version = doc.version;

            return JSON.stringify(layer);
        }

        board.getMainLayer().on('destroy.animationAuthoring', '.item', function () {
            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            refreshHighlightOverlaysAtTime(animationCurrentTimeMs);
            updateAnimationControls();
        });

        board.getMainLayer().on('dragmove.animationHighlight transform.animationHighlight', '.item', function () {
            if (animationApplyingFrame || !animationTimeline.enabled) {
                return;
            }
            refreshHighlightOverlaysAtTime(animationCurrentTimeMs);
            updateAnimationControls();
        });

        board.getStage().on('click.animationHighlight tap.animationHighlight', function () {
            updateAnimationControls();
        });

        animationPanel.bindHandlers({
            onToggle: function () {
                setAnimationEnabled(!animationTimeline.enabled);
            },
            onPlay: function () {
                if (!animationTimeline.enabled) {
                    return;
                }

                if (animationIsPlaying) {
                    stopAnimationPlayback();
                    return;
                }

                startAnimationPlayback();
            },
            onRestart: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                applyAnimationFrameAt(0);
                syncPlaybackCursorToCurrentTime(0);
            },
            onPrevFrame: function () {
                stepAnimationFrame(-ANIMATION_TIME_STEP_MS);
            },
            onNextFrame: function () {
                stepAnimationFrame(ANIMATION_TIME_STEP_MS);
            },
            onToggleHighlight: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                toggleHighlightForSelectedItemAtCurrentMoment();
            },
            onDeleteKeyframe: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                removeCurrentMomentFromTimeline();
            },
            onRangeInput: function () {
                var nextTimeMs = Number(animationRangeEl.value || 0);
                stopAnimationPlayback();
                applyAnimationFrameAt(nextTimeMs);
                syncPlaybackCursorToCurrentTime(0);
            },
            onModeChange: function () {
                animationPlaybackMode = normalizePlaybackMode(animationModeEl.value);
                stopAnimationPlayback();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
            },
            onSlowRateChange: function () {
                animationSlowMotionRate = normalizePlaybackRate(animationSlowRateEl.value, DEFAULT_SLOW_MOTION_RATE);
                stopAnimationPlayback();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
            },
            onExport: function () {
                exportAnimationVideo();
            }
        });


        function handleBoardCleared() {
            stopAnimationPlayback();
            animationTimeline = createEmptyAnimationTimeline();
            animationCurrentTimeMs = 0;
            animationPlaybackElapsedMs = 0;
            animationPlaybackSegmentIndex = 0;
            animationPlaybackRate = 1;
            activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            refreshHighlightOverlaysAtTime(0);
            updateAnimationControls();
        }

        function handleDeleteSelected() {
            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            updateAnimationControls();
        }

        function handleExternalVisualChange(reason) {
            refreshHighlightOverlaysAtTime(animationCurrentTimeMs);
            updateAnimationControls();
            notifyContentChanged(reason || 'board');
        }

        if (animationModeEl) {
            animationPlaybackMode = normalizePlaybackMode(animationModeEl.value);
        }
        if (animationSlowRateEl) {
            animationSlowMotionRate = normalizePlaybackRate(animationSlowRateEl.value, DEFAULT_SLOW_MOTION_RATE);
        }
        activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
        syncPlaybackCursorToCurrentTime(0);
        updateAnimationControls();

        return {
            handleBoardContentChange: handleBoardContentChange,
            handleBoardCleared: handleBoardCleared,
            handleDeleteSelected: handleDeleteSelected,
            handleExternalVisualChange: handleExternalVisualChange,
            loadDrawingData: function (drawingJson) {
                loadDrawingState(drawingJson || '');
            },
            exportDrawingData: function (rawDrawing) {
                stopAnimationPlayback();
                removeStaleAnimationTracks();
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);

                var serialized = typeof rawDrawing === 'string' && rawDrawing !== ''
                    ? rawDrawing
                    : board.exportDrawingData();
                return embedAnimationTimelineInDrawing(serialized, animationTimeline);
            }
        };
    }

    window.MatchTacticsAnimationAuthoring = {
        create: create
    };
}(window));
