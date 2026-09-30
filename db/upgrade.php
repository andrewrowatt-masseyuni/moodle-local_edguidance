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
 * Upgrade steps for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_edguidance_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026093000) {
        // Every existing block becomes a note, the default, with no heading.
        $table = new xmldb_table('local_edguidance');

        $field = new xmldb_field('category', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'note', 'presetslot');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('heading', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'category');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026093000, 'local', 'edguidance');
    }

    if ($oldversion < 2026100100) {
        // Adding, editing and moving guidance is for managers now, not editing teachers. Taken back only
        // where install put it - allowed at site level for an editing-teacher role - so overrides in
        // courses and categories are left alone. (A site-level allow looks the same whether install or
        // an administrator made it; an administrator who wants it back grants it again.)
        $systemcontext = context_system::instance();
        foreach (get_archetype_roles('editingteacher') as $role) {
            $allowed = $DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'contextid' => $systemcontext->id,
                'capability' => 'local/edguidance:manage',
                'permission' => CAP_ALLOW,
            ]);
            if ($allowed) {
                unassign_capability('local/edguidance:manage', $role->id, $systemcontext->id);
            }
        }

        upgrade_plugin_savepoint(true, 2026100100, 'local', 'edguidance');
    }

    return true;
}
