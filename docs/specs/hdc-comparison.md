# Spec: ปุ่ม "เทียบ HDC" ในหน้า KPI

สถานะ: พร้อมให้ implement (ready-for-agent) · ร่างเมื่อ 2026-09-25
คำศัพท์โดเมนอ้างอิง `CONTEXT.md` (ข้อมูล HDC, จำนวนการใช้ยา, เป้าหมาย, สถานะ) และแบบแผน KPI ใน
`docs/KPI-REFERENCE-IMPLEMENTATION.md`

## Problem Statement

ทีมเภสัชกรรมและผู้บริหารของโรงพยาบาลเชียงกลางดูผลตัวชี้วัด RDU ในระบบนี้ ซึ่งคำนวณจาก HOSxP ของโรงพยาบาลโดยตรง
แต่ผลที่หน่วยงานเหนือใช้ประเมินคือ**ข้อมูล HDC** ของกระทรวงสาธารณสุข (MOPH Open Data) ตัวเลขสองฝั่งไม่เท่ากัน
เช่น KPI01 ปีงบ 2569 ฝั่ง HOSxP ได้ 1,017/1,758 (57.8%) แต่ HDC ได้ 923/1,670 (55.3%)

ตอนนี้ผู้ใช้ต้องเปิดเว็บ MOPH Open Data แยก หาตารางที่ตรงกับตัวชี้วัดเอง หาแถวของโรงพยาบาลจากรหัสหน่วยบริการ แล้วคิดเทียบเอง
จึงไม่รู้ทันทีว่าผลของตัวเองต่างจากส่วนกลางเท่าไร และไม่รู้ว่าอยู่ตรงไหนเมื่อเทียบกับโรงพยาบาลอื่นในจังหวัดน่าน

## Solution

ทุกหน้า KPI มีปุ่ม **"เทียบ HDC"** ที่มุมขวาบนของส่วนหัว กดแล้วเปิด modal บนหน้าเดิม แสดง:

1. **ตัวเลขของ รพ.เชียงกลางเทียบกัน** ระหว่างฝั่ง HOSxP (ระบบนี้) กับฝั่ง HDC ได้แก่ ตัวส่วน, ตัวตั้ง, ผลลัพธ์ %, ส่วนต่าง
   และวันที่ HDC ประมวลผลล่าสุด ทั้งสองฝั่งเป็น**ยอดรวมทั้งปีงบประมาณ**ที่เลือกอยู่บนหน้า
2. **ตารางโรงพยาบาลทุกแห่งในจังหวัดน่าน** จาก HDC เรียงตามผลลัพธ์ % ไฮไลต์แถวของ รพ.เชียงกลาง และมีค่าเฉลี่ยของจังหวัด

KPI ที่ยังไม่มีตาราง HDC คู่กัน ยังมีปุ่ม แต่กดแล้ว modal บอกว่า "ยังไม่มีข้อมูล HDC คู่กัน"
ถ้าเรียก HDC ไม่สำเร็จ modal แสดงข้อความพร้อมปุ่มลองใหม่ โดยหน้า KPI ยังใช้งานได้ปกติ

## User Stories

