# FragCity Server Guide

The public guide is a compact, searchable reference for the commands, game modes, permissions, and settings used by the FragCity CS2 server package.

## Route and files

- Website route: `index.php?page=serverguide`
- Page markup: `pages/serverguide.php`
- Styles: `css/serverguide.css`
- Interactions: `js/serverguide.js`
- Catalog: `data/server-guide.json`
- Navigation: `includes/header.php` and `includes/footer.php`
- Route and page asset registration: `index.php`

The older `?page=usermanual` page is intentionally retained. The Server Guide is a separate, compact directory and does not replace it.

## Source of truth

The package inventory is maintained in [larnzs/cs2-kus-addons](https://github.com/larnzs/cs2-kus-addons/tree/integration/kus-monorepo), branch `integration/kus-monorepo`.

When documenting a plugin, use sources in this order:

1. The actual tracked files/configuration in `cs2-kus-addons` for the current packaged version.
2. The upstream plugin's own README, Wiki, command documentation, or release-specific docs.
3. The plugin's configuration and command-registration files as supplementary evidence.

The tracked `CS2-SimpleAdmin/Commands.json` is the canonical list of the configured SimpleAdmin command names and aliases in this package. Upstream documentation supplies argument syntax, behavior, and permission flags. The two should be cross-checked; do not add a command merely because a different plugin or a newer upstream version supports it.

Useful source repositories include:

| Plugin | Upstream source |
|---|---|
| CS2-SimpleAdmin | https://github.com/daffyyyy/CS2-SimpleAdmin |
| GameModeManager | https://github.com/nickj609/GameModeManager |
| MatchZy | https://github.com/shobhit-pathak/MatchZy |
| CS2 Retakes | https://github.com/B3none/cs2-retakes |
| K4-Arenas | https://github.com/K4ryuu/K4-Arenas |
| VIPCore | https://github.com/partiusfabaa/cs2-VIPCore |
| CS2-Playervotes | https://github.com/asapverneri/CS2-Playervotes |
| CS2-StaffList | https://github.com/asapverneri/CS2-StaffList |
| CS2FaceitLevels | https://github.com/Staaar0/cs2-FaceitLevels |
| cs2-ranks | https://github.com/partiusfabaa/cs2-ranks |
| GG1MapChooser | https://github.com/ssypchenko/GG1MapChooser |
| AFKManager | https://github.com/NiGHT757/AFKManager |
| SimpleResetScore | https://github.com/stefanx111/cs2-SimpleResetScore |

## Availability labels

Each catalog item may contain an `availability` field:

- `default`: the plugin or command is in the package's default active payload.
- `config`: the feature is present but depends on a configuration value, API key, or permission.
- `mode`: the plugin is staged under `plugins/disabled/` and must be enabled for the relevant mode before its commands are available.
- `verify`: the tracked configuration or upstream package suggests the feature, but it needs a live server test before being described as guaranteed.
- `disabled`: the tracked configuration currently has the feature switched off.

These labels are documentation metadata, not a live health check. They describe the checked-in `integration/kus-monorepo` snapshot and may differ from a server whose runtime files have been changed locally.

Important package-specific distinctions:

- Daffy CS2-SimpleAdmin and KUS's separate `connercsbn/SimpleAdmin` are different plugins. The detailed administration commands in this guide refer to Daffy's CS2-SimpleAdmin unless stated otherwise.
- MatchZy, CS2 Retakes, and K4-Arenas are mode-specific payloads staged under `plugins/disabled/`; do not present their commands as globally available.
- The package README notes that GameModeManager is awaiting an upstream fix. Its configured modes and command reference are useful documentation, but mark it as `verify` until the current runtime is tested.
- The current GameModeManager configuration has its own RTV feature disabled. This does not mean the separate GG1MapChooser or RockTheVote plugins are disabled.
- GG1MapChooser's current config enables RTV but disables its separate PoolVote feature. Reflect the individual setting rather than treating every voting feature as enabled.
- FACEIT ELO commands depend on a valid runtime API key. Never commit a real API key.
- VIPCore database credentials, FACEIT keys, Discord webhooks, RCON passwords, and other secrets must never be copied into this guide or committed to the repository.

## Catalog structure

`data/server-guide.json` is valid JSON and contains:

- `meta`: page notice and description.
- `categories`: `id`, `title`, `description`, and Font Awesome `icon`.
- `items`: one entry per command, setting, or game mode.

Catalog item fields:

| Field | Purpose |
|---|---|
| `id` | Unique, stable identifier. Never reuse an existing ID. |
| `type` | `command`, `setting`, or `mode`. |
| `category` | Existing category ID from `categories`. |
| `title` | Short human-readable title. |
| `command` | Player-facing command or configuration key shown in the primary row. |
| `console_command` | Optional server-console form. It receives a separate copy button. |
| `aliases` | Verified aliases for the same command. |
| `description` | Concise explanation of the behavior. |
| `access` | Required permission, such as `@css/ban`, or the intended audience. |
| `plugin` | Exact plugin name responsible for the feature. |
| `source_url` | HTTPS link to the relevant vendor docs or package source. |
| `availability` | One of `default`, `config`, `mode`, `verify`, or `disabled`. |
| `availability_note` | Explains conditional activation, configuration, or verification requirements. |
| `example` | Optional, verified command example. |
| `note` | Optional safety or usage caveat. |
| `tags` | Search keywords. |

The website escapes catalog values before rendering. It accepts only HTTPS source links. Search includes titles, command names, aliases, descriptions, permissions, plugin names, tags, and availability notes. The copy controls use browser clipboard support with a fallback.

## Add or update a command

1. Confirm the plugin is present in the current `DEPENDENCIES.json` or KUS payload inventory.
2. Read its tracked command/config files from `integration/kus-monorepo`.
3. Check the matching upstream documentation for exact syntax, aliases, permissions, and behavior.
4. Add the entry to `data/server-guide.json` with a unique ID, plugin, source URL, and availability status.
5. Add examples only when the syntax is verified. Do not invent examples for commands whose arguments are unknown.
6. Keep the page markup generic; command data belongs in JSON.
7. Run the JSON, JavaScript, PHP, and architecture checks.

Example:

```json
{
  "id": "new-plugin-command",
  "type": "command",
  "category": "admin",
  "title": "Command title",
  "command": "!example <target>",
  "console_command": "css_example <target>",
  "aliases": [],
  "description": "Describe the behavior verified in the installed plugin version.",
  "access": "@css/generic",
  "plugin": "PluginName",
  "source_url": "https://github.com/vendor/plugin",
  "availability": "default",
  "tags": ["plugin", "example"]
}
```

## Validation

From the website repository root:

```bash
php -r '$d=json_decode(file_get_contents("data/server-guide.json"), true, 512, JSON_THROW_ON_ERROR); echo count($d["items"]), " guide items", PHP_EOL;'
php -l pages/serverguide.php
node --check js/serverguide.js
php linuxapi/tools/architecture-check.php
```

## Accuracy rule

The catalog describes a particular tracked package snapshot. It is not a replacement for the installed server's live plugin state. A file under `configs/plugins/` proves that a configuration file is tracked; it does not prove the corresponding plugin is loaded. A DLL under `plugins/disabled/` is not a globally active plugin. Always preserve this distinction in the public guide.
