/**
 * Add a random question functionality for a popup in cquiz editing page.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

var CSS = {
    RANDOMQUESTIONFORM: 'div.randomquestionformforpopup',
    PAGEHIDDENINPUT: 'input#rform_qpage',
    RANDOMQUESTIONLINKS: 'ul.menu a.addarandomquestion'
};

var PARAMS = {
    PAGE: 'addonpage',
    HEADER: 'header',
    FORM: 'form'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {

    dialogue: function(header) {
        // Create a dialogue on the page and hide it.
        var config = {
            headerContent : header,
            bodyContent : Y.one(CSS.RANDOMQUESTIONFORM),
            draggable : true,
            modal : true,
            zIndex : 1000,
            centered: false,
            width: 'auto',
            visible: false,
            postmethod: 'form',
            footerContent: null
        };
        var popup = { dialog: null };
        popup.dialog = new M.core.dialogue(config);
        popup.dialog.show();
    },

    initializer : function() {
        Y.one('body').delegate('click', this.display_dialogue, CSS.RANDOMQUESTIONLINKS, this);
    },

    display_dialogue : function (e) {
        e.preventDefault();

        Y.one(CSS.RANDOMQUESTIONFORM + ' ' + CSS.PAGEHIDDENINPUT).set('value',
                e.currentTarget.getData(PARAMS.PAGE));

        this.dialogue(e.currentTarget.getData(PARAMS.HEADER));
    }
});

M.mod_cquiz = M.mod_cquiz || {};
M.mod_cquiz.randomquestion = M.mod_cquiz.randomquestion || {};
M.mod_cquiz.randomquestion.init = function() {
    return new POPUP();
};
