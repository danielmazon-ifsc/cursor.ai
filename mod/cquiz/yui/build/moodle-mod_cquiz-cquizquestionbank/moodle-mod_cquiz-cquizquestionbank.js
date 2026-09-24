YUI.add('moodle-mod_cquiz-cquizquestionbank', function (Y, NAME) {

/**
 * Add questions from question bank functionality for a popup in cquiz editing page.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

var CSS = {
        QBANKLOADING:       'div.questionbankloading',
        ADDQUESTIONLINKS:   'ul.menu a.questionbank',
        ADDTOCQUIZCONTAINER: 'td.addtocquizaction',
        PREVIEWCONTAINER:   'td.previewaction',
        SEARCHOPTIONS:      '#advancedsearch'
};

var PARAMS = {
    PAGE: 'addonpage',
    HEADER: 'header'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    loadingDiv: '',
    dialogue: null,
    addonpage: 0,
    searchRegionInitialised: false,

    create_dialogue: function() {
        // Create a dialogue on the page and hide it.
        var config = {
            headerContent : '',
            bodyContent : Y.one(CSS.QBANKLOADING),
            draggable : true,
            modal : true,
            centered: true,
            width: null,
            visible: false,
            postmethod: 'form',
            footerContent: null,
            extraClasses: ['mod_cquiz_qbank_dialogue']
        };
        this.dialogue = new M.core.dialogue(config);
        this.dialogue.bodyNode.delegate('click', this.link_clicked, 'a[href]', this);
        this.dialogue.hide();

        this.loadingDiv = this.dialogue.bodyNode.getHTML();

        Y.later(100, this, function() {this.load_content(window.location.search);});
    },

    initializer : function() {
        if (!Y.one(CSS.QBANKLOADING)) {
            return;
        }
        this.create_dialogue();
        Y.one('body').delegate('click', this.display_dialogue, CSS.ADDQUESTIONLINKS, this);
    },

    display_dialogue : function (e) {
        e.preventDefault();
        this.dialogue.set('headerContent', e.currentTarget.getData(PARAMS.HEADER));

        this.addonpage = e.currentTarget.getData(PARAMS.PAGE);
        var controlsDiv = this.dialogue.bodyNode.one('.modulespecificbuttonscontainer');
        if (controlsDiv) {
            var hidden = controlsDiv.one('input[name=addonpage]');
            if (!hidden) {
                hidden = controlsDiv.appendChild('<input type="hidden" name="addonpage">');
            }
            hidden.set('value', this.addonpage);
        }

        this.initialiseSearchRegion();
        this.dialogue.show();
    },

    load_content : function(queryString) {
        this.dialogue.bodyNode.append(this.loadingDiv);

        // If to support old IE.
        if (window.history.replaceState) {
            window.history.replaceState(null, '', M.cfg.wwwroot + '/mod/cquiz/edit.php' + queryString);
        }

        Y.io(M.cfg.wwwroot + '/mod/cquiz/questionbank.ajax.php' + queryString, {
            method: 'GET',
            on: {
                success: this.load_done,
                failure: this.load_failed
            },
            context: this
        });

    },

    load_done: function(transactionid, response) {
        var result = JSON.parse(response.responseText);
        if (!result.status || result.status !== 'OK') {
            // Because IIS is useless, Moodle can't send proper HTTP response
            // codes, so we have to detect failures manually.
            this.load_failed(transactionid, response);
            return;
        }


        this.dialogue.bodyNode.setHTML(result.contents);
        Y.use('moodle-question-chooser', function() {M.question.init_chooser({});});
        this.dialogue.bodyNode.one('form').delegate('change', this.options_changed, '.searchoptions', this);

        if (this.dialogue.visible) {
            Y.later(0, this.dialogue, this.dialogue.centerDialogue);
        }
        M.question.qbankmanager.init();

        this.searchRegionInitialised = false;
        if (this.dialogue.get('visible')) {
            this.initialiseSearchRegion();
        }

        this.dialogue.fire('widget:contentUpdate');
        // TODO MDL-47602 really, the base class should listen for the even fired
        // on the previous line, and fix things like makeResponsive.
        // However, it does not. So the next two lines are a hack to fix up
        // display issues (e.g. overall scrollbars on the page). Once the base class
        // is fixed, this comment and the following four lines should be deleted.
        if (this.dialogue.get('visible')) {
            this.dialogue.hide();
            this.dialogue.show();
        }
    },

    load_failed: function() {
    },

    link_clicked: function(e) {
        // Add question to cquiz. mofify the URL, then let it work as normal.
        if (e.currentTarget.ancestor(CSS.ADDTOCQUIZCONTAINER)) {
            e.currentTarget.set('href', e.currentTarget.get('href') + '&addonpage=' + this.addonpage);
            return;
        }

        // Question preview. Needs to open in a pop-up.
        if (e.currentTarget.ancestor(CSS.PREVIEWCONTAINER)) {
            window.openpopup(e, {
                url: e.currentTarget.get('href'),
                name: 'questionpreview',
                options: 'height=600,width=800,top=0,left=0,menubar=0,location=0,scrollbars,' +
                         'resizable,toolbar,status,directories=0,fullscreen=0,dependent'
            });
            return;
        }

        // Click on expand/collaspse search-options. Has its own handler.
        // We should not interfere.
        if (e.currentTarget.ancestor(CSS.SEARCHOPTIONS)) {
            return;
        }

        // Anything else means reload the pop-up contents.
        e.preventDefault();
        this.load_content(e.currentTarget.get('search'));
    },

    options_changed: function(e) {
        e.preventDefault();
        this.load_content('?' + Y.IO.stringify(e.currentTarget.get('form')));
    },

    initialiseSearchRegion: function() {
        if (this.searchRegionInitialised === true) {
            return;
        }
        if (!Y.one(CSS.SEARCHOPTIONS)) {
            return;
        }

        M.util.init_collapsible_region(Y, "advancedsearch", "question_bank_advanced_search",
                M.util.get_string('clicktohideshow', 'moodle'));
        this.searchRegionInitialised = true;
    }
});

M.mod_cquiz = M.mod_cquiz || {};
M.mod_cquiz.cquizquestionbank = M.mod_cquiz.cquizquestionbank || {};
M.mod_cquiz.cquizquestionbank.init = function() {
    return new POPUP();
};


}, '@VERSION@', {
    "requires": [
        "base",
        "event",
        "node",
        "io",
        "io-form",
        "yui-later",
        "moodle-question-qbankmanager",
        "moodle-core-notification-dialogue"
    ]
});
