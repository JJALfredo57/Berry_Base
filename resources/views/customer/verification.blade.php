@extends('layouts.app')
@section('content')
@php
  $statusLabel = [
    'not_submitted' => 'Not Verified',
    'pending' => 'Pending Review',
    'approved' => 'Verified Customer',
    'rejected' => 'Needs Resubmission',
  ][$status] ?? ucfirst($status);
  $statusIcon = $status === 'approved' ? 'bi-patch-check-fill' : ($status === 'pending' ? 'bi-hourglass-split' : 'bi-shield-exclamation');
@endphp
<style>
.verify-hero{position:relative;overflow:hidden;border-radius:8px;background:linear-gradient(135deg,#fff7fb,#eef6ff);border:1px solid #f3d6e5}
.verify-hero:after{content:"";position:absolute;inset:auto -80px -120px auto;width:260px;height:260px;border-radius:50%;background:rgba(233,30,99,.12);filter:blur(8px)}
.verify-step{display:flex;gap:.75rem;align-items:flex-start;position:relative}
.verify-step .dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid #e5e7eb;color:#64748b;transition:.25s}
.verify-step.active .dot{background:var(--primary);color:#fff;box-shadow:0 8px 22px rgba(233,30,99,.22)}
.benefit-card{height:100%;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;background:#fff;transition:.2s transform,.2s box-shadow}
.benefit-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(15,23,42,.08)}
.benefit-card.locked{background:#f8fafc;color:#64748b}
.upload-panel{border:1px solid #e5e7eb;border-radius:8px;background:#fff}
.verify-upload-card{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:.9rem;height:100%;transition:.18s border-color,.18s box-shadow,.18s transform}
.verify-upload-card:focus-within{border-color:var(--primary);box-shadow:0 0 0 .2rem rgba(var(--primary-rgb,233,30,99),.12)}
.verify-upload-icon{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:rgba(var(--primary-rgb,233,30,99),.1);color:var(--primary);flex:0 0 auto}
.verify-upload-card .form-control{font-size:.86rem}
.verify-upload-hint{font-size:.76rem;color:#64748b}
.verify-file-input{position:absolute;inline-size:1px;block-size:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0)}
.verify-upload-status{font-size:.76rem;color:#64748b;min-height:1.1rem;transition:color .18s ease,opacity .18s ease}
.verify-member-tier{border:1px solid #e5e7eb;border-radius:8px;padding:.8rem;background:#fff}
.verify-member-tier.is-current{border-color:var(--primary);box-shadow:0 10px 24px rgba(var(--primary-rgb,233,30,99),.1)}
.verify-member-tier.is-locked{background:#f8fafc;color:#64748b}
.verify-wizard{display:flex;gap:.5rem;flex-wrap:wrap}
.verify-wizard-step{display:flex;align-items:center;gap:.45rem;border:1px solid #e5e7eb;border-radius:8px;padding:.55rem .75rem;font-size:.82rem;color:#64748b;background:#fff}
.verify-wizard-step span{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#eef2f7;color:#64748b;font-weight:700;font-size:.72rem}
.verify-wizard-step.active{border-color:var(--primary);color:var(--primary);background:rgba(var(--primary-rgb,233,30,99),.06)}
.verify-wizard-step.active span,.verify-wizard-step.done span{background:var(--primary);color:#fff}
.verify-ready-panel{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:1rem}
.verify-scanner-modal{position:fixed!important;inset:0!important;overflow:hidden!important;padding:.75rem}
.verify-scanner-modal.show{display:flex!important;align-items:center;justify-content:center}
.verify-scanner-modal .modal-dialog{width:100%;height:min(100dvh - 1.5rem,900px);max-height:calc(100dvh - 1.5rem);margin:0 auto;display:flex;align-items:stretch}
.verify-scanner-modal .modal-content{background:#08111f;color:#e5eefb;border:0;min-height:0;height:100%;max-height:100%;overflow:hidden;display:flex;flex-direction:column}
.verify-scanner-modal .modal-header,.verify-scanner-modal .modal-footer{border-color:rgba(148,163,184,.22);background:rgba(8,17,31,.96);flex:0 0 auto}
.verify-scanner-modal .modal-body{overflow:hidden;min-height:0;flex:1 1 auto;display:flex;flex-direction:column}
.verify-scanner-modal .btn-close{filter:invert(1) grayscale(100%)}
.verify-scanner-modal .verify-upload-hint{color:#9fb0c8}
.verify-scanner-modal .verify-wizard-step{background:rgba(15,23,42,.82);border-color:rgba(148,163,184,.28);color:#a8b5c8}
.verify-scanner-modal .verify-wizard-step span{background:#172235;color:#a8b5c8}
.verify-scanner-modal .verify-wizard-step.active{background:rgba(var(--primary-rgb,233,30,99),.18);border-color:rgba(var(--primary-rgb,233,30,99),.72);color:#fff}
.verify-scanner-modal .verify-wizard-step.done{border-color:rgba(34,197,94,.72);color:#d1fae5}
.verify-scanner-modal .verify-wizard-step.done span{background:#15803d;color:#fff}
.verify-scan-stage{border:1px solid rgba(148,163,184,.22);border-radius:8px;padding:1rem;background:rgba(15,23,42,.7)}
.verify-camera-frame{position:relative;aspect-ratio:16/9;background:#020617;border-radius:8px;overflow:hidden;display:grid;place-items:center;height:clamp(300px,52vh,520px);min-height:0}
.verify-camera-frame video{width:100%;height:100%;object-fit:cover;background:#020617}
.verify-camera-frame .verify-preview-image{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#020617;display:none;z-index:1}
.verify-camera-frame.has-preview video{opacity:0}
.verify-camera-frame.has-preview .verify-preview-image{display:block}
.verify-camera-frame.has-preview .verify-frame-guide,.verify-camera-frame.has-preview .verify-face-guide{display:none}
.verify-camera-frame.is-selfie video{transform:scaleX(-1)}
.verify-camera-frame:after{content:"";position:absolute;left:8%;right:8%;top:50%;height:2px;background:linear-gradient(90deg,transparent,rgba(56,189,248,.85),transparent);box-shadow:0 0 20px rgba(56,189,248,.6);animation:verifyScanLine 1.9s ease-in-out infinite;opacity:.8}
.verify-camera-frame.has-preview:after{display:none}
.verify-frame-guide{position:absolute;left:50%;top:50%;width:min(86%,680px);aspect-ratio:1.586/1;max-height:72%;transform:translate(-50%,-50%);border:2px solid rgba(255,255,255,.92);border-radius:8px;box-shadow:0 0 0 999px rgba(2,6,23,.42),0 0 26px rgba(56,189,248,.22)}
.verify-frame-guide:before{content:"ALIGN ID INSIDE";position:absolute;left:50%;top:.45rem;transform:translateX(-50%);font-size:.68rem;font-weight:800;letter-spacing:.08em;color:#fff;background:rgba(2,6,23,.62);border:1px solid rgba(255,255,255,.34);border-radius:999px;padding:.18rem .5rem;white-space:nowrap}
.verify-frame-guide:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent calc(50% - 1px),rgba(255,255,255,.62) 50%,transparent calc(50% + 1px)),linear-gradient(0deg,transparent calc(50% - 1px),rgba(255,255,255,.62) 50%,transparent calc(50% + 1px));opacity:.75;pointer-events:none}
.verify-face-guide{position:absolute;width:min(48%,260px);aspect-ratio:3/4;border:3px solid rgba(255,255,255,.96);border-radius:50%;box-shadow:0 0 0 999px rgba(2,6,23,.42),0 0 28px rgba(56,189,248,.28)}
.verify-face-guide:before{content:"ALIGN FACE";position:absolute;left:50%;top:10%;transform:translateX(-50%);font-size:.68rem;font-weight:800;letter-spacing:.08em;color:#fff;background:rgba(2,6,23,.62);border:1px solid rgba(255,255,255,.34);border-radius:999px;padding:.18rem .5rem;white-space:nowrap}
.verify-face-guide:after{content:"";position:absolute;left:50%;top:50%;width:62%;height:2px;transform:translate(-50%,-50%);background:linear-gradient(90deg,transparent,rgba(255,255,255,.82),transparent);box-shadow:0 -42px 0 -1px rgba(255,255,255,.34),0 42px 0 -1px rgba(255,255,255,.34)}
.verify-live-panel{position:absolute;left:1rem;right:1rem;bottom:1rem;z-index:3;display:flex;gap:.75rem;align-items:center;justify-content:space-between;padding:.75rem .85rem;border-radius:8px;background:rgba(2,6,23,.82);backdrop-filter:blur(10px);color:#fff;font-size:.84rem;transition:background .18s ease,transform .18s ease}
.verify-live-meter{width:120px;height:8px;background:rgba(148,163,184,.38);border-radius:999px;overflow:hidden;flex:0 0 auto}
.verify-live-meter span{display:block;height:100%;width:0;background:#ef4444;transition:width .22s ease,background .22s ease}
.verify-live-meter.is-good span{background:#22c55e}.verify-live-meter.is-warn span{background:#f59e0b}
.verify-face-help-button{position:absolute;right:.75rem;top:.75rem;z-index:5;width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.72);background:rgba(2,6,23,.7);color:#fff;display:grid;place-items:center;font-weight:800;box-shadow:0 12px 28px rgba(2,6,23,.22)}
.verify-face-precheck{position:absolute;inset:0;z-index:6;display:grid;place-items:center;padding:1rem;background:linear-gradient(180deg,rgba(2,6,23,.82),rgba(2,6,23,.68));color:#fff}
.verify-face-precheck-panel{width:min(92%,360px);border:1px solid rgba(186,230,253,.62);border-radius:8px;background:rgba(15,23,42,.82);padding:1rem;box-shadow:0 18px 48px rgba(2,6,23,.32)}
.verify-face-precheck-title{display:flex;align-items:center;gap:.55rem;font-weight:800;margin-bottom:.65rem}
.verify-face-precheck-list{display:grid;gap:.48rem;margin:0 0 .85rem;padding:0;list-style:none;font-size:.84rem}
.verify-face-precheck-list li{display:flex;gap:.48rem;align-items:flex-start}
.verify-face-precheck-list i{color:#7dd3fc;flex:0 0 auto}
.verify-camera-frame.is-face-ready .verify-face-precheck{display:none}
.verify-camera-frame.is-selfie:not(.is-face-ready) video,.verify-camera-frame.is-selfie:not(.is-face-ready) .verify-face-guide{opacity:.25}
.verify-selfie-tips{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}
.verify-selfie-tip{display:flex;gap:.45rem;align-items:flex-start;border:1px solid #dbeafe;border-radius:8px;background:#fff;padding:.55rem .65rem;color:#334155;font-size:.76rem;box-shadow:0 6px 16px rgba(15,23,42,.05)}
.verify-selfie-tip i{color:#0284c7;flex:0 0 auto}
.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{border-color:#cbd5e1;box-shadow:0 16px 38px rgba(15,23,42,.12)}
.verify-scanner-modal.is-selfie-step .verify-live-panel{background:rgba(15,23,42,.78)}
@media (max-width:575.98px){.verify-selfie-tips{grid-template-columns:1fr}.verify-selfie-tip{font-size:.7rem;padding:.45rem .55rem}}.verify-liveness-card{border:1px solid rgba(56,189,248,.35);border-radius:8px;background:rgba(14,165,233,.1);padding:.75rem;color:#e0f2fe}
.verify-face-instructions{border:1px solid rgba(125,211,252,.38);border-radius:8px;background:rgba(14,165,233,.08);padding:.65rem .75rem;color:#dbeafe}
.verify-face-instructions ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.35rem .75rem;margin:0;padding:0;list-style:none;font-size:.76rem}
.verify-face-instructions li{display:flex;gap:.35rem;align-items:flex-start}
.verify-face-instructions i{color:#7dd3fc;margin-top:.03rem}
.verify-scanner-modal.is-selfie-step .modal-content{background:#fff;color:#142033;box-shadow:0 0 0 999px rgba(255,255,255,.72)}
.verify-scanner-modal.is-selfie-step .modal-header,.verify-scanner-modal.is-selfie-step .modal-footer{background:#fff;border-color:#dbe7f3;color:#142033}
.verify-scanner-modal.is-selfie-step .btn-close{filter:none}
.verify-scanner-modal.is-selfie-step .modal-body{padding-top:.55rem}
.verify-scanner-modal.is-selfie-step .verify-wizard{margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step .verify-scan-stage[data-step="selfie"]{background:#fff;border-color:#cbd5e1;box-shadow:0 0 0 999px rgba(255,255,255,.55);padding:.65rem;display:flex;flex-direction:column;min-height:0;flex:1 1 auto}
.verify-scanner-modal.is-selfie-step .verify-liveness-card{background:#eef8ff;border-color:#7dd3fc;color:#0f2a3f;padding:.5rem;margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step .verify-face-instructions{background:#fff;border-color:#bae6fd;color:#0f2a3f}
.verify-scanner-modal.is-selfie-step .verify-upload-hint{color:#475569}
.verify-scanner-modal.is-selfie-step .verify-wizard-step{background:#fff;color:#475569;border-color:#dbe7f3}
.verify-scanner-modal.is-selfie-step .verify-wizard-step.active{background:#fff1f7;color:var(--primary);border-color:rgba(var(--primary-rgb,233,30,99),.55)}
.verify-scanner-modal.is-selfie-step .verify-screen-light-note{display:inline-flex}
.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{height:clamp(300px,58dvh,620px);max-height:none;flex:1 1 auto}
.verify-scanner-modal.is-selfie-step .verify-face-guide{width:min(48%,220px)}
.verify-scanner-modal.is-selfie-step [data-step="selfie"] > .d-flex:first-child{margin-bottom:.45rem!important}
.verify-scanner-modal.is-selfie-step [data-step="selfie"] .verify-upload-hint{font-size:.72rem}
.verify-scanner-modal.is-selfie-step .btn-outline-light{color:#0f172a;border-color:#94a3b8}
.verify-scanner-modal.is-selfie-step .btn-outline-light:hover{background:#e2e8f0;color:#0f172a}
.verify-screen-light-note{display:none;align-items:center;gap:.35rem;font-size:.74rem;color:#0f766e;background:#ccfbf1;border:1px solid #5eead4;border-radius:999px;padding:.25rem .55rem}
.verify-scanner-modal .verify-upload-status.text-danger{color:#fca5a5!important}.verify-scanner-modal .verify-upload-status.text-success{color:#86efac!important}.verify-scanner-modal .verify-upload-status.text-warning{color:#fde68a!important}
@keyframes verifyScanLine{0%,100%{transform:translateY(-22vh);opacity:.25}50%{transform:translateY(22vh);opacity:.9}}
.verify-upload-status.text-danger{color:#dc2626!important}.verify-upload-status.text-success{color:#15803d!important}
@media (max-width:575.98px){.verify-face-instructions ul{grid-template-columns:1fr;font-size:.7rem;gap:.24rem}.verify-hero{border-radius:0;margin-left:-.75rem;margin-right:-.75rem}.benefit-card{padding:.85rem}.upload-panel{padding:1rem!important}.verify-scanner-modal{padding:.35rem}.verify-scanner-modal .modal-dialog{margin:0;max-width:none;width:100%;height:calc(100dvh - .7rem);max-height:calc(100dvh - .7rem)}.verify-scanner-modal .modal-content{height:100%;min-height:0}.verify-scanner-modal .modal-header{padding:.7rem .85rem}.verify-scanner-modal .modal-title{font-size:.95rem}.verify-scanner-modal .modal-title + .small{font-size:.72rem}.verify-scanner-modal .modal-body{padding:.75rem;min-height:0}.verify-scanner-modal .modal-footer{padding:.65rem .75rem;gap:.5rem}.verify-scanner-modal .modal-footer .btn{flex:1 1 auto;padding:.55rem .6rem;font-size:.82rem}.verify-scanner-modal .verify-wizard{gap:.35rem;flex-wrap:nowrap;overflow-x:auto;padding-bottom:.15rem}.verify-scanner-modal .verify-wizard-step{flex:0 0 auto;padding:.42rem .55rem;font-size:.74rem}.verify-scanner-modal .verify-wizard-step span{width:19px;height:19px;font-size:.66rem}.verify-scan-stage{padding:.65rem;border-radius:8px}.verify-scan-stage .d-flex{gap:.5rem!important;align-items:flex-start!important}.verify-scan-stage h6{font-size:.88rem}.verify-scan-stage .small{font-size:.72rem}.verify-camera-frame{height:min(46dvh,340px);min-height:220px;max-height:calc(100dvh - 300px);aspect-ratio:auto;border-radius:8px}.verify-frame-guide{width:90%;max-height:64%}.verify-face-guide{width:min(50%,180px)}.verify-live-panel{left:.5rem;right:.5rem;bottom:.5rem;align-items:flex-start;flex-direction:column;gap:.45rem;padding:.55rem .6rem;font-size:.74rem;max-height:38%;overflow:auto}.verify-live-meter{width:100%;height:7px}.verify-liveness-card{padding:.55rem;font-size:.78rem}.verify-scanner-modal.is-selfie-step .modal-header{padding:.52rem .75rem}.verify-scanner-modal.is-selfie-step .modal-body{padding:.5rem .65rem}.verify-scanner-modal.is-selfie-step .modal-footer{padding:.5rem .65rem}.verify-scanner-modal.is-selfie-step .verify-wizard-step{padding:.34rem .48rem;font-size:.7rem}.verify-scanner-modal.is-selfie-step .verify-upload-icon{width:30px;height:30px}.verify-scanner-modal.is-selfie-step .verify-liveness-card{font-size:.72rem}.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{height:auto;min-height:320px;max-height:none;flex:1 1 auto}}
@media (max-width:360px){.verify-camera-frame{height:40dvh;min-height:200px;max-height:calc(100dvh - 320px)}.verify-scanner-modal .modal-footer .btn{font-size:.76rem;padding:.5rem .45rem}.verify-live-panel{font-size:.7rem}.verify-scanner-modal.is-selfie-step .verify-camera-frame.is-selfie{min-height:300px}}

.verify-scanner-modal .modal-header{padding:.85rem 1rem}
.verify-scanner-modal .modal-title{font-size:1rem}
.verify-scanner-modal .modal-body{gap:.75rem;padding:1rem}
.verify-scanner-modal .verify-wizard{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.45rem;margin-bottom:0!important}
.verify-scanner-modal .verify-wizard-step{justify-content:center;border-radius:999px;padding:.48rem .55rem;background:transparent}
.verify-scanner-modal .verify-wizard-step span{width:20px;height:20px}
.verify-scan-stage{border:0;border-radius:0;padding:0;background:transparent;min-height:0;display:flex;flex-direction:column;gap:.75rem;flex:1 1 auto}
.verify-scan-stage.d-none{display:none!important}
.verify-step-heading{display:flex;align-items:center;gap:.65rem;min-height:42px}
.verify-step-heading .verify-upload-icon{width:34px;height:34px}
.verify-step-copy{min-width:0}
.verify-step-title{font-weight:700;line-height:1.15}
.verify-camera-frame{height:clamp(320px,56vh,560px);border:1px solid rgba(148,163,184,.22);box-shadow:0 18px 42px rgba(0,0,0,.22)}
.verify-camera-frame.is-selfie{aspect-ratio:3/4;width:min(100%,430px);height:min(62dvh,620px);margin:0 auto;border-radius:18px;background:#020617}
.verify-camera-frame.is-selfie:after{display:none}
.verify-camera-frame.is-selfie video{object-fit:cover}
.verify-scanner-modal.is-selfie-step .modal-content{background:#f8fbff;color:#142033;box-shadow:none}
.verify-scanner-modal.is-selfie-step .modal-header,.verify-scanner-modal.is-selfie-step .modal-footer{background:#f8fbff;border-color:#dbe7f3;color:#142033}
.verify-scanner-modal.is-selfie-step .verify-scan-stage[data-step="selfie"]{background:transparent;border:0;box-shadow:none;padding:0}
.verify-scanner-modal.is-selfie-step .verify-face-instructions{border:0;background:transparent;padding:0;color:#334155}
.verify-scanner-modal.is-selfie-step .verify-face-instructions ul{display:flex;flex-wrap:wrap;gap:.4rem;font-size:.72rem}
.verify-scanner-modal.is-selfie-step .verify-face-instructions li{background:#fff;border:1px solid #dbeafe;border-radius:999px;padding:.28rem .55rem;align-items:center;box-shadow:0 6px 16px rgba(15,23,42,.05)}
.verify-scanner-modal.is-selfie-step .verify-liveness-card{border:0;background:#eaf7ff;color:#0f2a3f;padding:.55rem .7rem;margin-bottom:0!important}
.verify-scanner-modal.is-selfie-step .verify-live-panel{background:rgba(15,23,42,.78)}
.verify-scanner-modal.is-selfie-step .verify-face-guide{width:min(58%,240px)}
.verify-scan-actions{display:flex;flex-wrap:wrap;gap:.55rem;align-items:center}
.verify-scan-actions .btn{min-height:38px}
.verify-scanner-modal .modal-footer{padding:.75rem 1rem;gap:.75rem}
.verify-scanner-modal .modal-footer .verify-upload-hint{max-width:52ch}
@media (max-width:575.98px){.verify-scanner-modal .modal-body{padding:.65rem;gap:.55rem}.verify-scanner-modal .verify-wizard-step{font-size:.7rem;padding:.38rem .42rem}.verify-step-heading{min-height:36px}.verify-upload-icon{width:30px;height:30px}.verify-camera-frame{height:min(52dvh,390px);max-height:none}.verify-camera-frame.is-selfie{width:min(100%,360px);height:min(58dvh,500px);min-height:330px}.verify-scanner-modal.is-selfie-step .verify-face-instructions ul{gap:.3rem}.verify-scanner-modal.is-selfie-step .verify-face-instructions li{font-size:.68rem;padding:.24rem .45rem}.verify-scan-actions{gap:.45rem}.verify-scan-actions .btn{flex:1 1 auto;font-size:.78rem}.verify-scanner-modal .modal-footer{align-items:stretch}.verify-scanner-modal .modal-footer .verify-upload-hint{display:none}.verify-scanner-modal .modal-footer .d-flex{width:100%}.verify-scanner-modal .modal-footer .btn{flex:1 1 0}}
.verify-scanner-modal .modal-content{overflow:hidden}
@media (max-width:575.98px){
  .verify-scanner-modal .modal-body{overflow-y:auto;overscroll-behavior:contain}
  .verify-scanner-modal .modal-footer{flex:0 0 auto;position:relative;z-index:4}
  .verify-camera-frame{height:min(42dvh,320px)!important;max-height:none!important}
  .verify-camera-frame.is-selfie{height:min(40dvh,360px)!important;min-height:260px!important;flex:0 0 auto!important}
  .verify-live-panel{max-height:34%;overflow:auto}
  .verify-selfie-tips{gap:.38rem}
  .verify-selfie-tip{padding:.42rem .52rem;font-size:.68rem}
}
.verify-progress-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}
.verify-progress-tile{position:relative;min-height:112px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:.85rem;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.05);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.verify-progress-tile:before{content:"";position:absolute;left:.85rem;right:.85rem;top:.52rem;height:3px;border-radius:999px;background:#e2e8f0;overflow:hidden}
.verify-progress-tile.is-active{border-color:rgba(var(--primary-rgb,233,30,99),.42);box-shadow:0 14px 30px rgba(var(--primary-rgb,233,30,99),.11)}
.verify-progress-tile.is-active:before{background:linear-gradient(90deg,var(--primary),#22c55e);animation:verifyProgressPulse 1.8s ease-in-out infinite}
.verify-progress-tile:hover{transform:translateY(-2px)}
.verify-progress-icon{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#f8fafc;color:#64748b;margin-top:.25rem;margin-bottom:.55rem}
.verify-progress-tile.is-active .verify-progress-icon{background:rgba(var(--primary-rgb,233,30,99),.1);color:var(--primary)}
.verify-progress-label{font-weight:800;font-size:.82rem;color:#0f172a;line-height:1.15}
.verify-progress-copy{font-size:.72rem;color:#64748b;line-height:1.25;margin-top:.25rem}
.verify-reminder-shell{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1rem;box-shadow:0 12px 30px rgba(15,23,42,.05)}
.verify-reminder-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.verify-flip-card{border:0;background:transparent;padding:0;text-align:left;min-height:132px;perspective:900px;display:block;width:100%}
.verify-flip-inner{position:relative;min-height:132px;height:100%;transform-style:preserve-3d;transition:transform .48s cubic-bezier(.2,.7,.2,1);display:block;width:100%}
.verify-flip-card.is-flipped .verify-flip-inner,.verify-flip-card:focus-visible .verify-flip-inner{transform:rotateY(180deg)}
.verify-flip-face{position:absolute;inset:0;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:.82rem;backface-visibility:hidden;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 10px 22px rgba(15,23,42,.05);width:100%;min-width:0;overflow:hidden}
.verify-flip-back{transform:rotateY(180deg);background:#f8fbff;border-color:#cfe4ff}
.verify-flip-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.55rem}
.verify-flip-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:rgba(var(--primary-rgb,233,30,99),.1);color:var(--primary);flex:0 0 auto}
.verify-help-dot{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-weight:800;flex:0 0 auto}
.verify-flip-title{display:block;font-weight:800;font-size:.82rem;color:#0f172a;line-height:1.16;margin-top:.65rem;word-break:normal;overflow-wrap:normal}
.verify-flip-copy{font-size:.72rem;color:#64748b;line-height:1.3;margin-top:.3rem;word-break:normal;overflow-wrap:break-word}
.verify-flip-hint{font-size:.68rem;color:#94a3b8;margin-top:.55rem}
.verify-lock-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}
.verify-lock-tile{border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:.75rem;display:flex;gap:.55rem;align-items:flex-start;min-height:86px}
.verify-lock-tile i{color:#64748b;flex:0 0 auto;margin-top:.1rem}
.verify-lock-tile span{font-size:.76rem;color:#475569;line-height:1.28}
.verify-selfie-help-toggle{border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:.28rem .6rem;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.35rem}
.verify-selfie-tip-panel{display:none;border:1px solid #cfe4ff;border-radius:8px;background:#f8fbff;padding:.65rem .75rem;color:#334155;font-size:.74rem;line-height:1.35}
.verify-selfie-tip-panel.is-open{display:block;animation:verifySlideIn .24s ease-out}
.verify-selfie-tools{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.45rem}
@keyframes verifyProgressPulse{0%,100%{opacity:.55}50%{opacity:1}}
@keyframes verifySlideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
@media (max-width:991.98px){.verify-progress-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.verify-reminder-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:575.98px){.verify-progress-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem}.verify-progress-tile{min-height:104px;padding:.7rem}.verify-progress-label{font-size:.76rem}.verify-progress-copy{font-size:.68rem}.verify-reminder-shell{padding:.75rem}.verify-reminder-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;align-items:stretch}.verify-flip-card{min-height:132px}.verify-flip-inner{min-height:132px;display:block;width:100%}.verify-flip-face{padding:.68rem}.verify-flip-title{font-size:.76rem;line-height:1.15}.verify-flip-copy{font-size:.68rem;line-height:1.25}.verify-lock-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.verify-lock-tile{min-height:96px;padding:.62rem}.verify-lock-tile span{font-size:.68rem}.verify-selfie-tools{align-items:stretch}.verify-selfie-help-toggle{width:100%;justify-content:center}}
</style>

<div class="container-fluid py-4">
  @if(session('msg'))<div class="alert alert-success border-0">{{ session('msg') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger border-0">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger border-0">{{ $errors->first() }}</div>@endif

  @php
    $reviewStarted = in_array($status, ['pending','approved','rejected'], true);
    $progressItems = [
      ['icon' => 'bi-card-checklist', 'label' => 'Choose ID', 'copy' => 'Pick the exact ID type before scanning.', 'active' => true],
      ['icon' => 'bi-upc-scan', 'label' => 'Scan ID', 'copy' => 'Front and back photos are checked clearly.', 'active' => $reviewStarted],
      ['icon' => 'bi-person-bounding-box', 'label' => 'Face Match', 'copy' => 'Selfie is compared with the ID photo.', 'active' => $reviewStarted],
      ['icon' => 'bi-shield-check', 'label' => 'Admin Review', 'copy' => $status === 'approved' ? 'Verified benefits are active.' : 'Authorized admins make the final decision.', 'active' => $reviewStarted],
    ];
    $verifyReminders = [
      ['icon' => 'bi-credit-card-2-front', 'title' => 'Front ID', 'short' => 'Show the full front side.', 'detail' => 'Keep all corners visible. Avoid glare, blur, and cropped text.'],
      ['icon' => 'bi-arrow-repeat', 'title' => 'Back ID', 'short' => 'Use the same actual ID.', 'detail' => 'Do not upload the front side again. The back must be clear when required.'],
      ['icon' => 'bi-person-square', 'title' => 'Selfie', 'short' => 'Only your face should show.', 'detail' => 'Use a plain background and look straight at the camera.'],
      ['icon' => 'bi-brightness-high', 'title' => 'Lighting', 'short' => 'Bright, even light helps.', 'detail' => 'Avoid heavy shadows and strong glare on the ID or face.'],
      ['icon' => 'bi-eyeglasses', 'title' => 'No Covers', 'short' => 'Remove mask, cap, shades.', 'detail' => 'Anything covering the eyes or face can make the match inconclusive.'],
      ['icon' => 'bi-hourglass-split', 'title' => 'Review', 'short' => 'Admins check final result.', 'detail' => 'Needs review is not automatic rejection. Admin approval or rejection is manual.'],
    ];
  @endphp

  <div class="verify-hero p-4 p-md-5 mb-4">
    <div class="row g-4 align-items-center position-relative" style="z-index:1">
      <div class="col-lg-5">
        <div class="d-inline-flex align-items-center gap-2 rounded-pill px-3 py-1 mb-3" style="background:#fff;border:1px solid #f3d6e5">
          <i class="bi {{ $statusIcon }}" style="color:var(--primary)"></i>
          <span class="small fw-semibold">{{ $statusLabel }}</span>
        </div>
        <h3 class="fw-bold mb-2">Verify your BerryBase account</h3>
        <p class="text-muted mb-3">Upload a valid ID once to unlock rewards redemption, verified-only vouchers, and Customer Loyalty Trust for larger COD/COP orders.</p>
        <div class="d-flex flex-wrap gap-2 small">
          <span class="badge text-bg-light"><i class="bi bi-lock me-1"></i>Private review</span>
          <span class="badge text-bg-light"><i class="bi bi-camera me-1"></i>Guided scan</span>
          <span class="badge text-bg-light"><i class="bi bi-question-circle me-1"></i>Tap cards for reminders</span>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="verify-progress-grid">
          @foreach($progressItems as $item)
            <div class="verify-progress-tile {{ $item['active'] ? 'is-active' : '' }}">
              <div class="verify-progress-icon"><i class="bi {{ $item['icon'] }}"></i></div>
              <div class="verify-progress-label">{{ $item['label'] }}</div>
              <div class="verify-progress-copy">{{ $item['copy'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @foreach($benefits as $benefit)
      <div class="col-6 col-lg-3">
        <div class="benefit-card {{ $benefit['unlocked'] ? '' : 'locked' }}">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <i class="bi {{ $benefit['icon'] }}" style="font-size:1.4rem;color:{{ $benefit['unlocked'] ? 'var(--primary)' : '#94a3b8' }}"></i>
            <span class="badge {{ $benefit['unlocked'] ? 'text-bg-success' : 'text-bg-light' }}">{{ $benefit['unlocked'] ? 'Unlocked' : 'Locked' }}</span>
          </div>
          <div class="fw-bold small mb-1">{{ $benefit['title'] }}</div>
          <div class="text-muted small">{{ $benefit['copy'] }}</div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="verify-reminder-shell mb-4">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h6 class="fw-bold mb-1"><i class="bi bi-question-circle me-2" style="color:var(--primary)"></i>Scan Reminders</h6>
        <div class="text-muted small">Tap a card to flip it and see the reminder.</div>
      </div>
    </div>
    <div class="verify-reminder-grid">
      @foreach($verifyReminders as $reminder)
        <button type="button" class="verify-flip-card" aria-pressed="false">
          <span class="verify-flip-inner">
            <span class="verify-flip-face">
              <span class="verify-flip-top"><span class="verify-flip-icon"><i class="bi {{ $reminder['icon'] }}"></i></span><span class="verify-help-dot">?</span></span>
              <span><span class="verify-flip-title">{{ $reminder['title'] }}</span><span class="verify-flip-copy d-block">{{ $reminder['short'] }}</span></span>
              <span class="verify-flip-hint">Tap for detail</span>
            </span>
            <span class="verify-flip-face verify-flip-back">
              <span class="verify-flip-top"><span class="verify-flip-icon"><i class="bi bi-info-circle"></i></span><span class="verify-help-dot">?</span></span>
              <span><span class="verify-flip-title">{{ $reminder['title'] }}</span><span class="verify-flip-copy d-block">{{ $reminder['detail'] }}</span></span>
              <span class="verify-flip-hint">Tap to close</span>
            </span>
          </span>
        </button>
      @endforeach
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="upload-panel p-4">
        <h5 class="fw-bold mb-1">Submit valid ID</h5>
        <p class="text-muted small mb-3">Use the guided scanner for a clear ID and live face verification. Mobile uses camera-only scanning; desktop can upload ID photos, but face verification always requires a supported camera.</p>
        @if($status === 'pending')
          <div class="alert alert-warning border-0 mb-0"><i class="bi bi-hourglass-split me-1"></i>Your latest submission is pending review. You can still order normally.</div>
        @elseif($status === 'approved')
          <div class="alert alert-success border-0 mb-0"><i class="bi bi-patch-check-fill me-1"></i>Your account is verified. Benefits are active.</div>
        @else
          @if($status === 'rejected' && $latest?->rejection_reason)
            <div class="alert alert-danger border-0"><strong>Reason:</strong> {{ $latest->rejection_reason }}</div>
          @endif
          <form action="{{ route('customer.verification.store') }}" method="POST" enctype="multipart/form-data" id="verificationWizardForm" data-scan-url="{{ route('customer.verification.scan_front') }}" data-back-scan-url="{{ route('customer.verification.scan_back') }}" data-face-url="{{ route('customer.verification.compare_face') }}" data-prevent-double-submit>
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold small">ID Type</label>
              <select class="form-select" name="id_type" id="verificationIdType" required>
                <option value="">Select ID type</option>
                @foreach($idTypes as $idType)
                  @php $typeRule = $idTypeRules[$idType] ?? []; @endphp
                  <option value="{{ $idType }}" data-requires-back="{{ !empty($typeRule['requires_back']) ? '1' : '0' }}" {{ old('id_type') === $idType ? 'selected' : '' }}>{{ $idType }}</option>
                @endforeach
              </select>
              <div class="verify-upload-hint mt-1" id="idTypeLockNotice">Select your ID type before uploading or capturing your ID. It locks after the front ID is accepted.</div>
            </div>

            <input type="file" class="verify-file-input" id="idFrontInput" name="id_front" accept="image/*" required>
            <input type="file" class="verify-file-input" id="idBackInput" name="id_back" accept="image/*">
            <input type="file" class="verify-file-input" id="selfieInput" name="selfie" accept="image/*" required>
            <input type="hidden" name="liveness_challenge" id="livenessChallengeInput">
            <input type="hidden" name="liveness_result" id="livenessResultInput" value="not_required">
            <input type="hidden" name="liveness_method" id="livenessMethodInput" value="selfie_image_compare">
            <input type="hidden" name="id_front_hash" id="idFrontHashInput">
            <input type="hidden" name="id_back_hash" id="idBackHashInput">
            <input type="hidden" name="selfie_capture_source" id="selfieCaptureSourceInput" value="not_started">

            <div class="verify-ready-panel d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
              <div>
                <div class="fw-semibold">Smart ID scanner</div>
                <div class="verify-upload-hint">Upload or capture ID photos, then add a selfie for face matching.</div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="resetVerificationScan" disabled>
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Scan
                </button>
                <button type="button" class="btn btn-primary" id="openVerificationScanner" data-bs-toggle="modal" data-bs-target="#verificationScannerModal" disabled>
                  <i class="bi bi-upc-scan me-1"></i>Start Verification
                </button>
                <button type="submit" class="btn btn-success d-none" id="verificationSubmitButton" disabled>
                  <i class="bi bi-shield-check me-1"></i>Submit for Review
                </button>
              </div>
            </div>
            <div id="idUploadSummary" class="mt-3"></div>
            <div class="small text-muted mt-3"><i class="bi bi-lock me-1"></i>Your ID is checked before submit and then reviewed by authorized admins.</div>

            <div class="modal fade verify-scanner-modal" id="verificationScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
              <div class="modal-dialog modal-fullscreen-sm-down modal-xl">
                <div class="modal-content">
                  <div class="modal-header">
                    <div>
                      <h5 class="modal-title fw-bold mb-0">Smart ID Verification</h5>
                      <div class="verify-upload-hint">Clear ID photos first, then upload or capture a selfie for matching.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="verify-wizard mb-3">
                      <div class="verify-wizard-step active" data-step-label="front"><span>1</span> Front ID</div>
                      <div class="verify-wizard-step" data-step-label="back"><span>2</span> Back ID</div>
                      <div class="verify-wizard-step" data-step-label="selfie"><span>3</span> Face</div>
                    </div>

                    <div class="verify-scan-stage" data-step="front">
                      <div class="verify-step-heading">
                        <div class="verify-upload-icon"><i class="bi bi-credit-card-2-front"></i></div>
                        <div class="verify-step-copy"><div class="verify-step-title">Front ID</div><div class="verify-upload-hint">Upload a clear front photo or use the camera.</div></div>
                      </div>
                      <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><img class="verify-preview-image" alt="Accepted front ID preview"><div class="verify-frame-guide"></div><div class="verify-live-panel"><span data-live-hint>Open camera or upload a clear front ID.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="verify-scan-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-camera-start="idFrontInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="idFrontInput"><i class="bi bi-camera me-1"></i>Capture Now</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-torch-toggle hidden disabled><i class="bi bi-lightbulb me-1"></i>Flashlight</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-upload-trigger="idFrontInput" data-desktop-upload><i class="bi bi-upload me-1"></i>Upload Photo</button>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="idFrontInput">Waiting for front ID scan.</div>
                    </div>

                    <div class="verify-scan-stage d-none" data-step="back">
                      <div class="verify-step-heading">
                        <div class="verify-upload-icon"><i class="bi bi-arrow-repeat"></i></div>
                        <div class="verify-step-copy"><div class="verify-step-title">Back ID</div><div class="verify-upload-hint">Use the actual back side of the same ID.</div></div>
                      </div>
                      <div class="verify-camera-frame"><video playsinline muted></video><canvas hidden></canvas><img class="verify-preview-image" alt="Accepted back ID preview"><div class="verify-frame-guide"></div><div class="verify-live-panel"><span data-live-hint>Keep the back of the card landscape inside the guide.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="verify-scan-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-camera-start="idBackInput" data-facing="environment"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="idBackInput"><i class="bi bi-camera me-1"></i>Capture Now</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-torch-toggle hidden disabled><i class="bi bi-lightbulb me-1"></i>Flashlight</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-upload-trigger="idBackInput" data-desktop-upload><i class="bi bi-upload me-1"></i>Upload Photo</button>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="idBackInput">Waiting for back ID scan.</div>
                    </div>

                    <div class="verify-scan-stage d-none" data-step="selfie">
                      <div class="verify-step-heading">
                        <div class="verify-upload-icon"><i class="bi bi-person-bounding-box"></i></div>
                        <div class="verify-step-copy"><div class="verify-step-title">Selfie Verification</div><div class="verify-upload-hint">Upload a clear selfie or capture one with the front camera.</div><div class="verify-screen-light-note mt-1"><i class="bi bi-brightness-high"></i>Screen light active when camera opens</div></div>
                      </div>
                      <div class="verify-liveness-card mb-2"><div class="small text-uppercase fw-semibold" style="letter-spacing:.04em">Face Match Status</div><div class="fw-semibold" id="livenessPrompt">Add a clear selfie for matching.</div><div class="small" id="livenessProgress">The system compares your selfie with the face on the front ID.</div></div>
                      <div class="verify-selfie-tools"><div class="verify-selfie-tips"><div class="verify-selfie-tip"><i class="bi bi-brightness-high"></i><span>Bright light</span></div><div class="verify-selfie-tip"><i class="bi bi-person-square"></i><span>Plain background</span></div><div class="verify-selfie-tip"><i class="bi bi-eyeglasses"></i><span>No mask or shades</span></div><div class="verify-selfie-tip"><i class="bi bi-bullseye"></i><span>Look straight</span></div></div><button type="button" class="verify-selfie-help-toggle" aria-expanded="false"><i class="bi bi-question-circle"></i>Selfie reminders</button></div><div class="verify-selfie-tip-panel mb-2">Use bright light, keep only your face visible, remove glasses/shades/mask/cap, and look straight at the camera so admin review has a clear face match.</div>
                      <div class="verify-camera-frame is-selfie is-face-ready" id="faceCameraFrame"><video playsinline muted></video><canvas hidden></canvas><img class="verify-preview-image" alt="Accepted selfie preview"><div class="verify-face-guide"></div><div class="verify-live-panel"><span data-live-hint>Upload a selfie or open the front camera.</span><div class="verify-live-meter"><span></span></div></div></div>
                      <div class="verify-scan-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-upload-trigger="selfieInput"><i class="bi bi-upload me-1"></i>Upload Photo</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-start="selfieInput" data-facing="user"><i class="bi bi-camera-video me-1"></i>Open Camera</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-camera-capture="selfieInput"><i class="bi bi-camera me-1"></i>Capture Now</button>
                        <button type="button" class="btn btn-outline-light btn-sm" data-torch-toggle disabled><i class="bi bi-lightbulb me-1"></i>Flashlight</button>
                      </div>
                      <div class="verify-upload-status mt-2" data-upload-status-for="selfieInput">Waiting for selfie photo.</div>
                    </div>
                  </div>
                  <div class="modal-footer justify-content-between">
                    <div class="verify-upload-hint"><i class="bi bi-shield-lock me-1"></i>Server checks the front ID again before saving.</div>
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Done</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
        @endif
      </div>
    </div>
    <div class="col-lg-5">
      @php
        $membership = $loyaltyOverview ?? [];
        $membershipTiers = $membership['tiers'] ?? [];
        $nextMembership = $membership['next_tier'] ?? null;
      @endphp
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-award me-2" style="color:var(--primary)"></i>Membership Rewards</h6>
            <span class="badge text-bg-light">{{ $membership['current_tier'] ?? 'Bronze' }}</span>
          </div>
          <div class="text-muted small mb-3">
            {{ $status === 'approved' ? 'Verified redemption is active.' : 'Earn points now. Verify your account to redeem rewards and current verified-only vouchers.' }}
          </div>
          <div class="d-flex justify-content-between small mb-1">
            <span>{{ (int)($membership['balance'] ?? 0) }} earned points</span>
            <span>
              @if($nextMembership)
                {{ $membership['points_to_next'] ?? 0 }} to {{ $nextMembership->name }}
              @else
                Top tier
              @endif
            </span>
          </div>
          <div class="progress mb-3" style="height:8px">
            <div class="progress-bar" style="width:{{ (int)($membership['progress'] ?? 0) }}%;background:var(--primary)"></div>
          </div>
          <div class="vstack gap-2">
            @foreach($membershipTiers as $tier)
              <div class="verify-member-tier {{ $tier['is_current'] ? 'is-current' : '' }} {{ $tier['is_unlocked'] ? '' : 'is-locked' }}">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold small">{{ $tier['name'] }} Member</div>
                    <div class="text-muted" style="font-size:.76rem">{{ number_format($tier['min_lifetime_points']) }} earned pts required</div>
                  </div>
                  <span class="badge {{ $tier['is_current'] ? 'text-white' : ($tier['is_unlocked'] ? 'text-bg-success' : 'text-bg-light') }}" @if($tier['is_current']) style="background:var(--primary)" @endif>
                    {{ $tier['is_current'] ? 'Current' : ($tier['is_unlocked'] ? 'Unlocked' : 'Locked') }}
                  </span>
                </div>
                <div class="text-muted mt-2" style="font-size:.78rem">{{ $tier['perk_summary'] ?: ($tier['benefits'][0] ?? 'Earn rewards on completed orders.') }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h6 class="fw-bold mb-3">While not verified</h6>
          @if(count($limitations))
            @foreach($limitations as $item)
              <div class="d-flex gap-2 mb-2 small"><i class="bi bi-lock text-muted"></i><span>{{ $item }}</span></div>
            @endforeach
          @else
            <div class="alert alert-success py-2 mb-0 small">No locked benefits. Your account is fully verified.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
document.addEventListener('click', function (event) {
  var flipCard = event.target.closest('.verify-flip-card');
  if (flipCard) {
    flipCard.classList.toggle('is-flipped');
    flipCard.setAttribute('aria-pressed', flipCard.classList.contains('is-flipped') ? 'true' : 'false');
    return;
  }
  var selfieToggle = event.target.closest('.verify-selfie-help-toggle');
  if (selfieToggle) {
    var selfieStage = selfieToggle.closest('[data-step="selfie"]');
    var panel = selfieStage ? selfieStage.querySelector('.verify-selfie-tip-panel') : null;
    if (panel) {
      panel.classList.toggle('is-open');
      selfieToggle.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
    }
  }
});
  var form = document.getElementById('verificationWizardForm');
  if (!form) return;

  var scanUrl = form.dataset.scanUrl;
  var backScanUrl = form.dataset.backScanUrl;
  var faceUrl = form.dataset.faceUrl;
  var token = form.querySelector('input[name="_token"]')?.value || '';
  var idType = document.getElementById('verificationIdType');
  var submitButton = document.getElementById('verificationSubmitButton');
  var launchButton = document.getElementById('openVerificationScanner');
  var resetButton = document.getElementById('resetVerificationScan');
  var modalEl = document.getElementById('verificationScannerModal');
  var state = { front:false, back:false, selfie:false, frontFile:null, backFile:null, selfieFile:null, backRequired:true, lockedIdType:null, isMobileDevice:false, cameraCaptureInput:null, stream:null, activeInput:null, activeLoop:0, stableFrames:0, processing:false, currentStep:'front', stepStartedAt:0, lastCompareStartedAt:0, lastLive:{}, autoStarting:false, faceCompare:{ lastAt:0, inFlight:false, status:null, score:null, message:null }, liveness:{ challenge:null, baseline:null, passed:false, unsupported:false }, faceLandmarker:null, faceLandmarkerPromise:null, faceLandmarkerFailed:false, faceLandmarkerStartedAt:0, torchOn:false, torchTrack:null, faceReady:false, faceScanStartedAt:0, selfieServerMatched:false, selfieMatchedResult:null, previewUrls:{}, scanStarted:false };
  var BACK_SIDE_SAME_HASH_DISTANCE = 72;

  function statusFor(inputId) { return document.querySelector('[data-upload-status-for="' + inputId + '"]'); }
  function setStatus(inputId, message, type) {
    var el = statusFor(inputId);
    if (!el) return;
    if (el.textContent !== message) el.textContent = message;
    el.classList.remove('text-success','text-danger','text-warning');
    if (type) el.classList.add(type);
  }
  function activeStage() { return document.querySelector('[data-step="' + state.currentStep + '"]'); }
  function detectMobileDevice() {
    return window.matchMedia('(pointer: coarse)').matches || /Android|iPhone|iPad|iPod|IEMobile|Mobile/i.test(navigator.userAgent || '');
  }
  function applyDeviceRules() {
    state.isMobileDevice = detectMobileDevice();
    if (modalEl) modalEl.classList.toggle('is-mobile-scan-only', state.isMobileDevice);
    document.querySelectorAll('[data-desktop-upload]').forEach(function (button) {
      button.classList.remove('d-none');
      button.disabled = false;
    });
  }
  function manualStartMessage(step) {
    if (step === 'back') return 'Upload the back ID photo or open the camera and tap Capture Now.';
    if (step === 'selfie') return 'Upload a clear selfie or open the front camera and tap Capture Now.';
    return 'Upload the front ID photo or open the camera and tap Capture Now.';
  }
  function hasAnyScan() { return !!(state.front || state.back || state.selfie || state.frontFile || state.backFile || state.selfieFile); }
  function setIdTypeLocked(locked) {
    if (!idType) return;
    state.lockedIdType = locked ? idType.value : null;
    idType.disabled = !!locked;
    idType.classList.toggle('is-id-locked', !!locked);
    var notice = document.getElementById('idTypeLockNotice');
    if (notice) notice.textContent = locked ? 'ID type locked after accepted front scan. Use Reset Scan to choose a different ID type.' : 'Select your ID type before uploading or capturing your ID. It locks after the front ID is accepted.';
  }
  function setLiveness(message, progress) {
    var prompt = document.getElementById('livenessPrompt');
    var progressEl = document.getElementById('livenessProgress');
    if (prompt && message) prompt.textContent = message;
    if (progressEl && progress) progressEl.textContent = progress;
  }
  function setFaceInstructionVisible(visible) {
    var frame = document.getElementById('faceCameraFrame');
    var panel = document.getElementById('faceInstructionPanel');
    var help = document.getElementById('faceInstructionHelp');
    if (frame) frame.classList.add('is-face-ready');
    if (panel) panel.classList.toggle('d-none', !visible);
    if (help) help.classList.toggle('d-none', visible || !state.faceReady);
  }
  function resetFaceReady() {
    state.faceReady = true;
    setFaceInstructionVisible(false);
  }
  function prepareFaceStepInstructions() {
    resetFaceReady();
    setLiveness('Review the face instructions first.', 'Tap I\'m Ready when your face and background are prepared.');
    setStatus('selfieInput', 'Read the instructions, then tap I\'m Ready.', 'text-warning');
    setLive(document.querySelector('[data-step="selfie"]'), 'Read instructions, then tap I\'m Ready.', 52, true);
  }
  function resetLiveness() {
    var challenges = [
      { key:'move_closer', prompt:'Slowly move your face closer to the camera.', pass:function (base, box) { return base && box && box.width >= base.width * 1.06; } }
    ];
    state.liveness = { challenge: challenges[Math.floor(Math.random() * challenges.length)], baseline:null, passed:false, unsupported:false };
    document.getElementById('livenessChallengeInput').value = state.liveness.challenge.key;
    document.getElementById('livenessResultInput').value = 'pending';
    document.getElementById('livenessMethodInput').value = 'mediapipe_face_landmarker';
    setLiveness(state.liveness.challenge.prompt, 'Center your face first, then follow the challenge.');
  }
  async function loadFaceLandmarker() {
    if (state.faceLandmarker) return state.faceLandmarker;
    if (state.faceLandmarkerFailed) return null;
    if (!state.faceLandmarkerPromise) {
      state.faceLandmarkerStartedAt = Date.now();
      state.faceLandmarkerPromise = import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/vision_bundle.mjs')
        .then(async function (vision) {
          var fileset = await vision.FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/wasm');
          state.faceLandmarker = await vision.FaceLandmarker.createFromOptions(fileset, {
            baseOptions: { modelAssetPath:'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/latest/face_landmarker.task' },
            runningMode:'VIDEO',
            numFaces:1
          });
          return state.faceLandmarker;
        })
        .catch(function (error) { state.faceLandmarkerFailed = true; state.faceLandmarkerError = error && error.message ? error.message : 'load_failed'; return null; });
    }
    return await state.faceLandmarkerPromise;
  }
  function boxFromLandmarks(landmarks, video) {
    if (!landmarks || !landmarks.length || !video || !video.videoWidth) return null;
    var minX = 1, minY = 1, maxX = 0, maxY = 0;
    landmarks.forEach(function (point) {
      minX = Math.min(minX, point.x); minY = Math.min(minY, point.y);
      maxX = Math.max(maxX, point.x); maxY = Math.max(maxY, point.y);
    });
    return { x:minX * video.videoWidth, y:minY * video.videoHeight, width:(maxX - minX) * video.videoWidth, height:(maxY - minY) * video.videoHeight };
  }
  async function detectLiveFaceBox(video) {
    if (state.faceLandmarker) {
      var result = state.faceLandmarker.detectForVideo(video, performance.now());
      return result && result.faceLandmarks && result.faceLandmarks.length ? boxFromLandmarks(result.faceLandmarks[0], video) : null;
    }
    if (!state.faceLandmarkerPromise && !state.faceLandmarkerFailed) loadFaceLandmarker();
    if ('FaceDetector' in window) {
      var detector = new FaceDetector({ fastMode:true, maxDetectedFaces:1 });
      var faces = await detector.detect(video);
      return faces.length ? faces[0].boundingBox : null;
    }
    return null;
  }
  async function checkLiveness(stage) {
    if (state.currentStep !== 'selfie' || state.liveness.passed) return true;
    var video = stage ? stage.querySelector('video') : null;
    if (!video || !video.videoWidth) return false;
    try {
      setLiveness(state.liveness.challenge.prompt, state.faceLandmarker ? 'Face aligned. Follow the movement prompt.' : 'Loading face alignment detector...');
      var box = await detectLiveFaceBox(video);
      if (!box) {
        var waitingMs = Date.now() - (state.faceLandmarkerStartedAt || state.stepStartedAt || Date.now());
        if ((state.faceLandmarkerFailed && !('FaceDetector' in window)) || waitingMs > 1500) {
          state.liveness.unsupported = true;
          state.liveness.passed = true;
          document.getElementById('livenessResultInput').value = state.faceLandmarkerFailed ? 'detector_load_failed' : 'detector_loading_timeout';
          document.getElementById('livenessMethodInput').value = 'server_face_compare_required';
          setLiveness('Live detector is slow on this device.', 'Continue with the live camera selfie. Server face matching must still pass.');
          return true;
        }
        setLiveness(state.liveness.challenge.prompt, 'Finding your face. Center it inside the oval.');
        return false;
      }
      if (!state.liveness.baseline) {
        state.liveness.baseline = { x:box.x, y:box.y, width:box.width, height:box.height };
        setLiveness(state.liveness.challenge.prompt, 'Face aligned. Move slowly and stay inside the oval.');
        return false;
      }
      var challengeWaitMs = Date.now() - (state.stepStartedAt || Date.now());
      if (state.liveness.challenge.pass(state.liveness.baseline, box) || challengeWaitMs > 1200) {
        state.liveness.passed = true;
        document.getElementById('livenessResultInput').value = state.liveness.challenge.pass(state.liveness.baseline, box) ? 'passed' : 'fast_live_camera_review';
        document.getElementById('livenessMethodInput').value = state.faceLandmarker ? 'mediapipe_face_landmarker' : 'browser_face_detector';
        setLiveness('Live camera check accepted.', 'Hold steady. Matching your face with the ID now.');
        return true;
      }
      setLiveness(state.liveness.challenge.prompt, 'Small movement detected. Hold for a moment.');
      return false;
    } catch (e) {
      state.liveness.unsupported = true;
      document.getElementById('livenessResultInput').value = 'detector_error';
      document.getElementById('livenessMethodInput').value = 'server_face_compare_required';
      setLiveness('Live movement detector is unavailable.', 'Keep your face clear. Server face matching must still pass.');
      return true;
    }
  }
  function setLive(stage, message, score, force) {
    if (!stage) return;
    var hint = stage.querySelector('[data-live-hint]');
    var meter = stage.querySelector('.verify-live-meter');
    var fill = meter ? meter.querySelector('span') : null;
    var clamped = Math.max(0, Math.min(100, score || 0));
    var key = stage.dataset.step || 'active';
    var now = Date.now();
    var last = state.lastLive[key] || { message:'', at:0, score:0 };
    var shouldUpdateText = force || (message !== last.message && (now - last.at > 650 || clamped >= 86 || clamped < last.score - 18));
    if (hint && shouldUpdateText) {
      hint.textContent = message;
      state.lastLive[key] = { message:message, at:now, score:clamped };
    }
    if (fill) fill.style.width = clamped + '%';
    if (meter) {
      meter.classList.toggle('is-good', clamped >= 86);
      meter.classList.toggle('is-warn', clamped >= 60 && clamped < 86);
    }
  }
  function showStep(step) {
    state.currentStep = step;
    state.stepStartedAt = Date.now();
    if (step === 'selfie') state.lastCompareStartedAt = Date.now();
    document.querySelectorAll('[data-step]').forEach(function (el) { el.classList.toggle('d-none', el.dataset.step !== step); });
    document.querySelectorAll('[data-step-label]').forEach(function (el) {
      el.classList.toggle('active', el.dataset.stepLabel === step);
      el.classList.toggle('done', !!state[el.dataset.stepLabel]);
    });
    if (modalEl) modalEl.classList.toggle('is-selfie-step', step === 'selfie');
    stopCamera();
    var stage = activeStage();
    var input = inputForStep(step);
    setLive(stage, manualStartMessage(step), step === 'selfie' ? 62 : 45, true);
    if (input) setStatus(input.id, manualStartMessage(step), 'text-warning');
    if (step === 'selfie') {
      setLiveness('Add a clear selfie for matching.', 'The system compares your selfie with the face on the front ID.');
      document.getElementById('livenessChallengeInput').value = 'none';
      document.getElementById('livenessResultInput').value = 'not_required';
      document.getElementById('livenessMethodInput').value = 'selfie_image_compare';
    }
  }
  function selectedRequiresBack() {
    var option = idType ? idType.options[idType.selectedIndex] : null;
    return !option || option.dataset.requiresBack !== '0';
  }
  function updateBackRequirementUI() {
    state.backRequired = selectedRequiresBack();
    var backInput = document.getElementById('idBackInput');
    if (backInput) backInput.required = state.backRequired;
    document.querySelectorAll('[data-step-label="back"]').forEach(function (el) {
      el.classList.toggle('d-none', !state.backRequired);
    });
    var backStage = document.querySelector('[data-step="back"]');
    if (backStage && !state.backRequired) backStage.classList.add('d-none');
    if (!state.backRequired && !state.back) setStatus('idBackInput', 'Back ID is not required for this ID type.', 'text-success');
  }
  function updateSubmit() {
    updateBackRequirementUI();
    updateStepControls();
    var backOk = state.backRequired ? state.back : true;
    var complete = !!(state.front && backOk && state.selfie && state.lockedIdType && state.lockedIdType === idType.value);
    var hasScan = hasAnyScan();
    var workflowActive = hasScan || state.scanStarted;
    if (submitButton) {
      submitButton.disabled = !complete;
      submitButton.classList.toggle('d-none', !complete);
    }
    if (launchButton) {
      launchButton.disabled = !idType.value || workflowActive;
      launchButton.classList.toggle('d-none', workflowActive || complete);
    }
    if (resetButton) resetButton.disabled = !workflowActive;
  }
  function stopCamera() {
    resetTorchControls();
    state.activeLoop += 1;
    state.stableFrames = 0;
    state.processing = false;
    if (state.stream) state.stream.getTracks().forEach(function (track) { track.stop(); });
    state.stream = null;
    document.querySelectorAll('video').forEach(function (video) { video.srcObject = null; });
    if (modalEl && state.currentStep !== 'selfie') modalEl.classList.remove('is-selfie-step');
  }
  function torchButtonForStage(stage) {
    return stage ? stage.querySelector('[data-torch-toggle]') : null;
  }
  function setTorchButton(button, enabled, on) {
    if (!button) return;
    button.hidden = false;
    button.disabled = !enabled;
    button.classList.toggle('btn-warning', !!on);
    button.classList.toggle('btn-outline-light', !on);
    button.innerHTML = on ? '<i class="bi bi-lightbulb-fill me-1"></i>Flash On' : '<i class="bi bi-lightbulb me-1"></i>Flashlight';
  }
  function resetTorchControls() {
    state.torchOn = false;
    state.torchTrack = null;
    document.querySelectorAll('[data-torch-toggle]').forEach(function (button) { setTorchButton(button, false, false); });
  }
  async function setTorch(on) {
    var track = state.torchTrack;
    if (!track) return false;
    try {
      await track.applyConstraints({ advanced:[{ torch: !!on }] });
      state.torchOn = !!on;
      setTorchButton(torchButtonForStage(activeStage()), true, state.torchOn);
      return true;
    } catch (e) {
      state.torchOn = false;
      state.torchTrack = null;
      setTorchButton(torchButtonForStage(activeStage()), false, false);
      return false;
    }
  }
  function prepareTorchForStage(stage, input) {
    resetTorchControls();
    if (!state.stream || !stage || !input) return;
    var track = state.stream.getVideoTracks ? state.stream.getVideoTracks()[0] : null;
    var caps = track && track.getCapabilities ? track.getCapabilities() : {};
    var button = torchButtonForStage(stage);
    if (track && caps && caps.torch) {
      state.torchTrack = track;
      setTorchButton(button, true, false);
    } else {
      setTorchButton(button, false, false);
    }
  }
  function inputStep(input) {
    if (input.id === 'idFrontInput') return 'front';
    if (input.id === 'idBackInput') return 'back';
    return 'selfie';
  }
  function stepAllowed(step) {
    if (step === 'front') return !!(idType && idType.value);
    if (step === 'back') return !!(state.front && state.lockedIdType && state.lockedIdType === idType.value && state.backRequired);
    if (step === 'selfie') return !!(state.front && (state.backRequired ? state.back : true) && state.lockedIdType && state.lockedIdType === idType.value);
    return false;
  }
  function updateStepControls() {
    document.querySelectorAll('[data-step]').forEach(function (stage) {
      var allowed = stepAllowed(stage.dataset.step);
      stage.querySelectorAll('button').forEach(function (button) {
        if (button.id === 'verificationSubmitButton') return;
        button.disabled = !allowed || button.disabled && button.hasAttribute('data-torch-toggle');
      });
    });
  }
  function inputForStep(step) {
    return document.getElementById(step === 'front' ? 'idFrontInput' : (step === 'back' ? 'idBackInput' : 'selfieInput'));
  }
  function stageForInputId(inputId) {
    var input = document.getElementById(inputId);
    return input ? document.querySelector('[data-step="' + inputStep(input) + '"]') : null;
  }
  function clearFramePreview(inputId) {
    var stage = stageForInputId(inputId);
    var frame = stage ? stage.querySelector('.verify-camera-frame') : null;
    var preview = frame ? frame.querySelector('.verify-preview-image') : null;
    if (state.previewUrls[inputId]) {
      URL.revokeObjectURL(state.previewUrls[inputId]);
      delete state.previewUrls[inputId];
    }
    if (preview) preview.removeAttribute('src');
    if (frame) frame.classList.remove('has-preview');
  }
  function setFramePreview(inputId, file) {
    var stage = stageForInputId(inputId);
    var frame = stage ? stage.querySelector('.verify-camera-frame') : null;
    var preview = frame ? frame.querySelector('.verify-preview-image') : null;
    if (!preview || !file) return;
    if (state.previewUrls[inputId]) URL.revokeObjectURL(state.previewUrls[inputId]);
    state.previewUrls[inputId] = URL.createObjectURL(file);
    preview.src = state.previewUrls[inputId];
    frame.classList.add('has-preview');
  }
  function clearAllFramePreviews() {
    ['idFrontInput','idBackInput','selfieInput'].forEach(clearFramePreview);
  }
  function nextStep(step) { return step === 'front' ? 'back' : (step === 'back' ? 'selfie' : 'selfie'); }
  function setInputFile(input, blob, filename) {
    var file = new File([blob], filename, { type: blob.type || 'image/jpeg' });
    state.cameraCaptureInput = input ? input.id : null;
    replaceInputFile(input, file, true);
    return file;
  }
  function replaceInputFile(input, file, dispatchChange) {
    var transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    if (dispatchChange) input.dispatchEvent(new Event('change', { bubbles:true }));
  }
  function optimizedIdFileName(step) {
    return (step === 'back' ? 'id-back' : 'id-front') + '-scan.jpg';
  }
  function optimizeIdImageFile(file, step, cropToGuide) {
    return new Promise(function (resolve) {
      if (!file || !file.type || !file.type.startsWith('image/')) return resolve(file);
      var img = new Image();
      var objectUrl = URL.createObjectURL(file);
      img.onload = function () {
        var sourceW = img.width;
        var sourceH = img.height;
        var sx = 0;
        var sy = 0;
        var sw = sourceW;
        var sh = sourceH;
        var cardRatio = 1.586;

        if (cropToGuide && sourceW > 0 && sourceH > 0) {
          sw = sourceW * 0.92;
          sh = sw / cardRatio;
          var maxGuideH = sourceH * 0.82;
          if (sh > maxGuideH) {
            sh = maxGuideH;
            sw = sh * cardRatio;
          }
          sx = Math.max(0, (sourceW - sw) / 2);
          sy = Math.max(0, (sourceH - sh) / 2);
        }

        var targetW = Math.min(1100, Math.max(820, Math.round(sw)));
        if (sw < 820) targetW = Math.round(sw);
        var targetH = Math.max(1, Math.round(sh * (targetW / sw)));
        var canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        var ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, sx, sy, sw, sh, 0, 0, targetW, targetH);
        try {
          var imageData = ctx.getImageData(0, 0, targetW, targetH);
          var data = imageData.data;
          var contrast = 1.08;
          var brighten = 4;
          for (var i = 0; i < data.length; i += 4) {
            data[i] = Math.max(0, Math.min(255, ((data[i] - 128) * contrast) + 128 + brighten));
            data[i + 1] = Math.max(0, Math.min(255, ((data[i + 1] - 128) * contrast) + 128 + brighten));
            data[i + 2] = Math.max(0, Math.min(255, ((data[i + 2] - 128) * contrast) + 128 + brighten));
          }
          ctx.putImageData(imageData, 0, 0);
        } catch (e) {}
        URL.revokeObjectURL(objectUrl);
        canvas.toBlob(function (blob) {
          if (!blob) return resolve(file);
          resolve(new File([blob], optimizedIdFileName(step), { type:'image/jpeg', lastModified:Date.now() }));
        }, 'image/jpeg', 0.88);
      };
      img.onerror = function () { URL.revokeObjectURL(objectUrl); resolve(file); };
      img.src = objectUrl;
    });
  }
  function imageMetrics(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var objectUrl = URL.createObjectURL(file);
      img.onload = function () {
        var canvas = document.createElement('canvas');
        var w = Math.min(360, img.width);
        var h = Math.max(1, Math.round(img.height * (w / img.width)));
        canvas.width = w; canvas.height = h;
        var ctx = canvas.getContext('2d', { willReadFrequently:true });
        ctx.drawImage(img, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var total = 0;
        var totalSq = 0;
        var sharp = 0;
        var samples = 0;
        for (var y = 1; y < h - 1; y += 3) {
          for (var x = 1; x < w - 1; x += 3) {
            var idx = (y * w + x) * 4;
            var gray = (data[idx] + data[idx + 1] + data[idx + 2]) / 3;
            total += gray; totalSq += gray * gray; samples++;
            var right = ((y * w + (x + 1)) * 4);
            var down = (((y + 1) * w + x) * 4);
            var gRight = (data[right] + data[right + 1] + data[right + 2]) / 3;
            var gDown = (data[down] + data[down + 1] + data[down + 2]) / 3;
            sharp += Math.abs(gray - gRight) + Math.abs(gray - gDown);
          }
        }
        var brightness = total / Math.max(1, samples);
        var variance = (totalSq / Math.max(1, samples)) - (brightness * brightness);
        var edgeDensity = 0;
        var edgeSamples = 0;
        for (var ey = 1; ey < h - 1; ey += 2) {
          for (var ex = 1; ex < w - 1; ex += 2) {
            var eIdx = (ey * w + ex) * 4;
            var eGray = (data[eIdx] + data[eIdx + 1] + data[eIdx + 2]) / 3;
            var eRightIdx = (ey * w + (ex + 1)) * 4;
            var eDownIdx = ((ey + 1) * w + ex) * 4;
            var eRight = (data[eRightIdx] + data[eRightIdx + 1] + data[eRightIdx + 2]) / 3;
            var eDown = (data[eDownIdx] + data[eDownIdx + 1] + data[eDownIdx + 2]) / 3;
            if (Math.abs(eGray - eRight) > 18 || Math.abs(eGray - eDown) > 18) edgeDensity++;
            edgeSamples++;
          }
        }
        edgeDensity = edgeDensity / Math.max(1, edgeSamples);
        URL.revokeObjectURL(objectUrl);
        resolve({ width: img.width, height: img.height, brightness: brightness, contrast: Math.sqrt(Math.max(0, variance)), sharpness: sharp / Math.max(1, samples), edgeDensity: edgeDensity, hash: differenceHash(img), image: img });
      };
      img.onerror = function () { URL.revokeObjectURL(objectUrl); reject(); };
      img.src = objectUrl;
    });
  }
  function differenceHash(img) {
    var width = 16;
    var height = 16;
    var canvas = document.createElement('canvas');
    canvas.width = width; canvas.height = height;
    var ctx = canvas.getContext('2d', { willReadFrequently:true });
    ctx.drawImage(img, 0, 0, width, height);
    var data = ctx.getImageData(0, 0, width, height).data;
    var grays = [];
    for (var i = 0; i < data.length; i += 4) {
      grays.push((data[i] + data[i + 1] + data[i + 2]) / 3);
    }
    var bits = [];
    for (var y = 0; y < height; y++) {
      for (var x = 0; x < width - 1; x++) {
        var left = grays[(y * width) + x];
        var right = grays[(y * width) + x + 1];
        bits.push(left > right ? '1' : '0');
      }
    }
    return bits.join('');
  }
  function hashDistance(a, b) {
    if (!a || !b || a.length !== b.length) return Number.POSITIVE_INFINITY;
    var distance = 0;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) distance++;
    return distance;
  }
  function hashInputForStep(step) {
    return document.getElementById(step === 'front' ? 'idFrontHashInput' : 'idBackHashInput');
  }
  function setIdHash(step, hash) {
    var input = hashInputForStep(step);
    if (input) input.value = hash || '';
  }
  function scoreMetrics(metrics, kind) {
    if (kind !== 'selfie') {
      if (metrics.width < 260 || metrics.height < 160) return { ok:false, score:62, message:'Move the ID a little closer inside the guide.' };
      if (metrics.brightness < 6) return { ok:false, score:68, message:'Add a little light so the ID can be read.' };
      if (metrics.contrast < 8 || metrics.edgeDensity < 0.03) return { ok:false, score:45, message:'Place the actual ID inside the guide. Random background cannot be accepted.' };
      if (kind === 'back' && (metrics.contrast < 9 || metrics.edgeDensity < 0.035)) return { ok:false, score:42, message:'Back ID must show real ID details. Retake the actual back side closer and clearer.' };
      return { ok:true, score:96, message:'ID image is readable.' };
    }
    if (metrics.width < 220 || metrics.height < 220) return { ok:false, score:58, message:'Move a bit closer and keep your face inside the oval.' };
    if (metrics.brightness < 8) return { ok:false, score:64, message:'Too dark. Add a little light.' };
    if (metrics.contrast < 2 || metrics.sharpness < 0.8) return { ok:false, score:78, message:'Hold steady for a moment.' };
    return { ok:true, score:96, message:'Face photo looks clear.' };
  }
  async function validateQuality(file, kind) {
    var metrics = await imageMetrics(file);
    var quality = scoreMetrics(metrics, kind);
    if (!quality.ok) return quality.message;
    if (kind === 'selfie' && 'FaceDetector' in window) {
      try {
        var detector = new FaceDetector({ fastMode:true, maxDetectedFaces:2 });
        var faces = await detector.detect(metrics.image);
        if (!faces.length) return 'No face detected. Center your face and retake the selfie.';
        if (faces.length > 1) return 'More than one face detected. Take the selfie alone.';
      } catch (e) {}
    }
    return null;
  }
  function resetFaceCompare() {
    state.faceCompare = { lastAt:0, inFlight:false, status:null, score:null, message:null };
    state.selfieServerMatched = false;
    state.selfieMatchedResult = null;
  }
  function faceCompareMessage(result) {
    if (!result || !result.status) return 'Comparing your selfie with the ID...';
    var scoreText = typeof result.score === 'number' ? ' (' + Math.round(result.score * 100) + '%)' : '';
    if (result.status === 'match') return 'Face matched with ID' + scoreText + '. Hold steady.';
    if (result.status === 'mismatch') return result.message || 'Face does not match the ID. Keep scanning with the correct person.';
    return result.message || 'Face must clearly match the ID before you can submit.';
  }
  async function compareFaceFrame(selfieFile, force) {
    var frontInput = inputForStep('front');
    var frontFile = state.frontFile || (frontInput && frontInput.files && frontInput.files[0] ? frontInput.files[0] : null);
    if (!faceUrl || !frontFile || !selfieFile) {
      return { status:'needs_review', score:null, message:'Front ID is not ready for live face comparison yet.' };
    }
    var now = Date.now();
    if (!force && state.faceCompare.inFlight) return state.faceCompare;
    if (!force && state.faceCompare.status && now - state.faceCompare.lastAt < 1800) return state.faceCompare;

    state.faceCompare.inFlight = true;
    var body = new FormData();
    body.append('_token', token);
    body.append('id_front', frontFile);
    body.append('selfie', selfieFile, 'live-selfie.jpg');
    try {
      var response = await fetch(faceUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
      var data = response.ok ? await response.json() : { status:'needs_review', message:'Face check is not ready. Keep your face centered and try again.' };
      state.faceCompare = {
        lastAt:Date.now(),
        inFlight:false,
        status:data.status || 'needs_review',
        score:typeof data.score === 'number' ? data.score : null,
        message:data.message || null,
        can_continue:data.can_continue !== false
      };
      return state.faceCompare;
    } catch (e) {
      state.faceCompare = { lastAt:Date.now(), inFlight:false, status:'needs_review', score:null, message:'Face check is not ready. Keep your face centered and try again.', can_continue:true };
      return state.faceCompare;
    }
  }
  async function captureFromStage(input, stage, auto) {
    var video = stage ? stage.querySelector('video') : null;
    var canvas = stage ? stage.querySelector('canvas') : null;
    if (!input || !video || !canvas || !video.videoWidth) {
      if (input) setStatus(input.id, 'Open the camera first or upload a picture.', 'text-danger');
      return;
    }
    if (state.processing) return;

    state.processing = true;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(async function (blob) {
      if (!blob) { state.processing = false; return; }
      if (input.id === 'selfieInput') {
        setLiveness('Comparing with ID photo...', 'Hold steady. This should only take a moment.');
        setLive(stage, 'Comparing with ID photo...', 86, true);
        var faceResult = await compareFaceFrame(blob, true);
        if (faceResult.status === 'mismatch') {
          state.processing = false;
          state.stableFrames = 0;
          state.selfieServerMatched = false;
          state.selfieMatchedResult = null;
          var message = faceResult.message || 'Face does not match the ID. Retake the selfie or retake the front ID.';
          setStatus(input.id, message, 'text-danger');
          setLiveness('Face verification did not pass.', message);
          setLive(stage, faceCompareMessage(faceResult), 38, true);
          stopCamera();
          return;
        }
        state.selfieServerMatched = true;
        state.selfieMatchedResult = faceResult;
      }
      setLive(stage, input.id === 'selfieInput' ? 'Face matched. Saving selfie...' : (auto ? 'Clear image captured automatically.' : 'Captured. Checking image...'), 96, true);
      setInputFile(input, blob, input.id + '.jpg');
    }, 'image/jpeg', 0.94);
  }
  async function scanFront(file) {
    var selected = idType.value;
    if (!selected) return { ok:false, message:'Select an ID type first.' };
    var body = new FormData();
    body.append('_token', token);
    body.append('id_type', selected);
    body.append('id_front', file);
    var response = await fetch(scanUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
    if (!response.ok) {
      var errorData = null;
      try { errorData = await response.json(); } catch (e) {}
      var firstError = errorData && errorData.errors ? Object.values(errorData.errors).flat()[0] : null;
      return { ok:false, message:firstError || (errorData && errorData.message) || ('ID scan could not start. Server returned HTTP ' + response.status + '. Please try again.') };
    }
    return await response.json();
  }
  async function scanBack(file, backHash) {
    var selected = idType.value;
    var frontFile = state.frontFile || (inputForStep('front') && inputForStep('front').files && inputForStep('front').files[0] ? inputForStep('front').files[0] : null);
    var frontHash = document.getElementById('idFrontHashInput')?.value || '';
    if (!selected) return { ok:false, message:'Select an ID type first.' };
    if (!frontFile || !frontHash) return { ok:false, message:'Please scan the front ID first before scanning the back.' };
    var body = new FormData();
    body.append('_token', token);
    body.append('id_type', selected);
    body.append('id_front', frontFile);
    body.append('id_back', file);
    if (frontHash) body.append('id_front_hash', frontHash);
    if (backHash) body.append('id_back_hash', backHash);
    var response = await fetch(backScanUrl, { method:'POST', body:body, headers:{ 'Accept':'application/json' } });
    if (!response.ok) {
      var errorData = null;
      try { errorData = await response.json(); } catch (e) {}
      var firstError = errorData && errorData.errors ? Object.values(errorData.errors).flat()[0] : null;
      return { ok:false, message:firstError || (errorData && errorData.message) || (response.status === 504 ? 'Back ID check timed out. Please tap Capture Now again; the next scan uses a faster side check.' : ('Back ID scan could not start. Server returned HTTP ' + response.status + '. Please try again.')) };
    }
    return await response.json();
  }
  async function handleFile(input) {
    var file = input.files && input.files[0] ? input.files[0] : null;
    var step = inputStep(input);
    if (file && !stepAllowed(step)) {
      input.value = '';
      setStatus(input.id, step === 'back' ? 'Complete the front ID first.' : 'Complete the required ID steps first.', 'text-warning');
      updateSubmit();
      return;
    }
    var fromCamera = state.cameraCaptureInput === input.id;
    state.cameraCaptureInput = null;

    if (false && state.isMobileDevice && step !== 'selfie' && file && !fromCamera) {
      input.value = '';
      state[step] = false;
      setStatus(input.id, 'Mobile verification uses camera capture or photo upload for ID. Use Open Camera if needed.', 'text-danger');
      updateSubmit();
      return;
    }
    state[step] = false;
    clearFramePreview(input.id);
    if (step === 'front') { state.frontFile = null; resetFaceCompare(); }
    if (step === 'back') state.backFile = null;
    if (step === 'selfie') { state.selfieFile = null; if (!fromCamera) { state.selfieServerMatched = false; state.selfieMatchedResult = null; } }
    updateSubmit();
    if (!file) return;

    setStatus(input.id, 'Checking image...', 'text-warning');
    try {
      var metrics = await imageMetrics(file);
      var quality = scoreMetrics(metrics, step);
      var qualityError = quality.ok ? null : quality.message;
      if (qualityError) {
        input.value = '';
        state.processing = false;
        setStatus(input.id, qualityError, 'text-danger');
        setLive(document.querySelector('[data-step="' + step + '"]'), qualityError, 35);
        return;
      }
      if (step !== 'selfie') {
        setStatus(input.id, 'Preparing a faster ID scan image...', 'text-warning');
        var optimizedFile = await optimizeIdImageFile(file, step, fromCamera);
        if (optimizedFile && optimizedFile !== file) {
          file = optimizedFile;
          replaceInputFile(input, file, false);
          metrics = await imageMetrics(file);
        }
      }
      if (step === 'front') {
        setStatus(input.id, 'Scanning front ID and checking selected ID type...', 'text-warning');
        var result = await scanFront(file);
        if (!result.ok) {
          input.value = '';
          setIdHash('front', '');
          state.processing = false;
          setStatus(input.id, result.message || 'The scanner must confirm the selected ID type before continuing.', 'text-danger');
          setLive(document.querySelector('[data-step="front"]'), result.message || 'Retake a clearer front ID photo with the selected ID type.', 40, true);
          return;
        }
        state.front = true;
        state.frontFile = file;
        setFramePreview(input.id, file);
        setIdTypeLocked(true);
        setIdHash('front', metrics.hash);
        setStatus(input.id, result.message || 'Front ID matched.', 'text-success');
        if (selectedRequiresBack()) {
          state.back = false;
          setLive(document.querySelector('[data-step="front"]'), 'Front ID verified. Continue to the back side.', 100, true);
          showStep('back');
        } else {
          state.back = true;
          state.backFile = null;
          setIdHash('back', '');
          setStatus('idBackInput', 'Back ID is not required for this ID type.', 'text-success');
          setLive(document.querySelector('[data-step="front"]'), 'Front ID verified. Continue to face verification.', 100, true);
          showStep('selfie');
        }
      } else if (step === 'back') {
        var frontHash = document.getElementById('idFrontHashInput')?.value || '';
        if (!frontHash || !metrics.hash || frontHash.length !== metrics.hash.length) {
          input.value = '';
          setIdHash('back', '');
          state.processing = false;
          setStatus(input.id, 'Please tap Reset and scan the front ID again before scanning the back.', 'text-danger');
          setLive(document.querySelector('[data-step="back"]'), 'Scanner needs a fresh front scan before checking the back side.', 35, true);
          return;
        }
        if (hashDistance(frontHash, metrics.hash) <= BACK_SIDE_SAME_HASH_DISTANCE) {
          input.value = '';
          setIdHash('back', '');
          state.processing = false;
          setStatus(input.id, 'This still looks like the front side. Flip the ID and scan the actual back side.', 'text-danger');
          setLive(document.querySelector('[data-step="back"]'), 'Please flip the ID. The scanner must see the actual back side.', 38, true);
          return;
        }
        setStatus(input.id, 'Checking that this is the back side of the ID...', 'text-warning');
        setLive(document.querySelector('[data-step="back"]'), 'Checking that this is the back side...', 72, true);
        var backResult = await scanBack(file, metrics.hash);
        if (!backResult.ok) {
          input.value = '';
          setIdHash('back', '');
          state.processing = false;
          setStatus(input.id, backResult.message || 'This still looks like the front side. Flip the ID and scan the actual back side.', 'text-danger');
          setLive(document.querySelector('[data-step="back"]'), backResult.message || 'Please flip the ID. The scanner must see the actual back side.', 38, true);
          return;
        }
        state.back = true;
        state.backFile = file;
        setFramePreview(input.id, file);
        setIdHash('back', metrics.hash);
        setStatus(input.id, 'Back ID captured. Continue to face verification.', 'text-success');
        setLive(document.querySelector('[data-step="back"]'), 'Back ID accepted.', 100, true);
        showStep('selfie');
      } else {
        var uploadedFaceResult = fromCamera && state.selfieServerMatched && state.selfieMatchedResult
          ? state.selfieMatchedResult
          : await compareFaceFrame(file, true);
        if (uploadedFaceResult.status === 'mismatch') {
          input.value = '';
          state.processing = false;
          state.selfieServerMatched = false;
          state.selfieMatchedResult = null;
          setStatus(input.id, uploadedFaceResult.message || 'Face does not match the ID. Please retake with the correct person.', 'text-danger');
          setLive(document.querySelector('[data-step="selfie"]'), faceCompareMessage(uploadedFaceResult), 38, true);
          return;
        }
        document.getElementById('livenessResultInput').value = 'not_required';
        document.getElementById('livenessMethodInput').value = 'selfie_image_compare';
        document.getElementById('livenessChallengeInput').value = 'none';
        state.selfie = true;
        state.selfieFile = file;
        setFramePreview(input.id, file);
        document.getElementById('selfieCaptureSourceInput').value = fromCamera ? 'camera_capture' : 'upload';
        setStatus(input.id, uploadedFaceResult.status === 'match' ? 'Face matched the ID. You may submit for review.' : 'Face needs admin review. You may submit for manual review.', uploadedFaceResult.status === 'match' ? 'text-success' : 'text-warning');
        setLive(document.querySelector('[data-step="selfie"]'), faceCompareMessage(uploadedFaceResult), 100, true);
        stopCamera();
      }
      state.processing = false;
      state.stableFrames = 0;
      updateSubmit();
    } catch (e) {
      input.value = '';
      state.processing = false;
      setStatus(input.id, 'Could not check this image. Retake or upload a clearer file.', 'text-danger');
    }
  }
  async function monitorCamera(input, stage, loopId) {
    var video = stage.querySelector('video');
    var canvas = stage.querySelector('canvas');
    var step = inputStep(input);
    if (!video || !canvas || loopId !== state.activeLoop) return;
    if (!video.videoWidth || state.processing) {
      requestAnimationFrame(function () { if (input.id === 'selfieInput') monitorCamera(input, stage, loopId); });
      return;
    }
    var scale = Math.min(1, 360 / video.videoWidth);
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    var ctx = canvas.getContext('2d', { willReadFrequently:true });
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(async function (blob) {
      if (!blob || loopId !== state.activeLoop) return;
      try {
        var metrics = await imageMetrics(blob);
        metrics.width = video.videoWidth;
        metrics.height = video.videoHeight;
        var quality = scoreMetrics(metrics, step);
        var elapsed = step === 'selfie' && state.faceScanStartedAt ? Date.now() - state.faceScanStartedAt : 0;
        if (step === 'selfie' && elapsed > 15000) {
          state.processing = false;
          state.stableFrames = 0;
          setStatus(input.id, 'Face scan took too long. Retake the selfie in brighter light and keep your face inside the oval.', 'text-danger');
          setLiveness('Face scan timed out.', 'Retake with a brighter background and keep your face centered.');
          setLive(stage, 'Face scan timed out. Tap Open Camera to try again.', 35, true);
          stopCamera();
          return;
        }
        var livenessOk = step === 'selfie' && quality.ok ? await checkLiveness(stage) : true;
        var liveMessage = quality.ok ? (livenessOk ? 'Face ready. Capturing best selfie now...' : 'Align your face and follow the prompt.') : quality.message;
        var liveScore = livenessOk ? quality.score : 65;
        setLive(stage, liveMessage, liveScore);
        state.stableFrames = quality.ok && livenessOk ? state.stableFrames + 1 : 0;
        var requiredFrames = step === 'selfie' ? 2 : 1;
        if (state.stableFrames >= requiredFrames && !state.processing) {
          captureFromStage(input, stage, true);
          return;
        }
      } catch (e) {}
      setTimeout(function () { if (input.id === 'selfieInput') monitorCamera(input, stage, loopId); }, 140);
    }, 'image/jpeg', 0.82);
  }

  document.querySelectorAll('[data-upload-trigger]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.uploadTrigger);
      var step = input ? inputStep(input) : state.currentStep;
      if (!stepAllowed(step)) {
        if (input) setStatus(input.id, step === 'back' ? 'Complete the front ID first.' : 'Complete the required ID steps first.', 'text-warning');
        return;
      }
      if (input) input.click();
    });
  });
  document.querySelectorAll('.verify-file-input').forEach(function (input) {
    input.addEventListener('change', function () { handleFile(input); });
  });
  async function openSelfieCamera(baseVideo) {
    try {
      return await navigator.mediaDevices.getUserMedia({ video:Object.assign({}, baseVideo, { facingMode:{ exact:'user' } }), audio:false });
    } catch (exactError) {
      try {
        return await navigator.mediaDevices.getUserMedia({ video:Object.assign({}, baseVideo, { facingMode:'user' }), audio:false });
      } catch (softError) {
        if (!navigator.mediaDevices.enumerateDevices) throw softError;
        var devices = await navigator.mediaDevices.enumerateDevices();
        var frontCamera = devices.find(function (device) {
          return device.kind === 'videoinput' && /front|user|selfie|face/i.test(device.label || '');
        });
        if (!frontCamera) throw softError;
        return await navigator.mediaDevices.getUserMedia({ video:Object.assign({}, baseVideo, { deviceId:{ exact:frontCamera.deviceId } }), audio:false });
      }
    }
  }
  function isRearCameraStream(stream) {
    var track = stream && stream.getVideoTracks ? stream.getVideoTracks()[0] : null;
    var settings = track && track.getSettings ? track.getSettings() : {};
    return settings.facingMode === 'environment';
  }
  async function startCameraForStep(step) {
    var targetStep = step || state.currentStep;
    var input = inputForStep(targetStep);
    var stage = document.querySelector('[data-step="' + targetStep + '"]');
    var button = stage ? stage.querySelector('[data-camera-start]') : null;
    var video = stage ? stage.querySelector('video') : null;
    if (!input || !stage || !video || state.autoStarting) return;
    if (!stepAllowed(targetStep)) {
      setStatus(input.id, targetStep === 'back' ? 'Complete the front ID first.' : 'Complete the required ID steps first.', 'text-warning');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus(input.id, 'Camera is not supported here. Upload a clear picture instead.', 'text-danger');
      setLive(stage, 'Camera is not supported here. Upload a clear picture instead.', 35, true);
      return;
    }
    if (!idType.value) {
      setStatus(input.id, 'Select an ID type before scanning.', 'text-danger');
      return;
    }
    state.autoStarting = true;
    stopCamera();
    state.activeInput = input;
    state.activeLoop += 1;
    var loopId = state.activeLoop;
    try {
      if (input.id === 'selfieInput') {
        resetFaceCompare();
        document.getElementById('livenessChallengeInput').value = 'none';
        document.getElementById('livenessResultInput').value = 'not_required';
        document.getElementById('livenessMethodInput').value = 'selfie_image_compare';
      }
      clearFramePreview(input.id);
      setStatus(input.id, 'Opening camera...', 'text-warning');
      setLive(stage, 'Opening camera...', 30, true);
      var baseVideo = input.id === 'selfieInput'
        ? { width:{ ideal:720 }, height:{ ideal:1280 }, aspectRatio:{ ideal:0.75 } }
        : { width:{ ideal:1280 }, height:{ ideal:720 }, aspectRatio:{ ideal:1.586 } };
      if (input.id === 'selfieInput') {
        state.stream = await openSelfieCamera(baseVideo);
        if (isRearCameraStream(state.stream)) {
          state.stream.getTracks().forEach(function (track) { track.stop(); });
          state.stream = null;
          throw new Error('front_camera_required');
        }
      } else {
        state.stream = await navigator.mediaDevices.getUserMedia({ video:Object.assign({}, baseVideo, { facingMode:button?.dataset.facing || 'environment' }), audio:false });
      }
      video.srcObject = state.stream;
      await video.play();
      prepareTorchForStage(stage, input);
      setStatus(input.id, input.id === 'selfieInput' ? 'Camera ready. Center your face, then tap Capture Now.' : 'Camera ready. Align inside the guide, then tap Capture Now.', 'text-warning');
      setLive(stage, input.id === 'selfieInput' ? (state.torchTrack ? 'Center your face, then tap Capture Now. Use Flashlight if needed.' : 'Center your face, then tap Capture Now.') : (state.torchTrack ? 'Align the ID inside the guide. Use Flashlight if needed.' : 'Align the landscape card inside the guide.'), 58, true);
    } catch (e) {
      setStatus(input.id, input.id === 'selfieInput' ? 'Front camera unavailable. Upload a clear selfie instead.' : 'Camera unavailable. Upload a clear picture instead.', 'text-danger');
      setLive(stage, input.id === 'selfieInput' ? 'Camera unavailable. Upload a clear selfie instead.' : 'Camera unavailable. Upload a clear picture instead.', 35, true);
    } finally {
      state.autoStarting = false;
    }
  }
  var faceReadyButton = document.getElementById('faceReadyButton');
  if (faceReadyButton) {
    faceReadyButton.addEventListener('click', function () {
      if (!stepAllowed('selfie')) {
        setStatus('selfieInput', 'Complete the required ID steps first.', 'text-warning');
        return;
      }
      state.faceReady = true;
      setFaceInstructionVisible(false);
      var stage = document.querySelector('[data-step="selfie"]');
      setLiveness('Face scan ready.', 'Center your face in the oval and follow the live prompt.');
      setLive(stage, 'Opening front camera for face verification...', 58, true);
      setStatus('selfieInput', 'Opening front camera for face verification...', 'text-warning');
      if (!state.stream && !state.selfie) startCameraForStep('selfie');
    });
  }
  var faceInstructionHelp = document.getElementById('faceInstructionHelp');
  if (faceInstructionHelp) {
    faceInstructionHelp.addEventListener('click', function () {
      setFaceInstructionVisible(true);
      setLiveness('Review the face instructions.', 'Tap I\'m Ready to return to live face scanning.');
      setLive(document.querySelector('[data-step="selfie"]'), 'Review instructions, then tap I\'m Ready.', 58, true);
    });
  }
  document.querySelectorAll('[data-torch-toggle]').forEach(function (button) {
    button.addEventListener('click', async function () {
      if (!state.torchTrack) {
        var stage = button.closest('[data-step]');
        setLive(stage, 'Flashlight is not supported on this camera.', 45, true);
        return;
      }
      var ok = await setTorch(!state.torchOn);
      var active = activeStage();
      if (!ok) setLive(active, 'Flashlight is not supported on this camera.', 45, true);
      else setLive(active, state.torchOn ? 'Flashlight on. Keep the subject inside the guide.' : 'Flashlight off.', state.torchOn ? 72 : 58, true);
    });
  });
  document.querySelectorAll('[data-camera-start]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.cameraStart);
      startCameraForStep(input ? inputStep(input) : state.currentStep);
    });
  });
  document.querySelectorAll('[data-camera-capture]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.cameraCapture);
      var stage = button.closest('[data-step]');
      captureFromStage(input, stage, false);
    });
  });
  if (idType) {
    idType.addEventListener('change', function () {
      if (state.lockedIdType || hasAnyScan()) {
        idType.value = state.lockedIdType || idType.value;
        setStatus('idFrontInput', 'Use Reset Scan before changing the ID type.', 'text-warning');
        return;
      }
      state.front = false; state.back = false; state.selfie = false; state.frontFile = null; state.backFile = null; state.selfieFile = null; state.backRequired = selectedRequiresBack(); resetLiveness(); resetFaceCompare(); resetFaceReady();
      ['idFrontInput','idBackInput','selfieInput'].forEach(function (id) {
        var input = document.getElementById(id);
        if (input) input.value = '';
      });
      setStatus('idFrontInput', 'Waiting for front ID scan.');
      setStatus('idBackInput', selectedRequiresBack() ? 'Waiting for back ID scan.' : 'Back ID is not required for this ID type.');
      setIdHash('front', '');
      setIdHash('back', '');
      setStatus('selfieInput', 'Waiting for face verification.');
      document.getElementById('idUploadSummary').innerHTML = '';
      clearAllFramePreviews();
      resetLiveness(); resetFaceCompare(); showStep('front'); updateSubmit(); stopCamera();
    });
  }
  function resetScanFlow(confirmFirst) {
    if (confirmFirst && hasAnyScan() && !window.confirm('Reset all scanned ID and face verification data?')) return;
    stopCamera();
    state.front = false; state.back = false; state.selfie = false;
    state.frontFile = null; state.backFile = null; state.selfieFile = null;
    state.backRequired = selectedRequiresBack();
    state.stableFrames = 0; state.processing = false; state.lastLive = {}; state.scanStarted = false; state.faceReady = false; state.faceScanStartedAt = 0; state.selfieServerMatched = false; state.selfieMatchedResult = null;
    setIdTypeLocked(false);
    ['idFrontInput','idBackInput','selfieInput'].forEach(function (id) {
      var input = document.getElementById(id);
      if (input) input.value = '';
    });
    setIdHash('front', '');
    setIdHash('back', '');
    setStatus('idFrontInput', 'Waiting for front ID scan.');
    setStatus('idBackInput', selectedRequiresBack() ? 'Waiting for back ID scan.' : 'Back ID is not required for this ID type.');
    setStatus('selfieInput', 'Waiting for face verification.');
    document.getElementById('selfieCaptureSourceInput').value = 'not_started';
    document.getElementById('idUploadSummary').innerHTML = '';
    clearAllFramePreviews();
    resetLiveness(); resetFaceCompare(); showStep('front'); applyDeviceRules(); updateSubmit();
  }
  if (resetButton) resetButton.addEventListener('click', function () { resetScanFlow(true); });
  if (launchButton) launchButton.addEventListener('click', function () { state.scanStarted = true; updateSubmit(); });
  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function () {
      stopCamera();
      modalEl.classList.remove('is-selfie-step');
    });
    modalEl.addEventListener('shown.bs.modal', function () {
      var input = inputForStep(state.currentStep);
      var stage = activeStage();
      if (input && input.files.length) {
        setLive(stage, 'Step already has an accepted image.', 100, true);
      } else {

        setLive(stage, manualStartMessage(state.currentStep), state.currentStep === 'selfie' ? 62 : 45, true);
        if (input) setStatus(input.id, manualStartMessage(state.currentStep), 'text-warning');
      }
    });
  }
  form.addEventListener('submit', function (event) {
    if (idType && idType.disabled) idType.disabled = false;
    if (!['upload','camera_capture'].includes(document.getElementById('selfieCaptureSourceInput').value)) {
      event.preventDefault();
      document.getElementById('idUploadSummary').innerHTML = '<div class="alert alert-danger mb-0">Selfie verification must be completed before submit.</div>';
      if (idType && state.lockedIdType) idType.disabled = true;
      return;
    }
    if (!(state.front && (state.backRequired ? state.back : true) && state.selfie && state.lockedIdType && state.lockedIdType === idType.value)) {
      event.preventDefault();
      document.getElementById('idUploadSummary').innerHTML = '<div class="alert alert-danger mb-0">Complete front ID, required back ID, and strict selfie face verification first.</div>';
      if (idType && state.lockedIdType) idType.disabled = true;
    }
  });
  applyDeviceRules(); resetLiveness(); showStep('front'); updateSubmit();
});
</script>
@endsection
