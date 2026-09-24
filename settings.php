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
 * Site settings for teacher guidance: the guidance presets.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_edguidance', new lang_string('pluginname', 'local_edguidance'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_edguidance/presets',
            new lang_string('presets', 'local_edguidance'),
            new lang_string('presets_desc', 'local_edguidance')
        ));

        for ($slot = 1; $slot <= \local_edguidance\presets::MAX; $slot++) {
            $settings->add(new admin_setting_heading(
                'local_edguidance/preset' . $slot,
                new lang_string('presetn', 'local_edguidance', $slot),
                ''
            ));
            $settings->add(new admin_setting_configtext(
                'local_edguidance/presettitle' . $slot,
                new lang_string('presettitle', 'local_edguidance'),
                new lang_string('presettitle_desc', 'local_edguidance'),
                '',
                PARAM_TEXT,
                60
            ));
            $settings->add(new admin_setting_confightmleditor(
                'local_edguidance/presetguidance' . $slot,
                new lang_string('presetguidance', 'local_edguidance'),
                new lang_string('presetguidance_desc', 'local_edguidance'),
                ''
            ));
        }
    }
}
