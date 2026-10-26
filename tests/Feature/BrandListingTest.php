<?php

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('brands listing shows most recently added brands first', function () {
    $older = Brand::factory()->create();
    $newer = Brand::factory()->create();

    $this->get('/brands')->assertInertia(fn ($page) => $page
        ->where('brands.0.id', $newer->id)
        ->where('brands.1.id', $older->id)
    );
});
