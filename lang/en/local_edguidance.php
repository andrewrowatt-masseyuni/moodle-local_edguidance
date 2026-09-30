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

$string['backtocourse'] = 'Back to the course';
$string['category'] = 'Category';
$string['category_help'] = 'How the guidance is presented: a note (yellow), a recommendation (blue), a task (red) or an optional task (orange). A teacher marks a task or an optional task as complete, and anything else as read. Either way it is then hidden from that teacher only. The category can be changed at any time.';
$string['categorynote'] = 'Note';
$string['categoryoptionaltask'] = 'Optional task';
$string['categoryrecommendation'] = 'Recommendation';
$string['categorytask'] = 'Task';
$string['checklistchanged'] = 'This checklist has changed since the page was loaded, so nothing was ticked. Reload the page to see it as it is now.';
$string['checklistpreset'] = 'This checklist is part of a site guidance preset, so it cannot be ticked.';
$string['completedconfirm'] = 'You have marked this task as complete. When you revisit this page, it will be removed.';
$string['dismissed'] = 'Teacher guidance marked as read';
$string['dismissed_desc'] = 'Teacher guidance you have marked as read is not shown to you anywhere in this course. Restore it to see it again. This only changes what you see, never what your colleagues see.';
$string['dismissedconfirm'] = 'You have marked this teacher guidance as read. When you revisit this page, it will be removed.';
$string['dismissednone'] = 'You have not marked any teacher guidance as read in this course.';
$string['dismissedundo'] = 'If you didn\'t mean to do that you can undo this action.';
$string['edguidance:manage'] = 'Add and edit teacher guidance';
$string['edguidance:tick'] = 'Tick items on teacher guidance checklists';
$string['edguidance:view'] = 'View teacher guidance';
$string['eventchecklistitemchecked'] = 'Teacher guidance checklist item ticked';
$string['eventchecklistitemunchecked'] = 'Teacher guidance checklist item unticked';
$string['eventguidancecreated'] = 'Teacher guidance created';
$string['eventguidanceupdated'] = 'Teacher guidance updated';
$string['formcompleted'] = 'You have marked this task as complete, so it is hidden from you on the page and, unless you choose Show guidance marked as read, in the editor. Restore it to see it again.';
$string['formdismissed'] = 'You have marked this teacher guidance as read, so it is hidden from you on the page and, unless you choose Show guidance marked as read, in the editor. Restore it to see it again.';
$string['guidance'] = 'Teacher guidance';
$string['guidance_help'] = 'To add a checklist that teachers can tick, start each item on a line of its own with [ ] and a space, then the item:

[ ] Set the due date

Write [x] for an item that starts ticked. Ticks are shared: every teacher sees the same ones.';
$string['guidancemissing'] = 'The site guidance preset this used is no longer available, so this may be out of date.';
$string['heading'] = 'Heading';
$string['markascomplete'] = 'Mark as complete';
$string['markasread'] = 'Mark as read';
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
$string['privacy:metadata:favourites'] = 'Teacher guidance that a user has marked as read.';
$string['privacy:path:dismissed'] = 'Teacher guidance marked as read';
$string['restore'] = 'Restore';
$string['restored'] = 'The teacher guidance has been restored.';
$string['restoreguidance'] = 'Restore teacher guidance in {$a}';
$string['source'] = 'Guidance';
$string['source_help'] = 'Use a preset to show site-wide guidance that stays up to date and cannot be edited here. Choose "My own text" to write your own, or to edit a copy of a preset.';
$string['sourceown'] = 'My own text';
$string['sourcepreset'] = 'Use preset: {$a}';
$string['task:purgedrafts'] = 'Delete abandoned teacher guidance drafts';
$string['undo'] = 'Undo';
