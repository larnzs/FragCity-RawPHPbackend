# FragCity Admin Lifecycle Dashboard & API

## Scope

The administrator interface lives at `index.php?page=lifecycle`. It is rendered only for an authenticated FragCity session whose role is `admin`. The dashboard uses Paymenter's durable identity/service registry and lifecycle event log. Paymenter Core and Pterodactyl Core remain unmodified.

The browser calls `/api/admin-lifecycle.php`. That endpoint checks the administrator session, POST method and `X-FragCity-CSRF`, then calls the Paymenter bridge over HTTPS using the HMAC timestamp/nonce/signature contract. HMAC secrets and Pterodactyl Application API credentials are never sent to browser code.

## Paymenter Bridge routes

All routes are POST and require the HMAC-SHA256 timestamp, nonce and signature headers:

- `/api/v1/fragcity/bridge/admin/lifecycle/list` — paginated mappings, state counts and search across service/server identifiers and customer fields.
- `/api/v1/fragcity/bridge/admin/lifecycle/detail` — customer identity, Paymenter service, Pterodactyl IDs, reconciliation timestamps, recovery authorization and the latest 50 events.
- `/api/v1/fragcity/bridge/admin/lifecycle/reconcile` — runs the dedicated lifecycle reconciliation engine. It verifies the Pterodactyl Client UUID and exact Server external ID. When ownership is verified, it aligns the Server suspend state to Paymenter's `active` or `suspended` intent. It never creates a Client/Server or deletes a Server.
- `/api/v1/fragcity/bridge/admin/lifecycle/authorize-recreation` — records a reasoned, one-shot grant for a service in `missing_external`. It does not dispatch CreateJob; an administrator must retry the exact same Paymenter service's CreateJob separately after reviewing backup/data-loss implications.
- `/api/v1/fragcity/bridge/admin/lifecycle/revoke-recreation` — revokes a still-pending grant with a mandatory reason. Consumed grants cannot be revoked.
- `/api/v1/fragcity/bridge/admin/lifecycle/acknowledge-paymenter-missing` — records an administrator's reasoned decision to preserve an orphaned external mapping. It never deletes or mutates a Pterodactyl resource.

## Reconciliation contract

Paymenter service status is the desired-state authority; Pterodactyl is the observed-state authority. Only an authoritative Pterodactyl 404 is treated as missing. Network failures, timeouts, 401/403 and 5xx responses remain retryable failures and are never converted to `missing_external`.

A manually deleted Server remains `missing_external`. Reconciliation can detect a Server that reappears with the same external ID and verified owner, but it never recreates missing infrastructure. A missing Client, UUID mismatch or ownership mismatch becomes `review_required`; reconciliation does not repair Client profile data. Suspend/unsuspend/delete operations verify Client ID + UUID with GET-only checks and never repair Client profile fields as a side effect. Legacy numeric Client `external_id` values are not automatically migrated to the customer UUID; they require explicit administrator identity review and mapping.

A directly deleted Paymenter service row is marked `paymenter_missing`. The dashboard now allows an administrator to acknowledge the review with a mandatory reason and the fixed `preserve_external` decision. This records the reviewer, timestamp, reason and audit event, while leaving the external Pterodactyl Server untouched. There is deliberately no remote-delete action in this acknowledgement flow.

The scheduled worker processes a bounded batch, records `last_reconciled_at`, retries transient failures, and writes lifecycle audit events. Paymenter service deletion through Eloquent remains synchronous: a remote delete failure blocks the service-row deletion. Direct SQL deletion bypasses model hooks and is marked `paymenter_missing` for review only; no remote deletion is inferred from that observation.

## Recovery authorization state

Authorization is independent from `desired_state` and has a unique authorization UUID, approver SteamID64/username, reason and timestamp. A grant remains pending across transient CreateJob failures and is consumed only after Pterodactyl confirms the replacement Server. A pending grant can be revoked, with actor and reason recorded as a separate event. Before provisioning starts, CreateJob must atomically claim the authorization with a two-hour lease. Revocation is rejected during an active claim; a failed CreateJob releases the claim, while a crashed process allows its lease to expire. The dashboard does not automatically execute CreateJob.

## Runtime prerequisites and checks

The Paymenter FragCityAPI extension is version 1.10.0. Its upgrade hook runs the additive migrations `2026_11_04_000003_add_lifecycle_reconciliation_and_recreation_authorization_columns.php`, `2026_11_04_000004_add_recreation_authorization_claim_columns.php` and `2026_11_04_000005_add_paymenter_missing_review_fields.php`; earlier migration files must not be edited or rerun manually.

Before any production rollout, verify the extension migration has applied, inspect the six lifecycle route names with `php artisan route:list`, and exercise active, suspended, drift, missing Client, missing Server, transient API failure, grant, grant-revoke, grant-consume and Paymenter-missing acknowledgement cases against a disposable test service. These are validation steps, not a deployment request.

## Trust boundary

The website backend is the administrator authorization boundary. The actor SteamID64/username forwarded to Paymenter are audit metadata, not an independent permission grant. Keep the shared secret in server-side runtime settings only.
