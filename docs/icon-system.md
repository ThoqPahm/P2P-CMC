# Phosphor icon system

Replaces Bootstrap Icons throughout admin, student, login and embedded widget surfaces. The icon-only scope supersedes the previous screenshot icon freeze; page layouts, CMC branding, Microsoft branding and the thesis PDF remain unchanged.

Source: https://github.com/phosphor-icons/core, version 2.1.1, MIT license in `assets/icons/LICENSE`.

The existing `bi-*` class names remain semantic adapters so PHP and dynamically rendered JavaScript use the same family without changing event handlers. `tools/icon-map.json` maps 120 existing names to 93 Phosphor shapes. Regular weight is used for controls; duotone for navigation and larger feature icons. Authored SVG geometry is unchanged. SVG alpha masks inherit the current text color on both light and dark surfaces.

Assets are vendored in `assets/icons/phosphor.css`. Production needs no npm build, icon font, CDN or client-side SVG replacement. The shadow-DOM embed launcher contains the same officially sourced chat icon inline. Brand logos are deliberately not substituted with generic icons.

Rebuild from the official `@phosphor-icons/core@2.1.1` npm archive, extracted to a temporary directory:

```sh
php tools/build-icons.php /path/to/extracted/package
php tests/icons.php
```

Motion is interaction-only: send arrows nudge toward their destination, navigation icons lift slightly, the bell makes one brief movement on hover. Keyboard focus has equivalent feedback. `prefers-reduced-motion` disables these effects. No permanent icon animation, new JavaScript runtime or changes to application state.

Taste design read: targeted visual refinement for students and university staff; clean, rounded iconography within the existing Bootstrap/CMC visual system. Variance 3, motion 3, density 5. Landing-page-specific rules do not apply to this icon-only product UI change.
