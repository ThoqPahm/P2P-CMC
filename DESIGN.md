---
name: eAmbassador
description: Campus wayfinding for student progress and university operations.
colors:
  cmc-navy: "#002757"
  cmc-navy-raised: "#003B72"
  cmc-blue: "#008FD5"
  cmc-blue-dark: "#006EAA"
  cmc-cyan: "#00DEDF"
  ink: "#0B2B4D"
  muted: "#536B7E"
  subtle: "#5D7487"
  canvas: "#F3F8FB"
  surface: "#FFFFFF"
  surface-soft: "#EDF5F8"
  line: "#D8E5EC"
  line-strong: "#BFD3DE"
  success: "#14725B"
  success-soft: "#E6F5EF"
  warning: "#805F08"
  warning-soft: "#FFF6D8"
  danger: "#A53F4D"
  danger-soft: "#FDECEF"
  info-soft: "#E2F4FB"
typography:
  productMark:
    fontFamily: '"Syne", "Trebuchet MS", sans-serif'
    fontSize: "2rem"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "-0.04em"
  headline:
    fontFamily: '"Segoe UI Variable", Aptos, "Segoe UI", sans-serif'
    fontSize: "1.35rem"
    fontWeight: 750
    lineHeight: 1.5
    letterSpacing: "-0.025em"
  title:
    fontFamily: '"Segoe UI Variable", Aptos, "Segoe UI", sans-serif'
    fontSize: "1.05rem"
    fontWeight: 750
    lineHeight: 1.5
    letterSpacing: "-0.025em"
  body:
    fontFamily: '"Segoe UI Variable", Aptos, "Segoe UI", sans-serif'
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: '"Segoe UI Variable", Aptos, "Segoe UI", sans-serif'
    fontSize: "0.68rem"
    fontWeight: 700
    lineHeight: 1.2
  numeric:
    fontFamily: '"Segoe UI Variable", Aptos, "Segoe UI", sans-serif'
    fontSize: "1.45rem"
    fontWeight: 800
    lineHeight: 1.2
    fontFeature: "tabular-nums"
rounded:
  chip: "6px"
  control: "8px"
  surface: "12px"
  pill: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
components:
  button-primary:
    backgroundColor: "{colors.cmc-blue-dark}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "0.65rem 0.95rem"
    height: "42px"
  button-primary-hover:
    backgroundColor: "{colors.cmc-navy}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0.65rem 0.8rem"
    height: "46px"
  panel:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.surface}"
  status-success:
    backgroundColor: "{colors.success-soft}"
    textColor: "{colors.success}"
    rounded: "{rounded.chip}"
    padding: "0.32rem 0.5rem"
  status-warning:
    backgroundColor: "{colors.warning-soft}"
    textColor: "{colors.warning}"
    rounded: "{rounded.chip}"
    padding: "0.32rem 0.5rem"
---

# Design System: eAmbassador

## Overview

**Creative North Star: "Campus Wayfinding"**

eAmbassador treats operational work as a route through a campus: users should see where they are, what comes next, what is blocked, and which destination matters now. The signature composition is **Route + Status Rail**—one dominant work surface for sequence and handoffs, paired with a narrow live rail for verified metrics, priority signals, and secondary actions.

The system is clean, cool, precise, and dense enough for real work. Brand character comes from CMC navy, blue, and cyan; thin route lines; compact directories; tabular numbers; and stateful wayfinding markers—not ornamental graphics or a collection of interchangeable KPI cards. Student and admin surfaces share this visual grammar while presenting different operational stories.

**Key Characteristics:**

- One dominant board establishes hierarchy; supporting panels remain subordinate.
- Routes, stops, nodes, queues, and status labels make sequence visible.
- Cool white and blue-gray surfaces carry information without competing with brand color.
- Real PHP/SQLite values determine labels, counts, progress, actions, and accessibility text.
- Responsive behavior changes structure instead of merely shrinking desktop.

**The Data-Is-The-Map Rule.** Never infer a reassuring state from appearance alone. Every progress value, completion marker, priority count, action, and status label must derive from the same backend truth that drives the workflow.

