<?php
declare(strict_types=1);

// =========================
// CONFIG
// =========================
date_default_timezone_set('Asia/Jakarta');

$folder = __DIR__.'/foto/';

// Cek folder
if (!is_dir($folder)) {
    exit("<h3>Folder 'foto/' tidak ditemukan!</h3>");
}

// Ambil semua file gambar
$files = glob($folder.'*.{jpg,jpeg,png}', GLOB_BRACE);
if (!$files) {
    exit('<h3>Belum ada foto disimpan.</h3>');
}

// =========================
// HELPERS
// =========================
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Judul diambil dari nama file sebelum underscore pertama "_".
 * Contoh: "pak-tommy_2025-01-01_10-00-00.jpg" => "Pak Tommy"
 * Tanda "-" dianggap spasi.
 */
function getTitleFromFilename(string $filename): string
{
    $base = explode('_', $filename, 2)[0];
    $title = str_replace('-', ' ', $base);
    $title = trim($title);
    if ($title === '') {
        return 'Tidak diketahui';
    }

    return ucwords($title);
}

/**
 * Key judul untuk query param (lowercase).
 */
function getTitleKey(string $title): string
{
    return strtolower(trim($title));
}

/**
 * Ambil timestamp dari pola: YYYY-MM-DD_HH-MM-SS di filename.
 * contoh: xxx_2025-01-01_10-11-12.jpg.
 */
function getTimestampFromFilename(string $filename): ?int
{
    if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/', $filename, $m)) {
        $datetime = $m[1].' '.str_replace('-', ':', $m[2]); // YYYY-MM-DD HH:MM:SS
        $ts = strtotime($datetime);

        return ($ts !== false) ? $ts : null;
    }

    return null;
}

function buildQuery(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }

    return http_build_query($params);
}

function sortPhotos(array &$photos, string $sort, string $order): void
{
    usort($photos, function ($a, $b) use ($sort, $order) {
        $result = 0;

        if ($sort === 'name') {
            $result = strcmp($a['filename'], $b['filename']);
        } elseif ($sort === 'size') {
            $result = $a['size'] <=> $b['size'];
        } else { // date
            $result = $a['modified_ts'] <=> $b['modified_ts'];
        }

        // default: desc untuk date, tapi kita pakai order dari URL
        return ($order === 'asc') ? $result : -$result;
    });
}

// =========================
// PARAMS
// =========================
$filter = isset($_GET['filter']) ? strtolower(trim((string) $_GET['filter'])) : ''; // ''=semua

$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'date';    // date|name|size
$order = isset($_GET['order']) ? (string) $_GET['order'] : 'desc'; // asc|desc

$allowedSort = ['date', 'name', 'size'];
$allowedOrder = ['asc', 'desc'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'date';
}
if (!in_array($order, $allowedOrder, true)) {
    $order = 'desc';
}

// =========================
// BUILD DATA
// =========================
// Urutkan file raw berdasar modified terbaru dulu (biar terasa responsif)
usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

$availableTitles = []; // key => label
$photos = [];

foreach ($files as $file) {
    $filename = basename($file);

    $title = getTitleFromFilename($filename);
    $titleKey = getTitleKey($title);

    // Simpan title unik untuk dropdown (otomatis)
    $availableTitles[$titleKey] = $title;

    // Apply filter server-side
    if ($filter !== '' && $filter !== $titleKey) {
        continue;
    }

    $url = 'foto/'.$filename;
    $filesize = round(filesize($file) / 1024); // KB

    $nameTs = getTimestampFromFilename($filename);
    $ts = $nameTs ?? filemtime($file);

    $photos[] = [
        'title' => $title,
        'title_key' => $titleKey,
        'filename' => $filename,
        'url' => $url,
        'size' => (int) $filesize,
        'modified' => date('d M Y, H:i', $ts),
        'modified_ts' => (int) $ts,
    ];
}

// Sorting sesuai pilihan user
sortPhotos($photos, $sort, $order);
$totalPhotos = count($photos);

