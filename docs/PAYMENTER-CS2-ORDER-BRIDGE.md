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
percentage. The payload carries eight Egg environment values: six Checkout fields plus `SRCDS_MAXPLAYERS` and `TICKRATE`, which are native Config Option selections. All values are exact Egg values, not UI labels. The two Config Option selections are resolved to native Paymenter child rows and persisted as `ServiceConfig`; they are not duplicated as service properties. The selected `EXEC` CFG and `MAPGROUP` must also be a compatible pair according to the GameModeManager manifest.

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

## Renewal caveat

The first invoice uses the verified FragCity price. The bridge now persists
RAM, SSD, CPU, Player Slots and Tickrate as native Paymenter ServiceConfig
selections and verifies that every selected child has a price for the chosen
Plan and Rial currency. Player Slots uses values 5–64; the five-slot child is
the included base capacity and larger values represent paid additional slots.
Tickrate is limited to 64 for now; 128 remains rejected until its surcharge is
included in the authoritative FragCity quote and the Egg startup behavior is
verified. Automatic renewal billing still needs an end-to-end comparison
against the Raw PHP quote before it is enabled.
