@extends('layouts.app')
@section('page_title','Shop Settings')
@section('content')
@php
  $commissionEnabled = (bool)($shop->commission_enabled ?? 1);
  $commissionRate = $commissionEnabled ? (float)($shop->commission_rate ?? 0) : 0;
@endphp
<style>
.s-tab {
  background: none; border: none; border-bottom: 3px solid transparent;
  padding: .65rem 1.1rem; font-size: .875rem; font-weight: 600;
  color: var(--gray-500); cursor: pointer; white-space: nowrap;
  display: flex; align-items: center; gap: .45rem;
  transition: color .15s, border-color .15s;
  margin-bottom: -2px;
}
.s-tab:hover { color: var(--primary); }
.s-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.setting-card {
  background: #fff; border-radius: var(--radius-lg);
  border: 1.5px solid var(--gray-100); overflow: hidden; margin-bottom: 1.5rem;
}
.setting-card-header {
  padding: 1.1rem 1.5rem; border-bottom: 1.5px solid var(--gray-100);
  display: flex; align-items: center; gap: .6rem;
}
.setting-card-header .title {
  font-size: .95rem; font-weight: 700; color: var(--gray-900);
}
.setting-card-header .subtitle {
  font-size: .78rem; color: var(--gray-500); margin: .2rem 0 0;
}
.setting-card-body { padding: 1.5rem; }
#spane-profile .setting-card,
#spane-capacity .setting-card,
#spane-password .setting-card,
#spane-delivery > .row,
#spane-upgrade {
  width: 100%;
  max-width: none !important;
}
#spane-profile,
#spane-capacity,
#spane-delivery,
#spane-password,
#spane-upgrade {
  width: 100%;
}
.formula-box {
  background: #0f172a; border-radius: 10px; padding: 1.25rem 1.5rem;
  font-family: 'Courier New', monospace; color: #e2e8f0;
  font-size: .85rem; line-height: 1.9; margin-bottom: 1.25rem;
}
.formula-box .f-label  { color: #94a3b8; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .5rem; }
.formula-box .f-main   { color: #f8fafc; font-size: 1rem; font-weight: 600; }
.formula-box .f-var-a  { color: #34d399; }
.formula-box .f-var-b  { color: #60a5fa; }
.formula-box .f-var-c  { color: #f472b6; }
.formula-box .f-var-d  { color: #fbbf24; }
.formula-box .f-dim    { color: #64748b; }
.sim-result {
  background: #f8fafc; border: 1.5px solid var(--gray-100);
  border-radius: 10px; padding: 1rem 1.25rem;
}
.sim-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: .35rem 0; font-size: .83rem; border-bottom: 1px solid var(--gray-100);
}
.sim-row:last-child { border-bottom: none; }
.sim-row .lbl { color: var(--gray-500); }
.sim-row .val { font-weight: 600; color: var(--gray-900); }
.sim-row.sim-total { padding-top: .6rem; margin-top: .25rem; border-top: 2px solid var(--gray-200); border-bottom: none; }
.sim-row.sim-total .lbl { font-weight: 700; color: var(--gray-700); font-size: .88rem; }
.sim-row.sim-total .val { font-size: 1.1rem; color: var(--primary); }
.sim-free { text-align: center; padding: 1rem; color: #15803d; font-weight: 700; font-size: .95rem; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; }
.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: .35rem; }
.shop-cover-picker { position:relative;min-height:150px;border-radius:var(--radius-lg);border:1.5px solid var(--gray-200);overflow:hidden;background:linear-gradient(135deg,var(--primary-bg),#fff); }
.shop-cover-picker img { width:100%;height:100%;min-height:150px;object-fit:cover;display:block; }
.shop-cover-picker.empty { display:flex;align-items:center;justify-content:center;padding:1.25rem; }
.shop-cover-overlay { position:absolute;inset:0;display:flex;align-items:flex-end;padding:1rem;background:linear-gradient(to bottom,rgba(0,0,0,.04),rgba(0,0,0,.46));color:#fff;pointer-events:none; }
.shop-cover-upload { margin-top:.75rem; }
@media(max-width:575.98px){.shop-cover-picker,.shop-cover-picker img{min-height:118px}.shop-cover-overlay{padding:.8rem}}
</style>

<div>
  {{-- Header --}}
  <div style="margin-bottom:1.75rem">
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Shop Settings</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Manage your shop profile, capacity, delivery pricing, and account security.</p>
  </div>

  {{-- Alerts --}}
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

  {{-- Tier Banner --}}
  @php $upgradeStatus = $shop->upgrade_request_status ?? null; @endphp
  @if($shop->tier === 'verified')
  <div style="background:#FFF3E0;border:1.5px solid #FFCC80;border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.75rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <i class="bi bi-patch-check-fill" style="font-size:1.5rem;color:#E65100"></i>
    <div>
      <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">Verified Seller</div>
      <div style="font-size:.78rem;color:var(--gray-600)">
        @if($commissionEnabled) Commission: {{ number_format($commissionRate, 2) }}% on paid orders
        @else Commission: OFF for your shop right now @endif
      </div>
    </div>
  </div>
  @elseif($upgradeStatus === 'pending')
  <div style="background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:.875rem">
      <i class="bi bi-hourglass-split" style="font-size:1.4rem;color:#2563EB"></i>
      <div>
        <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">Upgrade to Verified — Under Review</div>
        <div style="font-size:.78rem;color:#3B82F6">Your upgrade request is being reviewed by our team. We'll notify you within 1-3 business days.</div>
      </div>
    </div>
    <span style="background:#DBEAFE;color:#1D4ED8;font-size:.75rem;font-weight:700;padding:.3rem .85rem;border-radius:99px;white-space:nowrap">Pending Review</span>
  </div>
  @elseif($upgradeStatus === 'rejected')
  <div style="background:#FFF1F2;border:1.5px solid #FDA4AF;border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.75rem">
    <div style="display:flex;align-items:center;gap:.875rem;margin-bottom:.6rem">
      <i class="bi bi-x-circle-fill" style="font-size:1.4rem;color:#E11D48"></i>
      <div>
        <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">Upgrade Request Not Approved</div>
        <div style="font-size:.78rem;color:#E11D48">{{ $shop->upgrade_request_note ?? 'Please review your submitted documents and try again.' }}</div>
      </div>
    </div>
    <button onclick="showSettingsTab('upgrade')" style="background:var(--primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.82rem;font-weight:600;cursor:pointer">
      <i class="bi bi-arrow-repeat me-1"></i> Re-submit Upgrade Request
    </button>
  </div>
  @else
  <div style="background:linear-gradient(135deg,#fffbf2 0%,#fff7e6 100%);border:1.5px solid #FCD34D;border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:.875rem">
      <i class="bi bi-person-check" style="font-size:1.4rem;color:var(--gray-500)"></i>
      <div>
        <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">Basic Seller</div>
        <div style="font-size:.78rem;color:var(--gray-600)">
          @if($commissionEnabled) Commission: {{ number_format($commissionRate, 2) }}% on paid orders
          @else Commission: OFF for your shop right now @endif
        </div>
      </div>
    </div>
    <button onclick="showSettingsTab('upgrade')" style="background:var(--primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.25rem;font-size:.82rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
      <i class="bi bi-patch-check-fill"></i> Upgrade to Verified
    </button>
  </div>
  @endif

  {{-- Tab Navigation --}}
  <div style="display:flex;border-bottom:2px solid var(--gray-100);margin-bottom:2rem;overflow-x:auto;gap:0">
    <button onclick="showSettingsTab('profile')"  id="stab-profile"  class="s-tab active">
      <i class="bi bi-shop"></i> Shop Profile
    </button>
    <button onclick="showSettingsTab('capacity')" id="stab-capacity" class="s-tab">
      <i class="bi bi-calendar-check"></i> Custom Cake Capacity
    </button>
    <button onclick="showSettingsTab('delivery')" id="stab-delivery" class="s-tab">
      <i class="bi bi-truck"></i> Delivery Fee
    </button>
    <button onclick="showSettingsTab('password')" id="stab-password" class="s-tab">
      <i class="bi bi-lock"></i> Change Password
    </button>
    @if($shop->tier !== 'verified')
    <button onclick="showSettingsTab('upgrade')" id="stab-upgrade" class="s-tab" style="position:relative">
      <i class="bi bi-patch-check-fill" style="color:#E65100"></i> Upgrade to Verified
      @if(($shop->upgrade_request_status ?? null) === 'pending')
        <span style="position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#2563EB"></span>
      @elseif(($shop->upgrade_request_status ?? null) === 'rejected')
        <span style="position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#E11D48"></span>
      @endif
    </button>
    @endif
  </div>

  {{-- ── PANE: Shop Profile ─────────────────────────────── --}}
  <div id="spane-profile">
    <div class="setting-card" style="max-width:780px">
      <div class="setting-card-header">
        <i class="bi bi-shop" style="font-size:1.1rem;color:var(--primary)"></i>
        <div>
          <div class="title">Shop Profile</div>
          <div class="subtitle">Your shop's public-facing information</div>
        </div>
      </div>
      <div class="setting-card-body">
        <form action="{{ route('seller.settings.shop') }}" method="POST" enctype="multipart/form-data" novalidate>
          @csrf
          <input type="hidden" name="_section" value="profile">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Shop Name <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="shop_name" value="{{ old('shop_name', $shop->shop_name) }}"
                     required minlength="3" maxlength="100"
                     oninvalid="this.setCustomValidity('Shop name is required (min 3 chars)')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
              <textarea class="form-control" name="description" rows="3" maxlength="500">{{ old('description', $shop->description) }}</textarea>
              <div class="form-text">Max 500 characters</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">City / Municipality <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="city" value="{{ old('city', $shop->city) }}"
                     required maxlength="80"
                     oninvalid="this.setCustomValidity('City is required')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">GCash Number <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="gcash_number" value="{{ old('gcash_number', $shop->gcash_number) }}"
                     required pattern="(\+63)?9[0-9]{9}"
                     oninvalid="this.setCustomValidity('Enter a valid GCash number')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Business Address <span style="color:var(--danger)">*</span></label>
              <input type="text" class="form-control" name="address" value="{{ old('address', $shop->address) }}"
                     required maxlength="255"
                     oninvalid="this.setCustomValidity('Address is required')"
                     oninput="this.setCustomValidity('')">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Shop Logo <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
              @if($shop->shop_logo)
                <img src="{{ $shop->shop_logo }}" style="display:block;width:72px;height:72px;border-radius:14px;object-fit:cover;margin-bottom:.5rem;border:2px solid var(--gray-200)">
              @endif
              <input type="file" class="form-control" name="shop_logo" accept=".jpg,.jpeg,.png"
                     onchange="previewFile(this,'logoPreview')" style="font-size:.8rem">
              <img id="logoPreview" style="display:none;max-height:72px;margin-top:.4rem;border-radius:14px;object-fit:cover">
              <div class="form-text">JPG or PNG · Max 3 MB</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Shop Background / Cover Image <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
              <div class="shop-cover-picker {{ empty($shop->shop_cover) ? 'empty' : '' }}" id="shopCoverPicker">
                @if($shop->shop_cover)
                  <img id="coverPreview" src="{{ $shop->shop_cover }}" alt="Current shop cover">
                @else
                  <img id="coverPreview" alt="Shop cover preview" style="display:none">
                  <div id="coverEmptyState" style="text-align:center;color:var(--gray-500)">
                    <i class="bi bi-image" style="font-size:1.7rem;color:var(--primary)"></i>
                    <div style="font-size:.86rem;font-weight:700;color:var(--gray-800);margin-top:.35rem">Add a branded shop background</div>
                    <div style="font-size:.74rem">Best-selling cakes, counter display, or clean packaging shot</div>
                  </div>
                @endif
                <div class="shop-cover-overlay">
                  <div>
                    <div style="font-size:.82rem;font-weight:800">Used on your shop, checkout, and seller pages</div>
                    <div style="font-size:.7rem;opacity:.86">Choose a clean photo without heavy text.</div>
                  </div>
                </div>
              </div>
              <input type="file" class="form-control shop-cover-upload" name="shop_cover" accept=".jpg,.jpeg,.png"
                     onchange="previewFile(this,'coverPreview')" style="font-size:.8rem">
              <div class="form-text">JPG or PNG · Max 5 MB. Wide photos look best.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Shop Theme Color <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
              <div style="display:flex;align-items:center;gap:.75rem">
                <input type="color" name="theme_color" id="themeColorPicker"
                       value="{{ old('theme_color', $shop->theme_color ?? '#E53935') }}"
                       style="width:48px;height:40px;padding:2px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);cursor:pointer;background:#fff">
                <div id="colorPreviewBox"
                     style="height:40px;flex:1;border-radius:var(--radius-sm);border:1.5px solid var(--gray-200);background:{{ old('theme_color', $shop->theme_color ?? '#E53935') }};transition:background .15s"></div>
              </div>
              <div class="form-text">Customers will see this as your shop's accent color.</div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary" style="padding:.65rem 2rem;font-weight:600">
              <i class="bi bi-check-lg me-1"></i> Save Shop Profile
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ── PANE: Daily Capacity ────────────────────────────── --}}
  <div id="spane-capacity" style="display:none">
    <div class="setting-card" style="max-width:780px">
      <div class="setting-card-header">
        <i class="bi bi-calendar-check" style="font-size:1.1rem;color:var(--primary)"></i>
        <div>
          <div class="title">Custom Cake Daily Capacity</div>
          <div class="subtitle">Limit how many custom cake requests your shop can accept per preferred date. Set to 0 to pause custom cake checkout until capacity is configured.</div>
        </div>
      </div>
      <div class="setting-card-body">
        @php
          $capacitySchedule = json_decode($shopSettings->custom_capacity_schedule ?? '[]', true) ?: [];
          $fulfillmentSlots = \Illuminate\Support\Facades\Schema::hasTable('fulfillment_time_slots')
              ? \Illuminate\Support\Facades\DB::table('fulfillment_time_slots')->where('shop_id', $shop->id)->orderBy('sort_order')->orderBy('start_time')->get()
              : collect();
          if ($fulfillmentSlots->isEmpty()) {
              $fulfillmentSlots = app(\App\Services\FulfillmentScheduleService::class)->slots($shop->id);
          }
        @endphp
        <form action="{{ route('seller.settings.daily_capacity') }}" method="POST">
          @csrf
          <input type="hidden" name="_section" value="capacity">
          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label fw-semibold">Custom Slots / Allowed Day</label>
              <input type="number" min="0" class="form-control" name="daily_max_cakes"
                     value="{{ old('daily_max_cakes', $shopSettings->daily_max_cakes ?? 0) }}"
                     oninput="updateCapacityPreview()">
              <div class="form-text">Maximum custom cake quantity accepted per allowed preferred date. 0 = pause custom cake checkout.</div>
              <input type="hidden" name="lead_1day_max" value="0">
              <input type="hidden" name="lead_2day_max" value="0">
              <input type="hidden" name="lead_3day_plus_max" value="0">
            </div>
            <div class="col-lg-6">
              <div class="h-100 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
                <div class="fw-semibold small mb-1"><i class="bi bi-info-circle me-1" style="color:var(--primary)"></i>How this works</div>
                <div class="text-muted" style="font-size:.82rem">Capacity starts only on dates allowed by your custom cake prep days. Bulk quantities above the normal limit should be reviewed manually by the seller.</div>
              </div>
            </div>
            <div class="col-12">
              <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
                <div class="d-flex align-items-center gap-2 mb-3">
                  <i class="bi bi-calendar2-week" style="color:var(--primary)"></i>
                  <div>
                    <div class="fw-semibold">Relative Day Capacity</div>
                    <div class="text-muted" style="font-size:.82rem">Optional. These start from the earliest allowed custom date based on Custom Cake Prep Days. Leave 0 to use the default capacity above.</div>
                  </div>
                </div>
                <div class="row g-3">
                  @foreach([0 => 'Earliest allowed day', 1 => 'Next day', 2 => 'Next 2 days', 3 => 'Next 3 days', 4 => 'Next 4 days'] as $offset => $label)
                    <div class="col-sm-6 col-lg">
                      <label class="form-label fw-semibold small">{{ $label }}</label>
                      <input type="number" min="0" max="999" class="form-control" name="custom_capacity_schedule[{{ $offset }}]"
                             value="{{ old('custom_capacity_schedule.'.$offset, $capacitySchedule[(string)$offset] ?? 0) }}"
                             oninput="updateCapacityPreview()">
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="p-3 rounded-3" style="background:#fff7fb;border:1px solid #fce7f3">
                <div class="d-flex align-items-center gap-2 mb-3">
                  <i class="bi bi-hourglass-split" style="color:var(--primary)"></i>
                  <div>
                    <div class="fw-semibold">Preparation, Buffer & Hold Rules</div>
                    <div class="text-muted" style="font-size:.82rem">Controls earliest checkout slots for pickup and delivery. Same-day slots only show when there is enough preparation and travel time.</div>
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Ready-made Prep Days</label>
                    <input type="number" min="0" max="30" class="form-control" name="ready_made_prep_days"
                           value="{{ old('ready_made_prep_days', $shopSettings->ready_made_prep_days ?? 0) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Date-level lead time. Keep 0 if ready-made cakes can be scheduled today.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Ready-made Prep Minutes</label>
                    <input type="number" min="0" max="1440" class="form-control" name="ready_made_prep_minutes"
                           value="{{ old('ready_made_prep_minutes', $shopSettings->ready_made_prep_minutes ?? 90) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Blocks same-day slots that are too soon for baking/packing.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Pickup Buffer Minutes</label>
                    <input type="number" min="0" max="480" class="form-control" name="pickup_buffer_minutes"
                           value="{{ old('pickup_buffer_minutes', $shopSettings->pickup_buffer_minutes ?? 0) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Extra handoff buffer added to pickup orders.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Custom Cake Prep Days</label>
                    <input type="number" min="0" max="30" class="form-control" name="custom_cake_prep_days"
                           value="{{ old('custom_cake_prep_days', $shopSettings->custom_cake_prep_days ?? 3) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Date-level lead time for custom preferred dates.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Custom Prep Minutes</label>
                    <input type="number" min="0" max="1440" class="form-control" name="custom_cake_prep_minutes"
                           value="{{ old('custom_cake_prep_minutes', $shopSettings->custom_cake_prep_minutes ?? 0) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Optional same-day buffer when custom prep days is 0.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Cart Hold Minutes</label>
                    <input type="number" min="1" max="120" class="form-control" name="custom_cart_hold_minutes"
                           value="{{ old('custom_cart_hold_minutes', $shopSettings->custom_cart_hold_minutes ?? 15) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Customer must refresh fulfillment after expiry.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Delivery Base Buffer Minutes</label>
                    <input type="number" min="0" max="480" class="form-control" name="delivery_base_buffer_minutes"
                           value="{{ old('delivery_base_buffer_minutes', $shopSettings->delivery_base_buffer_minutes ?? 30) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Added to delivery orders before distance is considered.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Delivery Minutes per km</label>
                    <input type="number" min="0" max="120" class="form-control" name="delivery_minutes_per_km"
                           value="{{ old('delivery_minutes_per_km', $shopSettings->delivery_minutes_per_km ?? 5) }}" oninput="updateCapacityPreview()">
                    <div class="form-text">Estimated travel buffer based on customer distance from the shop.</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history" style="color:var(--primary)"></i>
                    <div>
                      <div class="fw-semibold">Fulfillment Time Slots</div>
                      <div class="text-muted" style="font-size:.82rem">Set real start and end times. Customer labels are generated automatically from the time range.</div>
                    </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="addScheduleSlotRow()">
                    <i class="bi bi-plus-lg me-1"></i>Add Slot
                  </button>
                </div>
                <div id="scheduleSlotRows" class="d-grid gap-2">
                  @foreach($fulfillmentSlots as $idx => $slot)
                    <div class="row g-2 align-items-end schedule-slot-row" style="background:#fff;border:1px solid #e5e7eb;border-radius:.8rem;padding:.75rem">
                      <input type="hidden" name="slots[{{ $idx }}][id]" value="{{ $slot->id ?? '' }}">
                      <input type="hidden" name="slots[{{ $idx }}][sort_order]" value="{{ $idx + 1 }}">
                      <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Start</label>
                        <input type="time" class="form-control" name="slots[{{ $idx }}][start_time]" value="{{ old('slots.'.$idx.'.start_time', substr($slot->start_time, 0, 5)) }}" required>
                      </div>
                      <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">End</label>
                        <input type="time" class="form-control" name="slots[{{ $idx }}][end_time]" value="{{ old('slots.'.$idx.'.end_time', substr($slot->end_time, 0, 5)) }}" required>
                      </div>
                      <div class="col-md-4">
                        <input type="hidden" name="slots[{{ $idx }}][is_active]" value="0">
                        <div class="form-check form-switch mb-2">
                          <input class="form-check-input" type="checkbox" name="slots[{{ $idx }}][is_active]" value="1" @checked(old('slots.'.$idx.'.is_active', $slot->is_active ?? true)) title="Active">
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
                <div class="form-text mt-2"><i class="bi bi-shield-check me-1"></i>Customers can only submit slots that still satisfy prep and delivery buffers on the server.</div>
              </div>
            </div>
            <div class="col-12">
              <div id="capacityPreview" style="font-size:.82rem;color:var(--gray-600);background:var(--gray-50);border-radius:var(--radius-sm);padding:.6rem 1rem"></div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary" style="padding:.65rem 2rem;font-weight:600">
              <i class="bi bi-check-lg me-1"></i> Save Capacity Settings
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ── PANE: Delivery Fee ──────────────────────────────── --}}
  <div id="spane-delivery" style="display:none">
    <div class="row g-4" style="max-width:1100px">

      {{-- Left: Settings Form --}}
      <div class="col-lg-5">
        <div class="setting-card">
          <div class="setting-card-header">
            <i class="bi bi-truck" style="font-size:1.1rem;color:var(--primary)"></i>
            <div>
              <div class="title">Delivery Fee</div>
              <div class="subtitle">Set your base fee and per-km rate</div>
            </div>
          </div>
          <div class="setting-card-body">
            <form action="{{ route('seller.settings.delivery_calc') }}" method="POST" id="deliveryCalcForm">
              @csrf
              <input type="hidden" name="_section" value="delivery">
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="legend-dot" style="background:#34d399"></span>Base Delivery Fee (₱)
                </label>
                <div class="input-group">
                  <span class="input-group-text fw-bold" style="color:#059669">₱</span>
                  <input type="number" step="0.01" min="0" class="form-control" name="base_fee" id="inp_base"
                         value="{{ old('base_fee', $shopSettings->base_fee ?? 30) }}"
                         oninput="updateDeliveryCalc()">
                </div>
                <div class="form-text">Fixed charge added to every delivery order regardless of distance.</div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="legend-dot" style="background:#60a5fa"></span>Rate per km (₱/km)
                </label>
                <div class="input-group">
                  <span class="input-group-text fw-bold" style="color:#0284c7">₱</span>
                  <input type="number" step="0.01" min="0" class="form-control" name="fee_per_km" id="inp_pkm"
                         value="{{ old('fee_per_km', $shopSettings->fee_per_km ?? 15) }}"
                         oninput="updateDeliveryCalc()">
                  <span class="input-group-text">/km</span>
                </div>
                <div class="form-text">Additional charge per kilometer from your shop to the customer.</div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="legend-dot" style="background:#fbbf24"></span>Free Delivery Distance (km)
                </label>
                <div class="input-group">
                  <input type="number" step="0.1" min="0" class="form-control" name="free_delivery_km" id="inp_free_km"
                         value="{{ old('free_delivery_km', number_format((float)($shopSettings->free_delivery_radius ?? 0) / 1000, 1, '.', '')) }}"
                         oninput="updateDeliveryCalc()">
                  <span class="input-group-text">km free</span>
                </div>
                <div class="form-text">Customers inside this distance pay ₱0 delivery fee. Beyond it, only the excess distance is charged.</div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="legend-dot" style="background:#ec4899"></span>Coverage Detection Radius (km)
                </label>
                <div class="input-group">
                  <input type="number" step="0.5" min="1" max="25" class="form-control" name="delivery_coverage_km"
                         value="{{ old('delivery_coverage_km', number_format((float)($shopSettings->delivery_coverage_radius ?? 5000) / 1000, 1, '.', '')) }}">
                  <span class="input-group-text">km per pin</span>
                </div>
                <div class="form-text">How far a customer pin can be from one saved coverage pin before checkout marks it out of coverage.</div>
              </div>
              <div class="alert border-0 py-2 small mb-4" style="background:#f0f9ff;color:#075985;border-left:4px solid #38bdf8!important">
                <i class="bi bi-link-45deg me-1"></i>
                Delivery Zones control where you deliver. Delivery Fee controls how much customers pay inside those zones.
              </div>
              <div class="mt-4">
                <button type="submit" class="btn btn-primary w-100" style="padding:.65rem;font-weight:600">
                  <i class="bi bi-check-lg me-1"></i>Save Delivery Fee Settings
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      {{-- Right: Formula Display + Simulator --}}
      <div class="col-lg-7">

        {{-- How It Works Card --}}
        <div class="setting-card">
          <div class="setting-card-header">
            <i class="bi bi-info-circle" style="font-size:1.1rem;color:var(--primary)"></i>
            <div>
              <div class="title">How is the delivery fee calculated?</div>
              <div class="subtitle">Updates automatically as you change the values on the left</div>
            </div>
          </div>
          <div class="setting-card-body" style="padding-bottom:1.25rem">

            {{-- Plain explanation --}}
            <p style="font-size:.875rem;color:var(--gray-600);margin:0 0 1.1rem;line-height:1.65">
              Every delivery order is charged in <strong>two parts</strong>:
            </p>

            {{-- Part 1: Base Fee --}}
            <div style="display:flex;align-items:flex-start;gap:.85rem;margin-bottom:.75rem;padding:.9rem 1rem;background:#f0fdf4;border-radius:10px;border:1.5px solid #bbf7d0">
              <div style="width:34px;height:34px;border-radius:50%;background:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-weight:800;font-size:.88rem">1</div>
              <div>
                <div style="font-weight:700;font-size:.9rem;color:#065f46;margin-bottom:.2rem">Base Delivery Fee</div>
                <div style="font-size:.82rem;color:#374151;line-height:1.55">
                  A fixed charge of <strong id="fml_base" style="color:#059669">₱30.00</strong> is added only when the customer is outside your free delivery distance.
                </div>
              </div>
            </div>

            {{-- Part 2: Per-km --}}
            <div style="display:flex;align-items:flex-start;gap:.85rem;margin-bottom:1.25rem;padding:.9rem 1rem;background:#eff6ff;border-radius:10px;border:1.5px solid #bfdbfe">
              <div style="width:34px;height:34px;border-radius:50%;background:#0284c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-weight:800;font-size:.88rem">2</div>
              <div>
                <div style="font-weight:700;font-size:.9rem;color:#1e40af;margin-bottom:.2rem">Distance Charge</div>
                <div style="font-size:.82rem;color:#374151;line-height:1.55">
                  <strong id="fml_pkm" style="color:#0284c7">₱15.00</strong> is added per kilometer after the free delivery distance.
                </div>
              </div>
            </div>

            <div style="display:flex;align-items:flex-start;gap:.85rem;margin-bottom:1.25rem;padding:.9rem 1rem;background:#fffbeb;border-radius:10px;border:1.5px solid #fde68a">
              <div style="width:34px;height:34px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-weight:800;font-size:.88rem">3</div>
              <div>
                <div style="font-weight:700;font-size:.9rem;color:#92400e;margin-bottom:.2rem">Free Distance</div>
                <div style="font-size:.82rem;color:#374151;line-height:1.55">
                  The first <strong id="fml_free" style="color:#b45309">0.0 km</strong> can be free. Customers beyond that are charged only for the extra distance.
                </div>
              </div>
            </div>

            {{-- Live example --}}
            <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:1rem 1.1rem">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#92400e;margin-bottom:.7rem">
                <i class="bi bi-lightbulb-fill me-1" style="color:#f59e0b"></i>Example — Customer is 3 km away
              </div>
              <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.85rem">
                <div style="display:flex;justify-content:space-between">
                  <span style="color:#6b7280">Base Fee</span>
                  <strong id="ex_base" style="color:#374151">₱30.00</strong>
                </div>
                <div style="display:flex;justify-content:space-between">
                  <span style="color:#6b7280">Chargeable distance (<span id="ex_charge_km">3.0</span> km × <span id="ex_rate">₱15.00</span>/km)</span>
                  <strong id="ex_dist" style="color:#374151">₱45.00</strong>
                </div>
                <div style="height:1px;background:#fde68a;margin:.2rem 0"></div>
                <div style="display:flex;justify-content:space-between">
                  <span style="font-weight:700;color:#92400e">Total Delivery Fee</span>
                  <strong id="ex_total" style="color:var(--primary);font-size:1rem">₱75.00</strong>
                </div>
              </div>
            </div>

          </div>
        </div>

        {{-- Simulator Card --}}
        <div class="setting-card">
          <div class="setting-card-header">
            <i class="bi bi-calculator" style="font-size:1.1rem;color:var(--primary)"></i>
            <div>
              <div class="title">Fee Simulator</div>
              <div class="subtitle">Test how much a customer would be charged at any distance</div>
            </div>
          </div>
          <div class="setting-card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Test Distance: <span id="sim_km_label" style="color:var(--primary)">3.0 km</span>
              </label>
              <input type="range" class="form-range" id="sim_slider" min="0.1" max="20" step="0.1" value="3"
                     oninput="runSimulator(this.value)" style="accent-color:var(--primary)">
              <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--gray-400)">
                <span>0.1 km</span><span>5 km</span><span>10 km</span><span>15 km</span><span>20 km</span>
              </div>
              <div class="mt-2">
                <div class="input-group" style="max-width:180px">
                  <input type="number" class="form-control form-control-sm" id="sim_input"
                         min="0.1" max="20" step="0.1" value="3"
                         oninput="document.getElementById('sim_slider').value=this.value;runSimulator(this.value)"
                         placeholder="km">
                  <span class="input-group-text">km</span>
                </div>
              </div>
            </div>
            <div id="sim_output"></div>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- ── PANE: Change Password ───────────────────────────── --}}
  <div id="spane-password" style="display:none">
    <div class="setting-card" style="max-width:480px">
      <div class="setting-card-header">
        <i class="bi bi-lock" style="font-size:1.1rem;color:var(--primary)"></i>
        <div>
          <div class="title">Change Password</div>
          <div class="subtitle">Update your account login password</div>
        </div>
      </div>
      <div class="setting-card-body">
        <form action="{{ route('seller.settings.password') }}" method="POST" novalidate>
          @csrf
          <input type="hidden" name="_section" value="password">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Current Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="current_password" id="curPwd" required
                       oninvalid="this.setCustomValidity('Current password is required')"
                       oninput="this.setCustomValidity('')">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('curPwd',this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">New Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="newPwd"
                       required minlength="8"
                       oninvalid="this.setCustomValidity('Minimum 8 characters')"
                       oninput="this.setCustomValidity('');checkMatch()">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd',this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">At least 8 characters</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Confirm New Password <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <input type="password" class="form-control" name="password_confirmation" id="confPwd"
                       required oninput="checkMatch()"
                       oninvalid="this.setCustomValidity('Please confirm your password')"
                       onchange="this.setCustomValidity('')">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confPwd',this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div id="matchMsg" class="form-text"></div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary" style="padding:.65rem 2rem;font-weight:600">
              <i class="bi bi-shield-lock me-1"></i> Update Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<script>
// ── Tab switching ────────────────────────────────────────
function showSettingsTab(name) {
  if (name === 'appearance') name = 'profile';
  ['profile','capacity','delivery','password','upgrade'].forEach(t => {
    const pane = document.getElementById('spane-' + t);
    const tab  = document.getElementById('stab-'  + t);
    if (pane) pane.style.display = t === name ? '' : 'none';
    if (tab)  tab.classList.toggle('active', t === name);
  });
  if (name === 'delivery') { updateDeliveryCalc(); runSimulator(document.getElementById('sim_slider').value); }
  if (name === 'capacity') updateCapacityPreview();
}

// Restore active tab from URL ?tab= param or old('_section')
(function () {
  const urlTab  = new URLSearchParams(window.location.search).get('tab');
  const oldSect = '{{ old("_section") }}';
  const active  = urlTab || oldSect || 'profile';
  if (active !== 'profile') showSettingsTab(active);
  else { updateDeliveryCalc(); updateCapacityPreview(); }
})();

// ── Shop Profile helpers ─────────────────────────────────
document.getElementById('themeColorPicker').addEventListener('input', function() {
  document.getElementById('colorPreviewBox').style.background = this.value;
});
function previewFile(input, id) {
  const img = document.getElementById(id);
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    img.src = e.target.result;
    img.style.display = 'block';
    if (id === 'coverPreview') {
      const picker = document.getElementById('shopCoverPicker');
      const empty = document.getElementById('coverEmptyState');
      if (picker) picker.classList.remove('empty');
      if (empty) empty.style.display = 'none';
    }
  };
  r.readAsDataURL(input.files[0]);
}

// ── Password helpers ─────────────────────────────────────
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
  btn.querySelector('i').className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
function checkMatch() {
  const p = document.getElementById('newPwd');
  const c = document.getElementById('confPwd');
  const m = document.getElementById('matchMsg');
  if (!c.value) { m.textContent = ''; return; }
  if (p.value === c.value) {
    m.textContent = 'Passwords match ✓'; m.style.color = 'var(--success,#2E7D32)';
    c.setCustomValidity('');
  } else {
    m.textContent = 'Passwords do not match'; m.style.color = 'var(--danger,#C62828)';
    c.setCustomValidity('Passwords do not match');
  }
}

// ── Capacity preview ─────────────────────────────────────
let scheduleSlotIndex = {{ $fulfillmentSlots->count() }};
function addScheduleSlotRow() {
  const wrap = document.getElementById('scheduleSlotRows');
  if (!wrap) return;
  const idx = scheduleSlotIndex++;
  const row = document.createElement('div');
  row.className = 'row g-2 align-items-end schedule-slot-row';
  row.style.cssText = 'background:#fff;border:1px solid #e5e7eb;border-radius:.8rem;padding:.75rem';
  row.innerHTML = `
    <input type="hidden" name="slots[${idx}][sort_order]" value="${idx + 1}">
    <div class="col-6 col-md-4">
      <label class="form-label small fw-semibold">Start</label>
      <input type="time" class="form-control" name="slots[${idx}][start_time]" required>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small fw-semibold">End</label>
      <input type="time" class="form-control" name="slots[${idx}][end_time]" required>
    </div>
    <div class="col-md-4">
      <input type="hidden" name="slots[${idx}][is_active]" value="0">
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="slots[${idx}][is_active]" value="1" checked title="Active">
      </div>
    </div>`;
  wrap.appendChild(row);
}
function updateCapacityPreview() {
  const daily = parseInt(document.querySelector('[name="daily_max_cakes"]')?.value) || 0;
  const prep  = parseInt(document.querySelector('[name="custom_cake_prep_days"]')?.value) || 0;
  const hold  = parseInt(document.querySelector('[name="custom_cart_hold_minutes"]')?.value) || 15;
  const el    = document.getElementById('capacityPreview');
  if (!el) return;
  if (daily === 0) {
    el.textContent = 'Custom cake checkout is paused until you set Custom Slots / Allowed Day above 0.';
    return;
  }
  const earliest = new Date();
  earliest.setDate(earliest.getDate() + prep);
  const earliestText = earliest.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
  const labels = ['Earliest allowed day', 'Next day', 'Next 2 days', 'Next 3 days', 'Next 4 days'];
  const overrides = labels.map((label, offset) => {
    const input = document.querySelector(`[name="custom_capacity_schedule[${offset}]"]`);
    const value = parseInt(input?.value || '0', 10) || 0;
    if (value <= 0) return null;
    const day = new Date();
    day.setDate(day.getDate() + prep + offset);
    return `${label} (${day.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}): ${value} pcs`;
  }).filter(Boolean);
  const overrideText = overrides.length ? ' Overrides: ' + overrides.join(' | ') + '.' : ' No relative overrides set; default applies to all allowed dates.';
  el.textContent = `Custom orders open from ${earliestText}. Default after allowed day: ${daily} pcs/date. Cart schedule hold: ${hold} minute${hold === 1 ? '' : 's'}.` + overrideText + ' Bulk quantities above available capacity should be sent as a seller-reviewed bulk request.';
}

// ── Delivery Fee Calculator ──────────────────────────────
function getDeliveryParams() {
  return {
    base : parseFloat(document.getElementById('inp_base')?.value) || 0,
    pkm  : parseFloat(document.getElementById('inp_pkm')?.value)  || 0,
    free : parseFloat(document.getElementById('inp_free_km')?.value) || 0,
  };
}

function calcFee(km, p) {
  const chargeKm = Math.max(0, km - p.free);
  if (chargeKm <= 0) return 0;
  return Math.ceil(p.base + (p.pkm * chargeKm));
}

function fmt(n) { return '₱' + n.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function updateDeliveryCalc() {
  const p = getDeliveryParams();

  // Formula display
  const fmlBase = document.getElementById('fml_base');
  const fmlPkm  = document.getElementById('fml_pkm');
  const fmlFree = document.getElementById('fml_free');
  if (fmlBase) fmlBase.textContent = fmt(p.base);
  if (fmlPkm)  fmlPkm.textContent  = fmt(p.pkm);
  if (fmlFree) fmlFree.textContent = p.free.toFixed(1) + ' km';

  // Legend
  const lb = document.getElementById('legend_base');
  const lp = document.getElementById('legend_pkm');
  if (lb) lb.textContent = fmt(p.base);
  if (lp) lp.textContent = fmt(p.pkm) + '/km';

  // Example at 3 km
  const exBase  = document.getElementById('ex_base');
  const exDist  = document.getElementById('ex_dist');
  const exTotal = document.getElementById('ex_total');
  const exRate  = document.getElementById('ex_rate');
  const exChargeKm = document.getElementById('ex_charge_km');
  const exKm = 3;
  const exBillableKm = Math.max(0, exKm - p.free);
  if (exBase)  exBase.textContent  = fmt(p.base);
  if (exDist)  exDist.textContent  = fmt(p.pkm * exBillableKm);
  if (exTotal) exTotal.textContent = fmt(calcFee(exKm, p));
  if (exRate)  exRate.textContent  = fmt(p.pkm);
  if (exChargeKm) exChargeKm.textContent = exBillableKm.toFixed(1);

  runSimulator(document.getElementById('sim_slider')?.value || 3);
}

function runSimulator(km) {
  km = parseFloat(km) || 3;
  const lbl = document.getElementById('sim_km_label');
  const inp = document.getElementById('sim_input');
  if (lbl) lbl.textContent = km.toFixed(1) + ' km';
  if (inp) inp.value = km;

  const p       = getDeliveryParams();
  const chargeKm = Math.max(0, km - p.free);
  const distAmt = p.pkm * chargeKm;
  const rawTotal= chargeKm > 0 ? p.base + distAmt : 0;
  const finalFee= calcFee(km, p);
  const out     = document.getElementById('sim_output');
  if (!out) return;

  out.innerHTML = `
    <div class="sim-result">
      <div class="sim-row">
        <span class="lbl">Distance</span>
        <span class="val">${km.toFixed(1)} km</span>
      </div>
      <div class="sim-row">
        <span class="lbl">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fbbf24;margin-right:.4rem"></span>
          Free Distance
        </span>
        <span class="val">${p.free.toFixed(1)} km</span>
      </div>
      <div class="sim-row">
        <span class="lbl">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#34d399;margin-right:.4rem"></span>
          Base Fee
        </span>
        <span class="val">${fmt(p.base)}</span>
      </div>
      <div class="sim-row">
        <span class="lbl">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#60a5fa;margin-right:.4rem"></span>
          Chargeable Distance (₱${p.pkm.toFixed(2)}/km × ${chargeKm.toFixed(1)} km)
        </span>
        <span class="val">${fmt(distAmt)}</span>
      </div>
      <div class="sim-row sim-total">
        <span class="lbl">Estimated Fee <span style="font-size:.72rem;font-weight:400;color:var(--gray-400)">(⌈ rounded up ⌉)</span></span>
        <span class="val">${fmt(finalFee)}</span>
      </div>
    </div>`;
}

// Init
updateDeliveryCalc();
updateCapacityPreview();
</script>

{{-- ── PANE: Upgrade to Verified ────────────────────── --}}
@if($shop->tier !== 'verified')
<div id="spane-upgrade" style="display:none;max-width:780px">

  @php $upgradeStatus = $shop->upgrade_request_status ?? null; @endphp

  {{-- Status: Pending --}}
  @if($upgradeStatus === 'pending')
  <div style="background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:var(--radius-lg);padding:2rem;text-align:center;margin-bottom:1.5rem">
    <div style="width:64px;height:64px;border-radius:50%;background:#DBEAFE;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
      <i class="bi bi-hourglass-split" style="font-size:1.8rem;color:#2563EB"></i>
    </div>
    <h3 style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin:0 0 .5rem">Upgrade Request Under Review</h3>
    <p style="font-size:.875rem;color:#3B82F6;margin:0">Your documents have been submitted and are currently being reviewed by our team.<br>You will be notified via SMS once a decision has been made.</p>
    <div style="margin-top:1.25rem;display:inline-flex;align-items:center;gap:.5rem;background:#DBEAFE;border-radius:99px;padding:.4rem 1rem;font-size:.78rem;font-weight:600;color:#1D4ED8">
      <i class="bi bi-clock"></i> Typically reviewed within 1-3 business days
    </div>
  </div>

  {{-- Benefits preview --}}
  <div class="setting-card">
    <div class="setting-card-header">
      <i class="bi bi-patch-check-fill" style="font-size:1.1rem;color:#E65100"></i>
      <div><div class="title">What You'll Get After Approval</div></div>
    </div>
    <div class="setting-card-body">
      <div class="row g-3">
        @foreach([
          ['bi-patch-check-fill','#E65100', '#FFF3E0', 'Verified Badge',             'A verified badge displayed on your public shop page.'],
          ['bi-palette-fill',    '#2563EB', '#EFF6FF', 'Custom Cake Orders',         'Accept personalized cake requests with design notes and reference images.'],
          ['bi-sliders',         '#7C3AED', '#F5F3FF', 'Custom Options',             'Manage flavors, sizes, layers, and delivery time choices for custom cakes.'],
          ['bi-gift-fill',       '#DB2777', '#FDF2F8', 'Add-ons',                    'Offer candles, toppers, packaging, and other cake extras.'],
          ['bi-infinity',        '#059669', '#ECFDF5', 'Unlimited Products',         'No cap on how many products you can list.'],
          ['bi-graph-up-arrow',  '#0F766E', '#F0FDFA', 'Priority Visibility',        'Verified shops can be highlighted more clearly in listings and search.'],
          ['bi-percent',         '#B45309', '#FFFBEB', 'Lower Commission Rate',      'Verified Sellers use the lower platform commission rate when enabled.'],
          ['bi-shield-check',    '#4F46E5', '#EEF2FF', 'Higher Customer Trust',      'Customers can see that your shop documents were reviewed.'],
        ] as [$icon, $color, $bg, $title, $desc])
        <div class="col-md-6">
          <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.875rem;background:{{ $bg }};border-radius:var(--radius-md)">
            <i class="bi {{ $icon }}" style="font-size:1.2rem;color:{{ $color }};margin-top:.1rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.85rem;font-weight:700;color:var(--gray-900)">{{ $title }}</div>
              <div style="font-size:.77rem;color:var(--gray-500);margin-top:.15rem">{{ $desc }}</div>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  @else

  {{-- Form --}}
  <div class="setting-card">
    <div class="setting-card-header">
      <i class="bi bi-patch-check-fill" style="font-size:1.1rem;color:#E65100"></i>
      <div>
        <div class="title">Upgrade to Verified Seller</div>
        <div class="subtitle">Submit your Business Permit or DTI Certificate for review</div>
      </div>
    </div>
    <div class="setting-card-body">

      @if($upgradeStatus === 'rejected')
      <div style="background:#FFF1F2;border:1.5px solid #FDA4AF;border-radius:var(--radius-md);padding:.875rem 1rem;margin-bottom:1.25rem;font-size:.83rem;color:#BE123C">
        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Previous request was not approved:</strong>
        {{ $shop->upgrade_request_note ?? 'Please check your documents and re-submit.' }}
      </div>
      @endif

      {{-- Benefits --}}
      <div style="background:linear-gradient(135deg,#fffbf2 0%,#fff3e0 100%);border:1.5px solid #FCD34D;border-radius:var(--radius-md);padding:1.1rem;margin-bottom:1.5rem">
        <div style="font-size:.85rem;font-weight:700;color:#92400E;margin-bottom:.75rem"><i class="bi bi-stars me-2"></i>Benefits of Verified Seller</div>
        <div class="row g-2">
          @foreach([
            ['bi-patch-check-fill', 'Verified badge on your shop'],
            ['bi-palette-fill',     'Custom cake orders'],
            ['bi-sliders',          'Custom options for flavors, sizes, layers, and time slots'],
            ['bi-gift-fill',        'Add-ons such as candles, toppers, and packaging'],
            ['bi-infinity',         'Unlimited product listings'],
            ['bi-graph-up-arrow',   'Priority visibility in shop listings'],
            ['bi-percent',          'Lower commission rate when enabled'],
            ['bi-shield-check',     'Higher customer trust'],
          ] as [$icon, $label])
          <div class="col-md-6">
            <div style="font-size:.8rem;color:#78350F;display:flex;align-items:center;gap:.5rem">
              <i class="bi {{ $icon }}" style="color:#E65100"></i> {{ $label }}
            </div>
          </div>
          @endforeach
        </div>
      </div>

      <form action="{{ route('seller.upgrade_request') }}" method="POST" enctype="multipart/form-data" novalidate id="upgradeForm">
        @csrf

        <div style="margin-bottom:1.25rem">
          <label class="form-label fw-semibold">
            Business Permit or DTI Certificate <span style="color:var(--danger)">*</span>
          </label>
          <div style="font-size:.8rem;color:var(--gray-500);margin-bottom:.75rem">
            Upload a clear photo or scan of your valid DTI Certificate, Mayor's Permit, or Business Permit.
            Our team will verify this document before approving your upgrade.
          </div>
          <div id="upgradeDropZone"
               style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:2rem;text-align:center;cursor:pointer;transition:all .2s;background:var(--gray-50)"
               onclick="document.getElementById('business_permit').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-bg)'"
               ondragleave="this.style.borderColor='var(--gray-300)';this.style.background='var(--gray-50)'"
               ondrop="upgradeHandleDrop(event)">
            <div id="upgradeFilePreview" style="display:none;margin-bottom:.75rem">
              <img id="upgradePreviewImg" style="max-height:140px;border-radius:var(--radius-sm);object-fit:contain;border:1.5px solid var(--gray-200)">
              <div id="upgradeFileName" style="font-size:.78rem;color:var(--gray-500);margin-top:.4rem"></div>
            </div>
            <i class="bi bi-cloud-upload" style="font-size:2rem;color:var(--gray-400);display:block;margin-bottom:.5rem" id="upgradeUploadIcon"></i>
            <div style="font-size:.875rem;font-weight:600;color:var(--gray-700)" id="upgradeUploadText">Click or drag to upload document</div>
            <div style="font-size:.75rem;color:var(--gray-400);margin-top:.25rem">JPG, PNG, or PDF &bull; Max 5MB</div>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.2rem">Accepted: DTI Certificate, Mayor's Permit, Business Permit</div>
          </div>
          <input type="file" id="business_permit" name="business_permit" accept=".jpg,.jpeg,.png,.pdf"
                 required class="d-none"
                 onchange="upgradePreviewFile(this)"
                 oninvalid="this.setCustomValidity('Please upload your Business Permit or DTI Certificate')">
          @error('business_permit')
            <div style="color:var(--danger);font-size:.82rem;margin-top:.35rem"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
          @enderror
        </div>

        <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:var(--radius-md);padding:.875rem;margin-bottom:1.25rem;font-size:.8rem;color:#15803D">
          <i class="bi bi-info-circle-fill me-1"></i>
          Your document will be reviewed manually by our team. You'll receive an SMS notification once a decision is made. The upgrade process typically takes 1-3 business days.
        </div>

        <button type="submit" class="btn btn-primary w-100" style="padding:.75rem;font-size:.95rem;font-weight:600" id="upgradeSubmitBtn">
          <i class="bi bi-send-fill me-2"></i>Submit Upgrade Request
        </button>
      </form>
    </div>
  </div>
  @endif

</div>
@endif

<script>
function upgradePreviewFile(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { alert('File must not exceed 5MB.'); input.value = ''; return; }
  const zone = document.getElementById('upgradeDropZone');
  const preview = document.getElementById('upgradeFilePreview');
  const img = document.getElementById('upgradePreviewImg');
  const name = document.getElementById('upgradeFileName');
  const icon = document.getElementById('upgradeUploadIcon');
  const text = document.getElementById('upgradeUploadText');
  preview.style.display = 'block';
  name.textContent = file.name;
  icon.style.display = 'none';
  text.style.display = 'none';
  zone.style.borderColor = 'var(--primary)';
  zone.style.background  = 'var(--primary-bg)';
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
    reader.readAsDataURL(file);
  } else {
    img.style.display = 'none';
    name.textContent = 'PDF: ' + file.name;
  }
}
function upgradeHandleDrop(e) {
  e.preventDefault();
  const input = document.getElementById('business_permit');
  const dt = new DataTransfer();
  dt.items.add(e.dataTransfer.files[0]);
  input.files = dt.files;
  input.dispatchEvent(new Event('change'));
}
document.getElementById('upgradeForm')?.addEventListener('submit', function(e) {
  const file = document.getElementById('business_permit');
  if (!file.files || !file.files.length) {
    e.preventDefault();
    alert('Please upload your Business Permit or DTI Certificate before submitting.');
    return;
  }
  const btn = document.getElementById('upgradeSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
});
</script>

@endsection




