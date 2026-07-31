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
    var select = null;
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
        if (!select) {
            return;
        }

        select.value = mode;
        select.title = '当前：' + select.options[select.selectedIndex].text
            + '（实际为' + (theme === 'dark' ? '深色' : '浅色') + '）';
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

    function createControl() {
        var label = document.createElement('span');
        var optionLabels = {
            system: '跟随设备',
            light: '浅色',
            dark: '深色'
        };

        control = document.createElement('label');
        control.className = 'dark-mode-control';
        label.className = 'dark-mode-control-label';
        label.textContent = '外观';

        select = document.createElement('select');
        select.className = 'dark-mode-select';
        select.setAttribute('aria-label', '后台外观');

        allowedModes.forEach(function (mode) {
            var option = document.createElement('option');
            option.value = mode;
            option.textContent = optionLabels[mode];
            select.appendChild(option);
        });

        select.addEventListener('change', function () {
            applyMode(select.value, true);
        });

        control.appendChild(label);
        control.appendChild(select);

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
