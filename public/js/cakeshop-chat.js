/* CakeShop Admin Chat JS - requires window.CAKE_ADMIN to be set */

(function() {
  'use strict';

  if (!window.CAKE_ADMIN) return; // Only run for admin

  var cfg = window.CAKE_ADMIN;
  var mcOpen       = false;
  var mcOrderId    = null;
  var mcPollTimer  = null;
  var mcLastMsgId  = 0;

  // Create mini chat UI
  function buildChatUI() {
    var existing = document.getElementById('miniChat');
    if (existing) return;

    var div = document.createElement('div');
    div.id = 'miniChat';
    div.style.cssText = 'display:none;position:fixed;bottom:100px;right:24px;width:min(320px, calc(100vw - 48px));' +
      'height:min(420px, calc(100dvh - 132px));max-height:480px;background:#fff;border-radius:1.2rem;box-shadow:0 8px 40px rgba(0,0,0,.18);' +
      'z-index:9990;flex-direction:column;overflow:hidden;';
    div.innerHTML =
      '<div id="mcHeader" style="padding:12px 16px;background:var(--primary);color:#fff;display:flex;align-items:center;gap:8px">' +
        '<i class="bi bi-chat-dots"></i>' +
        '<span id="mcTitle" style="flex:1;font-weight:700;font-size:.9rem">Messages</span>' +
        '<button onclick="closeMiniChat()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem"><i class="bi bi-x-lg"></i></button>' +
      '</div>' +
      '<div id="mcList" style="flex:1;overflow-y:auto;min-height:0;padding:8px 0"></div>' +
      '<div id="mcThread" style="display:none;flex-direction:column;flex:1;overflow:hidden">' +
        '<div id="mcMsgs" style="flex:1;overflow-y:auto;min-height:0;padding:8px 12px;display:flex;flex-direction:column;gap:6px"></div>' +
        '<div style="padding:8px;border-top:1px solid #f3f4f6;display:flex;gap:6px">' +
          '<input id="mcInput" type="text" placeholder="Type a message..." style="flex:1;border:1px solid #e5e7eb;border-radius:.5rem;padding:6px 10px;font-size:.82rem">' +
          '<button onclick="mcSend()" style="background:var(--primary);border:none;color:#fff;border-radius:.5rem;padding:6px 12px;cursor:pointer"><i class="bi bi-send"></i></button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(div);

    // Enter key to send
    div.querySelector('#mcInput').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') mcSend();
    });
  }

  // Build floating bubble button
  function buildBubble() {
    var existing = document.getElementById('mcBubbleBtn');
    if (existing) return;
    var btn = document.createElement('button');
    btn.id = 'mcBubbleBtn';
    btn.title = 'Messages';
    btn.style.cssText = 'position:fixed;bottom:clamp(14px, 4vw, 24px);right:clamp(14px, 4vw, 24px);width:56px;height:56px;border-radius:50%;' +
      'background:var(--primary);border:none;color:#fff;font-size:1.4rem;cursor:pointer;z-index:9989;' +
      'box-shadow:0 4px 20px rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;';
    btn.innerHTML = '<i class="bi bi-chat-dots-fill"></i>';
    btn.addEventListener('click', toggleMiniChat);
    document.body.appendChild(btn);
  }

  // Toggle mini chat
  window.toggleMiniChat = function() {
    var chat = document.getElementById('miniChat');
    if (!chat) return;
    mcOpen = !mcOpen;
    chat.style.display = mcOpen ? 'flex' : 'none';
    if (mcOpen) loadThreadList();
  };

  window.closeMiniChat = function() {
    mcOpen = false;
    var chat = document.getElementById('miniChat');
    if (chat) chat.style.display = 'none';
    clearInterval(mcPollTimer);
  };

  // Load thread list
  function loadThreadList() {
    var list = document.getElementById('mcList');
    var thread = document.getElementById('mcThread');
    if (!list || !thread) return;
    thread.style.display = 'none';
    list.style.display = 'block';
    document.getElementById('mcTitle').textContent = 'Messages';

    fetch(cfg.dataUrl)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.threads || !d.threads.length) {
          list.innerHTML = '<div class="text-center text-muted py-3 small">No messages yet.</div>';
          return;
        }
        list.innerHTML = '';
        d.threads.forEach(function(t) {
          var el = document.createElement('div');
          el.style.cssText = 'padding:10px 14px;border-bottom:1px solid #f3f4f6;cursor:pointer;';
          el.innerHTML =
            '<div style="display:flex;justify-content:space-between;align-items:center">' +
              '<span style="font-weight:700;font-size:.82rem">' + escHtml(t.fullname || 'Guest') + '</span>' +
              (t.unread_count > 0 ? '<span style="background:var(--primary);color:#fff;border-radius:999px;padding:1px 7px;font-size:.65rem;font-weight:700">' + t.unread_count + '</span>' : '') +
            '</div>' +
            '<div style="font-size:.75rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escHtml(t.last_message || '') + '</div>';
          el.addEventListener('click', function() { openThread(t.order_id, t.fullname || 'Guest'); });
          list.appendChild(el);
        });
      })
      .catch(function() { list.innerHTML = '<div class="text-center text-muted py-3 small">Failed to load.</div>'; });
  }

  // Open a specific thread
  window.openThread = function(orderId, name) {
    mcOrderId = orderId;
    var list   = document.getElementById('mcList');
    var thread = document.getElementById('mcThread');
    if (!list || !thread) return;
    list.style.display = 'none';
    thread.style.display = 'flex';
    document.getElementById('mcTitle').textContent = name || 'Chat';
    document.getElementById('mcMsgs').innerHTML = '';
    mcLastMsgId = 0;
    pollMessages();
    clearInterval(mcPollTimer);
    mcPollTimer = setInterval(pollMessages, 5000);
  };

  function pollMessages() {
    if (!mcOrderId) return;
    fetch(cfg.dataUrl + '?order_id=' + mcOrderId)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.messages) return;
        var msgs = document.getElementById('mcMsgs');
        d.messages.forEach(function(m) {
          if (m.id <= mcLastMsgId) return;
          mcLastMsgId = m.id;
          var isAdmin = m.sender_role === 'admin';
          var row = document.createElement('div');
          row.style.cssText = 'display:flex;flex-direction:column;align-items:' + (isAdmin ? 'flex-end' : 'flex-start') + ';';
          var bubble = document.createElement('div');
          bubble.style.cssText = 'max-width:80%;padding:6px 10px;border-radius:' +
            (isAdmin ? '12px 0 12px 12px' : '0 12px 12px 12px') +
            ';background:' + (isAdmin ? 'var(--primary)' : '#f3f4f6') +
            ';color:' + (isAdmin ? '#fff' : '#111') + ';font-size:.8rem;word-break:break-word;';
          if (m.message) bubble.textContent = m.message;
          row.appendChild(bubble);
          if (msgs) { msgs.appendChild(row); msgs.scrollTop = msgs.scrollHeight; }
        });
        // Mark as read
        if (d.messages.length) {
          fetch(cfg.markUrl + '/' + mcOrderId, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Content-Type': 'application/json' },
            body: JSON.stringify({})
          }).catch(function(){});
        }
      })
      .catch(function(){});
  }

  window.mcSend = function() {
    var input = document.getElementById('mcInput');
    if (!input || !mcOrderId) return;
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    var fd = new FormData();
    fd.append('_token', cfg.csrf);
    fd.append('message', msg);
    fd.append('order_id', mcOrderId);
    fetch(cfg.sendUrl, { method: 'POST', body: fd })
      .then(function() { pollMessages(); })
      .catch(function(){});
  };

  function escHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // Init on DOM ready
  document.addEventListener('DOMContentLoaded', function() {
    buildChatUI();
    buildBubble();
  });

})();
