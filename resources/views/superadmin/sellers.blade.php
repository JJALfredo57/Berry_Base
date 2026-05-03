@extends('layouts.app')
@section('content')
<div>

<div style="margin-bottom:1.5rem;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem">
  <div>
    <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Seller Management</h1>
    <p style="font-size:.875rem;color:var(--gray-500);margin:0">Review seller applications and manage the platform commission policy.</p>
  </div>
  @php
    $commissionTotal = (int)($commissionStats->total ?? 0);
    $commissionEnabled = (int)($commissionStats->enabled ?? 0);
    $allCommissionOn = $commissionTotal > 0 && $commissionEnabled === $commissionTotal;
    $noCommissionOn = $commissionEnabled === 0;
    $commissionStateLabel = $allCommissionOn ? 'Enabled for all' : ($noCommissionOn ? 'Disabled for all' : 'Partially enabled');
    $commissionNextAction = $allCommissionOn ? 'disable' : 'enable';
  @endphp
  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
    <span style="font-size:.78rem;font-weight:700;color:{{ $allCommissionOn ? '#166534' : ($noCommissionOn ? '#9a3412' : '#1d4ed8') }};background:{{ $allCommissionOn ? '#f0fdf4' : ($noCommissionOn ? '#fff7ed' : '#eff6ff') }};border:1px solid {{ $allCommissionOn ? '#bbf7d0' : ($noCommissionOn ? '#fed7aa' : '#bfdbfe') }};border-radius:99px;padding:.35rem .75rem">
      Commission Status: {{ $commissionStateLabel }}
    </span>
    <form action="{{ route('superadmin.sellers.commission_bulk') }}" method="POST" class="d-inline">
      @csrf
      <input type="hidden" name="action" value="{{ $commissionNextAction }}">
      <button type="submit"
              style="background:{{ $allCommissionOn ? '#fff7ed' : '#f0fdf4' }};color:{{ $allCommissionOn ? '#9a3412' : '#166534' }};border:1.5px solid {{ $allCommissionOn ? '#fed7aa' : '#bbf7d0' }};border-radius:var(--radius-md);padding:.45rem .95rem;font-size:.8rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem"
              data-cs-confirm="{{ $allCommissionOn ? 'Disable commission for all approved and suspended sellers?' : 'Enable commission for all approved and suspended sellers?' }}"
              data-cs-title="{{ $allCommissionOn ? 'Disable Commission for All Sellers' : 'Enable Commission for All Sellers' }}"
              data-cs-icon="{{ $allCommissionOn ? 'bi-toggle-off' : 'bi-toggle-on' }}"
              data-cs-icon-bg="{{ $allCommissionOn ? '#fff7ed' : '#f0fdf4' }}"
              data-cs-icon-color="{{ $allCommissionOn ? '#ea580c' : '#16a34a' }}"
              data-cs-ok="{{ $allCommissionOn ? 'Turn Off' : 'Turn On' }}"
              data-cs-ok-color="{{ $allCommissionOn ? '#ea580c' : '#16a34a' }}">
        <i class="bi {{ $allCommissionOn ? 'bi-toggle-off' : 'bi-toggle-on' }}"></i>
        {{ $allCommissionOn ? 'Disable for All Sellers' : 'Enable for All Sellers' }}
      </button>
    </form>
  </div>
</div>

<div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);padding:1.1rem 1.25rem;margin-bottom:1.25rem;box-shadow:0 10px 28px rgba(15,23,42,.045)">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div style="max-width:620px">
      <div style="font-size:.95rem;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:.45rem">
        <i class="bi bi-percent" style="color:var(--primary)"></i> Seller Commission Policy
      </div>
      <div style="font-size:.8rem;color:var(--gray-500);margin-top:.2rem">
        These default rates are assigned by seller tier during application and approval. Existing seller-specific overrides stay hidden from this screen.
      </div>
    </div>
    <form action="{{ route('superadmin.sellers.commission_rate_bulk') }}" method="POST" style="display:flex;align-items:end;gap:.6rem;flex-wrap:wrap">
      @csrf
      <div>
        <label style="display:block;font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem">Basic Seller</label>
        <div class="input-group">
          <input type="number" step="0.01" min="0" max="100" name="commission_rate_basic" class="form-control" value="{{ number_format((float)($platform->commission_rate_basic ?? 0), 2, '.', '') }}" style="min-width:120px" required>
          <span class="input-group-text">%</span>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:.72rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem">Verified Seller</label>
        <div class="input-group">
          <input type="number" step="0.01" min="0" max="100" name="commission_rate_verified" class="form-control" value="{{ number_format((float)($platform->commission_rate_verified ?? 0), 2, '.', '') }}" style="min-width:120px" required>
          <span class="input-group-text">%</span>
        </div>
      </div>
      <button type="submit"
              class="btn btn-primary"
              style="padding:.6rem 1rem;font-size:.82rem;font-weight:700"
              data-cs-confirm="Save these default commission rates for seller applications and approvals?" data-cs-title="Update Commission Policy" data-cs-icon="bi-percent" data-cs-icon-bg="#eff6ff" data-cs-icon-color="#2563eb" data-cs-ok="Save Policy" data-cs-ok-color="#2563eb">
        <i class="bi bi-save me-1"></i> Save Policy
      </button>
    </form>
  </div>
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

