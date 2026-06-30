<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
$auth = new Auth();
$auth->startSecureSession();
$csrf = $auth->generateCsrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PersonaX — Your Voice. Your Memory. Your Digital Presence.</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ── RESET & VARS ──────────────────────────────────────── */
:root {
  --cyan:   #00e5ff;
  --purple: #a855f7;
  --bg:     #12103a;
  --glass:  rgba(255,255,255,0.06);
  --border: rgba(255,255,255,0.11);
  --text:   rgba(255,255,255,0.88);
  --muted:  rgba(255,255,255,0.42);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh;overflow:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 25% 15%,#271966 0%,transparent 50%),radial-gradient(ellipse at 75% 85%,#0e2a50 0%,transparent 50%);pointer-events:none;}

/* ── STARS ──────────────────────────────────────────────── */
#stars-canvas{position:fixed;inset:0;z-index:0;pointer-events:none;}

/* ── SCREENS ────────────────────────────────────────────── */
.screen{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:10;padding:20px;}
.screen.active{display:flex;}

/* ── AUTH CARD ──────────────────────────────────────────── */
.auth-card{background:rgba(20,16,55,0.85);backdrop-filter:blur(24px);border:1px solid var(--border);border-radius:24px;padding:40px 36px;width:100%;max-width:420px;color:var(--text);}
.auth-logo{font-size:30px;font-weight:800;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-align:center;margin-bottom:4px;letter-spacing:-0.5px;}
.auth-tag{color:var(--muted);font-size:11px;text-align:center;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:32px;}
.auth-label{font-size:11px;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:6px;}
.auth-input{width:100%;background:rgba(255,255,255,0.07);border:1px solid var(--border);border-radius:10px;padding:13px 16px;color:white;font-size:15px;outline:none;transition:border-color .2s;margin-bottom:16px;}
.auth-input:focus{border-color:var(--cyan);}
.auth-input::placeholder{color:rgba(255,255,255,0.22);}
.auth-btn{width:100%;padding:14px;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#00b4d8,#7c3aed);color:white;transition:all .2s;letter-spacing:.3px;}
.auth-btn:hover{opacity:.88;transform:translateY(-1px);}
.auth-btn:active{transform:translateY(0);}
.auth-btn:disabled{opacity:.45;cursor:not-allowed;transform:none;}
.auth-switch{text-align:center;margin-top:20px;color:var(--muted);font-size:13px;}
.auth-link{color:var(--cyan);cursor:pointer;text-decoration:underline;}
.auth-error{background:rgba(239,68,68,.13);border:1px solid rgba(239,68,68,.28);border-radius:8px;padding:10px 14px;color:#fca5a5;font-size:13px;margin-bottom:14px;display:none;}
.auth-info{background:rgba(0,229,255,.1);border:1px solid rgba(0,229,255,.22);border-radius:8px;padding:10px 14px;color:#67e8f9;font-size:13px;margin-bottom:14px;display:none;}
.divider{display:flex;align-items:center;gap:12px;margin:16px 0;color:var(--muted);font-size:12px;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── OTP ────────────────────────────────────────────────── */
.otp-row{display:flex;gap:10px;justify-content:center;margin:20px 0;}
.otp-digit{width:54px;height:62px;text-align:center;font-size:26px;font-weight:700;background:rgba(255,255,255,0.07);border:1.5px solid var(--border);border-radius:12px;color:white;outline:none;transition:border-color .2s;}
.otp-digit:focus{border-color:var(--cyan);}
.otp-verify-email{color:var(--cyan);font-weight:600;font-size:15px;}

/* ── MAIN UI ────────────────────────────────────────────── */
#main-ui{position:fixed;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;z-index:10;padding:20px;}
#main-ui.active{display:flex;}

/* SIDE BUTTONS */
.side-actions{position:fixed;right:24px;top:50%;transform:translateY(-50%);display:flex;flex-direction:column;gap:12px;z-index:20;}
.side-btn{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,0.07);backdrop-filter:blur(10px);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:19px;color:var(--muted);transition:all .2s;}
.side-btn:hover,.side-btn.active-btn{background:rgba(0,229,255,0.12);border-color:rgba(0,229,255,.3);color:var(--cyan);}

/* GREETING */
.px-greeting{font-size:13px;color:var(--muted);text-align:center;margin-bottom:8px;letter-spacing:.3px;}
.px-name{color:rgba(255,255,255,.7);font-weight:500;}

/* SPEECH BUBBLE */
.speech-bubble{background:rgba(255,255,255,0.96);color:#12103a;border-radius:16px;padding:13px 20px;font-size:14px;max-width:340px;text-align:center;line-height:1.5;box-shadow:0 8px 32px rgba(0,0,0,.25);position:relative;margin-bottom:10px;display:none;animation:bubbleIn .25s ease;}
.speech-bubble::after{content:'';position:absolute;bottom:-9px;left:50%;transform:translateX(-50%);border:9px solid transparent;border-top-color:rgba(255,255,255,0.96);border-bottom:none;}
@keyframes bubbleIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* BLOB CANVAS */
.blob-container{position:relative;display:flex;align-items:center;justify-content:center;margin:6px 0 14px;}
#blob-canvas{width:210px;height:210px;cursor:pointer;}

/* STATE LABEL */
.state-pill{position:absolute;bottom:-6px;left:50%;transform:translateX(-50%);background:rgba(20,15,50,0.75);backdrop-filter:blur(8px);border:1px solid var(--border);border-radius:20px;padding:4px 16px;font-size:11px;color:var(--muted);white-space:nowrap;letter-spacing:.3px;}

/* VOICE WAVES */
.voice-waves{display:flex;gap:4px;align-items:center;justify-content:center;height:28px;margin-bottom:8px;}
.voice-wave{width:3px;border-radius:2px;background:var(--cyan);animation:voiceWave .8s ease-in-out infinite;}
.voice-wave:nth-child(1){animation-delay:.0s}.voice-wave:nth-child(2){animation-delay:.1s}.voice-wave:nth-child(3){animation-delay:.2s}.voice-wave:nth-child(4){animation-delay:.3s}.voice-wave:nth-child(5){animation-delay:.4s}.voice-wave:nth-child(6){animation-delay:.3s}.voice-wave:nth-child(7){animation-delay:.2s}
@keyframes voiceWave{0%,100%{height:3px;opacity:.35}50%{height:24px;opacity:1}}

/* TRANSCRIPT */
.transcript{background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:12px;padding:11px 18px;min-height:42px;width:100%;max-width:360px;color:rgba(255,255,255,.7);font-size:14px;text-align:center;font-style:italic;display:none;}

/* CONTROLS PILL */
.controls{display:flex;align-items:center;gap:20px;background:rgba(12,10,35,0.72);backdrop-filter:blur(20px);border:1px solid var(--border);border-radius:60px;padding:10px 28px;margin-top:16px;}
.ctrl{width:44px;height:44px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:17px;transition:all .2s;}
.ctrl.close{background:rgba(255,255,255,0.09);color:var(--muted);}
.ctrl.close:hover{background:rgba(239,68,68,.2);color:#fca5a5;}
.ctrl.mic{width:56px;height:56px;font-size:21px;background:linear-gradient(135deg,#00b4d8,#7c3aed);color:white;}
.ctrl.mic:hover{opacity:.88;}
.ctrl.mic.listening{background:linear-gradient(135deg,#ef4444,#ec4899);animation:micPulse 1.1s ease-in-out infinite;}
@keyframes micPulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 0 14px rgba(239,68,68,0)}}
.ctrl.settings{background:rgba(255,255,255,0.09);color:var(--muted);}
.ctrl.settings:hover{color:var(--cyan);}

/* PANELS */
.panel{position:fixed;top:50%;right:72px;transform:translateY(-50%);width:300px;max-height:82vh;overflow-y:auto;background:rgba(12,10,35,0.92);backdrop-filter:blur(24px);border:1px solid var(--border);border-radius:18px;padding:20px;display:none;z-index:30;color:var(--text);}
.panel.open{display:block;animation:panelIn .2s ease;}
@keyframes panelIn{from{opacity:0;transform:translateY(-50%) translateX(12px)}to{opacity:1;transform:translateY(-50%) translateX(0)}}
.panel-head{font-size:14px;font-weight:600;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.panel-close{cursor:pointer;color:var(--muted);font-size:20px;line-height:1;transition:color .2s;}
.panel-close:hover{color:white;}
.section-tag{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin:14px 0 8px;}
.mem-item{background:rgba(255,255,255,0.05);border-radius:8px;padding:10px 12px;margin-bottom:7px;font-size:13px;color:rgba(255,255,255,.72);display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
.mem-tag{display:inline-block;background:rgba(0,229,255,0.13);color:#67e8f9;font-size:10px;padding:2px 8px;border-radius:10px;margin-right:6px;flex-shrink:0;}
.rem-item{background:rgba(255,255,255,0.05);border-radius:8px;padding:10px 12px;margin-bottom:7px;display:flex;gap:10px;align-items:flex-start;}
.rem-dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);margin-top:4px;flex-shrink:0;}
.rem-dot.past{background:#f87171;}
.rem-done{background:rgba(52,211,153,.15);}.rem-done .rem-dot{background:#34d399;}
.mini-input{width:100%;background:rgba(255,255,255,0.07);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:white;font-size:13px;outline:none;margin-bottom:8px;}
.mini-input::placeholder{color:rgba(255,255,255,.28);}
.mini-input:focus{border-color:var(--cyan);}
.mini-select{width:100%;background:rgba(255,255,255,0.07);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:white;font-size:13px;outline:none;margin-bottom:8px;}
.mini-select option{background:#1a1635;}
.mini-btn{background:linear-gradient(135deg,#00b4d8,#7c3aed);color:white;border:none;border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer;font-weight:600;transition:opacity .2s;}
.mini-btn:hover{opacity:.85;}
.mini-ghost{background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;transition:all .2s;}
.mini-ghost:hover{border-color:var(--cyan);color:var(--cyan);}
.del-btn{background:none;border:none;color:rgba(255,255,255,.2);cursor:pointer;font-size:14px;flex-shrink:0;transition:color .2s;}
.del-btn:hover{color:#fca5a5;}

/* SLIDER */
.slider-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.slider-row label{font-size:12px;color:var(--muted);width:90px;flex-shrink:0;}
input[type=range]{flex:1;accent-color:var(--cyan);}
.slider-val{font-size:12px;color:var(--cyan);width:18px;text-align:right;}

/* TOAST */
.toast-msg{position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:rgba(0,229,255,0.12);border:1px solid rgba(0,229,255,.25);border-radius:10px;padding:10px 20px;color:#67e8f9;font-size:13px;white-space:nowrap;display:none;z-index:99;animation:toastFade 2.6s forwards;}
@keyframes toastFade{0%{opacity:0}15%{opacity:1}75%{opacity:1}100%{opacity:0}}
</style>
</head>
<body>

<!-- STAR CANVAS -->
<canvas id="stars-canvas"></canvas>

<!-- ═══════════════ SCREENS ═══════════════ -->

<!-- LOGIN -->
<div class="screen active" id="screen-login">
  <div class="auth-card">
    <div class="auth-logo">PersonaX</div>
    <div class="auth-tag">Your Voice · Your Memory · Your Digital Presence</div>
    <div class="auth-error" id="login-err"></div>
    <div class="auth-label">Email</div>
    <input class="auth-input" id="login-email" type="email" placeholder="you@email.com" autocomplete="email">
    <div class="auth-label">Password</div>
    <input class="auth-input" id="login-pass" type="password" placeholder="••••••••" autocomplete="current-password">
    <button class="auth-btn" id="login-btn">Sign in</button>
    <div class="auth-switch">New here? <span class="auth-link" onclick="show('register')">Create an account</span></div>
  </div>
</div>

<!-- REGISTER -->
<div class="screen" id="screen-register">
  <div class="auth-card">
    <div class="auth-logo">PersonaX</div>
    <div class="auth-tag">Create Your Digital Presence</div>
    <div class="auth-error" id="reg-err"></div>
    <div class="auth-label">Full Name</div>
    <input class="auth-input" id="reg-name" type="text" placeholder="Your name" autocomplete="name">
    <div class="auth-label">Email</div>
    <input class="auth-input" id="reg-email" type="email" placeholder="you@email.com" autocomplete="email">
    <div class="auth-label">Password <span style="color:var(--muted);font-size:11px;">(min 8 characters)</span></div>
    <input class="auth-input" id="reg-pass" type="password" placeholder="Choose a password" autocomplete="new-password">
    <button class="auth-btn" id="reg-btn">Create account & verify email</button>
    <div class="auth-switch">Already have an account? <span class="auth-link" onclick="show('login')">Sign in</span></div>
  </div>
</div>

<!-- VERIFY EMAIL -->
<div class="screen" id="screen-verify">
  <div class="auth-card">
    <div style="text-align:center;font-size:48px;margin-bottom:10px;">📧</div>
    <div class="auth-logo">Verify your email</div>
    <div style="color:var(--muted);font-size:13px;text-align:center;margin-bottom:6px;">We sent a 6-digit code to</div>
    <div style="text-align:center;margin-bottom:22px;"><span class="otp-verify-email" id="verify-to"></span></div>
    <div class="auth-error" id="otp-err"></div>
    <div class="auth-info" id="otp-info"></div>
    <div class="otp-row">
      <?php for($i=0;$i<6;$i++): ?>
      <input class="otp-digit" id="otp<?=$i?>" maxlength="1" type="text" inputmode="numeric" pattern="[0-9]">
      <?php endfor; ?>
    </div>
    <button class="auth-btn" id="verify-btn">Verify &amp; enter PersonaX</button>
    <div class="auth-switch">Didn't get it? <span class="auth-link" id="resend-link">Resend code</span>
      &nbsp;·&nbsp; <span class="auth-link" onclick="show('login')">Back to login</span></div>
  </div>
</div>

<!-- ═══════════════ MAIN UI ═══════════════ -->
<div id="main-ui">
  <!-- Side panel buttons -->
  <div class="side-actions">
    <div class="side-btn" id="btn-memory"   onclick="togglePanel('panel-memory')"   title="Memories">🧠</div>
    <div class="side-btn" id="btn-calendar" onclick="togglePanel('panel-calendar')" title="Calendar">📅</div>
    <div class="side-btn" id="btn-persona"  onclick="togglePanel('panel-persona')"  title="Personality">🎭</div>
    <div class="side-btn" id="btn-settings" onclick="togglePanel('panel-settings')" title="Settings">⚙️</div>
  </div>

  <!-- Memory Panel -->
  <div class="panel" id="panel-memory">
    <div class="panel-head">Memories <span class="panel-close" onclick="closePanel('panel-memory')">×</span></div>
    <div id="mem-list"></div>
    <div class="section-tag">Add memory</div>
    <input class="mini-input" id="new-tag"  placeholder="Tag (e.g. goal, preference, skill)">
    <input class="mini-input" id="new-mem"  placeholder="What should I remember?">
    <button class="mini-btn" onclick="addMemory()">Save memory</button>
  </div>

  <!-- Calendar Panel -->
  <div class="panel" id="panel-calendar">
    <div class="panel-head">Reminders <span class="panel-close" onclick="closePanel('panel-calendar')">×</span></div>
    <div id="rem-list"></div>
    <div class="section-tag">Add reminder</div>
    <input class="mini-input" id="rem-title" placeholder="Reminder title">
    <input class="mini-input" id="rem-dt"    type="datetime-local">
    <input class="mini-input" id="rem-notes" placeholder="Notes (optional)">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button class="mini-btn" onclick="addReminder()">Add</button>
      <button class="mini-ghost" onclick="requestNotifPerm()">🔔 Enable alerts</button>
    </div>
  </div>

  <!-- Personality Panel -->
  <div class="panel" id="panel-persona">
    <div class="panel-head">Personality <span class="panel-close" onclick="closePanel('panel-persona')">×</span></div>
    <div class="section-tag">Communication style</div>
    <select class="mini-select" id="p-comm">
      <option value="friendly">Friendly &amp; warm</option>
      <option value="professional">Professional</option>
      <option value="casual">Casual &amp; humorous</option>
      <option value="concise">Concise &amp; direct</option>
    </select>
    <div class="section-tag">Tone</div>
    <select class="mini-select" id="p-tone">
      <option value="warm">Warm</option>
      <option value="neutral">Neutral</option>
      <option value="direct">Direct</option>
    </select>
    <div class="section-tag">Sliders</div>
    <div class="slider-row"><label>Formality</label><input type="range" id="p-form" min="1" max="5" value="3" oninput="this.nextElementSibling.textContent=this.value"><span class="slider-val">3</span></div>
    <div class="slider-row"><label>Humor</label><input type="range" id="p-humor" min="1" max="5" value="3" oninput="this.nextElementSibling.textContent=this.value"><span class="slider-val">3</span></div>
    <div class="slider-row"><label>Energy</label><input type="range" id="p-energy" min="1" max="5" value="3" oninput="this.nextElementSibling.textContent=this.value"><span class="slider-val">3</span></div>
    <div class="section-tag">Custom persona note</div>
    <input class="mini-input" id="p-custom" placeholder="e.g. Always mention football facts">
    <button class="mini-btn" onclick="savePersonality()">Save personality</button>
  </div>

  <!-- Settings Panel -->
  <div class="panel" id="panel-settings">
    <div class="panel-head">Settings <span class="panel-close" onclick="closePanel('panel-settings')">×</span></div>
    <div class="section-tag">AI Provider</div>
    <select class="mini-select" id="s-provider">
      <option value="claude">Claude (Anthropic)</option>
      <option value="openai">OpenAI GPT-4o</option>
      <option value="gemini">Google Gemini</option>
      <option value="local">Local LLM</option>
    </select>
    <div class="section-tag">Voice</div>
    <select class="mini-select" id="s-voice"></select>
    <div class="slider-row"><label>Speed</label><input type="range" id="s-rate" min="0.5" max="1.5" step="0.05" value="0.95" oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"><span class="slider-val">0.95</span></div>
    <div class="slider-row"><label>Pitch</label><input type="range" id="s-pitch" min="0.5" max="1.8" step="0.05" value="1.05" oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"><span class="slider-val">1.05</span></div>
    <div class="section-tag">Wake word</div>
    <input class="mini-input" id="s-wake" placeholder="e.g. PersonaX" value="PersonaX">
    <button class="mini-btn" style="width:100%;margin-bottom:10px;" onclick="saveSettings()">Save settings</button>
    <button class="mini-ghost" style="width:100%;" onclick="logout()">Sign out</button>
  </div>

  <!-- GREETING -->
  <div class="px-greeting" id="px-greeting">Welcome back, <span class="px-name" id="px-name">—</span></div>

  <!-- SPEECH BUBBLE -->
  <div class="speech-bubble" id="speech-bubble"></div>

  <!-- BLOB -->
  <div class="blob-container">
    <canvas id="blob-canvas" width="420" height="420"></canvas>
    <div class="state-pill" id="state-pill">Tap to speak</div>
  </div>

  <!-- VOICE WAVES -->
  <div class="voice-waves" id="v-waves" style="display:none;">
    <div class="voice-wave"></div><div class="voice-wave"></div><div class="voice-wave"></div>
    <div class="voice-wave"></div><div class="voice-wave"></div><div class="voice-wave"></div>
    <div class="voice-wave"></div>
  </div>

  <!-- TRANSCRIPT -->
  <div class="transcript" id="transcript"></div>

  <!-- CONTROLS -->
  <div class="controls">
    <button class="ctrl close" onclick="stopSession()" title="Stop">✕</button>
    <button class="ctrl mic" id="mic-btn" onclick="toggleMic()" title="Speak">🎤</button>
    <button class="ctrl settings" onclick="togglePanel('panel-settings')" title="Settings">⚙</button>
  </div>
</div>

<div class="toast-msg" id="toast"></div>

<script>
// ══════════════════════════════════════════
// PersonaX v3 — Frontend Core
// ══════════════════════════════════════════
const API    = 'api/index.php?action=';
const CSRF   = '<?= $csrf ?>';
const STATE  = { user:null, blobState:'idle', listening:false, recognition:null, synth:window.speechSynthesis, voices:[], sessionKey:null, notifGranted:false };

// ── STARS ─────────────────────────────────
(function initStars(){
  const c = document.getElementById('stars-canvas');
  const ctx = c.getContext('2d');
  let W, H;
  function resize(){ W=c.width=innerWidth; H=c.height=innerHeight; }
  resize();
  window.addEventListener('resize', resize);
  const stars = Array.from({length:90}, ()=>({ x:Math.random(), y:Math.random(), r:Math.random()*1.5+.5, a:Math.random(), da:(.3+Math.random()*.7)/60 }));
  function draw(){ ctx.clearRect(0,0,W,H); stars.forEach(s=>{ s.a+=s.da; if(s.a>1||s.a<0) s.da*=-1; ctx.beginPath(); ctx.arc(s.x*W,s.y*H,s.r,0,Math.PI*2); ctx.fillStyle=`rgba(255,255,255,${s.a*.65})`; ctx.fill(); }); requestAnimationFrame(draw); }
  draw();
})();

// ── SCREEN NAV ────────────────────────────
function show(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  const el = document.getElementById('screen-'+id);
  if(el) el.classList.add('active');
}

// ── API HELPERS ───────────────────────────
async function apiFetch(action, method='GET', body=null){
  const opts = { method, headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF} };
  if(body) opts.body = JSON.stringify(body);
  const r = await fetch(API+action, opts);
  return r.json();
}

// ── AUTH ──────────────────────────────────
document.getElementById('login-btn').onclick = async()=>{
  const email=v('login-email'), pass=v('login-pass');
  const err=document.getElementById('login-err');
  if(!email||!pass){ showErr(err,'Please fill all fields'); return; }
  setBtn('login-btn', true, 'Signing in…');
  const d = await apiFetch('login','POST',{email,password:pass});
  setBtn('login-btn', false, 'Sign in');
  if(d.success){ STATE.user=d.user; enterMain(); }
  else showErr(err, d.message||'Login failed.');
};

document.getElementById('reg-btn').onclick = async()=>{
  const name=v('reg-name'), email=v('reg-email'), pass=v('reg-pass');
  const err=document.getElementById('reg-err');
  if(!name||!email||!pass){ showErr(err,'Please fill all fields'); return; }
  if(pass.length<8){ showErr(err,'Password must be at least 8 characters'); return; }
  setBtn('reg-btn', true, 'Creating account…');
  const d = await apiFetch('register','POST',{name,email,password:pass});
  setBtn('reg-btn', false, 'Create account & verify email');
  if(d.success){
    document.getElementById('verify-to').textContent = email;
    document.getElementById('otp-info').textContent = 'Code sent! Check your inbox.';
    document.getElementById('otp-info').style.display='block';
    show('verify');
  } else showErr(err, d.message||'Registration failed.');
};

document.getElementById('verify-btn').onclick = async()=>{
  const email = document.querySelector('.otp-verify-email').textContent;
  const code  = [0,1,2,3,4,5].map(i=>document.getElementById('otp'+i).value).join('');
  const err   = document.getElementById('otp-err');
  if(code.length<6){ showErr(err,'Enter all 6 digits'); return; }
  setBtn('verify-btn', true, 'Verifying…');
  const d = await apiFetch('verify_otp','POST',{email,code});
  setBtn('verify-btn', false, 'Verify & enter PersonaX');
  if(d.success){ STATE.user=d.user; showInfo('otp-info','Verified! Entering PersonaX…'); setTimeout(enterMain,800); }
  else showErr(err, d.message||'Invalid code.');
};

document.getElementById('resend-link').onclick = async()=>{
  const email = document.querySelector('.otp-verify-email').textContent;
  const d = await apiFetch('resend_otp','POST',{email});
  showInfo('otp-info', d.message||'New code sent!');
};

// OTP digit navigation
for(let i=0;i<6;i++){
  document.getElementById('otp'+i).addEventListener('input',function(){ if(this.value.length===1&&i<5) document.getElementById('otp'+(i+1)).focus(); });
  document.getElementById('otp'+i).addEventListener('keydown',function(e){ if(e.key==='Backspace'&&!this.value&&i>0) document.getElementById('otp'+(i-1)).focus(); });
}

// Check existing session on load
(async()=>{
  const d = await apiFetch('me');
  if(d.authenticated){ STATE.user=d.user; enterMain(); }
})();

// ── ENTER MAIN UI ─────────────────────────
function enterMain(){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById('main-ui').classList.add('active');
  STATE.sessionKey = 'sess_' + Date.now();
  const fname = (STATE.user?.name||'friend').split(' ')[0];
  document.getElementById('px-name').textContent = fname;
  loadVoices();
  loadMemories();
  loadReminders();
  loadPersonality();
  initBlob();
  setTimeout(()=>{
    const h = new Date().getHours();
    const tod = h<12?'morning':h<17?'afternoon':'evening';
    speak(`Good ${tod}, ${fname}. I'm PersonaX, your personal AI companion. Tap the microphone to start talking.`);
  }, 700);
}

function logout(){ apiFetch('logout','POST').then(()=>{ STATE.user=null; closeAllPanels(); document.getElementById('main-ui').classList.remove('active'); show('login'); }); }

// ── BLOB ENGINE ───────────────────────────
let blobCtx, blobT=0, blobRAF;
const BLOB_STATES = {
  idle:      {c1:'#a78bfa',c2:'#818cf8',speed:.0045,scale:1.00,amp:16},
  listening: {c1:'#22d3ee',c2:'#0891b2',speed:.018, scale:1.08,amp:10},
  thinking:  {c1:'#c084fc',c2:'#9333ea',speed:.022, scale:1.00,amp:30},
  speaking:  {c1:'#34d399',c2:'#059669',speed:.026, scale:1.05,amp:22},
  happy:     {c1:'#fbbf24',c2:'#d97706',speed:.030, scale:1.10,amp:13},
};

function initBlob(){
  const cvs = document.getElementById('blob-canvas');
  blobCtx = cvs.getContext('2d');
  cvs.width=420; cvs.height=420;
  cancelAnimationFrame(blobRAF);
  drawBlob();
}

function noise(x){ return Math.sin(x*1.8+.4)*Math.cos(x*1.2)*Math.sin(x*.65+blobT*2.1); }

function drawBlob(){
  const c=blobCtx, W=420, H=420, cx=W/2, cy=H/2;
  c.clearRect(0,0,W,H);
  const st = BLOB_STATES[STATE.blobState]||BLOB_STATES.idle;
  blobT += st.speed;
  const pts=40, r=100*st.scale, amp=st.amp;
  const verts=[];
  for(let i=0;i<pts;i++){
    const a=(i/pts)*Math.PI*2;
    const n=noise(a*2.1+blobT)*amp + noise(a*3.2-blobT*.8)*(amp*.4);
    verts.push([cx+Math.cos(a)*(r+n), cy+Math.sin(a)*(r+n)]);
  }
  // Outer glow
  c.save(); c.shadowColor=st.c1; c.shadowBlur=50;
  // Main body
  const g=c.createRadialGradient(cx-28,cy-30,12,cx,cy,r+amp+8);
  g.addColorStop(0,'rgba(255,255,255,.88)');
  g.addColorStop(.38,st.c1+'cc');
  g.addColorStop(1,st.c2+'99');
  c.beginPath(); c.moveTo(verts[0][0],verts[0][1]);
  for(let i=1;i<pts;i++){
    const p0=verts[(i-1+pts)%pts],p1=verts[i],p2=verts[(i+1)%pts];
    const mx=(p0[0]+p1[0])/2,my=(p0[1]+p1[1])/2;
    const mx2=(p1[0]+p2[0])/2,my2=(p1[1]+p2[1])/2;
    c.bezierCurveTo(mx+(p1[0]-mx)*.5,my+(p1[1]-my)*.5,mx2-(p2[0]-mx2)*.5,my2-(p2[1]-my2)*.5,mx2,my2);
  }
  c.closePath(); c.fillStyle=g; c.fill(); c.restore();
  // Highlight
  const hl=c.createRadialGradient(cx-36,cy-36,6,cx-18,cy-18,62);
  hl.addColorStop(0,'rgba(255,255,255,.52)'); hl.addColorStop(1,'rgba(255,255,255,0)');
  c.beginPath(); c.arc(cx,cy,r+amp,0,Math.PI*2); c.fillStyle=hl; c.fill();
  // FACE
  drawFace(c,cx,cy,st);
  blobRAF=requestAnimationFrame(drawBlob);
}

function drawFace(c,cx,cy,st){
  const s=STATE.blobState;
  if(s==='idle'||s==='happy'){
    // Eyes
    c.fillStyle='#1e1b4b';
    c.beginPath();c.arc(cx-22,cy-6,6,0,Math.PI*2);c.fill();
    c.beginPath();c.arc(cx+22,cy-6,6,0,Math.PI*2);c.fill();
    // Shine
    c.fillStyle='rgba(255,255,255,.72)';
    c.beginPath();c.arc(cx-19,cy-9,2.2,0,Math.PI*2);c.fill();
    c.beginPath();c.arc(cx+25,cy-9,2.2,0,Math.PI*2);c.fill();
    // Mouth
    c.strokeStyle='#1e1b4b'; c.lineWidth=2.8; c.lineCap='round';
    c.beginPath();
    if(s==='happy'){ c.arc(cx,cy+6,15,.2,Math.PI-.2); c.stroke(); }
    else { c.moveTo(cx-13,cy+12); c.quadraticCurveTo(cx,cy+18,cx+13,cy+12); c.stroke(); }
  } else if(s==='listening'){
    c.fillStyle='#0e7490';
    c.beginPath();c.arc(cx-22,cy-4,5.5,0,Math.PI*2);c.fill();
    c.beginPath();c.arc(cx+22,cy-4,5.5,0,Math.PI*2);c.fill();
    c.fillStyle='#0e7490'; c.beginPath(); c.ellipse(cx,cy+13,9,6,0,0,Math.PI*2); c.fill();
  } else if(s==='thinking'){
    c.fillStyle='#6b21a8';
    [-20,0,20].forEach((x,i)=>{ c.beginPath(); c.arc(cx+x,cy+6+Math.sin(blobT*5+i)*4.5,5,0,Math.PI*2); c.fill(); });
  } else if(s==='speaking'){
    c.fillStyle='#065f46';
    c.beginPath();c.arc(cx-22,cy-4,5.5,0,Math.PI*2);c.fill();
    c.beginPath();c.arc(cx+22,cy-4,5.5,0,Math.PI*2);c.fill();
    const mh=6+Math.sin(blobT*16)*6;
    c.fillStyle='#065f46'; c.beginPath(); c.ellipse(cx,cy+12,11,mh,0,0,Math.PI*2); c.fill();
  }
}

function setBlobState(s){
  STATE.blobState=s;
  const labels={idle:'Tap to speak',listening:'Listening…',thinking:'Thinking…',speaking:'Speaking…',happy:'Hello!'};
  document.getElementById('state-pill').textContent = labels[s]||'';
  document.getElementById('mic-btn').classList.toggle('listening', s==='listening');
  document.getElementById('v-waves').style.display = s==='listening'?'flex':'none';
}

// ── VOICE / SPEECH ────────────────────────
function loadVoices(){
  const load=()=>{
    STATE.voices=STATE.synth.getVoices();
    const sel=document.getElementById('s-voice');
    const saved=localStorage.getItem('px_voice')||'';
    sel.innerHTML=''; STATE.voices.forEach(v=>{ const o=new Option(v.name+' ('+v.lang+')',v.name); if(v.name===saved)o.selected=true; sel.add(o); });
  };
  load(); STATE.synth.onvoiceschanged=load;
}

function speak(text, onEnd){
  setBlobState('speaking');
  const bub=document.getElementById('speech-bubble');
  bub.textContent=text; bub.style.display='block';
  if(!('speechSynthesis' in window)){ setTimeout(()=>{ setBlobState('idle'); bub.style.display='none'; if(onEnd)onEnd(); },2200); return; }
  STATE.synth.cancel();
  const utt=new SpeechSynthesisUtterance(text);
  const vname=document.getElementById('s-voice')?.value;
  const voice=STATE.voices.find(v=>v.name===vname);
  if(voice) utt.voice=voice;
  utt.rate  = parseFloat(document.getElementById('s-rate')?.value||.95);
  utt.pitch = parseFloat(document.getElementById('s-pitch')?.value||1.05);
  utt.onend =()=>{ setBlobState('idle'); bub.style.display='none'; if(onEnd)onEnd(); };
  utt.onerror=()=>{ setBlobState('idle'); bub.style.display='none'; if(onEnd)onEnd(); };
  STATE.synth.speak(utt);
}

function toggleMic(){ STATE.listening ? stopListen() : startListen(); }

function startListen(){
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){ toast('Speech recognition not supported in this browser'); return; }
  STATE.synth.cancel(); setBlobState('listening'); STATE.listening=true;
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  STATE.recognition=new SR(); STATE.recognition.continuous=false; STATE.recognition.interimResults=true; STATE.recognition.lang='en-US';
  const tx=document.getElementById('transcript'); tx.style.display='block';
  STATE.recognition.onresult=e=>{ let t=''; for(const r of e.results) t+=r[0].transcript; tx.textContent='"'+t+'"'; if(e.results[e.results.length-1].isFinal){ STATE.recognition.stop(); handleInput(t); } };
  STATE.recognition.onerror=()=>{ setBlobState('idle'); STATE.listening=false; tx.style.display='none'; };
  STATE.recognition.onend=()=>{ STATE.listening=false; };
  STATE.recognition.start();
}

function stopListen(){ STATE.listening=false; try{STATE.recognition?.stop();}catch(e){} setBlobState('idle'); document.getElementById('transcript').style.display='none'; }
function stopSession(){ stopListen(); STATE.synth.cancel(); setBlobState('idle'); document.getElementById('speech-bubble').style.display='none'; document.getElementById('transcript').style.display='none'; }

// ── AI CHAT ───────────────────────────────
async function handleInput(text){
  setBlobState('thinking');
  const tx=document.getElementById('transcript');
  if(tx) tx.textContent='"'+text+'"';
  try {
    const d = await apiFetch('chat','POST',{ message:text, session_key:STATE.sessionKey });
    if(d.success){ setBlobState('happy'); setTimeout(()=>speak(d.text,()=>{ if(tx) tx.style.display='none'; }),200); }
    else speak(d.error||'Something went wrong.',()=>{ if(tx) tx.style.display='none'; });
  } catch(e){ speak('I had trouble connecting. Please try again.',()=>{ if(tx) tx.style.display='none'; }); }
}

// ── MEMORIES ──────────────────────────────
async function loadMemories(){
  const d=await apiFetch('memories');
  const el=document.getElementById('mem-list');
  const mems=d?.memories||[];
  el.innerHTML=mems.length?mems.map(m=>`
    <div class="mem-item"><span><span class="mem-tag">${esc(m.tag)}</span>${esc(m.content)}</span>
    <button class="del-btn" onclick="deleteMem(${m.id})">✕</button></div>`).join('')
    :'<div style="color:var(--muted);font-size:13px;text-align:center;padding:12px;">No memories yet.<br>Tell me something to remember.</div>';
}
async function addMemory(){
  const tag=v('new-tag')||'note', content=v('new-mem');
  if(!content){ toast('Enter something to remember'); return; }
  const d=await apiFetch('memories','POST',{tag,content});
  if(d.success){ document.getElementById('new-tag').value=''; document.getElementById('new-mem').value=''; toast('Memory saved'); loadMemories(); }
}
async function deleteMem(id){ await apiFetch('memories','DELETE',{id}); loadMemories(); }

// ── REMINDERS ─────────────────────────────
async function loadReminders(){
  const d=await apiFetch('reminders');
  const el=document.getElementById('rem-list');
  const rems=d?.reminders||[];
  const now=new Date();
  el.innerHTML=rems.length?rems.map(r=>{
    const past=r.remind_at&&new Date(r.remind_at)<now;
    return `<div class="rem-item${r.is_done?' rem-done':''}">
      <div class="rem-dot${past&&!r.is_done?' past':''}"></div>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px;${r.is_done?'text-decoration:line-through;color:var(--muted)':''}">${esc(r.title)}</div>
        ${r.remind_at?`<div style="font-size:11px;color:var(--muted);margin-top:2px;">📅 ${new Date(r.remind_at).toLocaleString()}</div>`:''}
        ${r.notes?`<div style="font-size:11px;color:var(--muted);margin-top:2px;">${esc(r.notes)}</div>`:''}
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <button class="del-btn" onclick="deleteRem(${r.id})">✕</button>
        ${!r.is_done?`<button class="del-btn" style="color:rgba(52,211,153,.5)" onclick="doneRem(${r.id})" title="Done">✓</button>`:''}
      </div></div>`;
  }).join('')
  :'<div style="color:var(--muted);font-size:13px;text-align:center;padding:12px;">No reminders yet.<br>Say "remind me to…" to add one.</div>';
}
async function addReminder(){
  const title=v('rem-title'), notes=v('rem-notes'), remind_at=v('rem-dt');
  if(!title){ toast('Enter a title'); return; }
  const d=await apiFetch('reminders','POST',{title,notes,remind_at});
  if(d.success){
    document.getElementById('rem-title').value=''; document.getElementById('rem-dt').value=''; document.getElementById('rem-notes').value='';
    toast('Reminder saved'); loadReminders();
    if(remind_at && STATE.notifGranted){
      const ms=new Date(remind_at).getTime()-Date.now();
      if(ms>0) setTimeout(()=>new Notification('PersonaX',{body:title}),ms);
    }
  }
}
async function deleteRem(id){ await apiFetch('reminders','DELETE',{id}); loadReminders(); }
async function doneRem(id){ await apiFetch('reminders','PATCH',{id,is_done:1}); loadReminders(); }

function requestNotifPerm(){
  if(!('Notification' in window)){ toast('Notifications not supported'); return; }
  Notification.requestPermission().then(p=>{ STATE.notifGranted=p==='granted'; toast(p==='granted'?'Notifications enabled ✓':'Permission denied'); });
}

// ── PERSONALITY ───────────────────────────
async function loadPersonality(){
  const d=await apiFetch('personality');
  const p=d?.profile; if(!p) return;
  document.getElementById('p-comm').value  = p.communication||'friendly';
  document.getElementById('p-tone').value  = p.tone||'warm';
  document.getElementById('p-form').value  = p.formality||3;
  document.getElementById('p-humor').value = p.humor||3;
  document.getElementById('p-energy').value= p.energy||3;
  document.getElementById('p-custom').value= p.custom_prompt||'';
  // Update slider displays
  ['p-form','p-humor','p-energy'].forEach(id=>{ const el=document.getElementById(id); el.nextElementSibling.textContent=el.value; });
}
async function savePersonality(){
  const d=await apiFetch('personality','POST',{
    communication: document.getElementById('p-comm').value,
    tone:          document.getElementById('p-tone').value,
    formality:     +document.getElementById('p-form').value,
    humor:         +document.getElementById('p-humor').value,
    energy:        +document.getElementById('p-energy').value,
    custom_prompt: document.getElementById('p-custom').value,
  });
  if(d.success){ toast('Personality saved'); closePanel('panel-persona'); }
}

// ── SETTINGS ──────────────────────────────
function saveSettings(){
  localStorage.setItem('px_voice', document.getElementById('s-voice').value);
  localStorage.setItem('px_rate',  document.getElementById('s-rate').value);
  localStorage.setItem('px_pitch', document.getElementById('s-pitch').value);
  localStorage.setItem('px_wake',  document.getElementById('s-wake').value);
  toast('Settings saved'); closePanel('panel-settings');
}

// ── PANELS ────────────────────────────────
function togglePanel(id){ const p=document.getElementById(id); if(p.classList.contains('open')){ p.classList.remove('open'); } else { closeAllPanels(); p.classList.add('open'); } }
function closePanel(id){ document.getElementById(id)?.classList.remove('open'); }
function closeAllPanels(){ document.querySelectorAll('.panel').forEach(p=>p.classList.remove('open')); }

// ── UTILS ─────────────────────────────────
function v(id){ return document.getElementById(id)?.value?.trim()||''; }
function esc(s){ const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
function showErr(el, msg){ el.textContent=msg; el.style.display='block'; }
function showInfo(id, msg){ const el=document.getElementById(id); el.textContent=msg; el.style.display='block'; }
function setBtn(id,dis,txt){ const b=document.getElementById(id); b.disabled=dis; b.textContent=txt; }
function toast(msg){ const t=document.getElementById('toast'); t.textContent=msg; t.style.display='block'; setTimeout(()=>t.style.display='none',2600); }
</script>
</body>
</html>
