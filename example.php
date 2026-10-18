<?php
declare(strict_types=1);

require_once __DIR__ . '/moniepoint.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Demo only. Replace with validated order data from your application.
    // Do not accept arbitrary amounts or terminal serials from untrusted users.
    $terminalSerial = getenv('MONIEPOINT_TERMINAL_SERIAL');

    if (!$terminalSerial) {
        throw new RuntimeException('Set MONIEPOINT_TERMINAL_SERIAL on the server.');
    }

    // Example amount/reference only. Confirm amount units with Moniepoint.
    $result = moniepointPushPayment(
        $terminalSerial,
        11000,
        'ORDER-' . bin2hex(random_bytes(6)),
        'ANY'
    );

    http_response_code(200);
    echo json_encode(['success' => true, 'result' => $result],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    // In production, log details privately and return a generic error.
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
