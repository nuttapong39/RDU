# Drug code sets are configured per hospital, and a new hospital starts with none

The drug codes each KPI counts (CONTEXT.md "ชุดรหัสยา") used to be class constants in the query classes, such as the
66-code `ANTIBIOTIC_ICODES` copied into KPI01/03/04/05 and KPI02's own 28 codes (ADR 0003). The system is being shared
with other hospitals, and HOSxP `icode`s are local to each hospital: the same code can be a different drug elsewhere.
So the sets are now stored in `rdu_dashboard`, and the hospital's own pharmacists (CONTEXT.md "ผู้ตั้งค่ารหัสยา") edit
them on a settings page. A new install starts with **every set empty**, and each KPI shows "ยังไม่ตั้งค่า" until its
sets are filled. Chiang Klang's sets are moved over once, unchanged, by an import command run on its own server.
Agreed with the user 2026-11-10.

Which set a KPI uses stays in code, as part of the KPI's definition. For example, the antibiotic set serves
KPI01/03/04/05, KPI02 has its own set, and KPI10 has both a Metformin set and a glucose-lowering set. Pharmacists edit
what is in a set, not which set a KPI reads. ICD-10 codes and the age and eGFR thresholds stay in code, because they
are national definitions. The eGFR lab code is configured, because `lab_items_code` is local too.

## Consequences

- Editing a set re-counts every period with the current codes, including past ones, as a target change does. Cached
  counts and rows in `rdu_indicator_results` computed before the edit are no longer used. They are not deleted. The
  one-time Chiang Klang import is the baseline, so rows calculated before it stay valid: it changes no number.
- The import keeps Chiang Klang's sets exactly as they were, including the questionable entries in the antibiotic
  set (Budesonide 1483852, Albendazole, Acyclovir, eye and ear preparations). The pharmacist cleans them up later
  through the settings page, so changing the system and changing the numbers happen at different times.
- Tests can no longer rely on the constants. They seed the sets they need.

## Considered and rejected

- **Ship Chiang Klang's sets as every hospital's default.** Rejected: at another hospital those codes are other
  drugs or no drug at all. The dashboard would count the wrong drugs and show plausible numbers, with no sign that
  anything is wrong. An empty, visibly "not configured" KPI is safer.
- **A config file per hospital in the repo.** Rejected: each change would need a developer and a deploy, and the
  pharmacists who own the lists could not edit them.
- **Let pharmacists choose which set each KPI uses.** Rejected: that would let the KPI definition be changed from
  the screen. For example, KPI02 could be pointed back at the shared antibiotic set, against ADR 0003.
