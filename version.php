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
 * Version information for teacher guidance.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_edguidance';
$plugin->release = '0.2.0';
$plugin->version = 2026092800;
$plugin->requires = 2024100700;
// Pinned to 4.5 to match mod_edpreset and the rest of the ed* family, which are developed and tested
// together. The afterlink technique in classes/local/card_injector.php leans on core internals
// that a later release could move, so a wider range would be a claim nobody has tested.
$plugin->supported = [405, 405];
$plugin->maturity = MATURITY_BETA;
