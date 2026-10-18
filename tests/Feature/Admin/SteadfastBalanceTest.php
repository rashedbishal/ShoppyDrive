<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can check the steadfast balance to confirm credentials', function () {
    actingAs($this->admin, 'admin');

    Setting::insert([
        ['key' => 'steadfast_api_key', 'value' => json_encode('api-key'), 'group' => 'courier'],
        ['key' => 'steadfast_secret_key', 'value' => json_encode('secret-key'), 'group' => 'courier'],
    ]);

    Http::fake([
        '*/get_balance' => Http::response([
            'status' => 200,
            'current_balance' => 2500,
        ]),
    ]);

    $response = $this->getJson(route('admin.settings.steadfast.balance'));

    $response->assertOk();
    $response->assertJsonPath('current_balance', 2500);
});

test('checking balance without configured credentials returns an error', function () {
    actingAs($this->admin, 'admin');

    $response = $this->getJson(route('admin.settings.steadfast.balance'));

    $response->assertStatus(422);
    $response->assertJsonStructure(['message']);
});
