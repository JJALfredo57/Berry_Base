@once
<style>
.msg-interaction-wrap{position:relative}
.msg-action-btn{position:absolute;top:50%;transform:translateY(-50%);width:30px;height:30px;border:0;border-radius:50%;background:#fff;color:#64748b;box-shadow:0 8px 24px rgba(15,23,42,.16);display:none;align-items:center;justify-content:center;z-index:2}
.msg-interaction-wrap:hover .msg-action-btn,.msg-interaction-wrap.is-actions-open .msg-action-btn{display:flex}
.msg-action-btn.mine{left:-36px}.msg-action-btn.theirs{right:-36px}
.msg-reply-quote{border-left:3px solid currentColor;background:rgba(255,255,255,.18);border-radius:9px;padding:6px 8px;margin-bottom:6px;font-size:.72rem;line-height:1.3;opacity:.92;max-width:100%}
.msg-reply-quote.theirs{background:#f8fafc;color:#475569}.msg-reply-name{font-weight:900;font-size:.68rem}.msg-reply-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:240px}
.reply-compose-preview{display:none;align-items:center;gap:10px;border:1px solid rgba(var(--primary-rgb),.2);background:var(--primary-bg,#fff7ed);border-radius:12px;padding:8px 10px;margin-bottom:8px;color:#334155}
.reply-compose-preview.is-visible{display:flex}.reply-compose-bar{width:3px;align-self:stretch;border-radius:999px;background:var(--primary)}
.reply-compose-body{min-width:0;flex:1}.reply-compose-label{font-size:.68rem;font-weight:900;color:var(--primary);text-transform:uppercase}.reply-compose-text{font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.reply-compose-close{width:28px;height:28px;border:0;border-radius:50%;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center}
.message-reactions{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px}.message-reactions.mine{justify-content:flex-end}
.reaction-pill{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:2px 6px;font-size:.72rem;line-height:1;display:inline-flex;align-items:center;gap:3px;box-shadow:0 2px 8px rgba(15,23,42,.06);animation:reactionPop .26s cubic-bezier(.2,1.5,.4,1)}
.reaction-pill.mine{border-color:rgba(var(--primary-rgb),.35);background:var(--primary-bg,#fff7ed)}
.cake-reaction-tray{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%) scale(.94);z-index:1100;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:8px;display:none;gap:6px;box-shadow:0 24px 70px rgba(15,23,42,.26)}
.cake-reaction-tray.is-open{display:flex;animation:trayIn .16s ease forwards}
.cake-react-btn{width:46px;height:50px;border:0;border-radius:14px;background:#f8fafc;color:#111827;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;transition:transform .15s,background .15s}
.cake-react-btn:hover{transform:translateY(-4px);background:var(--primary-bg,#fff7ed)}.cake-react-icon{font-size:1.25rem;line-height:1;animation:cakeFloat 1.6s ease-in-out infinite}.cake-react-label{font-size:.54rem;font-weight:900;line-height:1;color:#64748b}
.cake-react-btn[data-reaction="burnt"] .cake-react-icon{animation:cakeAngry .55s ease-in-out infinite}.cake-react-btn[data-reaction="sad"] .cake-react-icon{animation:cakeSad 1.1s ease-in-out infinite}.cake-react-btn[data-reaction="nope"] .cake-react-icon{animation:cakeShake .42s ease-in-out infinite}.cake-react-btn[data-reaction="wow"] .cake-react-icon{animation:candlePop .85s ease-in-out infinite}
.cake-action-row{display:flex;gap:6px;border-right:1px solid #e5e7eb;padding-right:6px;margin-right:2px}.cake-action-btn{width:46px;height:50px;border:0;border-radius:14px;background:#fff7ed;color:var(--primary);display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:.72rem;font-weight:900}
@keyframes trayIn{to{transform:translate(-50%,-50%) scale(1)}}@keyframes reactionPop{from{opacity:0;transform:scale(.4)}to{opacity:1;transform:scale(1)}}@keyframes cakeFloat{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-4px) rotate(2deg)}}@keyframes cakeAngry{0%,100%{transform:translateX(0) rotate(0)}25%{transform:translateX(-2px) rotate(-8deg)}75%{transform:translateX(2px) rotate(8deg)}}@keyframes cakeSad{0%,100%{transform:translateY(0);filter:saturate(.8)}50%{transform:translateY(3px);filter:saturate(.45)}}@keyframes cakeShake{0%,100%{transform:rotate(0)}25%{transform:rotate(-10deg)}75%{transform:rotate(10deg)}}@keyframes candlePop{0%,100%{transform:scale(1)}50%{transform:scale(1.22);filter:drop-shadow(0 0 6px #f59e0b)}}
@media(max-width:640px){.msg-action-btn{display:none!important}.cake-reaction-tray{left:8px;right:8px;bottom:18px;top:auto;transform:translateY(12px);justify-content:center;overflow-x:auto}.cake-reaction-tray.is-open{animation:trayMobileIn .16s ease forwards}@keyframes trayMobileIn{to{transform:translateY(0)}}}
</style>
<div class="cake-reaction-tray" id="cakeReactionTray" aria-hidden="true"></div>
<script>
window.BerryMessageInteractions = window.BerryMessageInteractions || (function(){
  const reactions = [
    ['sweet','🍰','Sweet'], ['yummy','🧁','Yummy'], ['love','💗','Love'],
    ['wow','🕯️','Wow'], ['sad','🍪','Sad'], ['burnt','🔥','Angry'], ['nope','⚠️','Nope']
  ];
  let activeRow = null, cfg = {}, pressTimer = null;
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  function replyHtml(reply, mine) {
    if (!reply) return '';
    return `<div class="msg-reply-quote ${mine ? 'mine' : 'theirs'}"><div class="msg-reply-name">${esc(reply.label || reply.sender_role || 'Message')}</div><div class="msg-reply-text">${esc(reply.snippet || 'Message')}</div></div>`;
  }
  function reactionsHtml(items, mine) {
    if (!Array.isArray(items) || !items.length) return '';
    return `<div class="message-reactions ${mine ? 'mine' : ''}">` + items.map(r => `<span class="reaction-pill ${r.mine ? 'mine' : ''}" title="${esc(r.label)}"><span>${esc(r.icon)}</span><strong>${Number(r.count||0)}</strong></span>`).join('') + `</div>`;
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
    if (!activeRow || !cfg.reactUrl) return;
    const id = activeRow.dataset.msgId;
    const url = cfg.reactUrl.replace('__ID__', encodeURIComponent(id));
    const res = await fetch(url, {method:'POST',headers:{'X-CSRF-TOKEN':cfg.csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({reaction:type})});
    const data = await res.json();
    if (data.ok) updateReactions(id, data.reactions || []);
    closeTray();
  }
  function updateReactions(id, items) {
    const row = document.querySelector(`[data-msg-id="${CSS.escape(String(id))}"]`);
    if (!row) return;
    let target = row.querySelector('[data-reactions]');
    if (!target) {
      target = document.createElement('div');
      target.dataset.reactions = '1';
      row.querySelector('.msg-group,.customer-msg-bubble,.admin-msg-bubble,.bubble')?.appendChild(target);
    }
    target.innerHTML = reactionsHtml(items, row.classList.contains('mine') || row.classList.contains('justify-content-end'));
  }
  function openTray(row) {
    activeRow = row;
    const tray = document.getElementById('cakeReactionTray');
    if (!tray) return;
    tray.innerHTML = `<div class="cake-action-row"><button type="button" class="cake-action-btn" data-reply-action><i class="bi bi-reply-fill"></i><span>Reply</span></button></div>` +
      reactions.map(r => `<button type="button" class="cake-react-btn" data-reaction="${r[0]}" title="${r[2]}"><span class="cake-react-icon">${r[1]}</span><span class="cake-react-label">${r[2]}</span></button>`).join('');
    tray.classList.add('is-open');
    tray.setAttribute('aria-hidden','false');
    tray.querySelector('[data-reply-action]')?.addEventListener('click', () => setReply(row));
    tray.querySelectorAll('[data-reaction]').forEach(btn => btn.addEventListener('click', () => react(btn.dataset.reaction)));
  }
  function closeTray() {
    document.getElementById('cakeReactionTray')?.classList.remove('is-open');
    activeRow = null;
  }
  function bindRow(row) {
    if (!row || row.dataset.interactionsBound === '1') return;
    row.dataset.interactionsBound = '1';
    row.classList.add('msg-interaction-wrap');
    const mine = row.classList.contains('mine') || row.classList.contains('justify-content-end');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'msg-action-btn ' + (mine ? 'mine' : 'theirs');
    btn.innerHTML = '<i class="bi bi-reply"></i>';
    btn.title = 'Reply or react';
    btn.addEventListener('click', e => { e.stopPropagation(); openTray(row); });
    row.appendChild(btn);
    row.addEventListener('contextmenu', e => { e.preventDefault(); openTray(row); });
    row.addEventListener('touchstart', () => { pressTimer = setTimeout(() => openTray(row), 420); }, {passive:true});
    ['touchend','touchmove','touchcancel'].forEach(ev => row.addEventListener(ev, () => clearTimeout(pressTimer), {passive:true}));
  }
  document.addEventListener('click', e => { if (!e.target.closest('#cakeReactionTray,.msg-action-btn')) closeTray(); });
  return {
    init(options){ cfg = options || {}; document.querySelectorAll('[data-msg-id]').forEach(bindRow); },
    bindRow, clearReply, replyHtml, reactionsHtml, updateReactions
  };
})();
</script>
@endonce
