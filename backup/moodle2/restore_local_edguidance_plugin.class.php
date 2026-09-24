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
 * Restore of teacher guidance, attached to every activity.
 *
 * @package    local_edguidance
 * @category   backup
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores an activity's guidance blocks from its module.xml.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_edguidance_plugin extends restore_local_plugin {
    /** @var array<int, int> New block ids keyed by old, held until after_restore_module() maps them. */
    protected $blocks = [];

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
}
