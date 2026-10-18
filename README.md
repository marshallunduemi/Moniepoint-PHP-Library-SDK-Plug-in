# Moniepoint PHP Library SDK / Plug-in

**Developer:** Marshall Unduemi Josiah

This is an **unofficial, community-built PHP integration starter** intended to help developers explore integrating an application with the Moniepoint POS API. It is not developed, endorsed, certified, or supported by Moniepoint or TeamApt.

## Features in this starter
- Generate an access token using client credentials
- Send a sample push-payment request
- Request a transaction status
- Use PHP cURL for HTTP requests

## Requirements
- PHP 7.4 or later
- PHP cURL extension enabled
- Authorized Moniepoint API access and credentials for your business

## Configure
Set these environment variables on your server:
- `MONIEPOINT_CLIENT_ID`
- `MONIEPOINT_CLIENT_SECRET`
- `MONIEPOINT_TERMINAL_SERIAL`

See `.env.example` for placeholders. Do not put real secrets in public files, source control, or frontend JavaScript.

## Files
- `moniepoint.php` — reusable API functions
- `example.php` — demonstration request
- `.env.example` — environment-variable template
- `README.md` — setup instructions and project disclaimer

## Important
This is a starter template, not a verified production integration. Before sending live payments, verify the exact push-payment endpoint, status endpoint, required payload fields, payment method values, amount units, authentication response, and terminal identifier against your current authorized Moniepoint API reference. The sample endpoint and payload fields may need adjustment.

A successful HTTP acceptance is not proof that a payment completed. Verify final transaction status and reconcile the amount before fulfilling an order. Add authentication, CSRF protection where applicable, input validation, idempotency, rate limiting, and private server-side logging before production use.

## Trademark and affiliation disclaimer
“Moniepoint” is referenced only to identify the service this unofficial project is intended to integrate with. All relevant names and trademarks belong to their respective owners. This project is independent and is not an official Moniepoint product, library, plug-in, or SDK.
