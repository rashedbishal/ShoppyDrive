<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DISC-71: reads and rearranges the box structure of an MP4/MOV file in
 * plain PHP, because Hostinger shared hosting has no ffmpeg.
 *
 * An MP4 keeps its index (the `moov` box, which says where every frame
 * lives) either before or after the video data. Phones and many editors
 * write it at the end, so a browser has to fetch the end of the file before
 * it can show the first frame. moveIndexToFront() does what ffmpeg's
 * `-movflags +faststart` does: it puts the index first and shifts the chunk
 * offsets inside it to match. The encoded video itself is never touched.
 */
final class Mp4Video
{
    /** An index is small even for a long video; anything bigger is refused rather than loaded into memory. */
    private const int MAX_INDEX_BYTES = 16 * 1024 * 1024;

    /** A real file has a handful of top-level boxes; this only stops a crafted file from looping for long. */
    private const int MAX_TOP_LEVEL_BOXES = 10_000;

    /** The boxes inside the index that lead down to the chunk-offset tables. */
    private const array INDEX_CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl'];

    /**
     * The first video track's encoded size and codec (e.g. `avc1` = H.264,
     * `hvc1` = HEVC), or null when the file isn't a readable MP4/MOV.
     *
     * @return array{width: int, height: int, codec: string}|null
     */
    public function inspect(string $path): ?array
    {
        $boxes = $this->topLevelBoxes($path);
        $moov = $boxes === null ? null : $this->firstOfType($boxes, 'moov');
        $index = $moov === null ? null : $this->readBox($path, $moov);

        if ($index === null) {
            return null;
        }

        foreach ($this->childBoxes($index, $moov['header'], strlen($index)) as $trak) {
            if ($trak['type'] !== 'trak') {
                continue;
            }

            $mdia = $this->childOfType($index, $trak, 'mdia');
            $handler = $mdia === null ? null : $this->childOfType($index, $mdia, 'hdlr');

            // hdlr: version/flags (4) + pre_defined (4), then the handler type.
            if ($handler === null || substr($index, $handler['offset'] + $handler['header'] + 8, 4) !== 'vide') {
                continue;
            }

            $minf = $this->childOfType($index, $mdia, 'minf');
            $stbl = $minf === null ? null : $this->childOfType($index, $minf, 'stbl');
            $stsd = $stbl === null ? null : $this->childOfType($index, $stbl, 'stsd');

            if ($stsd === null) {
                continue;
            }

            // stsd: version/flags (4) + entry count (4), then the first sample
            // entry: size (4), codec (4), reserved (6), data reference (2),
            // pre_defined/reserved (16), width (2), height (2).
            $entry = $stsd['offset'] + $stsd['header'] + 8;

            if ($entry + 36 > $stsd['offset'] + $stsd['size']) {
                continue;
            }

            /** @var array{1: int, 2: int} $size */
            $size = unpack('n2', substr($index, $entry + 32, 4));

            return [
                'width' => $size[1],
                'height' => $size[2],
                'codec' => substr($index, $entry + 4, 4),
            ];
        }

        return null;
    }

