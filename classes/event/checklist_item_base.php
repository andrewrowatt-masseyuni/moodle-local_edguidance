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
 * A box on a teacher guidance checklist was ticked or unticked.
 *
 * other: index, the item's position in the checklist from 0; and item, its text, shortened, so that
 * the log says what was done without the guidance at hand.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class checklist_item_base extends base {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * Check other has what the description needs.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['index'])) {
            throw new \coding_exception('The \'index\' value must be set in other.');
        }
        if (!isset($this->other['item'])) {
            throw new \coding_exception('The \'item\' value must be set in other.');
        }
    }

    /**
     * The item, for a description: its number, counted from 1, and its text.
     *
     * @return string
     */
    protected function item(): string {
        return "item " . ((int)$this->other['index'] + 1) . " ('" . $this->other['item'] . "')";
    }
}
