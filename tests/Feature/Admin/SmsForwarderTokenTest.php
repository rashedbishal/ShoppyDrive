<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\SmsForwarderToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(createMotherAdmin(), 'admin');
});

test('regenerating replaces the stored token with a new 64-character value', function () {
    setSmsForwarderToken('old-token');

    $this->post(route('admin.settings.sms-forwarder.regenerate'))->assertSessionHas('newToken');

    $newToken = session('newToken');

    expect($newToken)->not->toBe('old-token')
        ->and(strlen($newToken))->toBe(64)
        ->and(SmsForwarderToken::current())->toBe($newToken);
});

test('regenerating with no existing token creates one from nothing', function () {
    // A local dev .env may still carry a value the seed migration picked
    // up; force the "nothing configured yet" state explicitly rather than
    // assuming it from a fresh schema.
    setSmsForwarderToken(null);
    expect(Setting::where('key', SmsForwarderToken::KEY)->exists())->toBeFalse();

    $this->post(route('admin.settings.sms-forwarder.regenerate'))->assertSessionHas('newToken');

    expect(SmsForwarderToken::current())->toBe(session('newToken'));
});

test('regenerating is recorded without the token', function () {
    $this->post(route('admin.settings.sms-forwarder.regenerate'));

    $entry = Activity::query()->where('description', 'like', '%regenerated the SMS forwarder token%')->sole();

    expect($entry->description)->not->toContain((string) session('newToken'));
});

test('admin images over 5MB are refused, and one at the limit is accepted (SEC-018)', function (int $kilobytes, bool $accepted) {
    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Kitchen', 'slug' => 'kitchen-'.$kilobytes,
        'image' => UploadedFile::fake()->image('big.jpg')->size($kilobytes),
    ]);

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('image');
})->with([
    'at the limit' => [5120, true],
    'over the limit' => [5121, false],
]);
