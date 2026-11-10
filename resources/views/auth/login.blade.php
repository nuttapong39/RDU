<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | RDU Dashboard{{ $facility['configured'] ? ' - '.$facility['name'] : '' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  /* Same violet as inside the system (resources/css/app.css --brand-*); status colours stay green/amber/red */
  --brand:#7c3aed;--brand-dark:#6d28d9;--brand-soft:#ede9fe;--brand-2:#4f46e5;
  --card-edge:rgba(167,139,250,.30);
  --card-shadow:0 1px 2px rgba(76,29,149,.07),0 6px 16px -4px rgba(109,40,217,.13),0 16px 32px -14px rgba(76,29,149,.14);
  --ease:cubic-bezier(.22,1,.36,1);
  --ink:#0f172a;--muted:#64748b;--line:#e2e8f0;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'IBM Plex Sans Thai','Inter',system-ui,sans-serif;color:var(--ink);background:#fff;line-height:1.6;-webkit-font-smoothing:antialiased;
  min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
a{text-decoration:none;color:inherit}

/* HEADER */
.header{position:sticky;top:0;z-index:60;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--card-edge)}
.header-inner{max-width:1640px;margin:0 auto;padding:10px 48px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.brand{display:flex;align-items:center;gap:18px}
.logo-moph{width:68px;height:68px;padding:4px;border-radius:50%;flex:none;display:grid;place-items:center;background:#fff;border:2.5px solid var(--brand);box-shadow:0 4px 12px rgba(109,40,217,.15);overflow:hidden}
.logo-moph img,.logo-moph svg{width:100%;height:100%;object-fit:contain;border-radius:50%}
.brand-text h1{font-size:24px;font-weight:700;line-height:1.2}
.brand-text span{font-size:13px;color:var(--muted);letter-spacing:1.6px;text-transform:uppercase}
.nav{display:flex;align-items:center;gap:30px}
.nav-item{display:flex;align-items:flex-start;gap:8px;transition:.2s;cursor:default}
.nav-item:hover{color:var(--brand)}
.nav-item strong{display:block;font-size:13px;font-weight:700;letter-spacing:.3px}
.nav-item small{display:block;font-size:11px;color:var(--muted);font-weight:400}

/* HERO */
/* Fills the space between the header and the footer, content centred vertically */
.hero{position:relative;overflow:hidden;padding:28px 48px;flex:1;display:flex;align-items:center;
  background:linear-gradient(160deg,#fbfaff 0%,#fcfbff 45%,#f7f5ff 100%)}
.blob{position:absolute;border-radius:50%;pointer-events:none;filter:blur(70px);will-change:transform}
.b1{width:520px;height:520px;background:rgba(167,139,250,.30);top:-190px;left:-160px;animation:drift-a 38s ease-in-out infinite alternate}
.b2{width:420px;height:420px;background:rgba(196,181,253,.34);bottom:-170px;left:24%;animation:drift-b 44s ease-in-out infinite alternate}
.b3{width:560px;height:560px;background:rgba(139,92,246,.20);top:0;right:-200px;animation:drift-a 50s ease-in-out infinite alternate-reverse}
@keyframes drift-a{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(60px,40px,0) scale(1.12)}}
@keyframes drift-b{from{transform:translate3d(0,0,0) scale(1.08)}to{transform:translate3d(-50px,-36px,0) scale(.96)}}
.grid-dots{position:absolute;inset:0;pointer-events:none;opacity:.35;
  background-image:radial-gradient(circle,#a78bfa 1px,transparent 1px);
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

/* VITAL SIGN MONITOR: like a hospital monitor in a film. A bright dot starts at the left edge of the screen and draws a
   flat line first, then heartbeats of different heights; the trace behind it slowly fades and an erase gap runs ahead of
   the dot. Drawn on a canvas by the script below. */
.vital{position:absolute;left:0;right:0;bottom:0;height:112px;overflow:hidden;pointer-events:none;z-index:1;
  -webkit-mask-image:linear-gradient(90deg,#000 94%,transparent);mask-image:linear-gradient(90deg,#000 94%,transparent)}
.vital canvas{display:block;width:100%;height:100%;filter:drop-shadow(0 0 4px rgba(139,92,246,.8))}
.vital-dot{position:absolute;left:0;top:0;width:11px;height:11px;margin:-5.5px 0 0 -5.5px;border-radius:50%;background:#fff;
  box-shadow:0 0 0 3px rgba(124,58,237,.95),0 0 16px 6px rgba(139,92,246,.75);will-change:transform}

/* ENTRANCE: one short sequence. The text rises line by line, then the card; the status numbers count up (script below) */
@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.hero-text>*{animation:rise .6s var(--ease) both}
.hero-text>:nth-child(1){animation-delay:.05s}.hero-text>:nth-child(2){animation-delay:.12s}.hero-text>:nth-child(3){animation-delay:.2s}
.hero-text>:nth-child(4){animation-delay:.3s}.hero-text>:nth-child(5){animation-delay:.42s}.hero-text>:nth-child(6){animation-delay:.54s}.hero-text>:nth-child(7){animation-delay:.62s}
.login-wrap .card{animation:rise .7s var(--ease) .25s both}

/* HERO TEXT */
.pill{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--card-edge);border-radius:999px;padding:9px 18px 9px 12px;font-size:13.5px;font-weight:600;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.pill .dot{width:24px;height:24px;border-radius:7px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center}
.hero h2{font-size:clamp(30px,4.2vw,52px);font-size:clamp(28px,8.4cqi,52px);font-weight:700;line-height:1.15;margin:14px 0 4px}
.hero h3{font-size:clamp(24px,3.6vw,44px);font-weight:700;line-height:1.15;background:linear-gradient(90deg,var(--brand),var(--brand-2) 55%,#a855f7);-webkit-background-clip:text;background-clip:text;color:transparent}
/* Sized to the text column, so "RDU – การใช้ยาอย่างสมเหตุผล" stays on one line */
.hero h3{font-size:clamp(22px,7.2cqi,44px)}
.hero h3 .nowrap{white-space:nowrap}
.hero p{max-width:600px;color:#475569;font-size:14.5px;margin:14px 0 20px}
.hero p b{color:var(--brand-dark);font-weight:600}
.chips{display:flex;flex-wrap:wrap;gap:12px}
.chip{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.9);border:1px solid var(--card-edge);border-radius:999px;padding:9px 18px;font-size:13.5px;font-weight:500;transition:.2s;backdrop-filter:blur(4px)}
.chip:hover{border-color:var(--brand);color:var(--brand-dark);transform:translateY(-2px);box-shadow:0 6px 16px rgba(109,40,217,.13)}
.stats{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
.stats-title{width:100%;font-size:12.5px;font-weight:600;color:#475569}
.st{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#fff,#fcfbff);border:1px solid var(--card-edge);border-radius:12px;padding:8px 14px;font-size:14px;font-weight:600;box-shadow:var(--card-shadow);transition:transform .22s var(--ease),box-shadow .22s var(--ease)}
.st:hover{transform:translateY(-3px);box-shadow:0 12px 28px -6px rgba(109,40,217,.20),0 0 0 1px rgba(167,139,250,.55)}
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

/* PHARMACIST SCENE. The base style is the FINAL pose (she holds the jar, her other palm is out, "RDU" above it);
   .play replays the story: she walks in, tips the jar, pills drop into her palm, "RDU" pops out of the hand. ~3.6 s. */
.scene-stage{display:flex;align-items:center;justify-content:center;position:relative;z-index:2}
.rx-stage{width:100%;max-width:480px;cursor:pointer;-webkit-tap-highlight-color:transparent;user-select:none}
.rx-stage svg{display:block;width:100%;height:auto;max-height:64vh;overflow:visible}
.rx-stage .pill{opacity:0}
.rx-stage .pill-r{transform-box:fill-box;transform-origin:center}
.rx-stage .eye{transform-box:fill-box;transform-origin:center;animation:rx-blink 6s ease-in-out 4.2s infinite}
.rx-stage .forearm{transform-origin:124px 298px}
.rx-stage .jar{transform-origin:150px 276px}
.rx-stage .badge{transform-origin:205px 312px}
.rx-stage .sp{transform-box:fill-box;transform-origin:center}
.rx-stage.play .rx-char{animation:rx-enter .7s var(--ease) backwards}
.rx-stage.play .forearm{animation:rx-forearm 2.6s ease-in-out .8s backwards}
.rx-stage.play .jar{animation:rx-jar 2.6s ease-in-out .8s backwards}
.rx-stage.play .pill{animation:rx-pf calc(3.1s - var(--d)) linear var(--d) both}
.rx-stage.play .pill-x{animation:rx-px .5s cubic-bezier(.2,.7,.4,1) var(--d) both}
.rx-stage.play .pill-y{animation:rx-py .7s linear var(--d) both}
.rx-stage.play .pill-r{animation:rx-pr .6s linear var(--d) both}
.rx-stage.play .badge{animation:rx-pop .75s cubic-bezier(.34,1.56,.64,1) 2.85s backwards}
.rx-stage.play .sp{animation:rx-pop .6s cubic-bezier(.34,1.56,.64,1) calc(3.05s + var(--s,0s)) backwards}
@keyframes rx-enter{from{opacity:0;translate:70px 0}to{opacity:1;translate:0 0}}
@keyframes rx-forearm{0%,100%{rotate:0deg}18%,82%{rotate:-10deg}}
@keyframes rx-jar{0%,14%,92%,100%{rotate:0deg}30%,72%{rotate:108deg}}
@keyframes rx-pf{0%{opacity:0}3%,86%{opacity:1}100%{opacity:0}}
@keyframes rx-px{from{translate:0 0}to{translate:var(--tx) 0}}
@keyframes rx-py{0%{translate:0 0;animation-timing-function:cubic-bezier(.5,0,1,.6)}
  55%{translate:0 var(--ty);animation-timing-function:cubic-bezier(.2,.7,.4,1)}
  75%{translate:0 calc(var(--ty) - 7px);animation-timing-function:cubic-bezier(.5,0,1,.6)}
  100%{translate:0 var(--ty)}}
@keyframes rx-pr{from{rotate:0deg}to{rotate:var(--rot)}}
@keyframes rx-pop{0%{scale:0;rotate:-10deg;opacity:0}60%{scale:1.18;rotate:3deg;opacity:1}80%{scale:.95;rotate:0deg}100%{scale:1;opacity:1}}
@keyframes rx-blink{0%,92%,100%{scale:1 1}95%{scale:1 .1}}
.float-ico{display:none}

/* LOGIN CARD */
.login-wrap{display:flex;justify-content:flex-end}
.card{width:100%;max-width:430px;background:linear-gradient(180deg,#fff,#fcfbff);border:1px solid var(--card-edge);border-radius:24px;box-shadow:var(--card-shadow),0 24px 48px -20px rgba(76,29,149,.22);padding:26px 32px 22px;backdrop-filter:blur(14px)}
.card-head{text-align:center;margin-bottom:18px}
.card-icon{width:68px;height:68px;padding:4px;margin:0 auto 10px;border-radius:50%;background:#fff;border:3px solid var(--brand);display:grid;place-items:center;box-shadow:0 10px 24px rgba(109,40,217,.22);overflow:hidden}
.card-icon img,.card-icon svg{width:100%;height:100%;object-fit:contain;border-radius:50%}
.card-head h4{font-size:21px;font-weight:700}
.card-head p{font-size:13.5px;color:var(--muted);margin-top:4px}
.alert-box{display:none;align-items:center;gap:9px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;padding:11px 14px;border-radius:11px;margin-bottom:16px}
.alert-box.show{display:flex}
.field{margin-bottom:12px}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}
.input-box{position:relative}
.input-box .lead-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);pointer-events:none;display:flex;align-items:center;color:#94a3b8}
.input-box input{width:100%;padding:13px 44px 13px 42px;border:1px solid #ddd6fe;border-radius:12px;font-family:inherit;font-size:14.5px;background:#fff;transition:.2s;color:var(--ink)}
.input-box input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 4px var(--brand-soft)}
.input-box input.is-error{border-color:#f87171;background:#fff5f5}
.toggle-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;padding:6px;display:flex;align-items:center}
.toggle-eye:hover{color:#64748b}
.row-between{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:6px 0 14px;flex-wrap:wrap}
.check-label{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer}
.check-label input{width:16px;height:16px;accent-color:var(--brand);cursor:pointer}
.btn{width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:15px;font-weight:700;transition:.2s;display:flex;align-items:center;justify-content:center;gap:9px}
.btn-primary{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#fff;box-shadow:0 8px 20px rgba(109,40,217,.30)}
.btn-primary::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);transition:left .6s var(--ease)}
.btn-primary:hover::after{left:130%}
.btn-primary:hover{filter:brightness(1.06);transform:translateY(-2px)}
.btn-primary:active{transform:translateY(0);filter:brightness(.97)}
.divider{display:flex;align-items:center;gap:12px;margin:12px 0;color:#94a3b8;font-size:12px}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--line)}
.btn-ghost{background:#fff;border:1.5px solid var(--line);color:var(--ink)}
.btn-ghost:not(:disabled):hover{border-color:var(--brand);color:var(--brand);background:#faf8ff}
.btn-ghost:disabled{opacity:.45;cursor:not-allowed}
.badge-soon{margin-left:6px;font-size:11px;font-weight:600;background:var(--brand-soft);color:var(--brand-dark);border:1px solid #ddd6fe;border-radius:999px;padding:2px 8px}
.card-foot{text-align:center;font-size:12px;color:var(--muted);margin-top:14px}
.field-error{font-size:12px;color:#dc2626;margin-top:5px}

/* FOOTER */
.footer{border-top:1px solid var(--card-edge);padding:7px 48px;background:#fff;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:4px 14px;font-size:11.5px;line-height:1.5;color:var(--muted)}
.footer .dev{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:center}
.footer .dev b{color:var(--brand-dark);font-weight:600}

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
  .logo-moph{width:52px;height:52px}
  .brand-text h1{font-size:18px}
  .brand-text span{font-size:11px}
  .header-inner{padding:12px 16px}
  .card{padding:28px 20px 24px;border-radius:20px}
}
@media(prefers-reduced-motion:reduce){
  .float-ico,.btn-primary,.chip,.blob,.hero-text>*,.login-wrap .card,.st{animation:none!important;transition:none!important}
  .btn-primary::after{display:none}
  .rx-stage,.rx-stage *{animation:none!important}
  .vital-dot{display:none}
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
        <x-facility-logo :facility="$facility" />
      </div>
      <div class="brand-text">
        <h1>{{ $facility['title'] }}</h1>
        <span>Rational Drug Use Dashboard</span>
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

  {{-- Vital-sign line flowing left to right along the bottom (decorative) --}}
  <div class="vital" aria-hidden="true"><canvas></canvas><i class="vital-dot"></i></div>

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
        @if(($counts['not_configured'] ?? 0) > 0)
          <span class="st st-na"><i aria-hidden="true"></i>ยังไม่ตั้งค่า <b>{{ $counts['not_configured'] }}</b></span>
        @endif
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

    {{-- A cute pharmacist tips a jar of pills into her other palm and "RDU" pops out of that hand; the scene rests on
         that pose. Inline SVG + CSS only, decorative (aria-hidden). Click to replay. With prefers-reduced-motion it shows
         the final pose at once. --}}
    <div class="scene-stage" aria-hidden="true">
      <div class="rx-stage play" id="rxStage" title="คลิกเพื่อดูอีกครั้ง">
        <svg viewBox="-30 20 420 430" xmlns="http://www.w3.org/2000/svg" focusable="false">
          <defs>
            <radialGradient id="rxHalo" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#c4b5fd" stop-opacity=".55"/><stop offset="1" stop-color="#c4b5fd" stop-opacity="0"/></radialGradient>
            <linearGradient id="rxBadge" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#4f46e5"/></linearGradient>
            <linearGradient id="rxJar" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fbbf24"/><stop offset=".55" stop-color="#f59e0b"/><stop offset="1" stop-color="#d97706"/></linearGradient>
          </defs>
          <circle cx="200" cy="240" r="190" fill="url(#rxHalo)"/>
          <ellipse cx="200" cy="432" rx="120" ry="13" fill="rgba(76,29,149,.14)"/>

          <g class="rx-char">
            <!-- legs and shoes -->
            <rect x="164" y="380" width="30" height="44" rx="9" fill="#5b4b8a"/>
            <rect x="206" y="380" width="30" height="44" rx="9" fill="#5b4b8a"/>
            <ellipse cx="177" cy="426" rx="21" ry="9" fill="#312e81"/>
            <ellipse cx="223" cy="426" rx="21" ry="9" fill="#312e81"/>

            <!-- hair behind the head: two buns with violet scrunchies -->
            <circle cx="152" cy="82" r="21" fill="#3b2552"/>
            <circle cx="248" cy="82" r="21" fill="#3b2552"/>
            <rect x="141" y="96" width="24" height="9" rx="4.5" fill="#8b5cf6" transform="rotate(-24 153 100)"/>
            <rect x="235" y="96" width="24" height="9" rx="4.5" fill="#8b5cf6" transform="rotate(24 247 100)"/>
            <ellipse cx="200" cy="132" rx="66" ry="64" fill="#3b2552"/>

            <!-- coat -->
            <path d="M130 234Q130 206 162 202H238Q270 206 270 234L278 394Q200 412 122 394Z" fill="#fff" stroke="#ddd6fe" stroke-width="3" stroke-linejoin="round"/>
            <path d="M200 266V404" stroke="#ede9fe" stroke-width="3"/>
            <path d="M177 204 200 266 223 204Z" fill="#8b5cf6"/>
            <path d="M162 202 192 270 170 276 140 222Z" fill="#fff" stroke="#c4b5fd" stroke-width="3" stroke-linejoin="round"/>
            <path d="M238 202 208 270 230 276 260 222Z" fill="#fff" stroke="#c4b5fd" stroke-width="3" stroke-linejoin="round"/>
            <path d="M156 318v14M149 325h14" stroke="#8b5cf6" stroke-width="4.5" stroke-linecap="round"/>

            <!-- head -->
            <rect x="188" y="180" width="24" height="26" rx="8" fill="#f7c9a0"/>
            <circle cx="143" cy="140" r="10" fill="#ffe2c6"/><circle cx="257" cy="140" r="10" fill="#ffe2c6"/>
            <circle cx="200" cy="130" r="58" fill="#ffe2c6"/>
            <path d="M143 122Q146 66 200 64Q254 66 257 122Q240 96 216 106Q198 82 178 106Q158 98 143 122Z" fill="#3b2552"/>
            <path d="M142 124Q132 174 152 186L160 142Z" fill="#3b2552"/>
            <path d="M258 124Q268 174 248 186L240 142Z" fill="#3b2552"/>
            <ellipse cx="160" cy="154" rx="11" ry="6.5" fill="#fb9fb0" opacity=".6"/>
            <ellipse cx="240" cy="154" rx="11" ry="6.5" fill="#fb9fb0" opacity=".6"/>
            <g class="eye"><ellipse cx="177" cy="138" rx="9.5" ry="12" fill="#3b1d5a"/><circle cx="180.5" cy="133" r="4" fill="#fff"/><circle cx="173" cy="143.5" r="2" fill="#fff" opacity=".85"/></g>
            <g class="eye"><ellipse cx="223" cy="138" rx="9.5" ry="12" fill="#3b1d5a"/><circle cx="226.5" cy="133" r="4" fill="#fff"/><circle cx="219" cy="143.5" r="2" fill="#fff" opacity=".85"/></g>
            <path d="M166 128l-7-4M234 128l7-4" stroke="#3b1d5a" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M166 119Q177 112 188 118M212 118Q223 112 234 119" stroke="#3b2552" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <path d="M199 150q1.5 3.5 4 .5" stroke="#e8a98a" stroke-width="2" fill="none" stroke-linecap="round"/>
            <path d="M189 160Q200 177 211 160Z" fill="#e11d48"/>
            <ellipse cx="200" cy="168" rx="6" ry="3.4" fill="#fb7185"/>

            <!-- the arm that receives: held out in front, palm up -->
            <path d="M262 230Q292 258 280 296" stroke="#c4b5fd" stroke-width="34" stroke-linecap="round" fill="none"/>
            <path d="M262 230Q292 258 280 296" stroke="#fff" stroke-width="28" stroke-linecap="round" fill="none"/>
            <path d="M280 296Q248 308 216 318" stroke="#c4b5fd" stroke-width="32" stroke-linecap="round" fill="none"/>
            <path d="M280 296Q248 308 216 318" stroke="#fff" stroke-width="26" stroke-linecap="round" fill="none"/>
            <ellipse cx="204" cy="322" rx="21" ry="8.5" fill="#ffe2c6"/>
            <ellipse cx="190" cy="315.5" rx="9" ry="4.6" fill="#ffe2c6" transform="rotate(-24 190 315.5)"/>
            <path d="M192 324q12 3 24-1" stroke="#f0c19c" stroke-width="1.6" fill="none" stroke-linecap="round"/>

            <!-- the arm that pours: upper arm stays, forearm lifts a little, the jar tips about the hand -->
            <path d="M140 230Q114 260 124 298" stroke="#c4b5fd" stroke-width="34" stroke-linecap="round" fill="none"/>
            <path d="M140 230Q114 260 124 298" stroke="#fff" stroke-width="28" stroke-linecap="round" fill="none"/>
            <g class="forearm">
              <path d="M124 298Q146 296 150 278" stroke="#c4b5fd" stroke-width="32" stroke-linecap="round" fill="none"/>
              <path d="M124 298Q146 296 150 278" stroke="#fff" stroke-width="26" stroke-linecap="round" fill="none"/>
              <g class="jar">
                <rect x="126" y="228" width="48" height="62" rx="9" fill="url(#rxJar)"/>
                <rect x="132" y="218" width="36" height="14" rx="4" fill="#b45309"/>
                <rect x="130" y="242" width="40" height="30" rx="4" fill="#fff"/>
                <path d="M150 248v18M141 257h18" stroke="#7c3aed" stroke-width="5" stroke-linecap="round"/>
                <path d="M132 236v48" stroke="#fff" stroke-width="3" opacity=".35" stroke-linecap="round"/>
                <circle cx="150" cy="278" r="13" fill="#ffe2c6"/>
              </g>
            </g>
          </g>

          <!-- pills poured into her palm -->
          <g class="pill" style="--d:1.55s;--tx:-12px;--ty:33px;--rot:420deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#dc2626"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.62s;--tx:4px;--ty:30px;--rot:-380deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#059669"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.69s;--tx:12px;--ty:35px;--rot:460deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#4f46e5"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.76s;--tx:-4px;--ty:31px;--rot:-420deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#0891b2"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.83s;--tx:8px;--ty:36px;--rot:360deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#dc2626"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.90s;--tx:-9px;--ty:30px;--rot:-460deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#059669"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:1.97s;--tx:0px;--ty:34px;--rot:400deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#4f46e5"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:2.04s;--tx:14px;--ty:32px;--rot:-360deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#0891b2"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:2.11s;--tx:-6px;--ty:36px;--rot:440deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#dc2626"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>
          <g class="pill" style="--d:2.18s;--tx:6px;--ty:29px;--rot:-400deg" transform="translate(203 280)">
            <g class="pill-x"><g class="pill-y"><g class="pill-r">
              <rect x="-9" y="-4.5" width="18" height="9" rx="4.5" fill="#059669"/>
              <path d="M0 -4.5H4.5a4.5 4.5 0 0 1 0 9H0Z" fill="#fff" opacity=".85"/>
            </g></g></g>
          </g>

          <!-- "RDU" pops out of the palm -->
          <g class="badge">
            <path d="M212 289 205 312 232 289Z" fill="url(#rxBadge)"/>
            <rect x="180" y="238" width="108" height="52" rx="22" fill="url(#rxBadge)"/>
            <text x="234" y="274" text-anchor="middle" font-family="Inter, 'IBM Plex Sans Thai', system-ui, sans-serif" font-size="32" font-weight="800" fill="#fff" letter-spacing="2">RDU</text>
          </g>
          <g class="sp" style="--s:0s" transform="translate(300 232)"><path d="M0-9 2.5-2.5 9 0 2.5 2.5 0 9-2.5 2.5-9 0-2.5-2.5Z" fill="#fbbf24"/></g>
          <g class="sp" style="--s:.12s" transform="translate(178 232)"><path d="M0-7 2-2 7 0 2 2 0 7-2 2-7 0-2-2Z" fill="#a78bfa"/></g>
          <g class="sp" style="--s:.22s" transform="translate(308 274)"><path d="M0-6 1.7-1.7 6 0 1.7 1.7 0 6-1.7 1.7-6 0-1.7-1.7Z" fill="#a78bfa"/></g>
        </svg>
      </div>
    </div>

    {{-- Login card --}}
    <div class="login-wrap">
      <div class="card">
        <div class="card-head">
          <div class="card-icon">
            <x-facility-logo :facility="$facility" />
          </div>
          <h4>เข้าสู่ระบบ</h4>
          <p>{{ $facility['configured'] ? 'สำหรับเจ้าหน้าที่'.$facility['name'] : 'สำหรับเจ้าหน้าที่' }}</p>
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
  <span>&copy; {{ now()->year + 543 }} {{ $facility['configured'] ? $facility['title'].' | ' : '' }}RDU Dashboard v1.0</span>
  <span class="dev">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <rect x="2" y="3" width="20" height="14" rx="2"/>
      <path d="M8 21h8M12 17v4"/>
    </svg>
    พัฒนาโดย : <b>นายณัฐพงษ์ นิลคง</b>
    นักวิชาการคอมพิวเตอร์ งานเทคโนโลยีสารสนเทศ รพ.เชียงกลาง
  </span>
</footer>

<script>
// Vital-sign monitor along the bottom of the hero (see .vital in the CSS)
(function () {
    const box = document.querySelector('.vital');
    if (!box) return;
    const cv = box.querySelector('canvas'), dot = box.querySelector('.vital-dot'), ctx = cv.getContext('2d');
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const SPEED = 0.075, LIFE = 12, GROUP = 16, GAP = 36;   // screen widths per second (one sweep = ~13 s), trace lifetime (s), columns per stroke, erase gap (px)
    let W, H, base, ys, born, beats, head, last = 0, sweepStart = 0;

    function newSweep(now) {
        beats = [];
        let x = W * 0.2 + Math.random() * 40;                    // flat line first, then the first beat
        while (x < W - 170) { beats.push({ x: x, k: 0.45 + Math.random() * 0.65 }); x += 210 + Math.random() * 90; }
        head = 0;
        sweepStart = now;
    }
    function yAt(x) {
        for (const b of beats) {
            const u = x - b.x, k = b.k;
            if (u < 0 || u >= 160) continue;
            if (u < 20)  return base - 10 * k * Math.sin(Math.PI * u / 20);              // P
            if (u < 46)  return base;
            if (u < 54)  return base + 6 * k * (u - 46) / 8;                              // Q
            if (u < 66)  return base + 6 * k - 44 * k * (u - 54) / 12;                    // up to R
            if (u < 78)  return base - 38 * k + 70 * k * (u - 66) / 12;                   // down to S
            if (u < 88)  return base + 32 * k * (1 - (u - 78) / 10);
            if (u < 120) return base;
            return base - 18 * k * Math.sin(Math.PI * (u - 120) / 40);                   // T
        }
        return base;
    }
    function layout() {
        const r = box.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = r.width; H = r.height; base = H * 0.56;
        cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = '#7c3aed'; ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        ys = new Float32Array(Math.ceil(W) + 2);
        born = new Float64Array(ys.length).fill(-1e9);
        newSweep(performance.now() / 1000);
        if (still) {                                              // no motion: one finished trace
            for (let x = 0; x < ys.length; x++) { ys[x] = yAt(x); born[x] = sweepStart; }
            head = W; draw(sweepStart, true);
        }
    }
    function stroke(from, to, alpha) {
        ctx.globalAlpha = alpha;
        ctx.beginPath(); ctx.moveTo(from, ys[from]);
        for (let x = from + 1; x <= to; x++) ctx.lineTo(x, ys[x]);
        ctx.stroke();
    }
    function draw(t, whole) {
        ctx.clearRect(0, 0, W, H);
        const h = Math.floor(head), last = ys.length - 1;
        for (let g = 0; g < last; g += GROUP) {
            const to = Math.min(g + GROUP, last);
            if (!whole && to > head && g < head + GAP) continue;                       // the dot's own group and the erase gap
            const a = whole ? 1 : 1 - (t - born[g]) / LIFE;
            if (a > 0) stroke(g, to, a * a);
        }
        if (!whole) {
            const from = Math.floor(h / GROUP) * GROUP;                                // the freshly drawn part, right up to the dot
            if (born[from] > -1e8 && h > from) stroke(from, Math.min(h, last), 1);
        }
        ctx.globalAlpha = 1;
    }
    function frame(now) {
        const t = now / 1000, dt = Math.min(t - last, 0.05);
        last = t;
        const next = head + W * SPEED * dt;
        for (let x = Math.floor(head); x <= Math.floor(next) && x < ys.length; x++) { ys[x] = yAt(x); born[x] = t; }
        head = next;
        draw(t, false);
        dot.style.transform = 'translate3d(' + head + 'px,' + yAt(head) + 'px,0)';
        if (head >= W + 6) newSweep(t);
        requestAnimationFrame(frame);
    }
    layout();
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(layout).observe(box);
    if (!still) requestAnimationFrame(function (now) { last = now / 1000; frame(now); });
})();
</script>
<script>
// Click the pharmacist to watch the scene again
(function () {
    const stage = document.getElementById('rxStage');
    if (!stage) return;
    let busy = false;
    stage.addEventListener('click', function () {
        if (busy) return;
        busy = true;
        stage.classList.remove('play');
        void stage.getBoundingClientRect();
        stage.classList.add('play');
        setTimeout(function () { busy = false; }, 3800);
    });
})();
</script>
<script>
// Status numbers count up from 0 once the page is in (skipped when the user asked for less motion)
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('.st b').forEach(function (el) {
        const target = parseInt(el.textContent, 10);
        if (!target || target < 1) return;
        const start = performance.now() + 500, span = 900;
        el.textContent = '0';
        (function tick(now) {
            const t = Math.min(Math.max((now - start) / span, 0), 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(tick);
        })(performance.now());
    });
})();
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
