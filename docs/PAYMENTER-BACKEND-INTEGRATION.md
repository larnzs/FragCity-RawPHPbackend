# FragCity ↔ Paymenter Backend Integration

**Status:** Native Paymenter Checkout and the private account/server bridge are implemented on the feature branch. Production deployment and an end-to-end sandbox purchase still require verification.

## 1. Product boundary

FragCity owns the customer identity and website experience:
- Steam OpenID authentication and FragCity account data.
- Account synchronization to Paymenter through a private signed bridge.
- Customer-facing server inventory, panel identity and server controls.
- The storefront entry page that sends an authenticated customer to Paymenter's native CS2 Product Checkout.

Paymenter owns all commerce and provisioning:
- Product catalogue, Plans, recurring prices and native Config Options.
- Checkout, Cart validation and pricing.
- Order, Service, native Service Config, Invoice and Invoice Transaction records.
- Payment Gateway handling, invoice settlement, recurring renewal and upgrades.
- Queueing its normal Pterodactyl server creation job.

The FragCity CS2 server extension owns game-specific Egg fields, client identity synchronization and the hand-off to Paymenter's inherited Pterodactyl provisioner. Pterodactyl/Wings own the actual server, node allocation, Docker container and runtime.

## 2. Source-of-truth boundaries

| Data | Authoritative owner |
|---|---|
| FragCity login identity and Steam account | FragCity website |
| Paymenter user link for each SteamID64 | FragCityAPI bridge |
| Product, Plans, billing periods and base prices | Paymenter |
| RAM, SSD, CPU, Player Slots and Tickrate options and child prices | Native Paymenter Product Config Options |
| Remaining CS2-specific fields such as map, CFG, MAPGROUP and cooldown | FragCityCS2 extension Checkout manifest |
| ZarinPal authority and verified receipt | ZarinPal Gateway extension in Paymenter |
| Order, Service, Invoice and Service Config records | Paymenter |
| Pterodactyl server UUID, node, allocations and container lifecycle | Pterodactyl |
| CS2 plugin configuration and game-mode settings | Private files of each game instance, managed through FragCity backend |

Do not recreate retired Windows Agent tables or use them as the new provisioning source. Do not introduce direct SQL writes to Paymenter Core tables.

## 2.1. Registration and credential synchronization

- New FragCity identities are created only after a validated Steam OpenID callback. A browser-supplied SteamID64 by itself is not proof of account ownership.
- The Steam registration-completion form and authenticated account-completion form collect mandatory client first name and last name, a valid email address, the required mobile number and password confirmation.
- The normal password login synchronizes the verified account details to Paymenter through the private HMAC bridge.
- Paymenter has no native username column. The bridge stores the FragCity username as the Paymenter User Property `fragcity_username`; first name, last name, email and password use Paymenter's native user fields.
- Before the official server provisioner runs, `FragCityCS2` reads `fragcity_username` and creates or updates the Pterodactyl client using its native Application API. It also sets Pterodactyl `external_id` to the Paymenter user ID.
- Panel passwords are independent from FragCity and Paymenter passwords. Customers set or replace the panel password from FragCity's My Servers page; the signed bridge authorizes the operation against the linked Paymenter user and owned service.

## 3. Native Paymenter Config Options

Create these options using **Paymenter Admin → Configuration → Config Options**, then attach them to the CS2 Product. FragCityAPI must not create the Config Option rows, resolve their IDs or convert their values.

| Config Option | Parent Environment Variable | Suggested Type | Child Environment Variables |
| --- | --- | --- | --- |
| RAM | `memory` | Select or Slider | `1024`, `1280`, `1536` MB |
| SSD Storage | `disk` | Select or Slider | `5120`, `6144`, `7168`, `8192`, `9216`, `10240` MB |
| CPU | `cpu` | Select or Slider | `100`, `200`, `300`, `400` percent |
| Player Slots | `SRCDS_MAXPLAYERS` | Slider | Exact child values from `5` through `64` |
| Tickrate | `TICKRATE` | Select | `64`, `128` |

The parent Config Option's Environment Variable is the service property key. The selected child Environment Variable is the exact value. Set all child prices on every recurring Plan. Base RAM and 5 slots should be zero-priced where included in the base plan; add RAM and slot surcharges to the correct child values. CPU/SSD may be zero-priced while they have no surcharge. Any 128 Tick surcharge belongs on the 128 Tick child price.

Paymenter's own Cart/Checkout validates selections, adds their child prices to the selected Plan, and persists the selection as a native Service Config. Paymenter's `ExtensionHelper::getServiceProperties()` then exposes those Config Values to the server extension. The inherited Pterodactyl extension consumes `memory`, `disk` and `cpu` as resource limits and passes `SRCDS_MAXPLAYERS` and `TICKRATE` to the Egg.

The FragCityCS2 extension still supplies the remaining CS2-specific Checkout fields: `SRCDS_MAP`, `EXEC`, `MAPGROUP`, `COOLDOWN`, `ENABLE_GUARD` and `GUARD_BAN_MINUTES`. Player Slots and Tickrate are intentionally excluded from its custom `checkout_fields` manifest.

