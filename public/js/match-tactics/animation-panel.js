(function (window) {
    'use strict';

    if (window.MatchTacticsAnimationPanel) {
        return;
    }

    function getAnimationIconSvg(iconName) {
        if (iconName === 'toggle') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="3.8" x2="12" y2="11.2"></line><path d="M17.3 6.8a8.2 8.2 0 1 1-10.6 0"></path></svg>';
        }
        if (iconName === 'play') {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"></path></svg>';
        }
        if (iconName === 'pause') {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14"></rect><rect x="14" y="5" width="4" height="14"></rect></svg>';
        }
        if (iconName === 'restart') {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="4.5" y="5" width="2.5" height="14"></rect><path d="M19 6v12l-5.6-6z"></path><path d="M13 6v12l-5.6-6z"></path></svg>';
        }
        if (iconName === 'prev') {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="5" y="5" width="2.5" height="14"></rect><path d="M18 6v12l-9-6z"></path></svg>';
        }
        if (iconName === 'next') {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="16.5" y="5" width="2.5" height="14"></rect><path d="M6 6v12l9-6z"></path></svg>';
        }
        if (iconName === 'export') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10"></path><polyline points="8 10 12 14 16 10"></polyline><rect x="4" y="15" width="16" height="5" rx="1.4"></rect></svg>';
        }
        if (iconName === 'highlight') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.6l2.2 4.4 4.8.7-3.5 3.4.8 4.8L12 14.6 7.7 17l.8-4.8L5 8.7l4.8-.7z"></path></svg>';
        }
        if (iconName === 'key-add') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4.5h11.5l2.5 2.5V19.5H5z"></path><path d="M8 4.5v5h7v-5"></path><rect x="8" y="13.5" width="8" height="6"></rect></svg>';
        }
        if (iconName === 'key-delete') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4 7 20 7"></polyline><path d="M18.5 7l-1 12a2 2 0 0 1-2 1.8H8.5a2 2 0 0 1-2-1.8L5.5 7"></path><path d="M9.5 7V5.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5V7"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
        }
        return '';
    }

    function setButtonIcon(button, iconName) {
        if (!button) {
            return;
        }

        button.innerHTML = getAnimationIconSvg(iconName);
    }

    function createPanel(config) {
        var options = config && typeof config === 'object' ? config : {};
        var tacticsMainEl = options.tacticsMainEl || null;
        var editorShellEl = options.editorShellEl || null;

        var panelEl = document.createElement('div');
        panelEl.className = 'match-tactics-animation-panel';

        var defaultDurationLabel = String(options.defaultDurationLabel || '1.0s');
        var rangeMax = Number(options.defaultDurationMs);
        if (!Number.isFinite(rangeMax) || rangeMax <= 0) {
            rangeMax = 1000;
        }
        var rangeStep = Number(options.timeStepMs);
        if (!Number.isFinite(rangeStep) || rangeStep <= 0) {
            rangeStep = 1000;
        }

        var modeNormalSlow = String(options.playbackModeNormalAndSlow || 'normal_slow');
        var modeNormal = String(options.playbackModeNormal || 'normal');
        var modeSlowOnly = String(options.playbackModeSlowOnly || 'slow_only');

        panelEl.innerHTML = ''
            + '<div class="match-tactics-animation-head">'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-toggle" aria-label="Animatie aan of uit" title="Animatie aan of uit"></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-play" aria-label="Afspelen" title="Afspelen" disabled></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-restart" aria-label="Terug naar begin" title="Terug naar begin" disabled></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-prev" aria-label="Vorige frame" title="Vorige frame" disabled></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-next" aria-label="Volgende frame" title="Volgende frame" disabled></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-highlight" aria-label="Speler highlighten" title="Speler highlighten" disabled></button>'
            + '  <button type="button" class="match-tactics-animation-btn match-tactics-animation-keyframe-delete" aria-label="Keyframe verwijderen" title="Keyframe verwijderen" disabled></button>'
            + '  <div class="match-tactics-animation-controls">'
            + '    <label class="match-tactics-animation-select-field" for="match-tactics-animation-mode">'
            + '      <span class="match-tactics-animation-select-label">Modus</span>'
            + '      <select id="match-tactics-animation-mode" class="match-tactics-animation-select match-tactics-animation-mode">'
            + '        <option value="' + modeNormalSlow + '" selected>Normaal + slowmo</option>'
            + '        <option value="' + modeNormal + '">Alleen normaal</option>'
            + '        <option value="' + modeSlowOnly + '">Alleen slowmo</option>'
            + '      </select>'
            + '    </label>'
            + '    <label class="match-tactics-animation-select-field" for="match-tactics-animation-slow-rate">'
            + '      <span class="match-tactics-animation-select-label">Slowmo</span>'
            + '      <select id="match-tactics-animation-slow-rate" class="match-tactics-animation-select match-tactics-animation-slow-rate">'
            + '        <option value="0.25">0.25x</option>'
            + '        <option value="0.5" selected>0.5x</option>'
            + '        <option value="0.75">0.75x</option>'
            + '      </select>'
            + '    </label>'
            + '    <span class="match-tactics-animation-select-field match-tactics-animation-export-field">'
            + '      <span class="match-tactics-animation-select-label match-tactics-animation-export-label">Export</span>'
            + '      <button type="button" class="match-tactics-animation-btn match-tactics-animation-export-btn" aria-label="Exporteer video" title="Exporteer video"></button>'
            + '    </span>'
            + '  </div>'
            + '  <span class="match-tactics-animation-time">0.0s / ' + defaultDurationLabel + '</span>'
            + '</div>'
            + '<input type="range" class="match-tactics-animation-range" min="0" max="' + rangeMax + '" step="' + rangeStep + '" value="0" disabled>';

        if (tacticsMainEl && editorShellEl && editorShellEl.parentNode === tacticsMainEl) {
            tacticsMainEl.insertBefore(panelEl, editorShellEl);
        } else if (tacticsMainEl) {
            tacticsMainEl.appendChild(panelEl);
        }

        var refs = {
            panelEl: panelEl,
            toggleBtn: panelEl.querySelector('.match-tactics-animation-toggle'),
            playBtn: panelEl.querySelector('.match-tactics-animation-play'),
            restartBtn: panelEl.querySelector('.match-tactics-animation-restart'),
            prevFrameBtn: panelEl.querySelector('.match-tactics-animation-prev'),
            nextFrameBtn: panelEl.querySelector('.match-tactics-animation-next'),
            highlightBtn: panelEl.querySelector('.match-tactics-animation-highlight'),
            deleteKeyframeBtn: panelEl.querySelector('.match-tactics-animation-keyframe-delete'),
            modeEl: panelEl.querySelector('.match-tactics-animation-mode'),
            slowRateEl: panelEl.querySelector('.match-tactics-animation-slow-rate'),
            exportBtn: panelEl.querySelector('.match-tactics-animation-export-btn'),
            rangeEl: panelEl.querySelector('.match-tactics-animation-range'),
            timeEl: panelEl.querySelector('.match-tactics-animation-time')
        };

        setButtonIcon(refs.toggleBtn, 'toggle');
        setButtonIcon(refs.playBtn, 'play');
        setButtonIcon(refs.restartBtn, 'restart');
        setButtonIcon(refs.prevFrameBtn, 'prev');
        setButtonIcon(refs.nextFrameBtn, 'next');
        setButtonIcon(refs.highlightBtn, 'highlight');
        setButtonIcon(refs.deleteKeyframeBtn, 'key-delete');
        setButtonIcon(refs.exportBtn, 'export');

        function bindHandlers(handlers) {
            var map = handlers && typeof handlers === 'object' ? handlers : {};

            if (refs.toggleBtn && typeof map.onToggle === 'function') {
                refs.toggleBtn.addEventListener('click', map.onToggle);
            }
            if (refs.playBtn && typeof map.onPlay === 'function') {
                refs.playBtn.addEventListener('click', map.onPlay);
            }
            if (refs.restartBtn && typeof map.onRestart === 'function') {
                refs.restartBtn.addEventListener('click', map.onRestart);
            }
            if (refs.prevFrameBtn && typeof map.onPrevFrame === 'function') {
                refs.prevFrameBtn.addEventListener('click', map.onPrevFrame);
            }
            if (refs.nextFrameBtn && typeof map.onNextFrame === 'function') {
                refs.nextFrameBtn.addEventListener('click', map.onNextFrame);
            }
            if (refs.highlightBtn && typeof map.onToggleHighlight === 'function') {
                refs.highlightBtn.addEventListener('click', map.onToggleHighlight);
            }
            if (refs.deleteKeyframeBtn && typeof map.onDeleteKeyframe === 'function') {
                refs.deleteKeyframeBtn.addEventListener('click', map.onDeleteKeyframe);
            }
            if (refs.rangeEl && typeof map.onRangeInput === 'function') {
                refs.rangeEl.addEventListener('input', map.onRangeInput);
            }
            if (refs.modeEl && typeof map.onModeChange === 'function') {
                refs.modeEl.addEventListener('change', map.onModeChange);
            }
            if (refs.slowRateEl && typeof map.onSlowRateChange === 'function') {
                refs.slowRateEl.addEventListener('change', map.onSlowRateChange);
            }
            if (refs.exportBtn && typeof map.onExport === 'function') {
                refs.exportBtn.addEventListener('click', map.onExport);
            }
        }

        return {
            element: panelEl,
            refs: refs,
            setButtonIcon: setButtonIcon,
            bindHandlers: bindHandlers
        };
    }

    window.MatchTacticsAnimationPanel = {
        createPanel: createPanel,
        getAnimationIconSvg: getAnimationIconSvg,
        setButtonIcon: setButtonIcon
    };
}(window));