1. As a pharmacist, I want a "เทียบ HDC" button on every KPI page, so that I can check the ministry's figure without leaving the dashboard.
2. As a pharmacist, I want the button in the top-right corner of the KPI header, so that it is easy to find and does not disturb the charts.
3. As a user, I want the comparison to open in a modal, so that I keep my place on the KPI page and can close it to continue reading the charts.
4. As a pharmacist, I want to see our hospital's HOSxP denominator, numerator and result % next to the HDC denominator, numerator and result %, so that I can see how far apart they are.
5. As a pharmacist, I want the difference between the two results shown in percentage points, so that I do not have to calculate it.
6. As a pharmacist, I want to see the date HDC last processed the data, so that I know how fresh the ministry figure is.
7. As a user, I want a clear label that both sides are full fiscal-year totals, so that I do not mistake them for the quarter or month I have filtered on the page.
8. As a user, I want the HOSxP side to always cover all 12 months of the selected fiscal year even when I have filtered a quarter or month, so that the comparison with HDC is like-for-like.
9. As a user, I want the comparison to use the fiscal year currently selected on the KPI page, so that I can compare past years too.
10. As a manager, I want a table of every hospital in Nan province from HDC, so that I can see where our hospital ranks.
11. As a manager, I want that table sorted by result %, so that the best and worst hospitals stand out.
12. As a manager, I want our hospital's row highlighted in the table, so that I can find it immediately.
13. As a manager, I want the province average shown with the table, so that I can tell whether we are above or below the provincial level.
14. As a user, I want each hospital identified by district name and hospital code (e.g. "อ.เชียงกลาง (11179)"), so that I can recognise the hospital even though HDC does not send hospital names.
15. As a user, I want each row in the provincial table to show its denominator, numerator and result %, so that I can judge whether a hospital's rate rests on many or few cases.
16. As a user, I want the result status (pass / near target / fail) shown on the HDC figures using the KPI's own target and direction, so that I read them the same way as the rest of the dashboard.
17. As a user of KPI03, I want the HDC figures converted to the same direction as our KPI (visits that did NOT receive an antibiotic), so that the two sides are comparable.
18. As a user of KPI02, I want HDC's `s_rdu8` second column pair judged against KPI02's corrected target (≤ 40%), so that the comparison uses the right direction.
19. As a user of a KPI with no matching HDC table, I want the button to still be there and the modal to say that no HDC equivalent is linked yet, so that I understand it is not an error.
20. As a user, I want a loading state while the HDC data is being fetched, so that I know the modal is working.
21. As a user, I want a clear message and a "ลองใหม่" button if HDC cannot be reached, so that I can retry without reloading the page.
22. As a user, I want the KPI page to load at its normal speed, so that the external HDC service never slows or breaks the dashboard.
23. As a user, I want the HDC data to appear quickly when I reopen the modal, so that repeated checks are instant.
24. As a user, I want to close the modal with a close button, the Escape key or a click outside it, so that it behaves like other dialogs.
25. As a keyboard or screen-reader user, I want the button and modal to be accessible (labelled button, focus moved into the modal and returned to the button on close), so that I can use the feature without a mouse.
26. As a user on a small screen, I want the modal and the provincial table to fit the screen width, with the table scrolling if needed, so that I can use it on a tablet.
27. As every logged-in user, I want to see the button, since HDC data is public aggregate data with no patient-level information.
28. As an administrator, I want the hospital code and province code to be configurable, so that they can be corrected without code changes.
29. As a user, I want a short note in the modal explaining why the two sides can differ (different definitions, data source and cut-off date), so that I do not assume one side is wrong.
30. As a developer adding a new KPI, I want linking the KPI to its HDC table to be part of the KPI reference checklist, so that new KPIs get the comparison too.

## Implementation Decisions

- **Modal, not a page.** The comparison opens in a modal on the existing KPI page. The modal fetches its data only when opened (lazy), via a new JSON endpoint on this application, scoped to the KPI code and fiscal year. The KPI page render is unchanged apart from the button and modal shell.
- **New external-data module, separate from the HOSxP path.** A new service talks to the MOPH Open Data API. It is **not** part of `RduQueryService`, which stays the single gateway for HOSxP only (CLAUDE.md rule). The HOSxP side of the comparison is still obtained through `RduQueryService` (summing the 12 fiscal months), never by querying HOSxP from a controller.
- **HDC API contract** (verified live on 2026-09-25, no authentication):
  - Data: `POST https://opendata.moph.go.th/api/report_data` with JSON body `{"tableName": <table>, "year": <fiscal year พ.ศ.>, "province": <2-digit province code>, "type": "json"}` → `{ "data": [ …rows ], "total", "limit", "offset" }`. One row per hospital in the province with at least `hospcode`, `areacode`, `date_com` (processing timestamp `YYYYMMDDhhmm`), `b_year`, and the table's count columns.
  - Metadata (optional, for column meanings): `GET …/api/report_schema/<table>`, `GET …/api/report_year/<table>`.
  - HDC returns only hospital codes and area codes, no hospital names.
- **KPI ↔ HDC mapping** (a single mapping owned by the new module):
  - KPI01 → `s_rdu8`, columns `target` (denominator) / `result` (numerator: visits that received an antibiotic)
  - KPI02 → `s_rdu8`, `target1` / `result1` (linked 2026-09-25 after the seed was corrected to lower, 40%)
  - KPI03 → `s_rdu9`, `target` / `result`; HDC `result` counts deliveries that **received** an antibiotic, so the HDC numerator shown is `target − result` to match KPI03's direction (not received)
  - KPI04 → `s_rdu6`, `target` / `result`
  - KPI05 → `s_rdu7`, `target` / `result`
  - KPI08 → `s_rdu15` (asthma, ICS), KPI09 → `s_rdu14` (CKD stage ≥ 3, NSAIDs), KPI10 → `s_rdu12` (metformin), KPI13 → `s_rdu16` (elderly, long-acting benzodiazepine) — linked 2026-09-25 together with corrected seed names/targets
  - KPI06, KPI07, KPI11, KPI12 → not linked (the modal shows the "no HDC equivalent yet" state); no HDC table with a matching definition was found
