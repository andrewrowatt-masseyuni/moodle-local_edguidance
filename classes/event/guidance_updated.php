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
 * Teacher guidance was changed from the editor's guidance form.
 *
 * One event however much changed - including ticks added or removed by hand in the text, which
 * are not logged item by item as ticking a box on the page is.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guidance_updated extends base {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * The event's name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventguidanceupdated', 'local_edguidance');
    }

    /**
     * What happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated the teacher guidance with id '{$this->objectid}'.";
    }
}
