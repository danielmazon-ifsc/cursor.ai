// Javascript functions for Sala de Conferências course format (section drag-and-drop).

M.course = M.course || {};
M.course.format = M.course.format || {};

M.course.format.get_config = function() {
    return {
        container_node: 'div',
        container_class: 'saladeconferencias-sections',
        section_node: 'div',
        section_class: 'saladeconferencias-section'
    };
};

// The legacy YUI dragdrop module (moodle-course-dragdrop) is loaded by
// include_course_ajax() whenever the format does not support components and uses
// sections. Its setup_for_section() expects a Boost/Topics-style DOM with
// "section > .content > div.summary" markers that our custom renderer does not
// emit, so it crashes with "Cannot read properties of null (reading 'insert')".
//
// We don't want or need YUI-driven drag-and-drop for the video card layout, but we
// do want to keep core_course/actions and dnd uploads enabled. Replace the public
// init entry points with non-writable noops so the YUI module's later redefinition
// (M.course.init_section_dragdrop = ...) is silently ignored and the inline
// Y.use(...) bootstrap ends up calling these instead.
(function() {
    var noop = function() {};
    var lock = function(name) {
        try {
            Object.defineProperty(M.course, name, {
                configurable: false,
                enumerable: false,
                get: function() { return noop; },
                // Swallow any later writes from the YUI dragdrop module so its
                // redefinition cannot resurface the broken implementation. This
                // works in both strict and non-strict mode without throwing.
                set: function() { /* intentionally ignored */ }
            });
        } catch (e) {
            M.course[name] = noop;
        }
    };
    lock('init_section_dragdrop');
    lock('init_resource_dragdrop');
})();

M.course.format.swap_sections = function(Y, node1, node2) {
    var CSS = {
        COURSECONTENT: 'saladeconferencias-sections',
        SECTIONADDMENUS: 'section_add_menus'
    };
    var sectionlist = Y.Node.all('.' + CSS.COURSECONTENT + ' .' + M.course.format.get_section_selector(Y).replace('.', ''));
    if (sectionlist && sectionlist.item(node1) && sectionlist.item(node2)) {
        var menu1 = sectionlist.item(node1).one('.' + CSS.SECTIONADDMENUS);
        var menu2 = sectionlist.item(node2).one('.' + CSS.SECTIONADDMENUS);
        if (menu1 && menu2) {
            menu1.swap(menu2);
        }
    }
};

M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    if (response.action !== 'move' || !response.sectiontitles) {
        return;
    }
    if (sectionfrom > sectionto) {
        var temp = sectionto;
        sectionto = sectionfrom;
        sectionfrom = temp;
    }
    for (var i = sectionfrom; i <= sectionto; i++) {
        if (response.sectiontitles[i] && sectionlist.item(i)) {
            var titleNode = sectionlist.item(i).one('.saladeconferencias-section__title');
            if (titleNode) {
                titleNode.setHTML(response.sectiontitles[i]);
            }
        }
    }
};
