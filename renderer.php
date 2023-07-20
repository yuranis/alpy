<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer for outputting the alpy course format.
 *
 * @package format_alpy
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/alpy/lib.php');

/**
 * Basic renderer for weeks format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_alpy_renderer extends format_section_renderer_base
{
    /**
     * Generate the starting container html for a list of sections
     *
     * @return string HTML to output.
     */
    protected function start_section_list()
    {
        return html_writer::start_tag('ul', array('class' => 'weeks'));
    }

    /**
     * Generate the closing container html for a list of sections
     *
     * @return string HTML to output.
     */
    protected function end_section_list()
    {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     *
     * @return string the page title
     */
    protected function page_title()
    {
        return get_string('weeklyoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course)
    {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course)
    {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused)
    {

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    // YURY
                    echo $this->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }
    }

    /**
     * Renders HTML to display a list of course modules in a course section
     * Also displays "move here" controls in Javascript-disabled mode
     *
     * This function calls {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course course object
     * @param int|stdClass|section_info $section relative section number or section object
     * @param int $sectionreturn section number to return to
     * @param int $displayoptions
     * @return string of course module html
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = array())
    {
        global $USER;

        $output = '';
        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of modules visible to user (excluding the module being moved if there is one)
        $moduleshtml = array();
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                if ($modulehtml = $this->course_section_cm_list_item(
                    $course,
                    $completioninfo,
                    $mod,
                    $sectionreturn,
                    $displayoptions
                )) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($moduleshtml) || $ismoving) {
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', array('moveto' => $modnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag(
                        'li',
                        html_writer::link($movingurl, '', array('title' => $strmovefull, 'class' => 'movehere')),
                        array('class' => 'movehere')
                    );
                }

                $sectionoutput .= $modulehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag(
                    'li',
                    html_writer::link($movingurl, '', array('title' => $strmovefull, 'class' => 'movehere')),
                    array('class' => 'movehere')
                );
            }
        }

        // Always output the section module list.
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        return $output;
    }

    /**
     * Renders HTML to display one course module for display within a section.
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return String
     */
    public function course_section_cm_list_item(
        $course,
        &$completioninfo,
        cm_info $mod,
        $sectionreturn,
        $displayoptions = array()
    ) {
        $output = '';
        if ($modulehtml = $this->course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {
            $infoclass = '';
            if ((($course->enablecompletion == COMPLETION_ENABLED) &&
                    ($course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS)) ||
                !empty($course->showactivitydates)
            ) {
                // This will apply styles to the course homepage when the activity information output component is displayed.
                $infoclass = 'hasinfo';
            }
            $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses . ' ' . $infoclass;
            $output .= html_writer::tag('li', $modulehtml, array('class' => $modclasses, 'id' => 'module-' . $mod->id));
        }
        return $output;
    }

    /**
     * Renders HTML to display one course module in a course section
     *
     * This includes link, content, availability, completion info and additional information
     * that module type wants to display (i.e. number of unread forum posts)
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm_name()}
     * {@link core_course_renderer::course_section_cm_text()}
     * {@link core_course_renderer::course_section_cm_availability()}
     * {@link course_get_cm_edit_actions()}
     * {@link core_course_renderer::course_section_cm_edit_actions()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array())
    {
        global $USER, $DB, $COURSE;

        $output = '';
        // We return empty string (because course module will not be displayed at all)
        // if:
        // 1) The activity is not visible to users
        // and
        // 2) The 'availableinfo' is empty, i.e. the activity was
        //     hidden in a way that leaves no info, such as using the
        //     eye icon.
        if (!$mod->is_visible_on_course_page()) {
            return $output;
        }

        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-' . $mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        if ($this->page->user_is_editing()) {
            $output .= course_get_cm_move($mod, $sectionreturn);
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer w-100'));

        // This div is used to indent the content.
        $output .= html_writer::div('', $indentclasses);

        // Start a wrapper for the actual content to keep the indentation consistent
        $output .= html_writer::start_tag('div');

        // Display the link to the module (or do nothing if module has no url)
        $cmname = $this->course_section_cm_name($mod, $displayoptions);

        if (!empty($cmname)) {
            // Start the div for the activity title, excluding the edit icons.
            $output .= html_writer::start_tag('div', array('class' => 'activityinstance'));
            $output .= $cmname;

            // Module can put text after the link (e.g. forum unread)
            $output .= $mod->afterlink;

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        // If there is content but NO link (eg label), then display the
        // content here (BEFORE any icons). In this case cons must be
        // displayed after the content so that it makes more sense visually
        // and for accessibility reasons, e.g. if you have a one-line label
        // it should work similarly (at least in terms of ordering) to an
        // activity.
        $contentpart = $this->course_section_cm_text($mod, $displayoptions);
        $url = $mod->url;
        if (empty($url)) {
            $output .= $contentpart;
        }

        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $modicons .= ' ' . $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $modicons .= $mod->afterediticons;
        }

        if (!empty($modicons)) {
            $output .= html_writer::div($modicons, 'actions');
        }

        // Fetch completion details.
        $showcompletionconditions = $course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS;
        $completiondetails = \core_completion\cm_completion_details::get_instance($mod, $USER->id, $showcompletionconditions);
        $ismanualcompletion = $completiondetails->has_completion() && !$completiondetails->is_automatic();

        // Fetch activity dates.
        $activitydates = [];
        if ($course->showactivitydates) {
            $activitydates = \core\activity_dates::get_dates_for_module($mod, $USER->id);
        }

        // Show the activity information if:
        // - The course's showcompletionconditions setting is enabled; or
        // - The activity tracks completion manually; or
        // - There are activity dates to be shown.
        if ($showcompletionconditions || $ismanualcompletion || $activitydates) {
            $output .= $this->output->activity_information($mod, $completiondetails, $activitydates);
        }

        // YURY Get the tags for this module.
        $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $mod->id);
        $context = context_course::instance($COURSE->id);
        $roles = get_user_roles($context, $USER->id, false);
        $isTeacher = false;

        foreach ($roles as $role) {
            $roleid = $role->roleid;
            $role = $DB->get_record('role', array('id' => $roleid));
            if ($role->shortname == 'editingteacher' || $role->shortname == 'teacher') {
                $isTeacher = true;
                break;
            }
        }

        if (!$isTeacher && !is_siteadmin($USER)) {
            if (!$this->learning_style($tags)) {
                return "";
            }
        }
        // END YURY

        // Show availability info (if module is not available).
        $output .= $this->course_section_cm_availability($mod, $displayoptions);

        // If there is content AND a link, then display the content here
        // (AFTER any icons). Otherwise it was displayed before
        if (!empty($url)) {
            $output .= $contentpart;
        }

        $output .= html_writer::end_tag('div'); // $indentclasses

        // End of indentation div.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders HTML to show course module availability information (for someone who isn't allowed
     * to see the activity itself, or for staff)
     *
     * @param cm_info $mod
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm_availability(cm_info $mod, $displayoptions = array())
    {
        global $CFG;
        $output = '';
        if (!$mod->is_visible_on_course_page()) {
            return $output;
        }
        if (!$mod->uservisible) {
            // this is a student who is not allowed to see the module but might be allowed
            // to see availability info (i.e. "Available from ...")
            if (!empty($mod->availableinfo)) {
                $formattedinfo = \core_availability\info::format_info(
                    $mod->availableinfo,
                    $mod->get_course()
                );
                $output = $this->availability_info($formattedinfo, 'isrestricted');
            }
            return $output;
        }
        // this is a teacher who is allowed to see module but still should see the
        // information that module is not available to all/some students
        $modcontext = context_module::instance($mod->id);
        $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $modcontext);

        // YURY Get the tags for this module.
        $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $mod->id);
        foreach ($tags as $tag) {
            $output .= html_writer::tag('span', $tag->get_display_name(), array('class' => 'badge badge-info'));
        }
        if (!$this->learning_style($tags)) {
            $output .= $this->availability_info(get_string('hiddenfromstudents'), 'ishidden');
            return $output;
        }
        // END YURY

        if ($canviewhidden && !$mod->visible) {
            // This module is hidden but current user has capability to see it.
            // Do not display the availability info if the whole section is hidden.
            if ($mod->get_section_info()->visible) {
                $output .= $this->availability_info(get_string('hiddenfromstudents'), 'ishidden');
            }
        } else if ($mod->is_stealth()) {
            // This module is available but is normally not displayed on the course page
            // (this user can see it because they can manage it).
            $output .= $this->availability_info(get_string('hiddenoncoursepage'), 'isstealth');
        }
        if ($canviewhidden && !empty($CFG->enableavailability)) {
            // Display information about conditional availability.
            // Don't add availability information if user is not editing and activity is hidden.
            if ($mod->visible || $this->page->user_is_editing()) {
                $hidinfoclass = 'isrestricted isfullinfo';
                if (!$mod->visible) {
                    $hidinfoclass .= ' hide';
                }
                $ci = new \core_availability\info_module($mod);
                $fullinfo = $ci->get_full_information();
                if ($fullinfo) {
                    $formattedinfo = \core_availability\info::format_info(
                        $fullinfo,
                        $mod->get_course()
                    );
                    $output .= $this->availability_info($formattedinfo, $hidinfoclass);
                }
            }
        }
        return $output;
    }

    /**
     * Generate the availability info for a module according to the learning style
     *
     * @param $tags
     * @return bool
     * @throws dml_exception
     */
    public function learning_style($tags)
    {

        if (empty($tags)) {
            return true;
        }

        global $DB, $USER;

        $sql = "SELECT * FROM {learning_style} WHERE user = ? ORDER BY id DESC";
        $records = $DB->get_records_sql($sql, array($USER->id), 0, 1);

        if ($records) {
            $record = reset($records);
        }
        if (isset($record)) {
            if ($record) {
                $tagValues = [
                    'active' => $record->act_ref[1] == "a",
                    'reflexive' => $record->act_ref[1] != "a",
                    'sensitive' => $record->sen_int[1] == "a",
                    'intuitive' => $record->sen_int[1] != "a",
                    'visual' => $record->vis_vrb[1] == "a",
                    'verbal' => $record->vis_vrb[1] != "a",
                    'sequential' => $record->seq_glo[1] == "a",
                    'global' => $record->seq_glo[1] != "a",
                ];

                foreach ($tags as $tag) {
                    $tagName = $tag->get_display_name();
                    if (isset($tagValues[$tagName]) && $tagValues[$tagName]) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Displays availability info for a course section or course module
     *
     * @param string $text
     * @param string $additionalclasses
     * @return string
     */
    public function availability_info($text, $additionalclasses = '')
    {

        $data = ['text' => $text, 'classes' => $additionalclasses];
        $additionalclasses = array_filter(explode(' ', $additionalclasses));

        if (in_array('ishidden', $additionalclasses)) {
            $data['ishidden'] = 1;
        } else if (in_array('isstealth', $additionalclasses)) {
            $data['isstealth'] = 1;
        } else if (in_array('isrestricted', $additionalclasses)) {
            $data['isrestricted'] = 1;

            if (in_array('isfullinfo', $additionalclasses)) {
                $data['isfullinfo'] = 1;
            }
        }

        return $this->render_from_template('core/availability_info', $data);
    }

    /**
     * Renders html to display a name with the link to the course module on a course page
     *
     * If module is unavailable for user but still needs to be displayed
     * in the list, just the name is returned without a link
     *
     * Note, that for course modules that never have separate pages (i.e. labels)
     * this function return an empty string
     *
     * @param cm_info $mod
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm_name(cm_info $mod, $displayoptions = array())
    {
        if (!$mod->is_visible_on_course_page() || !$mod->url) {
            // Nothing to be displayed to the user.
            return '';
        }

        list($linkclasses, $textclasses) = $this->course_section_cm_classes($mod);
        $groupinglabel = $mod->get_grouping_label($textclasses);

        // Render element that allows to edit activity name inline. It calls {@link course_section_cm_name_title()}
        // to get the display title of the activity.
        $tmpl = new \core_course\output\course_module_name($mod, $this->page->user_is_editing(), $displayoptions);
        return $this->output->render_from_template('core/inplace_editable', $tmpl->export_for_template($this->output)) .
            $groupinglabel;
    }

    /**
     * Returns the CSS classes for the activity name/content
     *
     * For items which are hidden, unavailable or stealth but should be displayed
     * to current user ($mod->is_visible_on_course_page()), we show those as dimmed.
     * Students will also see as dimmed activities names that are not yet available
     * but should still be displayed (without link) with availability info.
     *
     * @param cm_info $mod
     * @return array array of two elements ($linkclasses, $textclasses)
     */
    protected function course_section_cm_classes(cm_info $mod)
    {
        $linkclasses = '';
        $textclasses = '';
        if ($mod->uservisible) {
            $conditionalhidden = $this->is_cm_conditionally_hidden($mod);
            $accessiblebutdim = (!$mod->visible || $conditionalhidden) &&
                has_capability('moodle/course:viewhiddenactivities', $mod->context);
            if ($accessiblebutdim) {
                $linkclasses .= ' dimmed';
                $textclasses .= ' dimmed_text';
                if ($conditionalhidden) {
                    $linkclasses .= ' conditionalhidden';
                    $textclasses .= ' conditionalhidden';
                }
            }
            if ($mod->is_stealth()) {
                // Stealth activity is the one that is not visible on course page.
                // It still may be displayed to the users who can manage it.
                $linkclasses .= ' stealth';
                $textclasses .= ' stealth';
            }
        } else {
            $linkclasses .= ' dimmed';
            $textclasses .= ' dimmed dimmed_text';
        }
        return array($linkclasses, $textclasses);
    }

    /**
     * Checks if course module has any conditions that may make it unavailable for
     * all or some of the students
     *
     * This function is internal and is only used to create CSS classes for the module name/text
     *
     * @param cm_info $mod
     * @return bool
     */
    protected function is_cm_conditionally_hidden(cm_info $mod)
    {
        global $CFG;
        $conditionalhidden = false;
        if (!empty($CFG->enableavailability)) {
            $info = new \core_availability\info_module($mod);
            $conditionalhidden = !$info->is_available_for_all();
        }
        return $conditionalhidden;
    }

    /**
     * Renders html to display the module content on the course page (i.e. text of the labels)
     *
     * @param cm_info $mod
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm_text(cm_info $mod, $displayoptions = array())
    {
        $output = '';
        if (!$mod->is_visible_on_course_page()) {
            // nothing to be displayed to the user
            return $output;
        }
        $content = $mod->get_formatted_content(array('overflowdiv' => true, 'noclean' => true));
        list($linkclasses, $textclasses) = $this->course_section_cm_classes($mod);
        if ($mod->url && $mod->uservisible) {
            if ($content) {
                // If specified, display extra content after link.
                $output = html_writer::tag('div', $content, array('class' =>
                trim('contentafterlink ' . $textclasses)));
            }
        } else {
            $groupinglabel = $mod->get_grouping_label($textclasses);

            // No link, so display only content.
            $output = html_writer::tag(
                'div',
                $content . $groupinglabel,
                array('class' => 'contentwithoutlink ' . $textclasses)
            );
        }
        return $output;
    }

    /**
     * Renders HTML for displaying the sequence of course module editing buttons
     *
     * @param action_link[] $actions Array of action_link objects
     * @param cm_info $mod The module we are displaying actions for.
     * @param array $displayoptions additional display options:
     *     ownerselector => A JS/CSS selector that can be used to find an cm node.
     *         If specified the owning node will be given the class 'action-menu-shown' when the action
     *         menu is being displayed.
     *     constraintselector => A JS/CSS selector that can be used to find the parent node for which to constrain
     *         the action menu to when it is being displayed.
     *     donotenhance => If set to true the action menu that gets displayed won't be enhanced by JS.
     * @return string
     * @see course_get_cm_edit_actions()
     *
     */
    public function course_section_cm_edit_actions($actions, cm_info $mod = null, $displayoptions = array())
    {
        global $CFG;

        if (empty($actions)) {
            return '';
        }

        if (isset($displayoptions['ownerselector'])) {
            $ownerselector = $displayoptions['ownerselector'];
        } else if ($mod) {
            $ownerselector = '#module-' . $mod->id;
        } else {
            debugging('You should upgrade your call to ' . __FUNCTION__ . ' and provide $mod', DEBUG_DEVELOPER);
            $ownerselector = 'li.activity';
        }

        if (isset($displayoptions['constraintselector'])) {
            $constraint = $displayoptions['constraintselector'];
        } else {
            $constraint = '.course-content';
        }

        $menu = new action_menu();
        $menu->set_owner_selector($ownerselector);
        $menu->set_constraint($constraint);
        $menu->set_alignment(action_menu::TR, action_menu::BR);
        $menu->set_menu_trigger(get_string('edit'));

        foreach ($actions as $action) {
            if ($action instanceof action_menu_link) {
                $action->add_class('cm-edit-action');
            }
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' section-cm-edit-actions commands';

        // Prioritise the menu ahead of all other actions.
        $menu->prioritise = true;

        return $this->render($menu);
    }
}
