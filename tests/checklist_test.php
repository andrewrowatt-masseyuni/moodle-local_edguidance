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

namespace local_edguidance;

/**
 * Tests for checklists in guidance text.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\checklist
 */
final class checklist_test extends \advanced_testcase {
    /**
     * Each item's state and text, for comparing.
     *
     * @param string $text HTML.
     * @return string[] One "[x] text" or "[ ] text" per item.
     */
    private function summary(string $text): array {
        return array_map(fn($item) => ($item->checked ? '[x] ' : '[ ] ') . $item->text, checklist::items($text));
    }

    /**
     * Format as the page does, with the boxes tickable.
     *
     * @param string $text HTML.
     * @param bool $tickable Whether the boxes can be ticked.
     * @return string
     */
    private function format(string $text, bool $tickable = true): string {
        return checklist::format($text, FORMAT_HTML, \context_system::instance(), $tickable);
    }

    /**
     * Where items are found, and where they are not.
     *
     * @return array
     */
    public static function items_provider(): array {
        return [
            'lines in a paragraph' => ['<p>[ ] One<br>[x] Two<br />[X] Three</p>', ['[ ] One', '[x] Two', '[x] Three']],
            'a paragraph each' => ['<p>[ ] One</p><p>[x] Two</p>', ['[ ] One', '[x] Two']],
            'list items' => ['<ul><li>[ ] One</li><li>[x] Two</li></ul>', ['[ ] One', '[x] Two']],
            'non-breaking spaces' => ['<p>[&nbsp;]&nbsp;One</p><p>[x]&#160;Two</p>', ['[ ] One', '[x] Two']],
            'inside inline formatting' => ['<p><strong>[ ] One</strong></p>', ['[ ] One']],
            'after a break inside formatting' => ['<p><em>[ ] One<br></em>[ ] Two</p>', ['[ ] One', '[ ] Two']],
            'formatting and entities in the text' => [
                '<p>[ ] Check <em>groups</em> &amp; dates</p>',
                ['[ ] Check groups & dates'],
            ],
            'mid-line' => ['<p>Not an item [ ] here</p>', []],
            'a leading dash' => ['<p>- [ ] Not an item</p><ul><li>- [ ] Nor this</li></ul>', []],
            'no space after the box' => ['<p>[ ]Not an item</p>', []],
            'something else in the box' => ['<p>[y] Not an item</p>', []],
        ];
    }

    /**
     * Items are lines that start with a box.
     *
     * @dataProvider items_provider
     * @param string $text HTML.
     * @param string[] $expected What summary() should make of it.
     */
    public function test_items(string $text, array $expected): void {
        $this->assertSame($expected, $this->summary($text));
    }

    /**
     * An item's hash depends on its text and not on whether it is ticked, so a tick does not make
     * the page that sent it stale.
     */
    public function test_hash_ignores_the_tick(): void {
        [$unticked] = checklist::items('<p>[ ] Set the date</p>');
        [$ticked] = checklist::items('<p>[x] Set the date</p>');
        [$other] = checklist::items('<p>[ ] Set the time</p>');

        $this->assertSame($unticked->hash, $ticked->hash);
        $this->assertNotSame($unticked->hash, $other->hash);
    }

    /**
     * Setting an item changes what is between its brackets and nothing else.
     */
    public function test_set(): void {
        $text = '<p>Intro [ ] not this</p><ul><li>[ ] One</li><li>[X] Two</li></ul><p>[&nbsp;]&nbsp;Three</p>';

        $this->assertSame(
            '<p>Intro [ ] not this</p><ul><li>[x] One</li><li>[X] Two</li></ul><p>[&nbsp;]&nbsp;Three</p>',
            checklist::set($text, 0, true)
        );
        $this->assertSame(
            '<p>Intro [ ] not this</p><ul><li>[ ] One</li><li>[ ] Two</li></ul><p>[&nbsp;]&nbsp;Three</p>',
            checklist::set($text, 1, false)
        );
        $this->assertSame(
            '<p>Intro [ ] not this</p><ul><li>[ ] One</li><li>[X] Two</li></ul><p>[x]&nbsp;Three</p>',
            checklist::set($text, 2, true)
        );
    }

    /**
     * There is no setting an item that is not there.
     */
    public function test_set_a_missing_item(): void {
        $this->expectException(\coding_exception::class);
        checklist::set('<p>[ ] One</p>', 1, true);
    }

