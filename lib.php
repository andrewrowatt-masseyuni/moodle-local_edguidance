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
 * Library callbacks for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edguidance\api;
use local_edguidance\local\card_injector;

/**
 * After any activity is saved through its settings form, record the guidance in its description.
 *
 * This is also where a draft made on the "add an activity" form - before the activity had an id -
 * is adopted by the activity that now carries its token.
 *
 * Failures are reported rather than thrown: the activity has already been saved, and a problem with
 * its guidance must not turn a successful save into an error page.
 *
 * @param stdClass $data The module info, with ->coursemodule set.
 * @param stdClass $course The course.
 * @return stdClass The module info, unchanged.
 */
function local_edguidance_coursemodule_edit_post_actions($data, $course) {
    if (empty($data->coursemodule)) {
        return $data;
    }

    try {
        api::adopt_intro((int)$data->coursemodule);
    } catch (\moodle_exception $e) {
        debugging('Could not record teacher guidance for course module ' . $data->coursemodule . ': ' .
            $e->getMessage(), DEBUG_DEVELOPER);
    }

    return $data;
}

/**
 * Delete a course module's guidance blocks before the module goes.
 *
 * tool_recyclebin's own callback runs first (tool before local), so a recycled activity is backed
 * up with its guidance before this deletes it.
 *
 * @param stdClass $cm The course_modules record.
 */
function local_edguidance_pre_course_module_delete($cm) {
    api::delete_for_cm((int)$cm->id);
}

/**
 * Prime course page cards before a web service call re-renders them.
 *
 * Never overrides anything: it always returns false, so core goes on to run the real function,
 * which then renders from the modinfo primed here. See card_injector for why this is needed.
 *
 * @param stdClass $externalfunctioninfo The function being called.
 * @param array $params Its validated parameters, in declaration order.
 * @return false
 */
function local_edguidance_override_webservice_execution($externalfunctioninfo, $params) {
    card_injector::prime_for_webservice((string)$externalfunctioninfo->name, (array)$params);

    return false;
}

/**
 * Serve the files embedded in a guidance block's own text.
 *
 * @param stdClass $course The course.
 * @param stdClass|null $cm The course module, or null for a draft's course context.
 * @param context $context The file's context.
 * @param string $filearea The file area.
 * @param array $args The remaining path: itemid, then the file path.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Further options.
 * @return bool False if the file is not found; otherwise the file is sent and this does not return.
 */
function local_edguidance_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($filearea !== api::FILEAREA || count($args) < 2) {
        return false;
    }

    $itemid = (int)array_shift($args);

    if ($context->contextlevel == CONTEXT_MODULE) {
        require_login($course, false, $cm);
        require_capability('local/edguidance:view', $context);
        $owned = $DB->record_exists('local_edguidance', ['id' => $itemid, 'cmid' => $cm->id]);
    } else if ($context->contextlevel == CONTEXT_COURSE) {
        // A draft, only ever seen by whoever is writing it.
        require_login($course);
        require_capability('local/edguidance:manage', $context);
        $owned = $DB->record_exists('local_edguidance', ['id' => $itemid, 'cmid' => 0, 'courseid' => $course->id]);
    } else {
        return false;
    }

    if (!$owned) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_edguidance', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}
