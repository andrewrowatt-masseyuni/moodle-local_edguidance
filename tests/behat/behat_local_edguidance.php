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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for teacher guidance.
 *
 * @package    local_edguidance
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_edguidance extends behat_base {
    /**
     * Resolve a page belonging to a course.
     *
     * The dismissed guidance page is reached from a course navigation node, and whether that node is
     * directly clickable depends on how much secondary navigation fits at the current window size -
     * and on the theme. Going straight to the URL keeps these tests about the plugin rather than
     * about navigation overflow.
     *
     * Recognised page types:
     * - "Course 1 > dismissed guidance": the guidance this user has dismissed in that course.
     *
     * @param string $type The page type.
     * @param string $identifier The course shortname or fullname.
     * @return moodle_url
     * @throws Exception If the page type is not one of the above.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'dismissed guidance':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier])
                    ?: $DB->get_field('course', 'id', ['fullname' => $identifier]);
                if (!$courseid) {
                    throw new Exception("There is no course with shortname or fullname '{$identifier}'.");
                }
                return new moodle_url('/local/edguidance/dismissed.php', ['course' => $courseid]);
            default:
                throw new Exception("Unrecognised local_edguidance page type '{$type}'.");
        }
    }
}