    /**
     * Each item becomes a labelled checkbox carrying its number and hash, with the rest of its line.
     */
    public function test_format_lines(): void {
        $text = '<p>Before:<br>[ ] Set the <em>date</em><br>[x] Tāne &amp; co</p><p>After.</p>';
        [$first, $second] = checklist::items($text);

        $html = $this->format($text);

        $this->assertStringContainsString('<p>Before:<br><label class="edguidance-checkitem">', $html);
        $this->assertStringContainsString(
            '<input type="checkbox" class="edguidance-check" data-action="edguidance-check" data-checkindex="0" ' .
            'data-checkhash="' . $first->hash . '"><span class="edguidance-checktext"> Set the <em>date</em></span></label>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" class="edguidance-check" checked data-action="edguidance-check" data-checkindex="1" ' .
            'data-checkhash="' . $second->hash . '"><span class="edguidance-checktext"> Tāne &amp; co</span></label></p>',
            $html
        );
        // The line break that ended the first item goes: the label is a line of its own.
        $this->assertStringNotContainsString('</label><br>', $html);
        $this->assertStringContainsString('<p>After.</p>', $html);
        $this->assertStringNotContainsString('[ ]', $html);
        $this->assertStringNotContainsString('edgcheck', $html);
    }

    /**
     * An item in a list is a box in its list item, and the list is left as it is.
     */
    public function test_format_lists(): void {
        $html = $this->format('<ul><li>[ ] One</li><li>[ ] Two<ul><li>[ ] Two a</li></ul></li><li>Just a bullet</li></ul>');

        $this->assertSame(3, substr_count($html, '<li><label class="edguidance-checkitem">'));
        $this->assertStringContainsString('<li>Just a bullet</li>', $html);
        // The nested list is not swallowed into its parent item's label.
        $this->assertStringContainsString('Two</span></label><ul>', $html);
    }

    /**
     * Where the boxes cannot be ticked they are disabled, carry nothing to tick them with, and say
     * why if there is a reason to give.
     */
    public function test_format_untickable(): void {
        $html = checklist::format('<p>[x] One</p>', FORMAT_HTML, \context_system::instance(), false, 'From a preset');

        $this->assertStringContainsString(
            '<input type="checkbox" class="edguidance-check" checked disabled title="From a preset">',
            $html
        );
        $this->assertStringNotContainsString('data-action', $html);
        $this->assertStringNotContainsString('data-checkindex', $html);
    }

    /**
     * Text with no checklist is formatted exactly as it would be without one, and only HTML has
     * checklists.
     */
    public function test_format_leaves_other_text_alone(): void {
        $context = \context_system::instance();
        $text = '<p>Nothing to tick [ ] here.</p>';

        $this->assertSame(format_text($text, FORMAT_HTML, ['context' => $context]), $this->format($text));
        $this->assertStringNotContainsString(
            '<input',
            checklist::format("[ ] One\n[ ] Two", FORMAT_MOODLE, $context, true)
        );
    }

    /**
     * Items are numbered as the text has them, before any filter runs: an item a filter leaves out
     * does not renumber the items after it.
     */
    public function test_numbering_survives_a_filter_dropping_items(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        \filter_manager::reset_caches();
        $text = '<p><span lang="fr" class="multilang">[ ] Un<br></span><span lang="en" class="multilang">[ ] One</span></p>' .
            '<p>[ ] Two</p>';
        $this->assertSame(['[ ] Un', '[ ] One', '[ ] Two'], $this->summary($text));

        $html = $this->format($text);

        $this->assertStringNotContainsString('Un', $html);
        $this->assertStringContainsString('data-checkindex="1"', $html);
        $this->assertStringContainsString('data-checkindex="2"', $html);
        $this->assertStringNotContainsString('data-checkindex="0"', $html);
    }

    /**
     * The tally counts the boxes shown and those ticked; text with no checklist has none.
     */
    public function test_tally(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $tally = fn(string $text) => checklist::format_with_tally($text, FORMAT_HTML, $context, true);

        $some = $tally('<p>[x] One</p><p>[ ] Two</p>');
        $this->assertSame([2, 1], [$some->items, $some->ticked]);
        $none = $tally('<p>Nothing to tick.</p>');
        $this->assertSame([0, 0], [$none->items, $none->ticked]);

        // An item a filter leaves out counts for nothing either way.
        filter_set_global_state('multilang', TEXTFILTER_ON);
        \filter_manager::reset_caches();
        $shown = $tally('<p><span lang="fr" class="multilang">[ ] Un<br></span>' .
            '<span lang="en" class="multilang">[x] One</span></p>');
        $this->assertSame([1, 1], [$shown->items, $shown->ticked]);
    }

    /**
     * Anything that looks like a placeholder in the text is only text: the real ones are unguessable.
     */
    public function test_placeholder_lookalikes_are_text(): void {
        $html = $this->format('<p>edgcheckn0z</p><p>[ ] One</p>');

        $this->assertStringContainsString('<p>edgcheckn0z</p>', $html);
        $this->assertSame(1, substr_count($html, '<input'));
    }
}
