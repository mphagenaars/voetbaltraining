(function (window) {
    'use strict';

    if (window.MatchTacticsShirtColorMenu) {
        return;
    }

    var DEFAULT_SHIRT_LONG_PRESS_MS = 450;
    var DEFAULT_SHIRT_LONG_PRESS_MOVE_PX = 10;
    var DEFAULT_SHIRT_ASSET_TYPE_ALIASES = {
        shirt_orange: 'shirt_yellow'
    };
    var DEFAULT_SHIRT_COLOR_OPTIONS = [
        { assetType: 'shirt_red_black', label: 'Rood/Zwart', color: '#d32f2f', colorAlt: '#101010' },
        { assetType: 'shirt_red_white', label: 'Rood/Wit', color: '#d32f2f', colorAlt: '#ffffff' },
        { assetType: 'shirt_blue', label: 'Blauw', color: '#1e88e5', colorAlt: '' },
        { assetType: 'shirt_yellow', label: 'Geel', color: '#fdd835', colorAlt: '' }
    ];

    function create(config) {
        var options = config && typeof config === 'object' ? config : {};
        var board = options.board;
        if (!board || typeof board.getStage !== 'function' || typeof board.getMainLayer !== 'function') {
            console.error('MatchTacticsShirtColorMenu requires a valid board instance.');
            return null;
        }

        var onShirtColorApplied = typeof options.onShirtColorApplied === 'function'
            ? options.onShirtColorApplied
            : function () {};

        var shirtLongPressMs = Number(options.longPressMs);
        if (!Number.isFinite(shirtLongPressMs) || shirtLongPressMs <= 0) {
            shirtLongPressMs = DEFAULT_SHIRT_LONG_PRESS_MS;
        }

        var shirtLongPressMovePx = Number(options.longPressMovePx);
        if (!Number.isFinite(shirtLongPressMovePx) || shirtLongPressMovePx <= 0) {
            shirtLongPressMovePx = DEFAULT_SHIRT_LONG_PRESS_MOVE_PX;
        }

        var shirtAssetTypeAliases = options.assetTypeAliases && typeof options.assetTypeAliases === 'object'
            ? options.assetTypeAliases
            : DEFAULT_SHIRT_ASSET_TYPE_ALIASES;

        var shirtColorOptions = Array.isArray(options.colorOptions) && options.colorOptions.length > 0
            ? options.colorOptions
            : DEFAULT_SHIRT_COLOR_OPTIONS;

        function extractAssetTypeFromImageSrc(imageSrc) {
            var src = String(imageSrc || '').trim();
            if (src === '') {
                return '';
            }

            var fileName = src.split('/').pop() || '';
            return fileName.replace(/\.svg$/i, '').trim();
        }

        function normalizeShirtAssetType(assetType) {
            var normalized = String(assetType || '').trim();
            if (normalized === '') {
                return '';
            }

            return shirtAssetTypeAliases[normalized] || normalized;
        }

        function isKnownShirtAssetType(assetType) {
            var normalized = normalizeShirtAssetType(assetType);
            if (normalized === '') {
                return false;
            }

            return shirtColorOptions.some(function (option) {
                return option.assetType === normalized;
            });
        }

        function getShirtAssetTypeForNode(node) {
            if (!node || typeof node.getClassName !== 'function' || node.getClassName() !== 'Image') {
                return '';
            }

            var assetType = normalizeShirtAssetType(extractAssetTypeFromImageSrc(node.getAttr('imageSrc')));
            return isKnownShirtAssetType(assetType) ? assetType : '';
        }

        function isShirtItemNode(node) {
            return getShirtAssetTypeForNode(node) !== '';
        }

        function getClientPointFromDomEvent(domEvent) {
            if (!domEvent) {
                return null;
            }

            if (domEvent.touches && domEvent.touches.length > 0) {
                return {
                    x: Number(domEvent.touches[0].clientX),
                    y: Number(domEvent.touches[0].clientY)
                };
            }

            if (domEvent.changedTouches && domEvent.changedTouches.length > 0) {
                return {
                    x: Number(domEvent.changedTouches[0].clientX),
                    y: Number(domEvent.changedTouches[0].clientY)
                };
            }

            if (Number.isFinite(Number(domEvent.clientX)) && Number.isFinite(Number(domEvent.clientY))) {
                return {
                    x: Number(domEvent.clientX),
                    y: Number(domEvent.clientY)
                };
            }

            return null;
        }

        function buildShirtColorMenu() {
            var menuEl = document.createElement('div');
            menuEl.className = 'match-tactics-shirt-color-menu';
            menuEl.hidden = true;

            shirtColorOptions.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'match-tactics-shirt-color-btn';
                button.dataset.assetType = option.assetType;
                button.title = option.label;
                button.setAttribute('aria-label', option.label);

                var swatch = document.createElement('span');
                swatch.className = 'match-tactics-shirt-color-swatch';
                if (option.colorAlt) {
                    swatch.style.background = 'linear-gradient(135deg, ' + option.color + ' 0 50%, ' + option.colorAlt + ' 50% 100%)';
                } else {
                    swatch.style.background = option.color;
                }
                button.appendChild(swatch);

                var label = document.createElement('span');
                label.className = 'match-tactics-shirt-color-label';
                label.textContent = option.label;
                button.appendChild(label);

                menuEl.appendChild(button);
            });

            document.body.appendChild(menuEl);
            return menuEl;
        }

        function positionShirtColorMenu(menuEl, clientPoint) {
            if (!menuEl || !clientPoint) {
                return;
            }

            var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            var menuWidth = menuEl.offsetWidth || 170;
            var menuHeight = menuEl.offsetHeight || 110;
            var margin = 8;

            var left = clientPoint.x + 10;
            var top = clientPoint.y + 10;

            if ((left + menuWidth + margin) > viewportWidth) {
                left = Math.max(margin, viewportWidth - menuWidth - margin);
            }
            if ((top + menuHeight + margin) > viewportHeight) {
                top = Math.max(margin, viewportHeight - menuHeight - margin);
            }

            menuEl.style.left = Math.round(left) + 'px';
            menuEl.style.top = Math.round(top) + 'px';
        }

        function getNodeRenderedImageSize(node) {
            if (!node) {
                return null;
            }

            var width = Number(node.width());
            var height = Number(node.height());
            var scaleX = Number(node.scaleX());
            var scaleY = Number(node.scaleY());

            if (
                Number.isFinite(width) &&
                Number.isFinite(height) &&
                Number.isFinite(scaleX) &&
                Number.isFinite(scaleY) &&
                width > 0 &&
                height > 0
            ) {
                return {
                    width: Math.abs(width * scaleX),
                    height: Math.abs(height * scaleY),
                    scaleXSign: scaleX < 0 ? -1 : 1,
                    scaleYSign: scaleY < 0 ? -1 : 1
                };
            }

            return null;
        }

        function applyShirtAssetTypeToNode(node, assetType) {
            var targetAssetType = normalizeShirtAssetType(assetType);
            if (!node || !isKnownShirtAssetType(targetAssetType)) {
                return;
            }

            var currentRawAssetType = extractAssetTypeFromImageSrc(node.getAttr('imageSrc'));
            var currentAssetType = normalizeShirtAssetType(currentRawAssetType);
            if (currentAssetType === targetAssetType && currentRawAssetType === targetAssetType) {
                return;
            }

            var currentRenderedSize = getNodeRenderedImageSize(node);
            var imageSrc = '/images/assets/' + targetAssetType + '.svg';
            Konva.Image.fromURL(imageSrc, function (loadedNode) {
                if (!loadedNode || typeof loadedNode.image !== 'function' || !node.getStage()) {
                    return;
                }

                var imageObj = loadedNode.image();
                if (!imageObj) {
                    return;
                }

                var naturalWidth = Number(imageObj.width || 0);
                var naturalHeight = Number(imageObj.height || 0);
                if (naturalWidth <= 0 || naturalHeight <= 0) {
                    return;
                }

                node.image(imageObj);
                node.offsetX(naturalWidth / 2);
                node.offsetY(naturalHeight / 2);

                if (currentRenderedSize && currentRenderedSize.width > 0 && currentRenderedSize.height > 0) {
                    node.scaleX(currentRenderedSize.scaleXSign * (currentRenderedSize.width / naturalWidth));
                    node.scaleY(currentRenderedSize.scaleYSign * (currentRenderedSize.height / naturalHeight));
                }

                node.setAttr('imageSrc', imageSrc);
                node.setAttr('shirtAssetType', targetAssetType);

                board.getMainLayer().batchDraw();
                board.getUiLayer().batchDraw();
                onShirtColorApplied('shirt-color');
            });
        }

        var shirtColorMenuEl = buildShirtColorMenu();
        var shirtColorMenuTargetNode = null;
        var shirtLongPressTimerId = 0;
        var shirtLongPressStartPoint = null;
        var shirtLongPressTargetNode = null;

        function closeShirtColorMenu() {
            shirtColorMenuTargetNode = null;
            shirtColorMenuEl.hidden = true;
            shirtColorMenuEl.classList.remove('is-visible');
            shirtColorMenuEl.querySelectorAll('.match-tactics-shirt-color-btn').forEach(function (button) {
                button.classList.remove('is-active');
            });
        }

        function openShirtColorMenu(targetNode, clientPoint) {
            if (!targetNode || !isShirtItemNode(targetNode)) {
                closeShirtColorMenu();
                return;
            }

            shirtColorMenuTargetNode = targetNode;
            shirtColorMenuEl.hidden = false;
            shirtColorMenuEl.classList.add('is-visible');
            positionShirtColorMenu(shirtColorMenuEl, clientPoint);

            var currentAssetType = getShirtAssetTypeForNode(targetNode);
            shirtColorMenuEl.querySelectorAll('.match-tactics-shirt-color-btn').forEach(function (button) {
                button.classList.toggle('is-active', String(button.dataset.assetType || '') === currentAssetType);
            });
        }

        function clearShirtLongPressTimer() {
            if (shirtLongPressTimerId) {
                window.clearTimeout(shirtLongPressTimerId);
                shirtLongPressTimerId = 0;
            }
            shirtLongPressStartPoint = null;
            shirtLongPressTargetNode = null;
        }

        function scheduleShirtLongPress(targetNode, domEvent) {
            clearShirtLongPressTimer();
            if (!targetNode || !isShirtItemNode(targetNode)) {
                return;
            }

            var startPoint = getClientPointFromDomEvent(domEvent);
            if (!startPoint) {
                return;
            }

            shirtLongPressTargetNode = targetNode;
            shirtLongPressStartPoint = startPoint;
            shirtLongPressTimerId = window.setTimeout(function () {
                shirtLongPressTimerId = 0;
                if (!shirtLongPressTargetNode || !shirtLongPressTargetNode.getStage()) {
                    clearShirtLongPressTimer();
                    return;
                }

                openShirtColorMenu(shirtLongPressTargetNode, shirtLongPressStartPoint);
                clearShirtLongPressTimer();
            }, shirtLongPressMs);
        }

        function maybeCancelLongPressByMove(domEvent) {
            if (!shirtLongPressTimerId || !shirtLongPressStartPoint) {
                return;
            }

            var point = getClientPointFromDomEvent(domEvent);
            if (!point) {
                return;
            }

            if (Math.abs(point.x - shirtLongPressStartPoint.x) > shirtLongPressMovePx || Math.abs(point.y - shirtLongPressStartPoint.y) > shirtLongPressMovePx) {
                clearShirtLongPressTimer();
            }
        }

        shirtColorMenuEl.addEventListener('click', function (event) {
            var button = event.target instanceof HTMLElement
                ? event.target.closest('.match-tactics-shirt-color-btn')
                : null;
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var assetType = String(button.dataset.assetType || '').trim();
            if (shirtColorMenuTargetNode && assetType !== '') {
                applyShirtAssetTypeToNode(shirtColorMenuTargetNode, assetType);
            }
            closeShirtColorMenu();
        });

        shirtColorMenuEl.addEventListener('mousedown', function (event) {
            event.stopPropagation();
        });
        shirtColorMenuEl.addEventListener('touchstart', function (event) {
            event.stopPropagation();
        }, { passive: true });

        document.addEventListener('mousedown', function (event) {
            if (!shirtColorMenuEl.classList.contains('is-visible')) {
                return;
            }
            if (event.target instanceof Node && shirtColorMenuEl.contains(event.target)) {
                return;
            }
            closeShirtColorMenu();
        });
        document.addEventListener('touchstart', function (event) {
            if (!shirtColorMenuEl.classList.contains('is-visible')) {
                return;
            }
            if (event.target instanceof Node && shirtColorMenuEl.contains(event.target)) {
                return;
            }
            closeShirtColorMenu();
        }, { passive: true });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeShirtColorMenu();
            }
        });
        window.addEventListener('resize', closeShirtColorMenu);
        window.addEventListener('scroll', closeShirtColorMenu, true);

        board.getStage().on('contextmenu.shirtColor', function (event) {
            var targetNode = event.target;
            if (!isShirtItemNode(targetNode)) {
                closeShirtColorMenu();
                return;
            }

            if (event.evt) {
                event.evt.preventDefault();
                event.evt.stopPropagation();
            }

            openShirtColorMenu(targetNode, getClientPointFromDomEvent(event.evt));
        });

        board.getStage().on('mousedown.shirtColor', function (event) {
            if (!(event.evt && event.evt.button === 2)) {
                closeShirtColorMenu();
            }
            clearShirtLongPressTimer();
        });

        board.getStage().on('touchstart.shirtColor', function (event) {
            if (board.getTool && board.getTool() !== 'select') {
                clearShirtLongPressTimer();
                return;
            }

            if (!isShirtItemNode(event.target)) {
                clearShirtLongPressTimer();
                closeShirtColorMenu();
                return;
            }

            scheduleShirtLongPress(event.target, event.evt);
        });

        board.getStage().on('touchmove.shirtColor', function (event) {
            maybeCancelLongPressByMove(event.evt);
        });
        board.getStage().on('touchend.shirtColor touchcancel.shirtColor', function () {
            clearShirtLongPressTimer();
        });

        board.getMainLayer().on('dragstart.shirtColor', '.item', function () {
            clearShirtLongPressTimer();
            closeShirtColorMenu();
        });
        board.getMainLayer().on('destroy.shirtColor', '.item', function () {
            if (shirtColorMenuTargetNode === this) {
                closeShirtColorMenu();
            }
            if (shirtLongPressTargetNode === this) {
                clearShirtLongPressTimer();
            }
        });

        return {
            closeMenu: closeShirtColorMenu
        };
    }

    window.MatchTacticsShirtColorMenu = {
        create: create
    };
}(window));
