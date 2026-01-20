const modal = document.getElementById('photoModal');
const modalImage = document.getElementById('modalImage');
const rotateBtn = document.getElementById('rotateBtn');
const closeModalBtn = document.getElementById('closeModalBtn');
const searchInputEl = document.getElementById('searchInput');
const filterSelect = document.getElementById('filterSelect');
const sortSelect = document.getElementById('sortSelect');
const noResults = document.getElementById('noResults');

let isVertical = false;

// ===== Modal open/close =====
function openModal(url, filename, size, modified, owner) {
  isVertical = false;
  modalImage.classList.remove('is-vertical');

  modalImage.src = url;
  document.getElementById('modalTitle').textContent = filename;
  document.getElementById('modalDetails').innerHTML =
    `👤 ${owner} &nbsp;&nbsp;|&nbsp;&nbsp; 📅 ${modified} &nbsp;&nbsp;|&nbsp;&nbsp; 💾 ${size}`;

  const downloadLink = document.getElementById('downloadLink');
  downloadLink.href = url;
  downloadLink.setAttribute('download', filename);

  modal.classList.add('is-open');
}

function closeModal() {
  modal.classList.remove('is-open');
  modalImage.src = '';
  isVertical = false;
  modalImage.classList.remove('is-vertical');
}

// klik luar modal -> tutup
modal.addEventListener('click', () => closeModal());
// stop propagation area dalam
modal.querySelector('.modal-controls').addEventListener('click', (e) => e.stopPropagation());
modal.querySelector('.modal-content').addEventListener('click', (e) => e.stopPropagation());
modal.querySelector('.modal-info').addEventListener('click', (e) => e.stopPropagation());

// tombol close
closeModalBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  closeModal();
});

// rotate toggle
rotateBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  isVertical = !isVertical;
  modalImage.classList.toggle('is-vertical', isVertical);
});

// ESC close
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeModal();
});

// ===== Klik card -> buka modal (pakai data-attributes) =====
document.querySelectorAll('.photo-card').forEach((card) => {
  card.addEventListener('click', () => {
    const url = card.dataset.url;
    const filename = card.dataset.filenameRaw;
    const size = card.dataset.size;
    const modified = card.dataset.modified;
    const owner = card.dataset.owner;
    openModal(url, filename, size, modified, owner);
  });
});

// ===== Filter client-side (search) =====
function filterPhotos() {
  const q = (searchInputEl.value || '').toLowerCase();
  const photoCards = document.querySelectorAll('.photo-card');
  let hasResults = false;

  photoCards.forEach((card) => {
    const filename = card.getAttribute('data-filename') || '';
    if (filename.includes(q)) {
      card.style.display = '';
      hasResults = true;
    } else {
      card.style.display = 'none';
    }
  });

  noResults.style.display = hasResults ? 'none' : 'block';
}

searchInputEl.addEventListener('keyup', filterPhotos);

// ===== Sort (server-side via query string) =====
function sortPhotos() {
  const [sort, order] = sortSelect.value.split('_');
  const url = new URL(window.location.href);
  url.searchParams.set('sort', sort);
  url.searchParams.set('order', order);
  window.location.href = url.toString();
}
sortSelect.addEventListener('change', sortPhotos);

// ===== Filter dropdown (server-side via query string) =====
function applyFilter() {
  const filterValue = filterSelect.value;
  const url = new URL(window.location.href);
  if (filterValue === "0") url.searchParams.delete('filter');
  else url.searchParams.set('filter', filterValue);
  window.location.href = url.toString();
}
filterSelect.addEventListener('change', applyFilter);

// ===== Buttons =====
document.getElementById('resetBtn').addEventListener('click', () => {
  const resetUrl = document.getElementById('resetBtn').dataset.resetUrl;
  window.location.href = resetUrl;
});
document.getElementById('refreshBtn').addEventListener('click', () => location.reload());

(function () {
  const PAGE_SIZE = 15;

  const gallery = document.getElementById("galleryContainer");
  if (!gallery) return;

  const cardsAll = Array.from(gallery.querySelectorAll(".photo-card"));

  const searchInput = document.getElementById("searchInput");
  const noResultsEl = document.getElementById("noResults");

  const showMoreWrap = document.getElementById("showMoreWrap");
  const showMoreBtn = document.getElementById("showMoreBtn");
  const showMoreInfo = document.getElementById("showMoreInfo");

  let currentLimit = PAGE_SIZE;

  function getSearchQuery() {
    return (searchInput?.value || "").trim().toLowerCase();
  }

  // menentukan kartu mana yang "eligible" (match pencarian)
  function getEligibleCards() {
    const q = getSearchQuery();
    if (!q) return cardsAll;

    return cardsAll.filter((card) => {
      const filename = (card.dataset.filename || "").toLowerCase();       // sudah lowercase dari PHP
      const owner = (card.dataset.owner || "").toLowerCase();
      const raw = (card.dataset.filenameRaw || "").toLowerCase();
      return filename.includes(q) || owner.includes(q) || raw.includes(q);
    });
  }

  function render() {
    const eligible = getEligibleCards();

    // Sembunyikan semua dulu
    cardsAll.forEach((c) => {
      c.style.display = "none";
    });

    // Tampilkan yang eligible sesuai limit
    eligible.forEach((c, idx) => {
      if (idx < currentLimit) c.style.display = "";
    });

    // noResults
    const hasAny = eligible.length > 0;
    if (noResultsEl) noResultsEl.style.display = hasAny ? "none" : "block";

    // show more
    if (!showMoreWrap || !showMoreBtn) return;

    if (!hasAny) {
      showMoreWrap.style.display = "none";
      return;
    }

    const shownNow = Math.min(currentLimit, eligible.length);
    const canShowMore = shownNow < eligible.length;

    showMoreWrap.style.display = "flex";
    showMoreBtn.style.display = canShowMore ? "inline-flex" : "none";

    if (showMoreInfo) {
      showMoreInfo.textContent = `Menampilkan ${shownNow} dari ${eligible.length} foto`;
    }
  }

  function resetLimit() {
    currentLimit = PAGE_SIZE;
  }

  // Show more click
  if (showMoreBtn) {
    showMoreBtn.addEventListener("click", () => {
      currentLimit += PAGE_SIZE;
      render();
    });
  }

  // Search input
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      resetLimit();
      render();
    });
  }

  // Jika kamu punya tombol refresh/reset/sort/filter yang reload halaman,
  // pagination akan otomatis jalan lagi saat load. Kalau ada logic lain yang
  // mengubah display card via JS, panggil render() setelahnya.

  // init
  resetLimit();
  render();
})();