- **HDC as a stand-in (added 2026-09-25).** A KPI whose HOSxP query is not implemented yet (not listed in `rdu.hosxp_kpis`) but is linked to an HDC table shows our hospital's HDC whole-year figure everywhere the KPI appears (KPI page, overview card, sidebar), labelled as HDC. It is always the whole-year figure, with a note when a quarter or month is selected; per-quarter cards, the visit donut and the quarterly split stay empty. A KPI listed in `rdu.hosxp_kpis` always uses HOSxP. When HDC is unreachable, the KPI shows "no data" with a note, and HDC is not retried for 5 minutes on dashboard pages (the modal still retries on demand).
- **Configuration.** Hospital code (default `11179`, confirmed by the user) and province code (default `55`, Nan) come from environment config, not code.
- **District labels.** Hospitals are labelled "อ.<district> (<hospcode>)". The district name comes from the first 4 digits of `areacode` via a fixed map of Nan's 15 district codes (5501 เมืองน่าน … 5515 เฉลิมพระเกียรติ). An unknown code falls back to the hospital code alone.
- **Caching.** HDC responses are cached server-side per (table, fiscal year, province) for 6 hours (HDC processes daily). Failed calls are not cached. The request uses a bounded timeout so a slow HDC fails fast.
- **Error contract of the new endpoint.** On success it returns the comparison (our hospital's HOSxP and HDC figures, difference, HDC processing date, provincial rows, province average) and whether the KPI is linked. When HDC fails, it returns an error state the modal can show with a retry, never a raw exception or the upstream body.
- **Status on HDC figures** uses the existing status rules (target, direction, near-target band) so HDC rows are judged exactly like the rest of the dashboard. Province average = total numerator ÷ total denominator across rows (weighted), not the mean of percentages.
- **Access.** The button and endpoint are available to every authenticated user (no settings-admin gate).
- **Docs.** Add a "link the HDC table" step to the checklist in the KPI reference implementation document.

## Testing Decisions

- **Good tests check external behaviour only:** what the endpoint returns for a given KPI, year and HDC response, and what the page shows. They do not test private helpers, cache keys or HTTP client internals.
- **Primary seam: one feature test of the new comparison endpoint**, with the HDC API faked by the HTTP client's fake and the HOSxP side supplied by the mock data source already used in testing. It covers:
  - KPI01: correct HOSxP vs HDC numbers, difference and HDC date for hospital 11179, and provincial rows sorted by %, with our row flagged and the weighted province average
  - KPI03: HDC numerator converted to "not received"
  - KPI02: uses the second column pair and has no HOSxP side
  - A KPI without a HOSxP query shows its HDC whole-year figure on its page and the overview, never spread over quarter cards; a KPI with a HOSxP query ignores HDC
  - An unlinked KPI (e.g. KPI06): the "not linked" state, and HDC is never called
  - HDC timeout or HTTP error: the error state, not a 500
  - A second request is served from cache (the HDC fake is hit once)
  - A guest is redirected to login
- **Secondary check:** the KPI page renders the button for every KPI code (extend the existing KPI page render assertions).
- **Prior art:** the existing feature tests of authenticated pages and settings (e.g. the font settings and security tests), which use the model factories and route assertions. The local test suite currently cannot run on this machine (missing PHP `intl` extension), so behaviour is also verified manually against the live HDC API via tinker, using the figures captured on 2026-09-25 (KPI01 HDC 923/1,670; KPI04 425/2,186; KPI05 94/440; KPI03 0/0 for hospital 11179, FY2569).

## Out of Scope

- Monthly or quarterly HDC comparison (HDC publishes fiscal-year totals only)
- Linking KPI06, KPI07, KPI11, KPI12 to HDC tables (no matching table found)
- Storing HDC history in the application database, or a scheduled sync job
- Full hospital names (HDC does not provide them; districts are used instead)
- Comparing with provinces other than the configured one, or a national view
- Exporting the comparison (CSV/Excel/PDF)
- Changing how the dashboard's own KPIs are calculated to match HDC definitions

## Further Notes

- The two sides are expected to differ: HDC uses the ministry's standard definitions over the 43-file data submitted by hospitals, while this system uses the hospital's own SQL over HOSxP, and the cut-off dates differ. The modal should say so briefly (see `CONTEXT.md` → ข้อมูล HDC).
- The application server must be able to reach `opendata.moph.go.th` over HTTPS. This was confirmed from the development machine; the production server should be checked.
- The HDC API is undocumented and could change without notice. Keeping all knowledge of it (URLs, table names, column names, direction conversion) inside the one new module limits the impact of such a change.
