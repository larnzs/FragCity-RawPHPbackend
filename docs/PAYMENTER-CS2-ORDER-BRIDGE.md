# FragCity CS2 → Paymenter paid-order bridge

This document defines the website-side request contract for the private
`FragCityAPI` endpoint in the `larnzs/FragCity-Paymenter` repository.

## Trust boundary

The browser never calls Paymenter. The FragCity PHP backend owns the ZarinPal
merchant credentials and must verify the callback with ZarinPal's server-side
Verify API before calling `PaymenterApiClient::createCs2ServerOrder()`.

A browser redirect, query parameter, JavaScript event, or a callback field such
as `Status=OK` is not proof of payment. Only the verified receipt returned by
ZarinPal may populate the `payment` object below.

Paymenter authenticates the website request with the existing HMAC bridge
headers. Paymenter does not call ZarinPal itself; it trusts only the signed
FragCity backend after the website has completed verification.

## Read-only catalogue and wallet bridge

## Authoritative price quote and storefront checkout

The live storefront now uses a two-step contract before accepting payment:

1. `POST /api/server-store.php` with `{"action":"quote", ...selection}`. FragCity derives SteamID64 from the PHP session, checks CSRF and the synced Paymenter account link, then calls Paymenter's read-only `POST /api/v1/fragcity/bridge/quotes/cs2`. Paymenter resolves the exact configured Product, duration Plan and all eight native Config Option child rows; includes recurring prices, setup fees and the customer's `Settings::tax($user)`; and returns an exact Rial total plus whole-Toman equivalent. The browser never sends a price, currency, tax, Product ID, Plan ID or Config Option ID.
2. `POST /api/server-store.php` with `{"action":"checkout", ...selection, "expected_amount_rial": ...}`. The website obtains a fresh quote again and compares it with the amount the customer just reviewed. If it changed, checkout stops and the new quote must be reviewed. Paymenter then recalculates the same total again inside the order transaction and rejects a mismatch.

The public catalogue is also used to build RAM, SSD, CPU, Player Slots, Tickrate, Game Mode and Map Group selectors. This controls the UI only; Paymenter still performs the authoritative mapping and price validation on quote and order.

The website stores each checkout in `fragcity_cs2_checkout_orders` with a stable 32-character `checkout_ref`, selection hash, amount, ZarinPal Authority/RefID and Paymenter Order/Invoice/Service IDs. Exact retries reuse the same reference. A transport-unknown ZarinPal request or a verified payment waiting for Paymenter is deliberately locked instead of issuing a second payment.

Wallet checkout calls Paymenter with `payment.provider=wallet` and no debit amount. For ZarinPal, the website makes the request using the stored Rial amount, redirects to the provider, then `/pages/zarinpal/zarinpal_cs2_callback.php` verifies the Authority and exact amount through the provider's server-side Verify endpoint before creating the Paymenter order. The callback query parameter `Status` is not trusted as payment evidence and cannot mutate the order state by itself.

Before enabling this storefront in production:
- Apply `database/migrations/20261110_fragcity_cs2_checkout_orders.sql` to the same website database profile configured by `files.index.database_profile`.
- Deploy `FragCityAPI` **1.11.0** (with `POST /api/v1/fragcity/bridge/quotes/cs2`) before deploying this website branch.
- Ensure all eight Product Config Options are Upgradable and have price rows for every allowed child value/Plan/Rial currency. Free values must have explicit zero-price/zero-setup-fee rows.
- Keep Bot Guard unavailable until its surcharge is included in the quote. Do not enable renewals until recurring totals have been checked end-to-end.
## FragCity website API boundary

The customer-facing website uses `POST /api/server-store.php` only as its
same-origin PHP boundary:

- `GET /api/server-store.php?action=catalog` reads the Paymenter HMAC catalogue,
  caches the safe presentation payload briefly on the website host, and omits
  Paymenter internal Product / Plan / Config Option IDs from the browser response.
