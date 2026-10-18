# Moniepoint POS PHP integration starter

This ZIP contains a PHP/cURL starter for token generation, pushing a payment request,
and checking transaction status.

## Requirements
- PHP 7.4 or later
- PHP cURL extension enabled
- Moniepoint API access and credentials authorized for your business

## Configure
Set these environment variables on the server:
- `MONIEPOINT_CLIENT_ID`
- `MONIEPOINT_CLIENT_SECRET`
- `MONIEPOINT_TERMINAL_SERIAL`

See `.env.example` for placeholders. Do not put real secrets in public files,
source control, or frontend JavaScript.

## Files
- `moniepoint.php` — reusable API functions
- `example.php` — demonstration request
- `.env.example` — environment-variable template

## Important
This is a starter template, not a verified production integration. Before sending live
payments, verify the exact push-payment endpoint, status endpoint, required payload fields,
payment method values, amount units, authentication response, and terminal identifier
against your current authorized Moniepoint API reference. The sample endpoint/payload
fields may need adjustment. A successful HTTP acceptance is not proof that a payment
completed; verify final transaction status and reconcile the amount before fulfilling
an order. Add authentication, CSRF protection where applicable, input validation,
idempotency, rate limiting, and private server-side logging before production use.
