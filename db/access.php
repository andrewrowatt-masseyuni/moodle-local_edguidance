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
 * Capabilities for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
     * Who can read guidance. The omission of 'student' is the whole reason students never see it,
     * and there is no second line of defence: every render path - the filter, the course page card
     * and the file handler - checks this capability and nothing else.
     *
     * Unlike mod_ednote's view capability, this one does not make anything invisible in core. The
     * host activity stays visible to students; only the guidance block inside it is withheld. So
     * granting this to 'student' does not reveal a hidden activity, it reveals the guidance itself,
     * on every activity on the site.
     */
    'local/edguidance:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
     * Who can tick items on a guidance checklist. The ticks are shared - every teacher sees them - so
     * this is a write, but a narrow one: it changes only what is between an item's square brackets,
     * never any text, so it carries no XSS risk. Given to the same roles as view, so a non-editing
     * teacher can tick off their part; take it away to leave ticking to the editing teachers.
     */
    'local/edguidance:tick' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Who can add and edit guidance blocks. RISK_XSS because the guidance is rich text rendered to
    // other teachers.
    'local/edguidance:manage' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],
];
