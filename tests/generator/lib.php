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
 * Test data generator for teacher guidance.
 *
 * @package    local_edguidance
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_edguidance_generator extends component_generator_base {
    /**
     * Create a guidance block directly, without going through an editor.
     *
     * @param array|stdClass $record Needs cmid, or courseid for a draft. Optional: embedkey,
     *     guidance, guidanceformat, presetslot, introorder.
     * @return stdClass The new row.
     */
    public function create_block($record): stdClass {
        global $DB;

        $record = (array)$record;
        $now = time();

        if (!empty($record['cmid'])) {
            $record['courseid'] = (int)$DB->get_field('course_modules', 'course', ['id' => $record['cmid']], MUST_EXIST);
        }

        $row = (object)array_merge([
            'cmid' => 0,
            'embedkey' => \local_edguidance\token::new_key(),
            'introorder' => 0,
            'presetslot' => 0,
            'guidance' => '<p>Check the due date before releasing this.</p>',
            'guidanceformat' => FORMAT_HTML,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $row->id = $DB->insert_record('local_edguidance', $row);
        \local_edguidance\guidance::reset_cache();

        return $row;
    }

    /**
     * Fill a site preset slot.
     *
     * @param int $slot The slot.
     * @param string $title The title.
     * @param string $guidance The guidance HTML.
     */
    public function set_preset(int $slot, string $title, string $guidance): void {
        set_config('presettitle' . $slot, $title, 'local_edguidance');
        set_config('presetguidance' . $slot, $guidance, 'local_edguidance');
    }
}
