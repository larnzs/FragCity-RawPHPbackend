# WeaponPaints SVG assets

All SVGs are served from `/img/weaponpaintsicons/<filename>`.

## Vertical right-side control rail

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

## Vertical team selector

- `t_char_bg.svg` — Terrorist (T)
- `ct_char_bg.svg` — Counter-Terrorist (CT)
- BOTH uses the layered-controls icon from the icon font.

The right-side rail groups category navigation, the vertical T / BOTH / CT selector, and the search field. Search starts as a compact icon control and expands to the left when focused. Category and team SVGs are displayed in FragCity orange with a soft glow; active controls receive a highlighted box and stronger lower glow.

Weapon silhouettes, gloves, agents, music kits, and pins each have individual display dimensions so their proportions remain balanced in the narrow rail. The glove source artwork is white; CSS applies the shared orange treatment without modifying the original SVG paths.
