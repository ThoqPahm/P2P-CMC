# Widget administration layout

Scope: `admin-widget` only. Taste preserve-mode for a Bootstrap admin workspace: variance 3, motion 2, density 5. Keep CMC tokens and the existing embedded widget unchanged.

The Chapter 4 screenshot review (PDF pages 19–20, printed pages 75–76) shows the public widget/demo, campaigns, overview and moderation. It does not show this integration administration page. The demo modal markup and its CSS remain unchanged.

- Compact page heading and a dedicated embed-code toolbar.
- Integration instructions and local-host URL warning.
- Replace the decorative website mockup with real online/pending counts and a link to the schedule queue; remove the hard-coded filter count and unverified “active” claim.
- Full appointment questions available through native disclosure, separate contact/time lines and accessible status controls. Existing query limit and mutation endpoints remain unchanged.
- Responsive layout and route-only stylesheet; no new JavaScript dependencies.

Validation: 15 render-only fixture checks cover escaping, empty schedules, deployment subpaths, original embed configuration, iframe URL and status fields. Browser checks cover copying, guide expansion and opening/closing the unchanged demo. No real appointment statuses were submitted.
