# Icon system: AnimateIcons

Source: https://github.com/Avijit07x/animateicons. Pinned npm package @animateicons/react 0.5.0 (MIT).

The owner's requested migration replaces the previous Phosphor runtime. Existing bi-* classes remain semantic adapters so templates, labels, layout, CMC artwork, Inter and the Syne eAmbassador wordmark do not change. 120 aliases map to 88 official components in tools/animateicons/mapping.json. Where there is no exact glyph, a related symbol from the same catalog is used (for example Tags for hash and BookOpenCheck for education).

## Runtime and hosting

- assets/icons/animateicons.css contains static SVG masks rendered from the official React components at build time. Icons remain visible if JS fails or is disabled.
- assets/js/animateicons.js is a prebuilt local production bundle (React, ReactDOM, AnimateIcons with bundled Motion). No CDN modules or npm are required on hosting.
- Only an interacted-with icon mounts a React component. Animated SVGs overlay the same fixed placeholder; links, buttons and labels are not React-controlled or translated.
- Hover and keyboard focus trigger animation; reduced-motion preference keeps the static icon. Removed widget nodes and changed icon classes are cleaned up.
- The shadow-DOM embed launcher uses the same official MessageCircleMore SVG, with its lightweight existing CSS hover effect. It does not load a second React bundle into the host site.
- The old Phosphor assets/build tool remain for historical reproducibility but no page loads them.

On cPanel, run only git pull origin main. No database or server configuration change is needed for this release.

## Rebuilding locally

Run from tools/animateicons:

```sh
npm ci --ignore-scripts
npm run build
```

Commit the generated CSS, JS, manifest and licenses together. Do not upload node_modules. The package lock pins dependencies. The build uses only exports present in the official package and fails on missing symbols.

## Verification

- php tests/icons.php: 120 aliases covered and 88 valid SVGs, no unsafe SVG elements.
- php tests/original-ui.php: original login markup, Inter body, Syne wordmark and new icon entry points.
- php tests/navigation-stability.php: no menu label translation and stable scrollbar gutter.
- Browser: bell hover mounts the actual component; button coordinates and 40x40 dimensions are identical before/after interaction. Pointer leave restores the static mask. No JS errors observed in this flow.

Licenses are distributed next to the icon assets; the bundle's legal-notice file must be retained. The JS bundle is about 400 KiB before HTTP compression; this is an explicit cost of using the official React/Motion components instead of only static masks.
