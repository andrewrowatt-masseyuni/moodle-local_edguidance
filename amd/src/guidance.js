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
 * Dismissing and restoring teacher guidance.
 *
 * A dismissed block collapses to a small help icon rather than disappearing, and clicking the icon
 * restores it. Nothing is ever lost, so there is no page for finding dismissed guidance again.
 *
 * Every listener is delegated from the document, once per page, so blocks that arrive after the
 * page has loaded - a course page card re-rendered over AJAX - work without re-initialising.
 *
 * @module     local_edguidance/guidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import 'theme_boost/popover';
import $ from 'jquery';
import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

const SELECTORS = {
    BLOCK: '[data-region="edguidance"]',
    FULL: '[data-region="edguidance-full"]',
    COLLAPSED: '[data-region="edguidance-collapsed"]',
    DISMISS: '[data-action="edguidance-dismiss"]',
    RESTORE: '[data-action="edguidance-restore"]',
};

let initialised = false;

/**
 * Record a dismissal or a restore against the current user.
 *
 * @param {number} guidanceid The block.
 * @param {boolean} dismissed Whether it should be dismissed.
 * @returns {Promise}
 */
const setDismissed = (guidanceid, dismissed) => fetchMany([{
    methodname: 'local_edguidance_set_dismissed',
    args: {guidanceid, dismissed},
}])[0];

/**
 * Show the "Review teacher guidance" popover on a collapsed block's icon.
 *
 * Deliberately not data-toggle="popover": theme_boost/loader shows every such popover on click,
 * which would fight clicking the icon to restore. This one is manual - shown on hover and focus,
 * hidden on leaving, and never on click. The popover is created the first time it is needed.
 *
 * @param {HTMLElement} button The icon.
 */
const showPopover = (button) => {
    if (!button.dataset.edguidancePopover) {
        $(button).popover({
            trigger: 'manual',
            placement: 'top',
            container: 'body',
            content: button.getAttribute('aria-label'),
        });
        button.dataset.edguidancePopover = '1';
    }
    $(button).popover('show');
};

/**
 * Hide the popover, if it was ever shown.
 *
 * @param {HTMLElement} button The icon.
 */
const hidePopover = (button) => {
    if (button.dataset.edguidancePopover) {
        $(button).popover('hide');
    }
};

/**
 * Swap a block between its guidance and its help icon.
 *
 * @param {HTMLElement} block The block root.
 * @param {boolean} dismissed Whether to show the icon.
 */
const showState = (block, dismissed) => {
    const full = block.querySelector(SELECTORS.FULL);
    const collapsed = block.querySelector(SELECTORS.COLLAPSED);

    full?.toggleAttribute('hidden', dismissed);
    collapsed?.toggleAttribute('hidden', !dismissed);
    block.classList.toggle('edguidance-is-dismissed', dismissed);

    // Keep a keyboard user where they were, rather than dropping focus on the element just hidden.
    const target = dismissed ? collapsed : full?.querySelector(SELECTORS.DISMISS);
    target?.focus();
};

/**
 * Record the choice, then swap the block over.
 *
 * Written with try/catch rather than a promise chain because core/ajax hands back a jQuery
 * Deferred, not a native promise: it has no .finally(), and calling one would leave the Pending
 * unresolved and the page permanently "not ready".
 *
 * @param {HTMLElement} block The block root.
 * @param {boolean} dismissed Whether it should be dismissed.
 */
const apply = async(block, dismissed) => {
    const pending = new Pending('local_edguidance/guidance:setdismissed');

    try {
        await setDismissed(parseInt(block.dataset.guidanceid, 10), dismissed);
        showState(block, dismissed);
    } catch (error) {
        Notification.exception(error);
    }

    pending.resolve();
};

/**
 * Show or hide the popover for a hover or focus event on a collapsed block's icon.
 *
 * @param {Event} event The mouseover, mouseout, focusin or focusout event.
 * @param {boolean} show Whether this event shows the popover.
 */
const popoverFromEvent = (event, show) => {
    const button = event.target instanceof Element ? event.target.closest(SELECTORS.RESTORE) : null;
    if (!button) {
        return;
    }
    // Moving between the icon and its own children is not leaving it.
    if (!show && event.relatedTarget instanceof Node && button.contains(event.relatedTarget)) {
        return;
    }

    if (show) {
        showPopover(button);
    } else {
        hidePopover(button);
    }
};

/**
 * Wire up the page.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    // Capture phase, and stopped there: a block can sit inside a card that is itself clickable -
    // theme_snap turns whole resource cards into links - and dismissing guidance must not also open
    // the activity.
    document.addEventListener('click', (event) => {
        const control = event.target instanceof Element
            ? event.target.closest(`${SELECTORS.DISMISS}, ${SELECTORS.RESTORE}`)
            : null;
        const block = control?.closest(SELECTORS.BLOCK);
        if (!block) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const restoring = control.matches(SELECTORS.RESTORE);
        if (restoring) {
            hidePopover(control);
        }
        apply(block, !restoring);
    }, true);

    document.addEventListener('mouseover', (event) => popoverFromEvent(event, true));
    document.addEventListener('mouseout', (event) => popoverFromEvent(event, false));
    document.addEventListener('focusin', (event) => popoverFromEvent(event, true));
    document.addEventListener('focusout', (event) => popoverFromEvent(event, false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            popoverFromEvent(event, false);
        }
    });
};
