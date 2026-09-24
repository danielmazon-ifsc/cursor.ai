/* eslint-env browser */

(function() {
    'use strict';

    function getModal() {
        return document.getElementById('customGamificationModal');
    }
    function getMini() {
        return document.getElementById('minimizedModal');
    }

    function openGamificationVideo(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        var modal = getModal();
        var mini = getMini();
        if (modal) {
            modal.classList.add('show');
        }
        if (mini) {
            mini.classList.remove('show');
        }
    }

    function closeGamificationVideo() {
        var modal = getModal();
        var mini = getMini();
        if (modal) {
            modal.classList.remove('show');
        }
        if (mini) {
            mini.classList.remove('show');
        }
    }

    function toggleMinimizeModal() {
        var fullModal = getModal();
        var miniModal = getMini();
        if (!fullModal || !miniModal) {
            return;
        }
        if (fullModal.classList.contains('show')) {
            fullModal.classList.remove('show');
            miniModal.classList.add('show');
        } else {
            miniModal.classList.remove('show');
            fullModal.classList.add('show');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var modal = getModal();
        if (modal) {
            modal.classList.remove('show');
        }
        var mini = getMini();
        if (mini) {
            mini.classList.remove('show');
        }
        if (modal) {
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeGamificationVideo();
                }
            });
        }

        document.querySelectorAll('[data-action="gamification-open"]').forEach(function(btn) {
            btn.addEventListener('click', openGamificationVideo);
        });
        document.querySelectorAll('[data-action="gamification-close"]').forEach(function(btn) {
            btn.addEventListener('click', closeGamificationVideo);
        });
        document.querySelectorAll('[data-action="gamification-toggle"]').forEach(function(btn) {
            btn.addEventListener('click', toggleMinimizeModal);
        });
    });

    // The legacy window.* shims used to live here so old onclick="…" markup
    // could still resolve after the data-action refactor. The HTML no longer
    // emits any inline handlers (renderer audit), so leaving the globals
    // around just pollutes the window namespace and hides typos. Removed.
}());
