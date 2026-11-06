<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap';

    /** Pages with their own top-level route; any other page lives at /page/{slug}. */
    private const TOP_LEVEL_PAGES = ['privacy-policy', 'terms', 'faq', 'return-policy', 'refund-policy', 'shipping-policy'];

    /**
     * OPS-001: under storage/app/private, not public/, so deploys keep it
     * (rsync --delete would remove a generated public file, and it would
     * break the courier release fingerprint). Served by the /sitemap.xml route.
     */
    public static function path(): string
    {
        return storage_path('app/private/sitemap.xml');
    }

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
            $sitemap->add(Url::create(in_array($page->slug, self::TOP_LEVEL_PAGES, true) ? '/'.$page->slug : '/page/'.$page->slug));
        });

        foreach (['/products', '/brands', '/blog', '/about', '/contact'] as $path) {
            $sitemap->add(Url::create($path));
        }

        Brand::active()->get()->each(function (Brand $brand) use ($sitemap) {
            $sitemap->add(Url::create('/brand/'.$brand->slug));
        });

        Blog::published()->get()->each(function (Blog $blog) use ($sitemap) {
            $sitemap->add(Url::create('/blog/'.$blog->slug));
        });

        File::ensureDirectoryExists(dirname(self::path()));
        $sitemap->writeToFile(self::path());

        $this->info('Sitemap generated successfully.');
    }
}
