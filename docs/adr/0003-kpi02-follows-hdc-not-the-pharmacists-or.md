# KPI02 follows HDC: injury AND external cause, with the pharmacist's own antibiotic list

KPI02 (ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ, CONTEXT.md "บาดแผลสดจากอุบัติเหตุ") came from a pharmacist's
reference query (git commit 0f565ab) that put a visit in the denominator when it had an injury-nature code **or**
an external-cause code. We changed it to require **both** in the same visit, like KPI01 (ADR 0001), because the
agreed rule for every KPI is to follow HDC: the OR version counted 2,401 visits in FY2569, almost twice HDC's
1,309. The AND version counts 1,437, and 865 of the dropped visits had a cause code but no injury code at all
(given an antibiotic in only 11.7% of cases). Agreed with the user 2026-10-17.

KPI02 also keeps the pharmacist's **own 28 antibiotic codes** instead of the 66-code `ANTIBIOTIC_ICODES` the other
antibiotic KPIs share (docs/KPI-REFERENCE-IMPLEMENTATION.md §3.2). The 28 are oral and injectable antibiotics only
and include Clindamycin, Levofloxacin and Nitrofurantoin. The shared list lacks those three, and includes eye and ear
preparations and non-antibiotics (Albendazole, Acyclovir).

## Consequences

- FY2569 750 / 1,437 (52.2%) against HDC 655 / 1,309 (50.0%). FY2568 801 / 1,516 against HDC 704 / 1,348. The
  remaining gap is on HDC's side (43-file submission) and we do not try to close it.
- KPI02 is **not** a subset of KPI01: its injury codes come from the pharmacist's list (e.g. it includes S05, eye
  injuries), not KPI01's.
- An edit to the shared 66-code antibiotic list does not apply to KPI02. Whether the shared list should drop its
  non-antibiotics and gain Clindamycin is a separate question for the pharmacist.

## Considered and rejected

- **Use the pharmacist's OR as written.** Rejected: the numbers could not be compared with HDC, and KPI02 would
  be larger than KPI01 even though KPI02 is KPI01 with some wound types removed.
- **Use KPI01's injury codes and its 66 antibiotics.** Rejected: the pharmacist defined KPI02's lists, and with
  KPI01's injury codes the result (56.9%) is further from HDC's 50.0%.
