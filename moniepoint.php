<?php
declare(strict_types=1);

/*
 * Moniepoint POS API integration.
 * PHP 7.4+ and cURL required.
 *
 * Keep your existing base URL and credentials configuration.
 * Never expose API secrets to the browser.
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

    $ch = curl_init(
        MONIEPOINT_BASE_URL . $endpoint
    );

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
            throw new RuntimeException(
                'Could not encode JSON payload.'
            );
        }

        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException(
            'Connection failed: ' . $error
        );
    }

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        $data = ['rawResponse' => $response];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            'Moniepoint HTTP ' . $httpCode . ': ' .
            json_encode($data)
        );
    }

    return [
        'httpCode' => $httpCode,
        'data' => $data,
    ];
}

/**
 * Obtain an access token.
 */
function moniepointAccessToken(): string
{
    $clientId = getenv('MONIEPOINT_CLIENT_ID');
    $clientSecret = getenv('MONIEPOINT_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        throw new RuntimeException(
            'Moniepoint credentials are not configured.'
        );
    }

    $result = moniepointRequest(
        'POST',
        '/v1/auth',
        [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ]
    );

    $token = $result['data']['accessToken'] ?? null;

    if (!is_string($token) || $token === '') {
        throw new RuntimeException(
            'No accessToken found in API response.'
        );
    }

    return $token;
}

/**
 * Push a payment request to the POS.
 * HTTP 202 means accepted, NOT paid.
 */
function moniepointPushPayment(
    string $terminalSerial,
    int $amount,
    string $merchantReference,
    string $paymentMethod = 'ANY'
): array {
    if ($amount <= 0) {
        throw new InvalidArgumentException(
            'Amount must be greater than zero.'
        );
    }

    if (trim($terminalSerial) === '' ||
        trim($merchantReference) === '') {
        throw new InvalidArgumentException(
            'Terminal serial and reference are required.'
        );
    }

    if (!in_array(
        $paymentMethod,
        ['ANY', 'CARD_PURCHASE', 'POS_TRANSFER'],
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid payment method.'
        );
    }

    $token = moniepointAccessToken();

    return moniepointRequest(
        'POST',
        '/v1/transactions',
        [
            'terminalSerial' => $terminalSerial,
            'amount' => $amount,
            'merchantReference' => $merchantReference,
            'transactionType' => 'PURCHASE',
            'paymentMethod' => $paymentMethod,
        ],
        $token
    );
}

/**
 * Retrieve the transaction status from Moniepoint.
 */
function moniepointPaymentStatus(
    string $merchantReference
): array {
    if (trim($merchantReference) === '') {
        throw new InvalidArgumentException(
            'Merchant reference is required.'
        );
    }

    $token = moniepointAccessToken();

    return moniepointRequest(
        'GET',
        '/v1/transactions/merchants/' .
            rawurlencode($merchantReference),
        null,
        $token
    );
}

/**
 * Verify whether the payment is actually completed.
 *
 * $expectedAmount must come from your server-side order,
 * NOT from an amount submitted by the browser.
 */
function moniepointVerifyPayment(
    string $merchantReference,
    int $expectedAmount
): array {
    if ($expectedAmount <= 0) {
        throw new InvalidArgumentException(
            'Expected amount must be greater than zero.'
        );
    }

    $result = moniepointPaymentStatus(
        $merchantReference
    );

    $tx = $result['data'];

    $status = strtoupper(
        trim((string) ($tx['processingStatus'] ?? 'UNKNOWN'))
    );

    $returnedReference =
        (string) ($tx['merchantReference'] ?? '');

    $requestAmount = $tx['requestAmount'] ?? null;
    $actualAmount = $tx['actualAmount'] ?? null;

    // The API response must match the order being verified.
    $referenceMatches =
        hash_equals($merchantReference, $returnedReference);

    $requestAmountMatches =
        is_numeric($requestAmount) &&
        (int) $requestAmount === $expectedAmount;

    $actualAmountMatches =
        is_numeric($actualAmount) &&
        (int) $actualAmount === $expectedAmount;

    // Fail closed: do not mark an unconfirmed payment as paid.
    $paid =
        $status === 'PROCESSED' &&
        $referenceMatches &&
        $requestAmountMatches &&
        $actualAmountMatches;

    if ($paid) {
        $message = 'Payment verified successfully.';
    } elseif ($status === 'PENDING') {
        $message = 'Payment is still pending.';
    } elseif ($status === 'CANCELLED') {
        $message = 'Payment was cancelled.';
    } elseif ($status === 'PROCESSED') {
        $message =
            'Transaction processed, but reference or amount ' .
            'could not be verified. Check transaction details.';
    } else {
        $message = 'Payment is not confirmed.';
    }

    return [
        'success' => true,
        'paid' => $paid,
        'status' => $status,
        'message' => $message,
        'reference' => $merchantReference,
        'expectedAmount' => $expectedAmount,
        'requestAmount' => $requestAmount,
        'actualAmount' => $actualAmount,
        'transactionReference' =>
            $tx['transactionReference'] ?? null,
        'httpCode' => $result['httpCode'],
    ];
}