## Colors

CMC navy anchors the shell and hierarchy; CMC blue indicates progress and primary action; CMC cyan is a scarce live signal. Cool neutrals define the canvas, surfaces, separators, and supporting copy. Semantic success, warning, and danger pairs communicate meaning independently of the brand accents.

### Primary

- **Campus Navy:** navigation, major headings, avatars, and the strongest structural anchors.
- **Route Blue:** progress rails, completed markers, links, and active operational states.
- **Action Blue:** primary controls and accessible focus treatment where a darker blue is needed.

### Secondary

- **Live Cyan:** active route signal, notification dots, active-navigation icons, and small live accents. It is never the main body-text color or a large decorative fill.

### Neutral

- **Cool Canvas:** app background behind the workspace.
- **Clear Surface:** boards, rails, controls, and table regions.
- **Soft Surface:** progress bands, icon wells, complete nodes, and quiet grouping.
- **Ink / Muted / Subtle:** primary copy, secondary copy, and compact metadata respectively.
- **Line / Strong Line:** default dividers and higher-emphasis control boundaries.

### Semantic

- **Success:** completed, approved, verified, and stable only.
- **Warning:** pending review or an item that genuinely needs handling.
- **Danger:** rejection, violation, destructive outcomes, or confirmed failure.
- **Info:** active but non-urgent system status.

**The Cool-Campus Rule.** Do not reintroduce orange, coral, cream, beige, warm paper, purple accents, or near-black identity colors.

**The Signal-Not-Fill Rule.** Reserve cyan for live or active cues; use blue for actions and progress, and navy for structure.

## Typography

**Display and Body Font:** Segoe UI Variable, with Aptos and Segoe UI fallbacks.

**Product Mark Font:** Syne, reserved for the eAmbassador wordmark across authentication, public navigation, the application sidebar, footer, and notification headers. On light surfaces, `e` uses CMC blue and `Ambassador` uses navy; on navy surfaces, `e` shifts to cyan and `Ambassador` to white for contrast. Syne is not used for headings, controls, labels, or ordinary body copy.

**Character:** a compact system sans that feels institutional, direct, and legible in Vietnamese. Hierarchy comes from weight, scale, and spacing; the single Syne product-mark exception provides identity without entering content typography. Headings use slightly tightened tracking; data uses tabular numerals so columns and status changes remain stable.

### Hierarchy

- **Headline:** primary board and page titles; bold, compact, and sentence case.
- **Title:** section names, rail headings, route-step titles, and table-block headings.
- **Body:** descriptions and operational copy; keep instructions short and let labels carry scanning structure.
- **Label:** context, state, metadata, and table labels; compact but never decorative all-caps.
- **Numeric:** progress, counts, rewards, and rankings; always use tabular numerals.

On narrow screens, body copy steps down to 13px and the sticky topbar title truncates to protect controls. Route descriptions stop truncating and wrap normally because workflow meaning outranks desktop density.

**The Scan-Before-Read Rule.** A user should understand title, state, value, and next action before reading explanatory copy.

### Embedded consultation widget

The public widget is a compact extension of the same system, isolated in an iframe so the host website cannot alter its layout. It uses the documented body typography and brand palette; Syne remains limited to the eAmbassador product mark. On desktop it opens as a focused side panel, while narrow screens use the full viewport. Its top navigation separates ambassador discovery, immediate chat, approved Content and appointment intent. Directory filters, content reading, profile details, chat and appointment states must remain usable by keyboard and preserve visible focus treatment.

## Layout

The internal shell uses a fixed 244px navy directory and a sticky 72px utility bar. The centered content canvas may grow to 1660px and uses 24px outer padding on desktop. The main dashboard grid is a fluid route board plus a 304px status rail, narrowing the rail to 272px below 1280px. Panels use a 16px gap and align to the top.

The route board owns the first viewport. Its header contains the surface title and primary action; the progress band follows; the route or operations map carries the central workflow; and a compact mission table or work queue closes the board. The status rail holds verified progress, UGC performance or priority signals, recent events, and secondary actions. A ranking list may follow the grid, but it does not compete with the route board.

