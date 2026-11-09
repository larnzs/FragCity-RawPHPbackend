# FragCity CS2 → Native Paymenter Checkout

Compatibility target: Paymenter **v1.5.9**. The old custom paid-order endpoint has been retired. FragCity no longer sends resource values, Egg variables, Plan IDs, prices, ZarinPal receipts or order payloads to a custom order-creation API.

## Ownership boundaries

**FragCity website owns:**
- FragCity/Steam authentication and user identity.
- Synchronizing the customer identity into Paymenter through the signed account-sync endpoint.
- Starting a short-lived one-time SSO hand-off for an authenticated customer.
- The customer-facing entry page and links to Paymenter.

**Paymenter owns:**
- Product, Plans and recurring Plan prices.
- Native Product Config Options and child values/prices.
- Customer Checkout validation and live quote.
- Cart, Order, Service, Service Properties and native Service Config rows.
- Invoice, selected Gateway, payment transaction and invoice settlement.
- Renewal/recurring price calculations and upgrades.
- Queueing the normal Pterodactyl server CreateJob.

**FragCityCS2 server extension owns:**
- The CS2 Egg manifest for the remaining game-specific Checkout/Product fields.
- Validating those game-specific Egg fields before provisioning.
- The FragCity-specific Pterodactyl client identity.
- Delegating server creation to Paymenter's inherited Pterodactyl extension.

## Native Product Config Options

Create and attach these Config Options in **Paymenter Admin → Configuration → Config Options**. FragCityAPI does not create them and does not map browser values to Config Option IDs.

| Config Option | Parent Environment Variable | Suggested Type | Child Environment Variables |
| --- | --- | --- | --- |
| RAM | `memory` | Select or Slider | `1024`, `1280`, `1536` MB |
| SSD Storage | `disk` | Select or Slider | `5120`, `6144`, `7168`, `8192`, `9216`, `10240` MB |
| CPU | `cpu` | Select or Slider | `100`, `200`, `300`, `400` percent |
| Player Slots | `SRCDS_MAXPLAYERS` | Slider | Child values `5` through `64` |
| Tickrate | `TICKRATE` | Select | `64`, `128` |

The parent Config Option's Environment Variable is the service property key. The selected child's Environment Variable is the exact value. Use the numeric value in the child field, not the UI label.

Paymenter's native Checkout calculates every selected child's price for the selected Plan and saves the selections as native Service Config records. Paymenter's `ExtensionHelper::getServiceProperties()` then merges them into the properties passed to the server extension. The inherited Pterodactyl extension consumes `memory`, `disk` and `cpu` as resource limits and passes `SRCDS_MAXPLAYERS` and `TICKRATE` to the Egg.

Set the base RAM and 5-slot choices to zero surcharge where included in the Plan. Set the extra RAM/slot prices on the corresponding child for every Plan. CPU and SSD can be zero-priced while no surcharge is intended. Any 128 Tick surcharge must also be configured on the Tickrate child in Paymenter; there is no separate FragCity price gate.

## Remaining FragCity-specific Checkout fields

The FragCityCS2 extension still defines these game-specific Checkout fields from its manifest: `SRCDS_MAP`, `EXEC`, `MAPGROUP`, `COOLDOWN`, `ENABLE_GUARD` and `GUARD_BAN_MINUTES`. They are validated and stored as service properties by Paymenter's native checkout mechanism. Player Slots and Tickrate are not part of this custom Checkout list.

## SSO hand-off

The website must authenticate the customer with FragCity first. The current login/registration flow synchronizes the customer's SteamID64 to a Paymenter User through the signed `users/sync` endpoint.

When the customer clicks the CS2 purchase CTA, the FragCity PHP backend sends the signed request:

```json
{
  "steamid64": "7656119XXXXXXXXXX"
}
```

to:

```text
POST /api/v1/fragcity/bridge/checkout-url
```

Paymenter resolves the SteamID64 to the linked User, verifies that the configured `cs2_product_id` is enabled and uses the `FragCityCS2` server extension, and returns a one-time checkout URL. The opaque token expires after two minutes and is consumed once by:

```text
GET /extensions/fragcity-api/checkout-login/{token}
```

Paymenter creates the authenticated web session and redirects the customer to its native Product Checkout. FragCity never submits the actual Cart, Plan, Config Option, price, Order or Invoice data.

The website validates that the returned checkout URL is HTTPS and belongs to the configured Paymenter host before redirecting the browser. Do not log or reuse the one-time token.

## ZarinPal Gateway

ZarinPal is integrated as a Paymenter Gateway extension, not as a FragCity order callback. The gateway:
1. creates a ZarinPal payment request for the current Paymenter Invoice;
2. keeps the invoice ID, amount, currency and authority bound to an opaque one-time callback token in Laravel Cache;
3. verifies the authority server-to-server with ZarinPal;
4. records the successful transaction through Paymenter's `ExtensionHelper::addPayment()`.

Paymenter then settles the Invoice and triggers its normal server provisioning lifecycle. Toman amounts are converted to Rial exactly once by the gateway when requesting/verifying payment. The gateway must be configured with the exact Paymenter currency code and its stored unit.

## Important operational notes

- Keep the Paymenter HMAC secret server-side in FragCity's private runtime `settings/settings.json`; never expose it to browser JavaScript.
- Use a persistent shared Laravel Cache backend in Paymenter so short-lived SSO tokens and ZarinPal callback state are available across application web nodes.
- The configured `cs2_product_id` must be the same Product that has the native Config Options and the `FragCityCS2` server extension assigned.
- Configure every Config Option child price for all recurring CS2 Plans. Otherwise the relevant choice may be unavailable or incorrectly priced in renewal/upgrade calculations.
- The CS2 store page is now an entry point. Its local preview values are not sent to Paymenter; the actual plan, resources, slots, tickrate and total are selected in the native Paymenter Checkout.
- The old `POST /api/v1/fragcity/bridge/orders/cs2` endpoint and `PaymenterApiClient::createCs2ServerOrder()` method are removed from the new branch.

