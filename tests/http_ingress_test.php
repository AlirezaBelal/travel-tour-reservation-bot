<?php
declare(strict_types=1);
require __DIR__ . '/../src/webhook.php';
require __DIR__ . '/../src/HttpIngress.php';
$secret = str_repeat('s', 40);
$valid = ['update_id' => 1, 'message' => ['message_id' => 2, 'from' => ['id' => 301, 'is_bot' => false],
    'chat' => ['id' => 301, 'type' => 'private'], 'text' => '/profile']];
$checks = 0;
foreach ([
    [null, 'POST', '{}', 403, null],
    [$secret, 'GET', '{}', 405, null],
    [$secret, 'POST', '', 200, null],
    [$secret, 'POST', false, 200, null],
    [$secret, 'POST', str_repeat('x', 1048577), 413, null],
    [$secret, 'POST', '{invalid', 400, null],
    [$secret, 'POST', '{}', 200, null],
    [$secret, 'POST', json_encode($valid, JSON_THROW_ON_ERROR), 200, $valid],
] as [$provided, $method, $body, $status, $update]) {
    if (HttpIngress::decode($secret, $provided, $method, $body) !== ['status' => $status, 'update' => $update]) {
        throw new RuntimeException('HTTP ingress behavior changed.');
    }
    $checks++;
}
fwrite(STDOUT, "Pure HTTP ingress passed ($checks checks).\n");