### Responsive contract

- **Below 1200px:** the fixed directory becomes an off-canvas sidebar; the content canvas takes full width. Opening the sidebar adds a dismissible overlay, moves focus to the close control, traps Tab inside, closes on Escape, and restores focus to the trigger.
- **Below 992px:** board and status rail stack. Student rail sections may form three columns and admin rail sections two columns before the phone layout.
- **Below 768px:** use one route column, full-width progress, wrapped route descriptions, and one-column rails. Student actions move beneath copy. Admin flow arrows rotate and the horizontal three-node map becomes a vertical sequence. Nonessential mission/table columns are removed while identity, state, and action remain.
- **Below 480px:** operations summary becomes one column; secondary topbar controls disappear before essential navigation or workflow content.

**The Structural-Reflow Rule.** No horizontal page overflow. Mobile changes reading order and density; it does not compress the desktop route map into illegible rows.

## Elevation & Depth

The system is flat by default and separates regions with tonal layering and cool 1px borders. Standard panels do not float. A soft ambient shadow is reserved for the active route stop, interactive hover lift, overlays, login shell, and other true foreground layers. The sidebar and topbar are separated structurally by color and borders.

Motion is state feedback: controls transition over roughly 180ms, the off-canvas sidebar over 220ms, and the active route signal pulses slowly. Pressed buttons move down by 1px; hover lift is at most 1–2px. Under `prefers-reduced-motion: reduce`, smooth scrolling is disabled and animation/transition durations collapse to near zero with one iteration.

**The Flat-Until-Active Rule.** Elevation identifies interaction, foreground, or the current stop; it is not generic card decoration.

## Shapes

The form language uses gently curved, compact geometry: 12px for major surfaces, 8px for controls and icon wells, 6px for status labels, and full pills only for counters and progress tracks. Route markers are circular because they represent stops; ordinary content containers remain squared-off rounded rectangles. Borders stay thin and cool.

**The One-Corner-Family Rule.** New components must choose an existing radius role. Do not introduce oversized 20–32px cards, arbitrary mixed radii, or pill-shaped containers for normal content.

## Components

### Shell and navigation

The navy sidebar is a compact directory, not a marketing rail. Links have a 44px minimum height; hover adds a restrained tonal shift and 2px horizontal cue; active links use a raised navy-blue field, cyan icon, and subtle cyan border. The topbar carries context, page title, breadcrumb-like location on wide screens, notification, and identity. Preserve semantic links/buttons, accessible names, and the existing Bootstrap Icons dependency.

### Buttons and fields

Primary buttons are action-blue with white text, 8px corners, and a 42px minimum height; they deepen to navy on hover. Outline, light, ghost, and icon variants keep the same geometry. Inputs and selects are 46px high with strong cool borders; focus changes the border to route blue and adds a translucent blue halo. All interactive elements also receive the global 3px dark-blue `:focus-visible` outline with a 3px offset.

### Login character scene

The desktop login visual uses the original Eyes Follow Mouse character geometry and interaction model: four overlapping flat characters in purple, black, orange, and yellow, with their original proportions, stacking order, face placement, tracking, random blinks, mutual glances, hidden-password lean, look-away state, and purple peek. Only the backdrop is customized: a CMC light-blue-dominant gradient that fades softly to white at the top, with a subtle 20px blue grid and the existing CMC University brand. The real PHP login form, validation, submission, and password toggle remain unchanged. On mobile the visual panel is removed and the white form retains the horizontal university logo.

The login shell is theme-based. The active theme key is stored in `ui_settings`, resolved through `login_theme_registry()`, and rendered from `pages/public/login-themes/`. The admin-only `appearance-studio` route selects the active login theme and is intentionally omitted from the sidebar. Adding another login design requires registering its key and template; authentication and form handling remain shared.

### Status labels

Labels pair text with a semantic soft background and never rely on hue alone. Use explicit Vietnamese state copy such as “Đã xác minh”, “Đang xử lý”, or “Cần xử lý”; do not substitute vague color-only dots. Success is not a generic positive accent—apply it only to completed or verified truth.

