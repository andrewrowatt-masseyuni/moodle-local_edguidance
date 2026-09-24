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
 * Tests for resolving the text a guidance block displays.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\guidance
 */
final class guidance_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
    }

    /**
     * The generator.
     *
     * @return \local_edguidance_generator
     */
    private function generator(): \local_edguidance_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_edguidance');
    }

    /**
     * A block with its own text shows it.
     */
    public function test_own_text(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid, 'guidance' => '<p>Own words.</p>']);

        $resolved = guidance::resolve($row);

        $this->assertStringContainsString('Own words.', $resolved->content);
        $this->assertFalse($resolved->missing);
    }

    /**
     * A block using a preset shows the preset as it is now, not as it was when linked.
     */
    public function test_preset_is_live(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->generator()->set_preset(2, 'Due dates', '<p>First wording.</p>');
        $row = $this->generator()->create_block([
            'cmid' => $page->cmid,
            'presetslot' => 2,
            'guidance' => '<p>First wording.</p>',
        ]);

        $this->generator()->set_preset(2, 'Due dates', '<p>Second wording.</p>');
        $resolved = guidance::resolve($row);

        $this->assertStringContainsString('Second wording.', $resolved->content);
        $this->assertStringNotContainsString('First wording.', $resolved->content);
        $this->assertFalse($resolved->missing);
    }

    /**
     * Emptying a preset's slot falls back to the snapshot, flagged as possibly stale.
     */
    public function test_emptied_slot_falls_back_to_snapshot(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block([
            'cmid' => $page->cmid,
            'presetslot' => 3,
            'guidance' => '<p>The snapshot.</p>',
        ]);

        $resolved = guidance::resolve($row);

        $this->assertStringContainsString('The snapshot.', $resolved->content);
        $this->assertTrue($resolved->missing);
    }

    /**
     * Blank text resolves to nothing.
     */
    public function test_blank_text_resolves_to_nothing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid, 'guidance' => '<p></p>']);

        $this->assertSame('', guidance::resolve($row)->content);
    }

    /**
     * A course costs one read however many activities in it ask, and drafts are left out.
     */
    public function test_a_course_costs_one_query(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cmids = [];
        for ($i = 0; $i < 4; $i++) {
            $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
            $this->generator()->create_block(['cmid' => $page->cmid, 'introorder' => 1]);
            $cmids[] = (int)$page->cmid;
        }
        $this->generator()->create_block(['courseid' => $course->id, 'cmid' => 0]);
        guidance::reset_cache();

        $before = $DB->perf_get_reads();
        foreach ($cmids as $cmid) {
            $this->assertCount(1, guidance::for_cm((int)$course->id, $cmid));
            $this->assertCount(1, guidance::for_intro((int)$course->id, $cmid));
        }
        $this->assertSame(1, $DB->perf_get_reads() - $before);

        $this->assertArrayNotHasKey(0, guidance::for_course((int)$course->id));
    }

    /**
     * Description blocks come back in description order, and chapter or page blocks do not.
     */
    public function test_for_intro_order(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $second = $this->generator()->create_block(['cmid' => $book->cmid, 'introorder' => 2]);
        $this->generator()->create_block(['cmid' => $book->cmid, 'introorder' => 0]);
        $first = $this->generator()->create_block(['cmid' => $book->cmid, 'introorder' => 1]);

        $ids = array_map(fn($row) => (int)$row->id, guidance::for_intro((int)$course->id, (int)$book->cmid));

        $this->assertSame([(int)$first->id, (int)$second->id], $ids);
    }
}
