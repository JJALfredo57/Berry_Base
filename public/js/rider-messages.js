(() => {
  const thread = document.getElementById('riderThread');
  if (!thread) return;
  const list = document.getElementById('messageList');
  const form = document.getElementById('messageForm');
  const textarea = form.elements.message;
  const imageInput = form.elements.image;
  const sendButton = form.querySelector('[type="submit"]');
  const error = document.getElementById('messageError');
  const status = document.getElementById('threadStatus');
  const attachment = document.getElementById('messageAttachment');
  const viewer = document.getElementById('messageImageViewer');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  let attachmentUrl;
  let loading;
  let sending = false;
  let available = false;
  let signature = '';
  let initialized = false;

  function syncSend() {
    sendButton.disabled = sending || !available || (!textarea.value.trim() && !imageInput.files.length);
  }

  function clearImage() {
    if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
    attachmentUrl = null;
    imageInput.value = '';
    attachment.querySelector('img').removeAttribute('src');
    attachment.hidden = true;
    syncSend();
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers},
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      location.assign(thread.dataset.login);
      throw new Error('Please sign in again.');
    }
    if (!response.ok) {
      if ([403, 404, 422].includes(response.status) && !options.method) {
        available = false;
        syncSend();
      }
      throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'We could not connect. Please try again.');
    }
    return data;
  }

  function messageElement(message) {
    const row = document.createElement('article');
    row.className = 'message' + (message.sender_type === 'rider' ? ' mine' : '');
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    if (message.message) {
      const text = document.createElement('div');
      text.className = 'message-text';
      text.textContent = message.message;
      bubble.append(text);
    }
    if (message.image_path) {
      const url = new URL(message.image_path, location.origin);
      if (['https:', 'http:'].includes(url.protocol)) {
        const link = document.createElement('a');
        link.className = 'image-link';
        link.href = url.href;
        link.setAttribute('aria-label', 'Open message attachment');
        const image = document.createElement('img');
        image.className = 'message-image';
        image.src = url.href;
        image.alt = 'Message attachment';
        image.addEventListener('load', () => {
          if (list.scrollHeight - list.scrollTop - list.clientHeight < 280) list.scrollTop = list.scrollHeight;
        });
        link.append(image);
        link.addEventListener('click', event => {
          event.preventDefault();
          viewer.querySelector('img').src = url.href;
          viewer.showModal();
        });
        bubble.append(link);
      }
    }
    const time = document.createElement('time');
    const date = new Date(message.created_at);
    time.dateTime = message.created_at;
    time.textContent = date.toLocaleString([], {month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'});
    row.append(bubble, time);
    return row;
  }

  function showStatus(message) {
    status.querySelector('span').textContent = message;
    status.hidden = false;
  }

  function load(forceBottom = false) {
    if (loading) return loading;
    loading = (async () => {
      try {
        const data = await request(thread.dataset.index);
        const messages = data.messages || [];
        const nextSignature = JSON.stringify(messages);
        const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
        if (nextSignature !== signature) {
          const previousTop = list.scrollTop;
          list.replaceChildren(...messages.map(messageElement));
          if (!messages.length) {
            const empty = document.createElement('p');
            empty.className = 'thread-empty';
            empty.textContent = 'No messages yet. Start the conversation.';
            list.append(empty);
          }
          signature = nextSignature;
          list.scrollTop = !initialized || nearBottom || forceBottom ? list.scrollHeight : previousTop;
        } else if (forceBottom) list.scrollTop = list.scrollHeight;
        initialized = true;
        available = true;
        status.hidden = true;
        syncSend();
      } catch (exception) {
        showStatus(exception.message);
        if (!initialized) list.querySelector('.thread-empty').textContent = 'Conversation could not be loaded.';
      } finally {
        list.setAttribute('aria-busy', 'false');
        loading = null;
      }
    })();
    return loading;
  }

  textarea.addEventListener('input', () => {
    textarea.style.height = '46px';
    textarea.style.height = Math.min(120, textarea.scrollHeight) + 'px';
    syncSend();
  });
  textarea.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && !sendButton.disabled) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
    attachmentUrl = null;
    attachment.hidden = true;
    error.textContent = '';
    if (file) {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        error.textContent = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
        imageInput.value = '';
      } else {
        attachmentUrl = URL.createObjectURL(file);
        attachment.querySelector('img').src = attachmentUrl;
        attachment.hidden = false;
      }
    }
    syncSend();
  });
  document.getElementById('removeAttachment').addEventListener('click', clearImage);
  document.getElementById('retryMessages').addEventListener('click', () => load());
  viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sendButton.disabled) return;
    sending = true;
    syncSend();
    error.textContent = '';
    const payload = new FormData(form);
    textarea.disabled = true;
    imageInput.disabled = true;
    try {
      await request(thread.dataset.send, {method: 'POST', body: payload});
      textarea.value = '';
      textarea.style.height = '46px';
      clearImage();
      if (loading) await loading;
      await load(true);
    } catch (exception) {
      error.textContent = exception.message;
    } finally {
      sending = false;
      textarea.disabled = false;
      imageInput.disabled = false;
      syncSend();
    }
  });
  load(true);
  setInterval(() => { if (!document.hidden && !sending) load(); }, 10000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && !sending) load(); });
})();
