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
 * Strings for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['dismiss'] = 'Dismiss';
$string['dismissguidance'] = 'Dismiss this teacher guidance';
$string['edguidance:manage'] = 'Add and edit teacher guidance';
$string['edguidance:view'] = 'View teacher guidance';
$string['guidance'] = 'Teacher guidance';
$string['guidancemissing'] = 'The site guidance preset this used is no longer available, so this may be out of date.';
$string['onlyteachers'] = 'Only teachers see this';
$string['pluginname'] = 'Teacher guidance';
$string['presetguidance'] = 'Guidance';
$string['presetguidance_desc'] = 'Shown wherever this preset is used. Changes appear everywhere straight away.';
$string['presetn'] = 'Preset {$a}';
$string['presetpreview'] = 'Preset guidance';
$string['presets'] = 'Guidance presets';
$string['presets_desc'] = 'Up to ten pieces of guidance that teachers can add to any activity, book chapter, lesson page or section summary. A teacher can either use a preset as it stands - it cannot be edited, and it updates whenever you change it here - or start from a copy of it and edit that. A preset needs both a title and some guidance to be offered.

Each slot is its own identity: guidance that uses slot 3 shows whatever slot 3 holds. To retire a preset, empty its slot rather than replacing it with something unrelated. Guidance that used an emptied slot keeps showing its last copy, marked as possibly out of date.';
$string['presettitle'] = 'Title';
$string['presettitle_desc'] = 'Short name shown to teachers when choosing a preset.';
$string['presetunavailable'] = 'That preset is no longer available. Choose another, or write your own.';
$string['previewempty'] = 'This teacher guidance has no text, so nobody will see it. Click to write some, or delete it.';
$string['previewnotfound'] = 'This teacher guidance belongs to another activity or section, or has been removed, so it will not show here. Click to write new guidance in its place, or delete it.';
$string['privacy:metadata:favourites'] = 'Teacher guidance blocks that a user has dismissed.';
$string['privacy:path:dismissed'] = 'Dismissed teacher guidance';
$string['review'] = 'Review teacher guidance for this activity';
$string['reviewsection'] = 'Review teacher guidance for this section';
$string['source'] = 'Guidance';
$string['source_help'] = 'Use a preset to show site-wide guidance that stays up to date and cannot be edited here. Choose "My own text" to write your own, or to edit a copy of a preset.';
$string['sourceown'] = 'My own text';
$string['sourcepreset'] = 'Use preset: {$a}';
$string['task:purgedrafts'] = 'Delete abandoned teacher guidance drafts';
