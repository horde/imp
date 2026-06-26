/**
 * relative_time.js
 *
 * Renders <time is="time-ago" datetime="..."> elements as a localized
 * relative phrase ("35 minutes ago", "il y a 35 minutes", "vor 35 Minuten")
 * via Intl.RelativeTimeFormat, and refreshes them on a timer so the phrase
 * stays current without a page reload.
 *
 * Drop-in replacement for the 2014 vintage js/external/time-elements.js, which
 * shipped hardcoded English strings and was the cause of horde/imp#22.
 *
 * Locale lookup order:
 *   1. HordeCore.conf.language (not yet exposed; reserved for a future Core
 *      change — see ~/php/horde-development/libraries/core/)
 *   2. document.documentElement.lang (set server-side by Horde_PageOutput from
 *      the user's Horde language preference)
 *   3. undefined (lets Intl fall back to the browser default)
 *
 * TODO: This script is a candidate to move into Horde Core for reuse by other
 * applications that want to render relative times client-side. Kept local to
 * IMP for now to limit the scope of the bug fix.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

(function () {
    'use strict';

    var MINUTE = 60;
    var HOUR = 3600;
    var DAY = 86400;
    var WEEK = 604800;
    var MONTH = 2629746;   // average Gregorian month in seconds
    var YEAR = 31556952;   // average Gregorian year in seconds

    var formatterCache = {};

    function resolveLocale() {
        try {
            if (window.HordeCore && HordeCore.conf && HordeCore.conf.language) {
                return HordeCore.conf.language;
            }
        } catch (e) {
            // HordeCore not present on this page; fall through.
        }
        var lang = document.documentElement.lang;
        return lang ? lang : undefined;
    }

    function getFormatter() {
        var locale = resolveLocale();
        var key = locale || '__default__';
        if (!formatterCache[key]) {
            formatterCache[key] = new Intl.RelativeTimeFormat(locale, {
                numeric: 'auto',
                style: 'long'
            });
        }
        return formatterCache[key];
    }

    /**
     * Pick a {value, unit} pair for the given signed delta in seconds.
     * Negative deltas mean "in the past".
     */
    function pickUnit(deltaSeconds) {
        var abs = Math.abs(deltaSeconds);
        var sign = deltaSeconds < 0 ? -1 : 1;
        if (abs < MINUTE) {
            return { value: sign * Math.round(abs), unit: 'second' };
        }
        if (abs < HOUR) {
            return { value: sign * Math.round(abs / MINUTE), unit: 'minute' };
        }
        if (abs < DAY) {
            return { value: sign * Math.round(abs / HOUR), unit: 'hour' };
        }
        if (abs < WEEK) {
            return { value: sign * Math.round(abs / DAY), unit: 'day' };
        }
        if (abs < MONTH) {
            return { value: sign * Math.round(abs / WEEK), unit: 'week' };
        }
        if (abs < YEAR) {
            return { value: sign * Math.round(abs / MONTH), unit: 'month' };
        }
        return { value: sign * Math.round(abs / YEAR), unit: 'year' };
    }

    /**
     * Return the refresh cadence in milliseconds appropriate for a timestamp
     * of this age. Recent timestamps refresh every minute; very old ones much
     * less often.
     */
    function refreshIntervalFor(abs) {
        if (abs < HOUR) {
            return 60 * 1000;
        }
        if (abs < DAY) {
            return 15 * 60 * 1000;
        }
        if (abs < WEEK) {
            return 60 * 60 * 1000;
        }
        return 24 * 60 * 60 * 1000;
    }

    function render(el) {
        var attr = el.getAttribute('datetime');
        if (!attr) {
            return;
        }
        var then = Date.parse(attr);
        if (isNaN(then)) {
            return;
        }
        var deltaSeconds = (then - Date.now()) / 1000;
        var pick = pickUnit(deltaSeconds);
        var text = getFormatter().format(pick.value, pick.unit);
        if (el.textContent !== text) {
            el.textContent = text;
        }
        if (!el.title) {
            el.title = attr;
        }
    }

    // --- Auto-tick -----------------------------------------------------------

    var liveElements = [];
    var lastTick = 0;

    function tick() {
        var now = Date.now();
        var minWait = 24 * 60 * 60 * 1000;
        for (var i = 0; i < liveElements.length; i++) {
            var el = liveElements[i];
            if (!el.isConnected) {
                liveElements.splice(i, 1);
                i--;
                continue;
            }
            var then = Date.parse(el.getAttribute('datetime'));
            if (isNaN(then)) {
                continue;
            }
            var abs = Math.abs((then - now) / 1000);
            var interval = refreshIntervalFor(abs);
            if (now - lastTick >= interval) {
                render(el);
            }
            if (interval < minWait) {
                minWait = interval;
            }
        }
        lastTick = now;
        // Re-check at the cadence of the most frequently updating element,
        // capped at one minute so newly inserted elements don't have to wait
        // a full day for the next tick.
        setTimeout(tick, Math.min(minWait, 60 * 1000));
    }

    function register(el) {
        render(el);
        for (var i = 0; i < liveElements.length; i++) {
            if (liveElements[i] === el) {
                return;
            }
        }
        liveElements.push(el);
    }

    // --- Custom element ------------------------------------------------------

    // Define a customized built-in <time is="time-ago"> element. This matches
    // the contract of the old time-elements.js library so existing call sites
    // (templates/dynamic/message.html.php and js/base.js) keep working.
    if (window.customElements && !customElements.get('time-ago')) {
        try {
            var TimeAgo = function () {
                return Reflect.construct(HTMLTimeElement, [], TimeAgo);
            };
            TimeAgo.prototype = Object.create(HTMLTimeElement.prototype);
            TimeAgo.prototype.constructor = TimeAgo;
            TimeAgo.prototype.connectedCallback = function () {
                register(this);
            };
            TimeAgo.prototype.attributeChangedCallback = function (name) {
                if (name === 'datetime') {
                    render(this);
                }
            };
            Object.defineProperty(TimeAgo, 'observedAttributes', {
                get: function () { return ['datetime']; }
            });
            customElements.define('time-ago', TimeAgo, { extends: 'time' });
        } catch (e) {
            // Some older browsers don't support customized built-ins. Fall
            // through to the DOM-scan path below, which still keeps the
            // element labels correct.
        }
    }

    // Sweep any pre-existing <time is="time-ago"> elements that the custom
    // element didn't pick up (either because customElements wasn't available
    // or because the element was inserted before this script ran).
    function sweep() {
        var nodes = document.querySelectorAll('time[is="time-ago"]');
        for (var i = 0; i < nodes.length; i++) {
            register(nodes[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sweep);
    } else {
        sweep();
    }

    // Kick off the refresh loop.
    setTimeout(tick, 60 * 1000);
}());
