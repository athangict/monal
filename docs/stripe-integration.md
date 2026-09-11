# DK Stripe Integration Guide (Accounts Module)

This document explains the DK Bank Stripe gateway integration implemented in the Accounts module.

## Source Specification

- data/Stripe Payment Processing API Documentation v2.pdf

## Architecture

- Controller: module/Accounts/src/Controller/StripeController.php
- Route: module/Accounts/config/module.config.php (route name: accstripe)
- View: module/Accounts/view/accounts/stripe/stripepay.phtml
- Store entry button: module/Store/view/store/payment/viewpayment.phtml

The Store module launches payment from payment details page, but the gateway logic lives in Accounts StripeController.

## End-to-End Flow

1. User opens payment record and clicks Pay with Stripe.
2. User lands on /accstripe/stripepay/:id.
3. User clicks Proceed to Secure Checkout.
4. Backend calls DK `POST /checkout`.
5. User is redirected to `response_data.session_url` from DK response.
6. DK redirects to success or cancel callback URL (browser return flow).
7. On success callback, backend verifies completion via DK `GET /check-application-status`.
8. Payment is treated successful only when `response_data` is `true`.

## Important: Callback and Webhook

The DK process flow expects both browser callback and webhook handling.

- Callback (`paymentsuccess`/`paymentcancel`) supports user navigation and immediate feedback.
- Webhook (`/accstripe/webhook`) supports server-to-server status reconciliation.
- Webhook processing verifies final outcome through DK `GET /check-application-status`.

## Webhook/Reconciliation Process

Use server-to-server confirmation so status updates do not depend only on user redirect.

1. Keep callback URLs for user experience (thank-you/cancel pages).
2. Add an asynchronous confirmation path for pending payments:
- Implemented: webhook endpoint at `/accstripe/webhook`.
- Fallback/backup: scheduled reconciliation job using `GET /check-application-status`.
3. Match records by `application_no` (`payment_no` in local payment record).
4. Update payment state idempotently (safe to process same event/check multiple times).
5. Log each update attempt with source (`callback`, `webhook`, or `reconciliation`).

### Suggested Status Handling

- `PAID`: `response_data === true` from status API.
- `PENDING`: checkout created but not yet confirmed paid.
- `CANCELLED/FAILED`: user cancelled or repeated checks confirm unpaid after timeout window.

### Reconciliation Job Template

- Frequency: every 5-15 minutes.
- Scope: payments in `PENDING` state only.
- Stop condition: mark `PAID` once confirmed true.
- Escalation: flag old pending records (for example older than 24 hours) for manual review.

This process closes the reliability gap when browser callbacks are missed.

### Webhook Endpoint Contract (Current Implementation)

- Method: `POST`
- URL: `/accstripe/webhook`
- Auth: optional `X-DK-Webhook-Token` header when `dk_stripe.webhook_token` is configured.
- Body: JSON payload that includes `application_no` (top level or nested in `response_data`, `data`, or `event_data`).
- Behavior:
    - Finds local payment by `payment_no = application_no`.
    - Calls DK status API to verify paid/unpaid.
    - Appends verification result to payment `note` for traceability.
    - Returns JSON acknowledgment.

## DK UAT Connection Details

- Base URL (UAT): `https://internal-gateway.sit.digitalkidu.bt:8082/uat/stripe/`
- Required header: `X-Gravitee-Api-Key: <your-key>`
- Content-Type: `application/json`

## DK Endpoints Used

1. Create checkout session
- Method: POST
- Path: /checkout

Request fields:

- amount (decimal, min 1)
- currency (`usd`)
- agency_name
- application_no
- submerchant_id
- dk_account
- success_url (HTTPS)
- cancel_url (HTTPS)

2. Check application status
- Method: GET
- Path: /check-application-status?application_no=...

Success condition:

- `response_data === true`

## Local Configuration

Configure in config/autoload/local.php:

```php
return [
    'dk_stripe' => [
        'base_url' => 'https://internal-gateway.sit.digitalkidu.bt:8082/uat/stripe',
        'api_key' => '...',
        'submerchant_id' => '2606308100000031',
        'dk_account' => '100100426695',
        'agency_name' => 'Your Agency Name',
        'currency' => 'usd',
        // Optional explicit HTTPS callback URLs
        // Supports {payment_id} placeholder
        'success_url' => '',
        'cancel_url' => '',
        // Optional: if set, webhook must include matching X-DK-Webhook-Token header
        'webhook_token' => '',
        'verify_ssl' => true,
    ],
];
```

Notes:

- Keep keys/secrets out of source control.
- If success_url/cancel_url are empty, controller generates canonical URLs.
- Generated URLs must still be HTTPS per DK requirements.

## Data Updates in Application

- Table: Store\Model\PaymentTable
- Field used for trace: `note`

The integration appends operational notes for:

- Checkout session creation
- Duplicate detection outcome
- Success callback verification outcome

## Error Handling Implemented

- Missing config: redirects back with flash error.
- Duplicate request (`4003`): triggers status check and resolves if already paid.
- Non-success response: keeps payment unconfirmed and displays response description.
- Cancel callback: marks as canceled in user flow (flash notice), no success update.

## Operational Recommendation

- Do not rely on callback alone for final accounting status.
- Treat webhook/reconciliation verification as source of truth.
- Run reconciliation continuously during active payment windows.

## UAT Availability Reminder

- Weekdays (Mon-Fri): 9:00 AM to 5:00 PM Bhutan Time
- Weekends/Govt holidays: unavailable

## UAT Checklist

1. Verify /checkout returns `response_code=0000`.
2. Confirm redirect to DK `session_url`.
3. Complete one successful payment in UAT.
4. Confirm success callback validates with /check-application-status.
5. Confirm local record marks completion only when `response_data=true`.
6. Test cancel path and duplicate request behavior.
7. Archive evidence (request/response and screenshots) for sign-off.
