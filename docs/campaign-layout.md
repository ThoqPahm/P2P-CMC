# Campaign card refinement

Preserve-mode refinement for `campaigns` and `admin-campaigns`, approved for both pages. Existing Bootstrap grid, CMC theme, full descriptions/briefs and form actions stay unchanged. No new dependency or JavaScript.

- Statistics use three equal columns, with values and labels on separate lines.
- Admin cards use flex layout to align metadata and actions within each row.
- Student platform headers are more compact; briefs retain full content and line breaks.
- Icon/text gaps, type sizes, line heights and footer spacing follow a shared rhythm.
- Text wraps without truncation; mobile actions can wrap and expand.
- CSS is loaded only on the two campaign routes, after shared styles.

Taste review: targeted typography/spacing refinement only (variance 3, motion 2, density 5). Dashboard layout remains Bootstrap rather than applying landing-page composition rules.

Validation: PHP header lint and diff whitespace check passed. Browser review covered both roles on desktop, student mobile at 390px, and opening the existing create-campaign/submission dialogs. Test data uses an isolated QA database; no real campaigns or submissions were created.
