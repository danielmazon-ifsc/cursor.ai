/**
 * This class manages the confirmation pop-up (also called the pre-flight check)
 * that is sometimes shown when a use clicks the start attempt button.
 *
 * This is also responsible for opening the pop-up window, if the cquiz requires to be in one.
 *
 * @module    mod_cquiz/preflightcheck
 * @class     preflightcheck
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 * @since     3.1
 */
define(['jquery', 'core/yui'], function($, Y) {

    /**
     * @alias module:mod_cquiz/preflightcheck
     */
    var t = {
        confirmDialogue: null,

        /**
         * Initialise the start attempt button.
         *
         * @param {String} startButtonId the id of the start attempt button that we will be enhancing.
         * @param {String} confirmationTitle the title of the dialogue.
         * @param {String} confirmationForm selector for the confirmation form to show in the dialogue.
         * @param {String} popupoptions If not null, the cquiz should be launced in a pop-up.
         */
        init: function(startButton, confirmationTitle, confirmationForm, popupoptions) {
            var finalStartButton = startButton;

            Y.use('moodle-core-notification', 'moodle-core-formchangechecker', 'io-form', function () {
                if (Y.one(confirmationForm)) {
                    t.confirmDialogue = new M.core.dialogue({
                        headerContent: confirmationTitle,
                        bodyContent: Y.one(confirmationForm),
                        draggable: true,
                        visible: false,
                        center: true,
                        modal: true,
                        width: null,
                        extraClasses: ['mod_cquiz_preflight_popup']
                    });

                    Y.one(startButton).on('click', t.displayDialogue);
                    Y.one('#id_cancel').on('click', t.hideDialogue);

                    finalStartButton = t.confirmDialogue.get('boundingBox').one('[name="submitbutton"]');
                }

                if (popupoptions) {
                    Y.one(finalStartButton).on('click', t.launchcquizPopup, t, popupoptions);
                }
            });
        },

        /**
         * Display the dialogue.
         * @param {Y.EventFacade} e the event being responded to, if any.
         */
        displayDialogue: function(e) {
            if (e) {
                e.halt();
            }
            t.confirmDialogue.show();
        },

        /**
         * Hide the dialogue.
         * @param {Y.EventFacade} e the event being responded to, if any.
         */
        hideDialogue: function(e) {
            if (e) {
                e.halt();
            }
            t.confirmDialogue.hide(e);
        },

        /**
         * Event handler for the cquiz start attempt button.
         */
        launchcquizPopup: function(e, popupoptions) {
            e.halt();
            M.core_formchangechecker.reset_form_dirty_state();
            var form = e.target.ancestor('form');
            window.openpopup(e, {
                url: form.get('action') + '?' + Y.IO.stringify(form).replace(/\bcancel=/, 'x='),
                windowname: 'cquizpopup',
                options: popupoptions,
                fullscreen: true,
            });
        }
    };

    return t;
});