### Student route state machine

The student route has four ordered destinations: choose a mission, produce or revise content, submit for review, then receive points and track UGC performance. The route derives from submission status:

- `not_started` → 0%: mission choice is active; later review/reward steps stay locked.
- `rejected` → 25%: production/revision is active and must expose the correction action.
- `pending` → 75%: earlier stops are complete, review is active, and the submission link remains available.
- `approved` → 100%: all stops are complete and the outcome is explicitly recorded.

Completed markers are blue checks; the single current marker is cyan with an active ring; locked markers are cool gray. Exactly one step may be active. Never show a later reward as available before approval. Progress text, marker states, labels, accessible percentage, CTA, and locked copy must update together.

### Admin operations map

The admin board maps four parallel domains—campaigns, UGC submissions, content performance, and the ambassador community—through three ordered nodes each. Every row contains domain identity, handoff context, count-bearing nodes, directional connectors, and a route-specific destination. The middle node is the operational focus; “complete,” “active,” and default node treatments reflect query results, not decoration. On phones, nodes stack vertically with arrows rotated downward. The priority rail totals pending UGC and flagged conversations, and its CTA opens real work.

### Boards, rails, queues, and empty states

Boards and rails share clear surfaces, cool borders, and 12px corners. Route tables and work queues use dividers rather than nested cards. Preserve identity, state, and destination when reducing columns. Empty states must explain what is absent and the next useful expectation or action; they must not fabricate zero-state metrics.

### Accessibility and interaction

Keep semantic headings, landmarks, tables/lists, native controls, `aria-label` text, keyboard navigation, visible focus, and WCAG AA contrast. Touch targets remain at least 40–44px. JavaScript may enhance focus management, copying, chat, and scrolling, but core content and workflow state must remain visible without animation. When motion is reduced, do not replace lost animation with hidden or delayed content.

## Do's and Don'ts

### Do:

- **Do** start new operational screens with the user’s next decision, route, queue, or handoff—not an undifferentiated metric grid.
- **Do** reuse the token source in `assets/css/app.css`, shell contract in `includes/header.php` and `includes/sidebar.php`, and the existing state vocabulary before adding a variant.
- **Do** bind UI state to existing routes, role checks, labels, forms, and database queries; preserve Vietnamese copy unless product requirements explicitly change it.
- **Do** verify student and admin screens together at desktop and mobile widths, including long Vietnamese labels, empty data, keyboard focus, off-canvas behavior, and reduced motion.
- **Do** treat the approved composition image as design reference only; shipped UI uses semantic PHP/HTML, CSS, Bootstrap Icons, and real data.
- **Do** update this file when a durable token, radius, shell rule, breakpoint behavior, state transition, or canonical component changes.

### Don't:

- **Don't** reintroduce a generic dashboard made from equal-weight KPI cards or floating white tiles.
- **Don't** replace the dominant Route + Status Rail hierarchy with empty minimalism, ornamental illustration, or a search-heavy campus-map imitation.
- **Don't** add warm brand colors, gradients, glassmorphism, oversized radii, excessive shadows, decorative pills, or near-black surfaces.
- **Don't** use cyan for large fills, body text, or every active control; its scarcity is what makes the live signal legible.
- **Don't** communicate state through color alone, hide the primary workflow on mobile, or remove visible keyboard focus.
- **Don't** hard-code counts, percentages, dates, rewards, labels, or success states that can disagree with SQLite-backed truth.
- **Don't** add entrance choreography, pointer-following effects, continuous decorative animation, or transitions that survive reduced-motion preferences.
- **Don't** load older visual layers beside `assets/css/app.css`; one active token and component vocabulary must govern the shell.

**Maintenance workflow:** inspect the shipped component and its real states first; reuse existing tokens and component roles; implement desktop and structural mobile behavior together; verify data truth, keyboard/focus, and reduced motion; compare student and admin surfaces for vocabulary drift; then document only decisions that are durable across screens.
