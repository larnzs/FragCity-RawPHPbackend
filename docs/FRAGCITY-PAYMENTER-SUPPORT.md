# FragCity Website — Paymenter Support Integration

This integration uses the existing FragCity /support page as the customer UI and Paymenter as the only ticketing system of record. Staff continue to answer and manage tickets inside the Paymenter admin panel. FragCity has no staff-ticket interface and does not store duplicate ticket/message rows.

## Responsibility split

### FragCity website

- Authenticates the customer through the existing FragCity session.
- Reads SteamID64 from the authenticated server-side session.
- Resolves the linked Paymenter user ID through the dedicated HMAC identity endpoint.
- Calls Paymenter's native Admin API from PHP.
- Shows the customer's own tickets, messages and read-only invoice information.
- Sends customer messages and permits the customer to close/reopen their own ticket.

### Paymenter

- Owns tickets, messages, departments, priority and status.
- Owns the staff response workflow and admin ticket interface.
- Owns invoices and invoice items.
- Remains the only persistent source of ticket and invoice records.

### FragCitySupportAPI extension

The Paymenter extension contains only two endpoints:

- POST /api/v1/fragcity/support/ping
- POST /api/v1/fragcity/support/identity/resolve

It reads the existing fragcity_user_links mapping created by the existing FragCityAPI extension. It does not create ticket, message, invoice, attachment or accounting records.

## Paymenter API Key

Create a separate Admin API Key, for example **FragCity Support Website**, with only:

| Permission | Required |
|---|---|
| View Tickets | Yes |
| Create Tickets | Yes |
| Update Tickets | Yes |
| View Ticket Messages | Yes |
| Create Ticket Messages | Yes |
| View Invoices | Yes |
| View Invoice Items | Yes |

Do not enable Delete, Impersonate, Create/Update Users, Invoice Create/Update, Invoice Item Create/Update, Orders, Services, Coupons or Credits. View Users is not required for the SteamID64 mapping.

The API key is administrative, not customer-scoped. Never expose it to the browser. The server-side adapter always resolves the Paymenter ID from the session's SteamID64 and verifies ownership using Paymenter's filter[id] and filter[user_id] before reading or mutating a specific ticket/invoice.

## Private settings

Copy the example settings structure into the private runtime file settings/settings.json. Do not place real credentials in Git.

~~~json
{
  "integrations": {
    "paymenter_support": {
      "base_url": "https://billing.fragcity.ir",
      "admin_api_key": "PASTE_PAYMENTER_ADMIN_API_KEY_HERE",
      "shared_secret": "PASTE_SUPPORT_EXTENSION_HMAC_SECRET_HERE"
    }
  }
}
~~~

Generate the HMAC secret with:

~~~bash
openssl rand -hex 32
~~~

Configure the same secret in Paymenter Admin → Extensions → Others → FragCity Support API. This secret must be different from integrations.paymenter_backend.shared_secret, which belongs to the existing server bridge.

base_url must be the HTTPS origin only. Do not append /api/v1/admin or any other path.

## Endpoints used by the website

### Custom identity bridge

The website backend sends a signed request to:

~~~http
POST /api/v1/fragcity/support/identity/resolve
Content-Type: application/json
X-FragCity-Timestamp: ...
X-FragCity-Nonce: ...
X-FragCity-Signature: ...
~~~

~~~json
{
  "steamid64": "7656119XXXXXXXXXX"
}
~~~

The SteamID64 comes from the existing authenticated FragCity PHP session. Browser-supplied steamid64 or paymenter_user_id is not trusted.

### Native Paymenter Admin API

Base path:

~~~text
https://billing.fragcity.ir/api/v1/admin
~~~

The PHP adapter uses:

- GET /tickets?filter[user_id]=...&sort=-updated_at&per_page=20
- GET /tickets?filter[id]=...&filter[user_id]=...&include=messages&per_page=1
- POST /tickets
- POST /ticket-messages
- PATCH /tickets/{id} with status=open|closed
- GET /invoices?filter[user_id]=...&include=items
- GET /invoices?filter[id]=...&filter[user_id]=...&include=items

Ticket creation and first-message creation are two Paymenter API calls. If the ticket is created but the first message request fails, the FragCity endpoint returns ticket_created_message_failed together with that new ticket ID. The frontend opens the existing ticket so the customer can send the message again; it must not create a duplicate ticket on retry.

### Native API limitations

Paymenter v1.5.9 Admin API does not provide an invoice transaction resource, an explicit remaining balance, or computed invoice total in the Invoice API resource. The current support integration therefore displays invoice status/due date/items only; it does not invent a payable balance or use Paymenter Credits.

Paymenter Admin API does not expose a Ticket Message upload endpoint. The current customer support interface is text-only. No custom attachment route is added.

## Customer website API

POST /api/support.php is the FragCity backend endpoint used by the existing /support/?tab=tickets page.

Supported actions:

- list_tickets
- show_ticket
- create_ticket
- reply_ticket
- close_ticket
- reopen_ticket
- list_invoices
- show_invoice

Every request requires an authenticated FragCity session and X-FragCity-CSRF. The SteamID64 is read from session on the server. Paymenter IDs and API keys are never accepted from or returned to the browser.

## Read-only preflight

Run from the Raw PHP repository root after configuring private settings:

~~~bash
php tools/paymenter-support-preflight.php
~~~

Optionally verify a specific account mapping:

~~~bash
php tools/paymenter-support-preflight.php 7656119XXXXXXXXXX
~~~

The preflight verifies:

- Signed HMAC ping and Support API version.
- Paymenter Admin API read access for tickets.
- Paymenter Admin API read access for ticket messages.
- Paymenter Admin API read access for invoices and invoice items.
- Optional SteamID64-to-Paymenter-user mapping.

This preflight is read-only. It does not create tickets/messages or modify accounting. Create and update permissions are exercised when a real test ticket is created/replied to/closed/reopened.

## Deployment notes

- Keep existing FragCityAPI installed; it owns the user-link mapping and current server bridge.
- Keep FragCityCS2 v1.4.0 untouched.
- Do not create or use local FragCity tickets / ticket_replies records for new support traffic.
- Existing historical local tickets are not migrated automatically; they remain in the old tables and should not be shown as Paymenter tickets.
