// Drug code settings page (CONTEXT.md: ชุดรหัสยา). Left = the hospital's drug list from HOSxP (read-only),
// right = the set a KPI counts with. Dragging only edits a draft in the browser; "บันทึก" saves it.
import Sortable from 'sortablejs';
import Swal from 'sweetalert2';

const root = document.querySelector('[data-drug-code-editor]');

if (root) {
    init(root);
}

function init(root) {
    const cfg = JSON.parse(document.getElementById('drug-code-config').textContent);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const FORMS = ['ยาเม็ด', 'ยาน้ำ', 'ยาฉีด'];
    const MAX_ROWS = 100; // rows drawn on the left at once; the search narrows it

    const toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true,
    });

    const state = {
        kpi: (cfg.kpis.find((k) => !k.configured) || cfg.kpis[0]).code,
        catalog: null, // icode => drug row, loaded once
        sets: {},
        filters: {}, // per set: {q, antibiotic, inactive}
        picked: {}, // per set: Set of icodes ticked on the left
    };

    Object.values(cfg.sets).forEach((s) => {
        state.sets[s.key] = { ...s, saved: clone(s.items), draft: clone(s.items), saving: false };
        state.filters[s.key] = { q: '', antibiotic: false, inactive: false, labResults: [] };
        state.picked[s.key] = new Set();
    });

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function clone(v) { return JSON.parse(JSON.stringify(v)); }
    const isDirty = (s) => JSON.stringify(s.saved) !== JSON.stringify(s.draft);
    const kpiConfigured = (kpi) => kpi.sets.every((key) => state.sets[key].saved.length > 0);
    const anyDirty = () => Object.values(state.sets).some(isDirty);

    window.addEventListener('beforeunload', (e) => {
        if (anyDirty()) { e.preventDefault(); e.returnValue = ''; }
    });

    // ---------- tabs ----------
    const tabs = root.querySelector('[data-tabs]');
    const body = root.querySelector('[data-body]');

    function renderTabs() {
        tabs.innerHTML = cfg.kpis.map((k) => {
            const ok = kpiConfigured(k);
            const dirty = k.sets.some((key) => isDirty(state.sets[key]));
            const active = k.code === state.kpi;
            const off = !k.active; // switched off for this hospital
            return `<button type="button" role="tab" aria-selected="${active}" data-kpi="${k.code}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
                ${active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'} ${off ? 'opacity-60' : ''}">
                <span class="w-2 h-2 rounded-full ${ok ? 'bg-emerald-500' : 'bg-slate-300'}" aria-hidden="true"></span>
                ${esc(k.code)}${off ? '<span class="text-xs font-normal">(ปิด)</span>' : ''}${dirty ? '<span class="text-amber-400" title="มีการแก้ไขที่ยังไม่บันทึก">●</span>' : ''}
            </button>`;
        }).join('');
    }

    tabs.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-kpi]');
        if (!btn) return;
        state.kpi = btn.dataset.kpi;
        renderTabs();
        renderBody();
    });

    // ---------- catalogue ----------
    async function loadCatalog() {
        if (state.catalog) return state.catalog;
        const res = await fetch(cfg.urls.catalog, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('catalog');
        const { drugs } = await res.json();
        state.catalog = Object.fromEntries(drugs.map((d) => [d.icode, d]));

        return state.catalog;
    }

    const drugTitle = (d) => `${d.name}${d.strength ? ' ' + d.strength : ''}`;

    function defaultAttribute(set, drug) {
        if (!drug) return '';
        if (set.attribute === 'ingredient') return (drug.generic_name || '').split(/[\s,(]/)[0].toLowerCase();
        if (set.attribute === 'form') {
            const f = `${drug.dosageform} ${drug.units}`.toLowerCase();
            if (/inj|ฉีด|amp|vial/.test(f)) return 'ยาฉีด';
            if (/syr|sol|susp|drop|น้ำ|ml/.test(f)) return 'ยาน้ำ';
            if (/tab|cap|เม็ด|แคป/.test(f)) return 'ยาเม็ด';
        }

        return '';
    }

    // ---------- body ----------
    function renderBody() {
        const kpi = cfg.kpis.find((k) => k.code === state.kpi);
        body.innerHTML = `
            <div class="mb-3">
                <div class="flex flex-wrap items-start gap-3">
                    <h2 class="text-base font-bold text-slate-800 flex-1 min-w-0">${esc(kpi.code)} — ${esc(kpi.name)}</h2>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none shrink-0">
                        <span>ใช้งานตัวชี้วัดนี้</span>
                        <input type="checkbox" role="switch" data-kpi-active ${kpi.active ? 'checked' : ''} class="h-5 w-9 cursor-pointer accent-indigo-600" aria-label="ใช้งาน ${esc(kpi.code)}">
                    </label>
                </div>
                ${kpi.active ? '' : '<p class="mt-1 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 inline-block">ปิดใช้งานอยู่ — ไม่แสดงในภาพรวม เมนู และหน้าล็อกอิน (ชุดรหัสยาและผลที่เคยคำนวณยังอยู่)</p>'}
                <p class="text-xs text-slate-500 mt-0.5">ลากรายการจากฝั่งซ้าย (ฐานข้อมูล HOSxP — อ่านอย่างเดียว) ไปวางฝั่งขวา แล้วกด “บันทึก” ระบบจะใช้ชุดนี้นับตัวชี้วัด</p>
            </div>
            <div class="space-y-5">${kpi.sets.map((key) => setSkeleton(state.sets[key])).join('')}</div>`;

        kpi.sets.forEach((key) => mountSet(state.sets[key]));

        body.querySelector('[data-kpi-active]').addEventListener('change', (e) => setKpiActive(kpi, e.target));
    }

    async function setKpiActive(kpi, input) {
        const want = input.checked;
        input.disabled = true;
        try {
            const res = await fetch(cfg.urls.kpiActive.replace('__KPI__', kpi.code), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ active: want }),
            });
            if (!res.ok) throw new Error('save');
            kpi.active = want;
            toast.fire({ icon: 'success', title: (await res.json()).message });
            renderTabs();
            renderBody();
        } catch {
            input.checked = !want;
            input.disabled = false;
            Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: 'ลองใหม่อีกครั้ง', confirmButtonText: 'ตกลง' });
        }
    }

    function setSkeleton(s) {
        const shared = s.kpis.length > 1
            ? `<span class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5">ใช้ร่วมกับ ${esc(s.kpis.filter((c) => c !== state.kpi).join(', '))} — แก้ที่นี่เปลี่ยนทุกตัว</span>`
            : '';
        const isLab = s.kind === 'lab';

        return `
        <section data-set="${s.key}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5" aria-label="${esc(s.label)}">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <h3 class="text-sm font-bold text-slate-800">${esc(s.label)}</h3>
                ${shared}
                <span data-dirty class="hidden text-xs font-semibold text-amber-600">มีการแก้ไขที่ยังไม่บันทึก</span>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" data-reset class="px-3 py-1.5 rounded-lg text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 cursor-pointer disabled:opacity-40 disabled:cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">ยกเลิกการแก้ไข</button>
                    <button type="button" data-save class="px-4 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 cursor-pointer disabled:opacity-40 disabled:cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">บันทึก</button>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-slate-600 mb-1.5">${isLab ? 'ค้นหารายการ Lab ใน HOSxP (อ่านอย่างเดียว)' : 'รายการยาใน HOSxP (อ่านอย่างเดียว)'}</div>
                    <input type="search" data-search autocomplete="off" placeholder="${isLab ? 'พิมพ์ชื่อหรือรหัส lab อย่างน้อย 2 ตัวอักษร' : 'ค้นหา รหัส / ชื่อยา / ชื่อสามัญ / ชื่อการค้า'}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="ค้นหา">
                    ${isLab ? '' : `<div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-600">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" data-f-antibiotic> เฉพาะยาปฏิชีวนะ</label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" data-f-inactive> แสดงยาที่ยกเลิกแล้ว</label>
                    </div>`}
                    <ul data-left class="mt-2 h-80 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100 bg-slate-50/50" aria-label="รายการใน HOSxP"></ul>
                    <div class="flex items-center justify-between mt-1.5 text-xs text-slate-500">
                        <span data-left-note></span>
                        ${isLab ? '' : '<button type="button" data-add-picked class="px-2.5 py-1 rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 cursor-pointer disabled:opacity-40 disabled:cursor-default">เพิ่มที่เลือก</button>'}
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-slate-600 mb-1.5">ชุดที่ใช้นับ <span data-count class="tabular-nums"></span></div>
                    <ul data-right class="min-h-[8rem] max-h-[26rem] overflow-y-auto rounded-lg border-2 border-dashed border-slate-300 p-1.5 space-y-1.5 bg-white" aria-label="ชุดที่ใช้นับ"></ul>
                    <p data-empty class="hidden text-xs text-slate-500 mt-1.5">ยังไม่มีรายการ — ลากจากฝั่งซ้ายมาวางที่นี่</p>
                </div>
            </div>
        </section>`;
    }

    function mountSet(s) {
        const sec = body.querySelector(`[data-set="${s.key}"]`);
        const f = state.filters[s.key];

        sec.querySelector('[data-search]').addEventListener('input', (e) => {
            f.q = e.target.value;
            if (s.kind === 'lab') debouncedLab(s); else renderLeft(s);
        });
        sec.querySelector('[data-f-antibiotic]')?.addEventListener('change', (e) => { f.antibiotic = e.target.checked; renderLeft(s); });
        sec.querySelector('[data-f-inactive]')?.addEventListener('change', (e) => { f.inactive = e.target.checked; renderLeft(s); });
        sec.querySelector('[data-save]').addEventListener('click', () => save(s));
        sec.querySelector('[data-reset]').addEventListener('click', () => { s.draft = clone(s.saved); refreshSet(s); });
        sec.querySelector('[data-add-picked]')?.addEventListener('click', () => {
            [...state.picked[s.key]].forEach((code) => addItem(s, code));
            state.picked[s.key].clear();
            refreshSet(s);
        });

        // left: click "+" or tick a row; right: remove / edit attribute
        sec.querySelector('[data-left]').addEventListener('click', (e) => {
            const add = e.target.closest('[data-add]');
            if (add) {
                if (s.kind === 'lab') { s.draft = [{ code: add.dataset.add, attribute: null, label: add.dataset.label }]; refreshSet(s); return; }
                addItem(s, add.dataset.add); refreshSet(s);
            }
        });
        sec.querySelector('[data-left]').addEventListener('change', (e) => {
            const cb = e.target.closest('[data-pick]');
            if (!cb) return;
            cb.checked ? state.picked[s.key].add(cb.dataset.pick) : state.picked[s.key].delete(cb.dataset.pick);
            updatePickedButton(s);
        });
        sec.querySelector('[data-right]').addEventListener('click', (e) => {
            const rm = e.target.closest('[data-remove]');
            if (!rm) return;
            s.draft = s.draft.filter((i) => i.code !== rm.dataset.remove);
            refreshSet(s);
        });
        sec.querySelector('[data-right]').addEventListener('input', (e) => {
            const input = e.target.closest('[data-attr]');
            if (!input) return;
            const item = s.draft.find((i) => i.code === input.dataset.attr);
            if (item) { item.attribute = input.value; refreshHeader(s); renderTabs(); }
        });

        if (s.kind === 'drug') {
            sec.querySelector('[data-left]').innerHTML = '<li class="p-3 text-sm text-slate-500">กำลังโหลดรายการยาจาก HOSxP…</li>';
            loadCatalog().then(() => refreshSet(s)).catch(() => {
                sec.querySelector('[data-left]').innerHTML = '<li class="p-3 text-sm text-red-600">โหลดรายการยาจาก HOSxP ไม่สำเร็จ ลองรีเฟรชหน้า</li>';
            });
            mountSortable(s, sec);
        } else {
            labLabelFor(s);
        }
        refreshSet(s);
    }

    function mountSortable(s, sec) {
        const group = `set-${s.key}`;
        Sortable.create(sec.querySelector('[data-left]'), {
            group: { name: group, pull: 'clone', put: false }, sort: false, animation: 150, filter: 'input,button', preventOnFilter: false,
        });
        Sortable.create(sec.querySelector('[data-right]'), {
            group, animation: 150, handle: '[data-handle]', ghostClass: 'opacity-40',
            onAdd(evt) { // dropped from the left
                const code = evt.item.dataset.code;
                evt.item.remove();
                addItem(s, code, evt.newIndex);
                refreshSet(s);
            },
            onUpdate() { // reordered
                const order = [...sec.querySelectorAll('[data-right] > li[data-code]')].map((li) => li.dataset.code);
                s.draft.sort((a, b) => order.indexOf(a.code) - order.indexOf(b.code));
                refreshHeader(s);
            },
        });
    }

    function addItem(s, code, index) {
        if (s.draft.some((i) => i.code === code)) { toast.fire({ icon: 'info', title: 'รายการนี้อยู่ในชุดแล้ว' }); return; }
        if (s.max && s.draft.length >= s.max) { toast.fire({ icon: 'warning', title: `ชุดนี้เลือกได้ไม่เกิน ${s.max} รายการ` }); return; }
        const item = { code, attribute: s.attribute ? defaultAttribute(s, state.catalog?.[code]) : null };
        index === undefined ? s.draft.push(item) : s.draft.splice(index, 0, item);
    }

    // ---------- rendering of one set ----------
    function refreshSet(s) {
        renderLeft(s);
        renderRight(s);
        refreshHeader(s);
        renderTabs();
    }

    function refreshHeader(s) {
        const sec = body.querySelector(`[data-set="${s.key}"]`);
        if (!sec) return;
        const dirty = isDirty(s);
        sec.querySelector('[data-dirty]').classList.toggle('hidden', !dirty);
        sec.querySelector('[data-save]').disabled = !dirty || s.saving;
        sec.querySelector('[data-reset]').disabled = !dirty || s.saving;
        sec.querySelector('[data-count]').textContent = `(${s.draft.length}${s.max ? '/' + s.max : ''} รายการ)`;
        sec.querySelector('[data-empty]').classList.toggle('hidden', s.draft.length > 0);
    }

    function updatePickedButton(s) {
        const btn = body.querySelector(`[data-set="${s.key}"] [data-add-picked]`);
        if (!btn) return;
        const n = state.picked[s.key].size;
        btn.disabled = n === 0;
        btn.textContent = n ? `เพิ่มที่เลือก (${n})` : 'เพิ่มที่เลือก';
    }

    function renderLeft(s) {
        const sec = body.querySelector(`[data-set="${s.key}"]`);
        const ul = sec.querySelector('[data-left]');
        const note = sec.querySelector('[data-left-note]');
        const f = state.filters[s.key];

        if (s.kind === 'lab') {
            const q = f.q.trim();
            ul.innerHTML = q.length < 2
                ? '<li class="p-3 text-sm text-slate-500">พิมพ์เพื่อค้นหา เช่น “egfr”</li>'
                : (f.labResults.length ? f.labResults.map((r) => `
                    <li class="flex items-center gap-2 px-2.5 py-2 text-sm">
                        <div class="min-w-0 flex-1"><div class="truncate text-slate-800">${esc(r.name)}</div><div class="text-xs text-slate-500">รหัส ${esc(r.code)}${r.unit ? ' · ' + esc(r.unit) : ''}</div></div>
                        <button type="button" data-add="${esc(r.code)}" data-label="${esc(r.name)}" class="shrink-0 px-2.5 py-1 rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 cursor-pointer">เลือก</button>
                    </li>`).join('') : '<li class="p-3 text-sm text-slate-500">ไม่พบรายการ</li>');
            note.textContent = '';

            return;
        }

        if (!state.catalog) return;
        const inSet = new Set(s.draft.map((i) => i.code));
        const q = f.q.trim().toLowerCase();
        const rows = Object.values(state.catalog).filter((d) => {
            if (inSet.has(d.icode)) return false;
            if (!f.inactive && !d.active) return false;
            if (f.antibiotic && !d.antibiotic) return false;
            if (!q) return true;
            return [d.icode, d.name, d.generic_name, d.trade_name].some((v) => v.toLowerCase().includes(q));
        });
        const shown = rows.slice(0, MAX_ROWS);

        ul.innerHTML = shown.length ? shown.map((d) => `
            <li data-code="${esc(d.icode)}" class="flex items-center gap-2 px-2.5 py-2 text-sm bg-white cursor-grab ${d.active ? '' : 'opacity-60'}">
                <input type="checkbox" data-pick="${esc(d.icode)}" ${state.picked[s.key].has(d.icode) ? 'checked' : ''} aria-label="เลือก ${esc(d.name)}">
                <div class="min-w-0 flex-1">
                    <div class="truncate text-slate-800">${esc(drugTitle(d))}${d.active ? '' : ' <span class="text-xs text-slate-500">(ยกเลิกแล้ว)</span>'}</div>
                    <div class="text-xs text-slate-500 truncate">${esc(d.icode)}${d.generic_name ? ' · ' + esc(d.generic_name) : ''}${d.dosageform ? ' · ' + esc(d.dosageform) : ''}</div>
                </div>
                <button type="button" data-add="${esc(d.icode)}" class="shrink-0 w-7 h-7 rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 cursor-pointer" aria-label="เพิ่ม ${esc(d.name)}">+</button>
            </li>`).join('') : '<li class="p-3 text-sm text-slate-500">ไม่พบรายการที่ตรงกัน</li>';
        note.textContent = rows.length > MAX_ROWS ? `แสดง ${MAX_ROWS} จาก ${rows.length.toLocaleString()} รายการ — พิมพ์ค้นหาเพื่อกรอง` : `${rows.length.toLocaleString()} รายการ`;
        updatePickedButton(s);
    }

    function renderRight(s) {
        const ul = body.querySelector(`[data-set="${s.key}"] [data-right]`);
        ul.innerHTML = s.draft.map((i) => {
            const d = state.catalog?.[i.code];
            const title = d ? drugTitle(d) : (i.label || `รหัส ${i.code}`);
            const sub = d ? `${i.code}${d.generic_name ? ' · ' + d.generic_name : ''}` : i.code;
            const missing = s.attribute && !String(i.attribute ?? '').trim();

            return `<li data-code="${esc(i.code)}" class="flex flex-wrap items-center gap-2 px-2 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-sm">
                ${s.kind === 'drug' ? '<span data-handle class="cursor-grab text-slate-400 select-none px-1" aria-hidden="true" title="ลากเพื่อเรียงลำดับ">⋮⋮</span>' : ''}
                <div class="min-w-0 flex-1"><div class="truncate text-slate-800">${esc(title)}</div><div class="text-xs text-slate-500 truncate">${esc(sub)}</div></div>
                ${attributeField(s, i, missing)}
                <button type="button" data-remove="${esc(i.code)}" class="shrink-0 w-7 h-7 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 cursor-pointer" aria-label="เอา ${esc(title)} ออก">×</button>
            </li>`;
        }).join('');
    }

    function attributeField(s, i, missing) {
        if (!s.attribute) return '';
        const ring = missing ? 'border-red-400' : 'border-slate-300';
        if (s.attribute === 'form') {
            const opts = FORMS.includes(i.attribute) || !i.attribute ? FORMS : [...FORMS, i.attribute];

            return `<select data-attr="${esc(i.code)}" aria-label="รูปแบบยา" class="rounded-lg border ${ring} px-2 py-1 text-sm bg-white">
                <option value="">เลือกรูปแบบ</option>${opts.map((o) => `<option ${o === i.attribute ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
        }

        return `<input data-attr="${esc(i.code)}" value="${esc(i.attribute ?? '')}" maxlength="60" placeholder="ชื่อตัวยา" aria-label="ตัวยา"
            class="w-32 rounded-lg border ${ring} px-2 py-1 text-sm">`;
    }

    // ---------- lab search ----------
    let labTimer;
    function debouncedLab(s) {
        clearTimeout(labTimer);
        const f = state.filters[s.key];
        if (f.q.trim().length < 2) { f.labResults = []; renderLeft(s); return; }
        labTimer = setTimeout(async () => {
            const res = await fetch(`${cfg.urls.labItems}?q=${encodeURIComponent(f.q.trim())}`, { headers: { Accept: 'application/json' } });
            f.labResults = res.ok ? (await res.json()).items : [];
            renderLeft(s);
        }, 300);
    }

    async function labLabelFor(s) { // name of an already-saved lab item
        for (const item of s.draft) {
            if (item.label) continue;
            const res = await fetch(`${cfg.urls.labItems}?q=${encodeURIComponent(item.code)}`, { headers: { Accept: 'application/json' } });
            const hit = res.ok ? (await res.json()).items.find((r) => r.code === item.code) : null;
            if (hit) { item.label = hit.name; s.saved.find((i) => i.code === item.code).label = hit.name; renderRight(s); }
        }
    }

    // ---------- save ----------
    async function save(s, confirmEmpty = false) {
        if (s.saving || !isDirty(s)) return;

        if (s.attribute && s.draft.some((i) => !String(i.attribute ?? '').trim())) {
            Swal.fire({ icon: 'warning', title: 'ยังระบุไม่ครบ', text: `ต้องระบุ${s.attribute === 'form' ? 'รูปแบบยา' : 'ตัวยา'}ให้ครบทุกรายการก่อนบันทึก`, confirmButtonText: 'ตกลง' });
            return;
        }

        if (s.draft.length === 0 && !confirmEmpty) {
            const users = s.kpis.join(', ');
            const r = await Swal.fire({
                icon: 'warning', title: 'บันทึกชุดว่าง?', showCancelButton: true, confirmButtonText: 'ยืนยัน บันทึกชุดว่าง', cancelButtonText: 'ยกเลิก',
                text: `ตัวชี้วัด ${users} จะกลับเป็น “ยังไม่ตั้งค่า” และไม่แสดงผลจนกว่าจะเลือกรายการใหม่`,
            });
            if (!r.isConfirmed) return;
            confirmEmpty = true;
        }

        s.saving = true; refreshHeader(s);
        try {
            const res = await fetch(cfg.urls.update.replace('__SET__', s.key), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ version: s.version, confirm_empty: confirmEmpty, items: s.draft.map(({ code, attribute }) => ({ code, attribute })) }),
            });
            const json = await res.json().catch(() => ({}));

            if (res.ok) {
                s.version = json.version; s.saved = clone(s.draft);
                const overlaps = Object.entries(json.overlaps || {});
                toast.fire({
                    icon: 'success',
                    title: json.message || 'บันทึกแล้ว',
                    // a warning, not an error: the same drug may be in several sets on purpose
                    text: overlaps.length ? `ยา ${overlaps.length} รายการอยู่ในชุดอื่นด้วย (${[...new Set(overlaps.flatMap(([, v]) => v))].join(', ')})` : undefined,
                    timer: overlaps.length ? 7000 : 3500,
                });
            } else if (res.status === 409) {
                await Swal.fire({ icon: 'error', title: 'มีผู้อื่นแก้ไขชุดนี้แล้ว', text: json.message, confirmButtonText: 'โหลดหน้าใหม่', allowOutsideClick: false });
                state.sets[s.key].saved = state.sets[s.key].draft; // let the reload through without the unsaved-changes prompt
                location.reload();
            } else {
                const first = json.errors ? Object.values(json.errors)[0][0] : null;
                Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: first || json.message || 'เกิดข้อผิดพลาด ลองใหม่อีกครั้ง', confirmButtonText: 'ตกลง' });
            }
        } catch {
            Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่อีกครั้ง', confirmButtonText: 'ตกลง' });
        } finally {
            s.saving = false;
            refreshSet(s);
        }
    }

    renderTabs();
    renderBody();
}
