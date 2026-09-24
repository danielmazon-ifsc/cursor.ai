/* eslint-env browser */
/* global M */

(function() {
    'use strict';

    function bootstrap() {
        initProfileCard();
        initBackButtons();
        initAutoSubmitForms();
        // Run the interactive-area initialiser whenever such elements exist
        // in the DOM, rather than gating by body id. Some site themes or
        // layouts (e.g. embedded/popup) may not set the conventional
        // page-course-view-specialization id, which previously left the
        // tooltip wiring dead even when the Eixo markup was present.
        if (document.querySelector('.interactive-area')) {
            initInteractiveAreas();
        }
    }

    // $PAGE->requires->js() in format.php emits the <script> tag late in the
    // page (footer region), often AFTER DOMContentLoaded has already fired.
    // In that case a plain addEventListener('DOMContentLoaded', ...) never
    // runs, so the interactive-area tooltip never binds. Detect the current
    // readyState and dispatch accordingly.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    function initProfileCard() {
        var toggleBtn = document.getElementById('toggleProfileCard');
        var profileCardContent = document.getElementById('profileCardContent');
        if (!toggleBtn || !profileCardContent) {
            return;
        }
        var isMinimized = false;
        toggleBtn.addEventListener('click', function() {
            if (isMinimized) {
                profileCardContent.style.opacity = '1';
                profileCardContent.style.maxHeight = '500px';
                setTimeout(function() {
                    toggleBtn.innerHTML = '<i class="icon fa fa-minus"></i>';
                }, 150);
            } else {
                profileCardContent.style.opacity = '0';
                profileCardContent.style.maxHeight = '0';
                setTimeout(function() {
                    toggleBtn.innerHTML = '<i class="icon fa fa-plus"></i>';
                }, 150);
            }
            isMinimized = !isMinimized;
        });
    }

    function initBackButtons() {
        document.querySelectorAll('[data-action="history-back"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                window.history.back();
            });
        });
    }

    function initAutoSubmitForms() {
        document.querySelectorAll('select[data-action="autosubmit"]').forEach(function(select) {
            select.addEventListener('change', function() {
                if (select.value && Number(select.value) !== 0 && select.form) {
                    select.form.submit();
                }
            });
        });
    }

    function disableModalButton(btn) {
        if (!btn) {
            return;
        }
        btn.classList.add('disabled');
        btn.setAttribute('aria-disabled', 'true');
        btn.disabled = true;
    }

    function enableModalButton(btn, url) {
        if (!btn) {
            return;
        }
        btn.classList.remove('disabled');
        btn.removeAttribute('aria-disabled');
        btn.disabled = false;
        if (url) {
            btn.dataset.url = url;
        }
    }

    function initInteractiveAreas() {
        var tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        document.body.appendChild(tooltip);

        var modal = document.getElementById('section-modal');
        if (modal) {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            // Attach modal action buttons via data-action instead of inline onclick.
            modal.querySelectorAll('[data-action="access-content"], '
                + '[data-action="access-evaluation"], '
                + '[data-action="access-secondchance"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.classList.contains('disabled') || btn.disabled) {
                        return;
                    }
                    var url = btn.dataset.url;
                    if (url) {
                        window.location.href = url;
                    }
                    modal.style.display = 'none';
                });
            });
        }

        document.querySelectorAll('.interactive-area').forEach(function(area) {
            if (area.dataset.isediting === '1') {
                return;
            }

            area.addEventListener('mousemove', function(e) {
                var title = area.dataset.tooltip || '';
                if (!title) {
                    tooltip.style.display = 'none';
                    return;
                }
                tooltip.textContent = title;
                // Position the tooltip relative to the current mouse position
                // (using clientX/Y since .custom-tooltip is position:fixed).
                // The previous implementation looked up a container by
                // shortname-derived class, which broke whenever the shortname
                // contained spaces or accents (the wrapper class is sanitised
                // but data-course wasn't), leaving the tooltip parked at 0,0.
                var offset = 12;
                tooltip.style.left = (e.clientX + offset) + 'px';
                tooltip.style.top  = (e.clientY + offset) + 'px';
                tooltip.style.display = 'block';
            });

            area.addEventListener('mouseout', function() {
                tooltip.style.display = 'none';
            });

            area.addEventListener('click', function(event) {
                // The "Avaliação de Reação" mural (.mural-aviso) is now
                // rendered as a child of .interactive-area (it used to be
                // injected by theme/pdi/js/avaliacao.js and is now built
                // server-side by format_specialization renderer). Its
                // anchor has target="_blank", which opens the avaliação
                // page in a new tab — but the click ALSO bubbles up here.
                // Without this guard, the bubbled click would open the
                // section modal (or navigate the current tab via
                // window.location.href below), giving the user a double
                // navigation. Bail out so the anchor's native handling
                // is the only thing that runs.
                if (event.target && event.target.closest
                        && event.target.closest('.mural-aviso')) {
                    return;
                }
                var target = event.currentTarget;
                if (target.classList.contains('locked-area')) {
                    return;
                }
                if (target.dataset.url) {
                    window.location.href = target.dataset.url;
                    return;
                }
                if (!modal) {
                    return;
                }
                var contentUrl       = target.dataset.contenturl;
                var evaluationUrl    = target.dataset.evaluationurl;
                var secondchanceUrl  = target.dataset.secondchanceurl;
                var contentCompleted = target.dataset.contentcompleted;
                var evaluationFailed = target.dataset.evaluationfailed;

                var btnContent      = modal.querySelector('#content');
                var btnEvaluation   = modal.querySelector('#evaluation');
                var btnSecondchance = modal.querySelector('#secondchance');

                if (contentUrl && evaluationUrl && secondchanceUrl) {
                    enableModalButton(btnContent, contentUrl);
                    if (contentCompleted !== '1') {
                        disableModalButton(btnEvaluation);
                        disableModalButton(btnSecondchance);
                    } else {
                        enableModalButton(btnEvaluation, evaluationUrl);
                        if (evaluationFailed !== '1') {
                            disableModalButton(btnSecondchance);
                        } else {
                            enableModalButton(btnSecondchance, secondchanceUrl);
                        }
                    }
                    modal.style.display = 'flex';
                } else if (contentUrl && evaluationUrl) {
                    enableModalButton(btnContent, contentUrl);
                    if (contentCompleted !== '1') {
                        disableModalButton(btnEvaluation);
                    } else {
                        enableModalButton(btnEvaluation, evaluationUrl);
                    }
                    modal.style.display = 'flex';
                } else if (contentUrl) {
                    window.location.href = contentUrl;
                } else if (evaluationUrl) {
                    window.location.href = evaluationUrl;
                }
            });
        });
    }
}());
