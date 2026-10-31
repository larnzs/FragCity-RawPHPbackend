# WeaponPaints SVG assets

All files are served from `/img/weaponpaintsicons/<filename>` and are used by the horizontal WeaponPaints toolbar.

## Category toolbar

| Category | SVG |
| --- | --- |
| Inventory | `inventory.svg` |
| All Skins | `allskins.svg` |
| Rifles | `ak47.svg` |
| SMG | `p90.svg` |
| Heavy Guns | `m249.svg` |
| Pistols | `usp_silencer.svg` |
| Snipers | `awp.svg` |
| Knives | `knife_m9_bayonet.svg` |
| Gloves | `gloves.svg` |
| Agents | `agent.svg` |
| Music Kits | `music_kit.svg` |
| Pins | `pin.svg` |

## Team selector

- `t_char_bg.svg` — Terrorist
- `ct_char_bg.svg` — Counter-Terrorist

The category switcher, team selector, and search field share one toolbar row on desktop. On narrower screens, the category strip remains horizontally scrollable and the team/search controls use a second row.

The glove icon's red artwork has been converted to white to match the other monochrome toolbar assets. Weapon silhouettes use a wider display box than square category icons; the CSS keeps each icon's proportions with `object-fit: contain`.
