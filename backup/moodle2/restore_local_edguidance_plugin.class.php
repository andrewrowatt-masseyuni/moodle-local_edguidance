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

/**
 * Restore of teacher guidance, attached to every activity and every section.
 *
 * @package    local_edguidance
 * @category   backup
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores an activity's guidance blocks from its module.xml, and a section's from its section.xml.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_edguidance_plugin extends restore_local_plugin {
    /** @var array<int, int> New block ids keyed by old, held until after_restore_module() maps them. */
    protected $blocks = [];

    /** @var array<int, int> New section block ids keyed by old, held until after_restore_section(). */
    protected $sectionblocks = [];

    /**
     * Paths at the module level.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element($this->get_namefor('block'), $this->get_pathfor('/blocks/block')),
        ];
    }

    /**
     * Restore one block against the new course module.
     *
     * @param array|stdClass $data The block as backed up.
     */
    public function process_local_edguidance_block($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);

        $data->courseid = $this->task->get_courseid();
        $data->cmid = $this->task->get_moduleid();

        // A key already used in this module would break the unique index. It cannot happen with a
        // fresh module, but a restore merged into an existing one is not worth a fatal error: the
        // existing block wins, and the token resolves to it.
        if ($DB->record_exists('local_edguidance', ['cmid' => $data->cmid, 'embedkey' => $data->embedkey])) {
            return;
        }

        // Not mapped yet: see after_restore_module().
        $this->blocks[$oldid] = $DB->insert_record('local_edguidance', $data);
    }

    /**
     * Map the blocks, then restore their files.
     *
     * Both wait until now. This plugin hangs off the module structure step, which runs before the
     * activity's own structure step has told the task the module's old context id - and a mapping
     * made for file restore records that id, so one made in process_local_edguidance_block() would
     * record 0 and match no file. By after_restore every task knows its contexts, and the temp
     * tables and the backup's files are still there.
     */
    public function after_restore_module() {
        if (!$this->blocks) {
            return;
        }

        foreach ($this->blocks as $oldid => $newid) {
            $this->set_mapping('local_edguidance_block', $oldid, $newid, true);
        }
        $this->add_related_files('local_edguidance', 'guidance', 'local_edguidance_block');
    }

    /**
     * Paths at the section level.
     *
     * Also holds off local_edguidance\api::claim_section_summary() for the course until the section
     * is done. Merging into an existing section fires course_section_updated before this plugin has
     * seen the section's blocks, and a claim then would give the section a copy of whichever of
     * the course's blocks already has the key, rather than the block in the backup. This is the
     * last moment before that event; process_local_edguidance_sectionblock() sorts the keys out.
     *
     * @return restore_path_element[]
     */
    protected function define_section_plugin_structure() {
        \local_edguidance\api::hold_claims($this->task->get_courseid(), true);

        return [
            new restore_path_element($this->get_namefor('sectionblock'), $this->get_pathfor('/blocks/block')),
        ];
    }

    /**
     * Restore one block against the new, or merged-into, section.
     *
     * The section's summary is already in place, so it decides:
     *
     * * a key the summary does not hold is skipped. That is a merge into an existing section that
     *   kept its own summary, where the block would have no token to show it;
     * * a key already used by this section's own block is skipped, and the token resolves to it;
     * * a key used anywhere else in the course - restoring a section back into the course it came
     *   from - would break the unique index and give two sections one key, which the filter cannot
     *   tell apart. The restored block takes a new key, and the summary follows it.
     *
     * @param array|stdClass $data The block as backed up.
     */
    public function process_local_edguidance_sectionblock($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);

        $data->courseid = $this->task->get_courseid();
        $data->cmid = 0;
        $data->sectionid = $this->task->get_sectionid();
        $data->introorder = 0;

        $summary = (string)$DB->get_field('course_sections', 'summary', ['id' => $data->sectionid]);
        if (!in_array($data->embedkey, \local_edguidance\token::keys_in($summary), true)) {
            return;
        }

        $taken = $DB->get_field('local_edguidance', 'sectionid', [
            'courseid' => $data->courseid,
            'cmid' => 0,
            'embedkey' => $data->embedkey,
        ]);
        if ($taken !== false && (int)$taken === (int)$data->sectionid) {
            return;
        }
        if ($taken !== false) {
            $newkey = \local_edguidance\token::new_key();
            $summary = \local_edguidance\token::rekey($summary, [$data->embedkey => $newkey]);
            $DB->set_field('course_sections', 'summary', $summary, ['id' => $data->sectionid]);
            $data->embedkey = $newkey;
        }

        // Not mapped yet, as for a module's blocks.
        $this->sectionblocks[$oldid] = $DB->insert_record('local_edguidance', $data);
    }

    /**
     * The section is done: let claims through again.
     */
    public function after_execute_section() {
        \local_edguidance\api::hold_claims($this->task->get_courseid(), false);
    }

    /**
     * Map the section's blocks, then restore their files, as after_restore_module() does.
     */
    public function after_restore_section() {
        if (!$this->sectionblocks) {
            return;
        }

        foreach ($this->sectionblocks as $oldid => $newid) {
            $this->set_mapping('local_edguidance_sectionblock', $oldid, $newid, true);
        }
        $this->add_related_files('local_edguidance', 'guidance', 'local_edguidance_sectionblock');
    }
}
