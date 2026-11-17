<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Category;
use App\Models\Page;
use App\Support\LayoutCache;
use App\Support\PageSeo;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * DISC-56: /llms.txt (https://llmstxt.org), a Markdown summary of the shop
 * for AI agents, linking its key pages. Built per request from live data
 * (site name, saved contact details, active categories, published policy
 * pages), so it never goes stale the way a committed file would.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $settings = LayoutCache::settings();
        $siteName = self::line($settings['site_name'] ?? null) ?? config('app.name');

        $lines = [
            '# '.$siteName,
            '',
            '> '.PageSeo::DEFAULT_DESCRIPTION,
            '',
            ...self::contactLines($settings),
            '## Shop',
            '',
            self::link('All products', route('products.index')),
            self::link('Brands', url('/brands')),
            self::link('Search products', route('search').'?q=', 'add the search words after q='),
            '',
            ...self::categoryLines(),
            '## About',
            '',
            self::link('About us', route('about')),
            self::link('Contact', route('contact')),
            self::link('Blog', url('/blog')),
            '',
            ...self::policyLines(),
            '## Optional',
            '',
            self::link('Sitemap', route('sitemap'), 'every public page'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Saved contact details only; the storefront's display placeholders
     * are never published as facts.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private static function contactLines(array $settings): array
    {
        $details = array_filter([
            'Email' => self::line($settings['contact_email'] ?? null),
            'Phone' => self::line($settings['contact_phone'] ?? null),
            'Address' => self::line($settings['contact_address'] ?? null),
        ]);

        if ($details === []) {
            return [];
        }

        return [
            ...array_map(fn (string $label, string $value): string => '- '.$label.': '.$value, array_keys($details), $details),
            '',
        ];
    }

    /** @return array<int, string> */
    private static function categoryLines(): array
    {
        $categories = Category::active()->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']);

        if ($categories->isEmpty()) {
            return [];
        }

        return [
            '## Categories',
            '',
            ...$categories->map(fn (Category $category): string => self::link($category->name, route('category.show', $category->slug))),
            '',
        ];
    }

    /** @return array<int, string> */
    private static function policyLines(): array
    {
        $pages = Page::where('is_published', true)->orderBy('title')->get(['title', 'slug']);

        if ($pages->isEmpty()) {
            return [];
        }

        return [
            '## Policies and information',
            '',
            ...$pages->map(fn (Page $page): string => self::link(
                $page->title,
                url(in_array($page->slug, GenerateSitemapCommand::TOP_LEVEL_PAGES, true) ? '/'.$page->slug : '/page/'.$page->slug),
            )),
            '',
        ];
    }

    private static function link(string $title, string $url, ?string $note = null): string
    {
        $title = str_replace(['[', ']'], ['(', ')'], self::line($title) ?? $url);

        return '- ['.$title.']('.$url.')'.($note !== null ? ': '.$note : '');
    }

    /**
     * One line of plain text: no line breaks, so an admin-entered value
     * can't start a new Markdown heading or list item.
     */
    private static function line(mixed $value): ?string
    {
        $text = is_string($value) ? Str::squish($value) : '';

        return $text !== '' ? $text : null;
    }
}
