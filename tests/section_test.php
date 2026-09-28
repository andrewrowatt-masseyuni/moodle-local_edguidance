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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for guidance in section summaries.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\api
 * @covers     \local_edguidance\observer
 */
final class section_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
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
     * A course whose section 1 summary holds a block with a file in its own text.
     *
     * @return array [course, section 1 info, row]
     */
    private function make_section(): array {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $section = get_fast_modinfo($course)->get_section_info(1);

        $row = $this->generator()->create_block([
            'sectionid' => $section->id,
            'guidance' => '<p>Section guidance <img src="@@PLUGINFILE@@/diagram.png" alt="Diagram"></p>',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'local_edguidance',
            'filearea' => 'guidance',
            'itemid' => $row->id,
            'filepath' => '/',
            'filename' => 'diagram.png',
        ], 'not really a png');

        $this->set_summary($course, 1, '<p>Week one.</p>' . token::html($row->embedkey));

        return [$course, get_fast_modinfo($course)->get_section_info(1), $row];
    }

    /**
     * Change a section's summary as the section form does, firing the event.
     *
     * @param \stdClass $course The course.
     * @param int $sectionnum The section number.
     * @param string $summary The new summary.
     */
    private function set_summary(\stdClass $course, int $sectionnum, string $summary): void {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_update_section($course, $section, ['summary' => $summary, 'summaryformat' => FORMAT_HTML]);
    }

    /**
     * Whether a block has its file.
     *
     * @param \stdClass $course The course.
     * @param int $rowid The block.
     * @return bool
     */
    private function has_file(\stdClass $course, int $rowid): bool {
        return get_file_storage()->file_exists(
            \context_course::instance($course->id)->id,
            'local_edguidance',
            'guidance',
            $rowid,
            '/',
            'diagram.png'
        );
    }

    /**
     * Guidance saved from a section's editor belongs to the section, not to a draft.
     */
    public function test_saving_in_a_section(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($course)->get_section_info(1);
        $context = \context_course::instance($course->id);

        $editor = ['text' => '<p>Mine.</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
        $key = api::save_embed($context, null, 0, $editor, (int)$section->id);

        $row = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(0, (int)$row->cmid);
        $this->assertSame((int)$section->id, (int)$row->sectionid);
        $this->assertSame('<p>Mine.</p>', $row->guidance);

        // Editing it finds it by the section, and not as a draft.
        $this->assertSame($key, api::save_embed($context, $key, 0, $editor, (int)$section->id));
        $this->assertNull(api::get_embed($context, $key));
    }

    /**
     * A section named with a course context it is not in is refused.
     */
    public function test_a_section_from_another_course_is_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($other)->get_section_info(1);

        $this->expectException(\invalid_parameter_exception::class);
        api::embed_target(\context_course::instance($course->id), (int)$section->id);
    }

    /**
     * A section's block has cm id 0 like a draft, but is neither adopted by an activity nor purged.
     */
    public function test_section_blocks_are_not_drafts(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $row] = $this->make_section();
        $DB->set_field('local_edguidance', 'timemodified', time() - 2 * DAYSECS, ['id' => $row->id]);

        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'intro' => token::html($row->embedkey),
        ]);
        api::purge_drafts(time() - api::DRAFT_LIFETIME);

        $row = $DB->get_record('local_edguidance', ['id' => $row->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$row->cmid);
        $this->assertSame(0, (int)$row->introorder);
    }

    /**
     * Duplicating a section gives the copy a block of its own, with its own key and files.
     */
    public function test_duplicating_a_section_copies_its_blocks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $section, $row] = $this->make_section();
        dismissed::set((int)$row->id, true);

        $copy = course_get_format($course)->duplicate_section($section);

        $keys = token::keys_in($copy->summary);
        $this->assertCount(1, $keys);
        $this->assertNotSame($row->embedkey, $keys[0]);
        $this->assertStringStartsWith('<p>Week one.</p>', $copy->summary);

        $new = $DB->get_record('local_edguidance', ['embedkey' => $keys[0]], '*', MUST_EXIST);
        $this->assertSame((int)$copy->id, (int)$new->sectionid);
        $this->assertSame($row->guidance, $new->guidance);
        $this->assertTrue($this->has_file($course, (int)$new->id));
        // A new block, so nobody has dismissed it yet.
        $this->assertFalse(dismissed::is_dismissed((int)$new->id));

        // The original is untouched.
        $this->assertSame(
            [$row->embedkey],
            token::keys_in(get_fast_modinfo($course)->get_section_info_by_id($section->id)->summary)
        );
        $this->assertSame((int)$section->id, (int)$DB->get_field('local_edguidance', 'sectionid', ['id' => $row->id]));
        $this->assertTrue($this->has_file($course, (int)$row->id));

        // And the copy shows its own block.
        $this->assertSame((int)$new->id, (int)guidance::for_sections((int)$course->id)[$keys[0]]->id);
    }

    /**
     * A token pasted from one section into another becomes a copy, as a duplicate does.
     */
    public function test_pasting_a_token_into_another_section_copies_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $row] = $this->make_section();

        $this->set_summary($course, 2, token::html($row->embedkey) . '<p>Week two.</p>');

        $section2 = get_fast_modinfo($course)->get_section_info(2);
        $keys = token::keys_in($section2->summary);
        $this->assertCount(1, $keys);
        $this->assertNotSame($row->embedkey, $keys[0]);
        $this->assertSame(
            (int)$section2->id,
            (int)$DB->get_field('local_edguidance', 'sectionid', ['embedkey' => $keys[0]], MUST_EXIST)
        );
        $this->assertSame(2, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
    }

    /**
     * Saving a section that holds only its own blocks changes nothing.
     */
    public function test_saving_a_section_again_changes_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $section, $row] = $this->make_section();

        $this->set_summary($course, 1, $section->summary . '<p>More.</p>');

        $this->assertSame([$row->embedkey], token::keys_in(get_fast_modinfo($course)->get_section_info(1)->summary));
        $this->assertSame(1, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
    }

    /**
     * Tokens that are not a section's block in this course are left for the filter to ignore.
     */
    public function test_other_tokens_are_left_alone(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, , $row] = $this->make_section();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $activity = $this->generator()->create_block(['cmid' => $page->cmid]);

        $summary = token::html($row->embedkey) . token::html($activity->embedkey);
        $this->set_summary($course, 1, $summary);

        $this->assertSame($summary, get_fast_modinfo($course)->get_section_info(1)->summary);
        $this->assertSame(1, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
    }

    /**
     * Deleting a section deletes its blocks, their files and everyone's dismissals of them.
     */
    public function test_deleting_a_section_cleans_up(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $row] = $this->make_section();
        $kept = $this->generator()->create_block(['sectionid' => get_fast_modinfo($course)->get_section_info(2)->id]);
        dismissed::set((int)$row->id, true);

        course_delete_section($course, 1);

        $this->assertFalse($DB->record_exists('local_edguidance', ['id' => $row->id]));
        $this->assertFalse($this->has_file($course, (int)$row->id));
        $this->assertFalse($DB->record_exists('favourite', ['component' => dismissed::COMPONENT, 'itemid' => $row->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $kept->id]));
    }

    /**
     * Deleting a course's contents deletes its sections' blocks, but not its drafts or other courses'.
     */
    public function test_deleting_course_contents_cleans_up_sections(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $row] = $this->make_section();
        $draft = $this->generator()->create_block(['courseid' => $course->id]);
        [, , $other] = $this->make_section();

        remove_course_contents($course->id, false);

        $this->assertFalse($DB->record_exists('local_edguidance', ['id' => $row->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $draft->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $other->id]));
    }
}
