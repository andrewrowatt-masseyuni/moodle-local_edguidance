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

namespace local_edguidance;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_http_headers;
use core_course\hook\before_course_deleted;
use local_edguidance\local\card_injector;

/**
 * Hook callbacks for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Put description guidance into course page cards, before the page renders them.
     *
     * course/view.php sets the page type well before it calls header(), and renders the course
     * content after, so this is the last moment the cards' afterlink can still be changed.
     *
     * @param before_http_headers $hook The hook.
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;

        if (during_initial_install() || !get_config('local_edguidance', 'version')) {
            return;
        }

        $pagetype = (string)$PAGE->pagetype;
        if (!str_starts_with($pagetype, 'course-view-') && $pagetype !== 'site-index') {
            return;
        }

        card_injector::prime((int)$PAGE->course->id);
    }

    /**
     * Load the dismiss/restore JavaScript for anyone who might see a block on this page.
     *
     * Loaded on the strength of the capability in the page's context rather than of a block
     * having been rendered, because blocks also arrive after the page has loaded - a card
     * re-rendered over AJAX, say - and the listeners are delegated so that they find those too.
     *
     * @param before_footer_html_generation $hook The hook.
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }

        if (!has_capability('local/edguidance:view', $PAGE->context)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_edguidance/guidance', 'init');
    }

    /**
     * Delete a course's blocks before the course goes.
     *
     * Course deletion removes modules without the per-module delete callbacks, so
     * local_edguidance_pre_course_module_delete() never sees them.
     *
     * @param before_course_deleted $hook The hook.
     */
    public static function before_course_deleted(before_course_deleted $hook): void {
        api::delete_for_course((int)$hook->course->id);
    }
}
