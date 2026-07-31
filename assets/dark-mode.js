(function () {
    'use strict';

    var storageKey = 'typecho-admin-theme';
    var allowedModes = ['system', 'light', 'dark'];
    var root = document.documentElement;
    var mediaQuery = window.matchMedia
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;
    var mobileQuery = window.matchMedia
        ? window.matchMedia('(max-width: 575px)')
        : null;
    var button = null;
    var icons = {};
    var control = null;
    var navigation = null;

    function normalizeMode(mode) {
        return allowedModes.indexOf(mode) !== -1 ? mode : 'system';
    }

    function currentMode() {
        return normalizeMode(root.getAttribute('data-typecho-theme-mode'));
    }

    function resolvedTheme(mode) {
        if (mode === 'dark') {
            return 'dark';
        }

        if (mode === 'light') {
            return 'light';
        }

        return mediaQuery && mediaQuery.matches ? 'dark' : 'light';
    }

    function updateControl(mode, theme) {
        var labels = {
            system: '跟随设备',
            light: '日间模式',
            dark: '夜间模式'
        };
        var order = ['light', 'dark', 'system'];
        var nextMode;

        if (!button) {
            return;
        }

        nextMode = order[(order.indexOf(mode) + 1) % order.length];
        button.setAttribute('data-mode', mode);
        button.setAttribute(
            'aria-label',
            '当前：' + labels[mode] + '；点击切换为' + labels[nextMode]
        );
        button.title = '当前：' + labels[mode]
            + (mode === 'system' ? '（实际为' + (theme === 'dark' ? '夜间' : '日间') + '）' : '')
            + '；点击切换为' + labels[nextMode];

        while (button.firstChild) {
            button.removeChild(button.firstChild);
        }
        button.appendChild(icons[mode]);
    }

    function applyMode(mode, persist) {
        var normalized = normalizeMode(mode);
        var theme = resolvedTheme(normalized);

        root.setAttribute('data-typecho-theme-mode', normalized);
        root.setAttribute('data-typecho-theme', theme);
        root.style.colorScheme = theme;
        updateControl(normalized, theme);

        if (persist) {
            try {
                window.localStorage.setItem(storageKey, normalized);
            } catch (error) {
                // The selected mode still applies for the current page.
            }
        }
    }

    function placeNavigationControl() {
        if (!control || !navigation) {
            return;
        }

        if (mobileQuery && mobileQuery.matches) {
            control.classList.add('is-mobile-header');
            document.body.appendChild(control);
        } else {
            control.classList.remove('is-mobile-header');
            navigation.insertBefore(control, navigation.firstChild);
        }
    }

    function createIcon(definitions) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        definitions.forEach(function (definition) {
            var element = document.createElementNS('http://www.w3.org/2000/svg', definition[0]);
            Object.keys(definition[1]).forEach(function (attribute) {
                element.setAttribute(attribute, definition[1][attribute]);
            });
            svg.appendChild(element);
        });

        return svg;
    }

    function createControl() {
        var iconDefinitions = {
            light: [
                ['circle', {cx: '12', cy: '12', r: '4'}],
                ['path', {d: 'M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.42'}]
            ],
            dark: [
                ['path', {d: 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z'}]
            ],
            system: [
                ['rect', {x: '2', y: '3', width: '20', height: '14', rx: '2'}],
                ['path', {d: 'M8 21h8M12 17v4'}]
            ]
        };
        var buttonOrder = ['light', 'dark', 'system'];

        control = document.createElement('div');
        control.className = 'dark-mode-control';

        button = document.createElement('button');
        button.type = 'button';
        button.className = 'dark-mode-button';

        buttonOrder.forEach(function (mode) {
            icons[mode] = createIcon(iconDefinitions[mode]);
        });

        button.addEventListener('click', function () {
            var index = buttonOrder.indexOf(currentMode());
            applyMode(buttonOrder[(index + 1) % buttonOrder.length], true);
        });
        control.appendChild(button);

        navigation = document.querySelector('.typecho-head-nav .operate');
        if (navigation) {
            placeNavigationControl();
        } else {
            control.className += ' is-floating';
            document.body.appendChild(control);
        }

        updateControl(currentMode(), resolvedTheme(currentMode()));
    }

    function handleSystemChange() {
        if (currentMode() === 'system') {
            applyMode('system', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createControl, {once: true});
    } else {
        createControl();
    }

    if (mediaQuery) {
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleSystemChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleSystemChange);
        }
    }

    if (mobileQuery) {
        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', placeNavigationControl);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(placeNavigationControl);
        }
    }

    window.addEventListener('storage', function (event) {
        if (event.key === storageKey) {
            applyMode(normalizeMode(event.newValue), false);
        }
    });
})();
