# FragCity Server Guide

The public Server Guide is a compact command and settings directory rendered inside the existing PHP website.

## Route and files

- Page: `index.php?page=serverguide`
- Page markup: `pages/serverguide.php`
- Styles: `css/serverguide.css`
- Interactions: `js/serverguide.js`
- Catalog: `data/server-guide.json`
- Navigation: `includes/header.php` and `includes/footer.php`
- Asset registration and route allowlist: `index.php`

The older `?page=usermanual` page is intentionally retained. The new guide is a separate compact interface and does not replace that page.

## Catalog structure

`data/server-guide.json` is valid JSON with two main arrays:

- `categories`: `id`, `title`, `description`, and Font Awesome `icon`.
- `items`: `id`, `type` (`command` or `setting`), `category`, `title`, `command`, `aliases`, `description`, `access`, and optional `syntax`, `example`, `note`, and `tags`.

The page escapes all catalog values before rendering. Search matches title, command, aliases, description, access level, and tags. Category filters and copy controls are client-side and do not make API calls.

## Add a command

1. Open `data/server-guide.json`.
2. Add the command to `items` with a unique stable `id`.
3. Use an existing category ID, or add a new category to `categories`.
4. Include the exact command spelling, aliases, a verified description, and its permission level.
5. Add an example only when it has been verified on the target server/plugin.
6. Validate the JSON and run the architecture checks.

Example:

```json
{
  "id": "new-command",
  "type": "command",
  "category": "player",
  "title": "Command title",
  "command": "!example <value>",
  "aliases": [],
  "description": "Describe the verified behavior.",
  "access": "بازیکن",
  "syntax": "!example <value>",
  "example": "!example sample",
  "tags": ["example"]
}
```

## Important content rule

The catalog is a user-facing reference, not an authoritative runtime permission system. Verify command names, aliases, access levels, and behavior against the actual CS2 plugin version and server configuration before publishing changes. Do not imply that a command is available on every server or every game mode when availability is conditional.
