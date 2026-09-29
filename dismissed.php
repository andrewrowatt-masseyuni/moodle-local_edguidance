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
 * The teacher guidance the current user has dismissed in a course, and the way to restore it.
 *
 * Dismissed guidance leaves nothing behind on the page or in the editor, so this is the only way
 * back. It is reached from the course navigation, which offers it only when there is something to
 * restore.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edguidance\dismissed;
use local_edguidance\output\dismissed_page;

$courseid = required_param('course', PARAM_INT);
$restore = optional_param('restore', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

// Everything on this page is guidance, so someone who cannot see guidance has nothing to do here.
require_capability('local/edguidance:view', context_course::instance($course->id));

$url = new moodle_url('/local/edguidance/dismissed.php', ['course' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('dismissed', 'local_edguidance'));
$PAGE->set_heading(format_string($course->fullname));

if ($restore > 0) {
    require_sesskey();

    // Nothing to check about the block itself: restoring only ever deletes the current user's own
    // flag, which reveals nothing and changes nothing for anyone else. Whether they then see the
    // block is decided where it renders, as for everyone.
    dismissed::set($restore, false);

    redirect($url, get_string('restored', 'local_edguidance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dismissed', 'local_edguidance'));
echo $OUTPUT->render_from_template(
    'local_edguidance/dismissed_page',
    (new dismissed_page($course))->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
