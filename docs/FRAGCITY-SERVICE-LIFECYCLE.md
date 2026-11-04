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
- `paymenter_missing`: show an orphaned-mapping warning. An administrator may record a reasoned `preserve_external` review acknowledgement; this is audit-only and does not delete or mutate Pterodactyl resources.
- other unavailable/review states: hide management controls and show a safe unavailable state.

A Pterodactyl 404 is not silently converted into provisioning. A timeout, 401/403 or 5xx is an operational failure and remains unavailable/retryable.

The Paymenter user ID recorded on a service mapping is an ownership invariant, not a mutable lookup hint. If the current Paymenter service owner differs from the recorded owner, the lifecycle enters `review_required`; no Client/Server mutation, deletion, or automatic reassignment is permitted until an administrator resolves the identity conflict.

The page never deletes, recreates, suspends or unsuspends a server directly. Those actions remain in the Paymenter extension/API lifecycle layer.

The customer inventory read is also read-only with respect to Pterodactyl Client accounts. It verifies the pre-approved Client ID and durable customer UUID with GET requests only. It does not search by email, create a Client, or synchronize Client profile fields. Missing or conflicting identity mappings fail closed and require administrator review. The local lifecycle registry may still record observed server state and reconciliation timestamps.

Legacy or missing Pterodactyl Client mappings can be resolved only through the administrator Client identity verification flow. The administrator selects an existing numeric Client ID and provides a reason. Paymenter must exactly match the Client email and confirms that neither the UUID nor Client ID belongs to another customer. Only an empty or numeric legacy `external_id` may be replaced with the durable customer UUID; arbitrary values and another UUID are rejected. The flow never creates a Client or changes its password/profile. After the mapping is recorded, the administrator must run Reconcile to verify the Server owner and desired suspend state.

## Account deletion and recovery

The Paymenter bridge retains the SteamID-to-customer UUID record when a Paymenter user is deleted. A subsequent authenticated website sync for that same SteamID can securely attach a replacement Paymenter user to the existing UUID. If the old email is already attached to another Paymenter account, the existing password and Steam link ownership checks still apply; the bridge does not claim accounts by email alone.

## Deployment order

1. Deploy and upgrade the Paymenter FragCityAPI / FragCityCS2 extensions first, so the bridge schema and `customer_uuid` response exist.
2. Deploy the website `main` branch second.
3. Run the normal authenticated website account-sync path and verify the returned identity. Customers with pre-existing mismatched Pterodactyl Client IDs require administrator verification of the Client mapping before provisioning can continue.
4. Verify the My Servers page for active, suspended, missing and multiple-service cases.

Do not deploy the website client before the bridge release it depends on: the updated client intentionally rejects an account-sync response that does not contain a valid `customer_uuid`.


## Lifecycle reconciliation and administrative recovery

Paymenter runs a bounded reconciliation worker and exposes the same operation to administrators. Paymenter service status is the desired-state authority; Pterodactyl Server state is the observed-state authority. Reconciliation verifies the stored Pterodactyl Client ID and its durable customer UUID, then verifies the Server's Paymenter service external ID and Client owner before any suspend/unsuspend operation.

Reconciliation can align an already verified Server's suspended state with Paymenter's active/suspended status. It does not create missing Clients or Servers, repair Client profile fields, or delete remote resources. Suspend/unsuspend/delete lifecycle mutations verify the stored Client ID and UUID through read-only Application API checks. Legacy numeric Client external IDs are not rewritten automatically; they require an explicit administrator-approved identity mapping. A missing Client, UUID mismatch or ownership mismatch becomes `review_required`. Only an authoritative Pterodactyl 404 is treated as missing; timeouts, authentication failures and 5xx responses remain retryable errors.

The registry tracks `last_reconciled_at`. Failed scheduled or administrator-triggered checks are retried and generate audit events. Direct SQL deletion of a Paymenter service is treated as `paymenter_missing` for review only. The admin dashboard can record a one-shot `preserve_external` acknowledgement with actor, timestamp and reason. This does not delete or mutate a Pterodactyl Server and is never treated as implicit permission to clean up the external resource.

A missing external Server can be granted a one-shot recreation authorization by an administrator. The grant has an independent UUID, actor, reason and timestamp; it remains pending across failed CreateJob retries and is consumed only after a replacement Server is confirmed. A still-pending grant can be revoked with a reason. Before the same service's CreateJob begins, it atomically claims the authorization with a two-hour lease. Revocation is rejected while that lease is active; failed attempts release the claim, and a crashed worker permits retry after lease expiration. The website dashboard does not run CreateJob automatically.
