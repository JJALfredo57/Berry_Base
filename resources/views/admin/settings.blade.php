@extends('layouts.app')
@section('content')
<div>
  <h4 class="fw-bold mb-4"><i class="bi bi-gear me-2" style="color:var(--primary)"></i>Settings</h4>

  {{-- Tabs --}}
  @php
    $allTabs = [
      'site'           => '🎨 Shop Settings',
      'location'       => '📍 Shop Location',
      'custom_options' => '🎂 Custom Order',
      'delivery_zones' => '🛵 Delivery Zones',
      'paymongo'       => '💳 GCash / PayMongo',
      'account'        => '👤 Profile & Password',
      'logs'           => '📋 Activity Logs',
      'backup'         => '💾 Backup',
      'addons'         => '🎁 Add-ons',
      'capacity'       => '📦 Daily Capacity',
    ];
    $visibleTabs = array_slice($allTabs, 0, 5, true);
    $moreTabs    = array_slice($allTabs, 5, null, true);
    $moreActive  = array_key_exists($tab, $moreTabs);
  @endphp
  <ul class="nav nav-tabs mb-4 border-0 gap-1 flex-wrap" id="settingsTabs">
    {{-- First 5 tabs --}}
    @foreach($visibleTabs as $key => $label)
    <li class="nav-item">
      <a class="nav-link px-3 py-2 {{ $tab===$key ? 'active fw-semibold' : 'text-muted' }}"
         href="{{ route('admin.settings.index', ['tab'=>$key]) }}"
         style="{{ $tab===$key ? 'border-bottom:2px solid var(--primary);color:var(--primary)!important;background:transparent' : 'border:0' }}">
        {{ $label }}
      </a>
    </li>
    @endforeach

    {{-- More dropdown --}}
    <li class="nav-item dropdown">
      <a class="nav-link px-3 py-2 dropdown-toggle {{ $moreActive ? 'fw-semibold' : 'text-muted' }}"
         href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
         style="{{ $moreActive ? 'border-bottom:2px solid var(--primary);color:var(--primary)!important;background:transparent' : 'border:0' }}">
        More
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width:200px">
        @foreach($moreTabs as $key => $label)
        <li>
          <a class="dropdown-item py-2 {{ $tab===$key ? 'fw-semibold' : '' }}"
             href="{{ route('admin.settings.index', ['tab'=>$key]) }}"
             style="{{ $tab===$key ? 'color:var(--primary)' : '' }}">
            {{ $label }}
          </a>
        </li>
        @endforeach
      </ul>
    </li>
  </ul>

  @if(session('msg'))<div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>@endif
  @if(session('err'))<div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('err') }}</div>@endif

  @if($tab==='site')
  <div class="card">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-4"><i class="bi bi-palette me-2" style="color:var(--primary)"></i>Branding & Theme</h6>
      <form action="{{ route('admin.settings.site') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Site Title</label>
            <input type="text" class="form-control" name="site_title" value="{{ $settings['site_title'] }}" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Tagline</label>
            <input type="text" class="form-control" name="tagline" value="{{ $settings['tagline'] }}">
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small"><i class="bi bi-clock me-1"></i>Timezone</label>
            <select class="form-select" name="timezone">
              @php
                $currentTz = $settings['timezone'] ?? 'Asia/Manila';
                $timezones = [
                  'Asia/Manila'     => 'Asia/Manila (PHT, UTC+8)',
                  'Asia/Singapore'  => 'Asia/Singapore (SGT, UTC+8)',
                  'Asia/Tokyo'      => 'Asia/Tokyo (JST, UTC+9)',
                  'Asia/Hong_Kong'  => 'Asia/Hong Kong (HKT, UTC+8)',
                  'Asia/Kuala_Lumpur' => 'Asia/Kuala Lumpur (MYT, UTC+8)',
                  'Asia/Jakarta'    => 'Asia/Jakarta (WIB, UTC+7)',
                  'Asia/Dubai'      => 'Asia/Dubai (GST, UTC+4)',
                  'Asia/Kolkata'    => 'Asia/Kolkata (IST, UTC+5:30)',
                  'Australia/Sydney'=> 'Australia/Sydney (AEDT, UTC+11)',
                  'Pacific/Auckland'=> 'Pacific/Auckland (NZST, UTC+12)',
                  'Europe/London'   => 'Europe/London (GMT, UTC+0)',
                  'America/New_York'=> 'America/New York (EST, UTC-5)',
                  'America/Los_Angeles' => 'America/Los Angeles (PST, UTC-8)',
                  'UTC'             => 'UTC (UTC+0)',
                ];
              @endphp
              @foreach($timezones as $tz => $label)
              <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            <div class="form-text">Current server time: <strong>{{ \Carbon\Carbon::now()->format('M d, Y h:i A') }}</strong> ({{ $currentTz }})</div>
          </div>

          {{-- Logo upload with preview --}}
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Shop Logo</label>
            <input type="file" class="form-control" name="logo" accept="image/*" onchange="previewImage(this,'logoPreview')">
            <div class="mt-2 d-flex align-items-center gap-2">
              @if(!empty($settings['logo_path']))
                <img id="logoPreview" src="{{ $settings['logo_path'] }}" style="height:48px;width:48px;border-radius:8px;object-fit:cover;border:1px solid #eee"
                     onerror="this.style.display='none'">
                <small class="text-muted">Current logo — upload a new one to replace</small>
              @else
                <img id="logoPreview" src="" style="height:48px;width:48px;border-radius:8px;object-fit:cover;border:1px solid #eee;display:none">
                <small class="text-muted">No logo uploaded yet</small>
              @endif
            </div>
          </div>

          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Primary Color</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" id="primaryColorInput" name="primary_color" value="{{ $settings['primary_color'] ?? '#e91e63' }}" style="width:52px" oninput="document.getElementById('primaryColorText').value=this.value">
              <input type="text" class="form-control" id="primaryColorText" value="{{ $settings['primary_color'] ?? '#e91e63' }}" oninput="document.getElementById('primaryColorInput').value=this.value">
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold small">Background Type</label>
            <div class="d-flex gap-4">
              @foreach(['gradient'=>'🌈 Gradient','color'=>'🎨 Solid Color','image'=>'🖼️ Image'] as $val=>$lbl)
              <div class="form-check">
                <input class="form-check-input" type="radio" name="bg_type" value="{{ $val }}" id="bg_{{ $val }}" {{ ($settings['bg_type']??'gradient')===$val ? 'checked' : '' }}>
                <label class="form-check-label" for="bg_{{ $val }}">{{ $lbl }}</label>
              </div>
              @endforeach
            </div>
          </div>
          <div class="col-sm-4">
            <label class="form-label fw-semibold small">Gradient Start</label>
            <input type="color" class="form-control form-control-color w-100" name="gradient_start" value="{{ $settings['gradient_start'] ?? '#fff7fb' }}">
          </div>
          <div class="col-sm-4">
            <label class="form-label fw-semibold small">Gradient End</label>
            <input type="color" class="form-control form-control-color w-100" name="gradient_end" value="{{ $settings['gradient_end'] ?? '#ffe3f1' }}">
          </div>
          <div class="col-sm-4">
            <label class="form-label fw-semibold small">Solid Color</label>
            <input type="color" class="form-control form-control-color w-100" name="bg_color" value="{{ $settings['bg_color'] ?? '#ffffff' }}">
          </div>
          <div class="col-12" id="bgImageSection">
            <label class="form-label fw-semibold small">Background Image</label>
            <input type="file" class="form-control" name="bg_image" accept="image/*">
            @if(!empty($settings['bg_image_path']))
            <div class="mt-1 d-flex align-items-center gap-2">
              <img src="{{ $settings['bg_image_path'] }}" style="height:36px;width:60px;object-fit:cover;border-radius:6px;border:1.5px solid #e9ecef">
              <span class="small text-muted">Current image</span>
            </div>
            @endif

            {{-- Opacity slider — visible only when Image bg type is selected --}}
            <div class="mt-3" id="bgOpacityWrap">
              <label class="form-label fw-semibold small d-flex justify-content-between">
                <span>🌫️ Image Opacity</span>
                <span id="opacityLabel" class="badge" style="background:var(--primary);min-width:44px;text-align:center">
                  {{ round(($settings['bg_image_opacity'] ?? 1.0) * 100) }}%
                </span>
              </label>
              <input type="range" class="form-range" name="bg_image_opacity" id="bgOpacitySlider"
                     min="0.05" max="1" step="0.05"
                     value="{{ $settings['bg_image_opacity'] ?? 1.0 }}"
                     oninput="document.getElementById('opacityLabel').textContent = Math.round(this.value * 100) + '%'">
              <div class="d-flex justify-content-between" style="font-size:clamp(.68rem,1.3vw,.72rem);color:#9ca3af;margin-top:2px">
                <span>5% (halos transparent)</span>
                <span>50%</span>
                <span>100% (buong kulay)</span>
              </div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Settings</button>
      </form>
    </div>
  </div>

  @elseif($tab==='paymongo')
  @php $pm = $settings; $pmMode = $pm['paymongo_mode'] ?? 'test'; @endphp

  {{-- Mode banner --}}
  <div class="alert border-0 d-flex align-items-center gap-3 mb-4"
       style="background:{{ $pmMode==='live' ? '#d1fae5' : '#fff3cd' }};border-radius:.9rem">
    <span style="font-size:clamp(1.3rem,3.5vw,1.8rem)">{{ $pmMode==='live' ? '🟢' : '🟡' }}</span>
    <div class="flex-grow-1">
      <strong>Currently in {{ strtoupper($pmMode) }} Mode</strong><br>
      <span class="small text-muted">
        {{ $pmMode==='live'
          ? 'Real GCash payments are being processed. Customers are charged actual money.'
          : 'Test mode — no real money is charged. Use PayMongo test credentials only.' }}
      </span>
    </div>
  </div>

  <form action="{{ route('admin.settings.paymongo') }}" method="POST">
    @csrf
    <div class="row g-4">

      {{-- Mode Toggle --}}
      <div class="col-12">
        <div class="card">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3">⚙️ Payment Mode</h6>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paymongo_mode" value="test"
                       id="modeTest" {{ $pmMode==='test' ? 'checked' : '' }}>
                <label class="form-check-label" for="modeTest">
                  <span class="fw-semibold">🟡 Test Mode</span><br>
                  <small class="text-muted">For development — no real charges</small>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paymongo_mode" value="live"
                       id="modeLive" {{ $pmMode==='live' ? 'checked' : '' }}>
                <label class="form-check-label" for="modeLive">
                  <span class="fw-semibold">🟢 Live Mode</span><br>
                  <small class="text-muted">For production — real GCash payments</small>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Test Keys --}}
      <div class="col-md-6">
        <div class="card h-100" style="border:2px solid {{ $pmMode==='test' ? '#fbbf24' : '#e5e7eb' }} !important">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-1">🟡 Test Keys</h6>
            <p class="text-muted small mb-3">
              From <a href="https://dashboard.paymongo.com" target="_blank">PayMongo Dashboard</a>
              → Developers → API Keys → Test
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Test Secret Key</label>
              <div class="input-group">
                <span class="input-group-text" style="font-size:clamp(.7rem,1.4vw,.75rem);background:#fef9c3">sk_test</span>
                <input type="text" class="form-control font-monospace"
                       name="paymongo_test_secret"
                       value="{{ $pm['paymongo_test_secret'] ?? '' }}"
                       placeholder="Enter your PayMongo test secret key">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Test Public Key</label>
              <div class="input-group">
                <span class="input-group-text" style="font-size:clamp(.7rem,1.4vw,.75rem);background:#fef9c3">pk_test</span>
                <input type="text" class="form-control font-monospace"
                       name="paymongo_test_public"
                       value="{{ $pm['paymongo_test_public'] ?? '' }}"
                       placeholder="pk_test_xxxxxxxxxxxxxxxxxxxxxxxx">
              </div>
            </div>
            <div class="alert border-0 p-2" style="background:#fffbeb;border-radius:.6rem;font-size:.78rem">
              <strong>Test GCash:</strong> Number: <code>09123456789</code> · OTP: <code>123456</code>
            </div>
          </div>
        </div>
      </div>

      {{-- Live Keys --}}
      <div class="col-md-6">
        <div class="card h-100" style="border:2px solid {{ $pmMode==='live' ? '#34d399' : '#e5e7eb' }} !important">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-1">🟢 Live Keys</h6>
            <p class="text-muted small mb-3">
              From <a href="https://dashboard.paymongo.com" target="_blank">PayMongo Dashboard</a>
              → Developers → API Keys → Live
              <span class="badge bg-warning text-dark ms-1" style="font-size:clamp(.66rem,1.3vw,.7rem)">Requires verified account</span>
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Live Secret Key</label>
              <div class="input-group">
                <span class="input-group-text" style="font-size:clamp(.7rem,1.4vw,.75rem);background:#d1fae5">sk_live</span>
                <input type="text" class="form-control font-monospace"
                       name="paymongo_live_secret"
                       value="{{ $pm['paymongo_live_secret'] ?? '' }}"
                       placeholder="Enter your PayMongo live secret key">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Live Public Key</label>
              <div class="input-group">
                <span class="input-group-text" style="font-size:clamp(.7rem,1.4vw,.75rem);background:#d1fae5">pk_live</span>
                <input type="text" class="form-control font-monospace"
                       name="paymongo_live_public"
                       value="{{ $pm['paymongo_live_public'] ?? '' }}"
                       placeholder="pk_live_xxxxxxxxxxxxxxxxxxxxxxxx">
              </div>
            </div>
            <div class="alert border-0 p-2" style="background:#ecfdf5;border-radius:.6rem;font-size:.78rem">
              ⚠️ <strong>Warning:</strong> Switching to Live mode will charge real money to customers.
            </div>
          </div>
        </div>
      </div>

      {{-- Where to get keys guide --}}
      <div class="col-12">
        <div class="card">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3">📖 How to get your PayMongo API Keys</h6>
            <div class="row g-3 small">
              <div class="col-md-6">
                <div class="d-flex gap-2">
                  <span class="fw-bold" style="color:var(--primary);min-width:20px">1.</span>
                  <span>Go to <a href="https://dashboard.paymongo.com" target="_blank">dashboard.paymongo.com</a> and register (free)</span>
                </div>
                <div class="d-flex gap-2 mt-2">
                  <span class="fw-bold" style="color:var(--primary);min-width:20px">2.</span>
                  <span>In the left menu → click <strong>Developers</strong> → <strong>API Keys</strong></span>
                </div>
                <div class="d-flex gap-2 mt-2">
                  <span class="fw-bold" style="color:var(--primary);min-width:20px">3.</span>
                  <span>Copy <strong>Secret Key</strong> (sk_test_...) and <strong>Public Key</strong> (pk_test_...) and paste above</span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="d-flex gap-2">
                  <span class="fw-bold" style="color:#10b981;min-width:20px">4.</span>
                  <span>For <strong>Live mode</strong>: complete PayMongo's business verification first</span>
                </div>
                <div class="d-flex gap-2 mt-2">
                  <span class="fw-bold" style="color:#10b981;min-width:20px">5.</span>
                  <span>Once verified, Live keys will appear in the same API Keys page</span>
                </div>
                <div class="d-flex gap-2 mt-2">
                  <span class="fw-bold" style="color:#10b981;min-width:20px">6.</span>
                  <span>Switch this page to <strong>Live Mode</strong> when you're ready to deploy</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <button type="submit" class="btn btn-primary mt-4 px-4">
      <i class="bi bi-save me-1"></i>Save PayMongo Settings
    </button>
  </form>

  {{-- VAT / Receipt Settings --}}
  <div class="card mt-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-1"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>VAT & Receipt Settings</h6>
      <p class="text-muted small mb-4">Configure tax settings displayed on GCash payment receipts. Only enable VAT if your business is BIR VAT-registered (annual gross sales ≥ ₱3M).</p>
      <form action="{{ route('admin.settings.site') }}" method="POST" enctype="multipart/form-data">
        @csrf
        {{-- Hidden fields to carry over existing site settings --}}
        <input type="hidden" name="site_title"       value="{{ $settings['site_title'] ?? '' }}">
        <input type="hidden" name="tagline"           value="{{ $settings['tagline'] ?? '' }}">
        <input type="hidden" name="bg_type"           value="{{ $settings['bg_type'] ?? 'gradient' }}">
        <input type="hidden" name="bg_color"          value="{{ $settings['bg_color'] ?? '#ffffff' }}">
        <input type="hidden" name="gradient_start"    value="{{ $settings['gradient_start'] ?? '#fff7fb' }}">
        <input type="hidden" name="gradient_end"      value="{{ $settings['gradient_end'] ?? '#ffe3f1' }}">
        <input type="hidden" name="primary_color"     value="{{ $settings['primary_color'] ?? '#e91e63' }}">
        <input type="hidden" name="bg_image_opacity"  value="{{ $settings['bg_image_opacity'] ?? 1 }}">

        <div class="row g-3 align-items-start">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">VAT</label>
            <div class="form-check form-switch mt-1">
              <input class="form-check-input" type="checkbox" name="vat_enabled" value="1" id="vatToggle"
                     {{ ($settings['vat_enabled'] ?? 0) ? 'checked' : '' }}
                     onchange="document.getElementById('vatFields').style.display=this.checked?'flex':'none'">
              <label class="form-check-label small" for="vatToggle">
                Enable VAT on receipts
              </label>
            </div>
            <div class="text-muted" style="font-size:clamp(.7rem,1.4vw,.75rem);margin-top:4px">
              Non-VAT registered businesses should leave this OFF.
            </div>
          </div>
          <div class="col-md-8">
            <div class="row g-3" id="vatFields" style="display:{{ ($settings['vat_enabled'] ?? 0) ? 'flex' : 'none' }}">
              <div class="col-sm-5">
                <label class="form-label fw-semibold small">VAT Rate (%)</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="vat_rate" min="0" max="100" step="0.01"
                         value="{{ $settings['vat_rate'] ?? 12 }}">
                  <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Standard PH VAT is 12%.</div>
              </div>
              <div class="col-sm-7">
                <label class="form-label fw-semibold small">TIN Number</label>
                <input type="text" class="form-control font-monospace" name="tin_number"
                       placeholder="000-000-000-000"
                       value="{{ $settings['tin_number'] ?? '' }}">
                <div class="form-text">Your BIR-registered Tax ID.</div>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-outline-primary mt-4 px-4">
          <i class="bi bi-save me-1"></i>Save VAT Settings
        </button>
      </form>
    </div>
  </div>

  @elseif($tab==='account')
  @php $adminUser = \Illuminate\Support\Facades\DB::table('users')->where('id',session('user')['id'])->first(); @endphp
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-4"><i class="bi bi-person me-2" style="color:var(--primary)"></i>Profile Information</h6>
          <form action="{{ route('admin.settings.profile') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Profile photo --}}
            <div class="mb-4 d-flex align-items-center gap-3">
              <div class="position-relative">
                @if(!empty($adminUser->profile_photo))
                  <img src="{{ $adminUser->profile_photo }}" id="adminPhotoPreview" alt="Photo"
                       style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)"
                       onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($adminUser->fullname) }}&background=e91e63&color=fff&size=72'">
                @else
                  <div id="adminPhotoPreview" style="width:72px;height:72px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center">
                    <span style="color:#fff;font-size:clamp(1.3rem,3.5vw,1.8rem);font-weight:700">{{ strtoupper(substr($adminUser->fullname,0,1)) }}</span>
                  </div>
                @endif
                <label for="adminPhotoInput" style="position:absolute;bottom:0;right:0;width:24px;height:24px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff">
                  <i class="bi bi-camera-fill text-white" style="font-size:clamp(.58rem,1.1vw,.6rem)"></i>
                </label>
                <input type="file" id="adminPhotoInput" name="profile_photo" accept="image/*" class="d-none" onchange="previewAdminPhoto(this)">
              </div>
              <div class="small text-muted">Click the camera icon to change your profile photo</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">Full Name</label>
              <input type="text" class="form-control" name="fullname" value="{{ $adminUser->fullname }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Email Address</label>
              <input type="email" class="form-control" name="email" value="{{ $adminUser->email }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Phone Number</label>
              <input type="text" class="form-control" name="phone" value="{{ $adminUser->phone }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Username</label>
              <input type="text" class="form-control bg-light" value="{{ $adminUser->username }}" disabled>
              <div class="form-text">Username cannot be changed.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Profile</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-4"><i class="bi bi-lock me-2" style="color:var(--primary)"></i>Change Password</h6>

          @php $acpStep = session('acp_step', 1); @endphp

          {{-- Step 1: Choose OTP channel --}}
          @if($acpStep == 1)
          <p class="text-muted small">To change your password, we'll send a verification code first.</p>
          <form action="{{ route('admin.settings.password.send_otp') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Send Verification Code Via</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="email" id="acpEmail" checked>
                  <label class="form-check-label" for="acpEmail"><i class="bi bi-envelope me-1"></i>Email</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" value="sms" id="acpSms">
                  <label class="form-check-label" for="acpSms"><i class="bi bi-phone me-1"></i>SMS</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-outline-danger">
              <i class="bi bi-send me-1"></i>Send OTP Code
            </button>
          </form>

          {{-- Step 2: Enter OTP --}}
          @elseif($acpStep == 2)
          <form action="{{ route('admin.settings.password.verify_otp') }}" method="POST">
            @csrf
            <p class="text-muted small mb-3">
              <i class="bi bi-shield-check me-1" style="color:var(--primary)"></i>
              Enter the 6-digit OTP code sent to you.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold small">OTP Code</label>
              <input type="text" class="form-control text-center fw-bold" name="otp"
                     maxlength="6" placeholder="000000" autofocus required
                     style="font-size:1.4rem;letter-spacing:.4rem"
                     oninput="this.value=this.value.replace(/\D/g,'')">
              <div class="form-text text-center">OTP expires in 10 minutes</div>
              @include('components.dev-otp-hint')
            </div>
            <button type="submit" class="btn btn-outline-danger mb-2">
              <i class="bi bi-check-lg me-1"></i>Verify OTP
            </button>
          </form>
          {{-- Resend --}}
          <div class="mt-1">
            <span class="small text-muted" id="acpResendWrap">
              Resend in <span id="acpResendCountdown" class="fw-semibold" style="color:var(--primary)">1:00</span>
            </span>
            <form action="{{ route('admin.settings.password.send_otp') }}" method="POST" id="acpResendForm" style="display:none">
              @csrf
              <input type="hidden" name="otp_channel" value="{{ session('acp_channel','email') }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
              </button>
            </form>
          </div>
          {{-- Back --}}
          <div class="mt-2">
            <a href="{{ route('admin.settings.password.back') }}" class="small text-muted">
              <i class="bi bi-arrow-left me-1"></i>Back
            </a>
          </div>

          {{-- Step 3: New Password form --}}
          @else
          <form action="{{ route('admin.settings.password') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">New Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="new_password" id="newPwd" data-strength="newPwd" data-pwdreq="newPwd" required minlength="8">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd',this)"><i class="bi bi-eye"></i></button>
              </div>
              @include('layouts.password_strength', ['inputId'=>'newPwd'])
              @include('layouts.password_requirements', ['inputId'=>'newPwd'])
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Confirm New Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="confirm_password" id="confirmPwd" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirmPwd',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
          </form>
          @endif

        </div>
      </div>
    </div>
  </div>

  @elseif($tab==='logs')
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

  <div class="cs-search-bar" style="flex:1;min-width:0;max-width:250px">
    <input type="text" id="logSearch"
      class="form-control form-control-sm"
      placeholder="Search logs…"
      oninput="filterLogs()">
  </div>

  <select id="logRoleFilter"
    class="form-select form-select-sm"
    style="flex:1;min-width:0;max-width:160px"
    onchange="filterLogs()">
    <option value="">All Roles</option>
    <option value="admin">Admin</option>
    <option value="customer">Customer</option>
  </select>

