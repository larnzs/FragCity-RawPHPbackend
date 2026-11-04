# FragCity Website: Paymenter Identity & Service Lifecycle

This document covers the website-side integration for the lifecycle and durable identity release. Paymenter and Pterodactyl Core are not modified.

## Integration contract

The website communicates with the Paymenter FragCityAPI bridge through `PaymenterApiClient` over HTTPS using the existing HMAC-signed server-to-server request flow. The shared secret remains in server-side runtime settings and is never exposed to browser JavaScript.

The account synchronization response now includes:

- `user_id`: current Paymenter user ID (mutable platform reference).
- `customer_uuid`: durable FragCity customer identity (UUID).
- `created`: whether a Paymenter user was created.
- `email`: normalized Paymenter email.

The website validates the UUID format before considering account synchronization successful. The remote bridge remains the source of truth for the UUID; the website does not generate a second identity.

## Multiple servers

A SteamID64/customer has one durable customer UUID and one Pterodactyl Client identity. Each Paymenter service is returned as its own server record. A customer with multiple CS2 services sees multiple cards, each identified by its Paymenter service ID and mapped to a distinct Pterodactyl Server.

## My Servers lifecycle presentation

The page interprets the bridge's lifecycle state and service status separately:

- `ready` plus active/unsuspended: normal management access.
- suspended service or suspended Pterodactyl Server: Console/File and other management links are disabled until reactivation.
- `provisioning`: show preparation state.
- `missing_external`: show that Pterodactyl no longer has the Server; do not automatically create another one.
- `paymenter_missing`: show a support/admin-review warning for an orphaned mapping.
- other unavailable/review states: hide management controls and show a safe unavailable state.

A Pterodactyl 404 is not silently converted into provisioning. A timeout, 401/403 or 5xx is an operational failure and remains unavailable/retryable.

The page never deletes, recreates, suspends or unsuspends a server directly. Those actions remain in the Paymenter extension/API lifecycle layer.

## Account deletion and recovery

The Paymenter bridge retains the SteamID-to-customer UUID record when a Paymenter user is deleted. A subsequent authenticated website sync for that same SteamID can securely attach a replacement Paymenter user to the existing UUID. If the old email is already attached to another Paymenter account, the existing password and Steam link ownership checks still apply; the bridge does not claim accounts by email alone.

## Deployment order

1. Deploy and upgrade the Paymenter FragCityAPI / FragCityCS2 extensions first, so the bridge schema and `customer_uuid` response exist.
2. Deploy the website `main` branch second.
3. Run the normal authenticated website account-sync path and verify the returned identity. Customers with pre-existing mismatched Pterodactyl Client IDs require administrator verification of the Client mapping before provisioning can continue.
4. Verify the My Servers page for active, suspended, missing and multiple-service cases.

Do not deploy the website client before the bridge release it depends on: the updated client intentionally rejects an account-sync response that does not contain a valid `customer_uuid`.
