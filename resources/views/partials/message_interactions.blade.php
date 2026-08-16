@once
<style>
.msg-interaction-wrap{position:relative}
.msg-interaction-bubble{position:relative}
.msg-action-btn{position:absolute;top:8px;width:30px;height:30px;border:0;border-radius:50%;background:#fff;color:#64748b;box-shadow:0 8px 24px rgba(15,23,42,.16);display:none;align-items:center;justify-content:center;z-index:2}
.msg-interaction-wrap:hover .msg-action-btn,.msg-interaction-wrap.is-actions-open .msg-action-btn{display:flex}
.msg-action-btn.mine{left:8px}.msg-action-btn.theirs{right:8px}
.msg-reply-quote{border-left:3px solid currentColor;background:rgba(255,255,255,.18);border-radius:9px;padding:6px 8px;margin-bottom:6px;font-size:.72rem;line-height:1.3;opacity:.92;max-width:100%;min-width:0}
.msg-reply-quote.theirs{background:#f8fafc;color:#475569}.msg-reply-name{font-weight:900;font-size:.68rem}.msg-reply-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:min(240px,100%)}
.reply-compose-preview{display:none;align-items:center;gap:10px;border:1px solid rgba(var(--primary-rgb),.2);background:var(--primary-bg,#fff7ed);border-radius:12px;padding:8px 10px;margin-bottom:8px;color:#334155}
.reply-compose-preview.is-visible{display:flex}.reply-compose-bar{width:3px;align-self:stretch;border-radius:999px;background:var(--primary)}
.reply-compose-body{min-width:0;flex:1}.reply-compose-label{font-size:.68rem;font-weight:900;color:var(--primary);text-transform:uppercase}.reply-compose-text{font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.reply-compose-close{width:28px;height:28px;border:0;border-radius:50%;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center}
.message-reactions{display:flex;gap:4px;flex-wrap:wrap;margin-top:6px;max-width:100%}.message-reactions.mine{justify-content:flex-end}
.reaction-pill{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:2px 6px;font-size:.72rem;line-height:1;display:inline-flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(15,23,42,.06);animation:reactionPop .26s cubic-bezier(.2,1.5,.4,1)}
.reaction-pill.mine{border-color:rgba(var(--primary-rgb),.35);background:var(--primary-bg,#fff7ed)}
.mc-bubble .msg-reply-quote{padding:5px 7px;margin-bottom:5px;font-size:.66rem;border-radius:8px}.mc-bubble .msg-reply-name{font-size:.62rem}.mc-bubble .msg-reply-text{font-size:.66rem;max-width:150px}.mc-bubble .message-reactions{margin-top:5px;gap:3px}.mc-bubble .reaction-pill{padding:1px 5px;font-size:.66rem}.bbl-g .message-reactions,.bubble .message-reactions{width:100%}
.cake-reaction-tray{position:fixed;left:0;top:0;transform:scale(.94);transform-origin:center;z-index:9500;background:linear-gradient(180deg,#fff,#fffaf5);border:1px solid rgba(226,232,240,.95);border-radius:18px;padding:8px;display:none;gap:6px;box-shadow:0 24px 70px rgba(15,23,42,.26),inset 0 1px 0 rgba(255,255,255,.9);max-width:calc(100vw - 16px);overflow-x:auto;opacity:0;pointer-events:none;will-change:transform,opacity}
.cake-reaction-tray:before{content:"";position:absolute;inset:2px 2px auto 2px;height:45%;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(255,255,255,0));pointer-events:none}
.cake-reaction-tray.is-open{display:flex;opacity:1;pointer-events:auto;animation:trayIn .18s cubic-bezier(.18,1.45,.36,1) forwards}
.cake-reaction-tray.is-bursting{display:flex;pointer-events:none;animation:trayBurst .24s ease forwards}
.cake-reaction-tray.is-shelved{display:flex;opacity:0;pointer-events:none;transform:scale(.94)}
.cake-reaction-tray.is-floating-back{animation:trayInflate .24s cubic-bezier(.18,1.55,.36,1) forwards}
.cake-react-btn{width:52px;height:58px;border:0;border-radius:14px;background:#f8fafc;color:#111827;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;transition:transform .15s,background .15s}
.cake-react-btn:hover{transform:translateY(-4px);background:var(--primary-bg,#fff7ed)}.cake-react-label{font-size:.54rem;font-weight:900;line-height:1;color:#64748b}
.cake-face{--cake:#f9a8d4;--frost:#fff7ed;--eye:#3f2a1d;--mouth:#3f2a1d;position:relative;width:30px;height:27px;border-radius:8px 8px 10px 10px;background:linear-gradient(180deg,var(--frost) 0 30%,var(--cake) 31% 100%);box-shadow:inset 0 -3px rgba(0,0,0,.08),0 2px 7px rgba(15,23,42,.16);animation:cakeFloat 1.6s ease-in-out infinite}
.cake-face:before{content:"";position:absolute;left:4px;right:4px;top:-5px;height:10px;border-radius:999px;background:var(--frost);box-shadow:7px 1px 0 -1px var(--frost),14px 0 0 -2px var(--frost)}
.cake-face .eye{position:absolute;top:11px;width:4px;height:5px;border-radius:50%;background:var(--eye)}.cake-face .eye.l{left:8px}.cake-face .eye.r{right:8px}
.cake-face .mouth{position:absolute;left:50%;top:18px;width:9px;height:5px;transform:translateX(-50%);border:2px solid var(--mouth);border-top:0;border-radius:0 0 999px 999px}
.cake-face.sweet{--cake:#f9a8d4;--frost:#fff7ed}.cake-face.yummy{--cake:#facc15;--frost:#fde68a}.cake-face.love{--cake:#fb7185;--frost:#ffe4e6}.cake-face.wow{--cake:#93c5fd;--frost:#dbeafe}.cake-face.sad{--cake:#c4b5fd;--frost:#ede9fe}.cake-face.burnt{--cake:#78350f;--frost:#292524;--eye:#fef3c7;--mouth:#fef3c7}.cake-face.nope{--cake:#fdba74;--frost:#ffedd5}
.cake-face.love .eye{width:6px;height:6px;background:#be123c;transform:rotate(45deg);border-radius:1px}.cake-face.love .eye:before,.cake-face.love .eye:after{content:"";position:absolute;width:6px;height:6px;border-radius:50%;background:#be123c}.cake-face.love .eye:before{left:-3px}.cake-face.love .eye:after{top:-3px}
.cake-face.wow:after{content:"";position:absolute;left:50%;top:-15px;width:5px;height:13px;transform:translateX(-50%);border-radius:4px;background:linear-gradient(#f97316 0 45%,#facc15 46% 100%);box-shadow:0 0 10px #f59e0b}.cake-face.wow .mouth{width:7px;height:8px;border:0;background:#1e293b;border-radius:50%;top:17px}
.cake-face.sad .mouth{border-top:2px solid var(--mouth);border-bottom:0;border-radius:999px 999px 0 0;top:20px}.cake-face.sad:after{content:"";position:absolute;right:5px;top:15px;width:3px;height:6px;border-radius:50%;background:#60a5fa;animation:tearDrop 1.2s ease-in-out infinite}
.cake-face.burnt .eye{height:2px;border-radius:2px;transform:rotate(18deg)}.cake-face.burnt .eye.r{transform:rotate(-18deg)}.cake-face.burnt .mouth{width:10px;height:2px;border:0;background:#fef3c7;border-radius:2px;top:20px}.cake-face.burnt:after{content:"";position:absolute;right:-2px;top:-10px;width:8px;height:12px;border-radius:999px 999px 999px 0;background:#ef4444;box-shadow:0 -2px 0 #facc15 inset;animation:flameFlicker .45s ease-in-out infinite}
.cake-face.nope .mouth{width:12px;height:2px;border:0;background:#7c2d12;border-radius:2px;top:19px}.cake-face.nope:after{content:"!";position:absolute;right:-5px;top:-8px;width:13px;height:13px;border-radius:50%;background:#f97316;color:#fff;font-size:10px;font-weight:900;line-height:13px;text-align:center}
.cake-face.tiny{width:18px;height:16px;border-radius:5px 5px 6px 6px;animation:none}.cake-face.tiny:before{left:3px;right:3px;top:-3px;height:6px}.cake-face.tiny .eye{top:7px;width:2.5px;height:3px}.cake-face.tiny .eye.l{left:5px}.cake-face.tiny .eye.r{right:5px}.cake-face.tiny .mouth{top:11px;width:6px;height:3px;border-width:1.5px}.cake-face.tiny.wow:after,.cake-face.tiny.burnt:after,.cake-face.tiny.nope:after,.cake-face.tiny.sad:after{display:none}
.cake-react-btn[data-reaction="burnt"] .cake-face{animation:cakeAngry .55s ease-in-out infinite}.cake-react-btn[data-reaction="sad"] .cake-face{animation:cakeSad 1.1s ease-in-out infinite}.cake-react-btn[data-reaction="nope"] .cake-face{animation:cakeShake .42s ease-in-out infinite}.cake-react-btn[data-reaction="wow"] .cake-face{animation:candlePop .85s ease-in-out infinite}
.cake-action-row{display:flex;gap:6px;border-right:1px solid #e5e7eb;padding-right:6px;margin-right:2px}.cake-action-btn{width:46px;height:50px;border:0;border-radius:14px;background:#fff7ed;color:var(--primary);display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:.72rem;font-weight:900}
@keyframes trayIn{0%{opacity:0;transform:scale(.82)}70%{opacity:1;transform:scale(1.04)}100%{opacity:1;transform:scale(1)}}@keyframes trayInflate{0%{opacity:0;transform:scale(.62)}65%{opacity:1;transform:scale(1.06)}100%{opacity:1;transform:scale(1)}}@keyframes trayBurst{0%{opacity:1;transform:scale(1)}45%{opacity:.9;transform:scale(1.1)}100%{opacity:0;transform:scale(.22)}}@keyframes reactionPop{from{opacity:0;transform:scale(.4)}to{opacity:1;transform:scale(1)}}@keyframes cakeFloat{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-4px) rotate(2deg)}}@keyframes cakeAngry{0%,100%{transform:translateX(0) rotate(0)}25%{transform:translateX(-2px) rotate(-8deg)}75%{transform:translateX(2px) rotate(8deg)}}@keyframes cakeSad{0%,100%{transform:translateY(0);filter:saturate(.8)}50%{transform:translateY(3px);filter:saturate(.45)}}@keyframes cakeShake{0%,100%{transform:rotate(0)}25%{transform:rotate(-10deg)}75%{transform:rotate(10deg)}}@keyframes candlePop{0%,100%{transform:scale(1)}50%{transform:scale(1.18);filter:drop-shadow(0 0 6px #f59e0b)}}@keyframes tearDrop{0%,45%{opacity:0;transform:translateY(-2px)}55%{opacity:1}100%{opacity:0;transform:translateY(5px)}}@keyframes flameFlicker{0%,100%{transform:rotate(-8deg) scale(1)}50%{transform:rotate(8deg) scale(1.12)}}
@media(max-width:640px){.msg-action-btn{display:none!important}.cake-reaction-tray{left:8px!important;right:8px!important;top:auto!important;transform:translateY(12px) scale(.98);justify-content:center;overflow-x:auto}.cake-reaction-tray.is-open{animation:trayMobileIn .18s cubic-bezier(.18,1.45,.36,1) forwards}.cake-reaction-tray.is-floating-back{animation:trayMobileInflate .24s cubic-bezier(.18,1.55,.36,1) forwards}@keyframes trayMobileIn{0%{opacity:0;transform:translateY(16px) scale(.86)}70%{opacity:1;transform:translateY(-2px) scale(1.02)}100%{opacity:1;transform:translateY(0) scale(1)}}@keyframes trayMobileInflate{0%{opacity:0;transform:translateY(18px) scale(.62)}65%{opacity:1;transform:translateY(-2px) scale(1.04)}100%{opacity:1;transform:translateY(0) scale(1)}}}
</style>
<div class="cake-reaction-tray" id="cakeReactionTray" aria-hidden="true"></div>
<script>
window.BerryMessageInteractions = window.BerryMessageInteractions || (function(){
  const reactions = [
    ['sweet','Sweet'], ['yummy','Yummy'], ['love','Love'],
    ['wow','Wow'], ['sad','Sad'], ['burnt','Angry'], ['nope','Nope']
  ];
  let activeRow = null, cfg = {}, pressTimer = null, trayShelved = false, trayBurstTimer = null;
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  function cakeFace(type, tiny = false) {
    const safe = String(type || 'sweet').replace(/[^a-z0-9_-]/gi, '');
    return `<span class="cake-face ${safe}${tiny ? ' tiny' : ''}" aria-hidden="true"><span class="eye l"></span><span class="eye r"></span><span class="mouth"></span></span>`;
  }
  function replyHtml(reply, mine) {
    if (!reply) return '';
    return `<div class="msg-reply-quote ${mine ? 'mine' : 'theirs'}"><div class="msg-reply-name">${esc(reply.label || reply.sender_role || 'Message')}</div><div class="msg-reply-text">${esc(reply.snippet || 'Message')}</div></div>`;
  }
  function reactionsHtml(items, mine) {
    if (!Array.isArray(items) || !items.length) return '';
    return `<div class="message-reactions ${mine ? 'mine' : ''}">` + items.map(r => `<span class="reaction-pill ${r.mine ? 'mine' : ''}" title="${esc(r.label)}">${cakeFace(r.reaction, true)}<strong>${Number(r.count||0)}</strong></span>`).join('') + `</div>`;
  }
  function messageBubble(row) {
    return row?.querySelector('.bubble,.bbl-g,.mc-bubble,.customer-msg-bubble,.admin-msg-bubble') || null;
  }
  function setReply(row) {
    const input = document.querySelector(cfg.replyInput || '[data-reply-input]');
    const preview = document.querySelector(cfg.replyPreview || '[data-reply-preview]');
    if (!input || !preview || !row) return;
    input.value = row.dataset.msgId || '';
    preview.classList.add('is-visible');
    preview.querySelector('[data-reply-preview-name]').textContent = row.dataset.replySender || 'Message';
    preview.querySelector('[data-reply-preview-text]').textContent = row.dataset.replySnippet || 'Photo message';
    closeTray();
    const composer = document.querySelector(cfg.composer || 'textarea,[contenteditable="true"]');
    setTimeout(() => composer?.focus(), 80);
  }
  function clearReply() {
    const input = document.querySelector(cfg.replyInput || '[data-reply-input]');
    const preview = document.querySelector(cfg.replyPreview || '[data-reply-preview]');
    if (input) input.value = '';
    if (preview) preview.classList.remove('is-visible');
  }
  async function react(type) {
    if (!activeRow) return;
    const id = activeRow.dataset.msgId;
    const template = activeRow.dataset.reactUrl || cfg.reactUrl;
    if (!template) return;
    const url = template.replace('__ID__', encodeURIComponent(id));
    const res = await fetch(url, {method:'POST',headers:{'X-CSRF-TOKEN':cfg.csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({reaction:type})});
    const data = await res.json();
    if (data.ok) updateReactions(id, data.reactions || []);
    closeTray();
  }
  function updateReactions(id, items) {
    document.querySelectorAll(`[data-msg-id="${CSS.escape(String(id))}"]`).forEach(row => {
      let target = row.querySelector('[data-reactions]');
      const bubble = messageBubble(row);
      if (!target) {
        target = document.createElement('div');
        target.dataset.reactions = '1';
      }
      if (!bubble) return;
      if (target.parentElement !== bubble) bubble.appendChild(target);
      target.innerHTML = reactionsHtml(items, row.classList.contains('mine') || row.classList.contains('justify-content-end') || row.classList.contains('me'));
    });
  }
  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }
  function rectsOverlap(a, b, pad = 0) {
    return a.left < b.right + pad && a.right > b.left - pad && a.top < b.bottom + pad && a.bottom > b.top - pad;
  }
  function visibleRect(el) {
    if (!el) return null;
    const styles = window.getComputedStyle(el);
    if (styles.display === 'none' || styles.visibility === 'hidden') return null;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 ? rect : null;
  }
  function messageViewport(row) {
    return row?.closest('#miniChatMessages,#chatBox,.chat-box,.chat-box-g') || null;
  }
  function intersectionRect(a, b) {
    const left = Math.max(a.left, b.left);
    const right = Math.min(a.right, b.right);
    const top = Math.max(a.top, b.top);
    const bottom = Math.min(a.bottom, b.bottom);
    return {
      width: Math.max(0, right - left),
      height: Math.max(0, bottom - top)
    };
  }
  function bubbleVisibleEnough(anchorRect, viewportRect) {
    const visible = intersectionRect(anchorRect, viewportRect);
    const requiredHeight = Math.min(Math.max(anchorRect.height * 0.55, 24), 96);
    const requiredWidth = Math.min(Math.max(anchorRect.width * 0.5, 36), 120);
    return visible.height >= requiredHeight && visible.width >= requiredWidth;
  }
  function setTrayPosition(tray, left, top) {
    tray.style.setProperty('left', left + 'px', 'important');
    tray.style.setProperty('top', top + 'px', 'important');
    tray.style.setProperty('right', 'auto', 'important');
    tray.style.setProperty('bottom', 'auto', 'important');
  }
  function positionInsideMessageViewport(row, tray, anchor, gap, margin) {
    const viewport = messageViewport(row);
    const viewportRect = visibleRect(viewport);
    if (!viewportRect) return false;

    const innerMargin = Math.max(6, margin);
    const maxWidth = Math.max(180, viewportRect.width - (innerMargin * 2));
    tray.style.maxWidth = Math.min(window.innerWidth - 16, maxWidth) + 'px';

    const anchorRect = anchor.getBoundingClientRect();
    if (!rectsOverlap(anchorRect, viewportRect, 0)) {
      shelveTray(tray);
      return true;
    }

    const trayRect = tray.getBoundingClientRect();
    const minLeft = viewportRect.left + innerMargin;
    const maxLeft = viewportRect.right - trayRect.width - innerMargin;
    const minTop = viewportRect.top + innerMargin;
    const maxTop = viewportRect.bottom - trayRect.height - innerMargin;

    if (maxLeft < minLeft || maxTop < minTop) {
      shelveTray(tray);
      return true;
    }

    if (!bubbleVisibleEnough(anchorRect, viewportRect)) {
      shelveTray(tray);
      return true;
    }

    const isMine = row.classList.contains('mine') || row.classList.contains('me') || row.classList.contains('justify-content-end');
    const centeredLeft = anchorRect.left + (anchorRect.width / 2) - (trayRect.width / 2);
    const centeredTop = anchorRect.top + (anchorRect.height / 2) - (trayRect.height / 2);
    const candidates = [
      { left: isMine ? anchorRect.left - trayRect.width - gap : anchorRect.right + gap, top: centeredTop },
      { left: centeredLeft, top: anchorRect.top - trayRect.height - gap },
      { left: centeredLeft, top: anchorRect.bottom + gap }
    ];

    const placement = candidates.map(candidate => {
      const left = clamp(candidate.left, minLeft, maxLeft);
      const top = clamp(candidate.top, minTop, maxTop);
      return {
        left,
        top,
        rect: { left, top, right: left + trayRect.width, bottom: top + trayRect.height }
      };
    }).find(candidate => !rectsOverlap(candidate.rect, anchorRect, 4));

    if (!placement) {
      shelveTray(tray);
      return true;
    }

    setTrayPosition(tray, placement.left, placement.top);
    unshelveTray(tray);
    return true;
  }
  function bottomBlockerRects() {
    const selectors = [
      cfg.replyPreview || '[data-reply-preview]',
      cfg.composer || 'textarea,[contenteditable="true"]',
      '#threadForm',
      '.compose-wrap',
      '.g-compose-wrap',
      '#threadImgPreview',
      '#imgPreviewBar',
      '#gImgPreviewBar',
      '#threadUploadSummary',
      '#guestUploadSummary',
      '.mc-fullscreen-reminder'
    ];
    return [...new Set(selectors)]
      .flatMap(selector => selector ? [...document.querySelectorAll(selector)] : [])
      .map(visibleRect)
      .filter(Boolean);
  }
  function shelveTray(tray) {
    if (trayShelved || !tray) return;
    trayShelved = true;
    window.clearTimeout(trayBurstTimer);
    tray.classList.remove('is-floating-back');
    tray.classList.add('is-bursting');
    tray.setAttribute('aria-hidden','true');
    trayBurstTimer = window.setTimeout(() => {
      if (!trayShelved) return;
      tray.classList.remove('is-bursting');
      tray.classList.add('is-shelved');
    }, 230);
  }
  function unshelveTray(tray) {
    if (!trayShelved || !tray) return;
    trayShelved = false;
    window.clearTimeout(trayBurstTimer);
    tray.classList.remove('is-bursting','is-shelved');
    tray.classList.add('is-open','is-floating-back');
    tray.setAttribute('aria-hidden','false');
    window.setTimeout(() => tray.classList.remove('is-floating-back'), 260);
  }
  function positionTray(row, tray) {
    if (!row || !tray) return;
    const anchor = messageBubble(row);
    if (!anchor) return;
    const gap = 8;
    const margin = 8;
    if (positionInsideMessageViewport(row, tray, anchor, gap, margin)) return;

    tray.style.maxWidth = 'calc(100vw - 16px)';
    if (window.matchMedia('(max-width: 640px)').matches) {
      tray.style.left = '';
      tray.style.top = '';
      tray.style.right = '';
      const blockers = bottomBlockerRects();
      const firstBottomBlockerTop = blockers.length
        ? Math.min(...blockers.map(rect => rect.top))
        : window.innerHeight;
      tray.style.bottom = Math.max(18, window.innerHeight - firstBottomBlockerTop + gap) + 'px';
      const trayRect = tray.getBoundingClientRect();
      const anchorRect = anchor.getBoundingClientRect();
      const unsafe = rectsOverlap(trayRect, anchorRect, 4) || trayRect.top < margin;
      unsafe ? shelveTray(tray) : unshelveTray(tray);
      return;
    }
    tray.style.right = 'auto';
    tray.style.bottom = 'auto';
    const rect = anchor.getBoundingClientRect();
    const trayRect = tray.getBoundingClientRect();
    const isMine = row.classList.contains('mine') || row.classList.contains('me') || row.classList.contains('justify-content-end');
    let left = isMine ? rect.left - trayRect.width - gap : rect.right + gap;
    if (left < margin || left + trayRect.width > window.innerWidth - margin) {
      left = rect.left + (rect.width / 2) - (trayRect.width / 2);
    }
    const top = rect.top + (rect.height / 2) - (trayRect.height / 2);
    tray.style.left = clamp(left, margin, window.innerWidth - trayRect.width - margin) + 'px';
    tray.style.top = clamp(top, margin, window.innerHeight - trayRect.height - margin) + 'px';
    const placedRect = tray.getBoundingClientRect();
    const unsafe = rectsOverlap(placedRect, rect, 4) || bottomBlockerRects().some(blocker => rectsOverlap(placedRect, blocker, 6));
    unsafe ? shelveTray(tray) : unshelveTray(tray);
  }
  function positionOpenTray() {
    const tray = document.getElementById('cakeReactionTray');
    if (activeRow && tray?.classList.contains('is-open')) positionTray(activeRow, tray);
  }
  function openTray(row) {
    activeRow = row;
    const tray = document.getElementById('cakeReactionTray');
    if (!tray) return;
    tray.innerHTML = `<div class="cake-action-row"><button type="button" class="cake-action-btn" data-reply-action><i class="bi bi-reply-fill"></i><span>Reply</span></button></div>` +
      reactions.map(r => `<button type="button" class="cake-react-btn" data-reaction="${r[0]}" title="${r[1]}">${cakeFace(r[0])}<span class="cake-react-label">${r[1]}</span></button>`).join('');
    trayShelved = false;
    window.clearTimeout(trayBurstTimer);
    tray.classList.remove('is-bursting','is-shelved','is-floating-back');
    tray.classList.add('is-open');
    tray.setAttribute('aria-hidden','false');
    positionTray(row, tray);
    tray.querySelector('[data-reply-action]')?.addEventListener('click', () => setReply(row));
    tray.querySelectorAll('[data-reaction]').forEach(btn => btn.addEventListener('click', () => react(btn.dataset.reaction)));
  }
  function closeTray() {
    const tray = document.getElementById('cakeReactionTray');
    window.clearTimeout(trayBurstTimer);
    tray?.classList.remove('is-open','is-bursting','is-shelved','is-floating-back');
    tray?.setAttribute('aria-hidden','true');
    trayShelved = false;
    activeRow = null;
  }
  function bindRow(row) {
    if (!row || row.dataset.interactionsBound === '1') return;
    const bubble = messageBubble(row);
    if (!bubble) return;
    row.dataset.interactionsBound = '1';
    row.classList.add('msg-interaction-wrap');
    bubble.classList.add('msg-interaction-bubble');
    const mine = row.classList.contains('mine') || row.classList.contains('me') || row.classList.contains('justify-content-end');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'msg-action-btn ' + (mine ? 'mine' : 'theirs');
    btn.innerHTML = '<i class="bi bi-reply"></i>';
    btn.title = 'Reply or react';
    btn.addEventListener('click', e => { e.stopPropagation(); openTray(row); });
    bubble.appendChild(btn);
    row.addEventListener('contextmenu', e => { e.preventDefault(); openTray(row); });
    row.addEventListener('touchstart', () => { pressTimer = setTimeout(() => openTray(row), 420); }, {passive:true});
    ['touchend','touchmove','touchcancel'].forEach(ev => row.addEventListener(ev, () => clearTimeout(pressTimer), {passive:true}));
  }
  document.addEventListener('click', e => { if (!e.target.closest('#cakeReactionTray,.msg-action-btn')) closeTray(); });
  window.addEventListener('resize', positionOpenTray, {passive:true});
  document.addEventListener('scroll', positionOpenTray, {passive:true, capture:true});
  return {
    init(options){ cfg = options || {}; document.querySelectorAll('[data-msg-id]').forEach(bindRow); },
    bindRow, clearReply, replyHtml, reactionsHtml, updateReactions
  };
})();
</script>
@endonce
