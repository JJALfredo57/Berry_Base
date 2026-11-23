<div class="apd-modal" id="adminPayoutDetailDialog" role="dialog" aria-modal="true" aria-labelledby="adminPayoutDetailTitle" hidden>
  <div class="apd-dialog">
    <div class="apd-header"><strong id="adminPayoutDetailTitle">Payout details</strong><button type="button" class="btn-close" data-apd-close aria-label="Close payout details"></button></div>
    <div class="apd-body" id="adminPayoutDetailBody"></div>
    <div class="apd-footer"><button type="button" class="btn btn-outline-secondary" data-apd-close>Close</button></div>
  </div>
</div>
<style>
.apd-list{display:grid;gap:.65rem;padding:1rem}.apd-item{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-width:0;padding:1rem;border:1px solid #e5e7eb;border-radius:13px;background:#fff}.apd-item.is-action-required{border-left:3px solid #f97316;background:#fffaf5}.apd-main{min-width:0;overflow-wrap:anywhere}.apd-main strong,.apd-main small{display:block}.apd-main small{color:#64748b}.apd-side{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;justify-content:flex-end}.apd-amount{font-weight:800;white-space:nowrap}.apd-modal{position:fixed;inset:0;z-index:1090;display:grid;place-items:center;padding:12px;background:rgba(15,23,42,.62)}.apd-modal[hidden]{display:none}.apd-dialog{width:min(620px,100%);max-height:calc(100dvh - 24px);display:flex;flex-direction:column;min-width:0;overflow:hidden;border-radius:17px;background:#fff;box-shadow:0 25px 70px rgba(0,0,0,.28)}.apd-header,.apd-footer{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem;border-bottom:1px solid #e5e7eb}.apd-footer{justify-content:flex-end;border-top:1px solid #e5e7eb;border-bottom:0}.apd-body{overflow:auto;min-width:0;padding:1rem;overflow-wrap:anywhere}.apd-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.apd-field{min-width:0;padding:.75rem;border-radius:10px;background:#f8fafc;overflow-wrap:anywhere}.apd-field small{display:block;margin-bottom:.2rem;color:#64748b;font-size:.72rem;font-weight:800;text-transform:uppercase}.apd-actions{display:grid;gap:.8rem;margin-top:1rem}.apd-actions form{display:grid;gap:.5rem;min-width:0;padding:1rem;border:1px solid #e5e7eb;border-radius:12px}.apd-actions .form-control{min-width:0;width:100%}.apd-actions .btn{justify-self:start;min-height:42px}.apd-actions .payout-mark-paid-form{display:grid!important;min-width:0!important}.apd-actions .payout-receipt-scan{min-width:0}.apd-help{font-size:.8rem;color:#64748b}.apd-item .btn{min-height:40px}@media(max-width:575px){.apd-item{align-items:stretch;flex-direction:column}.apd-side{justify-content:space-between}.apd-details{grid-template-columns:1fr}.apd-actions .btn{width:100%}}
.apd-body{min-height:0;overscroll-behavior:contain}
.apd-receipt-preview{min-width:0;margin-bottom:.5rem}.apd-receipt-preview summary{width:max-content;max-width:100%;color:var(--primary);font-weight:700;cursor:pointer;overflow-wrap:anywhere}.apd-receipt-preview summary:focus-visible{outline:2px solid var(--primary);outline-offset:3px}.apd-receipt-preview img{display:block;width:auto;max-width:100%;max-height:320px;margin-top:.65rem;border-radius:8px;object-fit:contain;background:#fff}
</style>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('adminPayoutDetailDialog');
  if (!modal) return;
  // Keep the fixed dialog outside the animated admin-page transform.
  document.body.appendChild(modal);
  const body = document.getElementById('adminPayoutDetailBody');
  const title = document.getElementById('adminPayoutDetailTitle');
  let previousFocus = null;
  let previousBodyOverflow = '';
  const close = () => { modal.hidden = true; body.replaceChildren(); document.body.style.overflow = previousBodyOverflow; previousFocus?.focus(); };
  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-payout-detail]');
    if (opener) {
      const template = document.getElementById(opener.dataset.payoutDetail);
      if (!template) return;
      previousFocus = opener;
      title.textContent = opener.dataset.payoutTitle || 'Payout details';
      body.replaceChildren(template.content.cloneNode(true));
      modal.hidden = false;
      previousBodyOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      modal.querySelector('[data-apd-close]').focus();
      return;
    }
    if (!modal.hidden && (event.target === modal || event.target.closest('[data-apd-close]'))) close();
  });
  document.addEventListener('keydown', event => {
    if (modal.hidden) return;
    if (document.getElementById('payoutReceiptViewerModal')?.classList.contains('show')) return;
    if (event.key === 'Escape') close();
    if (event.key === 'Tab') {
      const focusable = [...modal.querySelectorAll('button:not([disabled]),a[href],input:not([disabled])')].filter(el => el.getClientRects().length);
      if (!focusable.length) return;
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
});
</script>
@endpush
