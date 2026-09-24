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
require_once($CFG->dirroot . '/course/modlib.php');

/**
 * Tests for creating, adopting and deleting guidance blocks.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\api
 * @covers     ::local_edguidance_coursemodule_edit_post_actions
 * @covers     ::local_edguidance_pre_course_module_delete
 */
final class api_test extends \advanced_testcase {
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
     * An editor value with no files.
     *
     * @param string $text The text.
     * @return array
     */
    private function editor(string $text): array {
        return ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
    }

    /**
     * A new block with its own text, then edited.
     */
    public function test_save_own_text(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $context = \context_module::instance($book->cmid);

        $key = api::save_embed($context, null, 0, $this->editor('<p>First.</p>'));
        $row = $DB->get_record('local_edguidance', ['cmid' => $book->cmid, 'embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame((int)$course->id, (int)$row->courseid);
        $this->assertSame(0, (int)$row->presetslot);
        $this->assertStringContainsString('First.', $row->guidance);

        $this->assertSame($key, api::save_embed($context, $key, 0, $this->editor('<p>Second.</p>')));
        $this->assertStringContainsString('Second.', $DB->get_field('local_edguidance', 'guidance', ['id' => $row->id]));
        $this->assertSame(1, $DB->count_records('local_edguidance'));
    }

    /**
     * Using a preset links to the slot and keeps a snapshot; switching to own text unlinks.
     */
    public function test_use_preset_then_switch_to_own_text(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->generator()->set_preset(4, 'Groups', '<p>Check group mode.</p>');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $key = api::save_embed($context, null, 4);
        $row = $DB->get_record('local_edguidance', ['embedkey' => $key]);
        $this->assertSame(4, (int)$row->presetslot);
        $this->assertSame('<p>Check group mode.</p>', $row->guidance);

        api::save_embed($context, $key, 0, $this->editor('<p>My own version.</p>'));
        $row = $DB->get_record('local_edguidance', ['embedkey' => $key]);
        $this->assertSame(0, (int)$row->presetslot);
        $this->assertStringContainsString('My own version.', $row->guidance);
    }

    /**
     * A preset slot that is not in use is refused.
     */
    public function test_unused_preset_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->expectException(\invalid_parameter_exception::class);
        api::save_embed(\context_module::instance($page->cmid), null, 5);
    }

    /**
     * A key belonging to another activity cannot be used to edit that activity's block.
     */
    public function test_a_foreign_key_gets_a_new_block(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $second = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $theirs = $this->generator()->create_block(['cmid' => $first->cmid, 'guidance' => '<p>Theirs.</p>']);

        $key = api::save_embed(\context_module::instance($second->cmid), $theirs->embedkey, 0, $this->editor('<p>Mine.</p>'));

        $this->assertNotSame($theirs->embedkey, $key);
        $this->assertSame('<p>Theirs.</p>', $DB->get_field('local_edguidance', 'guidance', ['id' => $theirs->id]));
    }

    /**
     * Only an activity, or a course while an activity is being added, can hold a block.
     */
    public function test_embed_target(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertSame([(int)$course->id, (int)$page->cmid], api::embed_target(\context_module::instance($page->cmid)));
        $this->assertSame([(int)$course->id, 0], api::embed_target(\context_course::instance($course->id)));

        $this->expectException(\invalid_parameter_exception::class);
        api::embed_target(\context_system::instance());
    }

    /**
     * Saving an activity records which blocks are in its description, in order.
     *
     * Goes through create_module() and update_module(), so it also proves the settings-form
     * callback is wired up.
     */
    public function test_saving_an_activity_records_description_order(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        // A label: its update needs nothing but the intro, which keeps this about the callback.
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $a = $this->generator()->create_block(['cmid' => $label->cmid]);
        $b = $this->generator()->create_block(['cmid' => $label->cmid]);
        $chapter = $this->generator()->create_block(['cmid' => $label->cmid]);

        $this->update_intro((int)$label->cmid, token::html($b->embedkey) . '<p>Text</p>' . token::html($a->embedkey));

        $this->assertSame(2, (int)$DB->get_field('local_edguidance', 'introorder', ['id' => $a->id]));
        $this->assertSame(1, (int)$DB->get_field('local_edguidance', 'introorder', ['id' => $b->id]));
        $this->assertSame(0, (int)$DB->get_field('local_edguidance', 'introorder', ['id' => $chapter->id]));

        // Taking a token out of the description takes the block out of the card.
        $this->update_intro((int)$label->cmid, token::html($a->embedkey));

        $this->assertSame(1, (int)$DB->get_field('local_edguidance', 'introorder', ['id' => $a->id]));
        $this->assertSame(0, (int)$DB->get_field('local_edguidance', 'introorder', ['id' => $b->id]));
    }

    /**
     * A draft made while an activity was being added is adopted when it is saved, files and all.
     */
    public function test_a_draft_is_adopted_with_its_files(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $key = api::save_embed($coursecontext, null, 0, $this->editor('<p>Written before the quiz existed.</p>'));
        $draft = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(0, (int)$draft->cmid);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $coursecontext->id,
            'component' => 'local_edguidance',
            'filearea' => 'guidance',
            'itemid' => $draft->id,
            'filepath' => '/',
            'filename' => 'diagram.txt',
        ], 'content');

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'intro' => '<p>Quiz.</p>' . token::html($key),
        ]);

        $row = $DB->get_record('local_edguidance', ['id' => $draft->id], '*', MUST_EXIST);
        $this->assertSame((int)$quiz->cmid, (int)$row->cmid);
        $this->assertSame(1, (int)$row->introorder);

        $modulecontext = \context_module::instance($quiz->cmid);
        $this->assertTrue($fs->file_exists($modulecontext->id, 'local_edguidance', 'guidance', $draft->id, '/', 'diagram.txt'));
        $this->assertFalse($fs->file_exists($coursecontext->id, 'local_edguidance', 'guidance', $draft->id, '/', 'diagram.txt'));
    }

    /**
     * A draft in another course is not adopted, even with the right key.
     */
    public function test_drafts_are_scoped_to_their_course(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $elsewhere = $this->getDataGenerator()->create_course();
        $draft = $this->generator()->create_block(['courseid' => $elsewhere->id, 'cmid' => 0]);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'intro' => token::html($draft->embedkey),
        ]);

        $this->assertSame(0, (int)$DB->get_field('local_edguidance', 'cmid', ['id' => $draft->id]));
    }

    /**
     * Abandoned drafts are purged; recent ones and adopted blocks are not.
     */
    public function test_purge_drafts(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $old = $this->generator()->create_block(['courseid' => $course->id, 'timemodified' => time() - 2 * DAYSECS]);
        $recent = $this->generator()->create_block(['courseid' => $course->id]);
        $adopted = $this->generator()->create_block(['cmid' => $page->cmid, 'timemodified' => time() - 2 * DAYSECS]);

        $this->assertSame(1, api::purge_drafts(time() - api::DRAFT_LIFETIME));

        $this->assertFalse($DB->record_exists('local_edguidance', ['id' => $old->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $recent->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $adopted->id]));
    }

    /**
     * Deleting an activity deletes its blocks and everyone's dismissals of them.
     */
    public function test_deleting_an_activity_cleans_up(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $keep = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid]);
        $kept = $this->generator()->create_block(['cmid' => $keep->cmid]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        dismissed::set((int)$row->id, true);

        course_delete_module($page->cmid);

        $this->assertFalse($DB->record_exists('local_edguidance', ['id' => $row->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $kept->id]));
        $this->assertFalse($DB->record_exists('favourite', [
            'component' => dismissed::COMPONENT,
            'itemid' => $row->id,
        ]));
    }

    /**
     * Deleting a course deletes its blocks, drafts included.
     */
    public function test_deleting_a_course_cleans_up(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $otherpage = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);
        $this->generator()->create_block(['cmid' => $page->cmid]);
        $this->generator()->create_block(['courseid' => $course->id]);
        $this->generator()->create_block(['cmid' => $otherpage->cmid]);

        delete_course($course, false);

        $this->assertSame(0, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
        $this->assertSame(1, $DB->count_records('local_edguidance', ['courseid' => $other->id]));
    }

    /**
     * Change an activity's description through update_moduleinfo(), as the settings form does.
     *
     * @param int $cmid The course module.
     * @param string $intro The new description.
     */
    private function update_intro(int $cmid, string $intro): void {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        [$cm, , $module, $data] = get_moduleinfo_data($cm, get_course($cm->course));
        $data->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
        $data->modulename = $module->name;
        $data->coursemodule = $cmid;
        update_moduleinfo($cm, $data, get_course($cm->course));
    }
}
