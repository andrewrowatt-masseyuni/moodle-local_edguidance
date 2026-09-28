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
 * Backup of teacher guidance, attached to every activity and every section.
 *
 * @package    local_edguidance
 * @category   backup
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds an activity's guidance blocks to its module.xml, and a section's to its section.xml.
 *
 * This is why guidance is a local plugin rather than an activity module: core only lets format,
 * report, plagiarism, local and tool plugins add data to another module's backup. Riding along
 * here covers course backup and restore, import, course copy, duplicating an activity, the recycle
 * bin, and mod_edpreset's restore-based copies, with nothing else to wire up. Duplicating a section
 * is not a backup; see api::claim_section_summary().
 *
 * embedkey and presetslot are carried verbatim. The key is what the token in the description,
 * chapter, page or summary matches on, and that text is restored unchanged; the slot names a site
 * setting, not something in the course.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_edguidance_plugin extends backup_local_plugin {
    /**
     * Guidance at the module level.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        return $this->define_blocks(['cmid' => backup::VAR_MODID]);
    }

    /**
     * Guidance at the section level. Its files are in the course context, which is the section
     * task's context too.
     *
     * @return backup_plugin_element
     */
    protected function define_section_plugin_structure() {
        return $this->define_blocks(['courseid' => backup::VAR_COURSEID, 'sectionid' => backup::VAR_SECTIONID]);
    }

    /**
     * The blocks matching a source, with their files.
     *
     * @param array $source The source table's conditions.
     * @return backup_plugin_element
     */
    protected function define_blocks(array $source) {
        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $blocks = new backup_nested_element('blocks');
        $block = new backup_nested_element('block', ['id'], [
            'embedkey',
            'introorder',
            'presetslot',
            'guidance',
            'guidanceformat',
            'timecreated',
            'timemodified',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($blocks);
        $blocks->add_child($block);

        $block->set_source_table('local_edguidance', $source);
        $block->annotate_files('local_edguidance', 'guidance', 'id');

        return $plugin;
    }
}
