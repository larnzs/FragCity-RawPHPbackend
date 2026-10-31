# WeaponPaints SVG assets

These are the SVG files currently used by the WeaponPaints interface. Public URLs are served from `/img/weaponpaintsicons/<filename>`.

## Category rail

| UI category | SVG file |
| --- | --- |
| Rifles | `ak47.svg` |
| Heavy guns | `m249.svg` |
| Pistols | `usp_silencer.svg` |
| Snipers | `awp.svg` |
| Knives | `knife_m9_bayonet.svg` |
| Gloves | `gloves.svg` |
| Agents | `agent.svg` |
| Music kits | `music_kit.svg` |
| Pins | `pin.svg` |

The Inventory, All Skins, and SMG categories currently use their Font Awesome fallback because no matching SVG has been uploaded for those categories yet. The UI intentionally keeps these fallbacks so a missing asset never breaks navigation.

## Team switcher

- `t_char_bg.svg` — Terrorist team
- `ct_char_bg.svg` — Counter-Terrorist team

## Asset guidance

Keep the uploaded filenames unchanged because the PHP markup references them directly. Weapon silhouettes are landscape artwork and are scaled with `object-fit: contain`; team artwork is displayed separately in the CT/T selector. Prefer transparent SVGs with a clear `viewBox` and no embedded raster assets.
