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

The same private HMAC client also provides:

- `POST /api/v1/fragcity/bridge/catalog/cs2` — returns the configured CS2 Product, duration Plans, Rial Plan prices, every attached Config Option and child value with the per-duration price/setup fee, plus Egg Checkout fields and GameModeManager metadata.
- `POST /api/v1/fragcity/bridge/wallet/balance` — accepts the SteamID64 from the authenticated server-side website session and returns only that linked Paymenter user's configured Rial credit balance.
- `POST /api/v1/fragcity/bridge/wallet/topups/zarinpal` — applies a ZarinPal deposit already verified by the FragCity PHP backend through a native Paymenter Credit invoice.

The browser never calls either endpoint. The PHP backend calls them through `PaymenterApiClient::getCs2Catalog()` and `PaymenterApiClient::getCustomerWalletBalance()`.

## Wallet deposit and wallet checkout

A verified ZarinPal deposit is submitted by
`PaymenterApiClient::createCustomerWalletTopup()`. The website sends a stable
32-character `topup_ref`, the authenticated SteamID64 and the server-verified
ZarinPal Authority, RefID and amount pair. Paymenter validates the amount
conversion and its Credits settings, then creates/settles a native Credit
invoice. Do not call this method from the browser or before ZarinPal Verify
succeeds.

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
