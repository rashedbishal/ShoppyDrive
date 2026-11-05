<?php

declare(strict_types=1);

/*
 * BIZ-032/BIZ-034: the legal pages must describe how the shop actually
 * works, the return/refund/shipping policies must exist, and the seller's
 * identity must be editable in Admin and shown on the storefront.
 */

use App\Http\Controllers\ReturnRequestController;
use App\Models\Admin;
use App\Models\Page;
use App\Models\Setting;
use App\Services\PendingCodLimit;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function policyPagesMigration(): object
{
    return require database_path('migrations/2026_11_05_221557_publish_accurate_policy_pages.php');
}

test('every legal and policy page is published at its own URL after migrating', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('page.slug', $routeName)
            ->where('page.is_published', true)
        );
})->with([
    ['privacy-policy', 'static/Page'],
    ['terms', 'static/Page'],
    ['faq', 'static/FAQ'],
    ['return-policy', 'static/Page'],
    ['refund-policy', 'static/Page'],
    ['shipping-policy', 'static/Page'],
]);

test('the pages no longer make promises the shop does not keep', function () {
    $allText = Page::query()->pluck('content')->implode("\n");

    expect($allText)
        ->not->toContain('Payment must be completed before')
        ->not->toContain('within 1 hour')
        ->not->toContain('will be available soon')
        ->not->toContain('via email or SMS')
        ->not->toContain('full refund immediately')
        ->not->toContain('SMS/email providers')
        ->not->toContain('card details)');
});

test('the pages state the rules the shop actually applies', function () {
    $content = Page::query()->pluck('content', 'slug');

    expect($content['terms'])->toContain('bKash or Nagad')->toContain('handed to our courier partner')
        ->and($content['faq'])->toContain('1–2 working days')->toContain('3–5 working days')
        ->and($content['return-policy'])->toContain('7 days of delivery')->toContain('48 hours')
        ->toContain('Change of mind:</strong> you pay')
        ->and($content['refund-policy'])->toContain('delivery charge is not refunded')->toContain('3–5 working days')
        ->and($content['privacy-policy'])->toContain('IP address')->toContain('Unfinished checkouts');
});

test('a policy rule changed in code would contradict the pages, so the tested numbers match it', function () {
    expect(PendingCodLimit::MAX_UNITS_PER_PRODUCT)->toBe(10)
        ->and(ReturnRequestController::ELIGIBILITY_WINDOW_DAYS)->toBe(7);
});

test('the migration rewrites stale live pages without touching their creation date', function () {
    $createdAt = now()->subYear()->startOfSecond();
    DB::table('pages')->where('slug', 'terms')->update([
        'content' => '<p>Payment must be completed before orders are dispatched.</p>',
        'created_at' => $createdAt,
    ]);
    DB::table('pages')->where('slug', 'return-policy')->delete();

    policyPagesMigration()->up();

    $terms = DB::table('pages')->where('slug', 'terms')->first();
    expect($terms->content)->not->toContain('Payment must be completed before')
        ->and(Carbon::parse($terms->created_at)->equalTo($createdAt))->toBeTrue()
        ->and(DB::table('pages')->where('slug', 'return-policy')->exists())->toBeTrue()
        ->and(DB::table('pages')->where('slug', 'terms')->count())->toBe(1);
});

test('rolling the migration back removes only the three pages it created', function () {
    policyPagesMigration()->down();

    expect(Page::query()->pluck('slug')->sort()->values()->all())->toBe(['faq', 'privacy-policy', 'terms']);
});

test('the seeder and the migration publish exactly the same text', function () {
    (new PageSeeder)->run();

    foreach (PageSeeder::pages() as $page) {
        expect(Page::where('slug', $page['slug'])->value('content'))->toBe($page['content']);
    }
});

test('an admin with contact page access can save the seller identity, and the storefront shares it', function () {
    $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => bcrypt('password')]);
    $admin->forceFill(['permissions' => ['contact_page' => ['view', 'update']]])->save();

    $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), [
        'legal_business_name' => 'Shoppy Drive Enterprise',
        'trade_licence_number' => 'TRAD/DNCC/012345/2025',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect(Setting::where('key', 'legal_business_name')->value('value'))->toBe('Shoppy Drive Enterprise');

    auth('admin')->logout();
    $this->get(route('terms'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.legal_business_name', 'Shoppy Drive Enterprise')
        ->where('settings.trade_licence_number', 'TRAD/DNCC/012345/2025')
    );
});

test('an admin without contact page access cannot change the seller identity', function () {
    $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => bcrypt('password')]);
    $admin->forceFill(['permissions' => ['about_page' => ['view', 'update']]])->save();

    $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), [
        'legal_business_name' => 'Somebody Else Ltd',
    ])->assertForbidden();

    expect(Setting::where('key', 'legal_business_name')->exists())->toBeFalse();
});

test('the storefront shares the configured support hours, defaulting to every day 9 to 9', function () {
    $this->get(route('terms'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.support_hours', 'Every day, 9 AM–9 PM')
        ->where('settings.legal_business_name', null)
    );

    Setting::create(['key' => 'contact_page_support_hours', 'value' => 'Sat–Thu, 10 AM–8 PM', 'group' => 'contact_page']);

    $this->get(route('terms'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.support_hours', 'Sat–Thu, 10 AM–8 PM')
    );
});
