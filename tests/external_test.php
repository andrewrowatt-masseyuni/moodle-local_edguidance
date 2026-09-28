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

use core_external\external_api;
use local_edguidance\external\embed_preset;
use local_edguidance\external\get_previews;
use local_edguidance\external\set_dismissed;

/**
 * Tests for the web services.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\external\set_dismissed
 * @covers     \local_edguidance\external\embed_preset
 * @covers     \local_edguidance\external\get_previews
 * @covers     \local_edguidance\output\block
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

    /**
     * Previews for every key asked, once each, in order: own text and a preset in full, with no
     * buttons, and a key with no block here as the notice the filter shows.
     */
    public function test_get_previews(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        $generator->set_preset(1, 'Dates', '<p>Check the dates.</p>');
        [$course, $book, $own] = $this->make_block();
        $preset = $generator->create_block(['cmid' => $book->cmid, 'presetslot' => 1, 'guidance' => '<p>Old copy.</p>']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $unknown = token::new_key();
        $previews = get_previews::execute(
            \context_module::instance($book->cmid)->id,
            [$own->embedkey, $preset->embedkey, $unknown, $own->embedkey]
        );
        $previews = external_api::clean_returnvalue(get_previews::execute_returns(), $previews);

        $this->assertSame([$own->embedkey, $preset->embedkey, $unknown], array_column($previews, 'key'));

        $this->assertStringContainsString('Check the due date before releasing this.', $previews[0]['html']);
        $this->assertStringContainsString('edguidance-card', $previews[0]['html']);
        $this->assertStringNotContainsString('edguidance-dismiss', $previews[0]['html']);
        $this->assertStringNotContainsString('edguidance-restore', $previews[0]['html']);

        // The preset as it stands, not the snapshot.
        $this->assertStringContainsString('Check the dates.', $previews[1]['html']);
        $this->assertStringNotContainsString('Old copy.', $previews[1]['html']);

        $this->assertStringContainsString(get_string('previewnotfound', 'local_edguidance'), $previews[2]['html']);
    }

    /**
     * A block the teacher has dismissed is previewed in full: they are editing it.
     */
    public function test_get_previews_ignores_dismissal(): void {
        $this->resetAfterTest();
        [$course, $book, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        dismissed::set((int)$row->id, true);

        $html = get_previews::execute(\context_module::instance($book->cmid)->id, [$row->embedkey])[0]['html'];

        $this->assertStringNotContainsString('edguidance-is-dismissed', $html);
        $this->assertStringNotContainsString('hidden', $html);
        $this->assertStringContainsString('Check the due date before releasing this.', $html);
    }

    /**
     * A block is found only in the editor it belongs to, as the guidance form finds it.
     */
    public function test_get_previews_are_scoped_to_the_editor(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        [$course, $book, $row] = $this->make_block();
        $other = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course->id);
        $section1 = (int)$modinfo->get_section_info(1)->id;
        $section2 = (int)$modinfo->get_section_info(2)->id;
        $sectionrow = $generator->create_block(['sectionid' => $section1]);
        $draft = $generator->create_block(['courseid' => $course->id]);
        $coursecontext = \context_course::instance($course->id)->id;
        $notfound = get_string('previewnotfound', 'local_edguidance');
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $preview = fn(int $contextid, \stdClass $block, int $sectionid = 0): string =>
            get_previews::execute($contextid, [$block->embedkey], $sectionid)[0]['html'];

        $this->assertStringNotContainsString($notfound, $preview(\context_module::instance($book->cmid)->id, $row));
        $this->assertStringContainsString($notfound, $preview(\context_module::instance($other->cmid)->id, $row));

        $this->assertStringNotContainsString($notfound, $preview($coursecontext, $sectionrow, $section1));
        $this->assertStringContainsString($notfound, $preview($coursecontext, $sectionrow, $section2));

        // The "add an activity" form: its drafts, and nothing of any section's.
        $this->assertStringNotContainsString($notfound, $preview($coursecontext, $draft));
        $this->assertStringContainsString($notfound, $preview($coursecontext, $sectionrow));
    }

    /**
     * A section in a course the teacher is not editing is refused.
     */
    public function test_get_previews_refuses_a_section_elsewhere(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\invalid_parameter_exception::class);
        get_previews::execute(
            \context_course::instance($course->id)->id,
            [token::new_key()],
            (int)get_fast_modinfo($other)->get_section_info(1)->id
        );
    }

    /**
     * A token inside guidance is stripped, not previewed: the page never shows guidance in guidance.
     */
    public function test_get_previews_never_contain_a_token(): void {
        $this->resetAfterTest();
        [$course, $book, $inner] = $this->make_block();
        $outer = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $book->cmid,
            'guidance' => '<p>Outer.</p>' . token::html($inner->embedkey),
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $html = get_previews::execute(\context_module::instance($book->cmid)->id, [$outer->embedkey])[0]['html'];

        $this->assertStringContainsString('Outer.', $html);
        $this->assertStringNotContainsString(token::ATTRIBUTE, $html);
        $this->assertStringNotContainsString('Check the due date before releasing this.', $html);
    }

    /**
     * A block with nothing to say still previews, so there is something to click.
     */
    public function test_get_previews_of_an_empty_block(): void {
        $this->resetAfterTest();
        [$course, $book] = $this->make_block();
        $empty = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $book->cmid,
            'guidance' => '<p></p>',
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $html = get_previews::execute(\context_module::instance($book->cmid)->id, [$empty->embedkey])[0]['html'];

        $this->assertStringContainsString(get_string('previewempty', 'local_edguidance'), $html);
    }

    /**
     * Only people who may write guidance are shown the preview: a non-editing teacher has no
     * editor to preview in, and a student none at all.
     */
    public function test_get_previews_needs_manage(): void {
        $this->resetAfterTest();
        [$course, $book, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->expectException(\required_capability_exception::class);
        get_previews::execute(\context_module::instance($book->cmid)->id, [$row->embedkey]);
    }
}
