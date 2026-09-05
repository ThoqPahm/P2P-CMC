# Moderation layout refinement

Preserve-mode update using existing Bootstrap and CMC tokens (Taste: variance 3, motion 2, density 5). This is an admin workflow, so landing-page layout rules are not applied.

Audit found missing layout rules for sender/time metadata, quality/status controls and the safety note, plus oversized list headings and nested panel borders. The dedicated route stylesheet repairs these without affecting the student inbox or changing moderation decisions.

- Aligned list rows, active indicator and accessible flagged counts.
- Sender/time spacing, line-break preservation and wrapping for long messages.
- Explicit flagged state, subtle ambassador alignment, unchanged form endpoints.
- Quality/status toolbar and readable safety footer.
- Responsive single column, bounded mobile list and wrapping escalation controls.
- Empty list/message/selection states; quality bar visual width clamped.

Verification: render-only PHP fixtures cover empty states, escaping, flagged metadata and retained form targets. No real messages are hidden, restored or submitted during visual checks.
