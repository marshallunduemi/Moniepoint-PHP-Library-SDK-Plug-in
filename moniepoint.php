<?php
declare(strict_types=1);

/*
 * Moniepoint POS API integration starter.
 * Requires PHP 7.4+ and cURL.
 *
 * IMPORTANT:
 * Confirm the exact request fields and endpoint paths against your
 * authorized Moniepoint API documentation before using real payments.
 */

const MONIEPOINT_BASE_URL = 'https://channel.moniepoint.com';

function moniepointRequest(
    string $method,
    string $endpoint,
    ?array $payload = null,
    ?string $token = null
): array {
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init(MONIEPOINT_BASE_URL . $endpoint);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($payload !== null) {
        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Could not encode JSON payload.');
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Connection failed: ' . $error);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $data = ['rawResponse' => $response];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            'Moniepoint API returned HTTP ' . $httpCode . ': ' .
            json_encode($data)
        );
    }

    return ['httpCode' => $httpCode, 'data' => $data];
}

function moniepointAccessToken(): string
{
    $clientId = getenv('MONIEPOINT_CLIENT_ID');
    $clientSecret = getenv('MONIEPOINT_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        throw new RuntimeException(
            'Set MONIEPOINT_CLIENT_ID and MONIEPOINT_CLIENT_SECRET on the server.'
        );
    }

    $result = moniepointRequest('POST', '/v1/auth', [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
    ]);

    $token = $result['data']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('No accessToken found in the API response.');
    }

    return $token;
}

/*
 * NOTE: Confirm the push-payment endpoint and payload fields against the
 * current official documentation before enabling this for live payments.
 */
function moniepointPushPayment(
    string $terminalSerial,
    int $amount,
    string $merchantReference,
    string $paymentMethod = 'ANY'
): array {
    if ($amount <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }

    $token = moniepointAccessToken();

    return moniepointRequest('POST', '/v1/transactions', [
        'terminalSerial' => $terminalSerial,
        'amount' => $amount,
        'merchantReference' => $merchantReference,
        'transactionType' => 'PURCHASE',
        'paymentMethod' => $paymentMethod,
    ], $token);
}

/*
 * NOTE: Confirm the transaction-status endpoint against the current docs.
 */
function moniepointPaymentStatus(string $merchantReference): array
{
    $token = moniepointAccessToken();

    return moniepointRequest(
        'GET',
        '/v1/transactions/merchants/' . rawurlencode($merchantReference),
        null,
        $token
    );
}
