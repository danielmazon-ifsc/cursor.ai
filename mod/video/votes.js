/**
 * Relevancy voting controller (no jQuery dependency).
 *
 * @package   mod_video
 * @copyright 2018 Viddia (http://viddia.com.br)
 */
(function() {
    'use strict';

    function vote(action, postId) {
        var wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
        var url = wwwroot + '/mod/socialforum/post.php?' + action + '=' + postId + '&ajax=true';

        fetch(url, {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(result) {
                if (result) {
                    var element = document.getElementById('votes-' + postId);
                    if (element) {
                        element.innerHTML = result;
                    }
                }
            })
            .catch(function() {
                console.log('Error calling: ' + url);
            });
    }

    function init() {
        document.querySelectorAll('.votespack').forEach(function(pack) {
            pack.addEventListener('click', function(event) {
                var icon = event.target.closest('.icon');
                if (!icon || !pack.contains(icon)) {
                    return;
                }
                var id = icon.getAttribute('id');
                if (!id) {
                    return;
                }
                var parts = id.split('-');
                if (parts.length >= 2) {
                    vote(parts[0], parts[1]);
                }
            });

            pack.addEventListener('mouseover', function(event) {
                var icon = event.target.closest('.icon');
                if (icon && pack.contains(icon)) {
                    icon.style.cursor = 'pointer';
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