<div style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);padding:1rem 1.25rem;margin-bottom:1.25rem">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <div style="font-size:.95rem;font-weight:700;color:var(--gray-900)">Smart Seller Filter</div>
      <div style="font-size:.8rem;color:var(--gray-500)">Search by shop, owner, email, phone, city, or application status</div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
      <input type="text" id="sellerMgmtSearch" class="form-control" placeholder="Search sellers..." style="flex:1;min-width:0;max-width:280px" oninput="filterSellerManagement()">
      <select id="sellerMgmtStatus" class="form-select" style="flex:1;min-width:0;max-width:180px" onchange="filterSellerManagement()">
        <option value="">All status</option>
        <option value="pending">Pending Review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="suspended">Suspended</option>
      </select>
    </div>
  </div>
  <div id="sellerMgmtSummary" style="font-size:.78rem;color:var(--gray-500);margin-top:.6rem">Showing {{ $applications->count() }} seller applications</div>
</div>

@foreach($applications as $app)
@php
  $docs    = $documents[$app->id] ?? [];
  $dtiDoc  = collect($docs)->firstWhere('document_type','dti')
          ?? collect($docs)->firstWhere('document_type','business_permit');
  $idDoc   = collect($docs)->firstWhere('document_type','valid_id');
  $statusColors = [
    'pending'   => ['bg'=>'#FFF3E0','color'=>'#E65100','label'=>'Pending Review'],
    'approved'  => ['bg'=>'#E8F5E9','color'=>'#2E7D32','label'=>'Approved'],
    'rejected'  => ['bg'=>'#FFEBEE','color'=>'#C62828','label'=>'Rejected'],
    'suspended' => ['bg'=>'#F3E5F5','color'=>'#6A1B9A','label'=>'Suspended'],
  ];
  $sc = $statusColors[$app->status] ?? $statusColors['pending'];
@endphp

