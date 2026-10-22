<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shares youtube and tiktok social links with every page, not just facebook/instagram/twitter', function (): void {
    Setting::create(['key' => 'social_links', 'value' => [
        'facebook' => 'https://facebook.com/shoppydrive',
        'youtube' => 'https://youtube.com/@shoppydrive',
        'tiktok' => 'https://tiktok.com/@shoppydrive',
    ], 'group' => 'social']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.social_links.facebook', 'https://facebook.com/shoppydrive')
            ->where('settings.social_links.youtube', 'https://youtube.com/@shoppydrive')
            ->where('settings.social_links.tiktok', 'https://tiktok.com/@shoppydrive')
        );
});
