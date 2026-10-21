<?php
declare(strict_types=1);

require __DIR__ . '/../src/product_bootstrap.php';
$parameter = (new ReflectionMethod(TourController::class, '__construct'))->getParameters()[0];
if ($parameter->getName() !== 'catalogue' || $parameter->isOptional()
    || (string)$parameter->getType() !== TourCatalogService::class) {
    throw new RuntimeException('Catalogue must be an explicit required TourController dependency.');
}
$source = file_get_contents(__DIR__ . '/../src/TourCatalogService.php');
if ($source === false) {
    throw new RuntimeException('Catalogue source is missing.');
}
foreach (token_get_all($source) as $token) {
    if (is_array($token) && $token[0] === T_STRING
        && (in_array(strtolower($token[1]), ['productbot', 'outbox', 'notificationworker'], true)
            || str_starts_with(strtolower($token[1]), 'curl_'))) {
        throw new RuntimeException('Catalogue depends on Telegram/delivery: ' . $token[1]);
    }
}
$source = file_get_contents(__DIR__ . '/../src/TourController.php');
if ($source === false) {
    throw new RuntimeException('TourController source is missing.');
}
$tokens = token_get_all($source);
$checked = [];
for ($i = 0, $count = count($tokens); $i < $count; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
        continue;
    }
    $name = null;
    for ($j = $i + 1; $j < $count && $tokens[$j] !== '{'; $j++) {
        if ($name === null && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $name = $tokens[$j][1];
        }
    }
    if (!in_array($name, ['listing', 'categories', 'showTour'], true)) {
        continue;
    }
    $checked[] = $name;
    for ($depth = 1, $j++; $j < $count && $depth > 0; $j++) {
        $token = $tokens[$j];
        if ($token === '{' || (is_array($token)
            && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif ($token === '}') {
            $depth--;
        } elseif (is_array($token) && $token[0] === T_STRING
            && in_array(strtolower($token[1]), ['database', 'pdo', 'bookings'], true)) {
            throw new RuntimeException("Catalogue UI method $name bypasses its application service.");
        }
    }
}
sort($checked);
if ($checked !== ['categories', 'listing', 'showTour']) {
    throw new RuntimeException('Catalogue boundary check must inspect all three production methods.');
}
fwrite(STDOUT, "Catalogue architecture passed: three UI boundaries, required injection, no delivery dependency.\n");