<div class="seller-mgmt-item"
     data-search="{{ strtolower(trim(($app->shop_name ?? '') . ' ' . ($app->fullname ?? '') . ' ' . ($app->email ?? '') . ' ' . ($app->phone ?? '') . ' ' . ($app->city ?? '') . ' ' . ($app->status ?? ''))) }}"
     data-status="{{ strtolower($app->status ?? 'pending') }}"
     style="background:#fff;border-radius:var(--radius-lg);border:1.5px solid {{ $app->status==='pending' ? '#FED7AA' : 'var(--gray-100)' }};margin-bottom:1.25rem;overflow:hidden">

  {{-- Shop Header --}}
  <div style="padding:1.25rem 1.5rem;border-bottom:1.5px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.875rem;background:{{ $app->status==='pending' ? '#FFFBF5' : '#fff' }}">
    <div style="display:flex;align-items:center;gap:1rem">
      @if($app->shop_logo)
        <img src="{{ $app->shop_logo }}" style="width:52px;height:52px;border-radius:14px;object-fit:cover;border:2px solid var(--gray-100)" alt="">
      @else
        <div style="width:52px;height:52px;border-radius:14px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:700;color:var(--primary)">
          {{ strtoupper(substr($app->shop_name,0,1)) }}
        </div>
      @endif
      <div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
          <span style="font-size:1rem;font-weight:700;color:var(--gray-900)">{{ $app->shop_name }}</span>
          @if($app->tier === 'verified')
            <span style="background:#FFF3E0;color:#E65100;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:99px;display:inline-flex;align-items:center;gap:.25rem">
              <i class="bi bi-patch-check-fill"></i> Verified Tier
            </span>
          @else
            <span style="background:var(--gray-100);color:var(--gray-600);font-size:.7rem;font-weight:600;padding:.2rem .6rem;border-radius:99px">Basic Tier</span>
          @endif
          <span style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:99px">{{ $sc['label'] }}</span>
        </div>
        <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">
          {{ $app->fullname }} &bull; {{ $app->email }} &bull; {{ $app->phone }} &bull; {{ $app->city }}
        </div>
        <div style="font-size:.72rem;color:var(--gray-400);margin-top:.1rem">Applied {{ \Carbon\Carbon::parse($app->created_at)->diffForHumans() }}</div>
      </div>
    </div>

    {{-- Action Buttons --}}
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
      {{-- Upgrade Request Badge --}}
      @if(($app->upgrade_request_status ?? null) === 'pending')
        <span style="background:#DBEAFE;color:#1D4ED8;font-size:.72rem;font-weight:700;padding:.25rem .75rem;border-radius:99px;display:inline-flex;align-items:center;gap:.3rem">
          <i class="bi bi-arrow-up-circle-fill"></i> Upgrade Requested
        </span>
      @endif
      @if($app->status === 'pending')
        <form action="{{ route('superadmin.sellers.approve',$app->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit"
                  style="background:var(--success-bg);color:var(--success);border:1.5px solid var(--success);border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.83rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .15s"
                  data-cs-confirm="Approve {{ addslashes($app->shop_name) }}?" data-cs-title="Approve Shop" data-cs-icon="bi-check-circle" data-cs-icon-bg="#ecfdf5" data-cs-icon-color="#059669" data-cs-ok="Approve" data-cs-ok-color="#059669">
            <i class="bi bi-check-lg"></i> Approve
          </button>
        </form>
        <button type="button"
                style="background:var(--danger-bg);color:var(--danger);border:1.5px solid var(--danger);border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.83rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem"
                onclick="toggleRejectForm('reject-{{ $app->id }}')">
          <i class="bi bi-x-lg"></i> Reject
        </button>
      @elseif($app->status === 'approved')
        <form action="{{ route('superadmin.sellers.suspend',$app->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit"
                  style="background:#F3E5F5;color:#6A1B9A;border:1.5px solid #CE93D8;border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.83rem;font-weight:600;cursor:pointer"
                  data-cs-confirm="Suspend this shop?" data-cs-title="Suspend Shop" data-cs-icon="bi-pause-circle" data-cs-icon-bg="#F3E5F5" data-cs-icon-color="#6A1B9A" data-cs-ok="Suspend" data-cs-ok-color="#6A1B9A">
            <i class="bi bi-pause-circle"></i> Suspend
          </button>
        </form>
        <a href="{{ route('platform.shop',$app->shop_slug) }}" target="_blank"
           style="background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.83rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
          <i class="bi bi-box-arrow-up-right"></i> View Shop
        </a>
      @elseif($app->status === 'suspended')
        <form action="{{ route('superadmin.sellers.suspend',$app->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit"
                  style="background:var(--success-bg);color:var(--success);border:1.5px solid var(--success);border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.83rem;font-weight:600;cursor:pointer">
            <i class="bi bi-play-circle"></i> Reactivate
          </button>
        </form>
      @endif

      {{-- Commission status --}}
      @if(in_array($app->status, ['approved', 'suspended']))
        @php $commOn = (bool)($app->commission_enabled ?? 1); @endphp
        <span style="background:{{ $commOn ? '#f0fdf4' : '#fff7ed' }};color:{{ $commOn ? '#166534' : '#9a3412' }};border:1.5px solid {{ $commOn ? '#bbf7d0' : '#fed7aa' }};border-radius:var(--radius-md);padding:.45rem .85rem;font-size:.8rem;font-weight:700;display:inline-flex;align-items:center;gap:.35rem">
          <i class="bi {{ $commOn ? 'bi-check2-circle' : 'bi-slash-circle' }}"></i>
          {{ $commOn ? 'Commission: '.number_format((float)($app->commission_rate ?? 0), 2).'%' : 'Commission Disabled' }}
        </span>
      @endif
    </div>
  </div>

  {{-- Documents --}}
  <div style="padding:1.25rem 1.5rem">
    <div class="row g-3">

      {{-- Shop Address --}}
      <div class="col-md-4">
        <div style="font-size:.75rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Business Info</div>
        <div style="font-size:.83rem;color:var(--gray-700);line-height:1.6">
          <div><i class="bi bi-geo-alt" style="color:var(--primary);margin-right:.3rem"></i>{{ $app->address }}</div>
          <div style="margin-top:.25rem"><i class="bi bi-wallet2" style="color:var(--primary);margin-right:.3rem"></i>GCash: {{ $app->gcash_number }}</div>
          @if($app->description)
            <div style="margin-top:.4rem;color:var(--gray-500)">{{ Str::limit($app->description, 100) }}</div>
          @endif
        </div>
      </div>

      {{-- Valid ID --}}
      @if($idDoc)
      <div class="col-md-4">
        <div style="font-size:.75rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Government ID</div>
        @if(str_ends_with(strtolower($idDoc->file_path), '.pdf'))
          <a href="{{ $idDoc->file_path }}" target="_blank"
             style="display:inline-flex;align-items:center;gap:.4rem;background:var(--gray-100);color:var(--gray-700);padding:.5rem .875rem;border-radius:var(--radius-md);font-size:.8rem;font-weight:600">
            <i class="bi bi-file-pdf" style="color:#C62828"></i> View PDF
          </a>
        @else
          <img src="{{ $idDoc->file_path }}" alt="Valid ID"
               style="max-height:100px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--gray-200)"
               onclick="openDocViewer('{{ $idDoc->file_path }}','Government ID')">
        @endif
      </div>
      @endif

      {{-- DTI Certificate --}}
      @if($dtiDoc)
      <div class="col-md-4">
        <div style="font-size:.75rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">
          DTI / Business Permit
          {{-- OCR Status Badge --}}
          @if($dtiDoc->ocr_status)
            @php
              $ocrColors = [
                'likely_valid'   => ['bg'=>'#E8F5E9','color'=>'#2E7D32','label'=>'OCR: Likely Valid'],
                'needs_review'   => ['bg'=>'#FFF3E0','color'=>'#E65100','label'=>'OCR: Needs Review'],
                'likely_invalid' => ['bg'=>'#FFEBEE','color'=>'#C62828','label'=>'OCR: Likely Invalid'],
              ];
              $oc = $ocrColors[$dtiDoc->ocr_status] ?? $ocrColors['needs_review'];
            @endphp
            <span style="background:{{ $oc['bg'] }};color:{{ $oc['color'] }};font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;margin-left:.4rem">{{ $oc['label'] }}</span>
          @endif
        </div>

        @if($dtiDoc->file_path)
          @if(str_ends_with(strtolower($dtiDoc->file_path), '.pdf'))
            <a href="{{ $dtiDoc->file_path }}" target="_blank"
               style="display:inline-flex;align-items:center;gap:.4rem;background:var(--gray-100);color:var(--gray-700);padding:.5rem .875rem;border-radius:var(--radius-md);font-size:.8rem;font-weight:600">
              <i class="bi bi-file-pdf" style="color:#C62828"></i> View PDF
            </a>
          @else
            <img src="{{ $dtiDoc->file_path }}" alt="DTI"
                 style="max-height:100px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--gray-200)"
                 onclick="openDocViewer('{{ $dtiDoc->file_path }}','DTI Certificate')">
          @endif
        @endif

        {{-- OCR Details --}}
        @if($dtiDoc->ocr_business_name || $dtiDoc->ocr_expiry_date)
        <div style="margin-top:.6rem;font-size:.75rem;line-height:1.6;color:var(--gray-600)">
          @if($dtiDoc->ocr_business_name)
            <div><span style="color:var(--gray-400)">Business Name:</span> {{ $dtiDoc->ocr_business_name }}
              @if($dtiDoc->ocr_name_match)
                <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
              @else
                <i class="bi bi-exclamation-circle-fill" style="color:var(--warning)"></i>
              @endif
            </div>
          @endif
          @if($dtiDoc->ocr_expiry_date)
            <div><span style="color:var(--gray-400)">Expiry:</span> {{ $dtiDoc->ocr_expiry_date }}
              @if($dtiDoc->ocr_is_expired)
                <span style="color:var(--danger);font-weight:600">EXPIRED</span>
              @else
                <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
              @endif
            </div>
          @endif
        </div>
        @endif

        {{-- DTI Verify Link --}}
        @if($app->status === 'pending')
        <a href="https://bnrs.dti.gov.ph" target="_blank"
           style="display:inline-flex;align-items:center;gap:.35rem;font-size:.75rem;color:var(--info,#1565C0);font-weight:600;margin-top:.5rem">
          <i class="bi bi-box-arrow-up-right" style="font-size:.68rem"></i> Verify on DTI BNRS
        </a>
        @endif
      </div>
      @endif

    </div>

    {{-- Upgrade Request Section --}}
    @php
      $upgradeDoc = collect($docs)->filter(fn($d) => $d->document_type === 'upgrade_permit')->last();
    @endphp
    @if(($app->upgrade_request_status ?? null) === 'pending')
    <div style="margin-top:1.25rem;padding:1.25rem;background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:var(--radius-md)">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.875rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:.6rem">
          <i class="bi bi-arrow-up-circle-fill" style="font-size:1.2rem;color:#2563EB"></i>
          <div>
            <div style="font-size:.9rem;font-weight:700;color:var(--gray-900)">Upgrade Request — Basic → Verified</div>
            <div style="font-size:.76rem;color:#3B82F6">Submitted {{ $app->upgrade_requested_at ? \Carbon\Carbon::parse($app->upgrade_requested_at)->diffForHumans() : '' }}</div>
          </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <form action="{{ route('superadmin.sellers.approve_upgrade', $app->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit"
                    style="background:#ECFDF5;color:#065F46;border:1.5px solid #6EE7B7;border-radius:var(--radius-md);padding:.45rem 1.1rem;font-size:.82rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem"
                    data-cs-confirm="Upgrade {{ addslashes($app->shop_name) }} to Verified Seller?" data-cs-title="Approve Upgrade" data-cs-icon="bi-patch-check-fill" data-cs-icon-bg="#ECFDF5" data-cs-icon-color="#059669" data-cs-ok="Approve Upgrade" data-cs-ok-color="#059669">
              <i class="bi bi-patch-check-fill"></i> Approve Upgrade
            </button>
          </form>
          <button type="button"
                  style="background:#FFF1F2;color:#BE123C;border:1.5px solid #FDA4AF;border-radius:var(--radius-md);padding:.45rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem"
                  onclick="toggleUpgradeRejectForm('upg-reject-{{ $app->id }}')">
            <i class="bi bi-x-lg"></i> Reject Upgrade
          </button>
        </div>
      </div>

      {{-- Upgrade Document --}}
      @if($upgradeDoc)
      <div style="margin-bottom:1rem">
        <div style="font-size:.75rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem">Submitted Document</div>
        @if(str_ends_with(strtolower($upgradeDoc->file_path), '.pdf'))
          <a href="{{ $upgradeDoc->file_path }}" target="_blank"
             style="display:inline-flex;align-items:center;gap:.4rem;background:#DBEAFE;color:#1D4ED8;padding:.5rem .875rem;border-radius:var(--radius-md);font-size:.8rem;font-weight:600">
            <i class="bi bi-file-pdf" style="color:#C62828"></i> View PDF Document
          </a>
        @else
          <img src="{{ $upgradeDoc->file_path }}" alt="Business Permit"
               style="max-height:120px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid #BFDBFE"
               onclick="openDocViewer('{{ $upgradeDoc->file_path }}','Business Permit / DTI Certificate')">
        @endif
      </div>
      @endif

      {{-- Reject form (hidden) --}}
      <div id="upg-reject-{{ $app->id }}" style="display:none;margin-top:.875rem">
        <form action="{{ route('superadmin.sellers.reject_upgrade', $app->id) }}" method="POST" novalidate>
          @csrf
          <label style="font-size:.83rem;font-weight:700;color:#BE123C;display:block;margin-bottom:.4rem">Reason for Rejection <span style="color:var(--danger)">*</span></label>
          <textarea name="reason" class="form-control" rows="3" required minlength="10"
                    placeholder="Explain why the upgrade is not approved (e.g. expired document, wrong document submitted)..."
                    style="margin-bottom:.75rem;border-color:#FDA4AF"
                    oninvalid="this.setCustomValidity('Please provide a reason (min 10 characters)')"
                    oninput="this.setCustomValidity('')"></textarea>
          <div style="display:flex;gap:.5rem">
            <button type="submit" style="background:#BE123C;color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.25rem;font-size:.83rem;font-weight:600;cursor:pointer"
                    data-cs-confirm="Reject upgrade for {{ addslashes($app->shop_name) }}?" data-cs-title="Reject Upgrade" data-cs-icon="bi-x-circle" data-cs-icon-bg="#FFF1F2" data-cs-icon-color="#E11D48" data-cs-ok="Reject" data-cs-ok-color="#E11D48">
              Confirm Rejection
            </button>
            <button type="button" onclick="toggleUpgradeRejectForm('upg-reject-{{ $app->id }}')"
                    style="background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.45rem 1rem;font-size:.83rem;font-weight:600;cursor:pointer">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
    @elseif(($app->upgrade_request_status ?? null) === 'rejected' && $app->tier === 'basic')
    <div style="margin-top:.875rem;padding:.75rem 1rem;background:#FFF1F2;border-radius:var(--radius-md);border-left:3px solid #E11D48;font-size:.82rem;color:#BE123C">
      <strong>Upgrade Rejected:</strong> {{ $app->upgrade_request_note }}
    </div>
    @endif

    {{-- Reject Form --}}
    @if($app->status === 'pending')
    <div id="reject-{{ $app->id }}" style="display:none;margin-top:1.25rem;padding:1.25rem;background:var(--danger-bg);border-radius:var(--radius-md);border:1.5px solid var(--primary-light)">
      <form action="{{ route('superadmin.sellers.reject',$app->id) }}" method="POST" novalidate>
        @csrf
        <label style="font-size:.83rem;font-weight:700;color:var(--danger);display:block;margin-bottom:.5rem">
          Reason for Rejection <span style="color:var(--danger)">*</span>
        </label>
        <textarea name="reason" class="form-control" rows="3" required minlength="10"
                  placeholder="Please explain why this application is being rejected..."
                  style="margin-bottom:.75rem;border-color:var(--primary-light)"
                  oninvalid="this.setCustomValidity('Please provide a reason (min 10 characters)')"
                  oninput="this.setCustomValidity('')"></textarea>
        <div style="display:flex;gap:.5rem">
          <button type="submit" style="background:var(--danger);color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem 1.25rem;font-size:.83rem;font-weight:600;cursor:pointer"
                  data-cs-confirm="Reject this application?" data-cs-title="Reject Application" data-cs-icon="bi-x-circle" data-cs-icon-bg="#fff1f2" data-cs-icon-color="#ef4444" data-cs-ok="Reject" data-cs-ok-color="#ef4444">
            Confirm Rejection
          </button>
          <button type="button" style="background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:var(--radius-md);padding:.45rem 1rem;font-size:.83rem;font-weight:600;cursor:pointer"
                  onclick="toggleRejectForm('reject-{{ $app->id }}')">
            Cancel
          </button>
        </div>
      </form>
    </div>
    @endif

    {{-- Rejection Reason (if rejected) --}}
    @if($app->status === 'rejected' && $app->rejected_reason)
    <div style="margin-top:.875rem;padding:.875rem;background:var(--danger-bg);border-radius:var(--radius-md);border-left:3px solid var(--danger);font-size:.83rem;color:var(--danger)">
      <strong>Rejection Reason:</strong> {{ $app->rejected_reason }}
    </div>
    @endif
  </div>
