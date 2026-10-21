<?php
declare(strict_types=1);

/**
 * Narrow architecture fitness test: no production entry point or service may
 * include the historical handler directly. Every legacy update must traverse
 * the tested adapter, which runs within WebhookProcessor's transaction.
 *
 * This is an inclusion-boundary check, not a general-purpose static analyzer.
 */
$root = realpath(dirname(__DIR__));
if ($root === false) {
    throw new RuntimeException('Cannot resolve repository root.');
}

$scanned = 0;
$legacySites = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($path, 'vendor/') || str_starts_with($path, 'tests/')
        || str_starts_with($path, '.git/')) {
        continue;
    }
    $scanned++;
    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        throw new RuntimeException('Cannot read source file: ' . $path);
    }
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || !in_array($token[0],
            [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            continue;
        }
        $expression = '';
        for ($next = $i + 1, $count = count($tokens); $next < $count; $next++) {
            $part = is_array($tokens[$next]) ? $tokens[$next][1] : $tokens[$next];
            if ($part === ';') {
                break;
            }
            $expression .= $part;
        }
        if (str_contains($expression, 'handler.php')) {
            $legacySites[] = $path;
        }
    }
}

if ($scanned < 15) {
    throw new RuntimeException("Architecture check unexpectedly scanned only $scanned production PHP files.");
}
if ($legacySites !== ['src/LegacyHandlerAdapter.php']) {
    throw new RuntimeException('Legacy handler include boundary changed: ' . json_encode($legacySites));
}
fwrite(STDOUT, "Architecture check passed: $scanned PHP files, one legacy adapter include.\n");
