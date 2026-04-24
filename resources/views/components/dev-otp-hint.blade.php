@php
  $devOtp  = session('dev_otp');
  $devMode = false;
  try {
    $devMode = !empty(\Illuminate\Support\Facades\DB::table('platform_settings')->value('dev_mode'));
  } catch (\Throwable $e) {}

  if ($devMode && !empty($devOtp)) {
    $siteName = \Illuminate\Support\Facades\DB::table('site_settings')->value('site_title')
             ?? \Illuminate\Support\Facades\DB::table('platform_settings')->value('platform_name')
             ?? 'Cake Shop';
    $smsText  = "{$siteName}: Your OTP verification code is {$devOtp['otp']}. Valid for 10 minutes. Do not share this code.";
  }
@endphp
@if($devMode && !empty($devOtp))
<div id="devOtpHint" style="margin-top:1rem;border-radius:14px;overflow:hidden;border:1.5px solid #fde68a;box-shadow:0 4px 18px rgba(245,158,11,.12)">

  {{-- Header bar --}}
  <div style="background:linear-gradient(90deg,#78350f,#92400e);padding:.55rem 1rem;display:flex;align-items:center;justify-content:space-between">
    <span style="font-size:.7rem;font-weight:700;color:#fef3c7;letter-spacing:.08em;text-transform:uppercase;display:flex;align-items:center;gap:.4rem">
      <i class="bi bi-bug-fill" style="font-size:.75rem"></i>
      Developer Mode &mdash; SMS Preview
    </span>
    <span style="font-size:.65rem;color:rgba(254,243,199,.65)">{{ $devOtp['time'] }}</span>
  </div>

  <div style="background:linear-gradient(135deg,#fffbeb,#fefce8);padding:.85rem 1rem">

    {{-- Sub-label --}}
    <div style="font-size:.72rem;color:#b45309;margin-bottom:.65rem;font-style:italic">
      This is what the customer receives when SMS is working:
    </div>

    {{-- SMS bubble mockup --}}
    <div style="background:#fff;border-radius:12px;padding:.75rem .9rem;border:1px solid #fde68a;margin-bottom:.85rem;position:relative">
      <div style="font-size:.64rem;font-weight:700;color:#92400e;letter-spacing:.05em;margin-bottom:.3rem;text-transform:uppercase">
        <i class="bi bi-phone-fill" style="font-size:.7rem"></i> SMS from UniSMS
      </div>
      <div style="font-size:.8rem;color:#1c1917;line-height:1.6;font-family:monospace;word-break:break-word">{{ $smsText }}</div>
      <div style="font-size:.62rem;color:#a8a29e;text-align:right;margin-top:.35rem">{{ $devOtp['time'] }} &nbsp;·&nbsp; Delivered</div>
    </div>

    {{-- OTP copy section --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:10px;padding:.6rem .9rem">
      <div>
        <div style="font-size:.62rem;color:#c2410c;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">OTP Code</div>
        <div id="devOtpCode" style="font-size:1.7rem;font-weight:800;letter-spacing:.22em;color:#ea580c;font-family:monospace;line-height:1;cursor:pointer" onclick="devCopyOtp()" title="Click to copy">{{ $devOtp['otp'] }}</div>
      </div>
      <button onclick="devCopyOtp()" style="flex-shrink:0;background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border:none;border-radius:8px;padding:.45rem .9rem;font-size:.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.35rem;transition:opacity .15s" onmouseenter="this.style.opacity='.85'" onmouseleave="this.style.opacity='1'">
        <i class="bi bi-copy" id="devCopyIcon"></i>
        <span id="devCopyLabel">Copy OTP</span>
      </button>
    </div>

    {{-- Recipient info --}}
    <div style="display:flex;flex-wrap:wrap;gap:.3rem 1.2rem;margin-top:.65rem;font-size:.72rem;color:#92400e">
      <span><i class="bi bi-telephone-fill" style="font-size:.65rem;margin-right:.25rem"></i>+{{ $devOtp['phone'] }}</span>
      @if(!empty($devOtp['name']))
      <span><i class="bi bi-person-fill" style="font-size:.65rem;margin-right:.25rem"></i>{{ $devOtp['name'] }}</span>
      @endif
    </div>

  </div>
</div>

<script>
function devCopyOtp() {
  var code = '{{ $devOtp['otp'] }}';
  var label = document.getElementById('devCopyLabel');
  var icon  = document.getElementById('devCopyIcon');
  navigator.clipboard.writeText(code).then(function() {
    label.textContent = 'Copied!';
    icon.className = 'bi bi-check-lg';
    setTimeout(function() {
      label.textContent = 'Copy OTP';
      icon.className = 'bi bi-copy';
    }, 2000);
  }).catch(function() {
    // Fallback for older browsers
    var el = document.getElementById('devOtpCode');
    var range = document.createRange();
    range.selectNodeContents(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    document.execCommand('copy');
    window.getSelection().removeAllRanges();
    label.textContent = 'Copied!';
    icon.className = 'bi bi-check-lg';
    setTimeout(function() {
      label.textContent = 'Copy OTP';
      icon.className = 'bi bi-copy';
    }, 2000);
  });
}
</script>
@endif
