/**
 * WebVTT captions for mod_video players (HTML5 track + Vimeo/YouTube overlay).
 *
 * @package   mod_video
 */
(function(global) {
    'use strict';

    var cueCache = {};

    // Percentage of the video width reserved on the right so native captions do
    // not overlap the Libras (sign-language) interpreter shown in that corner.
    var CAPTION_RIGHT_RESERVE = 16;

    // Vertical position of the caption box as a percentage of the video height
    // (0 = top, 100 = bottom). Higher values push the captions further down.
    var CAPTION_LINE = 92;

    function parseTimestamp(value) {
        var parts = String(value).trim().split(':');
        if (parts.length < 2) {
            return 0;
        }
        var hours = 0;
        var minutes = 0;
        var seconds = 0;
        if (parts.length === 3) {
            hours = parseFloat(parts[0]) || 0;
            minutes = parseFloat(parts[1]) || 0;
            seconds = parseFloat(parts[2].replace(',', '.')) || 0;
        } else {
            minutes = parseFloat(parts[0]) || 0;
            seconds = parseFloat(parts[1].replace(',', '.')) || 0;
        }
        return (hours * 3600) + (minutes * 60) + seconds;
    }

    function parseVtt(text) {
        var cues = [];
        var normalized = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var blocks = normalized.split(/\n\n+/);
        for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i].trim();
            if (!block || block.indexOf('WEBVTT') === 0 || block.indexOf('NOTE') === 0) {
                continue;
            }
            var lines = block.split('\n');
            var timingLine = lines[0];
            var textStart = 1;
            if (timingLine.indexOf('-->') === -1 && lines.length > 1) {
                timingLine = lines[1];
                textStart = 2;
            }
            if (timingLine.indexOf('-->') === -1) {
                continue;
            }
            var times = timingLine.split('-->');
            if (times.length < 2) {
                continue;
            }
            var start = parseTimestamp(times[0]);
            var end = parseTimestamp(times[1].split(/\s+/)[0]);
            var cueText = lines.slice(textStart).join('\n').replace(/<\/?[^>]+>/g, '').trim();
            if (!cueText) {
                continue;
            }
            cues.push({start: start, end: end, text: cueText});
        }
        return cues;
    }

    function loadCues(url) {
        if (cueCache[url]) {
            return cueCache[url];
        }
        cueCache[url] = fetch(url, {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Caption HTTP ' + response.status);
                }
                return response.text();
            })
            .then(parseVtt)
            .catch(function() {
                return [];
            });
        return cueCache[url];
    }

    function cueAt(cues, time) {
        for (var i = 0; i < cues.length; i++) {
            if (time >= cues[i].start && time < cues[i].end) {
                return cues[i].text;
            }
        }
        return '';
    }

    function setOverlay(overlay, text, enabled) {
        if (!overlay) {
            return;
        }
        if (!enabled || !text) {
            overlay.setAttribute('hidden', 'hidden');
            overlay.textContent = '';
            return;
        }
        overlay.removeAttribute('hidden');
        overlay.textContent = text;
    }

    function findVimeoIframe(root) {
        var iframes = root.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            var src = iframes[i].getAttribute('src') || '';
            if (src.indexOf('vimeo.com') !== -1 || src.indexOf('player.vimeo.com') !== -1) {
                return iframes[i];
            }
        }
        return null;
    }

    function findYoutubeIframe(root) {
        var iframes = root.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            var src = iframes[i].getAttribute('src') || '';
            if (src.indexOf('youtube.com') !== -1 || src.indexOf('youtu.be') !== -1) {
                return iframes[i];
            }
        }
        return null;
    }

    // Shrink the native cue box and shift it left so it clears the reserved
    // right-hand strip. Native captions ignore CSS width/margins, so the box
    // must be constrained through the cue's own size/position/align settings.
    function constrainCues(track) {
        if (!track || !track.cues) {
            return;
        }
        var size = 100 - CAPTION_RIGHT_RESERVE;
        for (var i = 0; i < track.cues.length; i++) {
            var cue = track.cues[i];
            try {
                cue.size = size;
                cue.align = 'center';
                cue.position = size / 2;
                cue.snapToLines = false;
                cue.line = CAPTION_LINE;
            } catch (e) {
                // Older engines may reject some cue settings; ignore.
            }
        }
    }

    function constrainNativeTrack(root) {
        var trackEl = root.querySelector('track');
        var trackObj = trackEl ? trackEl.track : null;
        if (!trackObj) {
            return;
        }
        var apply = function() {
            constrainCues(trackObj);
        };
        if (trackObj.cues && trackObj.cues.length) {
            apply();
        }
        if (trackEl) {
            trackEl.addEventListener('load', apply);
        }
        trackObj.addEventListener('cuechange', apply);
    }

    function syncToggle(toggle, enabled) {
        if (!toggle) {
            return;
        }
        toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        toggle.classList.toggle('is-off', !enabled);
    }

    function bindHtml5(root, video, cues, overlay, toggle) {
        // Captions start off; the learner turns them on with the CC button.
        var enabled = false;
        var tracks = video.textTracks;
        var useNative = tracks && tracks.length > 0;

        if (useNative) {
            for (var i = 0; i < tracks.length; i++) {
                tracks[i].mode = 'disabled';
            }
            overlay.setAttribute('hidden', 'hidden');
            constrainNativeTrack(root);
        }
        syncToggle(toggle, enabled);

        function onTime() {
            if (useNative) {
                return;
            }
            setOverlay(overlay, cueAt(cues, video.currentTime || 0), enabled);
        }

        video.addEventListener('timeupdate', onTime);
        if (toggle) {
            toggle.addEventListener('click', function() {
                enabled = !enabled;
                syncToggle(toggle, enabled);
                if (useNative && tracks) {
                    for (var t = 0; t < tracks.length; t++) {
                        tracks[t].mode = enabled ? 'showing' : 'disabled';
                    }
                } else {
                    onTime();
                }
            });
        }
    }

    function bindVimeo(root, iframe, cues, overlay, toggle) {
        if (typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined') {
            return;
        }
        var enabled = false;
        var player = new Vimeo.Player(iframe);
        syncToggle(toggle, enabled);

        function onTime(data) {
            var seconds = data && typeof data.seconds === 'number' ? data.seconds : 0;
            setOverlay(overlay, cueAt(cues, seconds), enabled);
        }

        player.on('timeupdate', onTime);
        if (toggle) {
            toggle.addEventListener('click', function() {
                enabled = !enabled;
                syncToggle(toggle, enabled);
                if (!enabled) {
                    setOverlay(overlay, '', false);
                }
            });
        }
    }

    function bindYoutube(root, iframe, cues, overlay, toggle) {
        // Best-effort: poll currentTime via postMessage is unreliable without YT API id.
        // Keep overlay available; sync only if YT.Player exists on the iframe.
        if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
            return;
        }
        var enabled = false;
        var player;
        try {
            player = YT.get(iframe.id) || new YT.Player(iframe);
        } catch (e) {
            return;
        }
        var timer = null;
        syncToggle(toggle, enabled);

        function tick() {
            if (!enabled || !player || typeof player.getCurrentTime !== 'function') {
                return;
            }
            setOverlay(overlay, cueAt(cues, player.getCurrentTime() || 0), enabled);
        }

        timer = global.setInterval(tick, 250);
        root._modVideoCaptionTimer = timer;

        if (toggle) {
            toggle.addEventListener('click', function() {
                enabled = !enabled;
                syncToggle(toggle, enabled);
                if (!enabled) {
                    setOverlay(overlay, '', false);
                }
            });
        }
    }

    function initContainer(root) {
        if (!root || root.getAttribute('data-caption-bound') === '1') {
            return;
        }
        var url = root.getAttribute('data-caption-url');
        if (!url) {
            return;
        }
        root.setAttribute('data-caption-bound', '1');
        var overlay = root.querySelector('.mod-video-captions');
        var toggle = root.querySelector('.mod-video-captions-toggle');
        var video = root.querySelector('video');

        loadCues(url).then(function(cues) {
            if (!cues.length && !video) {
                return;
            }
            if (video) {
                bindHtml5(root, video, cues, overlay, toggle);
                return;
            }
            var vimeo = findVimeoIframe(root);
            if (vimeo) {
                bindVimeo(root, vimeo, cues, overlay, toggle);
                return;
            }
            var youtube = findYoutubeIframe(root);
            if (youtube) {
                bindYoutube(root, youtube, cues, overlay, toggle);
            }
        });
    }

    function init(scope) {
        var root = scope || document;
        if (root.classList && root.classList.contains('mod-video-player')) {
            initContainer(root);
            return;
        }
        var nodes = root.querySelectorAll ? root.querySelectorAll('.mod-video-player[data-caption-url]') : [];
        for (var i = 0; i < nodes.length; i++) {
            initContainer(nodes[i]);
        }
    }

    global.modVideoCaptionsInit = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init(document);
        });
    } else {
        init(document);
    }
})(typeof window !== 'undefined' ? window : this);
