(function (window) {
    'use strict';

    if (window.MatchTacticsVideoExport) {
        return;
    }

    function selectExportMimeType(mimeCandidates) {
        if (typeof MediaRecorder === 'undefined') {
            return '';
        }

        var candidates = Array.isArray(mimeCandidates) && mimeCandidates.length > 0
            ? mimeCandidates.slice()
            : ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];

        if (typeof MediaRecorder.isTypeSupported !== 'function') {
            return candidates[candidates.length - 1];
        }

        for (var i = 0; i < candidates.length; i += 1) {
            if (MediaRecorder.isTypeSupported(candidates[i])) {
                return candidates[i];
            }
        }

        return '';
    }

    function getExportExtensionForMimeType(mimeType) {
        var normalized = String(mimeType || '').toLowerCase();
        if (normalized.indexOf('mp4') >= 0) {
            return 'mp4';
        }
        if (normalized.indexOf('quicktime') >= 0 || normalized.indexOf('mov') >= 0) {
            return 'mov';
        }
        return 'webm';
    }

    function parseFilenameFromContentDisposition(contentDispositionValue) {
        var value = String(contentDispositionValue || '');
        if (value === '') {
            return '';
        }

        var utf8Match = value.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8Match && utf8Match[1]) {
            try {
                return decodeURIComponent(utf8Match[1]).replace(/["\r\n]/g, '').trim();
            } catch (error) {
                return String(utf8Match[1]).replace(/["\r\n]/g, '').trim();
            }
        }

        var simpleMatch = value.match(/filename=\"?([^\";]+)\"?/i);
        if (!simpleMatch || !simpleMatch[1]) {
            return '';
        }

        return String(simpleMatch[1]).replace(/["\r\n]/g, '').trim();
    }

    function renderStageToCanvas(stage, context, targetCanvas) {
        if (!stage || !context || !targetCanvas) {
            return;
        }

        stage.draw();
        var snapshot = stage.toCanvas({ pixelRatio: 1 });
        context.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
        context.drawImage(snapshot, 0, 0, targetCanvas.width, targetCanvas.height);
    }

    function sanitizeFileNamePart(value) {
        var normalized = String(value || '').toLowerCase().trim();
        normalized = normalized.replace(/[^a-z0-9]+/g, '-');
        normalized = normalized.replace(/^-+|-+$/g, '');
        return normalized || 'situatie';
    }

    function buildExportFilename(title, extension) {
        var ext = String(extension || 'webm').trim() || 'webm';
        var safeTitle = sanitizeFileNamePart(title);
        return safeTitle + '.' + ext;
    }

    function triggerBlobDownload(blob, filename) {
        if (!blob || blob.size <= 0) {
            return;
        }

        var objectUrl = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();

        window.setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
            if (link.parentNode) {
                link.parentNode.removeChild(link);
            }
        }, 0);
    }

    function createExporter(config) {
        var options = config && typeof config === 'object' ? config : {};
        var recordingFps = Number(options.recordingFps);
        if (!Number.isFinite(recordingFps) || recordingFps <= 0) {
            recordingFps = 30;
        }

        var mimeCandidates = Array.isArray(options.mimeCandidates) ? options.mimeCandidates.slice() : null;
        var forcedExportMode = typeof options.forcedExportMode === 'string'
            ? String(options.forcedExportMode).trim()
            : '';
        var transcodeEndpoint = typeof options.transcodeEndpoint === 'string'
            ? options.transcodeEndpoint.trim()
            : '';
        var videoBitsPerSecond = Number(options.videoBitsPerSecond);
        if (!Number.isFinite(videoBitsPerSecond) || videoBitsPerSecond <= 0) {
            videoBitsPerSecond = 2500000;
        }
        var serverUploadHardLimitBytes = Number(options.serverUploadHardLimitBytes);
        if (!Number.isFinite(serverUploadHardLimitBytes) || serverUploadHardLimitBytes <= 0) {
            serverUploadHardLimitBytes = 100 * 1024 * 1024;
        }
        var conservativeUploadLimitBytes = Number(options.conservativeUploadLimitBytes);
        if (!Number.isFinite(conservativeUploadLimitBytes) || conservativeUploadLimitBytes <= 0) {
            conservativeUploadLimitBytes = 7 * 1024 * 1024;
        }
        var containerOverheadFactor = Number(options.containerOverheadFactor);
        if (!Number.isFinite(containerOverheadFactor) || containerOverheadFactor < 1) {
            containerOverheadFactor = 1.12;
        }

        function status(message, isError) {
            if (typeof options.setStatus === 'function') {
                options.setStatus(message, isError);
            }
        }

        function getState() {
            if (typeof options.getState === 'function') {
                return options.getState() || {};
            }
            return {};
        }

        function resolveExportMode(state) {
            if (typeof options.getExportMode === 'function') {
                var callbackMode = String(options.getExportMode(state) || '').trim();
                if (callbackMode !== '') {
                    return callbackMode;
                }
            }

            var stateMode = state && typeof state.playbackMode === 'string'
                ? String(state.playbackMode).trim()
                : '';
            if (stateMode !== '') {
                return stateMode;
            }

            return forcedExportMode || 'normal_slow';
        }

        function describeExportPlan(plan) {
            if (!plan || !Array.isArray(plan.segments) || plan.segments.length === 0) {
                return 'export';
            }

            var planMode = String(plan.mode || '').trim();
            if (planMode === 'slow_only') {
                return 'alleen slowmo';
            }
            if (planMode === 'normal') {
                return 'alleen normaal';
            }
            if (plan.segments.length === 2) {
                return 'normaal + slowmo';
            }

            var firstSegment = plan.segments[0];
            if (firstSegment && Number(firstSegment.rate) < 1) {
                return 'alleen slowmo';
            }

            return 'alleen normaal';
        }

        function estimateRecordingBytes(exportPlan) {
            if (!exportPlan || !Number.isFinite(Number(exportPlan.totalElapsedMs))) {
                return 0;
            }

            var totalSeconds = Math.max(0, Number(exportPlan.totalElapsedMs)) / 1000;
            var baseBytes = (totalSeconds * videoBitsPerSecond) / 8;
            return Math.round(baseBytes * containerOverheadFactor);
        }

        function formatEstimatedSizeLabel(bytes) {
            var value = Number(bytes);
            if (!Number.isFinite(value) || value <= 0) {
                return '0 MB';
            }
            return (value / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function getStage() {
            if (typeof options.getStage === 'function') {
                return options.getStage();
            }
            if (options.board && typeof options.board.getStage === 'function') {
                return options.board.getStage();
            }
            return null;
        }

        function getCsrfToken() {
            if (typeof options.getCsrfToken === 'function') {
                return String(options.getCsrfToken() || '').trim();
            }
            return String(options.csrfToken || '').trim();
        }

        function getMatchId() {
            if (typeof options.getMatchId === 'function') {
                return Number(options.getMatchId() || 0);
            }
            return Number(options.matchId || 0);
        }

        function getExportContext() {
            var rawContext = null;
            if (typeof options.getExportContext === 'function') {
                rawContext = options.getExportContext();
            }

            if (!rawContext || typeof rawContext !== 'object') {
                rawContext = {};
            }

            var context = {};
            if (Object.prototype.hasOwnProperty.call(rawContext, 'match_id')) {
                var rawMatchId = Number(rawContext.match_id);
                if (Number.isFinite(rawMatchId) && rawMatchId > 0) {
                    context.match_id = Math.round(rawMatchId);
                }
            }
            if (Object.prototype.hasOwnProperty.call(rawContext, 'team_id')) {
                var rawTeamId = Number(rawContext.team_id);
                if (Number.isFinite(rawTeamId) && rawTeamId > 0) {
                    context.team_id = Math.round(rawTeamId);
                }
            }

            if (!Object.prototype.hasOwnProperty.call(context, 'match_id')) {
                var legacyMatchId = Math.round(getMatchId());
                if (Number.isFinite(legacyMatchId) && legacyMatchId > 0) {
                    context.match_id = legacyMatchId;
                }
            }

            return context;
        }

        function requestMp4Transcode(recordingBlob, fallbackFilename, title, inputExtension) {
            if (
                transcodeEndpoint === '' ||
                typeof fetch !== 'function' ||
                typeof FormData === 'undefined'
            ) {
                return Promise.reject(new Error('Transcode endpoint niet beschikbaar.'));
            }

            var csrfToken = getCsrfToken();
            var exportContext = getExportContext();
            var hasContext = Object.keys(exportContext).length > 0;
            if (csrfToken === '' || !hasContext) {
                return Promise.reject(new Error('Ontbrekende exportcontext voor MP4-conversie.'));
            }

            var formData = new FormData();
            formData.append('video', recordingBlob, fallbackFilename);
            formData.append('csrf_token', csrfToken);
            Object.keys(exportContext).forEach(function (key) {
                formData.append(key, String(exportContext[key]));
            });
            formData.append('title', String(title || ''));
            formData.append('input_extension', String(inputExtension || 'webm'));

            return fetch(transcodeEndpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                var contentType = String(response.headers.get('content-type') || '').toLowerCase();
                if (!response.ok) {
                    if (contentType.indexOf('application/json') >= 0) {
                        return response.json().then(function (payload) {
                            var message = payload && payload.error ? String(payload.error) : 'MP4-conversie mislukt.';
                            throw new Error(message);
                        });
                    }
                    return response.text().then(function (text) {
                        throw new Error(String(text || 'MP4-conversie mislukt.'));
                    });
                }

                var serverFileName = parseFilenameFromContentDisposition(response.headers.get('content-disposition'));
                return response.blob().then(function (convertedBlob) {
                    if (!convertedBlob || convertedBlob.size <= 0) {
                        throw new Error('Server gaf een leeg MP4-bestand terug.');
                    }

                    return {
                        blob: convertedBlob,
                        filename: serverFileName !== '' ? serverFileName : buildExportFilename(title, 'mp4')
                    };
                });
            });
        }

        function exportVideo() {
            var state = getState();
            if (state.isExporting) {
                return;
            }

            if (!state.isAnimationEnabled) {
                status('Zet animatie aan voordat je exporteert.', true);
                return;
            }

            if ((Number(state.keyframeCount) || 0) <= 0) {
                status('Voeg minimaal 1 keyframe toe voordat je exporteert.', true);
                return;
            }

            if (typeof MediaRecorder === 'undefined') {
                status('Video-export wordt niet ondersteund in deze browser.', true);
                return;
            }

            var mimeType = selectExportMimeType(mimeCandidates);
            if (mimeType === '') {
                status('Geen ondersteund videoformaat gevonden in deze browser.', true);
                return;
            }
            var fileExtension = getExportExtensionForMimeType(mimeType);

            var stage = getStage();
            if (!stage) {
                status('Kon de editor-stage niet laden voor export.', true);
                return;
            }

            if (typeof options.buildPlaybackPlan !== 'function') {
                status('Playback-plan kon niet opgebouwd worden voor export.', true);
                return;
            }

            var exportMode = resolveExportMode(state);
            var exportPlan = options.buildPlaybackPlan(exportMode, state.slowRate);
            if (!exportPlan || !Array.isArray(exportPlan.segments) || exportPlan.segments.length === 0) {
                status('Kon het playback-plan voor export niet opbouwen.', true);
                return;
            }

            var estimatedSizeBytes = estimateRecordingBytes(exportPlan);
            var needsServerUpload = fileExtension !== 'mp4' && transcodeEndpoint !== '';
            if (needsServerUpload && estimatedSizeBytes > serverUploadHardLimitBytes) {
                status(
                    'Export te groot (~' + formatEstimatedSizeLabel(estimatedSizeBytes) + '). Maak de animatie korter of kies minder slowmo.',
                    true
                );
                return;
            }

            if (typeof options.stopAnimationPlayback === 'function') {
                options.stopAnimationPlayback();
            }

            var exportCanvas = document.createElement('canvas');
            exportCanvas.width = Math.max(2, Math.round(stage.width()));
            exportCanvas.height = Math.max(2, Math.round(stage.height()));

            var exportContext = exportCanvas.getContext('2d');
            if (!exportContext) {
                status('Kon geen 2D-canvascontext maken voor export.', true);
                return;
            }

            var stream = null;
            try {
                stream = exportCanvas.captureStream(recordingFps);
            } catch (error) {
                status('Capture stream niet beschikbaar in deze browser.', true);
                return;
            }

            if (!stream) {
                status('Kon geen videosignaal maken van het canvas.', true);
                return;
            }

            var mediaRecorder = null;
            try {
                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: mimeType,
                    videoBitsPerSecond: videoBitsPerSecond
                });
            } catch (error) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
                status('Video-opname kon niet starten met dit formaat.', true);
                return;
            }

            var chunks = [];
            var exportCancelled = false;

            if (typeof options.beforeExportStart === 'function') {
                options.beforeExportStart({
                    exportPlan: exportPlan,
                    mimeType: mimeType,
                    fileExtension: fileExtension
                });
            }

            var sizeRiskSuffix = (
                needsServerUpload &&
                estimatedSizeBytes > conservativeUploadLimitBytes
            )
                ? ' · let op: mogelijk te groot voor lage server-uploadlimieten'
                : '';
            status(
                'Video-export bezig (' + fileExtension.toUpperCase() + '): '
                + describeExportPlan(exportPlan)
                + ' (~' + formatEstimatedSizeLabel(estimatedSizeBytes) + ')'
                + sizeRiskSuffix
                + '.',
                false
            );

            mediaRecorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) {
                    chunks.push(event.data);
                }
            };

            mediaRecorder.onerror = function () {
                exportCancelled = true;
                status('Video-export mislukt tijdens opname.', true);
            };

            function finalizeExport() {
                if (typeof options.onFinalizeExport === 'function') {
                    options.onFinalizeExport();
                }
            }

            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });

                if (!exportCancelled && chunks.length > 0) {
                    var blob = new Blob(chunks, { type: mimeType });
                    var title = typeof options.getTitle === 'function' ? options.getTitle() : '';
                    var fallbackFilename = buildExportFilename(title, fileExtension);

                    if (fileExtension === 'mp4') {
                        triggerBlobDownload(blob, fallbackFilename);
                        status('Video-export voltooid als ' + fileExtension.toUpperCase() + '.', false);
                        finalizeExport();
                        return;
                    }

                    status('WebM opgenomen. Converteren naar MP4 voor universele afspeelbaarheid...', false);
                    requestMp4Transcode(blob, fallbackFilename, title, fileExtension)
                        .then(function (result) {
                            triggerBlobDownload(result.blob, result.filename);
                            status('Video-export voltooid als MP4.', false);
                        })
                        .catch(function (error) {
                            var errorMessage = error && error.message ? String(error.message) : 'MP4-conversie mislukt.';
                            triggerBlobDownload(blob, fallbackFilename);
                            status(errorMessage + ' WebM is wel gedownload als fallback.', true);
                        })
                        .finally(function () {
                            finalizeExport();
                        });
                    return;
                } else if (!exportCancelled) {
                    status('Video-export bevatte geen opnamedata.', true);
                }

                finalizeExport();
            };

            try {
                mediaRecorder.start(120);
            } catch (error) {
                exportCancelled = true;
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });

                if (typeof options.onFinalizeExport === 'function') {
                    options.onFinalizeExport();
                }

                status('Video-opname kon niet gestart worden.', true);
                return;
            }

            if (typeof options.runPlaybackPlan !== 'function') {
                exportCancelled = true;
                if (mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                }
                status('Playback runner ontbreekt voor export.', true);
                return;
            }

            options.runPlaybackPlan(exportPlan, {
                startElapsedMs: 0,
                onFrame: function (moment) {
                    if (typeof options.onPlaybackMoment === 'function') {
                        options.onPlaybackMoment(moment);
                    }
                    renderStageToCanvas(stage, exportContext, exportCanvas);
                },
                onStop: function (result) {
                    if (!result || result.cancelled === true) {
                        exportCancelled = true;
                    }

                    window.setTimeout(function () {
                        if (mediaRecorder.state !== 'inactive') {
                            mediaRecorder.stop();
                        }
                    }, result && result.cancelled === true ? 0 : Math.max(60, Math.round(1000 / recordingFps)));
                }
            });
        }

        return {
            exportVideo: exportVideo
        };
    }

    window.MatchTacticsVideoExport = {
        createExporter: createExporter,
        selectExportMimeType: selectExportMimeType,
        getExportExtensionForMimeType: getExportExtensionForMimeType,
        buildExportFilename: buildExportFilename,
        triggerBlobDownload: triggerBlobDownload
    };
}(window));
