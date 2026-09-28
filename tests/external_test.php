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

use local_edguidance\external\embed_preset;
use local_edguidance\external\set_dismissed;

/**
 * Tests for the web services.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\external\set_dismissed
 * @covers     \local_edguidance\external\embed_preset
 */
final class external_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * A course with a book holding one block.
     *
     * @return array [course, book, row]
     */
    private function make_block(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(['cmid' => $book->cmid]);

        return [$course, $book, $row];
    }

    /**
     * A teacher can dismiss and restore.
     */
    public function test_teacher_can_dismiss_and_restore(): void {
        $this->resetAfterTest();
        [$course, , $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->assertTrue(set_dismissed::execute((int)$row->id, true)['dismissed']);
        $this->assertTrue(dismissed::is_dismissed((int)$row->id));

        $this->assertFalse(set_dismissed::execute((int)$row->id, false)['dismissed']);
        $this->assertFalse(dismissed::is_dismissed((int)$row->id));
    }

    /**
     * A student is refused, and nothing is stored for them.
     */
    public function test_student_cannot_dismiss(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, , $row] = $this->make_block();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        try {
            set_dismissed::execute((int)$row->id, true);
            $this->fail('A student was allowed to dismiss guidance.');
        } catch (\required_capability_exception $e) {
            $this->assertFalse($DB->record_exists('favourite', [
                'userid' => $student->id,
                'component' => dismissed::COMPONENT,
            ]));
        }
    }

    /**
     * A draft is never rendered, so it cannot be dismissed.
     */
    public function test_drafts_cannot_be_dismissed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $draft = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(['courseid' => $course->id]);
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        set_dismissed::execute((int)$draft->id, true);
    }

    /**
     * An editing teacher can embed a preset; the block is linked to its slot.
     */
    public function test_embed_preset(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Dates.</p>');
        [$course, $book] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $key = embed_preset::execute(\context_module::instance($book->cmid)->id, 1)['key'];

        $row = $DB->get_record('local_edguidance', ['cmid' => $book->cmid, 'embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(1, (int)$row->presetslot);
    }

    /**
     * A non-editing teacher can read guidance but not add it.
     */
    public function test_embed_preset_needs_manage(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Dates.</p>');
        [$course, $book] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->expectException(\required_capability_exception::class);
        embed_preset::execute(\context_module::instance($book->cmid)->id, 1);
    }

    /**
     * An empty slot is refused.
     */
    public function test_embed_preset_refuses_an_unused_slot(): void {
        $this->resetAfterTest();
        [$course, $book] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\invalid_parameter_exception::class);
        embed_preset::execute(\context_module::instance($book->cmid)->id, 9);
    }

    /**
     * A section's block is dismissed against the course.
     */
    public function test_section_blocks_can_be_dismissed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'sectionid' => get_fast_modinfo($course)->get_section_info(1)->id,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->assertTrue(set_dismissed::execute((int)$row->id, true)['dismissed']);
        $this->assertTrue(dismissed::is_dismissed((int)$row->id));

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->expectException(\required_capability_exception::class);
        set_dismissed::execute((int)$row->id, true);
    }

    /**
     * A preset embedded from a section summary's editor belongs to that section.
     */
    public function test_embed_preset_in_a_section(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Dates.</p>');
        $course = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($course)->get_section_info(1);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $key = embed_preset::execute(\context_course::instance($course->id)->id, 1, (int)$section->id)['key'];

        $row = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame((int)$section->id, (int)$row->sectionid);
        $this->assertSame(0, (int)$row->cmid);
    }

    /**
     * A section in a course the teacher is not editing is refused.
     */
    public function test_embed_preset_refuses_a_section_elsewhere(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Dates.</p>');
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\invalid_parameter_exception::class);
        embed_preset::execute(
            \context_course::instance($course->id)->id,
            1,
            (int)get_fast_modinfo($other)->get_section_info(1)->id
        );
    }
}
