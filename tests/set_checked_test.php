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
use local_edguidance\event\checklist_item_checked;
use local_edguidance\event\checklist_item_unchecked;
use local_edguidance\external\set_checked;
use local_edguidance\output\block;

/**
 * Tests for ticking checklist items: the web service, what it writes and logs, and who may.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\external\set_checked
 * @covers     \local_edguidance\api::set_checked
 * @covers     \local_edguidance\event\checklist_item_checked
 * @covers     \local_edguidance\event\checklist_item_unchecked
 * @covers     \local_edguidance\output\block::render_row
 */
final class set_checked_test extends \advanced_testcase {
    /** @var string The checklist the tests tick. */
    private const TEXT = '<p>Before week one:</p><ul><li>[ ] Set the due date</li><li>[ ] Check the groups</li></ul>';

    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * A course with a page whose block holds a checklist.
     *
     * @param array $record Overrides for the block.
     * @return array [course, row]
     */
    private function make_block(array $record = []): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(
            ['cmid' => $page->cmid, 'guidance' => self::TEXT] + $record
        );

        return [$course, $row];
    }

    /**
     * Tick or untick an item as the page would, with the hash the text gives it now.
     *
     * @param \stdClass $row The block.
     * @param int $index The item.
     * @param bool $checked Whether it should be ticked.
     * @return array The web service result.
     */
    private function tick(\stdClass $row, int $index, bool $checked): array {
        $hash = checklist::items(self::TEXT)[$index]->hash;
        $result = set_checked::execute((int)$row->id, $index, $hash, $checked);

        return external_api::clean_returnvalue(set_checked::execute_returns(), $result);
    }

    /**
     * The block's text now.
     *
     * @param \stdClass $row The block.
     * @return string
     */
    private function text(\stdClass $row): string {
        global $DB;

        return $DB->get_field('local_edguidance', 'guidance', ['id' => $row->id]);
    }

    /**
     * A non-editing teacher ticks an item for everyone, and unticks it; each is logged, with the item.
     */
    public function test_tick_and_untick(): void {
        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);
        $context = \context_module::instance($row->cmid);

        $sink = $this->redirectEvents();
        $this->assertTrue($this->tick($row, 1, true)['checked']);
        $events = $sink->get_events();

        $this->assertSame(
            '<p>Before week one:</p><ul><li>[ ] Set the due date</li><li>[x] Check the groups</li></ul>',
            $this->text($row)
        );
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(checklist_item_checked::class, $event);
        $this->assertSame((int)$row->id, (int)$event->objectid);
        $this->assertSame((int)$teacher->id, (int)$event->userid);
        $this->assertSame($context->id, $event->get_context()->id);
        $this->assertSame((int)$course->id, (int)$event->courseid);
        $this->assertSame(['index' => 1, 'item' => 'Check the groups'], $event->other);
        $this->assertStringContainsString("ticked item 2 ('Check the groups')", $event->get_description());
        $this->assertEquals(new \moodle_url('/course/view.php', ['id' => $course->id], 'module-' . $row->cmid), $event->get_url());
        $this->assertEventContextNotUsed($event);

        $sink->clear();
        $this->assertFalse($this->tick($row, 1, false)['checked']);
        $events = $sink->get_events();

        $this->assertSame(self::TEXT, $this->text($row));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(checklist_item_unchecked::class, reset($events));
        $this->assertStringContainsString("unticked item 2 ('Check the groups')", reset($events)->get_description());
    }

    /**
     * Guidance whose checklist is all ticked is dressed as complete, whatever its category - and not
     * before.
     */
    public function test_all_ticked_is_complete(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $row] = $this->make_block(['category' => category::TASK]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));
        $render = function () use ($DB, $row): string {
            guidance::reset_cache();
            return block::render_row($DB->get_record('local_edguidance', ['id' => $row->id]));
        };

        $this->assertStringNotContainsString('edguidance-complete', $render());
        $this->tick($row, 0, true);
        $this->assertStringNotContainsString('edguidance-complete', $render());
        $this->tick($row, 1, true);
        $this->assertStringContainsString('class="edguidance edguidance-task edguidance-complete"', $render());
    }

    /**
     * Ticking an item that is already ticked - two teachers at once, say - changes and logs nothing.
     */
    public function test_ticking_twice_logs_once(): void {
        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->tick($row, 0, true);
        $ticked = $this->text($row);

        $sink = $this->redirectEvents();
        $this->tick($row, 0, true);

        $this->assertSame($ticked, $this->text($row));
        $this->assertSame([], $sink->get_events());
    }

    /**
     * A tick from a page loaded before the guidance was edited is refused if the item it names is
     * not the one the page showed.
     */
    public function test_a_stale_page_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));
        // Edited since: the first item is gone, so what is now item 0 is not what the page showed.
        $edited = '<ul><li>[ ] Check the groups</li></ul>';
        $DB->set_field('local_edguidance', 'guidance', $edited, ['id' => $row->id]);

        $sink = $this->redirectEvents();
        try {
            $this->tick($row, 0, true);
            $this->fail('A tick from a stale page was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('checklistchanged', $e->errorcode);
        }

        $this->assertSame($edited, $this->text($row));
        $this->assertSame([], $sink->get_events());
    }

    /**
     * An item that is not there at all is refused the same way.
     */
    public function test_a_missing_item_is_refused(): void {
        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->expectExceptionMessage(get_string('checklistchanged', 'local_edguidance'));
        set_checked::execute((int)$row->id, 5, checklist::items(self::TEXT)[0]->hash, true);
    }

    /**
     * A preset's text is the whole site's, so its checklist cannot be ticked, and shows disabled.
     */
    public function test_preset_blocks_cannot_be_ticked(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Weekly', self::TEXT);
        [$course, $row] = $this->make_block(['presetslot' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = block::render_row($row);
        $this->assertStringContainsString('disabled title="' . get_string('checklistpreset', 'local_edguidance') . '"', $html);
        $this->assertStringNotContainsString('data-action="edguidance-check"', $html);

        $this->expectExceptionMessage(get_string('checklistpreset', 'local_edguidance'));
        $this->tick($row, 0, true);
    }

    /**
     * The hash the page renders for an item is the one ticking it takes.
     */
    public function test_the_page_and_the_service_agree(): void {
        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = block::render_row($row);
        $this->assertSame(2, preg_match_all('~data-checkindex="(\d+)" data-checkhash="([0-9a-f]+)"~', $html, $boxes));

        set_checked::execute((int)$row->id, (int)$boxes[1][1], $boxes[2][1], true);

        $this->assertStringContainsString('<li>[x] Check the groups</li>', $this->text($row));
    }

    /**
     * Taking the capability away leaves the boxes shown but disabled, and refuses the service.
     */
    public function test_needs_the_tick_capability(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $context = \context_module::instance($row->cmid);
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/edguidance:tick', CAP_PROHIBIT, $roleid, $context->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = block::render_row($row);
        $this->assertStringContainsString('<input type="checkbox" class="edguidance-check" disabled>', $html);
        $this->assertStringNotContainsString('data-action="edguidance-check"', $html);

        $this->expectException(\required_capability_exception::class);
        $this->tick($row, 0, true);
    }

    /**
     * A student is refused, and nothing changes.
     */
    public function test_students_cannot_tick(): void {
        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        try {
            $this->tick($row, 0, true);
            $this->fail('A student was allowed to tick a checklist.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(self::TEXT, $this->text($row));
        }
    }

    /**
     * A section's block is ticked, and logged, against its course.
     */
    public function test_section_blocks_can_be_ticked(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'sectionid' => get_fast_modinfo($course->id)->get_section_info(1)->id,
            'guidance' => self::TEXT,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $sink = $this->redirectEvents();
        $this->tick($row, 0, true);

        $this->assertStringContainsString('<li>[x] Set the due date</li>', $this->text($row));
        $this->assertSame(\context_course::instance($course->id)->id, $sink->get_events()[0]->get_context()->id);
    }

    /**
     * A draft is never rendered, so it cannot be ticked.
     */
    public function test_drafts_cannot_be_ticked(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $draft = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'courseid' => $course->id,
            'guidance' => self::TEXT,
        ]);
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        $this->tick($draft, 0, true);
    }

    /**
     * In the editor's preview the boxes tick for a teacher who may tick, as on the page; for anyone
     * else they are disabled, and a click there opens the guidance form for those who may edit it.
     */
    public function test_preview_boxes_tick_for_those_who_may(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $row] = $this->make_block();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $html = block::render_preview($row);
        $this->assertSame(2, substr_count($html, 'data-action="edguidance-check"'));

        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'manager']);
        assign_capability('local/edguidance:tick', CAP_PROHIBIT, $roleid, \context_module::instance($row->cmid)->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'manager'));

        $html = block::render_preview($row);
        $this->assertSame(2, substr_count($html, 'class="edguidance-check" disabled>'));
        $this->assertStringNotContainsString('data-action="edguidance-check"', $html);
    }
}
