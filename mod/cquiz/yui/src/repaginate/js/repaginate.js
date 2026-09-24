/**
 * Repaginate functionality for a popup in cquiz editing page.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

var CSS = {
    REPAGINATECONTAINERCLASS: '.rpcontainerclass',
    REPAGINATECOMMAND: '#repaginatecommand'
};

var PARAMS = {
    CMID: 'cmid',
    HEADER: 'header',
    FORM: 'form'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    header: null,
    body: null,

    initializer : function() {
        var rpcontainerclass = Y.one(CSS.REPAGINATECONTAINERCLASS);

        // Set popup header and body.
        this.header = rpcontainerclass.getAttribute(PARAMS.HEADER);
        this.body = rpcontainerclass.getAttribute(PARAMS.FORM);
        Y.one(CSS.REPAGINATECOMMAND).on('click', this.display_dialog, this);
    },

    display_dialog : function (e) {
        e.preventDefault();

        // Configure the popup.
        var config = {
            headerContent : this.header,
            bodyContent : this.body,
            draggable : true,
            modal : true,
            zIndex : 1000,
            context: [CSS.REPAGINATECOMMAND, 'tr', 'br', ['beforeShow']],
            centered: false,
            width: '30em',
            visible: false,
            postmethod: 'form',
            footerContent: null
        };

        var popup = { dialog: null };
        popup.dialog = new M.core.dialogue(config);
        popup.dialog.show();
    }
});

M.mod_cquiz = M.mod_cquiz || {};
M.mod_cquiz.repaginate = M.mod_cquiz.repaginate || {};
M.mod_cquiz.repaginate.init = function() {
    return new POPUP();
};
