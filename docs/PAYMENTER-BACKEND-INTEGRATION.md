# FragCity ↔ Paymenter Backend Integration

**Status:** The signed FragCity account bridge now requires client first and last names. Purchase/provisioning operations remain pending verification.

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

Pterodactyl/Wings own the actual server record, node allocation, Docker container and execution lifecycle. FragCity's `My Servers` page is the customer-facing inventory and credential-management surface. For an owned service it can link the customer to that server's Pterodactyl panel page; panel identity and password changes are authorized through the signed FragCity → Paymenter → Pterodactyl backend path. Customers never receive an Application API key.

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

## 2.1. Registration and credential synchronization

- New FragCity identities are created only after a validated Steam OpenID callback. A browser-supplied SteamID64 by itself is not proof of account ownership.
- The Steam registration-completion form and the authenticated account-completion form collect mandatory client first name and last name, a valid email address, the required mobile number and a password confirmation.
- The FragCity database stores `username`, `first_name` and `last_name` separately. Username is mandatory, lowercased, 3–50 characters, starts and ends with an ASCII letter or digit, and allows only ASCII letters, digits, underscores and hyphens. Both client names are mandatory (1–191 characters after trimming).
- A normal password login synchronizes the verified password and current account details, including username and both mandatory client names, to Paymenter through the private HMAC bridge.
- An existing Steam/remember-me session has no plaintext password available. If its Paymenter link or required contact details are missing, the user is sent to the authenticated account-completion form to choose/confirm a password.
- The FragCity website stores only its password hash. The plaintext password exists only in request memory and in the short-lived HTTPS/HMAC server-to-server body to Paymenter; neither side writes it to logs or stores it in plaintext.
- Paymenter stores its own password hash and has no native username column. The bridge stores the selected FragCity username as the Paymenter User Property `fragcity_username`; `first_name`, `last_name`, `email` and `password` use Paymenter's native user fields.
- Before the official Paymenter Pterodactyl server provisioner runs, `FragCityCS2` reads `fragcity_username` and creates or updates the Pterodactyl client through the Application API. The Pterodactyl native `username`, `first_name`, `last_name` and `email` receive the corresponding FragCity values. It also sets Pterodactyl `external_id` to the Paymenter user ID, then delegates server creation to the inherited official implementation.
- Pterodactyl's Application API allows the password field to be omitted when creating a client user. The Pterodactyl panel password is intentionally independent from FragCity and Paymenter. Customers set or replace it in the FragCity `My Servers` page after re-entering their current FragCity password; the signed Bridge sends the new panel password to Paymenter, and the FragCityCS2 extension updates the Pterodactyl user through the Application API.
- The current panel password is never readable or returned: Pterodactyl stores only a password hash. FragCity and Paymenter persist no Pterodactyl plaintext password or password hash. Paymenter stores only a `fragcity_panel_password_set_at` timestamp marker. The page displays the panel URL, username, email, server UUID/identifier, status and resource details; it provides a set/change-password form rather than a recoverable password display.
- The signed customer bridge endpoints are `POST /api/v1/fragcity/bridge/servers/list` and `POST /api/v1/fragcity/bridge/servers/password`. Both resolve the SteamID64 through `fragcity_user_links`, and password changes are additionally scoped to a service owned by that linked Paymenter user.
- Email and mobile are required for account synchronization. The mobile number is stored in Paymenter as the `fragcity_phone` user property because Paymenter v1.5.9 does not provide a native phone field.


## 2.2. Browser SSO between FragCity and Paymenter

- Paymenter starts the flow at `GET /auth/fragcity/sso/start` and stores a random 256-bit state in its own browser session.
- The browser is redirected to the main site's `/api/paymenter-sso.php`. This endpoint accepts no user identity from the URL; it derives the SteamID64 and Paymenter user ID from the authenticated FragCity session and the existing `fragcity_user_links` mapping.
- If the customer is not signed in to FragCity, the endpoint preserves the SSO state in the server-side PHP session and sends the browser to the existing login page. Both the validated Steam OpenID callback and the normal username/password login resume the same local return path. Account completion and new Steam registration also resume it after the existing Paymenter account synchronization succeeds.
- A valid FragCity remember-me cookie may restore the existing FragCity session. That preference is forwarded to Paymenter; a normal non-remembered FragCity session becomes a normal Paymenter session.
- The site produces a 90-second HMAC-SHA256 ticket and auto-submits it to `POST /auth/fragcity/sso/callback` as a form POST. Ticket content includes only the issuer, audience, SteamID64, Paymenter user ID, issue/expiry time, one-time ID, Paymenter state and remember preference.
- Paymenter verifies the exact website Origin, its own session-bound state, the shared-secret signature, expiry, issuer/audience and the database link between the SteamID64 and Paymenter user ID. A unique hash of the one-time ticket ID prevents replay.
- Paymenter creates its session using its native login action. Its built-in two-factor challenge remains in force. An already-authenticated, different Paymenter user is never silently replaced.

The bridge reuses `integrations.paymenter_backend.base_url` and `integrations.paymenter_backend.shared_secret` from the private FragCity runtime settings. Do not add a second SSO secret, expose the HMAC secret in JavaScript, send the website PHP session ID to Paymenter, or pass passwords in the SSO ticket.

The Billing links in the FragCity desktop/mobile navigation, footer and VIP wallet recharge action use the SSO start route. Paymenter's own login form has a matching **Steam / FragCity account** button. The button supports both Steam login and the site's existing username/password login.

Paymenter v1.5.9 hard-codes Google, GitHub and Discord in **Settings → Social Login** and does not expose an extension hook for adding a provider there. To keep core untouched, the FragCity SSO enable switch and canonical website origin are configured under **Admin → Extensions → Others → FragCity API**. The provider button is rendered by the FragCity theme.

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
6. Website `My Servers` inventory, customer server cards and independent Pterodactyl panel-password management (initial implementation now added; production verification remains pending).
7. RCON/live controls and separate schema-driven CFG/JSON file settings, with backups, validation and explicit reload/restart semantics.

No live purchase, real payment or production server operation is enabled by this document.
