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
require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/alpy/lib.php');


/**
 * Basic renderer for alpy format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_alpy_renderer extends format_section_renderer_base
{
    protected function start_section_list()
    {
        return html_writer::start_tag('ul', array('class' => 'alpy'));
    }

    protected function end_section_list()
    {
        return html_writer::end_tag('ul');
    }

    protected function page_title()
    {
        return get_string('weeklyoutline');
    }

    public function section_title($section, $course)
    {
        $title = $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
        $format = course_get_format($course);
        if (!($format instanceof format_alpy)) {
            throw new coding_exception('Format must be semana');
        }

        $modinfo = get_fast_modinfo($course);
        $activities = $format->get_activities_and_resources($section->id);

        foreach ($activities as $activity) {
            $cm = $modinfo->get_cm($activity->id);
            $title .= html_writer::start_tag('div', array('class' => 'activity-tags'));
            $title .= get_string('activity', 'format_alpy', $cm->name);
            foreach ($activity->tags as $tag) {
                $title .= html_writer::tag('span', $tag, array('class' => 'tag'));
            }
            $title .= html_writer::end_tag('div');
        }

        return $title;
    }

    public function section_title_without_link($section, $course)
    {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }
}
