# KPI01 requires both an injury-nature code and an external-cause code

KPI01's "บาดแผลจากอุบัติเหตุ" (accident wound) population was originally any visit with an ICD-10 code
matching a nature-of-injury list (S/T-type) **or** an external-cause list (V/W/X-type). A hospital
IT/reporting-provided reference query showed the standard ICD-10 practice of requiring **both** codes
together (a visit must have a nature-of-injury diagnosis *and* a separate external-cause diagnosis) —
we adopted this stricter definition (`AccidentWoundAntibioticQuery::injuryTypeExists()` AND
`externalCauseExists()`, see `docs/SQL_KPI/KPI1.sql`) because it's the clinically correct pairing, not
an arbitrary tightening.

## Consequences

Verified against live data for 2026-08-22–2026-09-21: the qualifying denominator dropped from 295 to
152 visits (51.5% of the previous count). This is a real, intentional shift in what KPI01 measures —
any trend comparison against numbers computed before this change (e.g. earlier exports, screenshots,
reports) is not apples-to-apples. We did not attempt to backfill or reconcile historical figures.

## Considered and rejected

- **Keep the "or" condition, just adopt the updated antibiotic icode list.** Rejected — the reference
  query's authors intentionally paired the two code groups; keeping "or" would silently diverge from
  the hospital's own standard again.
- **Adopt the reference query wholesale** (different columns, unmasked patient name, national ID
  exposed, different doctor/CC-HPI shape). Rejected — would break every KPI01 feature already built
  (attending-doctor rate chart, drug-usage chart, ICD-10 ranking) and violate the PDPA name-masking
  already agreed for this dashboard. Only the filtering logic and the antibiotic icode list were
  ported; the existing query structure, masking, and column shape were kept.
