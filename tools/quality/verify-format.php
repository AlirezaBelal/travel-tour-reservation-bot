<?php

declare(strict_types=1);

// Formatting-only PRs must preserve significant PHP tokens (control braces may be added).
$base = $argv[1] ?? '';
if (preg_match('/^[a-f0-9]{40}$/D', $base) !== 1) {
    throw new InvalidArgumentException('A full base commit SHA is required.');
}
$normalize = static function (string $source): array {
    $result = [];
    foreach (token_get_all($source, TOKEN_PARSE) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($token[0] === T_ELSEIF) {
                $result[] = [T_ELSE, 'else'];
                $result[] = [T_IF, 'if'];
                continue;
            }
        } elseif ($token === '{' || $token === '}') {
            continue;
        }
        $result[] = is_array($token) ? [$token[0], $token[1]] : $token;
    }
    return $result;
};
$count = 0;
foreach (glob('src/*.php') as $path) {
    $pipes = [];
    $process = proc_open(['git', 'show', $base . ':' . $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $before = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $errors !== '') {
        throw new RuntimeException('Cannot read formatting base source: ' . $path);
    }
    $after = file_get_contents($path);
    if ($after === false || $normalize($before) !== $normalize($after)) {
        throw new RuntimeException('Significant tokens changed in formatting-only PR: ' . $path);
    }
    $count++;
}
if ($count < 25) {
    throw new RuntimeException('Formatting token check scanned too few files.');
}
fwrite(STDOUT, "Formatting tokens preserved across $count source files (ignoring whitespace/comments/control braces).\n");
