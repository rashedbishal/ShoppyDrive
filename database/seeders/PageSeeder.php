<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * BIZ-032/BIZ-034: the legal and policy pages. Their HTML lives in
     * database/seeders/pages/{slug}.html so this seeder and the migration
     * that publishes it to production read exactly the same text. There is
     * no admin editor for pages, so changing this text needs a new migration.
     *
     * @return list<array{title: string, slug: string, meta_title: string, meta_description: string, content: string, is_published: bool}>
     */
    public static function pages(): array
    {
        $pages = [
            ['Privacy Policy', 'privacy-policy', 'Learn how ShoppyDrive collects, uses, and protects your personal information.'],
            ['Terms of Service', 'terms', 'Read the terms and conditions governing your use of ShoppyDrive.'],
            ['Frequently Asked Questions', 'faq', 'Answers to the most common questions about shopping, delivery, returns, and more at ShoppyDrive.'],
            ['Return Policy', 'return-policy', 'How to return an item to ShoppyDrive: the 7-day window, conditions and return delivery costs.'],
            ['Refund Policy', 'refund-policy', 'How ShoppyDrive refunds work: what is refunded, how and when.'],
            ['Shipping Policy', 'shipping-policy', 'ShoppyDrive delivery areas, delivery times and delivery charges across Bangladesh.'],
        ];

        return array_map(fn (array $page): array => [
            'title' => $page[0],
            'slug' => $page[1],
            'meta_title' => ($page[1] === 'faq' ? 'FAQ' : $page[0]).' — ShoppyDrive',
            'meta_description' => $page[2],
            'content' => trim((string) file_get_contents(__DIR__.'/pages/'.$page[1].'.html'))."\n",
            'is_published' => true,
        ], $pages);
    }

    public function run(): void
    {
        foreach (self::pages() as $data) {
            Page::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
