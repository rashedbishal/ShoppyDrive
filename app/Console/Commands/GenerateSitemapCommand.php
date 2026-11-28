<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ContactController;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\PromotionalBanner;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Support\ImageVariants;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap';

    /** Pages with their own top-level route; any other page lives at /page/{slug}. */
    public const TOP_LEVEL_PAGES = ['privacy-policy', 'terms', 'faq', 'return-policy', 'refund-policy', 'shipping-policy'];

    /**
     * OPS-001: under storage/app/private, not public/, so deploys keep it
     * (rsync --delete would remove a generated public file, and it would
     * break the courier release fingerprint). Served by the /sitemap.xml route.
     */
    public static function path(): string
    {
        return storage_path('app/private/sitemap.xml');
    }

    /**
     * DISC-60: every URL carries <lastmod> (the updated_at of what the page
     * shows) so crawlers and AI agents can tell what changed. A listing page
     * is dated by its newest item; a page with nothing to date omits it.
     */
    public function handle(): void
    {
        $sitemap = Sitemap::create();

        $products = Product::visible()->get();
        $brands = Brand::active()->get();
        $blogs = Blog::published()->get();
        $newestProduct = $products->max('updated_at');

        $sitemap->add($this->url('/', self::newest(
            $newestProduct,
            HomepageSetting::max('updated_at'),
            HeroSlide::max('updated_at'),
            PromotionalBanner::max('updated_at'),
        ))->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));

        $categories = Category::active()->get();

        $categories->each(function (Category $category) use ($sitemap) {
            $sitemap->add($this->url('/category/'.$category->slug, $category->updated_at));
        });

        // DISC-79: sub-categories share the /category/{slug} URL. Listed only
        // when that URL would show them: active, under an active category,
        // and not shadowed by an active category with the same slug
        // (ProductListingController::category() looks categories up first).
        SubCategory::active()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereNotIn('slug', $categories->pluck('slug'))
            ->get()
            ->each(function (SubCategory $subCategory) use ($sitemap) {
                $sitemap->add($this->url('/category/'.$subCategory->slug, $subCategory->updated_at));
            });

        $products->load('images');

        $products->each(function (Product $product) use ($sitemap) {
            $url = $this->url('/product/'.$product->slug, $product->updated_at);

            foreach (self::sitemapImagePaths($product) as $imagePath) {
                $url->addImage($imagePath);
            }

            $sitemap->add($url);
        });

        Page::where('is_published', true)->get()->each(function (Page $page) use ($sitemap) {
            $sitemap->add($this->url(in_array($page->slug, self::TOP_LEVEL_PAGES, true) ? '/'.$page->slug : '/page/'.$page->slug, $page->updated_at));
        });

        $sitemap->add($this->url('/products', $newestProduct));
        $sitemap->add($this->url('/brands', $brands->max('updated_at')));
        $sitemap->add($this->url('/flash-sales', FlashSale::max('updated_at')));
        $sitemap->add($this->url('/bundle-deals', ProductBundle::max('updated_at')));
        $sitemap->add($this->url('/blog', $blogs->max('updated_at')));
        $sitemap->add($this->url('/about', Setting::whereIn('key', AboutController::keys())->max('updated_at')));
        $sitemap->add($this->url('/contact', Setting::whereIn('key', ContactController::keys())->max('updated_at')));

        $brands->each(function (Brand $brand) use ($sitemap) {
            $sitemap->add($this->url('/brand/'.$brand->slug, $brand->updated_at));
        });

        $blogs->each(function (Blog $blog) use ($sitemap) {
            $sitemap->add($this->url('/blog/'.$blog->slug, $blog->updated_at));
        });

        File::ensureDirectoryExists(dirname(self::path()));
        $sitemap->writeToFile(self::path());

        $this->info('Sitemap generated successfully.');
    }

    private function url(string $path, DateTimeInterface|string|null $lastModified): Url
    {
        $url = Url::create($path);

        if ($lastModified !== null) {
            $url->setLastModificationDate(Carbon::parse($lastModified));
        }

        return $url;
    }

    /**
     * DISC-97: the product's own photos for its <image:image> entries, primary
     * first, then in gallery order. Only original product uploads that exist
     * on the public disk qualify, which leaves out videos saved as images,
     * the placeholder, external URLs and the resized WebP copies; each path is
     * listed once. Captions are left out on purpose: Google reads only
     * <image:loc>, and products carry no real alt text to caption with.
     *
     * @return list<string>
     */
    private static function sitemapImagePaths(Product $product): array
    {
        return $product->images
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc'], ['id', 'asc']])
            ->pluck('path')
            ->filter(function (?string $path): bool {
                $publicPath = ImageVariants::publicPath($path);

                return $publicPath !== null
                    && str_starts_with($publicPath, 'products/')
                    && Storage::disk('public')->exists($publicPath);
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The latest of several dates (max() on a query returns a string), or
     * null when none are set.
     */
    private static function newest(DateTimeInterface|string|null ...$dates): ?Carbon
    {
        return collect($dates)->filter()->map(fn (DateTimeInterface|string $date): Carbon => Carbon::parse($date))->max();
    }
}
