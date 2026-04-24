@extends('layouts.app')
@section('content')
<div>

<div style="margin-bottom:2rem">
  <h1 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--gray-900);margin:0 0 .25rem">Seller Management</h1>
  <p style="font-size:.875rem;color:var(--gray-500);margin:0">Review and manage seller applications</p>
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
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
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

{{-- Document Viewer Modal --}}
<div id="docViewer" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;flex-direction:column"
     onclick="if(event.target===this)closeDocViewer()">
  <div style="position:absolute;top:1rem;right:1rem;display:flex;gap:.75rem">
    <div id="docTitle" style="color:#fff;font-size:.9rem;font-weight:600;align-self:center"></div>
    <button onclick="closeDocViewer()" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:1rem">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <img id="docImg" src="" alt="" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:var(--radius-md)">
</div>

<script>
function toggleRejectForm(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function openDocViewer(src, title) {
  document.getElementById('docImg').src = src;
  document.getElementById('docTitle').textContent = title;
  document.getElementById('docViewer').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeDocViewer() {
  document.getElementById('docViewer').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key==='Escape') closeDocViewer(); });

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
