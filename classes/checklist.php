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
 * Checklists in guidance text, shown as checkboxes.
 *
 * An item is a line that starts "[ ] " (or "[x] " once ticked) - a paragraph, a line after a line
 * break, a list item or any other block. The ticks are part of the text, so a checklist is shared:
 * every teacher sees the same ticks, and ticking one rewrites the block's own text.
 *
 * markers() is the only parser. Rendering numbers the items with it, and ticking finds the item to
 * rewrite with it, so the two can never disagree about which item is which. Each item also carries
 * a hash of its text, so a tick sent from a page loaded before the guidance was edited is refused
 * rather than landing on whatever item now has that number.
 *
 * Rendering swaps each marker for a random placeholder before the text is formatted, and the
 * placeholders for checkboxes after. Before, because a filter can drop text - multilang shows one
 * language of several - and numbering what survives would not match the text. After, because
 * formatting cleans the text, and cleaning removes form controls. Only HTML text has checklists: it
 * is what the editor writes and what presets hold.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist {
    /** @var string A space as rich text writes one: plain, non-breaking, or a non-breaking entity. */
    protected const SPACE = '(?:\s|\x{00A0}|&nbsp;|&#160;|&#xa0;)';

    /**
     * @var string A marker at the start of a line: after the start of the text, a line break or the
     * opening tag of a block (with any inline tags, opening or closing, and spaces between), [ ], [x]
     * or [X], then a space.
     */
    protected const PATTERN = '~(?:^|<br\b[^>]*>|<(?:p|div|li|h[1-6]|td|th|blockquote)\b[^>]*>)'
        . '(?:' . self::SPACE . '|</?(?:span|strong|b|em|i|u)\b[^>]*>)*'
        . '\[(?<state> |\x{00A0}|&nbsp;|&#160;|&#xa0;|x)\](?=' . self::SPACE . ')~iu';

    /** @var string An item's text: from its marker to the end of its line. */
    protected const ITEMTEXT = '~\G(.*?)(?=<br\b|</?(?:p|div|li|ul|ol|h[1-6]|table|tr|td|th|blockquote|pre)\b|$)~isu';

    /** @var string[] Elements that end an item's line when they follow it. */
    protected const BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'dd', 'details', 'div', 'dl', 'dt', 'fieldset',
        'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li',
        'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul',
    ];

    /**
     * The items in some guidance text, in order.
     *
     * @param string $text HTML.
     * @return \stdClass[] Numbered from 0, each with ->checked, ->text (plain) and ->hash.
     */
    public static function items(string $text): array {
        return array_map(
            fn(\stdClass $marker) => (object)['checked' => $marker->checked, 'text' => $marker->text, 'hash' => $marker->hash],
            self::markers($text)
        );
    }

    /**
     * Tick or untick one item.
     *
     * @param string $text HTML.
     * @param int $index The item, numbered from 0 as items() numbers them.
     * @param bool $checked Whether it should be ticked.
     * @return string The text, with only that item's box changed.
     * @throws \coding_exception If there is no such item.
     */
    public static function set(string $text, int $index, bool $checked): string {
        $marker = self::markers($text)[$index] ?? null;
        if (!$marker) {
            throw new \coding_exception('There is no checklist item ' . $index . ' in this text.');
        }

        return substr_replace($text, $checked ? 'x' : ' ', $marker->state, $marker->statelength);
    }

    /**
     * Format guidance text for output, with any checklist in it as checkboxes.
     *
     * @param string $text The text.
     * @param int $format Its format. Only FORMAT_HTML has checklists.
     * @param \context $context The context to format it in.
     * @param bool $tickable Whether the boxes can be ticked here. Otherwise they are disabled.
     * @param string $title What a disabled box says when pointed at, or '' for nothing.
     * @return string HTML.
     */
    public static function format(string $text, int $format, \context $context, bool $tickable, string $title = ''): string {
        $markers = $format == FORMAT_HTML ? self::markers($text) : [];
        if (!$markers) {
            return format_text($text, $format, ['context' => $context]);
        }

        // Nobody writing guidance can know it, so nothing they write can pass for a placeholder.
        $nonce = random_string(16);
        foreach (array_reverse($markers, true) as $index => $marker) {
            $text = substr_replace($text, self::placeholder($nonce, $index), $marker->start, $marker->end - $marker->start);
        }

        return self::render(format_text($text, $format, ['context' => $context]), $nonce, $markers, $tickable, $title);
    }

    /**
     * Every marker in some text.
     *
     * @param string $text HTML.
     * @return \stdClass[] Numbered from 0, each with ->start and ->end (byte offsets of the marker,
     *     brackets included), ->state and ->statelength (of what is between the brackets), ->checked,
     *     ->text and ->hash.
     */
    protected static function markers(string $text): array {
        if (!preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $markers = [];
        foreach ($matches as $match) {
            [$state, $stateat] = $match['state'];
            $end = $stateat + strlen($state) + 1;

            preg_match(self::ITEMTEXT, $text, $item, 0, $end);
            $plain = html_entity_decode(strip_tags($item[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = trim(preg_replace('~[\s\x{00A0}]+~u', ' ', $plain));

            $markers[] = (object)[
                'start' => $stateat - 1,
                'end' => $end,
                'state' => $stateat,
                'statelength' => strlen($state),
                'checked' => strtolower($state) === 'x',
                'text' => $plain,
                'hash' => substr(sha1($plain), 0, 12),
            ];
        }

        return $markers;
    }

    /**
     * What stands in for a marker while the text is formatted.
     *
     * Letters and digits only, which cleaning leaves alone and no filter has a reason to touch.
     *
     * @param string $nonce This rendering's nonce.
     * @param int $index The item.
     * @return string
     */
    protected static function placeholder(string $nonce, int $index): string {
        return 'edgcheck' . $nonce . 'n' . $index . 'z';
    }

    /**
     * Replace the placeholders in formatted text with checkboxes.
     *
     * @param string $html The formatted text.
     * @param string $nonce The nonce its placeholders use.
     * @param \stdClass[] $markers The markers they stand for.
     * @param bool $tickable Whether the boxes can be ticked.
     * @param string $title What a disabled box says when pointed at.
     * @return string HTML.
     */
    protected static function render(string $html, string $nonce, array $markers, bool $tickable, string $title): string {
        $pattern = '~edgcheck' . $nonce . 'n(\d+)z~';
        if (!preg_match($pattern, $html)) {
            return $html;
        }

        $doc = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $doc->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            . '<body><div>' . $html . '</div></body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        $root = $doc->getElementsByTagName('body')->item(0)->firstChild;
        $xpath = new \DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('.//text()[contains(., "edgcheck' . $nonce . '")]', $root)) as $node) {
            self::place($doc, $node, $pattern, $markers, $tickable, $title);
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // A placeholder anywhere but a text node - there should be none - must not show.
        return preg_replace($pattern, '', $out);
    }

    /**
     * Turn one placeholder, and the rest of its line, into a labelled checkbox.
     *
     * @param \DOMDocument $doc The document.
     * @param \DOMText $node The text holding the placeholder.
     * @param string $pattern The placeholders' pattern.
     * @param \stdClass[] $markers The markers they stand for.
     * @param bool $tickable Whether the box can be ticked.
     * @param string $title What a disabled box says when pointed at.
     */
    protected static function place(
        \DOMDocument $doc,
        \DOMText $node,
        string $pattern,
        array $markers,
        bool $tickable,
        string $title
    ): void {
        if (!preg_match($pattern, $node->data, $match, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $index = (int)$match[1][0];
        $marker = $markers[$index] ?? null;
        $after = substr($node->data, $match[0][1] + strlen($match[0][0]));
        $node->data = substr($node->data, 0, $match[0][1]);
        if (!$marker) {
            $node->parentNode->insertBefore($doc->createTextNode($after), $node->nextSibling);
            return;
        }

        $box = $doc->createElement('input');
        $box->setAttribute('type', 'checkbox');
        $box->setAttribute('class', 'edguidance-check');
        if ($marker->checked) {
            $box->setAttribute('checked', 'checked');
        }
        if ($tickable) {
            $box->setAttribute('data-action', 'edguidance-check');
            $box->setAttribute('data-checkindex', (string)$index);
            $box->setAttribute('data-checkhash', $marker->hash);
        } else {
            $box->setAttribute('disabled', 'disabled');
            if ($title !== '') {
                $box->setAttribute('title', $title);
            }
        }

        $words = $doc->createElement('span');
        $words->setAttribute('class', 'edguidance-checktext');
        $words->appendChild($doc->createTextNode($after));

        $label = $doc->createElement('label');
        $label->setAttribute('class', 'edguidance-checkitem');
        $label->appendChild($box);
        $label->appendChild($words);
        $node->parentNode->insertBefore($label, $node->nextSibling);

        // The rest of the line joins the label, up to the line break, which the label replaces, or
        // the next block. Never another item: that would put a label inside a label.
        while ($next = $label->nextSibling) {
            if ($next instanceof \DOMElement) {
                $name = strtolower($next->nodeName);
                if ($name === 'br') {
                    $next->parentNode->removeChild($next);
                    break;
                }
                if (in_array($name, self::BLOCKS, true)) {
                    break;
                }
            }
            if (preg_match($pattern, $next->textContent)) {
                break;
            }
            $words->appendChild($next);
        }
    }
}
