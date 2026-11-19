<?php

declare(strict_types=1);

use App\Services\Mp4Video;

function mp4VideoTestFile(string $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'mp4-video-test');
    file_put_contents($path, $bytes);

    return $path;
}

/** The single chunk offset in the file's stco table. */
function mp4VideoChunkOffset(string $bytes): int
{
    return unpack('N', substr($bytes, strpos($bytes, 'stco') + 12, 4))[1];
}

test('it reads the video track size and codec', function () {
    $path = mp4VideoTestFile(fakeMp4Bytes(width: 1920, height: 1080, codec: 'hvc1'));

    expect((new Mp4Video)->inspect($path))->toBe(['width' => 1920, 'height' => 1080, 'codec' => 'hvc1']);
});

test('a file that is not an mp4 cannot be inspected', function (string $bytes) {
    expect((new Mp4Video)->inspect(mp4VideoTestFile($bytes)))->toBeNull();
})->with([
    'random bytes' => [str_repeat("\x1A\x45\xDF\xA3", 64)],
    'empty' => [''],
    'truncated mp4' => [substr(fakeMp4Bytes(), 0, 60)],
]);

test('it moves the index in front of the video data and keeps every chunk offset pointing at the same bytes', function () {
    $original = fakeMp4Bytes();
    $path = mp4VideoTestFile($original);

    expect((new Mp4Video)->moveIndexToFront($path))->toBeTrue();

    $rearranged = (string) file_get_contents($path);

    expect(strlen($rearranged))->toBe(strlen($original))
        ->and(strpos($rearranged, 'moov'))->toBeLessThan(strpos($rearranged, 'mdat'))
        ->and(substr($rearranged, mp4VideoChunkOffset($rearranged), 11))->toBe('FIRST-FRAME')
        ->and((new Mp4Video)->inspect($path))->toBe((new Mp4Video)->inspect(mp4VideoTestFile($original)));
});

test('a file whose index is already first is left untouched', function () {
    $original = fakeMp4Bytes(indexFirst: true);
    $path = mp4VideoTestFile($original);

    expect((new Mp4Video)->moveIndexToFront($path))->toBeFalse()
        ->and(file_get_contents($path))->toBe($original);
});

test('a file that is not an mp4 is never rewritten', function () {
    $original = str_repeat('not a video', 20);
    $path = mp4VideoTestFile($original);

    expect((new Mp4Video)->moveIndexToFront($path))->toBeFalse()
        ->and(file_get_contents($path))->toBe($original);
});
