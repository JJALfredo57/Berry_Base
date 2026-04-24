let lbImages  = [];   // array of src strings
let lbIndex   = 0;
let lbZoomLvl = 1;
const LB_ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4];

function openLightbox(imgEl) {
  // Collect all .chat-img images visible in the page / bubble
  const scope = imgEl.closest('#chatBox') || imgEl.closest('#miniChatMessages') || document;
  const imgs = [...scope.querySelectorAll('.chat-img[data-src]')];
  lbImages  = imgs.map(i => i.dataset.src || i.src);
  lbIndex   = imgs.indexOf(imgEl);
  if (lbIndex < 0) { lbImages = [imgEl.dataset.src || imgEl.src]; lbIndex = 0; }

  lbZoomLvl = 1;
  lbRender();
  document.getElementById('imgLightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  document.getElementById('imgLightbox').classList.remove('open');
  document.body.style.overflow = '';
}

function lbBgClick(e) {
  if (e.target === document.getElementById('imgLightbox')) closeLightbox();
}

function lbNav(dir) {
  lbIndex = Math.max(0, Math.min(lbImages.length - 1, lbIndex + dir));
  lbZoomLvl = 1;
  lbRender();
}

function lbRender() {
  const img  = document.getElementById('lbImg');
  const prev = document.getElementById('lbNavPrev');
  const next = document.getElementById('lbNavNext');
  const ctr  = document.getElementById('lbCounter');
  const dl   = document.getElementById('lbDownload');

  img.src = lbImages[lbIndex];
  img.style.transform = 'scale(' + lbZoomLvl + ')';
  document.getElementById('lbZoomLabel').textContent = Math.round(lbZoomLvl * 100) + '%';
  prev.disabled = lbIndex === 0;
  next.disabled = lbIndex === lbImages.length - 1;
  ctr.textContent = lbImages.length > 1 ? (lbIndex + 1) + ' / ' + lbImages.length : '';
  dl.href = lbImages[lbIndex];
  // Hide nav buttons if only 1 image
  prev.style.display = next.style.display = lbImages.length > 1 ? 'flex' : 'none';
}

function lbZoom(dir) {
  const cur = LB_ZOOM_STEPS.indexOf(lbZoomLvl);
  const next = Math.max(0, Math.min(LB_ZOOM_STEPS.length - 1, cur + dir));
  lbZoomLvl = LB_ZOOM_STEPS[next];
  document.getElementById('lbImg').style.transform = 'scale(' + lbZoomLvl + ')';
  document.getElementById('lbZoomLabel').textContent = Math.round(lbZoomLvl * 100) + '%';
}

function lbZoomReset() {
  lbZoomLvl = 1;
  document.getElementById('lbImg').style.transform = 'scale(1)';
  document.getElementById('lbZoomLabel').textContent = '100%';
}

// Keyboard nav
document.addEventListener('keydown', e => {
  if (!document.getElementById('imgLightbox').classList.contains('open')) return;
  if (e.key === 'Escape')      closeLightbox();
  if (e.key === 'ArrowLeft')   lbNav(-1);
  if (e.key === 'ArrowRight')  lbNav(1);
  if (e.key === '+' || e.key === '=') lbZoom(1);
  if (e.key === '-')           lbZoom(-1);
});

// Scroll to zoom on image
document.getElementById('lbImg').addEventListener('wheel', e => {
  e.preventDefault();
  lbZoom(e.deltaY < 0 ? 1 : -1);
}, { passive: false });
</script>