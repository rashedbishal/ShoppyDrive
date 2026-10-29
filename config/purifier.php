<?php

/**
 * Security-focused override of mews/purifier's shipped default config.
 *
 * `HTML.Allowed` only lists tags this app's rich-text editor
 * (resources/js/components/admin/RichTextEditor.tsx) actually has toolbar
 * buttons for, plus the browser-standard synonym for each one where
 * `document.execCommand`'s exact output tag isn't pinned down (e.g. both
 * `b`/`strong`, both `s`/`strike`) — allowing a safe synonym never widens
 * the attack surface (none of these carry attributes beyond what's
 * explicitly listed on `a`/`img`), it only guards against formatting
 * silently failing to survive if a given browser emits the other variant.
 *
 * Deliberately NOT yet decided: color/inline-style support. The editor's
 * `foreColor` command exists, but whether it outputs `<font color>` or an
 * inline `style="color:..."` has not been observed in a real browser.
 * Until it has, inline CSS stays fully disabled (`CSS.AllowedProperties`
 * empty, no `[style]` on any tag) rather than guessed at — this means the
 * color picker's output is currently stripped, not silently miscoded.
 *
 * `URI.AllowedSchemes` is explicit rather than relying on HTMLPurifier's
 * own default (verified separately to already exclude `javascript`/`data`,
 * but explicit here so a future upstream default change can't widen it
 * without this file changing too).
 *
 * The vendor default's `custom_definition`/`custom_attributes`/
 * `custom_elements` blocks (html5 elements like `video`/`section`, plus
 * `a[target]`) are intentionally dropped here, not carried forward —
 * verified empirically (via `Purifier::clean()` against a live payload
 * battery) to be inert under the vendor's own `HTML.Allowed`, and this
 * editor never produces any of them.
 */
return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,strike,h1,h2,blockquote,ul,ol,li,a[href],img[src|alt|width|height]',
            'CSS.AllowedProperties' => '',
            'URI.AllowedSchemes' => 'http,https,mailto',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],
    ],
];
