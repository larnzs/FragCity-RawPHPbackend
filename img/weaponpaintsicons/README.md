# WeaponPaints SVG assets

All SVGs are served from `/img/weaponpaintsicons/<filename>`.

## Category toolbar

The category tabs are displayed horizontally in a glass-effect toolbar directly below the main FragCity header.

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

- `t_char_bg.svg` — Terrorist (T)
- `ct_char_bg.svg` — Counter-Terrorist (CT)
- BOTH uses the layered-controls icon from the icon font.

The toolbar keeps category navigation, the horizontal T / BOTH / CT selector, and the search field together. It stays below the main header while scrolling and uses a translucent graphite surface with backdrop blur. Category and team SVGs use the FragCity orange accent; active controls use a restrained orange border and translucent fill without glow effects.

Weapon silhouettes, gloves, agents, music kits, and pins share a centered icon viewport with `object-fit: contain`, preserving their native proportions. The glove source artwork is white; CSS applies the orange treatment without modifying the original SVG paths.
