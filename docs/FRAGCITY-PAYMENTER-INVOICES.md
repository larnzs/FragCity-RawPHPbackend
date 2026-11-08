# FragCity Server Invoice Integration

This integration activates the **سفارش‌های سرور** tab on FragCity's existing order page. FragCity remains the customer UI; Paymenter remains the invoice system of record. No invoice, payment or balance rows are copied into the FragCity database.

## Architecture

- The browser calls only `POST /api/server-invoices.php`.
- The endpoint requires an authenticated FragCity session and the session CSRF token.
- SteamID64 is read only from the server-side session.
- Email and `paymenter_user_id` are loaded from the FragCity `users` row selected by that SteamID64.
- A dedicated server-side `PaymenterInvoiceApiClient` uses `integrations.paymenter_invoices`. It does not reuse the Support API key or the FragCityAPI HMAC secret.
- The native Paymenter endpoint is read-only: `GET /api/v1/admin/invoices`.
- The API asks for `include=items,user` and validates every invoice against the expected Paymenter user ID **and** the email returned by Paymenter. The Paymenter `filter[user_id]` is only an optimization, never the authorization boundary.
- Only verified invoice IDs, dates, statuses and line items are returned to the browser. The Paymenter API key, Paymenter user ID and email are not returned.

## Dedicated Paymenter API Key permissions

Create a new Admin API Key, for example **FragCity Server Invoices**, and enable exactly these read permissions:

| Paymenter permission | API permission | Why it is required |
|---|---|---|
| View Invoices | `admin.invoices.view` | Reads the invoice collection |
| View Invoice Items | `admin.invoice_items.view` | Allows `include=items` for invoice descriptions and prices |
| View Users | `admin.users.view` | Allows `include=user` so FragCity can verify the invoice owner's ID and email |

Do not enable Create, Update or Delete for invoices, invoice items or users. Do not enable Orders, Services, Credits, Tickets, Impersonation or any other permissions. The View Users permission is used only to include the user relationship on the invoice response; the website does not call the Users collection endpoint.

The Admin API key is global, so the PHP adapter performs strict per-invoice owner checks after every list request. A missing owner relationship, missing included user record or email mismatch fails closed. Do not treat the Paymenter filter as an access-control boundary.

## Private FragCity settings

Add the new block to the private `settings/settings.json` file (the example file documents its shape):

```json
{
  "integrations": {
    "paymenter_invoices": {
      "base_url": "https://billing.fragcity.ir",
      "admin_api_key": "PASTE_THE_DEDICATED_READ_ONLY_API_KEY"
    }
  }
}
```

Use a separate key from `paymenter_support` and `paymenter_backend`. The base URL must be only the HTTPS origin; do not append `/api/v1/admin`. Never commit the runtime key or expose it in JavaScript.

## Customer API contract

`POST /api/server-invoices.php`

Headers:
- `X-FragCity-CSRF: <session csrf token>`
- `Content-Type: application/json`

Body:
```json
{ "action": "list" }
```

The SteamID64, email, Paymenter user ID and invoice owner are never accepted from browser input. The endpoint returns at most the newest 100 verified invoices.

## Paymenter v1.5.9 limitations

The Invoice API resource exposes invoice status, currency code, due date and timestamps. It does not expose a computed invoice total, remaining balance or transaction/payment-method history. FragCity calculates the sum of the server Service/ServiceUpgrade line items only and labels it **جمع اقلام سرور**; it does not present that value as the full invoice total or a confirmed remaining balance. Explicit non-server invoice lines such as Credits are omitted; generic manually entered lines are shown because Paymenter does not encode a service relation for them. Taxes or successful transactions may make the payable/remaining amount differ. No pay, refund, edit or cancellation action is provided by this integration.

## Read-only verification

After configuring the dedicated key and deploying the website changes, first run the offline contract test (it does not connect to Paymenter), then run the live read-only preflight from the Raw PHP repository root:

```bash
php tools/paymenter-invoices-preflight.php 7656119XXXXXXXXXX
```

The command checks the FragCity account link, reads the Paymenter invoices with item and user relationships, verifies the owner ID and email, and reports only the number of verified invoices. It does not mutate Paymenter data.

## Deployment

1. Update the private `settings/settings.json` with the dedicated API key.
2. Pull the Raw PHP `main` branch.
3. Run the read-only preflight using a SteamID64 that belongs to an account linked to Paymenter.
4. Sign in to FragCity and open **سفارش‌های شما → سفارش‌های سرور**.

No Paymenter extension update, database migration or Paymenter core modification is required.
