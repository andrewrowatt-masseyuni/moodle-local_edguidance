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

/**
 * Tests for the site presets.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\presets
 */
final class presets_test extends \advanced_testcase {
    /**
     * Only slots with both a title and some guidance are offered.
     */
    public function test_only_complete_slots_are_in_use(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        $generator->set_preset(1, 'Due dates', '<p>Set the due date.</p>');
        $generator->set_preset(2, 'Title only', '');
        $generator->set_preset(3, '', '<p>Guidance with no title.</p>');
        $generator->set_preset(4, 'Blank HTML', '<p> </p>');
        $generator->set_preset(7, 'Groups', '<p>Check group mode.</p>');

        $this->assertSame([1 => 'Due dates', 7 => 'Groups'], presets::all());
        $this->assertNull(presets::get(2));
        $this->assertNull(presets::get(3));
        $this->assertNull(presets::get(4));
        $this->assertSame('<p>Check group mode.</p>', presets::get(7)->guidance);
    }

    /**
     * Slots outside the range do not exist.
     */
    public function test_out_of_range_slots(): void {
        $this->resetAfterTest();

        $this->assertNull(presets::get(0));
        $this->assertNull(presets::get(presets::MAX + 1));
        $this->assertNull(presets::get(-1));
    }
}
