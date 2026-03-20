(function (window) {
    'use strict';

    if (window.MatchTacticsBoardControls) {
        return;
    }

    function create(config) {
        var options = config && typeof config === 'object' ? config : {};

        var markerColorBlackBtn = options.markerColorBlackBtn || null;
        var markerColorRedBtn = options.markerColorRedBtn || null;
        var markerStyleSolidBtn = options.markerStyleSolidBtn || null;
        var markerStyleDashedBtn = options.markerStyleDashedBtn || null;
        var clearBtn = options.clearBtn || null;
        var deleteSelectedBtn = options.deleteSelectedBtn || null;
        var toBackBtn = options.toBackBtn || null;

        var markerColor = '#ffffff';
        var markerStrokeStyle = 'solid';

        function applyMarkerButtonState() {
            if (markerColorBlackBtn) {
                markerColorBlackBtn.classList.toggle('active', markerColor === '#ffffff');
            }
            if (markerColorRedBtn) {
                markerColorRedBtn.classList.toggle('active', markerColor === '#d32f2f');
            }
            if (markerStyleSolidBtn) {
                markerStyleSolidBtn.classList.toggle('active', markerStrokeStyle === 'solid');
            }
            if (markerStyleDashedBtn) {
                markerStyleDashedBtn.classList.toggle('active', markerStrokeStyle === 'dashed');
            }
        }

        function resolveDrawConfig(context) {
            if (!context || context.tool !== 'marker') {
                return null;
            }

            var attrs = {
                stroke: markerColor,
                fill: markerColor,
                strokeWidth: 4.5,
                pointerLength: 13,
                pointerWidth: 13,
                lineCap: 'round',
                lineJoin: 'round',
                tension: 0.5,
                name: 'item'
            };

            if (markerStrokeStyle === 'dashed') {
                attrs.dash = [24, 18];
                attrs.lineCap = 'butt';
                attrs.tension = 0;
                attrs.strokeWidth = 4;
            }

            return {
                kind: 'poly-arrow',
                attrs: attrs,
                minPointDistance: markerStrokeStyle === 'dashed' ? 6.5 : 2.4,
                minPoints: 6,
                minLength: 6
            };
        }

        function bindBoardActions(config) {
            var options = config && typeof config === 'object' ? config : {};
            var board = options.board;
            if (!board) {
                return;
            }

            var onClearConfirmed = typeof options.onClearConfirmed === 'function'
                ? options.onClearConfirmed
                : function () {};
            var onDeleteSelected = typeof options.onDeleteSelected === 'function'
                ? options.onDeleteSelected
                : function () {};

            if (markerColorBlackBtn) {
                markerColorBlackBtn.addEventListener('click', function () {
                    markerColor = '#ffffff';
                    applyMarkerButtonState();
                    board.setTool('marker');
                });
            }

            if (markerColorRedBtn) {
                markerColorRedBtn.addEventListener('click', function () {
                    markerColor = '#d32f2f';
                    applyMarkerButtonState();
                    board.setTool('marker');
                });
            }

            if (markerStyleSolidBtn) {
                markerStyleSolidBtn.addEventListener('click', function () {
                    markerStrokeStyle = 'solid';
                    applyMarkerButtonState();
                    board.setTool('marker');
                });
            }

            if (markerStyleDashedBtn) {
                markerStyleDashedBtn.addEventListener('click', function () {
                    markerStrokeStyle = 'dashed';
                    applyMarkerButtonState();
                    board.setTool('marker');
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    board.clearArmedToolbarType();
                    if (!confirm('Weet je zeker dat je deze tekening wilt wissen?')) {
                        return;
                    }

                    board.clearAll();
                    onClearConfirmed();
                });
            }

            if (deleteSelectedBtn) {
                deleteSelectedBtn.addEventListener('click', function () {
                    board.clearArmedToolbarType();
                    board.deleteSelected();
                    onDeleteSelected();
                });
            }

            if (toBackBtn) {
                toBackBtn.addEventListener('click', function () {
                    board.clearArmedToolbarType();
                    board.sendSelectedToBack();
                });
            }

            applyMarkerButtonState();
            board.setTool('select');
        }

        return {
            resolveDrawConfig: resolveDrawConfig,
            bindBoardActions: bindBoardActions,
            applyMarkerButtonState: applyMarkerButtonState
        };
    }

    window.MatchTacticsBoardControls = {
        create: create
    };
}(window));