- `POST /api/server-store.php` with `{"action":"wallet_balance"}` requires an
  authenticated FragCity session and the session CSRF header. It derives
  SteamID64 from the session, requires the local Paymenter account link to be
  synced, and returns only that customer's Paymenter Wallet balance and limits.

Neither action exposes the HMAC secret. Browser code must never call
`billing.fragcity.ir` or a `/api/v1/fragcity/bridge/*` URL directly. The
catalogue is public display data; every submitted selection is independently
mapped and priced again by Paymenter when creating the paid CS2 order.

The same private HMAC client also provides:

- `POST /api/v1/fragcity/bridge/catalog/cs2` — returns the configured CS2 Product, duration Plans, Rial Plan prices, every attached Config Option and child value with the per-duration price/setup fee, plus Egg Checkout fields and GameModeManager metadata.
- `POST /api/v1/fragcity/bridge/wallet/balance` — accepts the SteamID64 from the authenticated server-side website session and returns only that linked Paymenter user's configured Rial credit balance, whether Paymenter Credits are enabled, and the configured deposit/balance limits.
- `POST /api/v1/fragcity/bridge/wallet/topups/prepare` — reserves the linked customer's maximum-credit capacity before the website creates a ZarinPal deposit request.
- `POST /api/v1/fragcity/bridge/wallet/topups/zarinpal` — applies a ZarinPal deposit already verified by the FragCity PHP backend through a native Paymenter Credit invoice.

The browser never calls either endpoint. The PHP backend calls them through `PaymenterApiClient::getCs2Catalog()` and `PaymenterApiClient::getCustomerWalletBalance()`.

## Wallet deposit and wallet checkout

Before creating a ZarinPal deposit request, the website backend must call
`PaymenterApiClient::prepareCustomerWalletTopup()` with a stable 32-character
`topup_ref`, the authenticated SteamID64 and the requested Toman/Rial amount.
Paymenter validates its native Credits settings and reserves the required
maximum-credit capacity for 20 minutes. Only after a `prepared` response may
FragCity create the external ZarinPal request. Keep the same `topup_ref` for
the callback and every retry; an exact retry returns the existing reservation.

After the FragCity backend verifies the ZarinPal callback with the provider's
server-side Verify API, it calls
`PaymenterApiClient::createCustomerWalletTopup()` using that same reference,
the authenticated SteamID64 and the verified Authority, RefID and amount pair.
Paymenter settles a native Credit invoice and its normal paid-invoice processor
increments the Wallet. Never call either method from browser JavaScript, and
never call the settlement method before ZarinPal Verify succeeds. A reservation
that expires may be marked expired; a genuinely verified receipt is still
reconciled by Paymenter rather than silently discarded.

CS2 orders can also use Paymenter Wallet:
`PaymenterApiClient::createCs2ServerOrder()` receives only
`payment.provider=wallet`; it must not send an amount or payment receipt.
Paymenter recomputes the Plan + selected Config Option price, locks the user's
Credit row, checks the actual balance, debits it in the same DB transaction as
Order/Service/Invoice creation, and records a successful credit Invoice
Transaction. The browser never chooses the debit amount.

Both ZarinPal server purchases and wallet top-ups use the shared
`fragcity_payment_receipts` ledger. It is backfilled from existing server
orders by the extension migration and prevents one Authority or RefID from
being reused across a server purchase and a wallet deposit.

## Endpoint

`POST /api/v1/fragcity/bridge/orders/cs2`

The existing `PaymenterApiClient` builds the JSON body and signs its exact raw
bytes with the configured shared secret. Do not re-encode or modify the body
between signing and sending it.

## Payload

