// Refresh video completion icons when returning to the course page (bfcache / back button).

(function(global) {
    'use strict';

    var config = null;
    var debouncetimer = null;
    var STORAGEKEY = 'saladeconferencias_completed_cmid';

    function iscompletestate(state) {
        // COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL.
        return state === 1 || state === 2 || state === 3;
    }

    function updatecard(cmid) {
        if (!config || !cmid) {
            return;
        }
        var card = document.getElementById('cm-' + cmid);
        if (!card || card.classList.contains('saladeconferencias-video-card--complete')) {
            return;
        }
        card.classList.add('saladeconferencias-video-card--complete');
        var status = card.querySelector('.saladeconferencias-video-card__status');
        if (status) {
            status.className = 'saladeconferencias-video-card__status saladeconferencias-video-card__status--complete';
            status.innerHTML = '<img class="iconsmall saladeconferencias-video-card__check-icon" src="' +
                config.checkiconurl + '" alt="">';
        }
    }

    function applysessioncompletion() {
        try {
            var cmid = sessionStorage.getItem(STORAGEKEY);
            if (cmid) {
                updatecard(parseInt(cmid, 10));
                sessionStorage.removeItem(STORAGEKEY);
            }
        } catch (e) {
            // Ignore storage errors.
        }
    }

    function refreshfromserver() {
        if (!config || !config.courseid) {
            return;
        }
        if (typeof require === 'undefined') {
            return;
        }
        require(['core/ajax'], function(Ajax) {
            var request = {
                methodname: 'core_completion_get_activities_completion_status',
                args: {
                    courseid: config.courseid,
                    userid: config.userid,
                },
            };
            Ajax.call([request])[0].then(function(response) {
                if (!response || !response.statuses) {
                    return;
                }
                response.statuses.forEach(function(item) {
                    if (item.modname === 'video' && iscompletestate(item.state)) {
                        updatecard(item.cmid);
                    }
                });
            }).catch(function() {
                // Silent fail — page still usable without live refresh.
            });
        });
    }

    function refresh() {
        applysessioncompletion();
        refreshfromserver();
    }

    function schedulerefresh() {
        clearTimeout(debouncetimer);
        debouncetimer = setTimeout(refresh, 150);
    }

    function init(cfg) {
        config = cfg;
        applysessioncompletion();

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                schedulerefresh();
            } else {
                applysessioncompletion();
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                schedulerefresh();
            }
        });
    }

    global.M = global.M || {};
    M.format_saladeconferencias = M.format_saladeconferencias || {};
    M.format_saladeconferencias.completion_refresh = { init: init, updatecard: updatecard };
})(this);
