/**
 * Marks video activity completion when the player ends or reaches the cue point.
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 */
(function(root) {
    'use strict';
    if (typeof root === 'undefined') {
        root = typeof window !== 'undefined' ? window : this;
    }

    var marked = false;

    function getConfig() {
        if (window.VIDEO_COMPLETION_CFG) {
            return window.VIDEO_COMPLETION_CFG;
        }
        var wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
        var completedEl = document.getElementById('completed');
        var cmidEl = document.getElementById('cmid');
        var sesskeyEl = document.getElementById('sesskey');
        return {
            wwwroot: wwwroot,
            cmid: cmidEl ? cmidEl.value : '',
            sesskey: sesskeyEl ? sesskeyEl.value : '',
            completed: completedEl ? completedEl.value === '1' : false,
            completeimmediately: document.getElementById('completeimmediately') ?
                document.getElementById('completeimmediately').value === '1' : false,
            secondstocomplete: document.getElementById('secondstocomplete') ?
                parseInt(document.getElementById('secondstocomplete').value, 10) : 0,
            isyoutube: false,
            completeonview: false,
        };
    }

    function markCompleted(reload) {
        if (marked) {
            return;
        }
        var cfg = getConfig();
        if (cfg.completed) {
            marked = true;
            return;
        }
        if (!cfg.cmid || !cfg.sesskey) {
            console.error('Completion: missing cmid or sesskey');
            return;
        }
        if (!cfg.wwwroot) {
            console.error('Completion: wwwroot is not defined');
            return;
        }

        marked = true;
        var url = cfg.wwwroot + '/mod/video/togglecompletion.php?fromajax=1&completionstate=1' +
            '&id=' + encodeURIComponent(cfg.cmid) +
            '&sesskey=' + encodeURIComponent(cfg.sesskey);

        fetch(url, {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(body) {
                if (body.trim() !== 'OK') {
                    throw new Error(body.trim() || 'Completion request failed');
                }
                cfg.completed = true;
                try {
                    sessionStorage.setItem('saladeconferencias_completed_cmid', cfg.cmid);
                } catch (e) {
                    // Ignore.
                }
                if (root.M && root.M.format_saladeconferencias && root.M.format_saladeconferencias.completion_refresh &&
                        typeof root.M.format_saladeconferencias.completion_refresh.updatecard === 'function') {
                    root.M.format_saladeconferencias.completion_refresh.updatecard(parseInt(cfg.cmid, 10));
                }
                var completedInput = document.getElementById('completed');
                if (completedInput) {
                    completedInput.value = '1';
                }
                var mark = document.getElementById('completionmark');
                if (mark) {
                    mark.style.display = '';
                }
                if (reload) {
                    window.location.reload();
                }
            })
            .catch(function(err) {
                marked = false;
                console.error('Completion failed:', err);
            });
    }

    function initVimeoPlayer(iframe) {
        if (!iframe || typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined') {
            return false;
        }

        try {
            var player = new Vimeo.Player(iframe);
            var duration = 0;
            var cfg = getConfig();

            player.ready().then(function() {
                if (cfg.completeimmediately) {
                    markCompleted(false);
                    return;
                }
                return player.getDuration().then(function(value) {
                    duration = value;
                    var seconds = cfg.secondstocomplete;
                    if (isNaN(seconds)) {
                        seconds = 0;
                    }
                    if (seconds > 0 && duration > seconds) {
                        var cuepoint = Math.max(0, duration - seconds);
                        return player.addCuePoint(cuepoint).catch(function() {
                            // Ignore cue point errors on short videos.
                        });
                    }
                });
            }).catch(function(err) {
                console.log('Vimeo ready error:', err);
            });

            player.on('cuepoint', function() {
                markCompleted(true);
            });

            player.on('ended', function() {
                markCompleted(true);
            });

            player.on('timeupdate', function(data) {
                if (duration <= 0) {
                    return;
                }
                var seconds = cfg.secondstocomplete;
                if (isNaN(seconds)) {
                    seconds = 0;
                }
                var threshold = Math.max(0, duration - seconds);
                if (data.seconds >= threshold) {
                    markCompleted(true);
                }
            });

            return true;
        } catch (err) {
            console.log('Vimeo player error:', err);
            return false;
        }
    }

    function init() {
        var cfg = getConfig();
        if (cfg.completed || cfg.completeonview) {
            marked = true;
            return;
        }
        if (cfg.completeimmediately) {
            markCompleted(false);
            return;
        }

        var iframe = document.querySelector('#videoplayer iframe, .js-player iframe, iframe');
        if (!iframe) {
            return;
        }

        if (iframe.src.indexOf('youtube.com') !== -1 || iframe.src.indexOf('youtu.be') !== -1) {
            return;
        }
        if (iframe.src.indexOf('vimeo.com') !== -1) {
            initVimeoPlayer(iframe);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    root.videoCompletionReinit = function() {
        marked = false;
        init();
    };
})(typeof window !== 'undefined' ? window : this);
