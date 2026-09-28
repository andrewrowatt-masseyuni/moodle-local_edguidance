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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for guidance riding along with activity and section backup and restore.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_edguidance_plugin
 * @covers     \restore_local_edguidance_plugin
 */
final class backup_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
    }

    /**
     * Back a course up and restore it into a new course, or add it to an existing one.
     *
     * @param int $courseid The course.
     * @param int $targetid A course to add it to, or 0 for a new course.
     * @return int The course restored into.
     */
    private function backup_and_restore(int $courseid, int $targetid = 0): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $backup = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backup->execute_plan();
        $results = $backup->get_results();
        $backupid = $backup->get_backupid();
        $backup->destroy();

        $path = make_backup_temp_directory($backupid);
        $results['backup_destination']->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);

        if ($targetid) {
            $newcourseid = $targetid;
        } else {
            $source = get_course($courseid);
            $newcourseid = \restore_dbops::create_new_course($source->fullname, $source->shortname . '_copy', $source->category);
        }

        $restore = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            $targetid ? \backup::TARGET_EXISTING_ADDING : \backup::TARGET_NEW_COURSE
        );
        $restore->execute_precheck();
        $restore->execute_plan();
        $restore->destroy();

        return $newcourseid;
    }

    /**
     * A book with a chapter block holding a file, and a description block using a preset.
     *
     * @return array [course, book, own-text row, preset row]
     */
    private function make_book(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');

        $own = $generator->create_block([
            'cmid' => $book->cmid,
            'guidance' => '<p>Chapter guidance <img src="@@PLUGINFILE@@/diagram.png" alt="Diagram"></p>',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($book->cmid)->id,
            'component' => 'local_edguidance',
            'filearea' => 'guidance',
            'itemid' => $own->id,
            'filepath' => '/',
            'filename' => 'diagram.png',
        ], 'not really a png');

        $preset = $generator->create_block(['cmid' => $book->cmid, 'presetslot' => 5, 'introorder' => 1]);

        return [$course, $book, $own, $preset];
    }

    /**
     * Blocks come across with their keys, preset slots, order and files unchanged.
     */
    public function test_blocks_survive_a_course_restore(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $own, $preset] = $this->make_book();

        $newcourseid = $this->backup_and_restore((int)$course->id);

        $newbook = get_fast_modinfo($newcourseid)->get_instances_of('book');
        $newcm = reset($newbook);
        $rows = $DB->get_records('local_edguidance', ['cmid' => $newcm->id], '', 'embedkey, id, courseid, presetslot, introorder');
        $this->assertCount(2, $rows);

        // The whole point: the same keys, so the tokens restored in the text still match.
        $this->assertArrayHasKey($own->embedkey, $rows);
        $this->assertArrayHasKey($preset->embedkey, $rows);
        $this->assertSame($newcourseid, (int)$rows[$own->embedkey]->courseid);
        // A slot names a site setting, not something in the course, so it is carried verbatim.
        $this->assertSame(5, (int)$rows[$preset->embedkey]->presetslot);
        $this->assertSame(1, (int)$rows[$preset->embedkey]->introorder);

        $this->assertTrue(get_file_storage()->file_exists(
            \context_module::instance($newcm->id)->id,
            'local_edguidance',
            'guidance',
            $rows[$own->embedkey]->id,
            '/',
            'diagram.png'
        ));

        // And the original is untouched.
        $this->assertSame(2, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
    }

    /**
     * Duplicating an activity copies its blocks onto the copy.
     */
    public function test_duplicating_an_activity_copies_its_blocks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $book, $own] = $this->make_book();

        $cm = get_fast_modinfo($course->id)->get_cm($book->cmid);
        $newcm = duplicate_module($course, $cm);

        $this->assertSame(2, $DB->count_records('local_edguidance', ['cmid' => $newcm->id]));
        $copy = $DB->get_record('local_edguidance', ['cmid' => $newcm->id, 'embedkey' => $own->embedkey], '*', MUST_EXIST);
        $this->assertTrue(get_file_storage()->file_exists(
            \context_module::instance($newcm->id)->id,
            'local_edguidance',
            'guidance',
            $copy->id,
            '/',
            'diagram.png'
        ));
        $this->assertSame(2, $DB->count_records('local_edguidance', ['cmid' => $book->cmid]));
    }

    /**
     * A course whose section 1 summary holds a block with a file, and section 2 one using a preset.
     *
     * @return array [course, section 1 row, section 2 row]
     */
    private function make_sections(): array {
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $modinfo = get_fast_modinfo($course);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');

        $own = $generator->create_block([
            'sectionid' => $modinfo->get_section_info(1)->id,
            'guidance' => '<p>Section guidance <img src="@@PLUGINFILE@@/diagram.png" alt="Diagram"></p>',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'local_edguidance',
            'filearea' => 'guidance',
            'itemid' => $own->id,
            'filepath' => '/',
            'filename' => 'diagram.png',
        ], 'not really a png');
        $preset = $generator->create_block(['sectionid' => $modinfo->get_section_info(2)->id, 'presetslot' => 3]);

        $this->set_summary((int)$course->id, 1, '<p>Week one.</p>' . token::html($own->embedkey));
        $this->set_summary((int)$course->id, 2, token::html($preset->embedkey));

        return [$course, $own, $preset];
    }

    /**
     * Change a section's summary.
     *
     * @param int $courseid The course.
     * @param int $sectionnum The section number.
     * @param string $summary The summary.
     */
    private function set_summary(int $courseid, int $sectionnum, string $summary): void {
        course_update_section($courseid, get_fast_modinfo($courseid)->get_section_info($sectionnum), ['summary' => $summary]);
    }

    /**
     * Section blocks come across with their keys, preset slots and files, against the new sections.
     */
    public function test_section_blocks_survive_a_course_restore(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $own, $preset] = $this->make_sections();

        $newcourseid = $this->backup_and_restore((int)$course->id);

        $modinfo = get_fast_modinfo($newcourseid);
        $rows = $DB->get_records('local_edguidance', ['courseid' => $newcourseid], '', 'embedkey, id, cmid, sectionid, presetslot');
        $this->assertCount(2, $rows);
        $this->assertSame((int)$modinfo->get_section_info(1)->id, (int)$rows[$own->embedkey]->sectionid);
        $this->assertSame((int)$modinfo->get_section_info(2)->id, (int)$rows[$preset->embedkey]->sectionid);
        $this->assertSame(0, (int)$rows[$own->embedkey]->cmid);
        $this->assertSame(3, (int)$rows[$preset->embedkey]->presetslot);
        $this->assertSame([$own->embedkey], token::keys_in($modinfo->get_section_info(1)->summary));

        $this->assertTrue(get_file_storage()->file_exists(
            \context_course::instance($newcourseid)->id,
            'local_edguidance',
            'guidance',
            $rows[$own->embedkey]->id,
            '/',
            'diagram.png'
        ));

        $this->assertSame(2, $DB->count_records('local_edguidance', ['courseid' => $course->id]));
    }

    /**
     * Added to a course that already uses a key elsewhere, a section's block takes a new one; a
     * section that keeps its own summary gets no blocks it cannot show.
     */
    public function test_section_blocks_merged_into_a_course(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $own, $preset] = $this->make_sections();

        // The target: section 1 empty, so it takes the backup's summary; section 2 keeps its own;
        // section 3 already holds a block with the key the backup's section 1 uses.
        $target = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $this->set_summary((int)$target->id, 2, '<p>Our own week two.</p>');
        $theirs = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'sectionid' => get_fast_modinfo($target)->get_section_info(3)->id,
            'embedkey' => $own->embedkey,
        ]);
        $this->set_summary((int)$target->id, 3, token::html($own->embedkey));

        $this->backup_and_restore((int)$course->id, (int)$target->id);

        $modinfo = get_fast_modinfo($target->id);
        $keys = token::keys_in($modinfo->get_section_info(1)->summary);
        $this->assertCount(1, $keys);
        $this->assertNotSame($own->embedkey, $keys[0]);

        $restored = $DB->get_record('local_edguidance', ['courseid' => $target->id, 'embedkey' => $keys[0]], '*', MUST_EXIST);
        $this->assertSame((int)$modinfo->get_section_info(1)->id, (int)$restored->sectionid);
        $this->assertTrue(get_file_storage()->file_exists(
            \context_course::instance($target->id)->id,
            'local_edguidance',
            'guidance',
            $restored->id,
            '/',
            'diagram.png'
        ));

        // Section 3's block is untouched, and section 2 got nothing.
        $this->assertSame([$own->embedkey], token::keys_in($modinfo->get_section_info(3)->summary));
        $this->assertSame((int)$theirs->id, (int)$DB->get_field('local_edguidance', 'id', [
            'courseid' => $target->id,
            'embedkey' => $own->embedkey,
        ]));
        $this->assertFalse($DB->record_exists('local_edguidance', ['courseid' => $target->id, 'embedkey' => $preset->embedkey]));
        $this->assertSame(2, $DB->count_records('local_edguidance', ['courseid' => $target->id]));

        // Claims were held only while the restore ran: a token pasted in now is claimed as usual.
        $this->set_summary((int)$target->id, 2, token::html($keys[0]));
        $this->assertNotSame([$keys[0]], token::keys_in(get_fast_modinfo($target->id)->get_section_info(2)->summary));
    }
}
