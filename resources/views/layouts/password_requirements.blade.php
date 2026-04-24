{{-- Password Requirements Indicator — include below any password field --}}
{{-- Usage: @include('layouts.password_requirements', ['inputId' => 'fieldId']) --}}
<div id="pwdReq_{{ $inputId }}" class="mt-2" style="display:none">
  <div class="small fw-semibold mb-1" style="color:#1d4ed8">
    <i class="bi bi-shield-check me-1"></i>Password Requirements:
  </div>
  <ul class="list-unstyled mb-0">
    <li id="req_len_{{ $inputId }}"     class="d-flex align-items-center gap-2 mb-1 small">
      <span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span>
      <span style="color:#1d4ed8">Minimum of 8 characters</span>
    </li>
    <li id="req_upper_{{ $inputId }}"   class="d-flex align-items-center gap-2 mb-1 small">
      <span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span>
      <span style="color:#1d4ed8">Must contain at least 1 Uppercase letter</span>
    </li>
    <li id="req_num_{{ $inputId }}"     class="d-flex align-items-center gap-2 mb-1 small">
      <span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span>
      <span style="color:#1d4ed8">Must contain at least 1 Number</span>
    </li>
    <li id="req_special_{{ $inputId }}" class="d-flex align-items-center gap-2 mb-1 small">
      <span class="req-icon" style="color:#1d4ed8;font-size:.85rem">○</span>
      <span style="color:#1d4ed8">Must contain at least 1 Special Character (!@#$%^&*)</span>
    </li>
  </ul>
</div>
@once
@push('scripts')
<script>
function checkPwdRequirements(val, inputId) {
  const wrap = document.getElementById('pwdReq_' + inputId);
  if (!wrap) return false;
  wrap.style.display = val.length > 0 ? 'block' : 'none';

  const checks = {
    len:     val.length >= 8,
    upper:   /[A-Z]/.test(val),
    num:     /[0-9]/.test(val),
    special: /[!@#$%^&*()\-_=+\[\]{};':"\\|,.<>\/?'~]/.test(val),
  };

  Object.entries(checks).forEach(([key, passed]) => {
    const li   = document.getElementById('req_' + key + '_' + inputId);
    if (!li) return;
    const icon = li.querySelector('.req-icon');
    const text = li.querySelector('span:last-child');
    if (passed) {
      icon.textContent           = '✓';
      icon.style.color           = '#16a34a';
      text.style.color           = '#16a34a';
      text.style.textDecoration  = 'line-through';
      text.style.opacity         = '.7';
    } else {
      icon.textContent           = '○';
      icon.style.color           = '#1d4ed8';
      text.style.color           = '#1d4ed8';
      text.style.textDecoration  = 'none';
      text.style.opacity         = '1';
    }
  });
  return Object.values(checks).every(Boolean);
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type=password][data-pwdreq]').forEach(input => {
    input.addEventListener('input', () => checkPwdRequirements(input.value, input.dataset.pwdreq));
    input.addEventListener('focus', () => {
      const wrap = document.getElementById('pwdReq_' + input.dataset.pwdreq);
      if (wrap && input.value.length === 0) wrap.style.display = 'block';
    });
  });
});
</script>
@endpush
@endonce
