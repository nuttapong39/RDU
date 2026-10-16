<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | RDU Dashboard - โรงพยาบาลเชียงกลาง</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --green:#16a34a;--green-dark:#15803d;--green-soft:#dcfce7;
  --blue:#2563eb;--blue-soft:#dbeafe;--teal:#0d9488;
  --ink:#0f172a;--muted:#64748b;--line:#e2e8f0;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'IBM Plex Sans Thai','Inter',system-ui,sans-serif;color:var(--ink);background:#fff;line-height:1.6;-webkit-font-smoothing:antialiased;
  min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
a{text-decoration:none;color:inherit}

/* HEADER */
.header{position:sticky;top:0;z-index:60;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.header-inner{max-width:1640px;margin:0 auto;padding:10px 48px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.brand{display:flex;align-items:center;gap:14px}
.logo-moph{width:46px;height:46px;padding:3px;border-radius:50%;flex:none;display:grid;place-items:center;background:#fff;border:2.5px solid var(--green);box-shadow:0 4px 12px rgba(22,163,74,.15);overflow:hidden}
.logo-moph img{width:100%;height:100%;object-fit:contain;border-radius:50%}
.brand-text h1{font-size:17px;font-weight:700;line-height:1.25}
.brand-text span{font-size:11px;color:var(--muted);letter-spacing:1.4px;text-transform:uppercase}
.nav{display:flex;align-items:center;gap:30px}
.nav-item{display:flex;align-items:flex-start;gap:8px;transition:.2s;cursor:default}
.nav-item:hover{color:var(--green)}
.nav-item strong{display:block;font-size:13px;font-weight:700;letter-spacing:.3px}
.nav-item small{display:block;font-size:11px;color:var(--muted);font-weight:400}

/* HERO */
/* Fills the space between the header and the footer, content centred vertically */
.hero{position:relative;overflow:hidden;padding:28px 48px;flex:1;display:flex;align-items:center;
  background:radial-gradient(900px 520px at 8% 12%,rgba(209,250,229,.65) 0%,transparent 62%),
    radial-gradient(800px 500px at 92% 20%,rgba(219,234,254,.6) 0%,transparent 64%),
    radial-gradient(700px 500px at 55% 105%,rgba(204,251,241,.55) 0%,transparent 62%),
    linear-gradient(160deg,#f6fdf9 0%,#fafbfc 45%,#f5f9ff 100%)}
.blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(70px)}
.b1{width:420px;height:420px;background:rgba(34,197,94,.14);top:-140px;left:-130px}
.b2{width:340px;height:340px;background:rgba(45,212,191,.12);bottom:-130px;left:22%}
.b3{width:480px;height:480px;background:rgba(59,130,246,.11);top:10px;right:-160px}
.grid-dots{position:absolute;inset:0;pointer-events:none;opacity:.35;
  background-image:radial-gradient(circle,#94a3b8 1px,transparent 1px);
  background-size:28px 28px;
  -webkit-mask-image:linear-gradient(to bottom,#000,transparent 72%);
  mask-image:linear-gradient(to bottom,#000,transparent 72%)}
.float-ico{position:absolute;width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.7);
  border:1px solid rgba(255,255,255,.85);backdrop-filter:blur(6px);opacity:.82;
  display:grid;place-items:center;box-shadow:0 6px 18px rgba(15,23,42,.07);
  animation:floaty 6s ease-in-out infinite;z-index:1}
.float-ico svg{width:32px;height:32px;opacity:.85}
.f1{top:12%;left:4%;animation-delay:0s}
.f2{top:64%;left:9%;animation-delay:1.1s}
.f3{top:8%;right:6%;animation-delay:.6s;width:56px;height:56px}
.f4{bottom:10%;right:12%;animation-delay:1.8s;width:58px;height:58px}
.f5{top:44%;left:46%;animation-delay:2.4s;width:52px;height:52px;opacity:.6}
@keyframes floaty{0%,100%{transform:translateY(0) rotate(-3deg)}50%{transform:translateY(-16px) rotate(3deg)}}
/* Text at the left edge, card at the right edge, and a middle column kept free for the 3D scene:
   on narrower screens the text column gives way, not the scene */
.hero-inner{position:relative;z-index:3;width:100%;max-width:1640px;margin:0 auto;display:grid;grid-template-columns:minmax(0,620px) minmax(360px,1fr) minmax(0,430px);gap:24px;align-items:center}
.hero-text{container-type:inline-size}

/* HERO TEXT */
.pill{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:9px 18px 9px 12px;font-size:13.5px;font-weight:600;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.pill .dot{width:24px;height:24px;border-radius:7px;background:var(--blue-soft);color:var(--blue);display:grid;place-items:center}
.hero h2{font-size:clamp(30px,4.2vw,52px);font-size:clamp(28px,8.4cqi,52px);font-weight:700;line-height:1.15;margin:14px 0 4px}
.hero h3{font-size:clamp(24px,3.6vw,44px);font-weight:700;line-height:1.15;background:linear-gradient(90deg,var(--green),#0891b2);-webkit-background-clip:text;background-clip:text;color:transparent}
/* Sized to the text column, so "RDU – การใช้ยาอย่างสมเหตุผล" stays on one line */
.hero h3{font-size:clamp(22px,7.2cqi,44px)}
.hero h3 .nowrap{white-space:nowrap}
.hero p{max-width:600px;color:#475569;font-size:14.5px;margin:14px 0 20px}
.hero p b{color:var(--green-dark);font-weight:600}
.chips{display:flex;flex-wrap:wrap;gap:12px}
.chip{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.85);border:1px solid var(--line);border-radius:999px;padding:9px 18px;font-size:13.5px;font-weight:500;transition:.2s;backdrop-filter:blur(4px)}
.chip:hover{border-color:var(--green);color:var(--green-dark);transform:translateY(-2px);box-shadow:0 6px 16px rgba(22,163,74,.13)}
.stats{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
.stats-title{width:100%;font-size:12.5px;font-weight:600;color:#475569}
.st{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.9);border:1px solid var(--line);border-radius:12px;padding:8px 14px;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(15,23,42,.05)}
.st b{font-size:20px;font-weight:700}
.st i{width:10px;height:10px;border-radius:50%;flex:none}
.st-pass i{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18)} .st-pass b{color:#15803d}
.st-warning i{background:#f59e0b;box-shadow:0 0 0 4px rgba(245,158,11,.18)} .st-warning b{color:#b45309}
.st-fail i{background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.18)} .st-fail b{color:#b91c1c}
.st-na i{background:#94a3b8;box-shadow:0 0 0 4px rgba(148,163,184,.2)} .st-na b{color:#475569}
.stats-note{margin-top:10px;font-size:12px;color:var(--muted)}
/* Narrow text column (laptop screens): smaller chips so each row stays on one line */
@container (max-width:540px){
  .chips{gap:7px}
  .chip{padding:6px 12px;font-size:12.5px}
  .stats{display:grid;grid-template-columns:repeat(2,max-content);gap:7px}
  .stats-title{grid-column:1/-1}
  .st{padding:6px 10px;gap:6px;font-size:12.5px}
  .st b{font-size:16px}
}

/* 3D SCENE (resources/js/login-scene.js) — replaces the decorative background when WebGL is available */
.rdu-scene{position:absolute;inset:0;z-index:2;pointer-events:none;opacity:0;transition:opacity 1.2s ease}
.rdu-scene.is-ready{opacity:1}
.rdu-scene canvas{display:block;width:100%;height:100%}
.hero.has-scene .blob,.hero.has-scene .grid-dots,.hero.has-scene .float-ico{display:none}
/* Keep the text readable where capsules pass behind it: a soft white wash, not a text-shadow
   (the h3 is gradient-clipped transparent text — a shadow would show through and wash it out) */
.hero.has-scene .hero-text{position:relative}
.hero.has-scene .hero-text::before{content:"";position:absolute;inset:-16px -60px -16px -24px;z-index:-1;border-radius:40px;
  background:radial-gradient(ellipse at 35% 50%,rgba(255,255,255,.85) 0%,rgba(255,255,255,.7) 48%,rgba(255,255,255,0) 70%)}
.scene-tip{position:absolute;z-index:5;pointer-events:none;max-width:320px;background:rgba(15,23,42,.88);color:#fff;font-size:12.5px;line-height:1.45;padding:7px 11px;border-radius:10px;box-shadow:0 8px 20px rgba(15,23,42,.25)}

/* LOGIN CARD */
.login-wrap{display:flex;justify-content:flex-end}
.card{width:100%;max-width:430px;background:rgba(255,255,255,.98);border:1px solid #eef2f7;border-radius:24px;box-shadow:0 20px 50px rgba(15,23,42,.10);padding:26px 32px 22px;backdrop-filter:blur(14px)}
.card-head{text-align:center;margin-bottom:18px}
.card-icon{width:68px;height:68px;padding:4px;margin:0 auto 10px;border-radius:50%;background:#fff;border:3px solid var(--green);display:grid;place-items:center;box-shadow:0 10px 24px rgba(22,163,74,.22);overflow:hidden}
.card-icon img{width:100%;height:100%;object-fit:contain;border-radius:50%}
.card-head h4{font-size:21px;font-weight:700}
.card-head p{font-size:13.5px;color:var(--muted);margin-top:4px}
.alert-box{display:none;align-items:center;gap:9px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;padding:11px 14px;border-radius:11px;margin-bottom:16px}
.alert-box.show{display:flex}
.field{margin-bottom:12px}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}
.input-box{position:relative}
.input-box .lead-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);pointer-events:none;display:flex;align-items:center;color:#94a3b8}
.input-box input{width:100%;padding:13px 44px 13px 42px;border:1px solid var(--line);border-radius:12px;font-family:inherit;font-size:14.5px;background:#fff;transition:.2s;color:var(--ink)}
.input-box input:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 4px var(--green-soft)}
.input-box input.is-error{border-color:#f87171;background:#fff5f5}
.toggle-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;padding:6px;display:flex;align-items:center}
.toggle-eye:hover{color:#64748b}
.row-between{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:6px 0 14px;flex-wrap:wrap}
.check-label{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer}
.check-label input{width:16px;height:16px;accent-color:var(--green);cursor:pointer}
.btn{width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:15px;font-weight:700;transition:.2s;display:flex;align-items:center;justify-content:center;gap:9px}
.btn-primary{background:linear-gradient(135deg,var(--green),var(--teal));color:#fff;box-shadow:0 8px 20px rgba(22,163,74,.28)}
.btn-primary:hover{filter:brightness(1.06);transform:translateY(-2px)}
.btn-primary:active{transform:translateY(0);filter:brightness(.97)}
.divider{display:flex;align-items:center;gap:12px;margin:12px 0;color:#94a3b8;font-size:12px}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--line)}
.btn-ghost{background:#fff;border:1.5px solid var(--line);color:var(--ink)}
.btn-ghost:not(:disabled):hover{border-color:var(--blue);color:var(--blue);background:#f8fbff}
.btn-ghost:disabled{opacity:.45;cursor:not-allowed}
.badge-soon{margin-left:6px;font-size:11px;font-weight:600;background:#f0fdf4;color:var(--green-dark);border:1px solid #bbf7d0;border-radius:999px;padding:2px 8px}
.card-foot{text-align:center;font-size:12px;color:var(--muted);margin-top:14px}
.field-error{font-size:12px;color:#dc2626;margin-top:5px}

/* FOOTER */
.footer{border-top:1px solid var(--line);padding:7px 48px;background:#fff;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:4px 14px;font-size:11.5px;line-height:1.5;color:var(--muted)}
.footer .dev{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:center}
.footer .dev b{color:var(--green-dark);font-weight:600}

/* RESPONSIVE */
@media(max-width:900px){
  .nav{display:none}
  .hero-inner{grid-template-columns:1fr;gap:44px}
  .scene-stage{display:none}
  .hero{padding:44px 20px 60px}
  .login-wrap{justify-content:center}
  .footer{padding:8px 20px}
  .hero-text{text-align:center}
  .hero p{margin-inline:auto}
  .chips,.stats{justify-content:center}
  .float-ico{display:none}
}
@media(max-width:560px){
  .header-inner{padding:12px 16px}
  .card{padding:28px 20px 24px;border-radius:20px}
}
@media(prefers-reduced-motion:reduce){
  .float-ico,.btn-primary,.chip{animation:none;transition:none}
}
/* Compact further on short viewports (typical 1366x768 laptop) so the footer never needs a scroll */
@media(max-height:820px){
  .hero{padding:20px 48px}
  .hero h2{margin:6px 0 2px}
  .hero p{margin:10px 0 14px}
  .stats{margin-top:14px}
  .card{padding:20px 32px 16px}
  .card-head{margin-bottom:12px}
  .card-icon{width:52px;height:52px;margin-bottom:8px}
}
@media(max-height:740px){
  .hero{padding:12px 48px}
  .card-icon{width:42px;height:42px;margin-bottom:6px}
  .input-box input{padding-top:10px;padding-bottom:10px}
  .btn{padding:11px}
  .divider{margin:8px 0}
  .card-foot{margin-top:8px}
}
</style>
@if(app(\App\Services\FontService::class)->isCustomized())
@include('partials.fonts')
<style>body{font-family:var(--font-sans)}</style>
@endif
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="header-inner">
    <div class="brand">
      <div class="logo-moph">
        <img src="{{ asset('images/logo_ck.png') }}" alt="ตราสัญลักษณ์โรงพยาบาลเชียงกลาง">
      </div>
      <div class="brand-text">
        <h1>โรงพยาบาลเชียงกลาง จังหวัดน่าน</h1>
        <span>Chiangklang Hospital</span>
      </div>
    </div>

    <nav class="nav" aria-label="เมนูหลัก">
      <a href="#" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="margin-top:2px;opacity:.75;flex-shrink:0">
          <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span><strong>หน้าแรก</strong></span>
      </a>
      <a href="#" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="margin-top:2px;opacity:.75;flex-shrink:0">
          <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span><strong>RDU DASHBOARD</strong><small>การใช้ยาอย่างสมเหตุผล</small></span>
      </a>
      <a href="#" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="margin-top:2px;opacity:.75;flex-shrink:0">
          <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
        </svg>
        <span><strong>ติดต่อเรา</strong><small>งานเทคโนโลยีสารสนเทศ</small></span>
      </a>
    </nav>
  </div>
</header>

<!-- HERO + LOGIN -->
<section class="hero">
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>
  <div class="grid-dots"></div>

  {{-- 3D scene: 13 KPI capsules orbiting HOSxP XE, ring colour = last overview's status (CONTEXT.md: ภาพสถานะล่าสุด) --}}
  <div id="rduScene" class="rdu-scene" aria-hidden="true"></div>
  {{-- Above the text and the card (the scene layer sits below them) --}}
  <div id="rduSceneTooltip" class="scene-tip" aria-hidden="true" hidden></div>
  <script type="application/json" id="rduSceneData">@json(['kpis' => $snapshot['kpis']])</script>

  {{-- Floating icons --}}
  <div class="float-ico f1" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round">
      <rect x="2.5" y="8" width="19" height="8" rx="4" transform="rotate(-45 12 12)"/>
      <line x1="9.2" y1="14.8" x2="14.8" y2="9.2"/>
    </svg>
  </div>
  <div class="float-ico f2" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="1.8" stroke-linecap="round">
      <rect x="7" y="2.5" width="10" height="3.5" rx="1"/>
      <rect x="5.5" y="6" width="13" height="15.5" rx="2.5"/>
      <line x1="12" y1="10.5" x2="12" y2="17"/>
      <line x1="8.8" y1="13.7" x2="15.2" y2="13.7"/>
    </svg>
  </div>
  <div class="float-ico f3" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8">
      <circle cx="12" cy="12" r="9"/>
      <line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/>
    </svg>
  </div>
  <div class="float-ico f4" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round">
      <path d="M4.5 3v6a4.5 4.5 0 009 0V3"/>
      <line x1="2.8" y1="3" x2="6.2" y2="3"/>
      <line x1="11.8" y1="3" x2="15.2" y2="3"/>
      <path d="M9 13.5v2a5 5 0 0010 0v-1"/>
      <circle cx="19" cy="11" r="2.3"/>
    </svg>
  </div>
  <div class="float-ico f5" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="1.8">
      <rect x="3" y="3" width="18" height="18" rx="3"/>
      <circle cx="8.5" cy="8.5" r="1.8"/>
      <circle cx="15.5" cy="8.5" r="1.8"/>
      <circle cx="8.5" cy="15.5" r="1.8"/>
      <circle cx="15.5" cy="15.5" r="1.8"/>
    </svg>
  </div>

  <div class="hero-inner">

    {{-- Hero text --}}
    <div class="hero-text">
      <div class="pill">
        <span class="dot">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </span>
        Rational Drug Use Dashboard
      </div>
      <h2>ระบบรายงาน</h2>
      <h3>RDU – <span class="nowrap">การใช้ยาอย่างสมเหตุผล</span></h3>
      <p>
        ระบบติดตามและประเมินตัวชี้วัด <b>RDU Service Plan</b> อย่างต่อเนื่อง
        ครอบคลุมการสั่งใช้ยาปฏิชีวนะใน 4 กลุ่มโรคเป้าหมาย การใช้ยาในผู้ป่วยเบาหวาน
        ความดันโลหิตสูง และผู้สูงอายุ พร้อมเชื่อมโยงข้อมูลจาก HOSxP XE
        เพื่อสนับสนุนการตัดสินใจเชิงนโยบายของโรงพยาบาลอย่างครบวงจร
      </p>

      <div class="chips">
        <span class="chip">RI</span>
        <span class="chip">AD</span>
        <span class="chip">APL</span>
        <span class="chip">FTW</span>
        <span class="chip">DM / HT</span>
        <span class="chip">ผู้สูงอายุ</span>
      </div>

      {{-- Status summary — also the text equivalent of the (aria-hidden) 3D scene --}}
      @php $counts = $snapshot['counts']; @endphp
      <div class="stats">
        <div class="stats-title">สถานะ {{ count($snapshot['kpis']) }} ตัวชี้วัด RDU ปีงบประมาณปัจจุบัน</div>
        <span class="st st-pass"><i aria-hidden="true"></i>ผ่านเกณฑ์ <b>{{ $counts['pass'] }}</b></span>
        <span class="st st-warning"><i aria-hidden="true"></i>ใกล้เป้าหมาย <b>{{ $counts['warning'] }}</b></span>
        <span class="st st-fail"><i aria-hidden="true"></i>ไม่ผ่านเกณฑ์ <b>{{ $counts['fail'] }}</b></span>
        <span class="st st-na"><i aria-hidden="true"></i>ไม่มีข้อมูล <b>{{ $counts['na'] }}</b></span>
      </div>
      <p class="stats-note">
        @if($snapshot['takenAtText'])
          สถานะ ณ {{ $snapshot['takenAtText'] }}
        @else
          ยังไม่มีข้อมูลสถานะ — จะแสดงหลังมีผู้เปิดหน้าภาพรวมครั้งแรก
        @endif
        · อัปเดตเมื่อมีผู้เปิดหน้าภาพรวม (ผลคำนวณเก่าสุดไม่เกิน 1 ชั่วโมง) · ข้อมูลจาก HOSxP XE + HDC
      </p>
    </div>

    {{-- Kept empty: the 3D scene's core and orbits show here --}}
    <div class="scene-stage" aria-hidden="true"></div>

    {{-- Login card --}}
    <div class="login-wrap">
      <div class="card">
        <div class="card-head">
          <div class="card-icon">
            <img src="{{ asset('images/logo_ck.png') }}" alt="ตราสัญลักษณ์โรงพยาบาลเชียงกลาง">
          </div>
          <h4>เข้าสู่ระบบ</h4>
          <p>สำหรับเจ้าหน้าที่โรงพยาบาลเชียงกลาง</p>
        </div>

        {{-- Error alert --}}
        @if($errors->any())
          <div class="alert-box show" role="alert">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" flex-shrink="0" aria-hidden="true">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $errors->first() }}</span>
          </div>
        @endif

        <form method="POST" action="{{ route('login') }}" novalidate>
          @csrf

          {{-- Username --}}
          <div class="field">
            <label for="username">ชื่อผู้ใช้งาน (Username)</label>
            <div class="input-box">
              <span class="lead-icon" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </span>
              <input type="text" id="username" name="username"
                     value="{{ old('username') }}"
                     autocomplete="username" autofocus required
                     placeholder="กรอกชื่อผู้ใช้งาน"
                     class="{{ $errors->has('username') ? 'is-error' : '' }}"
                     aria-describedby="{{ $errors->has('username') ? 'username-error' : '' }}">
            </div>
            @error('username')
              <p class="field-error" id="username-error">{{ $message }}</p>
            @enderror
          </div>

          {{-- Password --}}
          <div class="field">
            <label for="password">รหัสผ่าน (Password)</label>
            <div class="input-box">
              <span class="lead-icon" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <rect x="3" y="11" width="18" height="11" rx="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              </span>
              <input type="password" id="password" name="password" required
                     autocomplete="current-password"
                     placeholder="กรอกรหัสผ่าน">
              <button type="button" id="togglePassword" class="toggle-eye"
                      aria-label="แสดงรหัสผ่าน">
                <svg id="eye-show" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                  <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg id="eye-hide" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="display:none">
                  <path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="row-between">
            <label class="check-label">
              <input type="checkbox" id="remember" name="remember">
              จดจำการเข้าสู่ระบบ
            </label>
          </div>

          <button type="submit" class="btn btn-primary">
            เข้าสู่ระบบ
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>

          <div class="divider">หรือ</div>

          <button type="button" class="btn btn-ghost" disabled
                  title="ยังไม่พร้อมใช้งาน — รอการเชื่อมต่อ HOSxP">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
              <rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/>
              <circle cx="8.5" cy="11" r="2.3"/>
              <path d="M4.8 16.4c.7-1.7 2.1-2.5 3.7-2.5s3 .8 3.7 2.5"/>
              <line x1="15" y1="9.5" x2="19.5" y2="9.5"/>
              <line x1="15" y1="13.5" x2="19.5" y2="13.5"/>
            </svg>
            Login ด้วย Provider ID
            <span class="badge-soon">เร็วๆ นี้</span>
          </button>
        </form>

        <p class="card-foot">หากพบปัญหาการเข้าใช้งาน ติดต่องานเทคโนโลยีสารสนเทศ<br>โทร. 0-54797111 ต่อ 106</p>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <span>&copy; 2569 โรงพยาบาลเชียงกลาง จังหวัดน่าน | RDU Dashboard v1.0</span>
  <span class="dev">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <rect x="2" y="3" width="20" height="14" rx="2"/>
      <path d="M8 21h8M12 17v4"/>
    </svg>
    พัฒนาโดย : <b>นายณัฐพงษ์ นิลคง</b>
    นักวิชาการคอมพิวเตอร์ งานเทคโนโลยีสารสนเทศ รพ.เชียงกลาง
  </span>
</footer>

@vite('resources/js/login-scene.js')
<script>
(function () {
    const btn     = document.getElementById('togglePassword');
    const input   = document.getElementById('password');
    const eyeShow = document.getElementById('eye-show');
    const eyeHide = document.getElementById('eye-hide');

    btn.addEventListener('click', function () {
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        eyeShow.style.display = isHidden ? 'none' : '';
        eyeHide.style.display = isHidden ? '' : 'none';
        btn.setAttribute('aria-label', isHidden ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });
})();
</script>
</body>
</html>