    /**
     * Move the index in front of the video data, rewriting the file in
     * place. False, with the file untouched, when the index is already first
     * or the file isn't one this can safely rearrange.
     */
    public function moveIndexToFront(string $path): bool
    {
        $boxes = $this->topLevelBoxes($path);

        if ($boxes === null || $this->firstOfType($boxes, 'moof') !== null) {
            // Unreadable, or a fragmented MP4, which already streams.
            return false;
        }

        $moov = $this->firstOfType($boxes, 'moov');
        $mdat = $this->firstOfType($boxes, 'mdat');

        if ($moov === null || $mdat === null || $moov['offset'] < $mdat['offset']) {
            return false;
        }

        $index = $this->readBox($path, $moov);

        if ($index === null) {
            return false;
        }

        // Everything from the first mdat up to the old index moves forward
        // by the index's size; whatever came after the old index stays put.
        $tablesPatched = 0;
        $patched = $this->shiftChunkOffsets($index, $moov['header'], strlen($index), $mdat['offset'], $moov['offset'], $moov['size'], $tablesPatched);

        if ($patched === null || $tablesPatched === 0) {
            return false;
        }

        $fileSize = (int) filesize($path);
        $afterIndex = $moov['offset'] + $moov['size'];
        $temporary = tempnam(dirname($path), 'faststart');

        if ($temporary === false) {
            return false;
        }

        $in = fopen($path, 'rb');
        $out = fopen($temporary, 'wb');
        $written = $in !== false && $out !== false
            && $this->copyRange($in, $out, 0, $mdat['offset'])
            && fwrite($out, $patched) === strlen($patched)
            && $this->copyRange($in, $out, $mdat['offset'], $moov['offset'] - $mdat['offset'])
            && $this->copyRange($in, $out, $afterIndex, $fileSize - $afterIndex);

        if ($in !== false) {
            fclose($in);
        }

        if ($out !== false) {
            fclose($out);
        }

        clearstatcache(true, $temporary);

        if (! $written || filesize($temporary) !== $fileSize || ! rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    /**
     * The file's top-level boxes, or null when it doesn't start with `ftyp`
     * or a box runs past the end of the file.
     *
     * @return list<array{type: string, offset: int, size: int, header: int}>|null
     */
    private function topLevelBoxes(string $path): ?array
    {
        $fileSize = @filesize($path);
        $handle = $fileSize === false ? false : @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $boxes = [];
        $offset = 0;

        while ($offset < $fileSize && count($boxes) < self::MAX_TOP_LEVEL_BOXES) {
            fseek($handle, $offset);
            $header = (string) fread($handle, 16);
            $box = $this->parseHeader($header, 0, $offset, $fileSize - $offset);

            if ($box === null) {
                fclose($handle);

                return null;
            }

            $boxes[] = $box;
            $offset += $box['size'];
        }

        fclose($handle);

        return $boxes !== [] && $boxes[0]['type'] === 'ftyp' ? $boxes : null;
    }

    /**
     * The box whose header starts at `$position` in `$bytes`, reported at
     * `$offset`, or null when it's malformed or longer than `$available`.
     *
     * @return array{type: string, offset: int, size: int, header: int}|null
     */
    private function parseHeader(string $bytes, int $position, int $offset, int $available): ?array
    {
        if (strlen($bytes) < $position + 8) {
            return null;
        }

        /** @var array{1: int} $declared */
        $declared = unpack('N', substr($bytes, $position, 4));
        $size = $declared[1];
        $header = 8;

        if ($size === 1) {
            if (strlen($bytes) < $position + 16) {
                return null;
            }

            /** @var array{1: int} $large */
            $large = unpack('J', substr($bytes, $position + 8, 8));
            $size = $large[1];
            $header = 16;
        } elseif ($size === 0) {
            $size = $available;
        }

        if ($size < $header || $size > $available) {
            return null;
        }

        return ['type' => substr($bytes, $position + 4, 4), 'offset' => $offset, 'size' => $size, 'header' => $header];
    }

    /**
     * The boxes directly inside `$bytes` between `$start` and `$end`, stopping
     * at the first malformed one.
     *
     * @return list<array{type: string, offset: int, size: int, header: int}>
     */
    private function childBoxes(string $bytes, int $start, int $end): array
    {
        $children = [];

        while ($start < $end) {
            $box = $this->parseHeader($bytes, $start, $start, $end - $start);

            if ($box === null) {
                break;
            }

            $children[] = $box;
            $start += $box['size'];
        }

        return $children;
    }

    /**
     * @param  array{type: string, offset: int, size: int, header: int}  $parent
     * @return array{type: string, offset: int, size: int, header: int}|null
     */
    private function childOfType(string $bytes, array $parent, string $type): ?array
    {
        $children = $this->childBoxes($bytes, $parent['offset'] + $parent['header'], $parent['offset'] + $parent['size']);

        return $this->firstOfType($children, $type);
    }

    /**
     * @param  list<array{type: string, offset: int, size: int, header: int}>  $boxes
     * @return array{type: string, offset: int, size: int, header: int}|null
     */
    private function firstOfType(array $boxes, string $type): ?array
    {
        foreach ($boxes as $box) {
            if ($box['type'] === $type) {
                return $box;
            }
        }

        return null;
    }

    /**
     * The whole box read from the file, its own header included, so offsets
     * inside it are relative to the box's start.
     *
     * @param  array{type: string, offset: int, size: int, header: int}  $box
     */
    private function readBox(string $path, array $box): ?string
    {
        if ($box['size'] > self::MAX_INDEX_BYTES) {
            return null;
        }

        $bytes = @file_get_contents($path, false, null, $box['offset'], $box['size']);

        return is_string($bytes) && strlen($bytes) === $box['size'] ? $bytes : null;
    }

    /**
     * `$bytes` with every chunk offset (stco/co64) that points into
     * [`$from`, `$to`) moved forward by `$shift`, or null when the file uses
     * something whose offsets this can't safely move.
     */
    private function shiftChunkOffsets(string $bytes, int $start, int $end, int $from, int $to, int $shift, int &$tablesPatched): ?string
    {
        foreach ($this->childBoxes($bytes, $start, $end) as $box) {
            $body = $box['offset'] + $box['header'];
            $boxEnd = $box['offset'] + $box['size'];

            if (in_array($box['type'], self::INDEX_CONTAINERS, true)) {
                $bytes = $this->shiftChunkOffsets($bytes, $body, $boxEnd, $from, $to, $shift, $tablesPatched);
            } elseif ($box['type'] === 'stco' || $box['type'] === 'co64') {
                $bytes = $this->shiftTable($bytes, $body, $boxEnd, $box['type'] === 'co64', $from, $to, $shift);
                $tablesPatched++;
            } elseif ($box['type'] === 'cmov' || $box['type'] === 'saio') {
                // A compressed index, or encryption data with its own
                // absolute offsets: neither is moved safely by this.
                return null;
            }

            if ($bytes === null) {
                return null;
            }
        }

        return $bytes;
    }

    private function shiftTable(string $bytes, int $body, int $end, bool $wide, int $from, int $to, int $shift): ?string
    {
        // version/flags (4), entry count (4), then one offset per chunk.
        if ($body + 8 > $end) {
            return null;
        }

        /** @var array{1: int} $count */
        $count = unpack('N', substr($bytes, $body + 4, 4));
        $width = $wide ? 8 : 4;
        $format = $wide ? 'J' : 'N';

        $tableLength = $count[1] * $width;

        if ($body + 8 + $tableLength > $end) {
            return null;
        }

        $table = '';

        foreach (str_split(substr($bytes, $body + 8, $tableLength), $width) as $entryBytes) {
            if ($entryBytes === '') {
                continue;
            }

            /** @var array{1: int} $entry */
            $entry = unpack($format, $entryBytes);
            $value = $entry[1];

            if ($value >= $from && $value < $to) {
                $value += $shift;
            }

            if (! $wide && $value > 0xFFFFFFFF) {
                return null;
            }

            $table .= pack($format, $value);
        }

        return substr_replace($bytes, $table, $body + 8, $tableLength);
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function copyRange($in, $out, int $offset, int $length): bool
    {
        if ($length === 0) {
            return true;
        }

        return fseek($in, $offset) === 0 && stream_copy_to_stream($in, $out, $length) === $length;
    }
}
