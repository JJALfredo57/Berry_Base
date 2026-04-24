/* CakeShop Main JS */

// ── Toast ───────────────────────────────────────────────────────────────
function cakeToast(msg, type) {
  type = type || 'success';
  var colors = { success:'#22c55e', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
  var el = document.createElement('div');
  el.className = 'cs-toast';
  el.style.background = colors[type] || colors.info;
  el.textContent = msg;
  var container = document.getElementById('csToastContainer');
  if (container) {
    container.appendChild(el);
    setTimeout(function() {
      el.style.opacity = '0';
      el.style.transform = 'translateX(30px)';
      el.style.transition = 'all .3s';
      setTimeout(function() { el.remove(); }, 300);
    }, 3000);
  }
}

// ── Confirm Modal ────────────────────────────────────────────────────────
function confirmAction(title, msg, onConfirm) {
  var modal = document.getElementById('confirmModal');
  if (!modal) { onConfirm(); return; }
  document.getElementById('confirmTitle').textContent = title || 'Confirm';
  document.getElementById('confirmMsg').textContent   = msg || '';
  var btn = document.getElementById('confirmOkBtn');
  var clone = btn.cloneNode(true);
  btn.parentNode.replaceChild(clone, btn);
  clone.addEventListener('click', function() {
    var bsModal = bootstrap.Modal.getInstance(modal);
    if (bsModal) bsModal.hide();
    onConfirm();
  });
  var bsModal = new bootstrap.Modal(modal);
  bsModal.show();
}

// ── Admin Sidebar ────────────────────────────────────────────────────────
function toggleSidebar() {
  var sb = document.getElementById('adminSidebar');
  var ov = document.getElementById('sbOverlay');
  if (!sb) return;
  sb.classList.toggle('open');
  if (ov) ov.classList.toggle('open');
}
function closeSidebar() {
  var sb = document.getElementById('adminSidebar');
  var ov = document.getElementById('sbOverlay');
  if (sb) sb.classList.remove('open');
  if (ov) ov.classList.remove('open');
}

// ── Image Lightbox ────────────────────────────────────────────────────────
var lbImages = [];
var lbIndex  = 0;

function openLightbox(el) {
  var src = el.dataset.src || el.src;
  var container = el.closest('.chat-imgs, .img-gallery, .msg-imgs') || document;
  var imgs = container.querySelectorAll('.chat-img[data-src]');
  if (imgs.length > 1) {
    lbImages = Array.from(imgs).map(function(i) { return i.dataset.src; });
    lbIndex  = lbImages.indexOf(src);
    if (lbIndex < 0) lbIndex = 0;
  } else {
    lbImages = [src];
    lbIndex  = 0;
  }
  lbShow();
}

function lbShow() {
  var lb  = document.getElementById('imgLightbox');
  var img = document.getElementById('lbImg');
  var dl  = document.getElementById('lbDownload');
  if (!lb || !img) return;
  img.src = lbImages[lbIndex] || '';
  if (dl) dl.href = lbImages[lbIndex] || '';
  lb.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  var lb = document.getElementById('imgLightbox');
  if (lb) lb.style.display = 'none';
  document.body.style.overflow = '';
}

function lbNav(dir) {
  lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
  lbShow();
}

document.addEventListener('keydown', function(e) {
  var lb = document.getElementById('imgLightbox');
  if (!lb || lb.style.display === 'none') return;
  if (e.key === 'Escape')     closeLightbox();
  if (e.key === 'ArrowLeft')  lbNav(-1);
  if (e.key === 'ArrowRight') lbNav(1);
});

// Close lightbox on click outside image
document.addEventListener('DOMContentLoaded', function() {
  var lb = document.getElementById('imgLightbox');
  if (lb) {
    lb.addEventListener('click', function(e) {
      if (e.target === lb) closeLightbox();
    });
  }
  // Auto-attach lightbox to .chat-img elements
  document.querySelectorAll('.chat-img[data-src]').forEach(function(img) {
    img.style.cursor = 'zoom-in';
    img.addEventListener('click', function() { openLightbox(this); });
  });
});

// ── Long Press stubs (overridden by page-specific scripts) ───────────────
var _longPressTimer = null;
function startLongPress(e, img) {}
function cancelLongPress() { if (_longPressTimer) { clearTimeout(_longPressTimer); _longPressTimer = null; } }