</div>
</div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light"><tr><th class="ps-3">Date</th><th>User</th><th>Role</th><th>Action</th><th>Description</th></tr></thead>
        <tbody id="logTableBody">
          @forelse($logs as $l)
          <tr class="log-row"
              data-search="{{ strtolower($l->fullname . ' ' . $l->username . ' ' . $l->action . ' ' . $l->details) }}"
              data-filter="{{ $l->role }}">
            <td class="ps-3 text-muted" style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('M d, Y H:i') }}</td>
            <td class="fw-semibold">{{ $l->fullname }}</td>
            <td><span class="badge rounded-pill {{ $l->role==='admin' ? 'bg-dark' : 'bg-secondary' }}">{{ $l->role }}</span></td>
            <td class="fw-semibold">{{ $l->action }}</td>
            <td class="text-muted">{{ $l->details }}</td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No logs yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3" id="logTableBody_pager"></div>
  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Logs use table rows — wire pagination manually
    const rows   = [...document.querySelectorAll('.log-row')];
    const perPage = 20;
    let active   = rows;
    let cur      = 1;

    function render() {
      const total = Math.ceil(active.length / perPage);
      rows.forEach(r => r.style.display = 'none');
      const s = (cur-1)*perPage;
      active.slice(s, s+perPage).forEach(r => r.style.display = '');
      const lbl = document.getElementById('logCountLabel');
      if (lbl) lbl.textContent = active.length + ' records';
      renderPager(total);
    }

    function renderPager(total) {
      const el = document.getElementById('logTableBody_pager');
      if (!el) return;
      if (total <= 1) { el.innerHTML = ''; return; }
      let h = '<div class="cs-pagination">';
      h += '<button class="cs-page-btn" onclick="logGo(' + cur-1 + ')" ' + cur===1?'disabled':'' + '>‹</button>';
      for (let p=1;p<=total;p++) {
        if (total > 7 && p > 2 && p < total-1 && Math.abs(p-cur) > 1) {
          if (p === 3 || p === total-2) h += '<button class="cs-page-btn dots">…</button>'; continue;
        }
        h += '<button class="cs-page-btn ' + p===cur?'active':'' + '" onclick="logGo(' + p + ')">' + p + '</button>';
      }
      h += '<button class="cs-page-btn" onclick="logGo(' + cur+1 + ')" ' + cur===total?'disabled':'' + '>›</button>';
      h += '<span class="ms-1 text-muted" style="font-size:.78rem">' + active.length + ' rows</span></div>';
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
      active = rows.filter(r => {
        const ms = !q    || (r.dataset.search||'').includes(q);
        const mr = !role || r.dataset.filter === role;
        return ms && mr;
      });
      cur = 1; render();
    };

    render();
  });
  </script>
  @endpush

  @elseif($tab==='backup')
  <div class="card mb-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-up me-2" style="color:var(--primary)"></i>Database Backup</h6>
      <p class="text-muted small">Create a full backup of the database. Backups are stored in <code>storage/app/backups/</code>.</p>
      <form action="{{ route('admin.settings.backup') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Create Backup Now</button>
      </form>
    </div>
  </div>
  @if(count($files) > 0)
  <div class="card">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3">Existing Backups ({{ count($files) }})</h6>
      @foreach($files as $f)
      <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div>
          <span class="small fw-semibold">{{ basename($f) }}</span>
          <span class="text-muted small ms-2">{{ number_format(filesize($f)/1024, 1) }} KB</span>
        </div>
        <div class="d-flex gap-2">
          <a href="{{ route('admin.settings.restore', ['file'=>basename($f)]) }}" class="btn btn-outline-secondary btn-sm" onclick="confirmAction('Restore Backup?', 'Current data will be overwritten. This cannot be undone.', () => window.location=this.href); return false;"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</a>
          <a href="{{ route('admin.settings.delete_backup', ['file'=>basename($f)]) }}" class="btn btn-outline-danger btn-sm" onclick="confirmDelete('This backup file will be permanently deleted.', () => window.location=this.href); return false;"><i class="bi bi-trash me-1"></i>Delete</a>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif
  @elseif($tab==='custom_options')
  {{-- ── CUSTOM ORDER OPTIONS TAB ─────────────────────────────── --}}
  @php
    $customOpts = \Illuminate\Support\Facades\DB::table('custom_order_options')
        ->orderBy('type')->orderBy('sort_order')->orderBy('id')
        ->get()->groupBy('type');
    $coTypeInfo = [
      'flavor'     => ['label'=>'Flavors',           'icon'=>'bi-droplet', 'has_price'=>false, 'color'=>'#6366f1'],
      'size'       => ['label'=>'Sizes / Diameter',  'icon'=>'bi-rulers',  'has_price'=>true,  'color'=>'#0ea5e9'],
      'layer'      => ['label'=>'Number of Layers',  'icon'=>'bi-layers',  'has_price'=>false, 'color'=>'#f59e0b'],
      'complexity' => ['label'=>'Design Complexity', 'icon'=>'bi-magic',   'has_price'=>true,  'color'=>'#ec4899'],
      'time_slot'  => ['label'=>'Time Slots',        'icon'=>'bi-clock',   'has_price'=>false, 'color'=>'#10b981'],
    ];
  @endphp

  <div class="row g-3">
    @foreach($coTypeInfo as $typeKey => $info)
    @php $opts = $customOpts[$typeKey] ?? collect(); @endphp
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-body p-4">

          {{-- Section Header --}}
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
              <div style="width:36px;height:36px;border-radius:10px;background:{{ $info['color'] }}22;display:flex;align-items:center;justify-content:center">
                <i class="bi {{ $info['icon'] }}" style="color:{{ $info['color'] }};font-size:clamp(.9rem,2.2vw,1.1rem)"></i>
              </div>
              <div>
                <div class="fw-bold">{{ $info['label'] }}</div>
                <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">{{ $opts->count() }} option(s)</div>
              </div>
            </div>
            <button class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#coAddModal_{{ $typeKey }}">
              <i class="bi bi-plus me-1"></i>Add
            </button>
          </div>

          {{-- Options List --}}
          @if($opts->count() > 0)
          <div class="d-flex flex-column gap-2">
            @foreach($opts as $opt)
            <div class="d-flex align-items-center gap-2 p-2 rounded {{ $opt->is_active ? '' : 'opacity-50' }}"
                 style="border:1.5px solid #e9ecef;background:{{ $opt->is_active ? '#fff' : '#f8f9fa' }}">

              {{-- Sort --}}
              <div class="d-flex flex-column" style="font-size:clamp(.62rem,1.2vw,.65rem)">
                <form action="{{ route('admin.custom_options.sort_up', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-link p-0 text-muted" style="line-height:1;font-size:clamp(.66rem,1.3vw,.7rem)">▲</button>
                </form>
                <form action="{{ route('admin.custom_options.sort_down', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-link p-0 text-muted" style="line-height:1;font-size:clamp(.66rem,1.3vw,.7rem)">▼</button>
                </form>
              </div>

              {{-- Label --}}
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold small text-truncate">{{ $opt->label }}</div>
                @if($opt->description)
                  <div class="text-muted" style="font-size:clamp(.66rem,1.3vw,.7rem)">{{ $opt->description }}</div>
                @endif
              </div>

              {{-- Price badge --}}
              @if($info['has_price'])
              <span class="badge flex-shrink-0"
                    style="background:{{ $info['color'] }}22;color:{{ $info['color'] }};font-size:clamp(.66rem,1.3vw,.7rem)">
                {{ $opt->price > 0 ? '+₱'.number_format($opt->price,2) : 'Free' }}
              </span>
              @endif

              {{-- Actions --}}
              <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#coEditModal_{{ $opt->id }}">
                  <i class="bi bi-pencil" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                </button>
                <form action="{{ route('admin.custom_options.toggle', $opt->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-sm py-0 px-2 {{ $opt->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    <i class="bi {{ $opt->is_active ? 'bi-eye-slash' : 'bi-eye' }}" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                  </button>
                </form>
                <form action="{{ route('admin.custom_options.destroy', $opt->id) }}" method="POST" class="m-0"
                      onsubmit="return false;" onclick="confirmDelete('Delete this option? This cannot be undone.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                    <i class="bi bi-trash" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                  </button>
                </form>
              </div>
            </div>

            {{-- Edit Modal --}}
            <div class="modal fade" id="coEditModal_{{ $opt->id }}" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0" style="border-radius:1.2rem">
                  <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">Edit Option</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form action="{{ route('admin.custom_options.update', $opt->id) }}" method="POST">
                      @csrf
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Label</label>
                        <input type="text" class="form-control form-control-sm" name="label"
                               value="{{ $opt->label }}" required maxlength="120">
                      </div>
                      @if($info['has_price'])
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Surcharge (₱)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                               name="price" value="{{ $opt->price }}">
                      </div>
                      @else
                        <input type="hidden" name="price" value="0">
                      @endif
                      @if($info['has_price'])
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" name="description"
                               value="{{ $opt->description }}" maxlength="255">
                      </div>
                      @else
                        <input type="hidden" name="description" value="">
                      @endif
                      <button type="submit" class="btn btn-primary btn-sm w-100">Save</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            @endforeach
          </div>
          @else
          <div class="text-center py-4 text-muted small">
            <i class="bi {{ $info['icon'] }}" style="font-size:2rem;opacity:.25"></i>
            <div class="mt-2">No options yet. Click <strong>+ Add</strong>.</div>
          </div>
          @endif

        </div>
      </div>
    </div>

    {{-- Add Modal --}}
    <div class="modal fade" id="coAddModal_{{ $typeKey }}" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0" style="border-radius:1.2rem">
          <div class="modal-header border-0 pb-0">
            <h6 class="modal-title fw-bold">Add {{ $info['label'] }}</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form action="{{ route('admin.custom_options.store') }}" method="POST">
              @csrf
              <input type="hidden" name="type" value="{{ $typeKey }}">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Label</label>
                <input type="text" class="form-control form-control-sm" name="label"
                       required maxlength="120" placeholder="Enter option name">
              </div>
              @if($info['has_price'])
              <div class="mb-3">
                <label class="form-label fw-semibold small">Surcharge (₱)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price" value="0">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control form-control-sm" name="description" maxlength="255">
              </div>
              @else
                <input type="hidden" name="price" value="0">
                <input type="hidden" name="description" value="">
              @endif
              <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-plus me-1"></i>Add Option
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @elseif($tab==='delivery_zones')
  {{-- ── DELIVERY ZONES TAB ─────────────────────────────────── --}}
  @php
    $zones = \Illuminate\Support\Facades\DB::table('delivery_zones')
        ->orderBy('sort_order')->orderBy('id')->get();
    $zoneTypes = [
      'free' => ['label'=>'Poblacion (Free)',    'color'=>'#10b981', 'bg'=>'#d1fae5'],
      'near' => ['label'=>'Nearby',              'color'=>'#0ea5e9', 'bg'=>'#e0f2fe'],
      'mid'  => ['label'=>'Mid-range',           'color'=>'#f59e0b', 'bg'=>'#fef3c7'],
      'far'  => ['label'=>'Far',                 'color'=>'#f97316', 'bg'=>'#ffedd5'],
      'ooc'  => ['label'=>'Out of Coverage',      'color'=>'#e11d48', 'bg'=>'#ffe4e6'],
    ];
  @endphp

  <div class="row g-4">
    {{-- Left: Zones List --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2" style="color:var(--primary)"></i>Barangay Delivery Zones</h6>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addZoneModal">
              <i class="bi bi-plus me-1"></i>Add Barangay
            </button>
          </div>

          @if($zones->count() > 0)
          <div class="d-flex flex-column gap-2">
            @foreach($zones as $z)
            @php $zt = $zoneTypes[$z->zone_type] ?? $zoneTypes['near']; @endphp
            <div class="d-flex align-items-center gap-3 p-2 rounded {{ $z->is_active ? '' : 'opacity-50' }}"
                 style="border:1.5px solid #e9ecef;background:{{ $z->is_active ? '#fff' : '#f8f9fa' }}">

              {{-- Zone type badge --}}
              <span class="badge flex-shrink-0"
                    style="background:{{ $zt['bg'] }};color:{{ $zt['color'] }};font-size:clamp(.66rem,1.3vw,.7rem);min-width:90px;text-align:center">
                {{ $zt['label'] }}
              </span>

              {{-- Barangay name --}}
              <div class="flex-grow-1 fw-semibold small">{{ $z->barangay }}</div>

              {{-- Fee --}}
              <div class="fw-bold small flex-shrink-0" style="color:{{ $zt['color'] }};min-width:70px;text-align:right">
                {{ $z->fee == 0 ? 'FREE' : '₱'.number_format($z->fee,2) }}
              </div>

              {{-- Actions --}}
              <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#editZoneModal_{{ $z->id }}">
                  <i class="bi bi-pencil" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                </button>
                <form action="{{ route('admin.delivery_zones.toggle', $z->id) }}" method="POST" class="m-0">
                  @csrf
                  <button type="submit" class="btn btn-sm py-0 px-2 {{ $z->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    <i class="bi {{ $z->is_active ? 'bi-eye-slash' : 'bi-eye' }}" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                  </button>
                </form>
                <form action="{{ route('admin.delivery_zones.destroy', $z->id) }}" method="POST" class="m-0"
                      onsubmit="return false;" onclick="confirmDelete('Delete {{ addslashes($z->barangay) }}? This cannot be undone.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                    <i class="bi bi-trash" style="font-size:clamp(.7rem,1.4vw,.75rem)"></i>
                  </button>
                </form>
              </div>
            </div>

            {{-- Edit Modal --}}
            <div class="modal fade" id="editZoneModal_{{ $z->id }}" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0" style="border-radius:1.2rem">
                  <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">Edit Zone</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form action="{{ route('admin.delivery_zones.update', $z->id) }}" method="POST">
                      @csrf
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Barangay Name</label>
                        <input type="text" class="form-control form-control-sm" name="barangay"
                               value="{{ $z->barangay }}" required maxlength="100">
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Zone Type</label>
                        <select class="form-select form-select-sm" name="zone_type">
                          @foreach($zoneTypes as $typeKey => $typeInfo)
                          <option value="{{ $typeKey }}" {{ $z->zone_type === $typeKey ? 'selected' : '' }}>
                            {{ $typeInfo['label'] }}
                          </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-semibold small">Delivery Fee (₱)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                               name="fee" value="{{ $z->fee }}">
                        <div class="form-text">Set 0 for free delivery</div>
                      </div>
                      <button type="submit" class="btn btn-primary btn-sm w-100">Save Changes</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            @endforeach
          </div>
          @else
          <div class="text-center py-5 text-muted small">
            <i class="bi bi-geo-alt" style="font-size:2.5rem;opacity:.25"></i>
            <div class="mt-2">No zones yet. Click <strong>+ Add Barangay</strong>.</div>
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Right: Legend + Tips --}}
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2" style="color:var(--primary)"></i>Zone Legend</h6>
          <div class="d-flex flex-column gap-2">
            @foreach($zoneTypes as $typeKey => $typeInfo)
            <div class="d-flex align-items-center gap-2">
              <span class="badge" style="background:{{ $typeInfo['bg'] }};color:{{ $typeInfo['color'] }};font-size:clamp(.68rem,1.3vw,.72rem);min-width:100px;text-align:center">
                {{ $typeInfo['label'] }}
              </span>
              <span class="small text-muted">
                @if($typeKey === 'free') Free delivery
                @elseif($typeKey === 'near') Close to shop
                @elseif($typeKey === 'mid') Moderate distance
                @elseif($typeKey === 'far') Far, higher delivery charge
                @else Ibang usapin sa admin
                @endif
              </span>
            </div>
            @endforeach
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb me-2" style="color:#f59e0b"></i>Reminder</h6>
          <p class="small text-muted mb-0">
            The <strong>Out of Coverage</strong> fee starts at ₱250 — you can adjust it per order
            on the Orders page once you've seen the customer's location.
            Perishable (Ice Cream Cake) orders automatically show a warning to the customer on checkout.
          </p>
        </div>
      </div>
    </div>
  </div>

  {{-- Add Zone Modal --}}
  <div class="modal fade" id="addZoneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0 pb-0">
          <h6 class="modal-title fw-bold">Add Barangay</h6>
          <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('admin.delivery_zones.store') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Barangay Name</label>
              <input type="text" class="form-control form-control-sm" name="barangay"
                     required maxlength="100" placeholder="e.g. Barangay Bagong Bayan">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Zone Type</label>
              <select class="form-select form-select-sm" name="zone_type">
                @foreach($zoneTypes as $typeKey => $typeInfo)
                <option value="{{ $typeKey }}">{{ $typeInfo['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Delivery Fee (₱)</label>
              <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                     name="fee" value="0" placeholder="0">
              <div class="form-text">Set 0 for free delivery</div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-plus me-1"></i>Add Zone
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  @elseif($tab==='addons')
  @php
    $addonCategories = \Illuminate\Support\Facades\DB::table('cake_addon_categories')->orderBy('sort_order')->get();
    $addonItems = \Illuminate\Support\Facades\DB::table('cake_addons')->orderBy('sort_order')->get()->groupBy('category_id');
  @endphp
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0"><i class="bi bi-stars me-2" style="color:var(--primary)"></i>Cake Add-ons Manager</h5>
      <p class="text-muted small mb-0">Manage customization options customers can select at checkout</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-folder-plus me-1"></i>New Category
      </button>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAddonModal">
        <i class="bi bi-plus me-1"></i>Add Add-on
      </button>
    </div>
  </div>

  @forelse($addonCategories as $cat)
  @php $catAddons = $addonItems[$cat->id] ?? collect(); @endphp
  <div class="card mb-4">
    <div class="card-body border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
         style="background:{{ $cat->is_active ? '#fff' : '#f8f9fa' }}">
      <div class="d-flex align-items-center gap-2">
        <i class="bi {{ $cat->icon }} fs-5" style="color:var(--primary)"></i>
        <div>
          <span class="fw-bold">{{ $cat->name }}</span>
          <span class="text-muted small ms-2">({{ $catAddons->count() }} items)</span>
          @if(!$cat->is_active)<span class="badge bg-secondary ms-2" style="font-size:clamp(.66rem,1.3vw,.7rem)">Hidden</span>@endif
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editCatModal{{ $cat->id }}">
          <i class="bi bi-pencil"></i>
        </button>
        <form action="{{ route('admin.addons.toggle_category', $cat->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm {{ $cat->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
            <i class="bi {{ $cat->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
          </button>
        </form>
        <form action="{{ route('admin.addons.destroy_category', $cat->id) }}" method="POST" class="d-inline"
              onsubmit="return false;" onclick="confirmDelete('Delete this category? All its add-ons must be removed first.', () => this.closest('form').submit())">
          @csrf
          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Add-on Name</th><th>Description</th><th>Price</th><th>Status</th><th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($catAddons as $a)
          <tr style="{{ !$a->is_active ? 'opacity:.5' : '' }}">
            <td class="ps-3 fw-semibold">{{ $a->name }}</td>
            <td class="text-muted">{{ $a->description ?? '—' }}</td>
            <td>
              @if($a->price > 0)
                <span class="fw-bold" style="color:var(--primary)">+₱{{ number_format($a->price,2) }}</span>
              @else
                <span class="badge bg-success" style="font-size:clamp(.66rem,1.3vw,.7rem)">FREE</span>
              @endif
            </td>
            <td>
              @if($a->is_active)
                <span class="badge" style="background:#d1fae5;color:#065f46;font-size:clamp(.68rem,1.3vw,.72rem)">Visible</span>
              @else
                <span class="badge bg-secondary" style="font-size:clamp(.68rem,1.3vw,.72rem)">Hidden</span>
              @endif
            </td>
            <td class="text-end pe-3">
              <div class="d-flex gap-1 justify-content-end">
                <button class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#editAddonModal{{ $a->id }}">
                  <i class="bi bi-pencil"></i>
                </button>
                <form action="{{ route('admin.addons.toggle', $a->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm py-0 px-2 {{ $a->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    <i class="bi {{ $a->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                  </button>
                </form>
                <form action="{{ route('admin.addons.destroy', $a->id) }}" method="POST" class="d-inline"
                      onsubmit="return false;" onclick="confirmDelete('Delete {{ $a->name }}? This cannot be undone.', () => this.closest('form').submit())">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          {{-- Edit Addon Modal --}}
          <div class="modal fade" id="editAddonModal{{ $a->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content border-0" style="border-radius:1.2rem">
                <div class="modal-header border-0">
                  <h5 class="modal-title fw-bold">Edit Add-on</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <form action="{{ route('admin.addons.update', $a->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Category</label>
                      <select class="form-select" name="category_id" required>
                        @foreach($addonCategories as $c)
                          <option value="{{ $c->id }}" {{ $a->category_id==$c->id?'selected':'' }}>{{ $c->name }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Add-on Name</label>
                      <input type="text" class="form-control" name="name" value="{{ $a->name }}" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
                      <input type="text" class="form-control" name="description" value="{{ $a->description }}">
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold small">Additional Price (₱)</label>
                      <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" min="0" class="form-control" name="price" value="{{ $a->price }}">
                      </div>
                      <div class="form-text">Set to 0 for free add-ons</div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="5" class="text-center text-muted py-3 ps-3">No add-ons in this category yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Edit Category Modal --}}
  <div class="modal fade" id="editCatModal{{ $cat->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Edit Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('admin.addons.update_category', $cat->id) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Category Name</label>
              <input type="text" class="form-control" name="name" value="{{ $cat->name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Icon <span class="text-muted fw-normal">(Bootstrap Icons class)</span></label>
              <input type="text" class="form-control font-monospace" name="icon" value="{{ $cat->icon }}" placeholder="bi-palette">
              <div class="form-text">Browse icons at <a href="https://icons.getbootstrap.com" target="_blank">icons.getbootstrap.com</a></div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @empty
  <div class="card text-center py-5">
    <i class="bi bi-stars" style="font-size:3rem;color:#ddd"></i>
    <p class="text-muted mt-3">No categories yet. Add one to get started.</p>
  </div>
  @endforelse

  {{-- Add Category Modal --}}
  <div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus me-2"></i>New Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('admin.addons.store_category') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Category Name</label>
              <input type="text" class="form-control" name="name" placeholder="e.g. 🎨 Design / Decorations" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Icon</label>
              <input type="text" class="form-control font-monospace" name="icon" value="bi-stars" placeholder="bi-stars">
              <div class="form-text">Bootstrap Icons class — see <a href="https://icons.getbootstrap.com" target="_blank">icons.getbootstrap.com</a></div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Add Category</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Add Addon Modal --}}
  <div class="modal fade" id="addAddonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius:1.2rem">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Add-on</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('admin.addons.store') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">Category</label>
              <select class="form-select" name="category_id" required>
                <option value="">-- Select Category --</option>
                @foreach($addonCategories as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Add-on Name</label>
              <input type="text" class="form-control" name="name" placeholder="e.g. Fresh Strawberries" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Description <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" class="form-control" name="description" placeholder="Short description">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Additional Price (₱)</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" step="0.01" min="0" class="form-control" name="price" value="0">
              </div>
              <div class="form-text">Set to 0 for free add-ons</div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Add</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @elseif($tab==='location')
  {{-- Shop Location Tab --}}
  @php
    $shopLat = $settings['shop_lat'] ?? 15.8107127;
    $shopLng = $settings['shop_lng'] ?? 120.4716710;
  @endphp
  <div class="card">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-1">📍 Shop Location</h5>
      <p class="text-muted small mb-4">
        This location is used as the default center of the map on the customer checkout page.
        Pin your exact shop location so customers can easily find their delivery zone.
      </p>

      {{-- Current coords display --}}
      <div class="alert border-0 mb-3 d-flex align-items-center gap-2" style="background:#f0fdf4">
        <i class="bi bi-geo-alt-fill" style="color:#16a34a;font-size:clamp(.9rem,2.2vw,1.1rem)"></i>
        <div class="small">
          Current location: <strong>Lat {{ number_format((float)$shopLat, 7) }}, Lng {{ number_format((float)$shopLng, 7) }}</strong>
        </div>
      </div>

      {{-- Map --}}
      <div class="mb-3">
        <label class="form-label fw-semibold small">Pin Shop Location</label>
        <div id="shopMap" style="height:360px;border-radius:.9rem;border:2px solid #dee2e6"></div>
        <div class="form-text mt-1"><i class="bi bi-hand-index me-1"></i>Click or drag the marker to set the exact shop location.</div>
      </div>

      <form action="{{ route('admin.settings.shop_location') }}" method="POST" id="shopLocationForm">
        @csrf
        <input type="hidden" name="shop_lat" id="shopLat" value="{{ $shopLat }}">
        <input type="hidden" name="shop_lng" id="shopLng" value="{{ $shopLng }}">

        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Latitude</label>
            <input type="text" class="form-control" id="shopLatDisplay" value="{{ $shopLat }}" readonly style="background:#f8f9fa">
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Longitude</label>
            <input type="text" class="form-control" id="shopLngDisplay" value="{{ $shopLng }}" readonly style="background:#f8f9fa">
          </div>
        </div>

        <button type="button" class="btn btn-primary fw-semibold"
                onclick="confirmShopLocation()">
          <i class="bi bi-save me-1"></i>Save Shop Location
        </button>
      </form>
    </div>
  </div>

  {{-- Leaflet for admin shop location --}}
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const initLat = {{ (float)$shopLat }};
    const initLng = {{ (float)$shopLng }};

    const shopMap = L.map('shopMap').setView([initLat, initLng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(shopMap);

    let shopMarker = L.marker([initLat, initLng], { draggable: true })
      .addTo(shopMap)
      .bindPopup('📍 Shop Location')
      .openPopup();

    function updateCoords(lat, lng) {
      document.getElementById('shopLat').value        = lat;
      document.getElementById('shopLng').value        = lng;
      document.getElementById('shopLatDisplay').value = lat.toFixed(7);
      document.getElementById('shopLngDisplay').value = lng.toFixed(7);
    }

    // Drag marker
    shopMarker.on('dragend', function(e) {
      const ll = e.target.getLatLng();
      updateCoords(ll.lat, ll.lng);
    });

    // Click map
    shopMap.on('click', function(e) {
      shopMarker.setLatLng(e.latlng);
      updateCoords(e.latlng.lat, e.latlng.lng);
    });
  });

  function confirmShopLocation() {
    const lat = document.getElementById('shopLat').value;
    const lng = document.getElementById('shopLng').value;
    cakeConfirm({
      title:'Update Shop Location?',
      message:'Lat: ' + lat + '\nLng: ' + lng + '\n\nThis will change the default map center on the customer checkout page.',
      icon:'bi-geo-alt',
      iconBg:'#ede9fe',
      iconColor:'#7c3aed',
      okLabel:'Update Location',
      okColor:'#7c3aed',
      onConfirm:function() {
        document.getElementById('shopLocationForm').submit();
      }
    });
  }
  </script>


  @elseif($tab==='capacity')
  <div class="card">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-1"><i class="bi bi-layers me-2" style="color:var(--primary)"></i>Daily Capacity Settings</h5>
      <p class="text-muted small mb-4">Set how many total cake pieces the shop can make per day. The system will limit orders based on the delivery date's lead time.</p>

      <form action="{{ route('admin.settings.daily_capacity') }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-12">
            <div class="alert border-0 py-2 small" style="background:#fff0f5">
              <i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>
              Set to <strong>0</strong> to disable the limit (unlimited orders allowed).
            </div>
          </div>

          <div class="col-sm-6">
            <label class="form-label fw-semibold small">Max Cakes Per Day (Total Shop)</label>
            <input type="number" min="0" class="form-control" name="daily_max_cakes"
                   value="{{ $settings['daily_max_cakes'] ?? 0 }}"
                   placeholder="e.g. 10">
            <div class="form-text">Overall max pcs across all products per day. 0 = unlimited.</div>
          </div>

          <div class="col-12 mt-2">
            <div class="fw-semibold small mb-3" style="color:var(--primary)">
              <i class="bi bi-clock-history me-1"></i>Lead Time Limits
              <span class="text-muted fw-normal ms-1">(overrides daily max for close-deadline orders)</span>
            </div>
            <div class="row g-3">
              <div class="col-sm-4">
                <label class="form-label fw-semibold small">1 Day Ahead</label>
                <input type="number" min="0" class="form-control" name="lead_1day_max"
                       value="{{ $settings['lead_1day_max'] ?? 0 }}"
                       placeholder="e.g. 3">
                <div class="form-text">Max pcs if delivery is tomorrow. 0 = use daily max.</div>
              </div>
              <div class="col-sm-4">
                <label class="form-label fw-semibold small">2 Days Ahead</label>
                <input type="number" min="0" class="form-control" name="lead_2day_max"
                       value="{{ $settings['lead_2day_max'] ?? 0 }}"
                       placeholder="e.g. 6">
                <div class="form-text">Max pcs if delivery is 2 days from now. 0 = use daily max.</div>
              </div>
              <div class="col-sm-4">
                <label class="form-label fw-semibold small">3+ Days Ahead</label>
                <input type="number" min="0" class="form-control" name="lead_3day_plus_max"
                       value="{{ $settings['lead_3day_plus_max'] ?? 0 }}"
                       placeholder="e.g. 10">
                <div class="form-text">Max pcs if delivery is 3+ days away. 0 = use daily max.</div>
              </div>
            </div>
          </div>

          {{-- Live preview --}}
          <div class="col-12 mt-2">
            <div class="p-3 rounded-3" style="background:#f8f9fa;border:1px solid #e9ecef">
              <div class="fw-semibold small mb-2"><i class="bi bi-eye me-1"></i>Preview</div>
              <div class="small text-muted">
                Based on your settings, here is how the system will behave:
              </div>
              <ul class="small mt-2 mb-0" style="color:#444">
                <li><strong>Tomorrow:</strong> max <strong id="prev1day">—</strong> pcs</li>
                <li><strong>2 days from now:</strong> max <strong id="prev2day">—</strong> pcs</li>
                <li><strong>3+ days from now:</strong> max <strong id="prev3day">—</strong> pcs</li>
              </ul>
            </div>
          </div>

          <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-save me-1"></i>Save Daily Capacity Settings
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <script>
  function updatePreview() {
    const daily = parseInt(document.querySelector('[name="daily_max_cakes"]')?.value) || 0;
    const d1    = parseInt(document.querySelector('[name="lead_1day_max"]')?.value) || 0;
    const d2    = parseInt(document.querySelector('[name="lead_2day_max"]')?.value) || 0;
    const d3    = parseInt(document.querySelector('[name="lead_3day_plus_max"]')?.value) || 0;
    const fmt   = (v, fallback) => v > 0 ? v + ' pcs' : (fallback > 0 ? fallback + ' pcs (daily max)' : 'unlimited');
    document.getElementById('prev1day').textContent = fmt(d1, daily);
    document.getElementById('prev2day').textContent = fmt(d2, daily);
    document.getElementById('prev3day').textContent = fmt(d3, daily);
  }
  document.querySelectorAll('[name="daily_max_cakes"],[name="lead_1day_max"],[name="lead_2day_max"],[name="lead_3day_plus_max"]')
    .forEach(el => el.addEventListener('input', updatePreview));
  updatePreview();
  </script>

  @endif
@push('scripts')
<script>
// ── Background type toggle ─────────────────────────────
function updateBgSections() {
  const sel   = document.querySelector('input[name="bg_type"]:checked')?.value;
  const wrap  = document.getElementById('bgOpacityWrap');
  if (wrap) wrap.style.display = (sel === 'image') ? 'block' : 'none';
}
document.querySelectorAll('input[name="bg_type"]').forEach(r =>
  r.addEventListener('change', updateBgSections)
);
updateBgSections();

function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById(previewId);
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function previewAdminPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const prev = document.getElementById('adminPhotoPreview');
      if (prev.tagName === 'IMG') {
        prev.src = e.target.result;
      } else {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.id = 'adminPhotoPreview';
        img.style = 'width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)';
        prev.replaceWith(img);
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  if (input.type === 'password') { input.type = 'text'; btn.innerHTML = '<i class="bi bi-eye-slash"></i>'; }
  else { input.type = 'password'; btn.innerHTML = '<i class="bi bi-eye"></i>'; }
}
@if(session('acp_step') == 2)
(function() {
  const wrap  = document.getElementById('acpResendWrap');
  const form  = document.getElementById('acpResendForm');
  const cdEl  = document.getElementById('acpResendCountdown');
  if (!wrap || !form || !cdEl) return;
  let seconds = 60;
  const timer = setInterval(() => {
    seconds--;
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    cdEl.textContent = m + ':' + String(s).padStart(2, '0');
    if (seconds <= 0) {
      clearInterval(timer);
      wrap.style.display = 'none';
      form.style.display = 'block';
    }
  }, 1000);
})();
@endif
</script>
@endpush
@endsection