## 4. Customer checkout and payment flow

1. The customer logs into FragCity. The registration/login flow synchronizes the user's identity and SteamID64 with Paymenter.
2. The customer opens the FragCity CS2 storefront and submits the checkout form.
3. FragCity PHP sends a signed server-to-server request containing only the SteamID64 to `POST /api/v1/fragcity/bridge/checkout-url`.
4. Paymenter confirms the SteamID64 link and configured CS2 Product, then returns a one-time SSO URL. The token expires after two minutes and is consumed once.
5. Paymenter establishes the customer's native web session and redirects to that Product's `products.checkout` route.
6. Paymenter displays its native Plans and Config Options, validates the selected values and calculates the current invoice total. FragCity's local storefront preview values are not sent to Checkout.
7. Paymenter's native Cart creates the Order, Service, Service Properties, Service Config records and Invoice.
8. The customer selects the ZarinPal Gateway. The gateway requests a ZarinPal authority for that exact invoice and redirects the customer to ZarinPal.
9. The Paymenter Gateway callback verifies the authority server-to-server with ZarinPal. Only after a successful verification does it call Paymenter's supported `ExtensionHelper::addPayment()`.
10. Paymenter's invoice observer settles the invoice and queues the normal server CreateJob. `FragCityCS2` validates its game-specific values and delegates provisioning to the inherited Pterodactyl extension.

No browser request creates Paymenter Orders or Services. FragCity no longer submits prices, Plan IDs, resource values or Egg values to a custom order API.

## 5. Paymenter extensions

### FragCityAPI

The bridge supports:
- `POST /api/v1/fragcity/bridge/ping`
- `POST /api/v1/fragcity/bridge/users/sync`
- `POST /api/v1/fragcity/bridge/checkout-url`
- `GET /extensions/fragcity-api/checkout-login/{token}` (one-time SSO token)
- `POST /api/v1/fragcity/bridge/servers/list`
- `POST /api/v1/fragcity/bridge/servers/password`
- `POST /api/v1/fragcity/bridge/servers/username`

All POST bridge endpoints require HMAC-SHA256, a timestamp within five minutes, a unique nonce and HTTPS. The SSO GET route requires a random, single-use token generated only by the signed POST endpoint.

Configure:
- `shared_secret`: same private value as FragCity's `integrations.paymenter_backend.shared_secret`.
- `allowed_ips`: optional exact source IP allowlist.
- `cs2_product_id`: the enabled Paymenter Product using the `FragCityCS2` server extension.

There are no Product Plan ID or currency settings in FragCityAPI. There is no custom order creation endpoint.

### FragCityCS2

This extension is assigned to the CS2 Product's Server Extension setting. It provides the remaining game-specific Egg Checkout/Product settings, aligns the Pterodactyl client identity and delegates server lifecycle operations to Paymenter's inherited Pterodactyl implementation.

### ZarinPal Gateway

Enable **ZarinPal Gateway** under **Paymenter Admin → Extensions → Gateways**. Configure the Merchant ID, exact Paymenter Currency Code, unit (Toman or Rial) and Sandbox mode. Toman is converted to Rial exactly once by the gateway. The gateway verifies each invoice payment before recording it in Paymenter.

## 6. Deployment and verification

1. Install the updated `FragCityAPI`, `FragCityCS2` and `ZarinPal` extension folders into the matching Paymenter paths.
2. Run `composer dump-autoload` and `php artisan optimize:clear` in the Paymenter installation.
3. Enable FragCityAPI under **Admin → Extensions → Others** and configure the HMAC secret, allowlist and CS2 Product ID.
4. Enable FragCityCS2 under **Admin → Extensions → Servers** and configure its inherited Pterodactyl host/API settings.
5. Create the five native Product Config Options listed above and attach them to the CS2 Product. Configure every child price on all four CS2 Plans.
6. Confirm the remaining game-specific fields from the FragCityCS2 manifest appear in native Product Checkout.
7. Enable ZarinPal Gateway and configure its Merchant ID, Paymenter currency code/unit and sandbox/production mode.
8. From a test FragCity account, confirm the SSO redirect signs the user into Paymenter and opens the correct CS2 Product Checkout.
9. Complete a ZarinPal sandbox purchase. Verify Paymenter creates the expected Order, Invoice, Service, Service Config values, limits and Egg environment, then confirm Pterodactyl finishes creating the server.
10. Test renewal and Config Option upgrades, including a capacity change and RAM change. Verify Paymenter's recurring price calculation and the Pterodactyl limit/update behavior.

Use a persistent shared Laravel Cache backend in production: both short-lived SSO tokens and ZarinPal callback state are stored in Cache and must be available across Paymenter web nodes.

The old `POST /api/v1/fragcity/bridge/orders/cs2` endpoint and `PaymenterApiClient::createCs2ServerOrder()` method are removed on the native-checkout feature branch. Historical database migration files/tables are intentionally retained for compatibility and audit history; they are no longer written by the active checkout flow.
