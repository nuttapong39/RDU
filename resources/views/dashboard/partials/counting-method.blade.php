{{--
    How one KPI is counted (CONTEXT.md: วิธีนับ) — shown in the KPI page's dialog and on the all-KPI page.
    Expects: $ind (RduIndicator), $cm (CountingMethodService::describe() result).
    Drug and diagnosis codes come from the KPI's query class itself, so they are always the codes counted.
--}}
@php
    $method = $cm['method'];
    $unit   = $ind->countUnit();
    $hasNotes = collect($cm['drugs'])->contains(fn ($d) => $d['note'] !== null);
@endphp

<div class="space-y-5 text-sm text-slate-700">
    {{-- 1. What it measures --}}
    <section>
        <h3 class="font-semibold text-slate-800 mb-1">1. วัดอะไร</h3>
        <p>{{ $ind->name }}</p>
        <p class="mt-1">
            เป้าหมาย <b>{{ $ind->targetLabel() }}</b>
            · {{ $ind->target_direction === 'higher' ? 'ยิ่งสูงยิ่งดี' : 'ยิ่งต่ำยิ่งดี' }}
        </p>
    </section>

    @if($method === null)
        <div class="p-4 rounded-xl border border-amber-200 bg-amber-50" role="note">
            <p class="font-semibold text-amber-800 flex items-center gap-2">
                <span class="msi text-[1.1rem]" aria-hidden="true">hourglass_top</span>
                ยังรอนิยามจากเภสัชกร
            </p>
            <p class="mt-1 text-slate-600">ตัวชี้วัดนี้ยังไม่มี query จาก HOSxP ข้อความด้านล่างเป็นนิยามเบื้องต้น ยังไม่ได้ตรวจกับเภสัชกร</p>
            <dl class="mt-3 space-y-2">
                <div><dt class="font-semibold text-slate-800">ตัวหาร</dt><dd>{{ $ind->denominator_desc }}</dd></div>
                <div><dt class="font-semibold text-slate-800">ตัวตั้ง</dt><dd>{{ $ind->numerator_desc }}</dd></div>
            </dl>
        </div>
    @else
        {{-- 2. Unit --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">2. นับเป็นอะไร</h3>
            <p>{{ $method->unit }}</p>
            <p class="mt-1 text-slate-500">
                @if($unit['additive'])
                    ยอดไตรมาสและทั้งปีเท่ากับผลรวมรายเดือน (แต่ละรายการนับอยู่ในเดือนเดียว ไม่ซ้ำข้ามเดือน)
                @else
                    ยอดไตรมาสและทั้งปีนับคนไม่ซ้ำตลอดช่วงนั้น — คนเดียวมาหลายเดือนนับ 1 คน ผลรวมรายเดือนจึงมากกว่ายอดไตรมาสได้
                @endif
            </p>
        </section>

        {{-- 3–4. Denominator / numerator --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">3. ตัวหาร</h3>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($method->denominator as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </section>
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">4. ตัวตั้ง</h3>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($method->numerator as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </section>

        {{-- 5. Shared conditions --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">5. เงื่อนไขร่วม</h3>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($method->conditions as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </section>

        @unless($cm['namesAvailable'])
            <p class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-500" role="note">
                ดึงชื่อยาและชื่อโรคจาก HOSxP ไม่ได้ในขณะนี้ จึงแสดงเฉพาะรหัส
            </p>
        @endunless

        {{-- 6. Drug codes --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">6. รหัสยาที่นับ — {{ $method->drugLabel }} ({{ count($cm['drugs']) }} รายการ)</h3>
            @if(count($cm['drugs']) === 0)
                <p class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-500" role="note" data-drugs-not-configured>
                    ยังไม่ได้ตั้งค่ารหัสยาของตัวชี้วัดนี้ —
                    <a href="{{ route('drug-code-sets.index') }}" class="text-indigo-600 hover:underline">ไปหน้าตั้งค่ารหัสยา</a>
                </p>
            @else
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th scope="col" class="text-left font-semibold px-3 py-2">icode</th>
                            <th scope="col" class="text-left font-semibold px-3 py-2">ชื่อยา</th>
                            <th scope="col" class="text-left font-semibold px-3 py-2">ความแรง</th>
                            <th scope="col" class="text-left font-semibold px-3 py-2">หน่วย</th>
                            @if($hasNotes)<th scope="col" class="text-left font-semibold px-3 py-2">ตัวยา</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($cm['drugs'] as $drug)
                            <tr>
                                <td class="px-3 py-1.5 font-mono">{{ $drug['icode'] }}</td>
                                <td class="px-3 py-1.5">{{ $drug['name'] ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ $drug['strength'] ?: '—' }}</td>
                                <td class="px-3 py-1.5">{{ $drug['units'] ?: '—' }}</td>
                                @if($hasNotes)<td class="px-3 py-1.5">{{ $drug['note'] ?? '—' }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        {{-- 7. Diagnosis codes --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">7. รหัสโรค (ICD-10)</h3>
            @forelse($cm['diagnoses'] as $group)
                <div class="mt-2">
                    <p class="font-medium text-slate-700">
                        {{ $group['label'] }}
                        @if($group['match'] === 'prefix' && count($group['codes']))
                            <span class="text-slate-500 font-normal">— นับทุกรหัสที่ขึ้นต้นด้วยรหัสเหล่านี้</span>
                        @endif
                    </p>
                    @if($group['note'])<p class="text-slate-500">{{ $group['note'] }}</p>@endif
                    @if(count($group['codes']))
                        <div class="mt-1 overflow-x-auto rounded-lg border border-slate-200 max-h-64 overflow-y-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-600 sticky top-0">
                                    <tr>
                                        <th scope="col" class="text-left font-semibold px-3 py-2">รหัส</th>
                                        <th scope="col" class="text-left font-semibold px-3 py-2">ชื่อโรค</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($group['codes'] as $dx)
                                        <tr>
                                            <td class="px-3 py-1.5 font-mono">{{ $dx['code'] }}</td>
                                            <td class="px-3 py-1.5">{{ $dx['name'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @empty
                <p>ไม่จำกัดการวินิจฉัย</p>
            @endforelse
            @if(count($cm['excluded']))
                <p class="mt-3 font-medium text-slate-700">รหัสที่ตั้งใจไม่นับ</p>
                <div class="mt-1 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th scope="col" class="text-left font-semibold px-3 py-2">รหัส</th>
                                <th scope="col" class="text-left font-semibold px-3 py-2">ชื่อโรค</th>
                                <th scope="col" class="text-left font-semibold px-3 py-2">เหตุผลที่ไม่นับ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($cm['excluded'] as $dx)
                                <tr>
                                    <td class="px-3 py-1.5 font-mono">{{ $dx['code'] }}</td>
                                    <td class="px-3 py-1.5">{{ $dx['name'] ?? '—' }}</td>
                                    <td class="px-3 py-1.5">{{ $dx['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 8. Doctor --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">8. แพทย์ในตารางรายแพทย์</h3>
            <p>{{ $method->doctor }}</p>
        </section>

        {{-- 9. Source --}}
        <section>
            <h3 class="font-semibold text-slate-800 mb-1">9. ที่มาของนิยาม</h3>
            <p class="break-words">{{ $method->source }}</p>
            @if($cm['agreedOn'])<p class="mt-0.5 text-slate-500">ตกลงนิยามเมื่อ {{ $cm['agreedOn'] }}</p>@endif
        </section>
    @endif
</div>
