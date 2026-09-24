// Inline featured video player for Sala de Conferências course format.

(function(global) {
    'use strict';

    var config = null;
    var loadingcmid = 0;
    var loadRequestId = 0;
    var pendingEmbedPlayCmid = 0;

    function fetchLoadVideo(params) {
        var endpoint = config.wwwroot + '/course/format/saladeconferencias/loadvideo.php';
        var autoplay = params.autoplay === '1' || params.autoplay === 1;
        if (autoplay) {
            var body = new URLSearchParams();
            Object.keys(params).forEach(function(key) {
                body.append(key, String(params[key]));
            });
            return fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            });
        }
        var qs = Object.keys(params).map(function(key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');
        return fetch(endpoint + '?' + qs, {credentials: 'same-origin'});
    }

    function getFeaturedEl() {
        return document.getElementById('saladeconferencias-currentvideo');
    }

    function getPlayerWrap(featured) {
        return featured ? featured.querySelector('.saladeconferencias-featured__player') : null;
    }

    function isPosterVisible(featured) {
        var poster = featured.querySelector('.saladeconferencias-featured__poster');
        return poster && poster.style.display !== 'none';
    }

    function usesPoster(featured) {
        return featured && featured.dataset.usePoster === '1';
    }

    function getPosterFromCard(cmid) {
        var card = document.getElementById('cm-' + cmid);
        var thumb = card && card.querySelector('.saladeconferencias-video-card__thumb img');
        return thumb ? thumb.src : '';
    }

    function setActiveCard(cmid) {
        document.querySelectorAll('.saladeconferencias-video-card--active').forEach(function(card) {
            card.classList.remove('saladeconferencias-video-card--active');
        });
        var card = document.getElementById('cm-' + cmid);
        if (card) {
            card.classList.add('saladeconferencias-video-card--active');
        }
    }

    function updateCompletionCard(cmid) {
        if (global.M && M.format_saladeconferencias && M.format_saladeconferencias.completion_refresh &&
                typeof M.format_saladeconferencias.completion_refresh.updatecard === 'function') {
            M.format_saladeconferencias.completion_refresh.updatecard(cmid);
            return;
        }
        try {
            sessionStorage.setItem('saladeconferencias_completed_cmid', String(cmid));
        } catch (e) {
            // Ignore.
        }
    }

    function applyCompletionConfig(completion) {
        global.VIDEO_COMPLETION_CFG = completion;
        if (typeof global.videoCompletionReinit === 'function') {
            global.videoCompletionReinit();
        }
    }

    function bindCaptions(scope) {
        if (typeof global.modVideoCaptionsInit === 'function') {
            global.modVideoCaptionsInit(scope || document);
        }
    }

    function updateUrl(cmid) {
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('video', String(cmid));
            window.history.replaceState({video: cmid}, '', url.toString());
        } catch (e) {
            // Ignore.
        }
    }

    function stopMediaIn(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('video').forEach(function(video) {
            try {
                video.pause();
            } catch (e) {
                // Ignore.
            }
        });
        container.querySelectorAll('iframe').forEach(function(iframe) {
            try {
                iframe.src = 'about:blank';
            } catch (e) {
                // Ignore.
            }
        });
    }

    function resetPlayerWrap(playerWrap) {
        if (!playerWrap) {
            return;
        }
        stopMediaIn(playerWrap);
        playerWrap.innerHTML = '';
        playerWrap.classList.remove('saladeconferencias-featured__player--loading');
    }

    function isEmbedCard(cmid) {
        var link = document.querySelector('.saladeconferencias-video-card__link[data-cmid="' + cmid + '"]');
        return !!(link && link.getAttribute('data-use-poster') === '1');
    }

    function updateFeaturedTitleFromCard(featured, cmid) {
        var card = document.getElementById('cm-' + cmid);
        var cardTitle = card && card.querySelector('.saladeconferencias-video-card__title');
        var title = featured.querySelector('.saladeconferencias-featured__video-title');
        if (cardTitle && title) {
            title.textContent = cardTitle.textContent;
        }
    }

    function showOptimisticPoster(featured, cmid) {
        var playerWrap = getPlayerWrap(featured);
        if (!playerWrap) {
            return;
        }
        featured.dataset.usePoster = '1';
        resetPlayerWrap(playerWrap);
        playerWrap.appendChild(buildPosterElement(getPosterFromCard(cmid), cmid));
        var inner = document.createElement('div');
        inner.className = 'saladeconferencias-featured__player-inner';
        inner.style.display = 'none';
        playerWrap.appendChild(inner);
    }

    function buildPosterElement(posterUrl, cmid) {
        if (!posterUrl && cmid) {
            posterUrl = getPosterFromCard(cmid);
        }
        var poster = document.createElement('div');
        poster.className = 'saladeconferencias-featured__poster';
        poster.innerHTML = '<img class="saladeconferencias-featured__poster-img" alt="">' +
            '<button type="button" class="saladeconferencias-featured__play-btn" aria-label="Play"></button>';
        var img = poster.querySelector('.saladeconferencias-featured__poster-img');
        if (img && posterUrl) {
            img.src = posterUrl;
        }
        return poster;
    }

    function revealEmbedPlayer(playerWrap) {
        var poster = playerWrap.querySelector('.saladeconferencias-featured__poster');
        var inner = playerWrap.querySelector('.saladeconferencias-featured__player-inner');
        if (poster) {
            poster.style.display = 'none';
        }
        if (inner) {
            inner.style.display = '';
        }
        return inner;
    }

    function getDirectMediaUrl(url) {
        if (!url || url === 'about:blank') {
            return '';
        }
        var href = url;
        try {
            href = new URL(url, window.location.href).href;
        } catch (e) {
            // Keep original.
        }
        if (/\.(mp4|webm|ogg|m4v|mov)(\?|#|$)/i.test(href)) {
            return href;
        }
        return '';
    }

    function getMediaTypeFromUrl(url) {
        var match = url.match(/\.(mp4|webm|ogg|m4v|mov)(\?|#|$)/i);
        if (!match) {
            return 'video/mp4';
        }
        var ext = match[1].toLowerCase();
        if (ext === 'mov' || ext === 'm4v') {
            return 'video/mp4';
        }
        return 'video/' + ext;
    }

    function cleanMediaUrl(url) {
        return url
            .replace(/([?&])autoplay=(?:1|true)(?=&|$)/gi, function(match, sep) {
                return sep === '?' ? '?' : sep;
            })
            .replace(/\?&/g, '?')
            .replace(/[?&]$/, '');
    }

    function playHtml5Video(video) {
        if (!video) {
            return;
        }
        video.controls = true;
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');

        if (!video.currentSrc && !video.getAttribute('src')) {
            var sourceEl = video.querySelector('source[src]');
            if (sourceEl) {
                video.src = cleanMediaUrl(sourceEl.getAttribute('src') || sourceEl.src);
            }
        }

        var attemptPlay = function() {
            var promise = video.play();
            if (promise && typeof promise.catch === 'function') {
                promise.catch(function() {
                    // Ignore autoplay restrictions.
                });
            }
        };

        if (video.readyState >= 2) {
            attemptPlay();
            return;
        }

        var onReady = function() {
            video.removeEventListener('canplay', onReady);
            video.removeEventListener('loadeddata', onReady);
            attemptPlay();
        };
        video.addEventListener('canplay', onReady);
        video.addEventListener('loadeddata', onReady);
        try {
            video.load();
        } catch (e) {
            // Ignore.
        }
    }

    function replaceIframeWithNativeVideo(container) {
        var iframe = container.querySelector('iframe');
        if (!iframe) {
            return null;
        }
        var mediaUrl = getDirectMediaUrl(iframe.getAttribute('src') || iframe.src || '');
        if (!mediaUrl) {
            return null;
        }

        var video = document.createElement('video');
        video.className = 'saladeconferencias-featured__native-video';
        video.setAttribute('controls', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('preload', 'auto');

        var source = document.createElement('source');
        source.src = cleanMediaUrl(mediaUrl);
        source.type = getMediaTypeFromUrl(mediaUrl);
        video.appendChild(source);

        var host = iframe.closest('.js-videowrapper') || iframe.closest('.js-player') || iframe.parentElement;
        if (host) {
            host.innerHTML = '';
            host.appendChild(video);
        }
        return video;
    }

    function prepareEmbedForPlayback(container) {
        if (!container) {
            return;
        }
        if (container.querySelector('video')) {
            return;
        }
        replaceIframeWithNativeVideo(container);
    }

    function applyIframeAutoplay(iframe) {
        var src = iframe.getAttribute('src') || iframe.src || '';
        if (!src || src === 'about:blank' || getDirectMediaUrl(src)) {
            return;
        }
        if (src.indexOf('youtube.com') !== -1 && src.indexOf('enablejsapi=1') !== -1) {
            try {
                iframe.contentWindow.postMessage(JSON.stringify({
                    event: 'command',
                    func: 'playVideo',
                    args: ''
                }), '*');
            } catch (e) {
                // Ignore.
            }
        }
        var newSrc = src;
        if (newSrc.indexOf('autoplay=1') === -1 && newSrc.indexOf('autoplay=true') === -1) {
            var sep = newSrc.indexOf('?') === -1 ? '?' : '&';
            newSrc = newSrc + sep + 'autoplay=1';
        }
        if (iframe.src !== newSrc) {
            iframe.src = newSrc;
            return;
        }
        iframe.src = 'about:blank';
        iframe.src = newSrc;
    }

    function startAutoplay(container) {
        if (!container) {
            return;
        }

        prepareEmbedForPlayback(container);

        var videos = container.querySelectorAll('video');
        if (videos.length) {
            videos.forEach(function(video) {
                playHtml5Video(video);
            });
            return;
        }

        var iframe = container.querySelector('iframe');
        if (!iframe) {
            return;
        }

        var iframeSrc = iframe.getAttribute('src') || iframe.src || '';
        if (getDirectMediaUrl(iframeSrc)) {
            var converted = replaceIframeWithNativeVideo(container);
            if (converted) {
                playHtml5Video(converted);
            }
            return;
        }

        if (iframeSrc.indexOf('vimeo.com') !== -1 && typeof Vimeo !== 'undefined' && Vimeo.Player) {
            try {
                new Vimeo.Player(iframe).play().catch(function() {
                    // Ignore.
                });
            } catch (e) {
                // Ignore.
            }
            return;
        }

        applyIframeAutoplay(iframe);
    }

    function runEmbedPlayback(inner, cmid) {
        if (!inner) {
            return;
        }
        startAutoplay(inner);
        window.setTimeout(function() {
            markEmbedView(cmid);
        }, 0);
        window.requestAnimationFrame(function() {
            startAutoplay(inner);
        });
        window.setTimeout(function() {
            startAutoplay(inner);
            if (typeof global.videoCompletionReinit === 'function') {
                global.videoCompletionReinit();
            }
        }, 400);
    }

    function playEmbedVideo(cmid) {
        var featured = getFeaturedEl();
        var playerWrap = getPlayerWrap(featured);
        if (!featured || !playerWrap) {
            return;
        }

        var inner = playerWrap.querySelector('.saladeconferencias-featured__player-inner');
        if (inner && inner.innerHTML.trim()) {
            revealEmbedPlayer(playerWrap);
            runEmbedPlayback(inner, cmid);
            featured.scrollIntoView({behavior: 'smooth', block: 'start'});
            return;
        }

        if (loadingcmid === cmid) {
            pendingEmbedPlayCmid = cmid;
            return;
        }

        pendingEmbedPlayCmid = cmid;
        loadVideo(cmid, true);
    }

    function finalizeFeaturedUpdate(featured, data) {
        featured.dataset.cmid = String(data.cmid);

        var title = featured.querySelector('.saladeconferencias-featured__video-title');
        if (title) {
            title.textContent = data.title;
        }

        applyCompletionConfig(data.completion);
        setActiveCard(data.cmid);
        updateUrl(data.cmid);

        if (data.completed) {
            updateCompletionCard(data.cmid);
        }

        featured.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function markEmbedView(cmid) {
        if (!config) {
            return;
        }
        fetchLoadVideo({
            cmid: cmid,
            autoplay: '1',
            completiononly: '1',
            sesskey: config.sesskey
        })
            .then(function(response) {
                return response.ok ? response.json() : null;
            })
            .then(function(data) {
                if (!data) {
                    return;
                }
                if (data.completion) {
                    applyCompletionConfig(data.completion);
                }
                if (data.completed) {
                    updateCompletionCard(cmid);
                }
            })
            .catch(function() {
                // Ignore.
            });
    }

    function activatePlayerDirect(featured, data) {
        var playerWrap = getPlayerWrap(featured);
        if (!playerWrap) {
            return;
        }

        featured.dataset.usePoster = '0';
        resetPlayerWrap(playerWrap);
        playerWrap.insertAdjacentHTML('beforeend', data.player);
        bindCaptions(playerWrap);

        finalizeFeaturedUpdate(featured, data);

        if (data.autoplay) {
            window.setTimeout(function() {
                startAutoplay(playerWrap);
                if (typeof global.videoCompletionReinit === 'function') {
                    global.videoCompletionReinit();
                }
            }, 150);
        }
    }

    function activatePlayer(featured, data) {
        var playerWrap = getPlayerWrap(featured);
        if (!playerWrap) {
            return;
        }

        featured.dataset.usePoster = '1';
        resetPlayerWrap(playerWrap);

        playerWrap.appendChild(buildPosterElement(data.poster, data.cmid));

        var inner = document.createElement('div');
        inner.className = 'saladeconferencias-featured__player-inner';
        inner.innerHTML = data.player;
        playerWrap.appendChild(inner);
        bindCaptions(inner);

        var poster = playerWrap.querySelector('.saladeconferencias-featured__poster');
        if (poster) {
            poster.style.display = 'none';
        }
        inner.style.display = '';

        finalizeFeaturedUpdate(featured, data);

        if (data.autoplay) {
            window.setTimeout(function() {
                startAutoplay(inner);
                if (typeof global.videoCompletionReinit === 'function') {
                    global.videoCompletionReinit();
                }
            }, 150);
        }
    }

    function showPosterOnly(featured, data) {
        var playerWrap = getPlayerWrap(featured);
        if (!playerWrap) {
            return;
        }

        featured.dataset.usePoster = '1';
        resetPlayerWrap(playerWrap);

        playerWrap.appendChild(buildPosterElement(data.poster, data.cmid));

        var inner = document.createElement('div');
        inner.className = 'saladeconferencias-featured__player-inner';
        inner.style.display = 'none';
        if (data.player) {
            inner.innerHTML = data.player;
            bindCaptions(inner);
        }
        playerWrap.appendChild(inner);

        featured.dataset.cmid = String(data.cmid);

        var title = featured.querySelector('.saladeconferencias-featured__video-title');
        if (title) {
            title.textContent = data.title;
        }
        setActiveCard(data.cmid);
        updateUrl(data.cmid);
        featured.scrollIntoView({behavior: 'smooth', block: 'start'});

        if (pendingEmbedPlayCmid === data.cmid) {
            pendingEmbedPlayCmid = 0;
            window.requestAnimationFrame(function() {
                playEmbedVideo(data.cmid);
            });
        }
    }

    function showLoading(featured) {
        var playerWrap = getPlayerWrap(featured);
        if (playerWrap) {
            playerWrap.classList.add('saladeconferencias-featured__player--loading');
        }
    }

    function hideLoading(featured) {
        var playerWrap = getPlayerWrap(featured);
        if (playerWrap) {
            playerWrap.classList.remove('saladeconferencias-featured__player--loading');
        }
    }

    function loadVideo(cmid, autoplay) {
        if (!config || !cmid) {
            return;
        }

        var featured = getFeaturedEl();
        if (!featured) {
            return;
        }

        var current = parseInt(featured.dataset.cmid || '0', 10);
        var playerWrap = getPlayerWrap(featured);
        if (loadingcmid === cmid) {
            if (autoplay) {
                pendingEmbedPlayCmid = cmid;
            }
            return;
        }
        if (current === cmid && usesPoster(featured) && isPosterVisible(featured)) {
            var preloadedInner = playerWrap && playerWrap.querySelector('.saladeconferencias-featured__player-inner');
            if (preloadedInner && preloadedInner.innerHTML.trim()) {
                playEmbedVideo(cmid);
                return;
            }
        }
        if (current === cmid && !usesPoster(featured)) {
            startAutoplay(getPlayerWrap(featured));
            featured.scrollIntoView({behavior: 'smooth', block: 'start'});
            return;
        }
        if (current === cmid && !autoplay) {
            featured.scrollIntoView({behavior: 'smooth', block: 'start'});
            return;
        }

        if (pendingEmbedPlayCmid && pendingEmbedPlayCmid !== cmid) {
            pendingEmbedPlayCmid = 0;
        }

        var requestId = ++loadRequestId;
        loadingcmid = cmid;

        featured.dataset.cmid = String(cmid);
        setActiveCard(cmid);
        updateFeaturedTitleFromCard(featured, cmid);
        updateUrl(cmid);

        if (isEmbedCard(cmid) && current !== cmid) {
            showOptimisticPoster(featured, cmid);
        }

        showLoading(featured);

        fetchLoadVideo({
            cmid: cmid,
            autoplay: autoplay ? '1' : '0',
            sesskey: config.sesskey
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (requestId !== loadRequestId) {
                    return;
                }
                if (!data || !data.player) {
                    throw new Error('Invalid response');
                }
                data.autoplay = autoplay;
                if (data.useposter) {
                    if (autoplay) {
                        activatePlayer(featured, data);
                    } else {
                        showPosterOnly(featured, data);
                    }
                } else {
                    activatePlayerDirect(featured, data);
                }
            })
            .catch(function(err) {
                if (requestId === loadRequestId) {
                    if (pendingEmbedPlayCmid === cmid) {
                        pendingEmbedPlayCmid = 0;
                    }
                    console.error('Failed to load video:', err);
                }
            })
            .finally(function() {
                if (requestId === loadRequestId) {
                    loadingcmid = 0;
                    hideLoading(featured);
                }
            });
    }

    function onCardClick(event) {
        var link = event.target.closest('.saladeconferencias-video-card__link[data-cmid]');
        if (!link || link.classList.contains('saladeconferencias-video-card__link--disabled')) {
            return;
        }
        event.preventDefault();
        var cmid = parseInt(link.getAttribute('data-cmid'), 10);
        if (!cmid) {
            return;
        }
        var cardUsesPoster = link.getAttribute('data-use-poster') === '1';
        loadVideo(cmid, !cardUsesPoster);
    }

    function onPlayerClick(event) {
        var featured = getFeaturedEl();
        if (!featured || !usesPoster(featured)) {
            return;
        }
        if (!event.target.closest('.saladeconferencias-featured__play-btn')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        var cmid = parseInt(featured.dataset.cmid || '0', 10);
        if (cmid) {
            playEmbedVideo(cmid);
        }
    }

    function init(cfg) {
        config = cfg;
        document.addEventListener('click', onCardClick);
        document.addEventListener('click', onPlayerClick);

        var featured = getFeaturedEl();
        if (featured && featured.dataset.cmid) {
            setActiveCard(parseInt(featured.dataset.cmid, 10));
            if (!usesPoster(featured) && global.VIDEO_COMPLETION_CFG) {
                applyCompletionConfig(global.VIDEO_COMPLETION_CFG);
            }
            bindCaptions(featured);
        }

        if (cfg.videoparam && featured && parseInt(featured.dataset.cmid || '0', 10) !== cfg.videoparam) {
            loadVideo(cfg.videoparam, false);
        }
    }

    global.M = global.M || {};
    M.format_saladeconferencias = M.format_saladeconferencias || {};
    M.format_saladeconferencias.featured_player = { init: init, loadVideo: loadVideo };
})(this);
