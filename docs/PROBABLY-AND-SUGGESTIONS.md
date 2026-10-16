# FragCity — Probably and Suggestions

> Last updated: 2026-10-16  
> This document contains hypotheses and recommendations, not final technical decisions. Confirmed decisions live in `docs/DECISIONS.md`; test results live in `docs/TESTING-BENCH.md`.

## 1. What this section means

“Probably” means a plausible direction that still needs evidence. “Suggestions” means a recommendation for the next experiment. Neither label means that code has been installed, tested, or approved for production.

Use this file as the shortlist and decision inbox. When a candidate is tested, update its status in the Testing Bench and then decide whether to keep, reject, or defer it.

## 2. Current top hypothesis

### Likely Linux production path: Pterodactyl + Wings, then optional VPK Sync

**Hypothesis:** Pterodactyl/Wings is the primary candidate for customer-facing server lifecycle, permissions, files, console and resources. Begin with independent per-server game copies so that the initial isolation baseline is simple. After two independent instances pass, test the K4ryuu/CS2-Egg VPK Sync approach as an optional storage optimisation.

- Pterodactyl should remain the one lifecycle authority for each managed instance.
- VPK Sync is a separate optimisation. It does not replace the Panel, customer accounts, billing, or lifecycle policy.
- Do not activate shared files just to save disk before isolation, update locking and restore tests pass.
- Paymenter remains a billing candidate; server storage and billing are separate decisions.

**Why this is the best first experiment:** it keeps client access and server operations inside the selected Panel model, while making storage optimisation reversible.

**Evidence required:** L-01 and MI-01/MI-02 pass first; then L-02, MI-04/MI-05/MI-07/MI-08/MI-09 pass with measured disk savings.

## 3. Linux candidates

