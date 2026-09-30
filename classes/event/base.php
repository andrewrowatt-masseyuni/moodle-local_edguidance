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

namespace local_edguidance\event;

/**
 * What every teacher guidance event has in common.
 *
 * The object is the local_edguidance row, and the context is the block's own: its activity's, or
 * its course's for a section's block or a draft.
 *
 * Log restore cannot map the row. Activity blocks are mapped only once the whole restore is done
 * (see restore_local_edguidance_plugin::after_restore_module()), after the logs, and section blocks
 * under another name; so the id is not carried across.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_edguidance';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Where the guidance is: the course page, at the activity if it is an activity's.
     *
     * Built from the event's own fields rather than its context, which may be gone by the time
     * anyone reads the log.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $anchor = $this->contextlevel == CONTEXT_MODULE ? 'module-' . $this->contextinstanceid : '';

        return new \moodle_url('/course/view.php', ['id' => $this->courseid], $anchor);
    }

    /**
     * Log restore cannot map the row.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_edguidance', 'restore' => self::NOT_MAPPED];
    }

    /**
     * Nothing in other is an id.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
