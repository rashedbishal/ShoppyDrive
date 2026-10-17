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

test('admin can fetch the merchant store list from pathao', function () {
    actingAs($this->admin, 'admin');

    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
    ]);

    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 432000,
        ]),
        '*/aladdin/api/v1/stores' => Http::response([
            'type' => 'success',
            'data' => [
                'data' => [
                    ['store_id' => 111, 'store_name' => 'Main Store', 'store_address' => 'Dhaka', 'is_default_store' => true],
                ],
            ],
        ]),
    ]);

    $response = $this->getJson(route('admin.settings.pathao.stores'));

    $response->assertOk();
    $response->assertJsonPath('stores.0.store_name', 'Main Store');
});

test('fetching stores without configured credentials returns an error', function () {
    actingAs($this->admin, 'admin');

    $response = $this->getJson(route('admin.settings.pathao.stores'));

    $response->assertStatus(422);
    $response->assertJsonStructure(['message']);
});
