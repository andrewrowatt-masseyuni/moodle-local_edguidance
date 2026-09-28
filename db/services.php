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
 * Web services for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// All flagged ajax and put in no service: they are called by this plugin's own JS as the logged-in
// user, never by an external client with a token.
$functions = [
    'local_edguidance_embed_preset' => [
        'classname' => 'local_edguidance\external\embed_preset',
        'description' => 'Create a guidance block that uses a site preset, for the editor to embed.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_edguidance_get_previews' => [
        'classname' => 'local_edguidance\external\get_previews',
        'description' => 'Render guidance blocks as the page shows them, for the editor to preview.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_edguidance_set_dismissed' => [
        'classname' => 'local_edguidance\external\set_dismissed',
        'description' => 'Dismiss or restore a guidance block for the current user.',
        'type' => 'write',
        'ajax' => true,
    ],
];