// Untuk label filter di header
$filterLabel = ($filter !== '' && isset($availableTitles[$filter])) ? $availableTitles[$filter] : $filter;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Galeri Foto</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap');
* { box-sizing: border-box; }

body{
    margin:0;
    padding:0;
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,#1f3b4d,#3a6b7e);
    color:#fff;
    min-height:100vh;
}

/* HEADER */
.header{
    padding:20px;
    text-align:center;
    background:rgba(0,0,0,.2);
    backdrop-filter:blur(10px);
}
.header h1{ margin:0; font-weight:600; letter-spacing:1px; }
.header p{ margin:6px 0 0; opacity:.9; font-size:14px; }

/* TOOLBAR */
.toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 20px;
    background:rgba(0,0,0,.1);
    flex-wrap:wrap;
    gap:12px;
}
.search-box{
    display:flex;
    align-items:center;
    background:rgba(255,255,255,.12);
    border-radius:999px;
    padding:8px 14px;
    flex-grow:1;
    max-width:300px;
}
.search-box input{
    background:none;
    border:none;
    outline:none;
    color:white;
    width:100%;
    margin-left:8px;
}
.search-box input::placeholder{ color:rgba(255,255,255,.7); }

.sort-options{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:10px;
}
.sort-options select{
    background:rgba(255,255,255,.12);
    border:none;
    color:white;
    padding:7px 10px;
    border-radius:6px;
    outline:none;
    cursor:pointer;
}
.sort-options select option{ background:#1f3b4d; }

/* GALLERY */
.gallery-container{
    padding:20px;
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
    gap:20px;
    max-width:1400px;
    margin:0 auto;
}
.photo-card{
    background:rgba(255,255,255,.1);
    border-radius:12px;
    overflow:hidden;
    backdrop-filter:blur(10px);
    box-shadow:0 4px 14px rgba(0,0,0,.25);
    transition:transform .25s ease, box-shadow .25s ease;
    cursor:pointer;
}
.photo-card:hover{
    transform:translateY(-6px);
    box-shadow:0 10px 28px rgba(0,0,0,.35);
}
.photo-thumbnail{
    width:100%;
    height:180px;
    object-fit:cover;
    display:block;
}
.photo-info{ padding:14px; }
.photo-info h3{
    margin:6px 0 8px;
    font-size:15px;
    font-weight:500;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.photo-info p{ margin:4px 0; font-size:13px; opacity:.85; }
.badge{
    display:inline-block;
    font-size:11px;
    padding:4px 9px;
    border-radius:999px;
    background:rgba(0,0,0,.35);
}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.92);
    z-index:1000;
    flex-direction:column;
}
.modal.is-open{ display:flex; }