| Candidate | Why it is worth considering | Current suggestion |
|---|---|---|
| [Pterodactyl Panel + Wings](https://github.com/pterodactyl/panel) | Customer-facing server management, allocations, resource limits, console, file access and lifecycle | **Primary PoC**, not yet production-approved |
| [K4ryuu/CS2-Egg VPK Sync](https://github.com/K4ryuu/CS2-Egg/blob/main/docs/features/vpk-sync.md) | Specific attempt at sharing VPK files in a Pterodactyl/Pelican environment | **First shared-storage candidate** after baseline |
| [GamesTwoLife/cs2-multiserver](https://github.com/GamesTwoLife/cs2-multiserver) | Strong example of independent configurations/ports around one shared base game install | Test as standalone comparison; do not let it and Wings own the same instance |
| [Trimeon/cs2-install](https://github.com/Trimeon/cs2-install) | Master installation linked to separate instances, with optional Docker | Alternative standalone Linux design; compare update, backup and plugin paths |
| [1zc/CS2-Pterodactyl](https://github.com/1zc/CS2-Pterodactyl) | Existing CS2 Egg/image implementation to compare | Use as an Egg baseline candidate; does not itself prove shared storage |
| [Auto-Tournament/cs2-server-manager](https://github.com/Auto-Tournament/cs2-server-manager) | Has useful shared-install and update-handling ideas | Technical reference only until its PolyForm Noncommercial licence and commercial-use terms are resolved |

### Linux suggestions

- Test on the native Debian lab before considering WSL2 or Windows compatibility.
- Run one independent instance, then two independent instances; only then test shared VPK.
- Prefer the narrowest sharing boundary possible (for example, verified read-mostly VPK files) over sharing the whole writable `game/` tree.
- Keep a central update lock and a known-good fallback. A partially updated base must never be served to new starts.
- If a stand-alone manager is tested, use a separate VM/snapshot. Do not overlay it on a server already owned by Pterodactyl.

## 4. Windows-native candidates

| Candidate | Why it is worth considering | Current suggestion / gate |
|---|---|---|
| [WindowsGSH](https://github.com/WindowsGSH/WindowsGSH) + [CounterStrike2 module](https://github.com/WindowsGSH/WindowsGSH.CounterStrike2) | Native Windows desktop management for multiple game servers; CS2 module declares install/start/query/backup-related capabilities | Test in an isolated Windows lab. Module README says it needs a live test. WindowsGSH says commercial use requires prior written permission, so do not select for commercial hosting before licence clearance. RCON/addon functions have known gaps. |
| [fpaezf/Game-Server-Admin](https://github.com/fpaezf/Game-Server-Admin) | Windows GUI for bulk install/update/start/stop, watchdog and console output | Test as a local Windows manager candidate; README warns it is Work in Progress. Verify licence, secrets, per-server install isolation and unattended restart before adoption. |
| [fpaezf/CS2-server-manager](https://github.com/fpaezf/CS2-server-manager) | Legacy Windows GUI | README marks it deprecated and points to Game-Server-Admin. Prefer the new repository; do not spend primary test effort on the legacy tool. |
| Existing FragCity Windows deployment workflow | Provides a useful known-good comparison for install/update behaviour | Test as a baseline in a separate authorised task; do not modify the protected `cs2-kus-addons` repository as part of this planning document. |

### Windows suggestions

- A native Windows manager may be useful for local operation and comparison, but a desktop app does not automatically provide tenant isolation, a secure customer-facing SaaS Panel, API provisioning, or wallet/expiry integration.
- For the longer-term hosting platform, treat Windows managers as operating-system-specific candidates, not replacements for the Linux production Panel architecture.
- Test headless startup, recovery after reboot, Windows service/session behaviour, Firewall rules, process arguments, per-instance working directories, console capture, port uniqueness, secret redaction and backup/restore.
- Do not assume that a Windows manager supports sharing a base game install just because it can manage multiple servers. Inspect how it creates install paths and test file identity and update behaviour.

## 5. Cross-platform / compatibility candidates

| Candidate | Claim or use | Suggestion |
|---|---|---|
| [maxdeepfield/cs2-server-cli](https://github.com/maxdeepfield/cs2-server-cli) | README claims Windows, Linux and macOS support and multiple instances | Build and test it on both Windows and native Linux; verify implementation, release activity, licence, data layout and safe command handling before relying on the claims. Shared base files are not assumed. |
| [GamesTwoLife/cs2-multiserver on WSL2](https://github.com/GamesTwoLife/cs2-multiserver) | README says it runs with Bash on Linux or WSL2 on Windows | Optional WSL2 compatibility test only. Do not count it as native Windows support or proof of Linux production reliability. |

## 6. Storage hypotheses to compare

| Hypothesis | Possible upside | Main risk | Position |
|---|---|---|---|
| Full independent copy per instance | Clear isolation, simple restore and predictable updates | Highest disk use | **Baseline and fallback** |
| Shared immutable/read-mostly base plus private writable data | Meaningful storage savings with clearer boundaries | Path inventory and writable-file split require testing | Strong design hypothesis |
| VPK Sync inside the Pterodactyl ecosystem | Potential storage reduction without replacing the Panel | Privileged host daemon, update ordering, file permissions and recovery must be reviewed | First Linux optimisation candidate |
| Full game-directory symlink/mount | May reduce duplicated storage | Shared configs, plugins, runtime files or updates can affect multiple tenants | Do not adopt without very strong evidence |
| Separate manager managing instances alongside Pterodactyl | Could offer extra operations | Conflicting start/stop/update/delete authority | Avoid unless integration ownership is explicit |

## 7. Suggested decision order

1. Freeze the candidate list and pin each repository version/commit.
2. Finish static review and licence check before execution.
3. Establish a working Linux Pterodactyl baseline with an independent copy.
4. Pass the two-instance isolation and recovery checks.
5. Compare Linux shared-storage candidates in disposable environments.
6. Test the strongest Windows-native candidates separately on Windows.
7. Build/test the cross-platform CLI on both OSs.
8. Compare results and record one ADR; retain a full-copy fallback.
9. Integrate billing only after the Panel and node lifecycle is stable.

## 8. What would change this suggestion?

Reconsider the top hypothesis if Pterodactyl cannot meet tested CS2 lifecycle, customer isolation, backup/restore, update reliability or resource isolation requirements; if VPK Sync cannot be safely operated/rolled back; if licence restrictions disqualify a candidate; or if measured disk savings are too small relative to maintenance risk.

The decision should follow reproducible results, not GitHub stars, repository wording, or estimated savings alone.

## Related documents

- [Testing Bench](TESTING-BENCH.md) — complete Linux/Windows test programme and result template.
- [CS2 Multi-Instance](CS2-MULTI-INSTANCE.md) — architecture and shared-file models.
- [Platform PoC](PLATFORM-POC.md) — panel, billing and integration acceptance tests.
- [Repository Inventory](REPOSITORIES.md) — overall repository shortlist.
- [Architecture Decisions](DECISIONS.md) — confirmed and provisional decisions.
