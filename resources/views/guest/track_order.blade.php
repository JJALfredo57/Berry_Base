@extends('layouts.app')
@section('content')
<div class="container-fluid py-4" style="padding-left:clamp(12px,3vw,32px);padding-right:clamp(12px,3vw,32px)">

  @if(session('msg'))
    <div class="alert alert-success border-0"><i class="bi bi-check-circle me-2"></i>{{ session('msg') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger border-0"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
  @endif
  <style>
    @keyframes depositShake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }
    .deposit-amount-input.is-invalid {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 .18rem rgba(220, 38, 38, .12) !important;
    }
    .deposit-error {
      display: none;
      color: #dc2626;
      font-size: .72rem;
      font-weight: 700;
      margin-top: .35rem;
    }
    .deposit-error.show {
      display: block;
      animation: depositShake .32s ease;
    }
    .track-payment-summary {
      background:#fff0f5;
      border:1px solid #fde2ef;
    }
    .track-payment-note {
      display:flex;
      align-items:flex-start;
      gap:.45rem;
      margin-top:.45rem;
      font-size:clamp(.72rem,1.5vw,.8rem);
      line-height:1.35;
      font-weight:700;
    }
    .track-payment-note i {
      flex:0 0 auto;
      margin-top:.05rem;
    }
    .track-payment-note.success { color:#166534; }
    .track-payment-note.warning { color:#9a3412; }
    .track-review-prompt {
      border:0;
      border-radius:18px;
      box-shadow:0 24px 70px rgba(15,23,42,.22);
      overflow:hidden;
    }
    .track-review-prompt .card-body {
      padding:clamp(18px,4vw,26px)!important;
    }
    .track-review-head {
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      margin-bottom:16px;
    }
    .track-review-kicker {
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      background:#fffbeb;
      color:#92400e;
      font-size:.72rem;
      font-weight:800;
      margin-bottom:8px;
    }
    .track-review-title {
      font-size:clamp(1rem,3vw,1.25rem);
      font-weight:900;
      color:#111827;
      margin:0;
      line-height:1.2;
    }
    .track-review-copy {
      color:#6b7280;
      font-size:clamp(.78rem,1.8vw,.9rem);
      line-height:1.45;
      margin:.35rem 0 0;
    }
    .track-review-actions {
      display:grid;
      grid-template-columns:1fr auto;
      gap:10px;
      align-items:center;
    }
    .track-paid-banner {
      display:flex;
      align-items:center;
      justify-content:center;
      gap:.5rem;
      padding:.75rem .9rem;
      border-radius:.65rem;
      background:#f0fdf4;
      border:1px solid #bbf7d0;
      color:#166534;
      font-size:clamp(.78rem,1.6vw,.86rem);
      font-weight:800;
      line-height:1.35;
      text-align:center;
    }
    .track-paid-banner i {
      color:#16a34a;
      flex:0 0 auto;
    }
    /* ── Chat bubbles ── */
    .chat-box-g{min-height:200px;max-height:380px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:6px;background:#f8f9fa}
    .msg-row-g{display:flex;gap:8px;align-items:flex-end}
    .msg-row-g.mine{flex-direction:row-reverse}
    .msg-av-g{width:28px;height:28px;border-radius:50%;background:#dee2e6;color:#666;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0;text-transform:uppercase}
    .msg-av-g.mine{background:var(--primary);color:#fff}
    .msg-grp-g{display:flex;flex-direction:column;max-width:72%;gap:2px}
    .msg-grp-g.mine{align-items:flex-end}
    .bbl-g{padding:9px 13px;border-radius:16px;font-size:.875rem;line-height:1.5;word-break:break-word}
    .bbl-g.theirs{background:#fff;color:#333;border-radius:4px 16px 16px 16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
    .bbl-g.mine{background:var(--primary);color:#fff;border-radius:16px 4px 16px 16px}
    .bbl-imgs-g{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;margin-top:6px;width:min(304px,100%);max-width:100%}
    .bbl-imgs-g.img-count-1{grid-template-columns:1fr;width:min(240px,100%)}
    .bbl-imgs-g.img-count-2{grid-template-columns:repeat(2,minmax(0,1fr));width:min(304px,100%)}
    .bbl-img-tile-g,.bbl-img-more-g{width:100%;aspect-ratio:1/1;border:0;border-radius:8px;cursor:zoom-in;display:block;min-width:0;overflow:hidden;padding:0;touch-action:manipulation;-webkit-tap-highlight-color:transparent}
    .bbl-img-tile-g{background:transparent}
    .bbl-img-tile-g img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
    .bbl-imgs-g.img-count-1 .bbl-img-tile-g{aspect-ratio:4/3}
    .bbl-img-more-g{background:#111827;color:#fff;position:relative;font-size:1.35rem;font-weight:900;display:flex;align-items:center;justify-content:center}
    .bbl-img-more-g:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.16),transparent 34%),linear-gradient(135deg,rgba(17,24,39,.94),rgba(31,41,55,.98))}
    .bbl-img-more-g span{position:relative;z-index:1;text-shadow:0 2px 10px rgba(0,0,0,.35)}
    .bbl-img-more-g:hover span{text-decoration:underline}
    .bbl-time-g{font-size:.65rem;color:#adb5bd;padding:0 2px}
    .bbl-time-g.mine{text-align:right}
    .sndr-lbl-g{font-size:.68rem;font-weight:600;color:#6c757d;padding:0 2px;margin-bottom:1px}
    .sndr-lbl-g.mine{text-align:right;color:var(--primary)}
    /* preview bar */
    .g-preview-bar{display:none;padding:10px 14px 6px;border-top:1px solid #f0f0f0;background:#fafafa;max-height:140px;overflow-y:auto}
    .g-img-cards{display:flex;gap:8px;flex-wrap:wrap}
    .g-img-card{position:relative;background:#fff;border:1.5px solid #e9ecef;border-radius:10px;overflow:hidden;width:96px;flex-shrink:0}
    .g-img-card img{width:96px;height:72px;object-fit:cover;display:block}
    .g-img-card-info{padding:3px 5px;font-size:.58rem;line-height:1.3;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .g-img-card-size{font-size:.58rem;color:#16a34a;font-weight:600}
    .g-img-card-rm{position:absolute;top:3px;right:3px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:.55rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1}
    .g-compressing{opacity:.5;pointer-events:none}
    /* compose */
    .g-compose-wrap{padding:10px 14px 12px;border-top:1px solid #e9ecef;background:#fff}
    .g-compose-row{display:flex;gap:8px;align-items:flex-end}
    .g-compose-box{flex:1;border:1.5px solid #e9ecef;border-radius:14px;padding:9px 12px;font-size:.9rem;outline:none;max-height:120px;overflow-y:auto;line-height:1.4;color:#333;transition:border-color .2s;min-height:40px;white-space:pre-wrap;word-break:break-word}
    .g-compose-box:focus{border-color:var(--primary)}
    .g-compose-box:empty:before{content:attr(data-placeholder);color:#adb5bd;pointer-events:none}
    .g-attach-btn{width:38px;height:38px;border-radius:50%;border:1.5px solid #e9ecef;background:#fff;color:#6c757d;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .15s;flex-shrink:0}
    .g-attach-btn:hover,.g-attach-btn.active{border-color:var(--primary);color:var(--primary);background:#fce7f3}
    .g-send-btn{width:40px;height:40px;border-radius:50%;border:none;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.95rem;transition:opacity .15s;flex-shrink:0}
    .g-send-btn:disabled{opacity:.45;cursor:not-allowed}
    .track-action-panel{display:none}
    .track-action-panel.is-open{display:block;position:fixed!important;left:50%!important;top:50%!important;width:min(720px,calc(100vw - 28px));max-height:min(82vh,720px);overflow:auto;z-index:1062;transform:translate(-50%,-50%);animation:trackPanelIn .2s ease;overscroll-behavior:contain}
    #messagePanel.track-action-panel.is-open{display:flex;flex-direction:column;overflow:hidden}
    #messagePanel .chat-box-g{flex:1 1 auto;min-height:190px;max-height:none;overscroll-behavior:contain}
    #messagePanel .g-preview-bar{flex:0 0 auto;max-height:126px;overflow:hidden;background:linear-gradient(180deg,#fff,#fafafa)}
    #messagePanel .g-img-cards{flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;padding-bottom:4px;scrollbar-width:thin}
    #messagePanel .g-img-card{width:88px;flex:0 0 88px;border-radius:12px}
    #messagePanel .g-img-card img{width:88px;height:64px}
    #messagePanel .g-img-card-info{white-space:nowrap;padding:3px 5px;font-size:.56rem}
    #messagePanel .g-compose-wrap{flex:0 0 auto;box-shadow:0 -10px 24px rgba(15,23,42,.05)}
    #messagePanel .g-compose-row{min-width:0}
    #messagePanel .g-compose-box{min-width:0}
    #messagePanel #guestUploadSummary{margin-bottom:.4rem}
    #messagePanel #guestUploadSummary .cs-upload-summary{display:flex!important;flex-wrap:nowrap!important;gap:.38rem;width:100%;overflow-x:auto;overflow-y:hidden;margin-top:0;padding-bottom:2px;scrollbar-width:thin}
    #messagePanel #guestUploadSummary .cs-upload-pill{width:auto!important;flex:0 0 auto;max-width:min(260px,78vw);border-radius:999px!important;white-space:nowrap}
    #messagePanel #guestUploadSummary .cs-upload-pill span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    #messagePanel #guestUploadSummary .cs-upload-arrow{display:none}
    @keyframes trackPanelIn{from{opacity:0;transform:translate(-50%,-46%) scale(.97)}to{opacity:1;transform:translate(-50%,-50%) scale(1)}}
    .track-fab-wrap{position:fixed!important;right:18px!important;bottom:22px!important;z-index:1050;display:flex;flex-direction:column;align-items:flex-end;gap:10px}
    .track-fab-menu{display:flex;flex-direction:column;align-items:flex-end;gap:9px;pointer-events:none}
    .track-fab-wrap.is-open .track-fab-menu{pointer-events:auto}
    .track-fab-item{border:0;background:#fff;color:#111827;border-radius:999px;box-shadow:0 12px 30px rgba(15,23,42,.16);height:46px;min-width:46px;padding:0 14px 0 13px;display:flex;align-items:center;gap:9px;opacity:0;transform:translateY(12px) scale(.92);transition:opacity .18s ease,transform .18s ease,box-shadow .18s ease}
    .track-fab-wrap.is-open .track-fab-item{opacity:1;transform:translateY(0) scale(1)}
    .track-fab-item:nth-child(1){transition-delay:.04s}.track-fab-item:nth-child(2){transition-delay:.08s}.track-fab-item:nth-child(3){transition-delay:.12s}
    .track-fab-item:hover{box-shadow:0 16px 34px rgba(15,23,42,.22);transform:translateY(-2px) scale(1.02)}
    .track-fab-icon{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff0f6;color:var(--primary);flex-shrink:0}
    .track-fab-label{font-size:.78rem;font-weight:800;white-space:nowrap;max-width:0;opacity:0;overflow:hidden;transition:max-width .2s ease,opacity .16s ease}
    .track-fab-wrap.is-open .track-fab-item:hover .track-fab-label,.track-fab-wrap.is-open .track-fab-item:focus .track-fab-label{max-width:160px;opacity:1}
    .track-fab-main{width:58px;height:58px;border-radius:50%;border:0;background:var(--primary);color:#fff;box-shadow:0 18px 34px rgba(219,39,119,.32);display:flex;align-items:center;justify-content:center;font-size:1.3rem;transition:transform .18s ease;position:relative;overflow:visible}
    .track-fab-wrap.is-open .track-fab-main{transform:rotate(45deg)}
    .track-count-badge{position:absolute;right:-3px;top:-3px;background:#16a34a;color:#fff;border-radius:999px;min-width:18px;height:18px;padding:0 5px;font-size:.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #fff;line-height:1;pointer-events:none;transform:none!important}
    .track-fab-wrap.is-open .track-count-badge{display:none}
    .receipt-drawer-backdrop{position:fixed!important;inset:0!important;background:rgba(15,23,42,.32);z-index:1060;opacity:0;pointer-events:none;transition:opacity .2s ease;overscroll-behavior:none}
    .receipt-drawer-backdrop.is-open{opacity:1;pointer-events:auto}
    .receipt-drawer{position:fixed!important;left:50%!important;top:50%!important;width:min(460px,calc(100vw - 28px));max-height:min(82vh,720px);background:#fff;z-index:1061;border-radius:16px;box-shadow:0 24px 60px rgba(15,23,42,.24);transform:translate(-50%,-46%) scale(.96);opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;display:flex;flex-direction:column;overflow:hidden;overscroll-behavior:contain}
    .receipt-drawer.is-open{opacity:1;pointer-events:auto;transform:translate(-50%,-50%) scale(1)}
    .receipt-drawer-body{overflow:auto;padding:14px}
    .proof-view-btn{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:999px;padding:6px 12px;font-size:.76rem;font-weight:800;display:inline-flex;align-items:center;gap:6px;margin-top:8px;text-decoration:none}
    .proof-view-btn:hover{background:#dcfce7;color:#14532d}
    .proof-viewer{position:fixed;inset:0;background:#05070bcc;z-index:1090;display:none;flex-direction:column;overflow:hidden;touch-action:none}
    .proof-viewer.is-open{display:flex}
    .proof-viewer-bar{height:56px;padding:8px max(12px,env(safe-area-inset-left));display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;background:linear-gradient(180deg,rgba(0,0,0,.54),rgba(0,0,0,0));flex-shrink:0}
    .proof-viewer-title{min-width:0;font-size:.9rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .proof-icon-btn{width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.12);color:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;backdrop-filter:blur(10px)}
    .proof-icon-btn:active{transform:scale(.96)}
    .proof-stage{position:relative;flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:10px;overflow:hidden}
    .proof-stage img{max-width:100%;max-height:100%;object-fit:contain;transform:scale(var(--proof-scale,1));transition:transform .15s ease;will-change:transform;user-select:none;-webkit-user-drag:none}
    .proof-tools{height:64px;padding:8px max(12px,env(safe-area-inset-left));display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(0deg,rgba(0,0,0,.58),rgba(0,0,0,0));flex-shrink:0}
    .proof-zoom-pill{min-width:72px;text-align:center;color:#fff;font-size:.8rem;font-weight:800}
    .refund-receipt-viewer{position:fixed!important;inset:0!important;background:rgba(15,23,42,.68);z-index:1092;display:none;align-items:center;justify-content:center;padding:18px;overflow:hidden}
    .refund-receipt-viewer.is-open{display:flex}
    .refund-receipt-dialog{width:min(520px,100%);max-height:min(86vh,760px);background:#fff;border-radius:16px;box-shadow:0 24px 70px rgba(15,23,42,.3);display:flex;flex-direction:column;overflow:hidden}
    .refund-receipt-head{height:54px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #e5e7eb}
    .refund-receipt-title{font-weight:800;color:#111827;font-size:.95rem;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .refund-receipt-body{background:#0f172a;display:flex;align-items:center;justify-content:center;padding:12px;overflow:auto;min-height:220px}
    .refund-receipt-body img{display:block;max-width:100%;max-height:64vh;object-fit:contain;border-radius:10px;background:#fff}
    .refund-receipt-foot{padding:12px 14px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb;flex-wrap:wrap}
    .refund-download-btn.is-loading{pointer-events:none;opacity:.72}
    .track-head{position:relative;text-align:center;margin-bottom:1.5rem}
    .track-bell-btn{position:absolute;right:0;top:4px;width:48px;height:48px;border:0;border-radius:50%;background:#fff;color:var(--primary);box-shadow:0 12px 28px rgba(15,23,42,.14);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
    .track-bell-btn.has-unread i{animation:trackBellRing 1.3s ease-in-out infinite;transform-origin:50% 0}
    .track-bell-badge{position:absolute;right:-3px;top:-3px;min-width:20px;height:20px;border-radius:999px;background:#dc2626;color:#fff;font-size:.68rem;font-weight:900;display:none;align-items:center;justify-content:center;border:2px solid #fff;padding:0 5px}
    .track-bell-btn.has-unread .track-bell-badge{display:flex}
    @keyframes trackBellRing{0%,100%{transform:rotate(0)}12%{transform:rotate(14deg)}24%{transform:rotate(-12deg)}36%{transform:rotate(10deg)}48%{transform:rotate(-7deg)}60%{transform:rotate(4deg)}72%{transform:rotate(0)}}
    .track-notif-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.38);z-index:1080;display:none}
    .track-notif-backdrop.is-open{display:block}
    .track-notif-panel{position:fixed;right:18px;top:78px;width:min(390px,calc(100vw - 28px));max-height:min(620px,calc(100vh - 100px));z-index:1081;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.24);display:none;overflow:hidden}
    .track-notif-panel.is-open{display:flex;flex-direction:column;animation:trackNotifIn .18s ease}
    @keyframes trackNotifIn{from{opacity:0;transform:translateY(-8px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
    .track-notif-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid #f1f5f9}
    .track-notif-title{font-weight:900;color:#111827;font-size:.98rem}
    .track-notif-actions{display:flex;gap:6px}
    .track-notif-icon{width:34px;height:34px;border:0;border-radius:50%;background:#f8fafc;color:#111827;display:flex;align-items:center;justify-content:center}
    .track-notif-list{overflow:auto;padding:8px;display:flex;flex-direction:column;gap:8px}
    .track-notif-item{border:1px solid #edf2f7;background:#fff;border-radius:12px;padding:11px;text-align:left;display:block;width:100%;transition:background .15s,border-color .15s}
    .track-notif-item.unread{background:#fff7ed;border-color:#fed7aa}
    .track-notif-item:hover{background:#f8fafc}
    .track-notif-item-title{font-size:.84rem;font-weight:900;color:#111827;margin-bottom:3px}
    .track-notif-item-msg{font-size:.76rem;color:#6b7280;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .track-notif-time{font-size:.68rem;color:#9ca3af;margin-top:6px;font-weight:700}
    .track-notif-empty{padding:28px 16px;text-align:center;color:#6b7280;font-size:.84rem}
    .track-notif-more{margin:2px 8px 10px;border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:.65rem;font-size:.78rem;font-weight:800;color:#111827}
    .track-notif-detail{position:fixed;left:50%;top:50%;width:min(420px,calc(100vw - 28px));max-height:min(580px,calc(100vh - 40px));z-index:1082;background:#fff;border-radius:18px;box-shadow:0 26px 80px rgba(15,23,42,.28);padding:18px;display:none;transform:translate(-50%,-50%);overflow:auto}
    .track-notif-detail.is-open{display:block;animation:trackPanelIn .18s ease}
    .track-notif-detail-title{font-size:1rem;font-weight:900;color:#111827;margin-right:38px}
    .track-notif-detail-message{white-space:pre-wrap;color:#374151;font-size:.86rem;line-height:1.55;margin-top:10px}
    .track-notif-detail-time{color:#9ca3af;font-size:.72rem;font-weight:800;margin-top:12px}
    @media (max-width:640px){
      .track-fab-wrap{right:14px;bottom:16px}
      .track-head{padding-top:4px}
      .track-bell-btn{right:2px;top:2px;width:44px;height:44px}
      .track-notif-panel{right:9px;top:70px;max-height:calc(100vh - 88px)}
      .track-fab-label{max-width:130px;opacity:1}
      .track-fab-item{height:44px}
      .receipt-drawer{width:calc(100vw - 18px);max-height:82vh;border-radius:16px}
      .proof-viewer-bar{height:52px}
      .proof-tools{height:60px}
      .proof-icon-btn{width:38px;height:38px}
      .track-payment-note,
      .track-paid-banner {
        align-items:flex-start;
        justify-content:flex-start;
        text-align:left;
      }
      .track-action-panel.is-open {
        width:calc(100vw - 18px);
        max-height:86vh;
        border-radius:16px;
      }
      #messagePanel.track-action-panel.is-open {
        height:calc(100dvh - 18px);
        max-height:calc(100dvh - 18px);
      }
      #messagePanel .chat-box-g {
        min-height:120px;
        padding:12px;
      }
      #messagePanel .g-preview-bar {
        max-height:98px;
        padding:8px 12px 4px;
      }
      #messagePanel .g-img-card {
        width:78px;
        flex-basis:78px;
      }
      #messagePanel .g-img-card img {
        width:78px;
        height:58px;
      }
      #messagePanel .g-compose-wrap {
        padding:9px 10px calc(10px + env(safe-area-inset-bottom));
      }
      #messagePanel .g-compose-row {
        gap:7px;
      }
      #messagePanel .g-attach-btn,
      #messagePanel .g-send-btn {
        width:40px;
        height:40px;
      }
      #messagePanel .g-compose-box {
        font-size:.86rem;
        border-radius:12px;
        max-height:92px;
      }
      .msg-grp-g {
        max-width:82%;
      }
      .bbl-imgs-g,
      .bbl-imgs-g.img-count-2 {
        width:min(244px,100%);
      }
      .bbl-imgs-g.img-count-1 {
        width:min(220px,100%);
      }
      .track-review-actions {
        grid-template-columns:1fr;
      }
    }
    html.track-modal-open,body.track-modal-open{overflow:hidden!important;height:100%!important}
    html.proof-viewer-open,body.proof-viewer-open{overflow:hidden!important;height:100%!important}
    html.refund-receipt-open,body.refund-receipt-open{overflow:hidden!important;height:100%!important}
  </style>

  {{-- Header --}}
  <div class="track-head">
    <button type="button" class="track-bell-btn" id="trackBellBtn" aria-label="Notifications" onclick="openTrackNotifications()">
      <i class="bi bi-bell-fill"></i>
      <span class="track-bell-badge" id="trackBellBadge">0</span>
    </button>
    <div style="font-size:3rem">🎂</div>
    <h4 class="fw-bold mb-1" style="color:var(--primary)">Order Tracking</h4>
    <div class="text-muted fw-semibold" style="font-size:1.75rem">Order #{{ $order->id }}</div>
  </div>

  <div class="track-notif-backdrop" id="trackNotifBackdrop" onclick="closeTrackNotifications()"></div>
  <aside class="track-notif-panel" id="trackNotifPanel" aria-label="Recent notifications">
    <div class="track-notif-head">
      <div>
        <div class="track-notif-title">Notifications</div>
        <div class="small text-muted">Recent updates for this order</div>
      </div>
      <div class="track-notif-actions">
        <button type="button" class="track-notif-icon" onclick="markAllTrackNotificationsRead()" title="Mark all read"><i class="bi bi-check2-all"></i></button>
        <button type="button" class="track-notif-icon" onclick="closeTrackNotifications()" title="Close"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="track-notif-list" id="trackNotifList">
      <div class="track-notif-empty">Loading notifications...</div>
    </div>
    <button type="button" class="track-notif-more" id="trackNotifMore" onclick="loadMoreTrackNotifications()" style="display:none">View more</button>
  </aside>

  <div class="track-notif-detail" id="trackNotifDetail" role="dialog" aria-modal="true" aria-label="Notification details">
    <button type="button" class="track-notif-icon" onclick="closeTrackNotificationDetail()" style="position:absolute;right:14px;top:14px"><i class="bi bi-x-lg"></i></button>
    <div class="track-notif-detail-title" id="trackNotifDetailTitle"></div>
    <div class="track-notif-detail-message" id="trackNotifDetailMessage"></div>
    <div class="track-notif-detail-time" id="trackNotifDetailTime"></div>
  </div>

  {{-- Status Badge --}}
  @php
    $isPickup = $order->fulfillment_type === 'Pickup';
    $hasDepositLock = ($order->deposit_status ?? null) === 'paid' || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid']);
    $hasPendingCancel = ($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending';
    $cancelApproved = ($order->cancel_status ?? '') === 'accepted';
    $cancelRejected = ($order->cancel_status ?? '') === 'rejected';
    $canRequestCancel = in_array($order->status, ['Pending', 'Pending Review', 'Awaiting Deposit', 'Confirmed', 'Preparing']) && !$cancelApproved;
    $canPayOrder = !in_array($order->status, ['Cancelled', 'Delivered', 'Picked Up'], true)
      && !in_array(($order->cancel_status ?? ''), ['pending', 'accepted'], true);
    $statusColors = [
      'Awaiting Deposit' => ['bg'=>'#fff7ed','color'=>'#9a3412','icon'=>'bi-credit-card-fill'],
      'Pending'          => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-hourglass-split'],
      'Pending Review'   => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'bi-hourglass-split'],
      'Confirmed'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-check-circle-fill'],
      'Preparing'        => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'bi-fire'],
      'Out for Delivery' => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'bi-truck'],
      'Pickup'           => ['bg'=>'#ede9fe','color'=>'#5b21b6','icon'=>'bi-shop'],
      'Delivered'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-house-check-fill'],
      'Picked Up'        => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'bi-bag-check-fill'],
      'Cancelled'        => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'bi-x-circle-fill'],
      'Refunded'         => ['bg'=>'#dcfce7','color'=>'#166534','icon'=>'bi-cash-coin'],
    ];
    $sc = $statusColors[$order->status] ?? $statusColors['Pending'];
    $statusLabels = ['Pickup' => 'Ready for Pickup'];
    $statusDisplay = $statusLabels[$order->status] ?? $order->status;

    $paymentTotalAmount = max(0, (float) ($order->total_price ?? 0));
    $paymentDepositAmount = max(0, (float) ($order->deposit_amount ?? 0));
    $paymentDepositPaid = ($order->deposit_status ?? null) === 'paid' || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid'], true);
    $paymentPaidAmount = ($order->payment_status ?? '') === 'Paid'
      ? $paymentTotalAmount
      : ($paymentDepositPaid ? min($paymentTotalAmount, $paymentDepositAmount) : 0);
    $paymentRemainingBalance = max(0, round($paymentTotalAmount - $paymentPaidAmount, 2));
    $paymentFullyPaid = ($order->payment_status ?? '') === 'Paid' || $paymentRemainingBalance <= 0.009;
    $paymentDueLabel = $isPickup ? 'pickup' : 'delivery';
    if ($paymentFullyPaid && ($order->status ?? '') === 'Awaiting Deposit') {
      $sc = $statusColors['Confirmed'];
      $statusDisplay = 'Confirmed';
    }
  @endphp

  <div class="text-center mb-4">
    <span class="badge px-4 py-3" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};font-size:1rem;border-radius:2rem">
      <i class="bi {{ $sc['icon'] }} me-2"></i>{{ $statusDisplay }}
    </span>
    @if($order->status === 'Out for Delivery')
    @php
      $zone = DB::table('delivery_zones')->where('barangay', $order->delivery_zone)->first();
      $eta  = $zone->estimated_time ?? null;
    @endphp
    @if($eta)
    <div class="mt-2">
      <span class="badge px-3 py-2" style="background:#dbeafe;color:#1e40af;border-radius:1rem;font-size:clamp(.74rem,1.5vw,.8rem)">
        <i class="bi bi-clock me-1"></i>Estimated arrival: ~{{ $eta }}
      </span>
    </div>
    @endif
    @endif
    @if($isPickup && $order->status === 'Out for Delivery')
    <div class="mt-2">
      <span class="badge px-3 py-2" style="background:#fef9c3;color:#854d0e;border-radius:1rem;font-size:clamp(.74rem,1.5vw,.8rem)">
        <i class="bi bi-info-circle me-1"></i>Your order is ready — please wait for the shop to contact you for pickup details.
      </span>
    </div>
    @endif
  </div>

  @if($hasPendingCancel)
  <div class="alert border-0 mb-4" style="background:#fff3cd;color:#854d0e">
    <i class="bi bi-hourglass-split me-2"></i>Cancellation request pending. Waiting for admin approval.
  </div>
  @elseif($cancelApproved)
  <div class="alert border-0 mb-4" style="background:#dcfce7;color:#166534">
    <i class="bi bi-check-circle me-2"></i>Cancellation request approved.
    @if($order->cancel_admin_note) {{ $order->cancel_admin_note }} @endif
  </div>
  @elseif($cancelRejected)
  <div class="alert border-0 mb-4" style="background:#fee2e2;color:#991b1b">
    <i class="bi bi-x-circle me-2"></i>Cancellation request rejected.
    @if($order->cancel_admin_note) Reason: {{ $order->cancel_admin_note }} @endif
  </div>
  @elseif($hasDepositLock && $order->status !== 'Cancelled')
  <div class="alert border-0 mb-4" style="background:#eff6ff;color:#1d4ed8">
    <i class="bi bi-shield-check me-2"></i>This order has payment recorded. Cancellation is still possible, but it must be reviewed because a refund is required.
  </div>
  @endif

  {{-- Progress Bar (non-cancelled) --}}
  @if($order->status !== 'Cancelled')
  @php
    if ($isPickup) {
      $steps = ['Pending'=>'Received','Confirmed'=>'Confirmed','Preparing'=>'Baking','Pickup'=>'Ready','Picked Up'=>'Picked Up'];
    } else {
      $steps = ['Pending'=>'Received','Confirmed'=>'Confirmed','Preparing'=>'Baking','Out for Delivery'=>'On the Way','Delivered'=>'Delivered'];
    }
    $stepKeys = array_keys($steps);

    // Map mismatched / pre-confirmed statuses to nearest progress step
    $statusForProgress = $order->status;
    if ($statusForProgress === 'Awaiting Deposit')              $statusForProgress = 'Pending';
    if ($statusForProgress === 'Pending Review')                $statusForProgress = 'Pending';
    if ($isPickup && $statusForProgress === 'Out for Delivery') $statusForProgress = 'Preparing';
    if (!$isPickup && $statusForProgress === 'Pickup')          $statusForProgress = 'Preparing';
    if (!$isPickup && $statusForProgress === 'Picked Up')       $statusForProgress = 'Delivered';

    $current = array_search($statusForProgress, $stepKeys);
    if ($current === false) $current = 0;
    $n = count($steps);
    $progressWidth = max(0, round(($current * 100 / $n) + (50 / $n) - 8));
  @endphp
  <div class="card mb-4">
    <div class="card-body p-3">
      <div class="d-flex justify-content-between position-relative" style="padding:0 8px">
        {{-- Line --}}
        <div style="position:absolute;top:16px;left:8%;right:8%;height:3px;background:#e9ecef;z-index:0"></div>
        <div style="position:absolute;top:16px;left:8%;height:3px;background:var(--primary);z-index:1;width:{{ $progressWidth }}%;transition:width .6s ease"></div>
        @foreach($steps as $key => $label)
        @php $idx = array_search($key, $stepKeys); $done = $idx <= $current; @endphp
        <div class="text-center" style="z-index:2;flex:1">
          <div class="mx-auto d-flex align-items-center justify-content-center"
               style="width:32px;height:32px;border-radius:50%;border:3px solid {{ $done ? 'var(--primary)' : '#e9ecef' }};background:{{ $done ? 'var(--primary)' : '#fff' }};transition:all .3s">
            @if($done)
              <i class="bi bi-check text-white" style="font-size:clamp(.8rem,1.7vw,.9rem)"></i>
            @else
              <span style="width:8px;height:8px;border-radius:50%;background:#dee2e6;display:block"></span>
            @endif
          </div>
          <div class="mt-1" style="font-size:clamp(.62rem,1.2vw,.65rem);color:{{ $done ? 'var(--primary)' : '#9ca3af' }};font-weight:{{ $done ? '600' : '400' }}">
            {{ $label }}
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- Order Details --}}
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Order Details</h6>
      <div class="d-flex align-items-center gap-3 mb-3">
        @php
            $refImgs = [];
            $thumbSrc = $order->image_path ?? '/images/default-cake.svg';
            if ($customOrder && !empty($customOrder->reference_images)) {
                $decodedRefs = is_array($customOrder->reference_images)
                    ? $customOrder->reference_images
                    : json_decode($customOrder->reference_images, true);
                $refImgs = is_array($decodedRefs) ? array_values(array_filter($decodedRefs)) : [];
                if (!empty($refImgs[0])) $thumbSrc = $refImgs[0];
            }
        @endphp
        <div data-lightbox-gallery="guest-track-order-{{ $order->id }}" style="display:flex">
          @if($customOrder && count($refImgs) > 0)
            @foreach($refImgs as $idx => $refImg)
              <img src="{{ $refImg }}" data-src="{{ $refImg }}" class="chat-img"
                   style="width:64px;height:64px;object-fit:cover;border-radius:.7rem;cursor:zoom-in;{{ $idx === 0 ? '' : 'display:none' }}"
                   onclick="openLightbox(this)"
                   onerror="this.style.display='none'">
            @endforeach
          @else
            <img src="{{ $thumbSrc }}" data-src="{{ $thumbSrc }}" class="chat-img"
                 style="width:64px;height:64px;object-fit:cover;border-radius:.7rem;cursor:zoom-in"
                 onclick="openLightbox(this)"
                 onerror="this.src='https://placehold.co/64x64/fce4ec/e91e63?text=Cake'">
          @endif
        </div>
        <div>
          <div class="fw-bold">{{ $order->product_name }}</div>
          <div class="text-muted small">Qty: {{ $order->quantity }}
            @if($order->selected_size) &bull; {{ $order->selected_size }} @endif
          </div>
          @if(!empty($order->discount_type) && (float)($order->discount_amount ?? 0) > 0)
            <div class="small mt-1" style="color:#c2410c">
              <i class="bi bi-tags me-1"></i>{{ \App\Helpers\CakeshopHelper::discountBadgeText($order->discount_type, $order->discount_value) ?? 'Product Discount' }}
              @if(!empty($order->discount_label))
                <span class="text-muted">({{ $order->discount_label }})</span>
              @endif
            </div>
          @endif
          @if($order->custom_note)
            <div class="small text-muted mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $order->custom_note }}</div>
          @endif
        </div>
      </div>

      {{-- Add-ons --}}
      @if(count($addons) > 0)
      <div class="mb-3">
        <div class="small fw-semibold mb-1">Add-ons:</div>
        <div class="d-flex flex-wrap gap-1">
          @foreach($addons as $a)
            <span class="badge" style="background:#f0fdf4;color:#166534;font-size:clamp(.68rem,1.3vw,.72rem)">{{ $a->addon_name }}</span>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Info Grid --}}
      <div class="row g-2 small">
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Name</div>
            <div class="fw-semibold">{{ $order->guest_name }}</div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Fulfillment</div>
            <div class="fw-semibold">
              <i class="bi bi-{{ $order->fulfillment_type === 'Delivery' ? 'truck' : 'shop' }} me-1"></i>
              {{ $order->fulfillment_type }}
            </div>
          </div>
        </div>
        @if($order->schedule_date)
        <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Schedule</div>
            <div class="fw-semibold">{{ \Carbon\Carbon::parse($order->schedule_date)->format('M d, Y') }}</div>
          </div>
        </div>
        @endif
              <div class="col-sm-6">
          <div class="p-2 rounded-2" style="background:#f8f9fa">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Payment</div>
            <div class="fw-semibold">{{ \App\Helpers\CakeshopHelper::displayPaymentMethod($order->payment_method, $order->fulfillment_type) }}
              <span class="badge ms-1 {{ $order->payment_status === 'Paid' ? 'bg-success' : ($order->payment_status === 'Partial Payment' ? 'bg-primary' : 'bg-warning text-dark') }}">
                {{ $order->payment_status }}
              </span>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="p-2 rounded-2 track-payment-summary">
            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase">Total Amount</div>
            <div class="fw-bold" style="color:var(--primary);font-size:clamp(.9rem,2.2vw,1.1rem)">&#8369;{{ number_format($paymentTotalAmount, 2) }}</div>
            @if($order->status === 'Cancelled' || $cancelApproved)
              @if(($refund ?? null) && ($refund->status ?? '') === 'refunded')
              <div class="track-payment-note success">
                <i class="bi bi-cash-coin"></i>
                <span>Order cancelled. Refund sent.</span>
              </div>
              @elseif(($refund ?? null) && ($refund->status ?? '') === 'pending')
              <div class="track-payment-note warning">
                <i class="bi bi-hourglass-split"></i>
                <span>Order cancelled. Refund request is waiting for seller review.</span>
              </div>
              @else
              <div class="track-payment-note success">
                <i class="bi bi-x-circle"></i>
                <span>Order cancelled. No payment is required.</span>
              </div>
              @endif
            @elseif($hasPendingCancel)
            <div class="track-payment-note warning">
              <i class="bi bi-hourglass-split"></i>
              <span>Cancellation/refund request pending. Please wait for seller review.</span>
            </div>
            @elseif($paymentDepositPaid)
              @if($paymentFullyPaid)
              <div class="track-payment-note success">
                <i class="bi bi-check-circle-fill"></i>
                <span>Payment complete. No remaining balance is due.</span>
              </div>
              @else
              <div class="track-payment-note warning">
                <i class="bi bi-clock-history"></i>
                <span>Deposit paid: &#8369;{{ number_format($paymentPaidAmount, 2) }}. Remaining balance: <strong>&#8369;{{ number_format($paymentRemainingBalance, 2) }}</strong> due on {{ $paymentDueLabel }}.</span>
              </div>
              @endif
            @elseif($order->deposit_required && $order->deposit_status === 'pending')
            <div class="track-payment-note warning">
              <i class="bi bi-clock"></i>
              <span>Deposit of &#8369;{{ number_format($paymentDepositAmount, 2) }} pending.</span>
            </div>
            @endif
          </div>
        </div>

        {{-- GCash Pay Button — only at correct status per fulfillment type --}}
        @if($order->payment_method === 'GCash' && $order->payment_status !== 'Paid')
          @php
            $showPayBtn  = ($isPickup && $order->status === 'Pickup')
                        || (!$isPickup && $order->status === 'Out for Delivery');
            $depositPaid = $order->deposit_status === 'paid';
            $remainingAmt = $depositPaid
              ? max(0, (float)$order->total_price - (float)$order->deposit_amount)
              : (float)$order->total_price;
            if ($isPickup) {
              $btnLabel = $depositPaid
                ? 'Pay Remaining Balance ₱' . number_format($remainingAmt, 2) . ' via GCash'
                : 'Pay ₱' . number_format($remainingAmt, 2) . ' via GCash — Ready for Pickup!';
            } else {
              $btnLabel = $depositPaid
                ? 'Pay Remaining Balance ₱' . number_format($remainingAmt, 2) . ' via GCash'
                : 'Pay Full Amount ₱' . number_format($remainingAmt, 2) . ' via GCash';
            }
          @endphp
          @if($showPayBtn)
          <div class="col-12 mt-2">
            <a href="{{ route('guest.pay_remaining', $order->track_code) }}"
               class="btn w-100 fw-semibold py-3"
               style="background:#007AFF;border-color:#007AFF;color:#fff;font-size:1rem"
               data-cs-confirm="You will be redirected to GCash payment via PayMongo.\n\nAmount: {{ $btnLabel }}\n\nProceed?"
               data-cs-title="Proceed to Payment"
               data-cs-ok="Continue"
               data-cs-icon="bi-wallet2"
               data-cs-icon-bg="#dbeafe"
               data-cs-icon-color="#2563eb">
              <i class="bi bi-phone-fill me-2"></i>{{ $btnLabel }}
            </a>
            @if($depositPaid)
            <div class="text-muted text-center mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
              <i class="bi bi-check-circle-fill me-1" style="color:#16a34a"></i>
              Deposit of ₱{{ number_format($order->deposit_amount, 2) }} already paid ✓
            </div>
            @endif
              <div class="text-muted text-center mt-1" style="font-size:clamp(.7rem,1.4vw,.75rem)">
              <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo
            </div>
          </div>
          @endif
        @endif

        {{-- Payment Fully Paid Badge --}}
        @if($order->payment_status === 'Paid' && in_array($order->status, ['Out for Delivery','Pickup','Delivered','Picked Up']))
        <div class="col-12 mt-2">
          <div class="p-2 rounded-2 text-center" style="background:#f0fdf4;border:1px solid #bbf7d0">
            <i class="bi bi-check-circle-fill me-1" style="color:#16a34a"></i>
            <span style="color:#166534;font-size:.83rem;font-weight:600">
              ✓ Fully Paid via GCash — {{ $isPickup ? 'Ready for pickup!' : 'Admin can now mark as Delivered.' }}
            </span>
          </div>
        </div>
        @endif

        {{-- ─── GCash Deposit Card — one-click payment ──────────────── --}}
        @if($canPayOrder && $order->payment_method === 'GCash' && $order->payment_status === 'Unpaid' && in_array($order->status, ['Pending','Pending Review']) && $order->deposit_status !== 'paid' && !$customOrder)
        @php $minDeposit = max(100, round($order->total_price * 0.5, 2)); @endphp
        <div class="col-12 mt-3">
          <div style="border-radius:1rem;overflow:hidden;border:1.5px solid #d1fae5">
            <div style="background:linear-gradient(90deg,#059669,#0284c7);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
              <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:1rem"></i>
              <span style="color:#fff;font-weight:700;font-size:.88rem;flex:1">Secure Your Order — Pay via GCash</span>
              @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
              @if($pmMode === 'test')
                <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">TEST MODE</span>
              @else
                <span style="background:rgba(255,255,255,.22);color:#d1fae5;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">LIVE</span>
              @endif
            </div>
            <div style="background:#f8fffe;padding:1rem">
              <div style="font-size:.8rem;color:#374151;margin-bottom:.8rem">
                <i class="bi bi-info-circle me-1" style="color:#0284c7"></i>Pay a deposit to confirm your order. Your cake will be auto-confirmed after payment.
              </div>
              <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
                <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Order Total</span>
                <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($order->total_price, 2) }}</span>
              </div>
              <div class="d-flex flex-column gap-2">
                {{-- Primary: 50% deposit --}}
                <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST"
                      class="deposit-amount-form"
                      data-min="{{ $minDeposit }}"
                      data-max="{{ $order->total_price }}">
                  @csrf
                  <label class="form-label fw-semibold small mb-1" style="color:#374151">Amount to pay now</label>
                  <div class="input-group">
                    <span class="input-group-text" style="font-weight:800;color:#059669;background:#ecfdf5;border-color:#bbf7d0">₱</span>
                    <input type="text"
                           name="deposit_amount"
                           class="form-control deposit-amount-input"
                           value="{{ number_format($minDeposit, 2, '.', '') }}"
                           inputmode="decimal"
                           autocomplete="off"
                           data-min="{{ $minDeposit }}"
                           data-max="{{ $order->total_price }}"
                           style="font-weight:800;color:#111827;border-color:#bbf7d0">
                  </div>
                  <div class="deposit-error">Minimum payment is 50%: ₱{{ number_format($minDeposit, 2) }}.</div>
                  <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem">
                    Enter at least ₱{{ number_format($minDeposit, 2) }}. You may pay more up to ₱{{ number_format($order->total_price, 2) }}.
                  </div>
                  <button type="submit" class="btn w-100 fw-bold py-3"
                          style="margin-top:.65rem;background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:.75rem;font-size:.95rem;letter-spacing:.01em"
                          data-cs-confirm="Pay 50% deposit of ₱{{ number_format($minDeposit,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is the only option — your phone number will be pre-filled."
                          data-cs-title="Confirm Deposit — ₱{{ number_format($minDeposit,2) }}"
                          data-cs-ok="Pay Now"
                          data-cs-icon="bi-phone-fill"
                          data-cs-icon-bg="#d1fae5"
                          data-cs-icon-color="#059669">
                    <i class="bi bi-phone-fill me-2"></i>Pay Deposit via GCash
                  </button>
                  <div style="font-size:.7rem;color:#6b7280;text-align:center;margin-top:.3rem">
                    Remaining balance: ₱{{ number_format($order->total_price - $minDeposit, 2) }} (paid on delivery)
                  </div>
                </form>
                {{-- Secondary: full payment --}}
                <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
                  @csrf
                  <input type="hidden" name="deposit_amount" value="{{ $order->total_price }}">
                  <button type="submit" class="btn w-100 fw-semibold py-2"
                          style="background:#fff;color:#059669;border:1.5px solid #059669;border-radius:.75rem;font-size:.88rem"
                          data-cs-confirm="Pay full amount of ₱{{ number_format($order->total_price,2) }} via GCash?\n\nYou'll be redirected to PayMongo."
                          data-cs-title="Confirm Full Payment — ₱{{ number_format($order->total_price,2) }}"
                          data-cs-ok="Pay in Full"
                          data-cs-icon="bi-wallet2"
                          data-cs-icon-bg="#d1fae5"
                          data-cs-icon-color="#059669">
                    <i class="bi bi-wallet2 me-2"></i>Pay in Full — ₱{{ number_format($order->total_price, 2) }}
                  </button>
                </form>
              </div>
              <div style="margin-top:.75rem;font-size:.68rem;color:#9ca3af;text-align:center">
                <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Processing fee shown before payment
              </div>
            </div>
          </div>
        </div>

        {{-- Already initiated → resume payment (editable amount) --}}
        @elseif($canPayOrder && $order->deposit_required && $order->deposit_status === 'pending')
        @php $pendingMin = max(100, round((float)$order->total_price * 0.5, 2)); @endphp
        <div class="col-12 mt-3">
          <div style="border-radius:1rem;overflow:hidden;border:1.5px solid #fed7aa">
            <div style="background:linear-gradient(90deg,#d97706,#ea580c);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
              <i class="bi bi-clock-fill" style="color:#fff;font-size:.9rem"></i>
              <span style="color:#fff;font-weight:700;font-size:.88rem">Payment Pending — Complete Your Payment</span>
            </div>
            <div style="background:#fffbeb;padding:1rem">
              <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a">
                <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Order Total</span>
                <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($order->total_price, 2) }}</span>
              </div>
              <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST"
                    class="deposit-amount-form"
                    data-min="{{ $pendingMin }}"
                    data-max="{{ $order->total_price }}"
                    data-btn-label="Continue Payment via GCash">
                @csrf
                <label class="form-label fw-semibold small mb-1" style="color:#374151">Amount to pay now</label>
                <div class="input-group">
                  <span class="input-group-text" style="font-weight:800;color:#d97706;background:#fffbeb;border-color:#fde68a">₱</span>
                  <input type="text"
                         name="deposit_amount"
                         class="form-control deposit-amount-input"
                         value="{{ number_format((float)$order->deposit_amount >= $pendingMin ? (float)$order->deposit_amount : $pendingMin, 2, '.', '') }}"
                         inputmode="decimal"
                         autocomplete="off"
                         data-min="{{ $pendingMin }}"
                         data-max="{{ $order->total_price }}"
                         style="font-weight:800;color:#111827;border-color:#fde68a">
                </div>
                <div class="deposit-error">Minimum payment is 50%: ₱{{ number_format($pendingMin, 2) }}.</div>
                <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem">
                  Enter at least ₱{{ number_format($pendingMin, 2) }}. You may pay more up to ₱{{ number_format($order->total_price, 2) }}.
                </div>
                <button type="submit"
                        class="btn w-100 fw-bold py-3"
                        style="margin-top:.65rem;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:.75rem;font-size:.95rem"
                        data-cs-title="Resume Payment"
                        data-cs-ok="Continue"
                        data-cs-icon="bi-phone-fill"
                        data-cs-icon-bg="#fef3c7"
                        data-cs-icon-color="#d97706">
                  <i class="bi bi-phone-fill me-2"></i>Continue Payment via GCash
                </button>
                <div style="font-size:.7rem;color:#6b7280;text-align:center;margin-top:.3rem">
                  Remaining balance paid on delivery if partial.
                </div>
              </form>
              <div style="margin-top:.6rem;font-size:.68rem;color:#9ca3af;text-align:center">
                <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Processing fee shown before payment
              </div>
            </div>
          </div>
        </div>
        @endif

        {{-- Deposit Paid Badge --}}
        @if($paymentDepositPaid && $order->status !== 'Cancelled' && !$cancelApproved)
        <div class="col-12 mt-2">
          <div class="track-paid-banner">
            <i class="bi bi-check-circle-fill"></i>
            <span>
              @if($paymentFullyPaid)
                Fully paid. No remaining balance.
              @else
                Deposit of &#8369;{{ number_format($paymentPaidAmount, 2) }} paid. Remaining balance: &#8369;{{ number_format($paymentRemainingBalance, 2) }}.
              @endif
            </span>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>

  @if(false && ($recentReceipts ?? collect())->count() > 0)
  <div class="card mb-3 d-none">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
        <div>
          <h6 class="fw-bold mb-1"><i class="bi bi-receipt-cutoff me-2" style="color:var(--primary)"></i>Recent Receipts</h6>
          <div class="small text-muted">Summary only. Full receipt opens per tracking code.</div>
        </div>
        <a href="{{ route('guest.receipts', $order->track_code) }}" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-list-ul me-1"></i>View All
        </a>
      </div>
      <div class="d-flex flex-column gap-2">
        @foreach($recentReceipts as $r)
          @php
            $isLedger = isset($r->receipt_id);
            $paidAmount = $isLedger
              ? (float)$r->amount
              : ($r->payment_status === 'Paid' ? (float)$r->total_price : (float)$r->deposit_amount);
            $paidDate = $r->paid_at ?? $r->deposit_paid_at ?? $r->created_at;
            $typeLabel = $isLedger ? \App\Helpers\PaymentTransactionHelper::typeLabel($r->type) : $r->payment_status;
            $orderId = $isLedger ? $r->order_id : $r->id;
            $viewUrl = $isLedger
              ? route('guest.receipt_transaction', ['trackCode' => $r->track_code, 'transactionId' => $r->receipt_id])
              : route('guest.receipt', ['trackCode' => $r->track_code]);
          @endphp
          <div class="p-3 rounded-3 d-flex align-items-center justify-content-between gap-3 flex-wrap" style="background:#f8fafc;border:1px solid #e5e7eb">
            <div style="min-width:180px">
              <div class="fw-bold" style="color:#111827">Order #{{ $orderId }}</div>
              <div class="small text-muted">{{ $typeLabel }} &bull; {{ \Carbon\Carbon::parse($paidDate)->format('M d, Y') }}</div>
            </div>
            <div class="text-sm-end">
              <div class="fw-bold" style="color:#16a34a">₱{{ number_format($paidAmount, 2) }}</div>
              <div class="small text-muted">{{ $r->product_name }}</div>
            </div>
            <a href="{{ $viewUrl }}" class="btn btn-primary btn-sm">
              <i class="bi bi-eye me-1"></i>View Receipt
            </a>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  @if($canRequestCancel && !$hasPendingCancel && !$cancelApproved)
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-x-circle me-2" style="color:#dc2626"></i>{{ $hasDepositLock ? 'Request Cancellation & Refund' : 'Cancel Order' }}</h6>
      <div class="small text-muted mb-3">
        @if($hasDepositLock)
          Payment has already been recorded for this order. Enter the GCash details where the refund should be sent, then the seller will review your request.
        @else
          No paid deposit has been recorded yet. Submitting this will cancel the order immediately.
        @endif
      </div>
      <form action="{{ route('guest.cancel_request', $order->track_code) }}" method="POST">
        @csrf
        <div class="mb-3">
          <label class="form-label fw-semibold small">Reason for Cancellation <span class="text-danger">*</span></label>
          <textarea class="form-control" name="cancel_reason" rows="3" required placeholder="Please explain why you want to cancel this order."></textarea>
        </div>
        @if($hasDepositLock)
        <div class="p-3 rounded-3 mb-3" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412">
          <div class="fw-bold small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Double-check your refund details</div>
          <div class="small">The refund will be sent to the GCash name and number you provide. Make sure the account name and mobile number are correct before submitting.</div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold small">GCash Account Name <span class="text-danger">*</span></label>
            <input class="form-control" name="refund_gcash_name" value="{{ old('refund_gcash_name') }}" placeholder="Exact registered GCash name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">GCash Mobile Number <span class="text-danger">*</span></label>
            <input class="form-control" name="refund_gcash_number" value="{{ old('refund_gcash_number') }}" placeholder="09XXXXXXXXX" pattern="^(09[0-9]{9}|\+639[0-9]{9})$" inputmode="tel" required>
          </div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="refundConfirmCheck" required>
          <label class="form-check-label small" for="refundConfirmCheck">
            I confirm that the GCash account name and number are correct.
          </label>
        </div>
        @endif
        <button type="submit"
                class="btn btn-outline-danger"
                data-cs-confirm="{{ $hasDepositLock ? 'Submit cancellation and refund request? Make sure your GCash details are correct.' : 'Cancel this unpaid order now?' }}"
                data-cs-title="{{ $hasDepositLock ? 'Request Refund' : 'Cancel Order' }}"
                data-cs-ok="{{ $hasDepositLock ? 'Submit Request' : 'Cancel Order' }}"
                data-cs-ok-color="#dc2626"
                data-cs-icon="bi-x-octagon"
                data-cs-icon-bg="#fee2e2"
                data-cs-icon-color="#dc2626">
          <i class="bi bi-send me-1"></i>{{ $hasDepositLock ? 'Submit Refund Request' : 'Cancel Order' }}
        </button>
      </form>
    </div>
  </div>
  @endif

  {{-- Custom Order Info --}}
  @if($customOrder)
  <div class="card mb-3">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2" style="color:var(--primary)"></i>Custom Order Details</h6>
      @php
        $coStatus = [
          'pending'  => ['bg'=>'#fff3cd','color'=>'#856404','label'=>'⏳ Awaiting Review'],
          'approved' => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'✅ Approved'],
          'rejected' => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'❌ Not Approved'],
        ];
        $cos = $coStatus[$customOrder->review_status] ?? $coStatus['pending'];
      @endphp
      <span class="badge mb-3 px-3 py-2" style="background:{{ $cos['bg'] }};color:{{ $cos['color'] }}">
        {{ $cos['label'] }}
      </span>
      @if($customOrder->price_confirmed === 'accepted' && $customOrder->admin_price > 0)
        @if($paymentFullyPaid)
        <span class="badge mb-3 px-3 py-2 ms-1" style="background:#d1fae5;color:#065f46">
          <i class="bi bi-shield-check me-1"></i>Price Accepted - Fully Paid
        </span>
        @elseif($paymentDepositPaid)
        <span class="badge mb-3 px-3 py-2 ms-1" style="background:#dcfce7;color:#166534">
          <i class="bi bi-check2-circle me-1"></i>Price Accepted - Deposit Paid
        </span>
        @else
        <span class="badge mb-3 px-3 py-2 ms-1" style="background:#dbeafe;color:#1e40af">
          <i class="bi bi-clock-history me-1"></i>Price Accepted - Awaiting Deposit
        </span>
        @endif
      @elseif($customOrder->price_confirmed === 'cancelled')
      <span class="badge mb-3 px-3 py-2 ms-1" style="background:#fee2e2;color:#991b1b">
        <i class="bi bi-x-circle me-1"></i>Price Declined
      </span>
      @endif
      @if($customOrder->admin_price)
        <div class="mb-2 small"><span class="text-muted">Final Price:</span>
          <strong class="ms-1" style="color:var(--primary)">₱{{ number_format($customOrder->admin_price, 2) }}</strong>
        </div>
      @endif
      @if($customOrder->admin_comment)
        <div class="p-2 rounded-2 small" style="background:{{ $customOrder->review_status === 'approved' ? '#f0fdf4' : '#fef2f2' }};border-left:3px solid {{ $customOrder->review_status === 'approved' ? '#22c55e' : '#ef4444' }}">
          <span class="fw-semibold">{{ $customOrder->review_status === 'approved' ? '✅ Baker:' : '❌ Reason:' }}</span>
          {{ $customOrder->admin_comment }}
        </div>
      @endif
      {{-- ── CUSTOM ORDER DEPOSIT — one-click payment card ─────────── --}}
      @if($canPayOrder
          && $customOrder->review_status === 'approved'
          && $customOrder->admin_price > 0
          && $customOrder->price_confirmed === 'accepted'
          && $order->payment_status === 'Unpaid'
          && $order->deposit_status !== 'paid'
          && in_array($order->status, ['Pending','Pending Review','Confirmed']))
      @php
        $coTotal = (float)$customOrder->admin_price;
        $minDep  = max(100, round($coTotal * 0.5, 2));
      @endphp
      @if($order->payment_method === 'GCash')
      <div class="mt-3" style="border-radius:1rem;overflow:hidden;border:1.5px solid #d1fae5">
        <div style="background:linear-gradient(90deg,#059669,#0284c7);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
          <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:1rem"></i>
          <span style="color:#fff;font-weight:700;font-size:.88rem;flex:1">Secure Your Custom Order — Pay via GCash</span>
          @php $pmMode = \App\Helpers\CakeshopHelper::getPaymongoMode(); @endphp
          @if($pmMode === 'test')
            <span style="background:rgba(255,255,255,.22);color:#fef9c3;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">TEST MODE</span>
          @else
            <span style="background:rgba(255,255,255,.22);color:#d1fae5;border-radius:20px;padding:2px 9px;font-size:.65rem;font-weight:700">LIVE</span>
          @endif
        </div>
        <div style="background:#f8fffe;padding:1rem">
          <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Final Price</span>
            <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($coTotal, 2) }}</span>
          </div>
          <div class="d-flex flex-column gap-2">
            <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST"
                  class="deposit-amount-form"
                  data-min="{{ $minDep }}"
                  data-max="{{ $coTotal }}">
              @csrf
              <label class="form-label fw-semibold small mb-1" style="color:#374151">Amount to pay now</label>
              <div class="input-group">
                <span class="input-group-text" style="font-weight:800;color:#059669;background:#ecfdf5;border-color:#bbf7d0">₱</span>
                <input type="text"
                       name="deposit_amount"
                       class="form-control deposit-amount-input"
                       value="{{ number_format($minDep, 2, '.', '') }}"
                       inputmode="decimal"
                       autocomplete="off"
                       data-min="{{ $minDep }}"
                       data-max="{{ $coTotal }}"
                       style="font-weight:800;color:#111827;border-color:#bbf7d0">
              </div>
              <div class="deposit-error">Minimum payment is 50%: ₱{{ number_format($minDep, 2) }}.</div>
              <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem">
                Enter at least ₱{{ number_format($minDep, 2) }}. You may pay more up to ₱{{ number_format($coTotal, 2) }}.
              </div>
              <button type="submit" class="btn w-100 fw-bold py-3"
                      style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:.75rem;font-size:.95rem"
                      data-cs-confirm="Pay 50% deposit of ₱{{ number_format($minDep,2) }} via GCash?\n\nYou'll be redirected to PayMongo. GCash is pre-selected."
                      data-cs-title="Confirm Deposit — ₱{{ number_format($minDep,2) }}"
                      data-cs-ok="Pay Now"
                      data-cs-icon="bi-phone-fill"
                      data-cs-icon-bg="#d1fae5"
                      data-cs-icon-color="#059669">
                <i class="bi bi-phone-fill me-2"></i>Pay Deposit via GCash
              </button>
              <div style="font-size:.7rem;color:#6b7280;text-align:center;margin-top:.3rem">
                Remaining: ₱{{ number_format($coTotal - $minDep, 2) }} (paid on delivery)
              </div>
            </form>
            <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
              @csrf
              <input type="hidden" name="deposit_amount" value="{{ $coTotal }}">
              <button type="submit" class="btn w-100 fw-semibold py-2"
                      style="background:#fff;color:#059669;border:1.5px solid #059669;border-radius:.75rem;font-size:.88rem"
                      data-cs-confirm="Pay full amount of ₱{{ number_format($coTotal,2) }} via GCash?\n\nYou'll be redirected to PayMongo."
                      data-cs-title="Confirm Full Payment — ₱{{ number_format($coTotal,2) }}"
                      data-cs-ok="Pay in Full"
                      data-cs-icon="bi-wallet2"
                      data-cs-icon-bg="#d1fae5"
                      data-cs-icon-color="#059669">
                <i class="bi bi-wallet2 me-2"></i>Pay in Full — ₱{{ number_format($coTotal, 2) }}
              </button>
            </form>
          </div>
          <div style="margin-top:.75rem;font-size:.68rem;color:#9ca3af;text-align:center">
            <i class="bi bi-shield-check me-1" style="color:#22c55e"></i>Secured by PayMongo &nbsp;·&nbsp; GCash only &nbsp;·&nbsp; Processing fee shown before payment
          </div>
        </div>
      </div>
      @else
      @php
        $isCop   = $order->payment_method === 'COP';
        $pmLabel = $isCop ? 'Cash on Pickup' : 'Cash on Delivery';
        $pmIcon  = $isCop ? 'bi-bag-check' : 'bi-truck';
      @endphp
      <div class="mt-3" style="border-radius:1rem;overflow:hidden;border:1.5px solid #fde68a">
        <div style="background:linear-gradient(90deg,#d97706,#b45309);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
          <i class="bi {{ $pmIcon }}" style="color:#fff;font-size:1rem"></i>
          <span style="color:#fff;font-weight:700;font-size:.88rem;flex:1">Secure Your Order — {{ $pmLabel }}</span>
        </div>
        <div style="background:#fffbeb;padding:1rem">
          <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">50% Deposit Required</span>
            <span style="font-weight:800;color:#111827;font-size:1rem">₱{{ number_format($minDep,2) }}</span>
          </div>
          <form action="{{ route('guest.set_deposit', $order->track_code) }}" method="POST">
            @csrf
            <input type="hidden" name="deposit_amount" value="{{ $minDep }}">
            <button type="submit" class="btn w-100 fw-bold py-3"
                    style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;border-radius:.75rem;font-size:.95rem"
                    data-cs-confirm="Acknowledge 50% {{ $pmLabel }} deposit of ₱{{ number_format($minDep,2) }}?\n\nRemaining ₱{{ number_format($coTotal - $minDep, 2) }} will be due on {{ $isCop ? 'pickup' : 'delivery' }}."
                    data-cs-title="Confirm {{ $pmLabel }} Deposit"
                    data-cs-ok="Acknowledge"
                    data-cs-icon="{{ $pmIcon }}"
                    data-cs-icon-bg="#fef3c7"
                    data-cs-icon-color="#b45309">
              <i class="bi {{ $pmIcon }} me-2"></i>Acknowledge Deposit &amp; Confirm Order
            </button>
          </form>
          <div style="margin-top:.6rem;font-size:.7rem;color:#9ca3af;text-align:center">
            Remaining ₱{{ number_format($coTotal - $minDep, 2) }} due on {{ $isCop ? 'pickup' : 'delivery' }}
          </div>
        </div>
      </div>
      @endif
      @endif

      {{-- Price acceptance buttons (only if admin set price but customer hasn't responded yet) --}}
      @if($canPayOrder
          && $customOrder->review_status === 'approved'
          && $customOrder->admin_price > 0
          && $customOrder->price_confirmed === 'pending')
      @php $acceptTotal = (float)$customOrder->admin_price; $acceptMin = max(100, round($acceptTotal * 0.5, 2)); @endphp
      <div class="mt-3" style="border-radius:1rem;overflow:hidden;border:1.5px solid #fbbf24">
        <div style="background:linear-gradient(90deg,#d97706,#92400e);padding:.7rem 1.1rem;display:flex;align-items:center;gap:.6rem">
          <i class="bi bi-tag-fill" style="color:#fff;font-size:1rem"></i>
          <span style="color:#fff;font-weight:700;font-size:.88rem">Final Price Set — Please Respond</span>
        </div>
        <div style="background:#fffbeb;padding:1rem">
          <div style="background:#fff;border-radius:.65rem;padding:.55rem .9rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb">
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Seller's Final Price</span>
            <span style="font-weight:800;color:var(--primary);font-size:1.1rem">₱{{ number_format($customOrder->admin_price,2) }}</span>
          </div>
          <p class="small text-muted mb-3">The seller has set a final price for your custom cake. Choose how much to pay now (minimum 50%), then accept to proceed — or cancel to withdraw.</p>
          <form action="{{ route('guest.custom_order.accept_price', $customOrder->id) }}" method="POST"
                class="deposit-amount-form"
                data-min="{{ $acceptMin }}"
                data-max="{{ $acceptTotal }}"
                data-btn-label="Accept &amp; Pay via GCash">
            @csrf
            <label class="form-label fw-semibold small mb-1" style="color:#374151">
              Amount to pay now <span style="color:#9ca3af;font-weight:400">(min 50%)</span>
            </label>
            <div class="input-group mb-1">
              <span class="input-group-text fw-bold" style="color:#d97706;background:#fffbeb;border-color:#fde68a">₱</span>
              <input type="text"
                     name="deposit_amount"
                     class="form-control deposit-amount-input"
                     value="{{ number_format($acceptMin, 2, '.', '') }}"
                     inputmode="decimal"
                     autocomplete="off"
                     data-min="{{ $acceptMin }}"
                     data-max="{{ $acceptTotal }}"
                     style="font-weight:800;color:#111827;border-color:#fde68a">
            </div>
            <div class="deposit-error">Minimum is 50%: ₱{{ number_format($acceptMin, 2) }}</div>
            <div style="font-size:.7rem;color:#6b7280;margin-top:.25rem;margin-bottom:.75rem">
              ₱{{ number_format($acceptMin, 2) }} min · ₱{{ number_format($acceptTotal, 2) }} max · remainder due later
            </div>
            <button type="submit" class="btn btn-success w-100 fw-bold py-2 mb-2"
                    data-cs-confirm="Accept ₱{{ number_format($customOrder->admin_price,2) }} as final price?"
                    data-cs-title="Accept Final Price"
                    data-cs-ok="Accept Price"
                    data-cs-icon="bi-check-circle"
                    data-cs-icon-bg="#dcfce7"
                    data-cs-icon-color="#16a34a">
              <i class="bi bi-check-circle me-1"></i>Accept Price
            </button>
          </form>
          <form action="{{ route('guest.custom_order.cancel_price', $customOrder->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-danger w-100 fw-semibold py-2"
                    data-cs-confirm="Cancel this custom order?"
                    data-cs-title="Cancel Custom Order"
                    data-cs-ok="Cancel Order"
                    data-cs-ok-color="#dc2626"
                    data-cs-icon="bi-x-octagon"
                    data-cs-icon-bg="#fee2e2"
                    data-cs-icon-color="#dc2626">
              <i class="bi bi-x-circle me-1"></i>Cancel Order
            </button>
          </form>
        </div>
      </div>
      @endif

      @if($customOrder->progress_image || $customOrder->progress_message)
        <div class="p-2 rounded-2 mt-2" style="background:#f0f4ff;border-left:3px solid #6366f1">
          <div class="fw-semibold small mb-1" style="color:#4f46e5">📸 Progress Update from Baker</div>
          @if($customOrder->progress_image)
            <img src="{{ $customOrder->progress_image }}" class="chat-img" data-src="{{ $customOrder->progress_image }}"
                 style="max-height:160px;border-radius:.5rem;cursor:zoom-in;display:block;margin-bottom:6px"
                 onclick="openLightbox(this)">
          @endif
          @if($customOrder->progress_message)
            <div class="small text-muted">{{ str_replace('[custom_order:'.$customOrder->id.']','', $customOrder->progress_message) }}</div>
          @endif
        </div>
      @endif
    </div>
  </div>
  @endif

  {{-- Order Timeline --}}
  <div class="card mb-4">
    <div class="card-body p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--primary)"></i>Order Timeline</h6>
      @php
        $deliveryProofUrl = trim((string) ($order->delivery_photo ?? ''));
        if ($deliveryProofUrl !== '' && !str_starts_with($deliveryProofUrl, 'http') && !str_starts_with($deliveryProofUrl, '/')) {
            $deliveryProofUrl = asset($deliveryProofUrl);
        }
      @endphp
      @foreach($tracking->sortByDesc('created_at') as $t)
      <div class="d-flex gap-3 mb-3">
        <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0"></div>
        <div>
          <div class="fw-semibold small">{{ $t->status }}</div>
          @if($t->notes)<div class="text-muted small">{{ $t->notes }}</div>@endif
          @if($t->status === 'Delivered' && $deliveryProofUrl !== '')
            <button type="button" class="proof-view-btn" onclick="openDeliveryProof(@js($deliveryProofUrl))">
              <i class="bi bi-image"></i>View Proof
            </button>
          @endif
          @if($t->status === 'Refunded' && ($refund ?? null) && ($refund->status ?? '') === 'refunded' && !empty($refund->receipt_path))
            <div class="d-flex gap-2 flex-wrap mt-2">
              <button type="button"
                      class="btn btn-outline-primary btn-sm"
                      onclick="openRefundReceipt(@js($refund->receipt_path), @js('Refund Receipt'))">
                <i class="bi bi-eye me-1"></i>View Receipt
              </button>
              <button type="button"
                      class="btn btn-primary btn-sm refund-download-btn"
                      onclick="downloadRefundReceipt(this, @js(route('guest.refund_receipt_download', [$order->track_code, $refund->id])))">
                <i class="bi bi-download me-1"></i>Download
              </button>
            </div>
          @endif
              <div class="text-muted" style="font-size:clamp(.68rem,1.3vw,.72rem)">{{ \Carbon\Carbon::parse($t->created_at)->format('M d, Y g:i A') }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Back to Catalog --}}
  <div class="text-center mb-4">
    <a href="{{ route('catalog') }}" class="btn btn-outline-primary px-4">
      <i class="bi bi-cake2 me-2"></i>Browse More Cakes
    </a>
  </div>

  {{-- ── REVIEW SECTION (Delivered or Picked Up) ────────────────────────── --}}
  @php
    $existingReview = in_array($order->status, ['Delivered', 'Picked Up'])
      ? \Illuminate\Support\Facades\DB::table('order_reviews')->where('order_id', $order->id)->first()
      : null;
    $shouldPromptReview = in_array($order->status, ['Delivered', 'Picked Up']) && !$existingReview;
  @endphp
  @if(in_array($order->status, ['Delivered', 'Picked Up']))
  <div class="card mb-4 track-action-panel track-review-prompt" id="ratePanel" data-review-auto="{{ $shouldPromptReview ? '1' : '0' }}">
    <div class="card-body p-4">
      <div class="track-review-head">
        <div>
          <div class="track-review-kicker"><i class="bi bi-stars"></i> Order completed</div>
          <h6 class="track-review-title">Rate your cake experience</h6>
          <p class="track-review-copy">Your feedback helps the shop improve the cake, service, and delivery experience.</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="dismissAutoRatingPrompt()" title="Close"><i class="bi bi-x-lg"></i></button>
      </div>
      @if(session('msg'))
        <div class="alert alert-success border-0 py-2">{{ session('msg') }}</div>
      @endif
      @if($existingReview)
        <div class="text-center py-3">
          <div style="font-size:clamp(1.1rem,3vw,1.5rem);color:#f59e0b">
            @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i<=$existingReview->rating ? '-fill' : '' }}"></i>@endfor
          </div>
          <div class="text-muted small mt-1">You already reviewed this order. Thank you! 🎂</div>
          @if($existingReview->review)
            <div class="mt-2 small fst-italic">"{{ $existingReview->review }}"</div>
          @endif
        </div>
      @else
        <form action="{{ route('guest.review.store', $order->track_code) }}" method="POST" enctype="multipart/form-data" onsubmit="rememberRatingPromptReviewed()">
          @csrf
          <div class="mb-3 text-center">
            <div class="fw-semibold small mb-2">How was your cake?</div>
            <div class="d-flex justify-content-center gap-2">
              @for($i=1;$i<=5;$i++)
                <i class="bi bi-star review-star" data-val="{{ $i }}"
                   style="font-size:2rem;cursor:pointer;color:#d1d5db;transition:color .15s"
                   onclick="setRating({{ $i }})" onmouseover="hoverRating({{ $i }})" onmouseout="unhoverRating()"></i>
              @endfor
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="5">
          </div>
          {{-- Rider Rating --}}
          @if(!empty($order->rider_id))
          <div class="mb-3">
            <div class="fw-semibold small mb-2"><i class="bi bi-bicycle me-1" style="color:var(--primary)"></i>Rate your Rider <span class="text-muted fw-normal">(optional)</span></div>
            <div class="d-flex justify-content-center gap-2">
              @for($i=1;$i<=5;$i++)
                <i class="bi bi-star rider-review-star" data-val="{{ $i }}"
                   style="font-size:1.6rem;cursor:pointer;color:#d1d5db;transition:color .15s"
                   onclick="setRiderRating({{ $i }})" onmouseover="hoverRiderRating({{ $i }})" onmouseout="unhoverRiderRating()"></i>
              @endfor
            </div>
            <input type="hidden" name="rider_rating" id="riderRatingInput" value="">
          </div>
          @endif
              <div class="mb-3">
            <textarea class="form-control" name="review" rows="3"
                      placeholder="Tell us about your experience... (optional)"></textarea>
          </div>
          <div class="track-review-actions">
            <button type="submit" class="btn" style="background:var(--primary);color:#fff">
              <i class="bi bi-send me-1"></i>Submit Review
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="dismissAutoRatingPrompt()">Maybe later</button>
          </div>
        </form>
      @endif
    </div>
  </div>
  @endif

  {{-- ── CHAT SECTION ────────────────────────────────────────────────────── --}}
  <div class="card mb-4 track-action-panel" id="messagePanel" style="border-radius:14px;overflow:hidden">
    <div style="background:#fff;padding:14px 16px 10px;border-bottom:1px solid #f0f0f0">
      <div style="display:flex;align-items:center;gap:10px">
        <i class="bi bi-chat-dots" style="color:var(--primary);font-size:1.1rem"></i>
        <span class="fw-bold" style="font-size:.95rem;flex:1">Message the Seller</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeTrackPanel('messagePanel')" title="Close"><i class="bi bi-x-lg"></i></button>
      </div>
      <div style="font-size:.78rem;color:#9ca3af;margin-top:4px;padding-left:2px">
        Have questions about your order? Feel free to message us — we're happy to help!
</div>
    </div>
    <div class="chat-box-g" id="chatBox">
      <div class="text-center py-4" id="msgEmpty">
        <i class="bi bi-chat-heart d-block mb-2" style="font-size:2rem;color:#fce4ec"></i>
        <span class="text-muted small">No messages yet. Ask us anything!</span>
      </div>
    </div>
    <div class="g-preview-bar" id="gImgPreviewBar">
      <div class="g-img-cards" id="gImgCards"></div>
    </div>
    @if(!in_array($order->status, ['Cancelled','Delivered']))
    <div class="g-compose-wrap">
      <div id="guestUploadSummary" style="display:flex;align-items:center;flex-wrap:wrap;margin-bottom:.35rem"></div>
      <div class="g-compose-row">
        <label class="g-attach-btn" id="gAttachBtn" title="Attach images">
          <i class="bi bi-paperclip"></i>
          <input type="file" id="gImgFilePicker" accept="image/*" multiple hidden data-size-preview-target="guestUploadSummary" onchange="onGuestFilePick(this)">
        </label>
        <div contenteditable="true" id="msgInput" class="g-compose-box" data-placeholder="Type a message…"
             onkeydown="handleMsgEnter(event)"></div>
        <button class="g-send-btn" id="msgSendBtn" onclick="sendGuestMsg()" title="Send">
          <i class="bi bi-send-fill"></i>
        </button>
      </div>
    </div>
    @else
    <div class="text-muted small text-center p-3 border-top">
      <i class="bi bi-lock me-1"></i>Messaging closed for {{ strtolower($order->status) }} orders.
    </div>
    @endif
  </div>

</div>

@php
  $receiptCount = $receiptCount ?? ($recentReceipts ?? collect())->count();
  $canRateFromBubble = in_array($order->status, ['Delivered', 'Picked Up']);
@endphp

<div class="proof-viewer" id="deliveryProofViewer" aria-hidden="true">
  <div class="proof-viewer-bar">
    <button type="button" class="proof-icon-btn" onclick="closeDeliveryProof()" title="Back">
      <i class="bi bi-arrow-left"></i>
    </button>
    <div class="proof-viewer-title">Proof of Delivery</div>
    <button type="button" class="proof-icon-btn" id="deliveryProofDownload" onclick="downloadDeliveryProof()" title="Download">
      <i class="bi bi-download"></i>
    </button>
  </div>
  <div class="proof-stage" id="deliveryProofStage">
    <img src="" alt="Proof of delivery" id="deliveryProofImage">
  </div>
  <div class="proof-tools">
    <button type="button" class="proof-icon-btn" onclick="zoomDeliveryProof(-0.25)" title="Zoom out">
      <i class="bi bi-zoom-out"></i>
    </button>
    <div class="proof-zoom-pill" id="deliveryProofZoom">100%</div>
    <button type="button" class="proof-icon-btn" onclick="zoomDeliveryProof(0.25)" title="Zoom in">
      <i class="bi bi-zoom-in"></i>
    </button>
    <button type="button" class="proof-icon-btn" onclick="resetDeliveryProofZoom()" title="Reset zoom">
      <i class="bi bi-arrows-angle-contract"></i>
    </button>
  </div>
</div>

<div class="refund-receipt-viewer" id="refundReceiptViewer" aria-hidden="true" onclick="if(event.target === this) closeRefundReceipt()">
  <div class="refund-receipt-dialog" role="dialog" aria-modal="true" aria-labelledby="refundReceiptViewerTitle">
    <div class="refund-receipt-head">
      <div class="refund-receipt-title" id="refundReceiptViewerTitle">Refund Receipt</div>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeRefundReceipt()" title="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="refund-receipt-body">
      <img src="" alt="Refund receipt" id="refundReceiptViewerImage" loading="lazy" decoding="async">
    </div>
    <div class="refund-receipt-foot">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeRefundReceipt()">Close</button>
    </div>
  </div>
</div>

<div class="receipt-drawer-backdrop" id="trackActionBackdrop" onclick="closeAllTrackPopups()"></div>

<div class="track-fab-wrap" id="trackFab">
  <div class="track-fab-menu">
    @if($receiptCount > 0)
      <button type="button" class="track-fab-item" onclick="openReceiptDrawer()" title="Recent Receipts">
        <span class="track-fab-icon"><i class="bi bi-receipt-cutoff"></i></span>
        <span class="track-fab-label">Recent Receipts</span>
      </button>
    @endif
    <button type="button" class="track-fab-item" onclick="openTrackPanel('messagePanel')" title="Message Seller">
      <span class="track-fab-icon"><i class="bi bi-chat-dots"></i></span>
      <span class="track-fab-label">Message Seller</span>
    </button>
    @if($canRateFromBubble)
      <button type="button" class="track-fab-item" onclick="openTrackPanel('ratePanel')" title="Rate Your Cake">
        <span class="track-fab-icon"><i class="bi bi-star-fill"></i></span>
        <span class="track-fab-label">Rate Your Cake</span>
      </button>
    @endif
  </div>
  <button type="button" class="track-fab-main" onclick="toggleTrackFab()" aria-label="Open order actions">
    <i class="bi bi-plus-lg"></i>
    @if($receiptCount > 0)
      <span class="track-count-badge">{{ $receiptCount }}</span>
    @endif
  </button>
</div>

@if($receiptCount > 0)
<aside class="receipt-drawer" id="receiptDrawer" aria-hidden="true">
  <div class="p-3 d-flex align-items-center justify-content-between gap-2" style="border-bottom:1px solid #e5e7eb">
    <div>
      <div class="fw-bold"><i class="bi bi-receipt-cutoff me-2" style="color:var(--primary)"></i>Recent Receipts</div>
      <div class="small text-muted">Latest payments for this phone number.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeReceiptDrawer()" title="Close">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <div class="receipt-drawer-body">
    <a href="{{ route('guest.receipts', $order->track_code) }}" class="btn btn-outline-primary btn-sm w-100 mb-3">
      <i class="bi bi-list-ul me-1"></i>View All Receipts
    </a>
    <div class="d-flex flex-column gap-2">
      @foreach($recentReceipts as $r)
        @php
          $isLedger = isset($r->receipt_id);
          $paidAmount = $isLedger
            ? (float)$r->amount
            : ($r->payment_status === 'Paid' ? (float)$r->total_price : (float)$r->deposit_amount);
          $paidDate = $r->paid_at ?? $r->deposit_paid_at ?? $r->created_at;
          $typeLabel = $isLedger ? \App\Helpers\PaymentTransactionHelper::typeLabel($r->type) : $r->payment_status;
          $orderId = $isLedger ? $r->order_id : $r->id;
          $viewUrl = $isLedger
            ? route('guest.receipt_transaction', ['trackCode' => $r->track_code, 'transactionId' => $r->receipt_id])
            : route('guest.receipt', ['trackCode' => $r->track_code]);
        @endphp
        <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e5e7eb">
          <div class="d-flex justify-content-between gap-2">
            <div>
              <div class="fw-bold">Order #{{ $orderId }}</div>
              <div class="small text-muted">{{ $typeLabel }}</div>
            </div>
            <div class="fw-bold" style="color:#16a34a">PHP {{ number_format($paidAmount, 2) }}</div>
          </div>
          <div class="small text-muted mt-1">{{ $r->product_name }} • {{ \Carbon\Carbon::parse($paidDate)->format('M d, Y') }}</div>
          <a href="{{ $viewUrl }}" class="btn btn-primary btn-sm w-100 mt-2">
            <i class="bi bi-eye me-1"></i>View Receipt
          </a>
        </div>
      @endforeach
    </div>
  </div>
</aside>
@endif

<script>
const TRACK_CODE = '{{ $order->track_code }}';
const GUEST_NAME = '{{ addslashes($order->guest_name ?? "You") }}';
const TRACK_STATUS_URL = '{{ route('track.status', ['trackCode' => $order->track_code]) }}';
const TRACK_NOTIFICATIONS_URL = '{{ route('guest.mobile_notifications', ['trackCode' => $order->track_code]) }}';
const TRACK_NOTIFICATION_READ_URL = '{{ route('guest.mobile_notifications.read', ['trackCode' => $order->track_code, 'id' => '__ID__']) }}';
const TRACK_NOTIFICATION_READ_ALL_URL = '{{ route('guest.mobile_notifications.read_all', ['trackCode' => $order->track_code]) }}';
const TRACK_INITIAL_SNAPSHOT = {
  status: @json($order->status),
  payment_status: @json($order->payment_status),
  deposit_status: @json($order->deposit_status),
  total_price: @json((string) round($paymentTotalAmount, 2)),
  deposit_amount: @json((string) round($paymentDepositAmount, 2)),
  paid_at: @json((string) ($order->paid_at ?? '')),
  deposit_paid_at: @json((string) ($order->deposit_paid_at ?? '')),
  tracking_count: {{ (int) ($tracking->count() ?? 0) }},
  receipt_count: {{ (int) ($receiptCount ?? ($recentReceipts ?? collect())->count()) }},
  updated_at: @json((string) ($order->updated_at ?? '')),
  latest_tracking_at: @json((string) optional(($tracking ?? collect())->last())->created_at),
};
let deliveryProofScale = 1;
let deliveryProofStartDistance = 0;
let deliveryProofStartScale = 1;
const deliveryProofPointers = new Map();
let trackNotifications = [];
let trackNotificationsOffset = 0;
let trackNotificationsHasMore = false;
let trackNotificationsLoading = false;

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function escapeTrackText(value) {
  return String(value ?? '').replace(/[&<>"']/g, function (char) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
  });
}

function updateTrackBell(unread) {
  const btn = document.getElementById('trackBellBtn');
  const badge = document.getElementById('trackBellBadge');
  const count = Number(unread || 0);
  if (btn) btn.classList.toggle('has-unread', count > 0);
  if (badge) badge.textContent = count > 9 ? '9+' : String(count);
}

function renderTrackNotifications(reset = true) {
  const list = document.getElementById('trackNotifList');
  const more = document.getElementById('trackNotifMore');
  if (!list) return;
  if (!trackNotifications.length) {
    list.innerHTML = '<div class="track-notif-empty"><i class="bi bi-bell me-1"></i>No notifications yet.</div>';
  } else if (reset) {
    list.innerHTML = trackNotifications.map(function (item) {
      return '<button type="button" class="track-notif-item ' + (!item.is_read ? 'unread' : '') + '" onclick="openTrackNotificationDetail(' + item.id + ')">'
        + '<div class="track-notif-item-title">' + escapeTrackText(item.title) + '</div>'
        + '<div class="track-notif-item-msg">' + escapeTrackText(item.message || '') + '</div>'
        + '<div class="track-notif-time">' + escapeTrackText(item.created_label || item.created_at || '') + '</div>'
        + '</button>';
    }).join('');
  }
  if (more) more.style.display = trackNotificationsHasMore ? 'block' : 'none';
}

async function fetchTrackNotifications(reset = true) {
  if (trackNotificationsLoading) return;
  trackNotificationsLoading = true;
  const offset = reset ? 0 : trackNotificationsOffset;
  try {
    const res = await fetch(TRACK_NOTIFICATIONS_URL + '?limit=10&offset=' + offset, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    });
    if (!res.ok) throw new Error('Notification status ' + res.status);
    const data = await res.json();
    if (reset) trackNotifications = data.notifications || [];
    else trackNotifications = trackNotifications.concat(data.notifications || []);
    trackNotificationsOffset = trackNotifications.length;
    trackNotificationsHasMore = !!data.has_more;
    updateTrackBell(data.unread || 0);
    renderTrackNotifications(true);
  } catch (e) {
    const list = document.getElementById('trackNotifList');
    if (list && reset) list.innerHTML = '<div class="track-notif-empty">Notifications could not load.</div>';
  } finally {
    trackNotificationsLoading = false;
  }
}

function openTrackNotifications() {
  document.getElementById('trackNotifBackdrop')?.classList.add('is-open');
  document.getElementById('trackNotifPanel')?.classList.add('is-open');
  fetchTrackNotifications(true);
}

function closeTrackNotifications() {
  document.getElementById('trackNotifBackdrop')?.classList.remove('is-open');
  document.getElementById('trackNotifPanel')?.classList.remove('is-open');
  closeTrackNotificationDetail();
}

function loadMoreTrackNotifications() {
  fetchTrackNotifications(false);
}

async function openTrackNotificationDetail(id) {
  const item = trackNotifications.find(n => String(n.id) === String(id));
  if (!item) return;
  document.getElementById('trackNotifDetailTitle').textContent = item.title || 'Notification';
  document.getElementById('trackNotifDetailMessage').textContent = item.message || '';
  document.getElementById('trackNotifDetailTime').textContent = item.created_at || '';
  document.getElementById('trackNotifDetail')?.classList.add('is-open');

  if (!item.is_read) {
    item.is_read = true;
    renderTrackNotifications(true);
    try {
      await fetch(TRACK_NOTIFICATION_READ_URL.replace('__ID__', encodeURIComponent(id)), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
        },
      });
      fetchTrackNotifications(true);
    } catch (e) {}
  }
}

function closeTrackNotificationDetail() {
  document.getElementById('trackNotifDetail')?.classList.remove('is-open');
}

async function markAllTrackNotificationsRead() {
  try {
    await fetch(TRACK_NOTIFICATION_READ_ALL_URL, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
    });
  } catch (e) {}
  trackNotifications.forEach(n => n.is_read = true);
  updateTrackBell(0);
  renderTrackNotifications(true);
}

function setDeliveryProofLock(locked) {
  document.documentElement.classList.toggle('proof-viewer-open', locked);
  document.body.classList.toggle('proof-viewer-open', locked);
}

function renderDeliveryProofScale() {
  const img = document.getElementById('deliveryProofImage');
  const zoom = document.getElementById('deliveryProofZoom');
  if (img) img.style.setProperty('--proof-scale', deliveryProofScale.toFixed(2));
  if (zoom) zoom.textContent = Math.round(deliveryProofScale * 100) + '%';
}

function openDeliveryProof(src) {
  const viewer = document.getElementById('deliveryProofViewer');
  const img = document.getElementById('deliveryProofImage');
  if (!viewer || !img || !src) return;
  deliveryProofScale = 1;
  img.src = src;
  viewer.dataset.proofSrc = src;
  renderDeliveryProofScale();
  viewer.classList.add('is-open');
  viewer.setAttribute('aria-hidden', 'false');
  setDeliveryProofLock(true);
}

function closeDeliveryProof() {
  const viewer = document.getElementById('deliveryProofViewer');
  const img = document.getElementById('deliveryProofImage');
  if (!viewer) return;
  viewer.classList.remove('is-open');
  viewer.setAttribute('aria-hidden', 'true');
  deliveryProofPointers.clear();
  setDeliveryProofLock(false);
  if (img) img.src = '';
}

function setRefundReceiptLock(locked) {
  document.documentElement.classList.toggle('refund-receipt-open', locked);
  document.body.classList.toggle('refund-receipt-open', locked);
}

function openRefundReceipt(src, title) {
  const viewer = document.getElementById('refundReceiptViewer');
  const img = document.getElementById('refundReceiptViewerImage');
  const titleEl = document.getElementById('refundReceiptViewerTitle');
  if (!viewer || !img || !src) return;
  img.src = src;
  if (titleEl) titleEl.textContent = title || 'Refund Receipt';
  viewer.classList.add('is-open');
  viewer.setAttribute('aria-hidden', 'false');
  setRefundReceiptLock(true);
}

function closeRefundReceipt() {
  const viewer = document.getElementById('refundReceiptViewer');
  const img = document.getElementById('refundReceiptViewerImage');
  if (!viewer) return;
  viewer.classList.remove('is-open');
  viewer.setAttribute('aria-hidden', 'true');
  setRefundReceiptLock(false);
  if (img) img.src = '';
}

function downloadRefundReceipt(button, url) {
  if (!url || button?.classList.contains('is-loading')) return;
  const originalHtml = button?.innerHTML;
  if (button) {
    button.classList.add('is-loading');
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Downloading...';
  }

  const resetButton = () => {
    if (!button) return;
    button.classList.remove('is-loading');
    button.disabled = false;
    button.innerHTML = originalHtml || '<i class="bi bi-download me-1"></i>Download';
  };

  try {
    if (window.BerryBaseDownloads && typeof window.BerryBaseDownloads.download === 'function') {
      window.BerryBaseDownloads.download(url, 'refund-receipt-' + TRACK_CODE + '.jpg');
      setTimeout(resetButton, 900);
      return;
    }

    const frame = document.createElement('iframe');
    frame.style.display = 'none';
    frame.src = url;
    document.body.appendChild(frame);
    setTimeout(() => {
      frame.remove();
      resetButton();
      if (typeof cakeToast === 'function') cakeToast('Receipt download started.', 'success');
    }, 1300);
  } catch (e) {
    window.location.href = url;
    setTimeout(resetButton, 1300);
  }
}

function zoomDeliveryProof(delta) {
  deliveryProofScale = Math.min(4, Math.max(1, deliveryProofScale + delta));
  renderDeliveryProofScale();
}

function resetDeliveryProofZoom() {
  deliveryProofScale = 1;
  renderDeliveryProofScale();
}

async function downloadDeliveryProof() {
  const viewer = document.getElementById('deliveryProofViewer');
  const src = viewer?.dataset?.proofSrc || document.getElementById('deliveryProofImage')?.src;
  if (!src) return;
  const preferredName = 'delivery-proof-' + TRACK_CODE + '.jpg';

  if (window.BerryBaseDownloads && typeof window.BerryBaseDownloads.download === 'function') {
    window.BerryBaseDownloads.download(src, preferredName);
    return;
  }

  const button = document.getElementById('deliveryProofDownload');
  const originalHtml = button?.innerHTML;
  if (button) {
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
  }

  try {
    const response = await fetch(src, { cache: 'no-store' });
    if (!response.ok) throw new Error('Download failed');
    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const ext = (blob.type.split('/')[1] || 'jpg').replace('jpeg', 'jpg');
    link.href = objectUrl;
    link.download = 'delivery-proof-' + TRACK_CODE + '.' + ext;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
  } catch (e) {
    const link = document.createElement('a');
    link.href = src;
    link.download = preferredName;
    document.body.appendChild(link);
    link.click();
    link.remove();
  } finally {
    if (button) {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  }
}

function deliveryProofPointerDistance(a, b) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
}

function mountTrackFloatingUi() {
  ['trackActionBackdrop', 'receiptDrawer', 'trackFab', 'messagePanel', 'ratePanel', 'deliveryProofViewer', 'refundReceiptViewer'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) document.body.appendChild(el);
  });
}

document.addEventListener('DOMContentLoaded', mountTrackFloatingUi);
mountTrackFloatingUi();

const ratingPromptStorageKey = 'berrybase-rating-prompt-{{ $order->id }}';

function rememberRatingPromptReviewed() {
  try { sessionStorage.setItem(ratingPromptStorageKey, 'done'); } catch (e) {}
}

function dismissAutoRatingPrompt() {
  rememberRatingPromptReviewed();
  closeTrackPanel('ratePanel');
}

function maybeOpenRatingPrompt() {
  const panel = document.getElementById('ratePanel');
  if (!panel || panel.dataset.reviewAuto !== '1') return;
  try {
    if (sessionStorage.getItem(ratingPromptStorageKey)) return;
  } catch (e) {}

  window.setTimeout(() => {
    if (document.querySelector('.track-action-panel.is-open') || document.getElementById('receiptDrawer')?.classList.contains('is-open')) return;
    openTrackPanel('ratePanel');
  }, 650);
}

document.addEventListener('DOMContentLoaded', maybeOpenRatingPrompt);
maybeOpenRatingPrompt();

function setupDeliveryProofGestures() {
  const stage = document.getElementById('deliveryProofStage');
  if (!stage || stage.dataset.ready === '1') return;
  stage.dataset.ready = '1';

  stage.addEventListener('wheel', event => {
    if (!document.getElementById('deliveryProofViewer')?.classList.contains('is-open')) return;
    event.preventDefault();
    zoomDeliveryProof(event.deltaY < 0 ? 0.2 : -0.2);
  }, { passive: false });

  stage.addEventListener('pointerdown', event => {
    deliveryProofPointers.set(event.pointerId, event);
    stage.setPointerCapture?.(event.pointerId);
    if (deliveryProofPointers.size === 2) {
      const points = [...deliveryProofPointers.values()];
      deliveryProofStartDistance = deliveryProofPointerDistance(points[0], points[1]);
      deliveryProofStartScale = deliveryProofScale;
    }
  });

  stage.addEventListener('pointermove', event => {
    if (!deliveryProofPointers.has(event.pointerId)) return;
    deliveryProofPointers.set(event.pointerId, event);
    if (deliveryProofPointers.size === 2 && deliveryProofStartDistance > 0) {
      const points = [...deliveryProofPointers.values()];
      const nextDistance = deliveryProofPointerDistance(points[0], points[1]);
      deliveryProofScale = Math.min(4, Math.max(1, deliveryProofStartScale * (nextDistance / deliveryProofStartDistance)));
      renderDeliveryProofScale();
    }
  });

  ['pointerup', 'pointercancel', 'pointerleave'].forEach(type => {
    stage.addEventListener(type, event => deliveryProofPointers.delete(event.pointerId));
  });
}

document.addEventListener('DOMContentLoaded', setupDeliveryProofGestures);
setupDeliveryProofGestures();

function setTrackModalLock(locked) {
  document.documentElement.classList.toggle('track-modal-open', locked);
  document.body.classList.toggle('track-modal-open', locked);
}

function toggleTrackFab(force) {
  const fab = document.getElementById('trackFab');
  if (!fab) return;
  const open = typeof force === 'boolean' ? force : !fab.classList.contains('is-open');
  fab.classList.toggle('is-open', open);
}

function openTrackPanel(id) {
  const panel = document.getElementById(id);
  if (!panel) return;
  mountTrackFloatingUi();
  panel.classList.add('is-open');
  document.getElementById('trackActionBackdrop')?.classList.add('is-open');
  setTrackModalLock(true);
  toggleTrackFab(false);
  if (id === 'messagePanel') {
    setTimeout(() => document.getElementById('msgInput')?.focus(), 320);
  }
}

function closeTrackPanel(id) {
  document.getElementById(id)?.classList.remove('is-open');
  if (!document.querySelector('.track-action-panel.is-open') && !document.getElementById('receiptDrawer')?.classList.contains('is-open')) {
    setTrackModalLock(false);
    document.getElementById('trackActionBackdrop')?.classList.remove('is-open');
  }
}

function openReceiptDrawer() {
  mountTrackFloatingUi();
  document.getElementById('receiptDrawer')?.classList.add('is-open');
  document.getElementById('trackActionBackdrop')?.classList.add('is-open');
  document.getElementById('receiptDrawer')?.setAttribute('aria-hidden', 'false');
  setTrackModalLock(true);
  toggleTrackFab(false);
}

function closeReceiptDrawer() {
  document.getElementById('receiptDrawer')?.classList.remove('is-open');
  document.getElementById('receiptDrawer')?.setAttribute('aria-hidden', 'true');
  if (!document.querySelector('.track-action-panel.is-open')) {
    setTrackModalLock(false);
    document.getElementById('trackActionBackdrop')?.classList.remove('is-open');
  }
}

function closeAllTrackPopups() {
  document.querySelectorAll('.track-action-panel.is-open').forEach(panel => panel.classList.remove('is-open'));
  closeDeliveryProof();
  closeRefundReceipt();
  closeReceiptDrawer();
  document.getElementById('trackActionBackdrop')?.classList.remove('is-open');
  setTrackModalLock(false);
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    if (document.getElementById('deliveryProofViewer')?.classList.contains('is-open')) {
      closeDeliveryProof();
      return;
    }
    if (document.getElementById('refundReceiptViewer')?.classList.contains('is-open')) {
      closeRefundReceipt();
      return;
    }
    toggleTrackFab(false);
    closeAllTrackPopups();
  }
});

// ── Star Rating ──────────────────────────────────────────────────────────
let selectedRating = 5;
function setRating(val) {
  selectedRating = val;
  const ri = document.getElementById('ratingInput');
  if (ri) ri.value = val;
  document.querySelectorAll('.review-star').forEach((s, i) => {
    s.className = 'bi review-star ' + (i < val ? 'bi-star-fill' : 'bi-star');
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function hoverRating(val) {
  document.querySelectorAll('.review-star').forEach((s, i) => {
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function unhoverRating() { setRating(selectedRating); }

// ── Rider Rating ─────────────────────────────────────────────────────────
let selectedRiderRating = 0;
function setRiderRating(val) {
  selectedRiderRating = val;
  const rri = document.getElementById('riderRatingInput');
  if (rri) rri.value = val;
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.className = 'bi rider-review-star ' + (i < val ? 'bi-star-fill' : 'bi-star');
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function hoverRiderRating(val) {
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
function unhoverRiderRating() {
  document.querySelectorAll('.rider-review-star').forEach((s, i) => {
    s.style.color = i < selectedRiderRating ? '#f59e0b' : '#d1d5db';
  });
}

// ── Image compression ─────────────────────────────────────────────────────
const G_MAX_PX  = 1200;
const G_QUALITY = 0.82;

async function compressImage(file) {
  return new Promise(resolve => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.naturalWidth, h = img.naturalHeight;
      const needsResize = w > G_MAX_PX || h > G_MAX_PX;
      if (needsResize) {
        const r = Math.min(G_MAX_PX / w, G_MAX_PX / h);
        w = Math.round(w * r); h = Math.round(h * r);
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(blob => {
        const useOrig = blob.size >= file.size && !needsResize;
        resolve({
          file    : useOrig ? file : new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type:'image/jpeg' }),
          origSize: file.size,
          newSize : useOrig ? file.size : blob.size,
          origW   : img.naturalWidth, origH: img.naturalHeight,
          newW    : w, newH: h,
        });
      }, 'image/jpeg', G_QUALITY);
    };
    img.src = url;
  });
}

function fmtGSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

let gPicked = [];
let gPickId = 0;

function renderGuestUploadTotalSummary() {
  const target = document.getElementById('guestUploadSummary');
  const picker = document.getElementById('gImgFilePicker');
  if (!target || !picker) return;

  if (!gPicked.length) {
    if (typeof window.csClearFileUploadPreview === 'function') window.csClearFileUploadPreview(picker);
    else target.innerHTML = '';
    return;
  }

  const ready = gPicked.filter(x => !x.compressing && x.file);
  const pending = gPicked.length - ready.length;
  const original = ready.reduce((sum, item) => sum + (item.origSize || item.file?.size || 0), 0);
  const optimized = ready.reduce((sum, item) => sum + (item.newSize || item.file?.size || 0), 0);
  const label = gPicked.length + (gPicked.length === 1 ? ' image' : ' images');
  const previewId = picker.dataset.sizePreviewId || ('img-size-preview-' + Math.random().toString(36).slice(2));
  picker.dataset.sizePreviewId = previewId;
  target.querySelectorAll('[id^="img-size-preview-"]').forEach(node => { if (node.id !== previewId) node.remove(); });

  let preview = document.getElementById(previewId);
  if (!preview) {
    preview = document.createElement('div');
    preview.id = previewId;
    target.appendChild(preview);
  }

  preview.style.display = 'block';
  preview.innerHTML = '<div class="cs-upload-summary">' +
    '<span class="cs-upload-pill is-muted"><i class="bi bi-images"></i><span>' + label + ': <strong>' + fmtGSize(original) + '</strong></span></span>' +
    '<span class="cs-upload-arrow">-&gt;</span>' +
    '<span class="cs-upload-pill is-muted"><i class="bi bi-lightning-charge-fill"></i><span>Optimized: <strong>~' + fmtGSize(optimized) + '</strong></span></span>' +
    (pending ? '<span class="cs-upload-pill is-warning"><i class="bi bi-hourglass-split"></i><span>' + pending + ' optimizing</span></span>' : '') +
    '</div>';
}

async function onGuestFilePick(input) {
  const files = Array.from(input.files);
  input.value = '';
  if (!files.length) return;
  const bar   = document.getElementById('gImgPreviewBar');
  const cards = document.getElementById('gImgCards');
  bar.style.display = 'block';
  document.getElementById('gAttachBtn').classList.add('active');

  for (const file of files) {
    const id = ++gPickId;
    gPicked.push({ id, file: null, preview: null, compressing: true });
    const card = document.createElement('div');
    card.className = 'g-img-card g-compressing';
    card.id = 'gcard-' + id;
    card.innerHTML = '<div style="width:96px;height:72px;background:#f0f0f0;display:flex;align-items:center;justify-content:center"><span class="spinner-border spinner-border-sm text-secondary"></span></div><div class="g-img-card-info">Compressing…</div>';
    cards.appendChild(card);
    renderGuestUploadTotalSummary();

    const result     = await compressImage(file);
    const pct        = Math.round((1 - result.newSize / result.origSize) * 100);
    const previewUrl = URL.createObjectURL(result.file);
    const entry      = gPicked.find(x => x.id === id);
    if (!entry) { URL.revokeObjectURL(previewUrl); continue; }
    Object.assign(entry, { file: result.file, preview: previewUrl, compressing: false,
      origSize: result.origSize, newSize: result.newSize,
      origW: result.origW, origH: result.origH, newW: result.newW, newH: result.newH });
    renderGuestUploadTotalSummary();

    const sizeInfo = result.origSize !== result.newSize
      ? `${fmtGSize(result.origSize)} → <span class="g-img-card-size">${fmtGSize(result.newSize)}</span> <span style="color:#16a34a">(${pct}% smaller)</span>`
      : `<span class="g-img-card-size">${fmtGSize(result.newSize)}</span>`;
    card.className = 'g-img-card';
    card.innerHTML = `<img src="${previewUrl}" onclick="openGuestImgPv('${previewUrl}')" title="${result.origW}×${result.origH} → ${result.newW}×${result.newH}"><div class="g-img-card-info">${sizeInfo}</div><button class="g-img-card-rm" onclick="removeGImg(${id})" title="Remove">✕</button>`;
  }
}

function removeGImg(id) {
  const idx = gPicked.findIndex(x => x.id === id);
  if (idx !== -1) { if (gPicked[idx].preview) URL.revokeObjectURL(gPicked[idx].preview); gPicked.splice(idx, 1); }
  const c = document.getElementById('gcard-' + id);
  if (c) c.remove();
  if (!gPicked.length) clearGPicker();
  else renderGuestUploadTotalSummary();
}

function clearGPicker(revoke = true) {
  if (revoke) gPicked.forEach(x => { if (x.preview) URL.revokeObjectURL(x.preview); });
  gPicked = [];
  document.getElementById('gImgCards').innerHTML = '';
  document.getElementById('gImgPreviewBar').style.display = 'none';
  const ab = document.getElementById('gAttachBtn');
  if (ab) ab.classList.remove('active');
  const picker = document.getElementById('gImgFilePicker');
  if (picker) {
    picker.value = '';
    if (typeof window.csClearFileUploadPreview === 'function') window.csClearFileUploadPreview(picker);
    else {
      const summary = picker.dataset.sizePreviewId ? document.getElementById(picker.dataset.sizePreviewId) : null;
      if (summary) { summary.innerHTML = ''; summary.style.display = 'none'; }
      const target = picker.dataset.sizePreviewTarget ? document.getElementById(picker.dataset.sizePreviewTarget) : null;
      if (target) target.innerHTML = '';
    }
  }
}

function openGuestImgPv(src) {
  const el = document.createElement('img');
  el.src = src; el.dataset.src = src;
  openLightbox(el);
}

function messageImageGridHtml(imgs) {
  const cleanImgs = imgs.filter(Boolean);
  if (!cleanImgs.length) return '';

  const visible = cleanImgs.slice(0, 4);
  const moreCount = cleanImgs.length - 3;
  const gridClass = 'img-count-' + Math.min(cleanImgs.length, 4);
  const galleryJson = escAttr(JSON.stringify(cleanImgs));
  const items = visible.map((src, index) => {
    const safeSrc = escAttr(src);
    if (index === 3 && cleanImgs.length > 4) {
      return `<button type="button" class="bbl-img-more-g chat-img" data-src="${safeSrc}" data-gallery-index="3" title="View ${cleanImgs.length} images">
        <span>+${moreCount}</span>
      </button>`;
    }

    return `<button type="button" class="bbl-img-tile-g chat-img" data-src="${safeSrc}" data-gallery-index="${index}" title="View image ${index + 1}">
      <img src="${safeSrc}" alt="" onerror="this.closest('button').style.display='none'">
    </button>`;
  }).join('');

  return `<div class="bbl-imgs-g ${gridClass}" data-lightbox-gallery data-gallery-sources="${galleryJson}">${items}</div>`;
}

function openGuestMessageImage(el) {
  const gallery = el.closest('.bbl-imgs-g');
  let sources = [];
  if (gallery && gallery.dataset.gallerySources) {
    try { sources = JSON.parse(gallery.dataset.gallerySources); } catch(e) { sources = []; }
  }
  const index = parseInt(el.dataset.galleryIndex || '0', 10);
  openLightbox(el, sources, Number.isFinite(index) ? index : 0);
}

// ── Messaging ────────────────────────────────────────────────────────────
let rendered = [];
let guestMsgSending = false;

function renderMessages(msgs) {
  const thread = document.getElementById('chatBox');
  const empty  = document.getElementById('msgEmpty');
  if (!msgs.length) return;
  if (empty) empty.style.display = 'none';

  msgs.filter(m => !rendered.includes(m.id)).forEach(m => {
    rendered.push(m.id);
    const isMine = !m.is_admin;
    let imgs = [];
    if (m.image_path) {
      try { const d = JSON.parse(m.image_path); imgs = Array.isArray(d) ? d : [m.image_path]; }
      catch(e) { imgs = [m.image_path]; }
    }
    const initials = isMine ? (GUEST_NAME.charAt(0).toUpperCase() || 'Y') : 'B';
    const imgHtml = messageImageGridHtml(imgs);
    const row = document.createElement('div');
    row.className = 'msg-row-g' + (isMine ? ' mine' : '');
    row.innerHTML = `
      <div class="msg-av-g${isMine?' mine':''}">${initials}</div>
      <div class="msg-grp-g${isMine?' mine':''}">
        <div class="sndr-lbl-g${isMine?' mine':''}">${escapeHtml(m.name)}</div>
        <div class="bbl-g ${isMine?'mine':'theirs'}">
          ${m.message?`<div style="white-space:pre-wrap">${escapeHtml(m.message)}</div>`:''}
          ${imgHtml}
        </div>
        <div class="bbl-time-g${isMine?' mine':''}">
          ${m.created_at}${isMine?' <span style="opacity:.65">✓</span>':''}
        </div>
      </div>`;
    thread.appendChild(row);
  });
  thread.scrollTop = thread.scrollHeight;
}

async function pollMessages() {
  try {
    const r = await fetch('/track/' + TRACK_CODE + '/messages');
    const d = await r.json();
    renderMessages(d.messages || []);
  } catch(e) {}
}

function handleMsgEnter(e) {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    sendGuestMsg();
  }
}

async function sendGuestMsg() {
  if (guestMsgSending) return;
  const input   = document.getElementById('msgInput');
  const text    = input ? input.innerText.trim() : '';
  const picker  = document.getElementById('gImgFilePicker');
  if (gPicked.some(x => x.compressing) || (typeof window.csIsFileUploadOptimizing === 'function' && window.csIsFileUploadOptimizing(picker))) {
    if (typeof cakeToast === 'function') cakeToast('Please wait for images to finish optimizing.', 'warning');
    return;
  }
  const hasImgs = gPicked.filter(x => !x.compressing && x.file).length > 0;
  if (!text && !hasImgs) return;

  guestMsgSending = true;
  const btn = document.getElementById('msgSendBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>'; }

  const fd = new FormData();
  fd.append('_token', '{{ csrf_token() }}');
  if (text) fd.append('message', text);
  gPicked.filter(x => !x.compressing && x.file).forEach(x => fd.append('images[]', x.file));

  try {
    const r  = await fetch('/track/' + TRACK_CODE + '/messages', { method:'POST', body:fd });
    const ct = r.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      console.error('HTTP ' + r.status, (await r.text()).substring(0, 400));
      cakeToast('Server error. Please try again.', 'error');
      return;
    }
    const d = await r.json();
    if (d.ok) {
      if (d.message) renderMessages([d.message]);
      else await pollMessages();
      if (input) input.innerHTML = '';
      clearGPicker();
    } else {
      cakeToast(d.error || 'Failed to send.', 'error');
    }
  } catch(e) {
    cakeToast('Network error. Please try again.', 'error');
  } finally {
    guestMsgSending = false;
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i>'; }
  }
}

function escAttr(s) {
  return s ? String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;') : '';
}

function escapeHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

function setupDepositAmountForms() {
  document.querySelectorAll('.deposit-amount-form').forEach(form => {
    const input = form.querySelector('.deposit-amount-input');
    const error = form.querySelector('.deposit-error');
    const button = form.querySelector('button[type="submit"]');
    const min = parseFloat(form.dataset.min || input?.dataset.min || '0');
    const max = parseFloat(form.dataset.max || input?.dataset.max || '0');

    if (!input || !error) return;

    const btnLabel = form.dataset.btnLabel || 'Pay Deposit via GCash';
    const setButtonCopy = () => {
      const amount = parseFloat(input.value || '0');
      if (button) {
        button.innerHTML = '<i class="bi bi-phone-fill me-2"></i>' + btnLabel;
        button.dataset.csConfirm = 'Pay ₱' + (amount || min).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' via GCash?\\n\\nYou will be redirected to PayMongo.';
        button.dataset.csTitle = 'Confirm Payment';
        button.dataset.csOk = 'Pay Now';
        button.dataset.csIcon = 'bi-phone-fill';
        button.dataset.csIconBg = '#fef3c7';
        button.dataset.csIconColor = '#d97706';
      }
    };

    const showError = message => {
      input.classList.add('is-invalid');
      error.textContent = message;
      error.classList.remove('show');
      void error.offsetWidth;
      error.classList.add('show');
      if (typeof cakeToast === 'function') cakeToast(message, 'error');
      if (navigator.vibrate) navigator.vibrate(120);
    };

    const clearError = () => {
      input.classList.remove('is-invalid');
      error.classList.remove('show');
    };

    input.addEventListener('input', () => {
      let value = input.value.replace(/[^\d.]/g, '');
      const firstDot = value.indexOf('.');
      if (firstDot !== -1) {
        value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
      }
      input.value = value;
      clearError();
      setButtonCopy();
    });

    input.addEventListener('blur', () => {
      const amount = parseFloat(input.value || '0');
      if (!amount) input.value = min.toFixed(2);
      else input.value = Math.min(amount, max).toFixed(2);
      setButtonCopy();
    });

    button?.addEventListener('click', event => {
      const amount = parseFloat(input.value || '0');
      if (!amount || amount < min) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Minimum payment is 50%: ₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
      }
    });

    form.addEventListener('submit', event => {
      const amount = parseFloat(input.value || '0');
      if (!amount || amount < min) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Minimum payment is 50%: ₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
        return false;
      }
      if (max && amount > max) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showError('Payment cannot exceed the order total: ₱' + max.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.');
        input.focus();
        return false;
      }
      input.value = amount.toFixed(2);
      setButtonCopy();
      return true;
    }, true);

    setButtonCopy();
  });
}

setupDepositAmountForms();

// Initial load + poll every 8 seconds
pollMessages();
setInterval(pollMessages, 8000);
fetchTrackNotifications(true);
setInterval(function () {
  if (!document.hidden) fetchTrackNotifications(true);
}, 30000);

let trackSnapshot = { ...TRACK_INITIAL_SNAPSHOT };
let trackStatusTimer = null;
let trackReloading = false;

function trackStatusChanged(next) {
  return ['status','payment_status','deposit_status','total_price','deposit_amount','paid_at','deposit_paid_at','tracking_count','receipt_count','updated_at','latest_tracking_at']
    .some(key => String(trackSnapshot[key] ?? '') !== String(next[key] ?? ''));
}

function scheduleTrackStatusPoll(delay) {
  if (trackStatusTimer) clearTimeout(trackStatusTimer);
  if (!delay || trackReloading) return;
  trackStatusTimer = setTimeout(pollTrackStatus, delay);
}

async function pollTrackStatus() {
  if (document.hidden) {
    scheduleTrackStatusPoll(30000);
    return;
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 7000);

  try {
    const res = await fetch(TRACK_STATUS_URL, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store',
      signal: controller.signal
    });
    if (!res.ok) throw new Error('status ' + res.status);
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || 'Unable to check status.');

    if (trackStatusChanged(data)) {
      trackReloading = true;
      cakeToast('Order status updated. Refreshing...', 'success');
      setTimeout(() => window.location.reload(), 900);
      return;
    }

    trackSnapshot = { ...trackSnapshot, ...data };
    scheduleTrackStatusPoll(data.interval_ms || 25000);
  } catch (e) {
    scheduleTrackStatusPoll(45000);
  } finally {
    clearTimeout(timeout);
  }
}

document.addEventListener('visibilitychange', () => {
  if (!document.hidden && !trackReloading) pollTrackStatus();
});

if (!['Delivered', 'Picked Up', 'Cancelled'].includes(trackSnapshot.status)) {
  scheduleTrackStatusPoll(['Preparing', 'Out for Delivery', 'Pickup'].includes(trackSnapshot.status) ? 10000 : 25000);
}

// Auto-highlight stars on load
setRating(5);
</script>

@push('scripts')
@endpush


@endsection