.modal-controls{
    flex:0 0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 16px;
    background:rgba(0,0,0,.6);
    backdrop-filter:blur(6px);
}
.modal-close{
    font-size:26px;
    font-weight:bold;
    color:#fff;
    background:none;
    border:none;
    cursor:pointer;
}
.modal-close:hover{ color:#ff5c5c; }

.modal-actions{
    display:flex;
    align-items:center;
    gap:10px;
}

/* tombol rotate mirip download */
.action-btn{
    text-decoration:none;
    color:#fff;
    background:#00c853;
    padding:7px 14px;
    border-radius:6px;
    font-size:14px;
    font-weight:500;
    border:none;
    cursor:pointer;
}
.action-btn:hover{ background:#00b44a; }

/* BODY (GAMBAR) */
.modal-content{
    flex:1 1 auto;
    min-height:0;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:12px;
    overflow:auto;
}
.modal-content img{
    display:block;
    width:auto;
    height:auto;
    max-width:min(96vw, 1200px);
    max-height:calc(100vh - 160px);
    object-fit:contain;
    border-radius:10px;
    transition:transform .2s ease;
}
.modal-content img.is-vertical{
    transform:rotate(90deg);
    max-width:min(80vw, 900px);
    max-height:calc(100vh - 220px);
}

/* FOOTER */
.modal-info{
    flex:0 0 auto;
    padding:12px 16px;
    background:rgba(0,0,0,.6);
    backdrop-filter:blur(6px);
    text-align:center;
}
.modal-info h3{
    margin:0 0 6px;
    font-size:16px;
    font-weight:500;
    word-break:break-word;
}
.modal-info p{
    margin:0;
    font-size:13px;
    opacity:.9;
    line-height:1.4;
}

/* NO RESULT */
.no-results{
    grid-column:1 / -1;
    text-align:center;
    padding:40px;
    background:rgba(255,255,255,.1);
    border-radius:12px;
}

/* FOOTER */
footer{
    margin-top:auto;
    padding:14px;
    font-size:12px;
    opacity:.7;
    text-align:center;
}

/* RESPONSIVE */
@media (max-width: 768px){
    .gallery-container{
        grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
        gap:14px;
        padding:14px;
    }
    .photo-thumbnail{ height:140px; }
    .modal-content img{ max-height:calc(100vh - 190px); }
    .modal-content img.is-vertical{
        max-width:92vw;
        max-height:calc(100vh - 240px);
    }
    .modal-info h3{ font-size:15px; }
    .modal-info p{ font-size:12px; }
}
</style>
</head>
<body>

<div class="header">
    <h1>📷 Galeri Foto</h1>
    <p>
        Total: <?php echo (int) $totalPhotos; ?> foto
        <?php if ($filter !== '') { ?>
            • Filter: <b><?php echo h($filterLabel ?: 'Unknown'); ?></b>
        <?php } ?>
    </p>
</div>

<div class="toolbar">
    <div class="search-box">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Cari foto..." onkeyup="filterPhotos()">
    </div>

    <div class="sort-options">
        <span>Filter:</span>
        <select id="filterSelect" onchange="applyFilter()">
            <option value="" <?php echo ($filter === '') ? 'selected' : ''; ?>>Semua</option>
            <?php
            // urutkan judul biar rapi (A-Z)
            asort($availableTitles);
foreach ($availableTitles as $key => $label) { ?>
                <option value="<?php echo h($key); ?>" <?php echo ($filter === $key) ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                </option>
            <?php } ?>
        </select>

        <span>Urutkan:</span>
        <select id="sortSelect" onchange="sortPhotos()">
            <option value="date_desc" <?php echo ($sort === 'date' && $order === 'desc') ? 'selected' : ''; ?>>Terbaru</option>
            <option value="date_asc"  <?php echo ($sort === 'date' && $order === 'asc') ? 'selected' : ''; ?>>Terlama</option>
            <option value="name_asc"  <?php echo ($sort === 'name' && $order === 'asc') ? 'selected' : ''; ?>>Nama (A-Z)</option>
            <option value="name_desc" <?php echo ($sort === 'name' && $order === 'desc') ? 'selected' : ''; ?>>Nama (Z-A)</option>
            <option value="size_asc"  <?php echo ($sort === 'size' && $order === 'asc') ? 'selected' : ''; ?>>Ukuran (Kecil-Besar)</option>
            <option value="size_desc" <?php echo ($sort === 'size' && $order === 'desc') ? 'selected' : ''; ?>>Ukuran (Besar-Kecil)</option>
        </select>

        <button
            onclick="location.href='?<?php echo h(buildQuery(['sort' => null, 'order' => null, 'filter' => null])); ?>'"
            style="padding: 8px 15px; background: #00c853; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🔄 Reset
        </button>

        <button
            onclick="location.reload()"
            style="padding: 8px 15px; background: rgba(255,255,255,0.15); color: white; border: none; border-radius: 5px; cursor: pointer;">
            ⟳ Refresh
        </button>
    </div>
</div>

<div class="gallery-container" id="galleryContainer">
    <?php if (count($photos) === 0) { ?>
        <div class="no-results">
            <h3>Tidak ada foto untuk filter ini</h3>
            <p style="opacity:.85;">
                Format nama file: <b>judul_YYYY-MM-DD_HH-MM-SS.jpg</b><br>
                Contoh: <b>pak-tommy_2025-01-01_10-00-00.jpg</b> (judul pakai "-" untuk spasi)
            </p>
        </div>
    <?php } ?>

    <?php foreach ($photos as $photo) { ?>
        <div class="photo-card"
             data-filename="<?php echo h(strtolower($photo['filename'])); ?>"
             data-owner="<?php echo h(strtolower($photo['title'])); ?>"
             onclick="openModal('<?php echo h($photo['url']); ?>',
                               '<?php echo h($photo['filename']); ?>',
                               '<?php echo (int) $photo['size']; ?> KB',
                               '<?php echo h($photo['modified']); ?>',
                               '<?php echo h($photo['title']); ?>')">
            <img src="<?php echo h($photo['url']); ?>" alt="<?php echo h($photo['filename']); ?>" class="photo-thumbnail">
            <div class="photo-info">
                <div class="badge"><?php echo h($photo['title']); ?></div>
                <h3><?php echo h($photo['filename']); ?></h3>
                <p>📅 <?php echo h($photo['modified']); ?></p>
                <p>💾 <?php echo (int) $photo['size']; ?> KB</p>
            </div>
        </div>
    <?php } ?>
</div>

<div id="noResults" class="no-results" style="display:none;">
    <h3>Tidak ada foto yang cocok dengan pencarian Anda</h3>
</div>

<div id="photoModal" class="modal" onclick="closeModal()">
    <div class="modal-controls" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeModal()">&times;</button>

        <div class="modal-actions">
            <button id="rotateBtn" class="action-btn" type="button">🔁 Rotate</button>
            <a id="downloadLink" href="" download class="action-btn">⬇️ Download</a>
        </div>
    </div>

    <div class="modal-content" onclick="event.stopPropagation()">
        <img id="modalImage" src="" alt="">
    </div>

    <div class="modal-info" onclick="event.stopPropagation()">
        <h3 id="modalTitle"></h3>
        <p id="modalDetails"></p>
    </div>
</div>

<footer>© Monitor Kamera Aeroponik v2.2</footer>

<script>
const modal = document.getElementById('photoModal');
const modalImage = document.getElementById('modalImage');
const rotateBtn = document.getElementById('rotateBtn');

let isVertical = false;

// Buka modal
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

// Tutup modal
function closeModal() {
    modal.classList.remove('is-open');
    modalImage.src = '';
    isVertical = false;
    modalImage.classList.remove('is-vertical');
}

// Rotate 2 mode: horizontal <-> vertical
rotateBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    isVertical = !isVertical;
    modalImage.classList.toggle('is-vertical', isVertical);
});

// Filter client-side (search box)
function filterPhotos() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const photoCards = document.querySelectorAll('.photo-card');
    const noResults = document.getElementById('noResults');
    let hasResults = false;

    photoCards.forEach(card => {
        const filename = card.getAttribute('data-filename');
        const owner = card.getAttribute('data-owner');
        const hay = (filename + ' ' + owner);

        if (hay.includes(searchInput)) {
            card.style.display = '';
            hasResults = true;
        } else {
            card.style.display = 'none';
        }
    });

    noResults.style.display = hasResults ? 'none' : 'block';
}

// Sort (server-side via reload)
function sortPhotos() {
    const sortSelect = document.getElementById('sortSelect');
    const [sort, order] = sortSelect.value.split('_');
    const url = new URL(window.location.href);

    url.searchParams.set('sort', sort);
    url.searchParams.set('order', order);

    window.location.href = url.toString();
}

// Filter dropdown (server-side via reload)
function applyFilter() {
    const filterValue = document.getElementById('filterSelect').value;
    const url = new URL(window.location.href);

    if (!filterValue) url.searchParams.delete('filter');
    else url.searchParams.set('filter', filterValue);

    window.location.href = url.toString();
}

// ESC close
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeModal();
});
</script>

</body>
</html>
