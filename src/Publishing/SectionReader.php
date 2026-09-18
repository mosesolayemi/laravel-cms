<?php

namespace Cms\Publishing;

use Cms\Components\ComponentRegistry;
use Cms\Models\Components\Component;
use RuntimeException;

/**
 * Reads the sections back out of a published file, in order:
 *
 *     @php $__cmsData = [...]; @endphp  @cmsSection('type', …) / @cmsBlock('type', …)
 *     @cmsComponent('handle')
 *
 * Data blocks are evaluated only after checking they hold nothing but plain
 * values — strings, numbers, true/false/null and arrays — so syncing can
 * never run code written into the file.
 */
class SectionReader
{
    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function read(string $source): array
    {
        $pattern = '/@php\s+\$__cmsData\s*=\s*(?<data>\[.*?\])\s*;\s*@endphp\s*@cms(?:Section|Block)\(\s*\'(?<type>[\w-]+)\''
            . '|@cmsComponent\(\s*\'(?<handle>[^\']+)\'\s*\)/s';

        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        $blocks = [];

        foreach ($matches as $match) {
            if (filled($match['handle'] ?? null)) {
                $id = Component::query()->where('handle', $match['handle'])->value('id');

                if (! $id) {
                    throw new RuntimeException("The file places library component “{$match['handle']}”, which does not exist.");
                }

                $blocks[] = ['type' => ComponentRegistry::LIBRARY, 'data' => ['component_id' => $id]];

                continue;
            }

            $blocks[] = ['type' => $match['type'], 'data' => static::evaluate($match['data'])];
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    public static function evaluate(string $literal): array
    {
        $allowed = [T_WHITESPACE, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_DOUBLE_ARROW, T_COMMENT, T_DOC_COMMENT];

        foreach (array_slice(token_get_all('<?php ' . $literal . ';'), 1) as $token) {
            if (is_string($token)) {
                if (! in_array($token, ['[', ']', ',', ';', '-'], true)) {
                    throw new RuntimeException("A data block holds “{$token}”, which is not plain data; it was not synced.");
                }

                continue;
            }

            [$id, $text] = $token;

            if ($id === T_STRING && in_array(strtolower($text), ['true', 'false', 'null'], true)) {
                continue;
            }

            if (! in_array($id, $allowed, true)) {
                throw new RuntimeException("A data block holds “{$text}”, which is not plain data; it was not synced.");
            }
        }

        $value = eval('return ' . $literal . ';');

        if (! is_array($value)) {
            throw new RuntimeException('A data block is not an array; it was not synced.');
        }

        return $value;
    }
}
