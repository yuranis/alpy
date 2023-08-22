// Javascript functions for Weeks course format

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format
 *
 * The section structure is:
 * <ul class="weeks">
 *  <li class="section">...</li>
 *  <li class="section">...</li>
 *   ...
 * </ul>
 *
 * @return {object} section list configuration
 */
M.course.format.get_config = function() {
    return {
        container_node : 'ul',
        container_class : 'weeks',
        section_node : 'li',
        section_class : 'section'
    };
}

/**
 * Swap section
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 * @return {NodeList} section list
 */
M.course.format.swap_sections = function(Y, node1, node2) {
    var CSS = {
        COURSECONTENT : 'course-content',
        SECTIONADDMENUS : 'section_add_menus'
    };

    var sectionlist = Y.Node.all('.'+CSS.COURSECONTENT+' '+M.course.format.get_section_selector(Y));
    // Swap the non-ajax menus, noting these are not always present (depends on theme and user prefs).
    if (sectionlist.item(node1).one('.'+CSS.SECTIONADDMENUS)) {
        sectionlist.item(node1).one('.' + CSS.SECTIONADDMENUS).swap(sectionlist.item(node2).one('.' + CSS.SECTIONADDMENUS));
    }
}

/**
 * Process sections after ajax response
 *
 * @param {YUI} Y YUI3 instance
 * @param {NodeList} sectionlist of sections
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 * @return void
 */
M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    var CSS = {
        SECTIONNAME : 'sectionname'
    },
    SELECTORS = {
        SECTIONLEFTSIDE : '.left .section-handle .icon'
    };

    if (response.action == 'move') {
        // If moving up swap around 'sectionfrom' and 'sectionto' so the that loop operates.
        if (sectionfrom > sectionto) {
            var temp = sectionto;
            sectionto = sectionfrom;
            sectionfrom = temp;
        }

        // Update titles and move icons in all affected sections.
        var ele, str, stridx, newstr;

        for (var i = sectionfrom; i <= sectionto; i++) {
            // Update section title.
            var content = Y.Node.create('<span>' + response.sectiontitles[i] + '</span>');
            sectionlist.item(i).all('.'+CSS.SECTIONNAME).setHTML(content);

            // Update the drag handle.
            ele = sectionlist.item(i).one(SELECTORS.SECTIONLEFTSIDE).ancestor('.section-handle');
            str = ele.getAttribute('title');
            stridx = str.lastIndexOf(' ');
            newstr = str.substr(0, stridx +1) + i;
            ele.setAttribute('title', newstr);

            // Remove the current class as section has been moved.
            sectionlist.item(i).removeClass('current');
        }
        // If there is a current section, apply corresponding class in order to highlight it.
        if (response.current !== -1) {
            // Add current class to the required section.
            sectionlist.item(response.current).addClass('current');
        }
    }
}

$(document).ready(function() {
    $('ul').each(function() {
        var mylist = $(this);
        var listitems = mylist.children('li[class^="alpy-"]').get();
        listitems.sort(function(a, b) {
            var compA = parseInt($(a).attr('class').split('-')[1]);
            var compB = parseInt($(b).attr('class').split('-')[1]);
            return (compA > compB) ? -1 : (compA < compB) ? 1 : 0;
        })
        $.each(listitems, function(idx, itm) { mylist.append(itm); });
    });
});

$(document).ready(function() {
    $('li[class^="alpy-"]').each(function() {
        var icon = $(this).find('img.activityicon'); // Buscar el icono dentro del elemento li

        // Obtener todas las clases y buscar la que comienza con "learning-"
        var classes = $(this).attr('class').split(/\s+/);
        var learningValue = null;
        for (var cls of classes) {
            if (cls.startsWith("learning-")) {
                learningValue = cls.split('-')[1];
                break;
            }
        }

        if (learningValue && icon.length > 0) {
            var newUrl = '/blocks/learning_style/pix/' + learningValue.toLowerCase() + '.png';
            icon.attr('src', newUrl); // Cambiar la URL del icono
        }
    });
});