</div>
@endforeach

<div id="sellerMgmtEmpty" style="display:none;background:#fff;border-radius:var(--radius-lg);border:1.5px dashed var(--gray-300);padding:2.5rem;text-align:center">
  <i class="bi bi-search" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:.75rem"></i>
  <div style="font-size:1rem;font-weight:700;color:var(--gray-900)">No seller applications found</div>
  <p style="font-size:.82rem;color:var(--gray-500);margin:.4rem 0 0">Try another keyword or status filter.</p>
</div>

{{-- Pagination --}}
<div style="margin-top:1.5rem">{{ $applications->links() }}</div>

</div>

<script>
function toggleRejectForm(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function toggleUpgradeRejectForm(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function filterSellerManagement() {
  const search = (document.getElementById('sellerMgmtSearch')?.value || '').toLowerCase().trim();
  const status = (document.getElementById('sellerMgmtStatus')?.value || '').toLowerCase();
  let visibleCount = 0;

  document.querySelectorAll('.seller-mgmt-item').forEach(el => {
    const matchesSearch = !search || (el.dataset.search || '').includes(search);
    const matchesStatus = !status || (el.dataset.status || '') === status;
    const matches = matchesSearch && matchesStatus;
    el.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });

  const summary = document.getElementById('sellerMgmtSummary');
  if (summary) summary.textContent = 'Showing ' + visibleCount + ' of ' + document.querySelectorAll('.seller-mgmt-item').length + ' seller applications';

  const empty = document.getElementById('sellerMgmtEmpty');
  if (empty) empty.style.display = visibleCount === 0 ? 'block' : 'none';
}
</script>
@endsection
