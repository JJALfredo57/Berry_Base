@extends('layouts.app')
@section('page_title','Platform Settings')

@push('styles')
<style>
  .backup-console {
    display:grid;
    grid-template-columns:minmax(0,1.1fr) minmax(260px,.9fr);
    gap:1rem;
    align-items:stretch;
  }
  .backup-panel {
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    background:#fff;
    box-shadow:0 10px 26px rgba(15,23,42,.055);
  }
  .backup-action {
    padding:1.25rem;
  }
  .backup-icon {
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:#eef2ff;
    color:#3730a3;
    font-size:1.15rem;
    flex-shrink:0;
  }
  .backup-meta {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.65rem;
    margin-top:1rem;
  }
  .backup-meta-box {
    padding:.75rem;
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    background:#f8fafc;
  }
  .backup-file-row {
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:1rem;
    align-items:center;
    padding:1rem 0;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .backup-file-row:last-child { border-bottom:0; padding-bottom:0; }
  .backup-file-name {
    font-weight:700;
    line-height:1.2;
    color:#0f172a;
    overflow-wrap:anywhere;
  }
  .backup-file-sub {
    color:#64748b;
    font-size:.78rem;
    margin-top:.2rem;
  }
  .backup-actions {
    display:flex;
    gap:.45rem;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  @media (max-width: 991.98px) {
    .backup-console { grid-template-columns:1fr; }
  }
  @media (max-width: 575.98px) {
    .backup-action { padding:1rem; }
    .backup-meta { grid-template-columns:1fr; }
    .backup-file-row { grid-template-columns:1fr; }
    .backup-actions { justify-content:flex-start; }
  }
</style>
@endpush

@section('content')
<div>
  <div style="margin-bottom:1.5rem">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Platform Settings</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Manage platform-wide configuration</p>
  </div>

  @if(session('msg'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-check-circle-fill flex-shrink-0"></i><span>{{ session('msg') }}</span>
    </div>
  @endif
  @if(session('err'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span>{{ session('err') }}</span>
    </div>
  @endif
  @foreach($errors->all() as $e)
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-exclamation-circle flex-shrink-0"></i><span>{{ $e }}</span>
    </div>
  @endforeach

  {{-- Tabs --}}
  @php
    $tabs = [
      'platform' => '🏢 Platform',
      'paymongo' => '💳 PayMongo',
      'sms'      => '📱 UniSMS',
      'logs'     => '📋 Activity Logs',
      'backup'   => '💾 Backup',
    ];
  @endphp
  <ul class="nav nav-tabs mb-4 border-0 gap-1 flex-wrap">
    @foreach($tabs as $key => $label)
    <li class="nav-item">
      <a class="nav-link px-3 py-2 {{ $tab === $key ? 'active fw-semibold' : 'text-muted' }}"
         href="{{ route('superadmin.settings', ['tab' => $key]) }}"
         style="{{ $tab === $key ? 'border-bottom:2px solid var(--primary);color:var(--primary)!important;background:transparent' : 'border:0' }}">
        {{ $label }}
      </a>
    </li>
    @endforeach
  </ul>

  {{-- ── PLATFORM TAB ─────────────────────────────────────────────── --}}
  @if($tab === 'platform')
  <form action="{{ route('superadmin.settings.update') }}" method="POST" enctype="multipart/form-data" novalidate>
    @csrf
    <div class="row g-4">
      <div class="col-lg-7">
        <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
          <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100)">
            <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
              <i class="bi bi-shop" style="color:var(--primary)"></i> Platform Branding
            </span>
          </div>
          <div style="padding:1.5rem">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Platform Name <span style="color:var(--danger)">*</span></label>
                <input type="text" class="form-control" name="platform_name"
                       value="{{ $platform->platform_name ?? 'Cake Shop Platform' }}"
                       required minlength="3" maxlength="100">
              </div>
              <div class="col-12">
                <label class="form-label">Tagline <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
                <input type="text" class="form-control" name="platform_tagline"
                       value="{{ $platform->platform_tagline ?? '' }}"
                       placeholder="e.g. Your local cake shop" maxlength="200">
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Email</label>
                <input type="email" class="form-control" name="platform_email"
                       value="{{ $platform->platform_email ?? '' }}" placeholder="admin@platform.com">
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Phone</label>
                <input type="text" class="form-control" name="platform_phone"
                       value="{{ $platform->platform_phone ?? '' }}" placeholder="+63 9XX XXX XXXX">
              </div>
              <div class="col-md-6">
                <label class="form-label">Platform Logo</label>
                @if(!empty($platform->platform_logo))
                  <img src="{{ $platform->platform_logo }}" style="display:block;height:48px;margin-bottom:.5rem;border-radius:var(--radius-sm)">
                @endif
                <input type="file" class="form-control" name="platform_logo" accept=".jpg,.jpeg,.png" style="font-size:.8rem">
                <div class="form-text">JPG or PNG. Max 3MB.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Theme Color</label>
                <div style="display:flex;align-items:center;gap:.75rem">
                  <input type="color" name="platform_primary_color" id="platformColorPicker"
                         value="{{ $platform->platform_primary_color ?? '#7B3A0F' }}"
                         style="width:48px;height:40px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer;background:#fff"
                         oninput="document.getElementById('platformColorHex').value=this.value;document.getElementById('platformColorPreview').style.background=this.value">
                  <input type="text" id="platformColorHex"
                         value="{{ $platform->platform_primary_color ?? '#7B3A0F' }}"
                         maxlength="7" class="form-control"
                         style="width:110px;font-family:monospace"
                         oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){document.getElementById('platformColorPicker').value=this.value;document.getElementById('platformColorPreview').style.background=this.value}">
                  <div id="platformColorPreview"
                       style="height:40px;flex:1;border-radius:var(--radius-sm);border:1.5px solid var(--gray-200);background:{{ $platform->platform_primary_color ?? '#7B3A0F' }};transition:background .15s"></div>
                </div>
                <div class="form-text">Applies to buttons, links, and accents across the entire platform.</div>
              </div>

              {{-- ── Dashboard Background ── --}}
              <div class="col-12">
                <div style="border-top:1.5px solid var(--gray-100);margin:.25rem 0 1rem"></div>
                <div style="font-size:.88rem;font-weight:700;color:var(--gray-900);margin-bottom:.75rem"><i class="bi bi-image me-1" style="color:var(--primary)"></i> Dashboard Background</div>

                {{-- Type Picker --}}
                @php $curBgType = $platform->platform_bg_type ?? 'color'; @endphp
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem">
                  @foreach(['color'=>'Solid Color','gradient'=>'Gradient','image'=>'Image'] as $bval => $blbl)
                  <label style="display:flex;align-items:center;gap:.4rem;font-size:.83rem;font-weight:600;cursor:pointer;padding:.45rem .9rem;border:1.5px solid {{ $curBgType===$bval ? 'var(--primary)' : 'var(--gray-200)' }};border-radius:var(--radius-md);background:{{ $curBgType===$bval ? 'var(--primary-bg,#fdf8f4)' : '#fff' }};color:{{ $curBgType===$bval ? 'var(--primary)' : 'var(--gray-700)' }}">
                    <input type="radio" name="platform_bg_type" value="{{ $bval }}" {{ $curBgType===$bval ? 'checked' : '' }}
                           style="accent-color:var(--primary)" onchange="switchPBgType('{{ $bval }}')"> {{ $blbl }}
                  </label>
                  @endforeach
                </div>

                {{-- Solid Color --}}
                <div id="pbg-color" style="display:{{ $curBgType==='color' ? 'flex' : 'none' }};align-items:center;gap:.75rem;margin-bottom:.75rem">
                  <input type="color" name="platform_bg_color" id="pbgColorPicker"
                         value="{{ $platform->platform_bg_color ?? '#FFF8F8' }}"
                         style="width:48px;height:40px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer"
                         oninput="document.getElementById('pbgColorHex').value=this.value;document.getElementById('pbgPreview').style.background=this.value">
                  <input type="text" id="pbgColorHex" value="{{ $platform->platform_bg_color ?? '#FFF8F8' }}"
                         maxlength="7" class="form-control" style="width:110px;font-family:monospace"
                         oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){document.getElementById('pbgColorPicker').value=this.value;document.getElementById('pbgPreview').style.background=this.value}">
                  <div class="form-text">Background color of the dashboard.</div>
                </div>

                {{-- Gradient --}}
                <div id="pbg-gradient" style="display:{{ $curBgType==='gradient' ? 'flex' : 'none' }};align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem">
                  <div style="display:flex;align-items:center;gap:.5rem">
                    <span class="form-text" style="white-space:nowrap;margin:0">From</span>
                    <input type="color" name="platform_bg_gradient_start" id="pbgGradStart"
                           value="{{ $platform->platform_bg_gradient_start ?? '#fff7fb' }}"
                           style="width:44px;height:38px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer"
                           oninput="updatePbgGradPreview()">
                  </div>
                  <div style="display:flex;align-items:center;gap:.5rem">
                    <span class="form-text" style="white-space:nowrap;margin:0">To</span>
                    <input type="color" name="platform_bg_gradient_end" id="pbgGradEnd"
                           value="{{ $platform->platform_bg_gradient_end ?? '#ffe3f1' }}"
                           style="width:44px;height:38px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer"
                           oninput="updatePbgGradPreview()">
                  </div>
                  <div class="form-text">135° diagonal gradient from left to right.</div>
                </div>

                {{-- Image --}}
                <div id="pbg-image" style="display:{{ $curBgType==='image' ? 'block' : 'none' }};margin-bottom:.75rem">
                  @if(!empty($platform->platform_bg_image))
                  <img src="{{ $platform->platform_bg_image }}" style="display:block;width:100%;max-width:340px;height:80px;object-fit:cover;border-radius:var(--radius-md);border:1.5px solid var(--gray-200);margin-bottom:.5rem">
                  @endif
                  <input type="file" class="form-control" name="platform_bg_image" accept=".jpg,.jpeg,.png,.webp" style="font-size:.8rem;max-width:340px">
                  <div class="form-text">JPG, PNG or WebP · Max 5 MB. Leave blank to keep current image.</div>
                  <div style="margin-top:.65rem">
                    <label class="form-label fw-semibold" style="font-size:.8rem">Image Opacity: <span id="pbgOpacityVal">{{ number_format(($platform->platform_bg_opacity ?? 1.0) * 100) }}%</span></label>
                    <input type="range" name="platform_bg_opacity" min="0.1" max="1" step="0.05"
                           value="{{ $platform->platform_bg_opacity ?? 1.0 }}"
                           style="width:100%;max-width:280px;accent-color:var(--primary)"
                           oninput="document.getElementById('pbgOpacityVal').textContent=Math.round(this.value*100)+'%'">
                  </div>
                </div>

                {{-- Live Preview --}}
                <div id="pbgPreview" style="height:52px;border-radius:var(--radius-md);border:1.5px solid var(--gray-200);transition:background .2s;
                  @if($curBgType==='gradient') background:linear-gradient(135deg,{{ $platform->platform_bg_gradient_start ?? '#fff7fb' }} 0%,{{ $platform->platform_bg_gradient_end ?? '#ffe3f1' }} 100%)
                  @elseif($curBgType==='image' && !empty($platform->platform_bg_image)) background:url('{{ $platform->platform_bg_image }}') center/cover no-repeat
                  @else background:{{ $platform->platform_bg_color ?? '#FFF8F8' }}
                  @endif
                "></div>
                <div class="form-text mt-1">Live preview of the dashboard background.</div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
              <i class="bi bi-save me-1"></i> Save Platform Settings
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  {{-- ── Developer Mode Card ──────────────────────────────────────── --}}
  <div class="row g-4 mt-0">
    <div class="col-lg-7">
      <div style="background:#fff;border-radius:var(--radius-lg);border:2px dashed rgba(245,158,11,0.45);overflow:hidden">
        <div style="padding:1rem 1.5rem;border-bottom:1px solid rgba(245,158,11,0.18);background:linear-gradient(90deg,#fffbeb,#fff)">
          <span style="font-size:.92rem;font-weight:700;color:#92400e;display:flex;align-items:center;gap:.6rem">
            <i class="bi bi-bug-fill" style="color:#d97706"></i>
            Developer Mode
            <span style="font-size:.65rem;font-weight:700;padding:2px 9px;background:#fef3c7;color:#b45309;border-radius:20px;border:1px solid #fde68a;letter-spacing:.04em">DEV TOOLS</span>
          </span>
        </div>
        <div style="padding:1.25rem 1.5rem">
          <form action="{{ route('superadmin.settings.dev_mode') }}" method="POST" id="devModeForm">
            @csrf
            <div style="display:flex;align-items:flex-start;gap:1.25rem;flex-wrap:wrap">
              <div style="flex:1;min-width:200px">
                <div style="font-weight:600;color:var(--gray-900);margin-bottom:.35rem">
                  OTP &amp; SMS Screen Preview
                </div>
                <div style="font-size:.8rem;color:var(--gray-500);line-height:1.65;margin-bottom:.6rem">
                  When <strong style="color:#16a34a">ON</strong> — OTP codes appear below the input field (with customer name &amp; number),
                  and SMS notifications flash at the top of the admin screen for 15 seconds.<br>
                  When <strong style="color:#dc2626">OFF</strong> — all previews are hidden.
                </div>
                <div style="font-size:.75rem;color:#d97706;font-weight:500;display:flex;align-items:center;gap:.3rem">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  Turn OFF in production — OTP codes become visible to anyone on the screen.
                </div>
              </div>
              <div style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding-top:.25rem">
                @php $devOn = !empty($platform->dev_mode); @endphp
                <div id="devModeStatusLabel" style="font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:{{ $devOn ? '#16a34a' : '#6b7280' }}">
                  {{ $devOn ? 'ON' : 'OFF' }}
                </div>
                {{-- Toggle switch --}}
                <label style="position:relative;width:52px;height:28px;cursor:pointer;display:block" title="Toggle Developer Mode">
                  <input type="checkbox" name="dev_mode" value="1" id="devModeChk"
                         {{ $devOn ? 'checked' : '' }}
                         style="opacity:0;width:0;height:0;position:absolute"
                         onchange="
                           var on=this.checked;
                           document.getElementById('devModeTrack').style.background=on?'#16a34a':'#d1d5db';
                           document.getElementById('devModeKnob').style.left=on?'26px':'3px';
                           document.getElementById('devModeStatusLabel').textContent=on?'ON':'OFF';
                           document.getElementById('devModeStatusLabel').style.color=on?'#16a34a':'#6b7280';
                           this.form.submit();
                         ">
                  <span id="devModeTrack" style="
                    position:absolute;inset:0;border-radius:14px;
                    transition:background .25s;
                    background:{{ $devOn ? '#16a34a' : '#d1d5db' }};
                  "></span>
                  <span id="devModeKnob" style="
                    position:absolute;top:4px;width:20px;height:20px;border-radius:50%;
                    background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.22);
                    transition:left .25s;
                    left:{{ $devOn ? '26px' : '3px' }};
                  "></span>
                </label>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ── PAYMONGO TAB ─────────────────────────────────────────────── --}}
  @elseif($tab === 'paymongo')
  @php
    $pmMode    = $platform->paymongo_mode ?? 'test';
    $hasPmTest = !empty($platform->paymongo_test_secret) || !empty($platform->paymongo_test_public);
    $hasPmLive = !empty($platform->paymongo_live_secret) || !empty($platform->paymongo_live_public);
  @endphp
  <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
    <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
        <i class="bi bi-credit-card-2-front" style="color:var(--primary)"></i> PayMongo (GCash / Card)
      </span>
      @if($hasPmLive && $pmMode === 'live')
        <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:.72rem">LIVE MODE</span>
      @elseif($hasPmTest)
        <span class="badge" style="background:#fff8e1;color:#e65100;font-size:.72rem">TEST MODE</span>
      @else
        <span class="badge bg-secondary" style="font-size:.72rem">NOT CONFIGURED</span>
      @endif
      <span style="font-size:.78rem;color:var(--gray-500);margin-left:auto">Platform-wide payment fallback for all shops</span>
    </div>
    <form action="{{ route('superadmin.settings.paymongo') }}" method="POST" style="padding:1.5rem">
      @csrf
      {{-- Mode Selector --}}
      <div class="mb-4">
        <label class="form-label fw-semibold">Payment Mode</label>
        <div class="d-flex gap-3">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.6rem 1.25rem;border-radius:var(--radius-md);border:1.5px solid {{ $pmMode==='test' ? 'var(--primary)' : 'var(--gray-200)' }};background:{{ $pmMode==='test' ? 'var(--primary-bg,#fff0f5)' : '#fff' }}" id="labelTest">
            <input type="radio" name="paymongo_mode" value="test" {{ $pmMode==='test' ? 'checked' : '' }} onchange="pmToggleMode(this.value)" style="accent-color:var(--primary)">
            <span style="font-weight:600;font-size:.875rem">Test Mode</span>
          </label>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.6rem 1.25rem;border-radius:var(--radius-md);border:1.5px solid {{ $pmMode==='live' ? '#2e7d32' : 'var(--gray-200)' }};background:{{ $pmMode==='live' ? '#e8f5e9' : '#fff' }}" id="labelLive">
            <input type="radio" name="paymongo_mode" value="live" {{ $pmMode==='live' ? 'checked' : '' }} onchange="pmToggleMode(this.value)" style="accent-color:#2e7d32">
            <span style="font-weight:600;font-size:.875rem;color:{{ $pmMode==='live' ? '#2e7d32' : 'inherit' }}">Live Mode</span>
          </label>
        </div>
        <div id="pmLiveWarning" style="display:{{ $pmMode==='live' ? 'block' : 'none' }};margin-top:.5rem">
          <div class="alert alert-warning py-2 mb-0" style="font-size:.82rem">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Live mode</strong> — real money will be charged to customers.
          </div>
        </div>
      </div>
      <div class="row g-4">
        {{-- Test Keys --}}
        <div class="col-md-6">
          <div style="border:1.5px solid var(--gray-100);border-radius:var(--radius-md);padding:1rem">
            <div style="font-size:.8rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.875rem">
              <i class="bi bi-flask me-1"></i> Test Keys
              @if($hasPmTest)<span style="color:#2e7d32;margin-left:.4rem">&#10003; Configured</span>@endif
            </div>
            <div class="mb-3">
              <label class="form-label">Public Key</label>
              <input type="text" class="form-control" name="paymongo_test_public"
                     value="{{ $platform->paymongo_test_public ?? '' }}"
                     placeholder="pk_test_...">
            </div>
            <div>
              <label class="form-label">Secret Key</label>
              <div class="input-group">
                <input type="password" class="form-control" name="paymongo_test_secret" id="pmTestSecret"
                       placeholder="{{ !empty($platform->paymongo_test_secret) ? 'Set — leave blank to keep' : 'sk_test_...' }}">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleKey('pmTestSecret',this)"
                        style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.5rem .75rem">
                  <i class="bi bi-eye" style="color:var(--gray-500)"></i>
                </button>
              </div>
              @if(!empty($platform->paymongo_test_secret))
                <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Secret key is set. Leave blank to keep.</div>
              @endif
            </div>
          </div>
        </div>
        {{-- Live Keys --}}
        <div class="col-md-6">
          <div style="border:1.5px solid {{ $hasPmLive ? '#c8e6c9' : 'var(--gray-100)' }};border-radius:var(--radius-md);padding:1rem;background:{{ $hasPmLive ? '#f9fff9' : '#fff' }}">
            <div style="font-size:.8rem;font-weight:700;color:{{ $hasPmLive ? '#2e7d32' : 'var(--gray-600)' }};text-transform:uppercase;letter-spacing:.05em;margin-bottom:.875rem">
              <i class="bi bi-lightning-charge me-1"></i> Live Keys
              @if($hasPmLive)<span style="color:#2e7d32;margin-left:.4rem">&#10003; Configured</span>
              @else<span style="color:var(--gray-400);margin-left:.4rem">Not yet set</span>@endif
            </div>
            <div class="mb-3">
              <label class="form-label">Public Key</label>
              <input type="text" class="form-control" name="paymongo_live_public"
                     value="{{ $platform->paymongo_live_public ?? '' }}"
                     placeholder="pk_live_...">
            </div>
            <div>
              <label class="form-label">Secret Key</label>
              <div class="input-group">
                <input type="password" class="form-control" name="paymongo_live_secret" id="pmLiveSecret"
                       placeholder="{{ !empty($platform->paymongo_live_secret) ? 'Set — leave blank to keep' : 'sk_live_...' }}">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleKey('pmLiveSecret',this)"
                        style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.5rem .75rem">
                  <i class="bi bi-eye" style="color:var(--gray-500)"></i>
                </button>
              </div>
              @if(!empty($platform->paymongo_live_secret))
                <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Secret key is set. Leave blank to keep.</div>
              @endif
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
        <i class="bi bi-save me-1"></i> Save PayMongo Settings
      </button>
    </form>
  </div>

  {{-- ── PHILSMS TAB ──────────────────────────────────────────────── --}}
  @elseif($tab === 'sms')
  @php $hasSms = !empty($platform->philsms_token); @endphp
  <div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
    <div style="padding:1.1rem 1.5rem;border-bottom:1.5px solid var(--gray-100);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <span style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.6rem">
        <i class="bi bi-chat-dots" style="color:var(--primary)"></i> UniSMS (SMS Notifications)
      </span>
      @if($hasSms)
        <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:.72rem"><i class="bi bi-check-circle me-1"></i>CONFIGURED</span>
      @else
        <span class="badge bg-secondary" style="font-size:.72rem">NOT CONFIGURED</span>
      @endif
    </div>
    <form action="{{ route('superadmin.settings.unisms') }}" method="POST" style="padding:1.5rem">
      @csrf
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Secret API Key</label>
          <div class="input-group">
            <input type="password" class="form-control" name="philsms_token" id="smsToken"
                   placeholder="{{ $hasSms ? 'API key is set — leave blank to keep' : 'Enter your UniSMS secret API key' }}">
            <button type="button" class="btn btn-secondary" onclick="toggleKey('smsToken',this)"
                    style="border:1.5px solid var(--gray-200);border-left:0;background:var(--gray-50);padding:.6rem .875rem">
              <i class="bi bi-eye" style="color:var(--gray-500)"></i>
            </button>
          </div>
          @if($hasSms)
            <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>API key is configured. Leave blank to keep existing.</div>
          @else
            <div class="form-text">Get your secret key from <strong>unismsapi.com/api_keys</strong></div>
          @endif
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Sender ID <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" class="form-control" name="philsms_sender"
                 value="{{ $platform->philsms_sender ?? '' }}"
                 placeholder="e.g. CakeShop" maxlength="20">
          <div class="form-text">Optional only. Branded sender names need telco approval through UniSMS.</div>
        </div>
        <div class="col-md-3">
          <div style="background:var(--gray-50);border-radius:var(--radius-md);padding:.875rem 1rem;font-size:.82rem;color:var(--gray-600);margin-top:1.75rem">
            <div style="font-weight:600;margin-bottom:.3rem">SMS is used for:</div>
            <div><i class="bi bi-dot"></i>OTP verification</div>
            <div><i class="bi bi-dot"></i>Order status updates</div>
            <div><i class="bi bi-dot"></i>Password resets</div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4" style="padding:.65rem 2rem;font-weight:600">
        <i class="bi bi-save me-1"></i> Save UniSMS Settings
      </button>
    </form>
  </div>

  {{-- ── ACTIVITY LOGS TAB ───────────────────────────────────────── --}}
  @elseif($tab === 'logs')
  @php
    $logs = \Illuminate\Support\Facades\DB::table('activity_logs as l')
      ->join('users as u','u.id','=','l.user_id')
      ->select('l.*','u.fullname','u.username')
      ->orderByDesc('l.id')->limit(500)->get();
  @endphp
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h6 class="fw-bold mb-0">Activity Logs</h6>
      <small class="text-muted" id="logCountLabel">{{ $logs->count() }} records (latest 500)</small>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <input type="text" id="logSearch" class="form-control form-control-sm"
             placeholder="Search logs…" oninput="filterLogs()" style="flex:1;min-width:0;max-width:250px">
      <select id="logRoleFilter" class="form-select form-select-sm" style="flex:1;min-width:0;max-width:160px" onchange="filterLogs()">
        <option value="">All Roles</option>
        <option value="superadmin">Super Admin</option>
        <option value="admin">Admin</option>
        <option value="seller">Seller</option>
        <option value="customer">Customer</option>
      </select>
    </div>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light">
          <tr><th class="ps-3">Date</th><th>User</th><th>Role</th><th>Action</th><th>Description</th></tr>
        </thead>
        <tbody id="logTableBody">
          @forelse($logs as $l)
          <tr class="log-row"
              data-search="{{ strtolower(($l->fullname ?? '') . ' ' . ($l->username ?? '') . ' ' . $l->action . ' ' . ($l->details ?? '')) }}"
              data-filter="{{ $l->role }}">
            <td class="ps-3 text-muted" style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('M d, Y H:i') }}</td>
            <td class="fw-semibold">{{ $l->fullname ?? $l->username ?? '—' }}</td>
            <td>
              <span class="badge rounded-pill
                {{ $l->role==='superadmin' ? 'bg-danger' : ($l->role==='admin' ? 'bg-dark' : ($l->role==='seller' ? 'bg-warning text-dark' : 'bg-secondary')) }}">
                {{ $l->role }}
              </span>
            </td>
            <td class="fw-semibold">{{ $l->action }}</td>
            <td class="text-muted">{{ $l->details ?? '' }}</td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No logs yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3" id="logPager"></div>
  @push('scripts')
  <script>
  function switchPBgType(type) {
    ['color','gradient','image'].forEach(t => {
      document.getElementById('pbg-'+t).style.display = t === type ? (t==='gradient'?'flex':'block') : 'none';
    });
    updatePbgGradPreview();
  }
  function updatePbgGradPreview() {
    const s = document.getElementById('pbgGradStart')?.value || '#fff7fb';
    const e = document.getElementById('pbgGradEnd')?.value   || '#ffe3f1';
    const p = document.getElementById('pbgPreview');
    if (p) p.style.background = `linear-gradient(135deg,${s} 0%,${e} 100%)`;
  }
  // Keep color preview in sync on solid
  document.addEventListener('DOMContentLoaded', () => {
    const cp = document.getElementById('pbgColorPicker');
    if (cp) cp.addEventListener('input', () => {
      const p = document.getElementById('pbgPreview');
      if (p) p.style.background = cp.value;
    });
  });
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const rows = [...document.querySelectorAll('.log-row')];
    const perPage = 20;
    let active = rows, cur = 1;
    function render() {
      rows.forEach(r => r.style.display = 'none');
      active.slice((cur-1)*perPage, cur*perPage).forEach(r => r.style.display = '');
      const lbl = document.getElementById('logCountLabel');
      if (lbl) lbl.textContent = active.length + ' records';
      renderPager(Math.ceil(active.length / perPage));
    }
    function renderPager(total) {
      const el = document.getElementById('logPager');
      if (!el) return;
      if (total <= 1) { el.innerHTML = ''; return; }
      let h = '<div class="cs-pagination">';
      h += `<button class="cs-page-btn" onclick="logGo(${cur-1})" ${cur===1?'disabled':''}>‹</button>`;
      for (let p=1;p<=total;p++) {
        if (total>7 && p>2 && p<total-1 && Math.abs(p-cur)>1) {
          if (p===3||p===total-2) h += '<button class="cs-page-btn dots">…</button>'; continue;
        }
        h += `<button class="cs-page-btn ${p===cur?'active':''}" onclick="logGo(${p})">${p}</button>`;
      }
      h += `<button class="cs-page-btn" onclick="logGo(${cur+1})" ${cur===total?'disabled':''}>›</button></div>`;
      el.innerHTML = h;
    }
    window.logGo = function(p) {
      const t = Math.ceil(active.length/perPage);
      if (p<1||p>t) return; cur=p; render();
      document.getElementById('logTableBody')?.closest('.card')?.scrollIntoView({behavior:'smooth'});
    };
    window.filterLogs = function() {
      const q    = (document.getElementById('logSearch')?.value||'').toLowerCase();
      const role = document.getElementById('logRoleFilter')?.value||'';
      active = rows.filter(r => (!q||(r.dataset.search||'').includes(q)) && (!role||r.dataset.filter===role));
      cur = 1; render();
    };
    render();
  });
  </script>
  @endpush

  {{-- ── BACKUP TAB ───────────────────────────────────────────────── --}}
  @elseif($tab === 'backup')
  @php
    $totalBackupSize = array_sum(array_map(fn($f) => (int) ($f['size'] ?? 0), $files));
    $latestBackup = count($files) ? $files[0] : null;
    $backupAutoOn = !empty($platform->backup_auto_enabled);
    $backupFrequency = $platform->backup_frequency ?? 'daily';
    $backupRetention = (int) ($platform->backup_retention_count ?? 14);
    $backupIncludeUploads = !empty($platform->backup_include_uploads);
    $lastRun = !empty($platform->backup_last_run_at) ? \Carbon\Carbon::parse($platform->backup_last_run_at) : null;
    $lastBackupAgeHours = $latestBackup ? floor((time() - ($latestBackup['modified_at'] ?? time())) / 3600) : null;
    $healthColor = !$latestBackup ? '#dc2626' : (($lastBackupAgeHours !== null && $lastBackupAgeHours > 48) ? '#d97706' : '#16a34a');
    $healthText = !$latestBackup ? 'No backup yet' : (($lastBackupAgeHours !== null && $lastBackupAgeHours > 48) ? 'Backup is getting old' : 'Protected');
    $fullBackupAvailable = class_exists(\ZipArchive::class);
  @endphp

  <div class="backup-console mb-4">
    <div class="backup-panel backup-action">
      <div class="d-flex align-items-start gap-3">
        <div class="backup-icon"><i class="bi bi-cloud-arrow-up"></i></div>
        <div>
          <h6 class="fw-bold mb-1">Database Backup</h6>
          <p class="text-muted small mb-0">Create a full SQL backup of the active database connection and store it securely in <code>storage/app/backups/</code>.</p>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <form action="{{ route('superadmin.settings.backup') }}" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('.backup-btn-text').textContent='Creating Backup...';">
          @csrf
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-database-down me-1"></i><span class="backup-btn-text">Database Backup</span>
          </button>
        </form>
        <form action="{{ route('superadmin.settings.full_backup') }}" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('.backup-btn-text').textContent='Creating Full Backup...';">
          @csrf
          <button type="submit" class="btn btn-outline-primary" {{ $fullBackupAvailable ? '' : 'disabled' }}>
            <i class="bi bi-archive me-1"></i><span class="backup-btn-text">Full Backup</span>
          </button>
        </form>
      </div>
      <div class="form-text mt-2">
        Full backup includes the database plus uploaded files.
        @unless($fullBackupAvailable)
          Enable PHP ZipArchive on the server to use this.
        @endunless
      </div>
    </div>

    <div class="backup-panel backup-action">
      <h6 class="fw-bold mb-2"><i class="bi bi-shield-check me-2" style="color:{{ $healthColor }}"></i>Backup Status</h6>
      <div class="backup-meta">
        <div class="backup-meta-box">
          <div class="text-muted small">Files</div>
          <div class="fw-bold">{{ count($files) }}</div>
        </div>
        <div class="backup-meta-box">
          <div class="text-muted small">Total Size</div>
          <div class="fw-bold">{{ number_format($totalBackupSize / 1024, 1) }} KB</div>
        </div>
      </div>
      <div class="text-muted small mt-3">
        Latest:
        <strong>{{ $latestBackup ? date('M d, Y H:i', $latestBackup['modified_at']) : 'No backup yet' }}</strong>
        <span class="badge ms-1" style="background:{{ $healthColor }};color:#fff">{{ $healthText }}</span>
      </div>
      <div class="text-muted small mt-2">
        Automation:
        <strong>{{ $backupAutoOn ? ucfirst($backupFrequency) : 'Off' }}</strong>
        @if($lastRun)
          &bull; Last run {{ $lastRun->format('M d, Y H:i') }}
        @endif
      </div>
      @if(!empty($platform->backup_last_status))
        <div class="small mt-2 {{ $platform->backup_last_status === 'success' ? 'text-success' : 'text-danger' }}">
          <i class="bi {{ $platform->backup_last_status === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>{{ $platform->backup_last_message }}
        </div>
      @endif
    </div>
  </div>

  <div class="backup-console mb-4">
    <div class="backup-panel backup-action">
      <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Automation Rules</h6>
      <form action="{{ route('superadmin.settings.backup_settings') }}" method="POST">
        @csrf
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Auto Backup</label>
            <select name="backup_auto_enabled" class="form-select">
              <option value="1" {{ $backupAutoOn ? 'selected' : '' }}>Enabled</option>
              <option value="0" {{ !$backupAutoOn ? 'selected' : '' }}>Disabled</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Frequency</label>
            <select name="backup_frequency" class="form-select">
              @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                <option value="{{ $value }}" {{ $backupFrequency === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Keep Latest</label>
            <input type="number" name="backup_retention_count" class="form-control" min="1" max="100" value="{{ $backupRetention }}">
          </div>
          <div class="col-12">
            <label class="d-flex align-items-center gap-2 small fw-semibold">
              <input type="checkbox" name="backup_include_uploads" value="1" {{ $backupIncludeUploads && $fullBackupAvailable ? 'checked' : '' }} {{ $fullBackupAvailable ? '' : 'disabled' }} style="accent-color:var(--primary)">
              Include uploaded files in automated backups
            </label>
            @unless($fullBackupAvailable)
              <div class="form-text">Unavailable until PHP ZipArchive is enabled.</div>
            @endunless
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i>Save Automation</button>
      </form>
    </div>

    <div class="backup-panel backup-action">
      <h6 class="fw-bold mb-3"><i class="bi bi-upload me-2" style="color:#2563eb"></i>Upload SQL Backup</h6>
      <form action="{{ route('superadmin.settings.upload_backup') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="backup_file" class="form-control" accept=".sql" required>
        <div class="form-text">Upload a trusted SQL backup, then restore it from the list below. Max 50 MB.</div>
        <button type="submit" class="btn btn-outline-primary mt-3"><i class="bi bi-cloud-upload me-1"></i>Upload Backup</button>
      </form>
      <div class="alert alert-warning py-2 mt-3 mb-0 small">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>Restores automatically create a safety backup first.
      </div>
    </div>
  </div>

  @if(count($files) > 0)
  <div class="backup-panel">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3">Existing Backups ({{ count($files) }})</h6>
      @foreach($files as $f)
      <div class="backup-file-row">
        <div>
          <div class="backup-file-name">
            {{ $f['name'] }}
            <span class="badge ms-1 {{ $f['extension'] === 'sql' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($f['extension']) }}</span>
          </div>
          <div class="backup-file-sub">{{ number_format(($f['size'] ?? 0)/1024, 1) }} KB &bull; {{ date('M d, Y H:i', $f['modified_at']) }}</div>
        </div>
        <div class="backup-actions">
          <a href="{{ route('superadmin.settings.download_backup', ['file'=>$f['name']]) }}"
             class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i>Download
          </a>
          @if($f['is_restorable'])
          <form action="{{ route('superadmin.settings.restore') }}" method="POST" class="d-inline"
                data-cs-confirm="Restore this SQL backup? A safety backup will be created first, then current data will be overwritten." data-cs-title="Restore Backup" data-cs-icon="bi-arrow-counterclockwise" data-cs-ok="Restore">
            @csrf
            <input type="hidden" name="file" value="{{ $f['name'] }}">
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
          </form>
          @endif
          <form action="{{ route('superadmin.settings.delete_backup') }}" method="POST" class="d-inline"
                data-cs-confirm="Delete this backup file permanently?" data-cs-title="Delete Backup" data-cs-icon="bi-trash" data-cs-icon-bg="#fff1f2" data-cs-icon-color="#ef4444" data-cs-ok="Delete" data-cs-ok-color="#ef4444">
            @csrf
            <input type="hidden" name="file" value="{{ $f['name'] }}">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
          </form>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @else
  <div class="backup-panel"><div class="card-body text-center text-muted py-5">No backups yet.</div></div>
  @endif
  @endif

</div>

<script>
function pmToggleMode(mode) {
  const labelTest = document.getElementById('labelTest');
  const labelLive = document.getElementById('labelLive');
  const warn      = document.getElementById('pmLiveWarning');
  if (!labelTest || !labelLive) return;
  if (mode === 'live') {
    labelLive.style.borderColor = '#2e7d32'; labelLive.style.background = '#e8f5e9';
    labelTest.style.borderColor = 'var(--gray-200)'; labelTest.style.background = '#fff';
    if (warn) warn.style.display = 'block';
  } else {
    labelTest.style.borderColor = 'var(--primary)'; labelTest.style.background = 'var(--primary-bg,#fff0f5)';
    labelLive.style.borderColor = 'var(--gray-200)'; labelLive.style.background = '#fff';
    if (warn) warn.style.display = 'none';
  }
}
function toggleKey(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (!input) return;
  input.type     = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  icon.style.color = 'var(--gray-500)';
}
</script>
@endsection
