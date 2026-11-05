<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap';

    public function handle(): void
    {
        $sitemap = Sitemap::create();

        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));

        Category::active()->get()->each(function (Category $category) use ($sitemap) {
            $sitemap->add(Url::create('/category/'.$category->slug));
        });

        Product::visible()->get()->each(function (Product $product) use ($sitemap) {
            $sitemap->add(Url::create('/product/'.$product->slug));
        });

        Page::where('is_published', true)->get()->each(function (Page $page) use ($sitemap) {
            $sitemap->add(Url::create('/'.$page->slug));
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully.');
    }
}
