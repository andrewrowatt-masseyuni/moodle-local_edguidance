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
 * Tests for guidance riding along with activity backup and restore.
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
     * Back a course up and restore it into a new course.
     *
     * @param int $courseid The course.
     * @return int The new course id.
     */
    private function backup_and_restore(int $courseid): int {
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

        $source = get_course($courseid);
        $newcourseid = \restore_dbops::create_new_course($source->fullname, $source->shortname . '_copy', $source->category);

        $restore = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
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
}
