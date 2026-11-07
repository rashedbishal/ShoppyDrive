<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * SEC-024. Works on a throwaway directory through environmentFilePath(), so
 * no test here can touch the real .env.
 */
beforeEach(function () {
    $this->environmentDirectory = storage_path('framework/testing/env-'.Str::random(8));
    File::ensureDirectoryExists($this->environmentDirectory);
    $this->environmentFile = $this->environmentDirectory.'/.env';
    file_put_contents($this->environmentFile, "APP_NAME=Shop\nSMS_FORWARDER_TOKEN=old-token\nDB_PASSWORD=\"keep me\"\n");
    chmod($this->environmentFile, 0640);
    $this->originalEnvironmentPath = app()->environmentPath();
    app()->useEnvironmentPath($this->environmentDirectory);

    actingAs(createMotherAdmin(), 'admin');
});

afterEach(function () {
    app()->useEnvironmentPath($this->originalEnvironmentPath);
    chmod($this->environmentDirectory, 0755);
    File::deleteDirectory($this->environmentDirectory);
});

test('regenerating replaces only the token, keeps the file permissions and leaves nothing behind', function () {
    $this->post(route('admin.settings.sms-forwarder.regenerate'))->assertSessionHas('newToken');

    $contents = (string) file_get_contents($this->environmentFile);

    expect($contents)->toContain('APP_NAME=Shop')->toContain('DB_PASSWORD="keep me"')
        ->toContain('SMS_FORWARDER_TOKEN='.session('newToken'))->not->toContain('old-token')
        ->and(fileperms($this->environmentFile) & 0777)->toBe(0640)
        ->and(glob($this->environmentDirectory.'/.env*.tmp'))->toBe([]);
});

test('a token the file never had is added on its own line', function () {
    file_put_contents($this->environmentFile, "APP_NAME=Shop\n");

    $this->post(route('admin.settings.sms-forwarder.regenerate'));

    expect(file($this->environmentFile, FILE_IGNORE_NEW_LINES))->toBe(['APP_NAME=Shop', 'SMS_FORWARDER_TOKEN='.session('newToken')]);
});

test('regenerating is recorded without the token', function () {
    $this->post(route('admin.settings.sms-forwarder.regenerate'));

    $entry = Activity::query()->where('description', 'like', '%regenerated the SMS forwarder token%')->sole();

    expect($entry->description)->not->toContain((string) session('newToken'));
});

test('if the file cannot be replaced, the old token stays whole and the admin is told', function () {
    chmod($this->environmentDirectory, 0555);

    $this->post(route('admin.settings.sms-forwarder.regenerate'))
        ->assertSessionHas('error')
        ->assertSessionMissing('newToken');

    expect((string) file_get_contents($this->environmentFile))->toContain('SMS_FORWARDER_TOKEN=old-token')->toContain('DB_PASSWORD="keep me"')
        ->and(Activity::query()->where('description', 'like', '%regenerated%')->exists())->toBeFalse();
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
