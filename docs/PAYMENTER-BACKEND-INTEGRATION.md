# FragCity ↔ Paymenter Backend Integration

**Status:** The signed FragCity account-bridge slice is implemented. Purchase/provisioning operations remain pending verification.

## 1. Product boundary

FragCity's Raw PHP website owns the complete customer and administrator experience:

- Public server catalogue and custom checkout UI.
- Customer account, server dashboard and all customer-facing controls.
- Custom administrator dashboard and product/node presentation.
- Zarinpal request, callback and server-side payment verification.
- Product pricing, hardware profiles, allowed customer selections and the external order state machine.

Paymenter is a private backend supervisor. Its own customer-facing theme and admin UI are not part of the FragCity customer workflow. It is used for the commercial records and lifecycle that are useful to FragCity:

- Paymenter users, orders, services, invoices, invoice items and tickets.
- Service status and expiry/billing lifecycle.
- The FragCity CS2 server extension and Pterodactyl provisioning.
- Internal service actions/monitoring where Paymenter provides a safe supported path.

Pterodactyl/Wings own the actual server record, node allocation, Docker container and execution lifecycle. Customers must not be directed to the Pterodactyl panel. FragCity's backend will authorize and proxy the supported operations.

## 2. Source-of-truth boundaries

| Data | Authoritative owner |
|---|---|
| FragCity login identity and Steam account | FragCity website |
| Public product catalogue, hardware labels, allowed checkout options and display prices | FragCity website |
| Zarinpal authority, verified amount and payment reference | FragCity website payment ledger |
| External purchase intent and retry/outbox state | FragCity website |
| Paymenter user/order/service/invoice/ticket records | Paymenter |
| Pterodactyl server UUID, node, allocations and container lifecycle | Pterodactyl |
| CS2 plugin configuration and game-mode settings | Private files of each game instance, managed through FragCity backend |

Do not recreate retired Windows Agent tables or use them as the new provisioning source.

## 3. Paymenter v1.5.9 API findings

The pinned API routes expose Admin API resources under `/api/v1/admin` for users, products, services, orders, invoices, invoice items, tickets, ticket messages and credits. These routes use a Paymenter Admin API key as a Bearer token. The API key must be enabled, have type `admin`, and carry only the permissions required by the integration. IP allowlisting should be used once the website's outbound IP is confirmed.

The public resources do not expose every operation required by FragCity:

- The Service create request accepts core service fields, but not arbitrary per-service Egg properties or Config Option rows.
- There is no Invoice Transaction resource in the standard API routes.
- Creating a Service record alone does not trigger server creation.

The Paymenter source shows the supported lifecycle: a paid invoice is processed by `ProcessPaidInvoiceService`; a pending server Service is sent to `CreateJob`; that job invokes the configured server extension. The supported `ExtensionHelper::addPayment()` path creates an invoice transaction; Paymenter's invoice settlement flow can then mark the invoice paid and run its normal paid-invoice processing.

Therefore, do not make direct SQL writes into Paymenter's database and do not mark invoices paid by simply changing a status field. Use a dedicated `FragCityAPI` add-on extension for the authenticated server-to-server Bridge. The previous locale-only `extensions/Others/FragCity` add-on has been removed and must not be reused. Do not fork or patch Paymenter core.

Primary source references:

- [Paymenter v1.5.9 API routes](https://github.com/Paymenter/Paymenter/blob/v1.5.9/routes/api.php)
- [Admin API middleware](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Http/Middleware/Api/AdminApi.php)
- [Create Service request](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Http/Requests/Api/Admin/Services/CreateServiceRequest.php)
- [ExtensionHelper](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Helpers/ExtensionHelper.php)
- [Invoice observer](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Observers/InvoiceObserver.php)
- [Paid invoice processor](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Services/Invoice/ProcessPaidInvoiceService.php)
- [Server create job](https://github.com/Paymenter/Paymenter/blob/v1.5.9/app/Jobs/Server/CreateJob.php)

## 4. Intended purchase flow

1. FragCity calculates the price server-side from its own published product/option catalogue. Browser-submitted prices are never trusted.
2. FragCity creates a local pending purchase intent with a unique external UUID, customer identity, selected options, immutable price/currency snapshot and status.
3. FragCity creates a Zarinpal payment request using the stored amount and associates the returned Authority with that intent.
4. Zarinpal returns the customer to FragCity. The website backend verifies the Authority directly with Zarinpal and compares the verified amount against the immutable intent. A browser redirect or `Status=OK` alone is never payment proof.
5. In one local database transaction, FragCity records the payment as verified and creates an outbox/provisioning task. Duplicate callbacks must not credit or provision twice.
6. A retryable server-to-server call sends the verified purchase to the authenticated FragCity endpoint inside Paymenter. No browser request may call this endpoint.
7. The Paymenter extension validates the signature, timestamp, nonce, external purchase UUID, configured product/plan, amount/currency and allowed environment variables. It creates/links the Paymenter user, Order, pending Service, Invoice, Invoice Item and allowed Service Configs; then it records the verified payment through Paymenter's supported payment helper.
8. Paymenter processes the paid invoice and queues its regular server creation job. The FragCity server extension passes only allowlisted values to the Pterodactyl Egg.
9. FragCity records the resulting Paymenter IDs and displays a normalized provisioning state. A reconciliation task repairs any local/remote mismatch.

The purchase UUID must be unique in both systems. A timeout after Paymenter accepted a request must be safe to retry without creating a second Service or charging twice.

## 5. First implementation slice: read-only API connectivity

The website-side client is `class/PaymenterApiClient.php`. The CLI-only check is `tools/paymenter-api-preflight.php`. It makes exactly one read-only request:

`GET /api/v1/admin/products?per_page=1`

The minimum API permission for this preflight is `admin.products.view`. Store the base URL and API key only in the private runtime `settings/settings.json` under `integrations.paymenter_backend`. Do not put a real key in `settings/settings.example.json`, Git, JavaScript, a URL or a log.

Run the preflight from the website repository root after adding the private runtime values:

```bash
php tools/paymenter-api-preflight.php
```

Expected output contains a PASS line, HTTP 200 and a count for the one-item sample page. This check does not create or modify any Paymenter records or CS2 servers.

## 6. Next implementation slices

1. Read-only Paymenter connection and least-privilege API key.
2. Create a dedicated Paymenter add-on at `extensions/Others/FragCityAPI` for the signed Bridge route; do not reintroduce localization code or edit Paymenter core.
3. Idempotent provisioning transaction and user/product/plan mapping.
4. Zarinpal server-purchase payment intent + verify + outbox/retry.
5. One test customer, one test product, one test CS2 server; verify environment, node, limits and lifecycle.
6. Website customer dashboard and administrator controls.
7. RCON/live controls and separate schema-driven CFG/JSON file settings, with backups, validation and explicit reload/restart semantics.

No live purchase, real payment or production server operation is enabled by this document.
