<?php

declare(strict_types=1);

// This is a very minimal example.
// In a real application, validate input and handle errors carefully.

$callbackData = [
    'OutSum' => $_REQUEST['OutSum'] ?? null,
    'InvId' => $_REQUEST['InvId'] ?? null,
    'SignatureValue' => $_REQUEST['SignatureValue'] ?? null,
];

$stateDir = __DIR__ . '/../var';
if (!is_dir($stateDir)) {
    mkdir($stateDir, 0777, true);
}

file_put_contents(
    $stateDir . '/robokassa_callback.json',
    json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// According to Robokassa docs, you should output "OK<InvId>" if everything is fine. [web:22]
if (!empty($callbackData['InvId'])) {
    echo 'OK' . $callbackData['InvId'];
} else {
    echo 'ERROR';
}
