# FragCity — Testing Bench: CS2 Multi-Instance

> Last reviewed: 2026-10-16  
> Overall status: PLANNED — no candidate in this document has passed a live test on the FragCity lab.  
> Purpose: one controlled test programme for Linux, Windows, cross-platform managers, and shared CS2 storage.

## 1. Rules of the bench

1. **Every candidate stays Pending until the relevant tests have evidence.** Reading its README or seeing a successful build is not a live test.
2. Use a disposable VM, snapshot, or isolated test directory per candidate. Do not install competing lifecycle managers over the same live instance.
3. Never test against production website DB, payment credentials, customer data, or production game server directories.
4. Keep Steam credentials, GSLT, RCON secrets, API tokens, and logs containing secrets out of GitHub and screen captures.
5. Establish a working one-instance baseline, then validate two instances on the selected shared Base with private writable paths. A full independent-copy setup is a fallback/comparison only, not FragCity's current target architecture.
6. A test can be technically successful and still be rejected for security, licence, maintenance, or commercial-use reasons.
7. Do not touch `larnzs/cs2-kus-addons` as part of this document/project unless explicitly requested in a separate task. It may be used as a comparison reference only, with its own controlled test plan.
8. An “OK” result means that the named scenario passed on the exact OS/build/commit recorded in the test record; it does not automatically approve production use.

## 2. Status vocabulary

| Status | Meaning |
|---|---|
| **NOT REVIEWED** | Candidate identified, source/requirements not yet inspected. |
| **STATIC REVIEW** | Read README, code, scripts, licence and dependencies; no live execution yet. |
| **READY FOR LAB** | Static and licence/security gates allow a controlled test. |
| **IN TEST** | Test run started; evidence and result still being collected. |
| **PASS — LAB ONLY** | Tests in the stated scope passed; not a production approval. |
| **FAIL** | One or more acceptance criteria failed; preserve logs and reproduction steps. |
| **BLOCKED** | Cannot proceed until a licence, dependency, access, or infrastructure blocker is resolved. |
| **DEFERRED** | Lower priority or redundant candidate; reason must be written down. |
| **REJECTED** | Not suitable for the stated use, with a recorded reason. |

Initial candidate status is deliberately Pending. Public project descriptions are not evidence of successful operation on our OS/hardware.

## 3. OS test lanes

### Lane L — Linux native

#### L-00 — Current KUS Linux repository baseline

