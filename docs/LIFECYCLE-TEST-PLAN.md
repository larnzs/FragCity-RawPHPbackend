# FragCity Lifecycle Integration Test Plan

This matrix is for a disposable Paymenter/Pterodactyl test service only. Do not run destructive cases against a production customer service. The static preflight does not replace these live integration tests.

## Preconditions

- Use a disposable customer identity with a known customer_uuid.
- Use one test service and record its Paymenter service ID, Pterodactyl Client ID and Pterodactyl Server ID.
- Confirm the FragCity lifecycle migrations through 000004 are applied in the test database.
- Keep Pterodactyl Application API credentials server-side.
- Capture the lifecycle mapping row and its event history before and after each case.

## Required cases

| Case | Setup / action | Expected result |
|---|---|---|
| First provisioning | A never-provisioned Paymenter service has no Client/Server yet. Run the normal Paymenter CreateJob. | One customer Client and one service Server; mapping moves from provisioning to ready. |
| Existing Server retry | Simulate loss of the CreateJob response after Pterodactyl has created the exact Server, then retry. | Existing Server is found by exact service external ID and verified owner; no duplicate is created. |
| Active drift | Paymenter service active, verified Pterodactyl Server suspended. Run manual reconcile. | The exact Server is unsuspended, re-read and recorded as ready only after verification. |
| Suspended drift | Paymenter service suspended, verified Pterodactyl Server active. Run manual reconcile. | The exact Server is suspended, re-read and recorded as suspended only after verification. |
| Server manually deleted | Delete the disposable Server directly in Pterodactyl, then reconcile and allow scheduler to run. | State is missing_external; no automatic Server or Client creation. |
| Missing Client | Delete the disposable Client while its service mapping records the prior Client ID. Reconcile and retry CreateJob. | State becomes review_required; CreateJob does not create a replacement Client or orphan account. |
| Client UUID conflict | Change the disposable Client external_id to a different value. Reconcile. | review_required; no Server mutation or Client profile repair. |
| Legacy numeric Client external_id | Use a disposable Client whose external_id is the old numeric Paymenter user ID, without an approved identity registry mapping. Attempt provisioning/lifecycle mutation. | Operation is refused; no automatic external_id rewrite. After an administrator explicitly records the verified mapping, retry the test. |
| Server owner conflict | Change test data so the service external ID resolves to a Server with a different owner. | review_required; no suspend/unsuspend/delete operation. |
| Transient Pterodactyl failure | Cause a controlled 5xx/timeout or use a test proxy to return one. Reconcile. | Keep prior observed state, record failure/retry event; never classify as missing_external from a transient error. |
| Authoritative 404 | Request a non-existent exact Client/Server ID. | Only HTTP 404 is treated as missing; resource-specific policy determines review_required or missing_external. |
| Direct SQL Paymenter deletion | Remove only the disposable service row outside Eloquent, then run scheduled reconciliation. | paymenter_missing with admin review event; no external Server deletion. |
| Eloquent service deletion | Delete the disposable Paymenter service through the supported model/UI path. | Only its mapped Pterodactyl Server is terminated; a remote failure blocks service deletion; a remote 404 is idempotent. |
| Recovery grant | With a previously provisioned test service in missing_external, approve with a reason. | Separate authorization UUID/actor/reason/timestamp stored; desired_state remains active or suspended; no CreateJob is dispatched. |
| Recovery grant revoke | Revoke an unclaimed pending grant with a reason. | Grant is revoked and audited; CreateJob cannot use it. |
| Claim vs revoke race | Start a retry so it claims the grant, then try to revoke it. | Revoke returns conflict while the two-hour claim is active. |
| Retry failure | Make CreateJob fail after claim but before a verified replacement Server exists. | Claim is released; grant remains pending; failure event recorded. |
| Process crash | Simulate worker termination after claim without cleanup. | Claim blocks revoke/retry until its two-hour lease expires; then a new retry or revocation can proceed. |
| Successful recovery | Retry the same Paymenter service CreateJob with a pending grant. | Exact replacement Server is verified; authorization is consumed once and event records the new Server ID. |
| Multi-service customer | Create at least two test services owned by one customer; delete/recover only one. | Only that service's Server/mapping is changed; the other service and shared Client identity remain intact. |

## Static preflight

From the FragCity website repository root:

    php tools/lifecycle-preflight.php --paymenter-root="/path/to/FragCity-Paymenter"

The script checks PHP parseability, duplicate named functions, API/route wiring, migration fields and safety invariants. It does not access a live server, execute migrations or make network requests.

## Release gate

Do not treat the feature as production-ready until the static preflight passes and all applicable integration cases above pass in a disposable environment. No production migration, cache clear, extension upgrade or deployment is part of this test plan.
