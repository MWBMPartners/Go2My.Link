<!--
File: /BrandKit/docs/EDITING_GUIDE.md
Purpose: How to edit/recolour the Go2My.link logo assets in Photoshop / Affinity using the provided vectors
(C) 2025–present MWBM Partners Ltd (d/b/a MW Services)
Version: 1.0
-->
# Editing & recolouring guide

## Best editable source (vector)
Use: `BrandKit/logos/svg/Go2My.link-Logo-EDITABLE-LAYERS.svg`

It contains separate groups:
- `layer-icon`
- `layer-wordmark`

…and uses CSS variables at the top for easy recolouring.

## Photoshop
This environment can’t generate a native layered `.psd` directly.
But Photoshop imports SVG as vector shapes (often as a Smart Object):
1. File → Open → `Go2My.link-Logo-EDITABLE-LAYERS.svg`
2. Edit colours/gradients
3. Export to PNG/SVG/PDF as required

## Affinity Designer/Photo
This environment can’t generate native `.afdesign` / `.afphoto` directly.
But Affinity imports SVG with layer groups intact:
1. File → Open → `Go2My.link-Logo-EDITABLE-LAYERS.svg`
2. Expand `layer-icon` / `layer-wordmark`
3. Recolour and export

## Perfect fidelity glossy version
Use: `Go2My.link-Logo-ORIGINAL-Embedded.svg`
This is scalable and matches the approved art exactly (because it embeds the original PNG).