The first test in this lane is not Pterodactyl or shared storage. It is the existing Linux installer and plugin payload from [`larnzs/cs2-kus-addons`](https://github.com/larnzs/cs2-kus-addons/tree/integration/kus-monorepo), on branch `integration/kus-monorepo`.

- **Reported complete:** Git installed on Debian and repository cloned under the expected path `/home/fragcityadmin/projects/cs2-kus-addons`.
- **Still pending:** capture `git status --short --branch` and `git log -1 --oneline`; shell syntax checks; snapshot VM; native CS2/Steam Runtime installation with the repository setup script while the plugin updater is explicitly disabled; first controlled instance launch; verify actual plugin load logs.
- **Not yet passed:** Linux game install, CS2 startup, MetaMod/CounterStrikeSharp load, individual plugin health or database-backed plugin behaviour.

This baseline must be measured before comparing alternative multi-instance managers or shared storage. See [Debian PoC Log](DEBIAN-POC.md).

Primary target: the Debian 13 lab Node with Pterodactyl/Wings under evaluation. The first round should validate one server on the central Base and then two Pterodactyl instances sharing that Base, with private addons/cfg/GameMode files verified independently. Use the exact installed Debian version, kernel, Docker/Wings/Panel/Egg versions and filesystem in every record.

Candidates:
- **L-01 Pterodactyl Panel + Wings + one CS2 Egg** — baseline control plane and lifecycle.
- **L-02 K4ryuu/CS2-Egg VPK Sync** — optional comparison candidate only if it helps implement/validate shared files; not the selected architecture by itself.
- **L-03 GamesTwoLife/cs2-multiserver** — native Bash multi-instance manager with a shared base installation.
- **L-04 Trimeon/cs2-install** — native Linux master install plus per-instance directories; optional Docker mode.
- **L-05 Auto-Tournament/cs2-server-manager** — native Linux shared-install manager; commercial licence gate must be resolved before any commercial deployment.
- **L-06 1zc/CS2-Pterodactyl** — candidate CS2 Egg/image to compare as the Panel baseline, not a shared-storage manager by itself.

### Lane W — Windows native

Primary target: the existing Windows lab host, but tests must be scheduled so they do not disrupt other use. Record Windows edition/build, filesystem, free space, Defender/Firewall state and exact tool release.

Candidates:
- **W-01 WindowsGSH + WindowsGSH.CounterStrike2 module** — native desktop server manager and CS2 module. The module README currently labels it **NEEDS LIVE TEST**. WindowsGSH describes its licence as source-available, not OSI-approved, and states that commercial use requires prior written permission; therefore licence clearance is a production gate. CS2 RCON and addon management are listed as unsupported/unverified in the module README.
- **W-02 fpaezf/Game-Server-Admin** — Windows GUI, bulk install/update/start/stop, watchdog and console monitoring; README describes it as Work in Progress. Verify current licence, release, safe credential handling and whether instances really use independent install paths. Shared game files are not assumed.
- **W-03 FragCity's existing Windows install/update workflow** — comparison baseline only. Do not modify another protected repository as part of these tests; any code changes need a separate explicit task and branch.
- **W-04 fpaezf/CS2-server-manager (legacy)** — explicitly deprecated in its README and points to Game-Server-Admin. Do not treat this legacy repo as the preferred test target; use it only to understand history if needed.

### Lane X — Cross-platform and compatibility

- **X-01 maxdeepfield/cs2-server-cli** — README claims Windows, Linux and macOS support and multi-server operations. Treat the claim as unverified until builds and lifecycle tests pass on both Windows and Linux. Do not assume shared base files; verify implementation.
- **X-02 GamesTwoLife/cs2-multiserver on WSL2** — the README says Bash on Linux or WSL2. This is a compatibility experiment only, not proof of native Windows support and not a substitute for Linux production testing.
- **X-03 FragCity Windows/Linux deployment scripts comparison** — run as a separate comparison task if explicitly authorised. A passing Windows test does not imply Linux parity and vice versa.

## 4. Test stages

### Stage 0 — Intake, source and licence

For each candidate, record:
- Exact repository URL, branch/commit SHA and release/version; do not follow a moving `main` silently for a repeat test.
- Licence text and whether commercial hosting, modification, redistribution and use of dependencies are allowed.
- Last meaningful release/activity, open security advisories, issues relevant to CS2, known limitations and required runtimes.
- Installation scripts, requested privileges, network downloads, self-update logic, scheduled jobs/services, startup commands and cleanup/delete operations.
- Secret handling: Steam login, Steam Guard, GSLT, RCON, file permissions, redacted output and logs.
- Whether the candidate shares files, which exact paths are shared, how writable paths are isolated, and how update locking/rollback works.

**Gate:** a candidate with an unresolved licence blocker is not approved for commercial use. A candidate requiring unexplained root/admin execution or exposing secrets stays blocked until reviewed.

### Stage 1 — One server baseline

1. Install one clean CS2 dedicated server using the candidate's documented method.
2. Validate files and startup command, then record boot time and disk footprint.
3. Join locally or from an authorised test client; check server listing/query if that is part of the expected use.
4. Confirm map, game mode, player slots, port, console, logs and graceful stop/restart.
5. Start the server after a host reboot and confirm there is no duplicate process.
6. Install a representative plugin stack only after an unmodded baseline passes. Validate CounterStrikeSharp/Metamod separately where relevant.
7. Capture redacted logs and exact version info.

### Stage 2 — Two independent instances

1. Create two instances with different names, ports, maps/game modes and settings.
2. Start both simultaneously and verify that each is reachable on its own expected port.
3. Change one instance's hostname, `cfg/`, addon files/configuration, GameMode text files and a disposable private test file; verify the other instance does not change.
4. Check process identity, process arguments, working directory, PID tracking, console routing, logs and restart behaviour.
5. Verify collision protection covers game, query, GOTV and every additional port required by that server's configuration.
6. Reboot the host and verify exactly the configured servers return without starting duplicates.
7. Try a crash/forced exit in the lab and check recovery and logging.

**Gate:** no port collision, no cross-instance configuration mutation, no process-control ambiguity and no data loss.

### Stage 3 — Shared Base and coordinated-update validation

1. Inventory paths and classify each as shared Base/read-only, Instance-private configuration, Instance-private addon/mod payload, or Instance-writable runtime data. Record evidence; never infer solely from a folder name.
2. Establish a working one-instance disk/startup baseline on the central Base. A full independent-copy footprint may be measured for comparison but is not the target design.
3. Validate the proposed method for exposing Base files into each Pterodactyl instance (mount, link, overlay or other mechanism) separately. Treat VPK Sync as optional unless it is shown to be needed.
4. Compare shared paths and private files from each instance; prove that addons/, cfg/, GameMode text files and mutable runtime data are not inadvertently shared.
5. Attempt to modify a private test file from one instance; the other must not see an unauthorised change. Never run this against real customer data.
6. Simulate an update trigger. All instances using the Base must stop before SteamCMD runs centrally, and new start/restart requests must be blocked for the whole update window.
7. Verify that SteamCMD runs once against the central Base and is not invoked by each instance's deploy/install script.
8. Validate update success/version/file state before allowing starts again. Restore only instances that were running before the update; leave previously stopped instances stopped.
9. Simulate an interrupted/failed update and verify no instance starts against a partial Base; confirm recovery/rollback and status reporting.
10. Reinstall or delete one instance and confirm the shared Base and other instances remain intact. Test backup/restore of one instance and after a Base update.

**Gate:** shared Base files are only changed by the central update process; per-instance data remain isolated; the update barrier prevents starts against partially updated files; failure is detected and recoverable.

### Stage 4 — Backup, permissions and security

- Back up each instance and restore it to a disposable target.
- Confirm that client-level file access, SFTP/File Manager, console, backup, metadata and API access cannot cross tenant boundaries.
- Check ownership and ACLs on host paths and in containers.
- Verify that paths outside the instance are not unintentionally exposed.
- Check secret redaction in the process list, UI, logs, config exports and error messages.
- Confirm that uninstall, remove, reinstall and cleanup commands do not delete shared data or sibling instances.
- Verify updates can be pinned, reviewed, rolled back and reproduced.

**Gate:** a cross-tenant access issue, credential exposure, unreviewed destructive script or failed restore is a hard stop.

### Stage 5 — Performance and scale

Run comparable tests at **1, 2 and 5 instances**. Only consider 8–10 after measuring actual capacity.

Record, for each run:
- Total and per-instance disk usage; free space and inode usage.
- RAM at idle, map loaded and with a controlled player/bot load where feasible.
- CPU average and peak, disk I/O, system load, swap use, and any OOM/crash.
- Time to install, update, boot, map-ready, stop and restore.
- Download volume/time for a new instance and an update.
- Query/connectivity success, latency and packet loss under the same network conditions.
- Shared-storage savings compared with independent copies.

Never infer performance from repository marketing figures. Preserve raw results and test conditions.

### Stage 6 — Pterodactyl-specific checks

- The Panel owns each Server record; Wings/Docker lifecycle must be the only authority starting/stopping the Pterodactyl-managed process.
- Confirm the Egg/template works on the target Linux Node. Its per-instance addon/mod deploy step must not invoke SteamCMD, install/update the Base Game, or start the server; Base update belongs to the central updater.
- Confirm allocations, container path, user/group permissions, CPU/RAM/disk limits, console, File Manager, SFTP, logs and backup.
- Verify whether custom mounts are visible in File Manager/SFTP; do not assume they are.
- If VPK Sync is evaluated, confirm its install/update daemon, status protocol, lock, recovery path and privileged scripts before installing; it is not a required dependency of the selected design.
- A standalone manager such as MSM or cs2-install must not independently stop, update, or rewrite a Pterodactyl-owned instance unless an explicit integration owns and tests that boundary.

### Stage 7 — Decision record

For every candidate, record:
- Passed, failed, blocked or not tested cases by ID.
- Exact command/workflow and redacted log path.
- Observed disk savings and measured capacity.
- Security/licence/maintenance risks.
- Keep, reject, or defer recommendation and the reason.
- Follow-up actions and who approved them.

## 5. Candidate test matrix

| ID | Candidate | Lane | Intended test | Initial status |
|---|---|---|---|---|
| L-00 | Current KUS Linux repository baseline | Linux | Native install and health of CS2, MetaMod/CounterStrikeSharp and tracked plugins | CLONE REPORTED COMPLETE; install and runtime test pending |\n| L-01 | Pterodactyl Panel + Wings + CS2 Egg | Linux | Baseline, lifecycle, tenant isolation | NOT REVIEWED |
| L-02 | K4ryuu/CS2-Egg VPK Sync | Linux | Shared VPK, update locking, rollback | NOT REVIEWED |
| L-03 | GamesTwoLife/cs2-multiserver | Linux | Shared base, independent config, ports, watchdog | NOT REVIEWED |
| L-04 | Trimeon/cs2-install | Linux | Master install, instances, optional Docker | NOT REVIEWED |
| L-05 | Auto-Tournament/cs2-server-manager | Linux | Shared install, hardlink storage, updates | BLOCKED pending commercial licence review |
| L-06 | 1zc/CS2-Pterodactyl | Linux | Egg/image baseline comparison | NOT REVIEWED |
| W-01 | WindowsGSH + CS2 module | Windows | Native install, multi-server lifecycle, A2S, backup | BLOCKED pending licence/security review; module needs live test |
| W-02 | fpaezf/Game-Server-Admin | Windows | Bulk operations, watchdog, console, independent paths | NOT REVIEWED |
| W-03 | Current FragCity Windows deployment workflow | Windows | Known-good baseline comparison | DEFERRED to a separate authorised test task |
| W-04 | Legacy fpaezf/CS2-server-manager | Windows | No live test planned; deprecated upstream | DEFERRED |
| X-01 | maxdeepfield/cs2-server-cli | Cross-platform | Build + lifecycle on both Windows and Linux | NOT REVIEWED |
| X-02 | GamesTwoLife/cs2-multiserver under WSL2 | Windows compatibility | WSL2 compatibility only | NOT REVIEWED |

## 6. Results log template

Copy this block into a dated result entry for every run.

```text
Run ID:
Date/time and timezone:
Candidate / repository:
Commit SHA / release:
Lane and OS build:
Hardware / filesystem / free disk:
Panel / Wings / Docker / Egg versions (if applicable):
Test IDs:
Preconditions and snapshot/backup:
Steps performed:
Expected result:
Actual result:
Pass / Fail / Blocked:
Measured boot/update/restore times:
Disk and memory results:
Log / screenshot locations (secrets redacted):
Unexpected side effects:
Rollback performed and result:
Recommendation:
Reviewer:
```

## 7. Exit criteria

The testing programme is not complete until:
- At least one native Linux Pterodactyl baseline passes.
- A two-instance isolation test passes.
- Linux shared-file candidates are either passed or explicitly rejected with evidence.
- At least one Windows-native candidate and the cross-platform candidate receive documented build/live-test outcomes or a reasoned blocker.
- The trade-off between full copy and shared-file storage is quantified on the actual lab.
- Backup/restore, update interruption, delete/reinstall and restart/reboot behaviour pass.
- Licence, security and commercial-use blockers are resolved for the recommended path.
- A separate ADR records the final decision; no candidate is considered production-ready merely because its README claims the feature.


## 8. Current lab checkpoint — KUS Linux clone

Current state is **clone reported complete; install not run**. Expected clone path: `/home/fragcityadmin/projects/cs2-kus-addons`, branch `integration/kus-monorepo`. The Git status and last commit output must be captured before installing. First baseline commands and the reason to avoid `linux/install.sh` for this first run are documented in [Debian PoC Log](DEBIAN-POC.md). Keep this test independent of the Windows game files; do not copy or move the Windows installation into the Linux runtime.
