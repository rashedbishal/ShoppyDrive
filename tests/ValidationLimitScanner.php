<?php

declare(strict_types=1);

namespace Tests;

use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Finds every validation rule written in app/ (form requests, inline
 * `$request->validate([...])`, `Validator::make()`) and reads its limits, so
 * a test can compare them with the columns they are saved into (DISC-68).
 *
 * It reads the source rather than calling rules(), because most rules are
 * inline in controllers and many rules() methods need a live request.
 */
class ValidationLimitScanner
{
    /** Rules whose max/min are about files, list sizes or images, not text. */
    private const NOT_A_VALUE_LIMIT = '/[\'"|](?:file|image|array|list|boolean)[\'"|:]|mimes:|mimetypes:|dimensions:|RasterImageOrUrl|hasFile/';

    private const NUMBER_RULE = '/[\'"|](?:integer|numeric|decimal)[\'"|:]/';

    /** Only arrays that look like validation rules, not messages or payloads. */
    private const VALIDATION_RULE = '/[\'"|](?:required|nullable|sometimes|filled|present|string|integer|numeric|decimal|url|email|max:|min:|between:)/';

    /**
     * @return list<array{file: string, line: int, field: string, kind: 'text'|'number', min: float|null, max: float|null, unresolved: list<string>, models: list<string>}>
     */
    public static function scan(string $directory): array
    {
        $rules = [];

        foreach ((new Finder)->files()->in($directory)->name('*.php') as $file) {
            $source = $file->getContents();
            $class = self::classOf($source);
            $models = self::modelsUsedBy($source);

            preg_match_all('/([\'"])([A-Za-z0-9_.*-]+)\1\s*=>\s*(?=[\[\'"])/', $source, $keys, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($keys as $key) {
                $start = $key[0][1] + strlen($key[0][0]);
                $rule = self::expressionAt($source, $start);

                if ($rule === null || ! preg_match(self::VALIDATION_RULE, $rule) || preg_match(self::NOT_A_VALUE_LIMIT, $rule)) {
                    continue;
                }

                $unresolved = [];
                $max = self::bound($rule, 'max', $class, $source, $unresolved);
                $min = self::bound($rule, 'min', $class, $source, $unresolved);

                if (preg_match('/(?<=[\'"|])between:(-?[\d.]+),(-?[\d.]+)/', $rule, $between)) {
                    [$min, $max] = [(float) $between[1], (float) $between[2]];
                }

                $isNumber = (bool) preg_match(self::NUMBER_RULE, $rule);

                if (! $isNumber && ! preg_match('/[\'"|](?:string|url|email|alpha_dash|alpha_num)[\'"|:]/', $rule) && $max === null) {
                    continue;
                }

                $rules[] = [
                    'file' => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()),
                    'line' => substr_count($source, "\n", 0, $start) + 1,
                    'field' => $key[2][0],
                    'kind' => $isNumber ? 'number' : 'text',
                    'min' => $min,
                    'max' => $max,
                    'unresolved' => $unresolved,
                    'models' => $models,
                ];
            }
        }

        return $rules;
    }

    /**
     * The array literal or string literal starting at $offset, brackets
     * balanced and quoted text skipped.
     */
    private static function expressionAt(string $source, int $offset): ?string
    {
        $open = $source[$offset];

        if ($open !== '[') {
            return preg_match('/\G([\'"])((?:\\\\.|(?!\1).)*)\1/s', $source, $match, 0, $offset) ? $match[0] : null;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($i = $offset; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']' && --$depth === 0) {
                return substr($source, $offset, $i - $offset + 1);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $unresolved
     */
    private static function bound(string $rule, string $name, ?string $class, string $source, array &$unresolved): ?float
    {
        if (preg_match('/(?<=[\'"|])'.$name.':(-?[\d.]+)/', $rule, $literal)) {
            return (float) $literal[1];
        }

        if (preg_match('/(?<=[\'"|])'.$name.':[\'"]\s*\.\s*([A-Za-z_\\\\]+)::([A-Z0-9_]+)/', $rule, $constant)) {
            $owner = in_array($constant[1], ['self', 'static'], true) ? $class : self::resolveClass($constant[1], $source);

            if ($owner !== null && class_exists($owner) && (new ReflectionClass($owner))->hasConstant($constant[2])) {
                return (float) (new ReflectionClass($owner))->getConstant($constant[2]);
            }
        }

        if (preg_match('/(?<=[\'"|])'.$name.':[\'"]\s*\./', $rule)) {
            $unresolved[] = $name;
        }

        return null;
    }

    /**
     * The App\\Models classes a file works with, by its imports.
     *
     * @return list<string>
     */
    public static function modelsUsedBy(string $source): array
    {
        preg_match_all('/\\\\?App\\\\Models\\\\(\\w+)/', $source, $matches);

        return array_values(array_unique(array_map(fn (string $model): string => 'App\\Models\\'.$model, $matches[1])));
    }

    private static function classOf(string $source): ?string
    {
        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) || ! preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $source, $class)) {
            return null;
        }

        return $namespace[1].'\\'.$class[1];
    }

    private static function resolveClass(string $name, string $source): ?string
    {
        $name = ltrim($name, '\\');

        if (preg_match('/^use\s+([^;]*\\\\'.preg_quote($name, '/').');/m', $source, $import)) {
            return $import[1];
        }

        preg_match('/^namespace\s+([^;]+);/m', $source, $namespace);

        return isset($namespace[1]) && class_exists($namespace[1].'\\'.$name) ? $namespace[1].'\\'.$name : (class_exists($name) ? $name : null);
    }
}
