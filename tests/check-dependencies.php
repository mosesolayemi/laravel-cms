<?php

/**
 * Fails if src/ reaches for a namespace the package does not pay for.
 *
 * This is the guard for a bug that is invisible in development: the package
 * is written inside a host application, so a class the host requires is
 * autoloadable here whether or not composer.json names it. Install the
 * package somewhere else and the same class is simply absent. That is how
 * Intervention\Image shipped undeclared through v1.0.0.
 *
 * Every namespace src/ imports therefore has to be accounted for — provided
 * by a declared require, pulled in by one of them, or the package's own.
 * A new third-party namespace fails the check until someone decides which.
 *
 * Usage: php tests/check-dependencies.php
 */

$root = dirname(__DIR__);

$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$declared = array_keys($composer['require'] ?? []);

/*
 * The namespace each declared package puts on the autoloader. Only the roots
 * src/ actually imports need an entry; a package required for its service
 * provider alone never appears in a use statement.
 */
$provided = [
    'Illuminate' => 'illuminate/support',
    'Filament' => 'filament/filament',
    'Intervention' => 'intervention/image',
];

/*
 * Namespaces that arrive through something already declared rather than on
 * their own. Each one names what guarantees it, because that is the thing
 * that would have to change for the import to break. Relying on these is a
 * deliberate choice, not an oversight: pinning Livewire here would fight
 * whichever version Filament has settled on, and the framework namespaces
 * are present in every application the package can be installed into —
 * illuminate/support is declared as the proxy for the Laravel version.
 */
$transitive = [
    'Carbon' => 'illuminate/support, which requires nesbot/carbon',
    'Livewire' => 'filament/filament, which requires livewire/livewire',
    'Laravel' => 'the host application: laravel/prompts, via illuminate/console',
    'Symfony' => 'the host application: symfony/console, via illuminate/console',
];

/* The package's own root, from the PSR-4 map rather than hardcoded. */
$own = array_map(
    fn (string $prefix): string => strtok($prefix, '\\'),
    array_keys($composer['autoload']['psr-4'] ?? [])
);

/**
 * Collect the root namespace of every external reference in a file.
 *
 * The file is tokenised rather than grepped so that a namespace written in a
 * string — a class name in a config array, a message naming a class — is not
 * mistaken for a reference the autoloader has to satisfy.
 *
 * @return array<string, int[]>  [root => line numbers]
 */
function roots(string $file): array
{
    $tokens = token_get_all(file_get_contents($file));
    $found = [];

    foreach ($tokens as $i => $token) {
        if (! is_array($token)) {
            continue;
        }

        [$id, $text, $line] = $token;

        /*
         * An import. `use` also opens a closure's binding list and pulls in a
         * trait, but neither is followed by a qualified name, so looking only
         * at the next meaningful token keeps those out.
         */
        if ($id === T_USE) {
            $next = next_meaningful($tokens, $i);

            if ($next === null || ! in_array($next[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $found[root_of($next[1])][] = $line;

            continue;
        }

        /* A name written out in full, as the image driver classes are. */
        if ($id === T_NAME_FULLY_QUALIFIED) {
            $found[root_of($text)][] = $line;
        }
    }

    return $found;
}

/**
 * The next token that is not whitespace, a comment or `function`/`const`.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: int, 1: string, 2: int}|null
 */
function next_meaningful(array $tokens, int $from): ?array
{
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_FUNCTION, T_CONST];

    for ($i = $from + 1, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_string($token)) {
            return null;
        }

        if (! in_array($token[0], $skip, true)) {
            return $token;
        }
    }

    return null;
}

/** The first segment of a qualified name, with any leading separator dropped. */
function root_of(string $name): string
{
    return strtok(ltrim($name, '\\'), '\\');
}

/* ---------------------------------------------------------------------- */

$files = new RegexIterator(
    new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src')),
    '/\.php$/'
);

/** @var array<string, array<string, int[]>> $references [root => [file => lines]] */
$references = [];

foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);

    foreach (roots($file->getPathname()) as $namespace => $lines) {
        if (in_array($namespace, $own, true)) {
            continue;
        }

        $references[$namespace][$relative] = $lines;
    }
}

/*
 * Drop PHP's own classes. A reference like \RuntimeException or \BackedEnum
 * has no vendor root to attribute, and class_exists() with autoloading off
 * answers for the internal ones without touching the filesystem.
 */
$references = array_filter(
    $references,
    fn (string $namespace): bool => ! class_exists($namespace, false) && ! interface_exists($namespace, false),
    ARRAY_FILTER_USE_KEY
);

ksort($references);

$failures = [];
$warnings = [];

echo "Namespaces imported by src/\n\n";

foreach ($references as $namespace => $files) {
    $count = array_sum(array_map('count', $files));

    if (isset($provided[$namespace])) {
        $package = $provided[$namespace];

        if (in_array($package, $declared, true)) {
            printf("  ok       %-14s %3d refs  declared: %s\n", $namespace, $count, $package);

            continue;
        }

        $failures[$namespace] = sprintf(
            '%s is provided by %s, which composer.json does not require.',
            $namespace,
            $package
        );

        printf("  MISSING  %-14s %3d refs  needs: %s\n", $namespace, $count, $package);

        continue;
    }

    if (isset($transitive[$namespace])) {
        printf("  via      %-14s %3d refs  through %s\n", $namespace, $count, $transitive[$namespace]);

        continue;
    }

    $where = array_key_first($files);
    $failures[$namespace] = sprintf(
        '%s is imported (%s:%d) but nothing accounts for it. Require the package that '
        . 'provides it and add it to $provided, or record it in $transitive with the '
        . 'dependency that pulls it in.',
        $namespace,
        $where,
        $files[$where][0]
    );

    printf("  UNKNOWN  %-14s %3d refs  first at %s:%d\n", $namespace, $count, $where, $files[$where][0]);
}

/*
 * The other direction. An unused require is not a bug, so this only warns —
 * filament/filament would be needed for its panel even if src/ never named it.
 */
foreach ($provided as $namespace => $package) {
    if (in_array($package, $declared, true) && ! isset($references[$namespace])) {
        $warnings[] = sprintf('%s is required but src/ never imports %s.', $package, $namespace);
    }
}

echo "\n";

foreach ($warnings as $warning) {
    echo "warning: {$warning}\n";
}

if ($failures === []) {
    echo "PASS  every namespace src/ imports is accounted for.\n";

    exit(0);
}

foreach ($failures as $failure) {
    echo "FAIL  {$failure}\n";
}

exit(1);
