{{--
  Password Strength Indicator — include after any password input
  Usage: @include('layouts.password_strength', ['inputId' => 'myPasswordField'])
--}}
<div id="strength_{{ $inputId }}" class="mt-1" style="display:none">
  <div style="height:5px;border-radius:99px;background:#e9ecef;overflow:hidden;margin-bottom:4px">
    <div id="strengthBar_{{ $inputId }}" style="height:100%;width:0;border-radius:99px;transition:all .3s"></div>
  </div>
  <div id="strengthText_{{ $inputId }}" class="small fw-semibold"></div>
</div>

@once
@push('scripts')
<script>
function checkPasswordStrength(val, inputId) {
  const wrap = document.getElementById('strength_' + inputId);
  const bar  = document.getElementById('strengthBar_' + inputId);
  const text = document.getElementById('strengthText_' + inputId);
  if (!wrap) return;

  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';

  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { min:0, max:1, label:'🔴 Weak',      color:'#ef4444', width:'20%' },
    { min:2, max:2, label:'🟠 Fair',       color:'#f97316', width:'40%' },
    { min:3, max:3, label:'🟡 Good',       color:'#eab308', width:'65%' },
    { min:4, max:4, label:'🟢 Strong',     color:'#22c55e', width:'85%' },
    { min:5, max:5, label:'💪 Very Strong',color:'#16a34a', width:'100%'},
  ];

  const level = levels.find(l => score >= l.min && score <= l.max) || levels[0];
  bar.style.width       = level.width;
  bar.style.background  = level.color;
  text.textContent      = level.label;
  text.style.color      = level.color;
}

// Auto-attach to all password inputs that have data-strength="true"
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type=password][data-strength]').forEach(input => {
    input.addEventListener('input', () => checkPasswordStrength(input.value, input.dataset.strength));
  });
});
</script>
@endpush
@endonce