```json
{
  "order_ref": "32-character-unique-hex-reference",
  "steamid64": "7656119XXXXXXXXXX",
  "payment": {
    "provider": "zarinpal",
    "authority": "ZarinPal authority from the request step",
    "ref_id": "verified ZarinPal RefID",
    "amount_toman": 749000,
    "amount_rial": 7490000
  },
  "duration_days": 30,
  "server_name": "FragCity Public",
  "resources": {
    "memory_mb": 1024,
    "disk_mb": 10240,
    "cpu_percent": 100
  },
  "feature_limits": {
    "databases": 0,
    "additional_allocations": 0,
    "backups": 0
  },
  "egg": {
    "SRCDS_MAP": "de_dust2",
    "SRCDS_MAXPLAYERS": "10",
    "TICKRATE": "64",
    "EXEC": "comp.cfg",
    "MAPGROUP": "mg_comp",
    "COOLDOWN": "0",
    "ENABLE_GUARD": "0",
    "GUARD_BAN_MINUTES": "60"
  }
}
```

The amounts are integer values. `amount_rial` must equal `amount_toman * 10`.
Allowed durations are 1, 7, 15 and 30 days. Resources are transmitted in the
same units consumed by Paymenter/Pterodactyl: memory and disk in MB, CPU as a
percentage. Paymenter independently recalculates the first invoice amount from
the selected Product Plan and each matching native Config Option Child price,
including setup fees and the applicable Paymenter customer tax policy. It
rejects the order unless this exact total equals the verified ZarinPal Rial
amount and converts exactly to the Toman amount. The payload carries eight Egg environment values: six Checkout fields plus `SRCDS_MAXPLAYERS` and `TICKRATE`; the latter two are native Config Option selections. The separate `feature_limits` object carries Databases, Additional Allocations and Backups as Pterodactyl provisioning limits, not Egg variables. All Config Option selections are resolved to native Paymenter child rows and persisted as `ServiceConfig`, not duplicated as service properties. The selected `EXEC` CFG and `MAPGROUP` must also be a compatible pair according to the GameModeManager manifest.

Generate `order_ref` once for the local FragCity purchase record and reuse it
for every callback/API retry. Do not generate a new order reference on retry.

## Success response

The response has `success: true` and a `data` object containing:
- `order_id`: Paymenter Order ID;
- `invoice_id`: paid Paymenter Invoice ID;
- `service_id`: Paymenter Service ID;
- `status: accepted`;
- `replayed`: true when the same order was already accepted;
- `provisioning: queued_by_paymenter`.

This response means Paymenter committed the native Order, Invoice, Service and
successful InvoiceTransaction. Marking the invoice paid triggers Paymenter's
own post-commit invoice processing and server CreateJob. It does not prove the
Pterodactyl server has finished installing; provisioning is asynchronous.

## Idempotency and errors

Paymenter enforces unique indexes on `order_ref`, ZarinPal Authority and
verified RefID. Exact retries return the existing IDs; conflicting payloads or
re-use of a payment receipt return HTTP 409. Keep the original purchase record
pending/reconcilable when the API returns a transport error or 5xx; retry using
the same `order_ref` and a fresh HMAC nonce.

## Current paid-option gate

Tickrate 64 and 128 are both free options. Paymenter accepts either value only
through the native Tickrate Config Option, which must have a zero-price child
row for every supported Plan and Rial currency. Bot Guard remains a paid
add-on; `ENABLE_GUARD=1` is still rejected until its surcharge is included in
the authoritative storefront quote.

Databases, Additional Allocations and Backups are also included/free for now. Every selected child must have a zero-price and zero-setup-fee row for the chosen Plan and Rial currency.

## Renewal caveat

The optional `feature_limits` object defaults to zero when omitted for backward compatibility. When supplied, the bridge persists its three selections as native `ServiceConfig` rows and FragCityCS2 maps them to Pterodactyl's `feature_limits` during initial provisioning.

The first invoice uses the verified FragCity price. The bridge now persists
RAM, SSD, CPU, Player Slots and Tickrate as native Paymenter ServiceConfig
selections and verifies each selected child against the exact chosen Plan and
Rial currency. Player Slots uses values 5–64; the five-slot child is the
included base capacity and larger values represent paid additional slots.
Both Tickrate children (64 and 128) must be zero-priced, including setup fees;
the bridge rejects a non-zero price or setup fee for these values.
Automatic renewal billing still needs an end-to-end comparison against the Raw
PHP quote before it is enabled.
