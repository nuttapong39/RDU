# Doctor identity is masked in the web UI (stable anonymized code), never in exports

Since KPI01 was first built, doctor-rate charts showed the attending/prescribing doctor's real name +
license number unmasked, on the reasoning that PDPA masking in this project protects *patients*, and
doctors need to stay identifiable for quality-improvement feedback. A hospital-provided reference
dashboard (a pre-existing Google Sheets/Apps Script tool this Laravel project supersedes) instead
displays doctors as stable anonymized codes (`DR-01`, `DR-02`, ...) in its antibiotic-rate-by-doctor
view. We adopted this for the web UI only: showing a doctor's real name front-and-center in a
pass/fail-colored ranking reads as calling that person out; a stable code softens that while the
underlying data stays fully traceable.

We did **not** adopt full anonymization. The real name/license is still revealable via click/hover in
the same view — this table's whole purpose is surfacing which doctors need intervention, so a design
with no path back to a real identity would defeat it. Exports (Excel/CSV/PDF) are explicitly excluded
from this masking and continue to show real names: they're downloaded by already-authenticated staff
for a different purpose (offline reporting, records), and the "reduce at-a-glance stigma" rationale
for masking doesn't apply there.

## Consequences

- New mapping table in `rdu_dashboard` (`pgsql` connection — app data, not HOSxP) assigns each real
  doctor (keyed by HOSxP `doctor.code`) a code on first sight, once, forever. The mapping is global
  across all KPIs and time periods — the same doctor is always the same code everywhere, so trends
  for one doctor stay trackable across KPI pages and fiscal years.
- This reverses the "doctors are never masked" framing implicitly established at KPI01 (see the
  attending/prescribing-doctor glossary entries in `CONTEXT.md`) — that framing was about *data*
  never being masked from the system; this ADR narrows it to "not masked in exports or on click," not
  "never masked in any UI."
