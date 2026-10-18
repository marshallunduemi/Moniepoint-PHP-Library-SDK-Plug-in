<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/moniepoint.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $action = $_POST['action'] ?? 'payment';

        /*
         * VERIFY EXISTING TRANSACTION
         */
        if ($action === 'verify') {
            $reference = $_POST['reference'] ?? '';

            if (
                !is_string($reference) ||
                !preg_match('/^ORDER-[a-f0-9]{12}$/', $reference)
            ) {
                throw new InvalidArgumentException(
                    'Invalid transaction reference.'
                );
            }

            if (!function_exists('moniepointPaymentStatus')) {
    throw new RuntimeException(
        'moniepointPaymentStatus() is missing from moniepoint.php.'
    );
}

$result = moniepointPaymentStatus($reference);

echo json_encode([
    'success' => true,
    'reference' => $reference,
    'result' => $result
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

exit;
        }

        /*
         * REQUEST NEW PAYMENT
         */
        if ($action !== 'payment') {
            throw new InvalidArgumentException('Unknown action.');
        }

        $amountInput = $_POST['amount'] ?? '';

        if (
            !is_string($amountInput) ||
            !preg_match('/^[0-9]+$/', $amountInput) ||
            (float) $amountInput <= 0 ||
            (float) $amountInput > 1000000000
        ) {
            throw new InvalidArgumentException(
                'Enter a valid positive whole-number amount.'
            );
        }

        if (($_POST['confirm_receipt'] ?? '') !== 'yes') {
            throw new InvalidArgumentException(
                'Please confirm the payment details before proceeding.'
            );
        }

        $terminalSerial = getenv('MONIEPOINT_TERMINAL_SERIAL');

        if (!$terminalSerial) {
            throw new RuntimeException(
                'MONIEPOINT_TERMINAL_SERIAL is not configured.'
            );
        }

        // Confirm the required amount unit in your API documentation.
        $amount = (int) $amountInput;
        $reference = 'ORDER-' . bin2hex(random_bytes(6));

        $result = moniepointPushPayment(
            $terminalSerial,
            $amount,
            $reference,
            'CARD_PURCHASE'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment request submitted. Verify the final transaction status.',
            'reference' => $reference,
            'result' => $result
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (InvalidArgumentException $e) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Throwable $e) {
        http_response_code(500);

        // In production, log details privately.
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moniepoint POS Payment</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f3f5f9;
            color: #202938;
            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 460px;
            padding: 28px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .08);
        }

        h1 {
            margin-top: 0;
            font-size: 25px;
        }

        h2 {
            font-size: 19px;
            margin-top: 30px;
        }

        .muted {
            color: #667085;
            line-height: 1.5;
        }

        label.field-label {
            display: block;
            margin: 18px 0 8px;
            font-weight: 600;
        }

        input[type="number"],
        input[type="text"] {
            width: 100%;
            padding: 13px;
            border: 1px solid #ccd2dc;
            border-radius: 8px;
            font-size: 16px;
        }

        .confirm {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 18px;
            line-height: 1.5;
        }

        .confirm input {
            margin-top: 4px;
        }

        button {
            width: 100%;
            margin-top: 18px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #087443;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button.secondary {
            background: #243b53;
        }

        button:disabled {
            opacity: .6;
            cursor: wait;
        }

        .result {
            display: none;
            margin-top: 18px;
            padding: 14px;
            border-radius: 8px;
            background: #f1f5f9;
            overflow-wrap: anywhere;
        }

        pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 12px;
        }

        .divider {
            margin-top: 28px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>

<body>
<main class="card">
    <h1>Moniepoint POS Payment</h1>

    <p class="muted">
        Enter the amount to request payment from your configured POS terminal.
    </p>

    <form id="paymentForm">
        <label class="field-label" for="amount">
            Amount (API units)
        </label>

        <input
            type="number"
            id="amount"
            name="amount"
            min="1"
            max="1000000000"
            step="1"
            placeholder="Enter amount"
            required
        >

        <label class="confirm">
            <input
                type="checkbox"
                name="confirm_receipt"
                value="yes"
                required
            >
            <span>
                I confirm the amount is correct and the customer is ready
                to proceed with the POS payment.
            </span>
        </label>

        <button type="submit" id="payButton">
            Request POS Payment
        </button>
    </form>

    <section id="paymentResult" class="result" aria-live="polite">
        <strong id="paymentTitle"></strong>
        <pre id="paymentBody"></pre>
        <button
            type="button"
            class="secondary"
            id="copyReferenceButton"
            hidden
        >
            Copy Transaction Reference
        </button>
    </section>

    <div class="divider"></div>

    <h2>Verify Transaction</h2>

    <p class="muted">
        Enter the reference returned when you requested the payment.
    </p>

    <form id="verifyForm">
        <label class="field-label" for="reference">
            Transaction Reference
        </label>

        <input
            type="text"
            id="reference"
            name="reference"
            placeholder="ORDER-xxxxxxxxxxxx"
            pattern="ORDER-[a-fA-F0-9]{12}"
            required
        >

        <button
            type="submit"
            class="secondary"
            id="verifyButton"
        >
            Verify Transaction
        </button>
    </form>

    <section id="verifyResult" class="result" aria-live="polite">
        <strong id="verifyTitle"></strong>
        <pre id="verifyBody"></pre>
    </section>
</main>

<script>
const paymentForm = document.getElementById('paymentForm');
const payButton = document.getElementById('payButton');

const paymentResult = document.getElementById('paymentResult');
const paymentTitle = document.getElementById('paymentTitle');
const paymentBody = document.getElementById('paymentBody');
const referenceInput = document.getElementById('reference');
const copyReferenceButton = document.getElementById('copyReferenceButton');

const verifyForm = document.getElementById('verifyForm');
const verifyButton = document.getElementById('verifyButton');
const verifyResult = document.getElementById('verifyResult');
const verifyTitle = document.getElementById('verifyTitle');
const verifyBody = document.getElementById('verifyBody');

async function sendRequest(formData) {
    const response = await fetch(window.location.pathname, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.error || 'The request failed.');
    }

    return data;
}

paymentForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    if (!paymentForm.reportValidity()) return;

    if (!confirm('Send this payment request to the POS terminal?')) {
        return;
    }

    payButton.disabled = true;
    payButton.textContent = 'Sending request...';
    paymentResult.style.display = 'block';
    paymentTitle.textContent = 'Processing request';
    paymentBody.textContent = '';
    copyReferenceButton.hidden = true;

    const formData = new FormData(paymentForm);
    formData.append('action', 'payment');

    try {
        const data = await sendRequest(formData);

        paymentTitle.textContent = 'Payment request response';
        paymentBody.textContent = JSON.stringify(data, null, 2);

        if (data.reference) {
            referenceInput.value = data.reference;
            copyReferenceButton.hidden = false;
        }

        // The request being accepted does not mean payment is complete.
    } catch (error) {
        paymentTitle.textContent = 'Request error';
        paymentBody.textContent = error.message;
    } finally {
        payButton.disabled = false;
        payButton.textContent = 'Request POS Payment';
    }
});

copyReferenceButton.addEventListener('click', async function () {
    if (!referenceInput.value) return;

    try {
        await navigator.clipboard.writeText(referenceInput.value);
        copyReferenceButton.textContent = 'Reference Copied';
    } catch (error) {
        referenceInput.select();
        copyReferenceButton.textContent = 'Select and Copy Reference';
    }
});

verifyForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    if (!verifyForm.reportValidity()) return;

    verifyButton.disabled = true;
    verifyButton.textContent = 'Verifying...';
    verifyResult.style.display = 'block';
    verifyTitle.textContent = 'Checking transaction';
    verifyBody.textContent = '';

    const formData = new FormData();
    formData.append('action', 'verify');
    formData.append('reference', referenceInput.value.trim());

    try {
        const data = await sendRequest(formData);

        verifyTitle.textContent = 'Verification response';
        verifyBody.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
        verifyTitle.textContent = 'Verification error';
        verifyBody.textContent = error.message;
    } finally {
        verifyButton.disabled = false;
        verifyButton.textContent = 'Verify Transaction';
    }
});
</script>
</body>
</html>
