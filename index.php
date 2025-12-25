<?php
// Set timezone ke Jakarta Indonesia
date_default_timezone_set('Asia/Jakarta');

// Folder penyimpanan foto
$folder = __DIR__ . "/foto/";

// Cek folder
if (!is_dir($folder)) { die("<h3>Folder 'foto/' tidak ditemukan!</h3>"); }

// Ambil semua file gambar
$files = glob($folder . "*.{jpg,jpeg,png}", GLOB_BRACE);
if (!$files) { die("<h3>Belum ada foto disimpan.</h3>"); }

// =========================
// FILTER (id -> label)
// id=1 => Leyangan, id=2 => Pak Tommy
// Deteksi id dari AWAL nama file: "1_", "2-", "1 " dll.
// =========================
$filter = isset($_GET['filter']) ? (int)$_GET['filter'] : 0; // 0=semua
$filterMap = [
    1 => "Leyangan",
    2 => "Pak Tommy",
];

function getPhotoIdFromFilename(string $filename): int {
    // Cocokkan digit pertama di awal filename: 1 atau 2, diikuti pemisah (_ - spasi .) atau langsung akhir
    if (preg_match('/^([12])(?:[_\-\s\.]|$)/', $filename, $m)) {
        return (int)$m[1];
    }
    return 0; // tidak teridentifikasi
}

// Urutkan berdasarkan terakhir diubah
usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });

// Siapkan data untuk setiap foto (sekaligus apply filter server-side)
$photos = [];
foreach ($files as $file) {
    $filename = basename($file);

    $photoId = getPhotoIdFromFilename($filename);

    // Apply filter jika dipilih
    if ($filter !== 0 && $photoId !== $filter) {
        continue;
    }

    $url = "foto/" . $filename;
    $filesize = round(filesize($file) / 1024); // in KB
    $modifiedTs = filemtime($file);
    $modified = date("d M Y, H:i", $modifiedTs);

    $photos[] = [
        'id' => $photoId,
        'owner' => $filterMap[$photoId] ?? 'Tidak diketahui',
        'filename' => $filename,
        'url' => $url,
        'size' => $filesize,
        'modified' => $modified,
        'modified_ts' => $modifiedTs, // biar sorting tanggal akurat (jangan pakai strtotime dari string)
    ];
}

// Ambil parameter sorting dari URL
$sort  = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Fungsi untuk mengurutkan foto
function sortPhotos(&$photos, $sort, $order) {
    usort($photos, function($a, $b) use ($sort, $order) {
        $result = 0;

        if ($sort === 'name') {
            $result = strcmp($a['filename'], $b['filename']);
        } elseif ($sort === 'size') {
            $result = $a['size'] - $b['size'];
        } elseif ($sort === 'date') {
            // pakai timestamp asli agar tidak salah
            $result = $b['modified_ts'] - $a['modified_ts'];
        }

        return ($order === 'asc') ? -$result : $result;
    });
}

// Urutkan foto sesuai parameter
sortPhotos($photos, $sort, $order);

// Hitung total foto
$totalPhotos = count($photos);

// Helper untuk menjaga parameter URL saat ganti sort/filter
function buildQuery(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    // hapus yang null
    foreach ($params as $k => $v) {
        if ($v === null) unset($params[$k]);
    }
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Galeri Foto</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap');

body {
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #1f3b4d, #3a6b7e);
    color: #fff;
    min-height: 100vh;
}

.header {
    padding: 20px;
    text-align: center;
    background: rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
}

.header h1 {
    margin: 0;
    font-weight: 600;
    letter-spacing: 1px;
}

.header p {
    margin: 5px 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: rgba(0,0,0,0.1);
    flex-wrap: wrap;
    gap: 10px;
}

.toolbar .search-box {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.1);
    border-radius: 25px;
    padding: 8px 15px;
    margin-right: 10px;
    flex-grow: 1;
    max-width: 300px;
}

.toolbar .search-box input {
    background: none;
    border: none;
    color: white;
    outline: none;
    width: 100%;
    margin-left: 8px;
}

.toolbar .search-box input::placeholder {
    color: rgba(255,255,255,0.7);
}

.toolbar .sort-options {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.toolbar .sort-options select {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    outline: none;
    cursor: pointer;
}

.toolbar .sort-options select option {
    background: #1f3b4d;
}

.gallery-container {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.photo-card {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    overflow: hidden;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
}

.photo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

.photo-thumbnail {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.photo-info {
    padding: 15px;
}

.photo-info h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.photo-info p {
    margin: 5px 0;
    font-size: 13px;
    opacity: 0.85;
}

.badge {
    display: inline-block;
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(0,0,0,0.25);
    margin-bottom: 8px;
}

/* --- MODAL --- */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    flex-direction: column;
}

.modal-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #fff;
    cursor: pointer;
    background: none;
    border: none;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #ff4d4d;
}

.download-btn {
    text-decoration: none;
    color: #fff;
    background: #00c853;
    padding: 8px 15px;
    border-radius: 5px;
    font-weight: 500;
    transition: background 0.3s;
}

.download-btn:hover {
    background: #00b44a;
}

.modal-content {
    flex-grow: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-content img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 5px;
}

.modal-info {
    padding: 15px 20px;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    text-align: center;
}

.modal-info h3 {
    margin: 0 0 5px;
    font-weight: 500;
}

.modal-info p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}

footer {
    margin-top: auto;
    padding: 15px;
    font-size: 12px;
    opacity: 0.7;
    text-align: center;
}

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

@media (max-width: 768px) {
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar .search-box {
        max-width: 100%;
        margin-right: 0;
    }

    .gallery-container {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        padding: 15px;
    }

    .modal-info h3 { font-size: 16px; }
    .modal-info p { font-size: 12px; }
}
</style>
</head>
<body>

<div class="header">
    <h1>📷 Galeri Foto</h1>
    <p>
        Total: <?php echo $totalPhotos; ?> foto
        <?php if ($filter !== 0): ?>
            • Filter: <b><?php echo htmlspecialchars($filterMap[$filter] ?? 'Unknown'); ?></b>
        <?php endif; ?>
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
            <option value="0" <?php echo ($filter === 0) ? 'selected' : ''; ?>>Semua</option>
            <option value="1" <?php echo ($filter === 1) ? 'selected' : ''; ?>>Leyangan</option>
            <option value="2" <?php echo ($filter === 2) ? 'selected' : ''; ?>>Pak Tommy</option>
        </select>

        <span>Urutkan:</span>
        <select id="sortSelect" onchange="sortPhotos()">
            <option value="date_desc" <?php echo ($sort == 'date' && $order == 'desc') ? 'selected' : ''; ?>>Terbaru</option>
            <option value="date_asc" <?php echo ($sort == 'date' && $order == 'asc') ? 'selected' : ''; ?>>Terlama</option>
            <option value="name_asc" <?php echo ($sort == 'name' && $order == 'asc') ? 'selected' : ''; ?>>Nama (A-Z)</option>
            <option value="name_desc" <?php echo ($sort == 'name' && $order == 'desc') ? 'selected' : ''; ?>>Nama (Z-A)</option>
            <option value="size_asc" <?php echo ($sort == 'size' && $order == 'asc') ? 'selected' : ''; ?>>Ukuran (Kecil-Besar)</option>
            <option value="size_desc" <?php echo ($sort == 'size' && $order == 'desc') ? 'selected' : ''; ?>>Ukuran (Besar-Kecil)</option>
        </select>

        <button onclick="location.href='?<?php echo buildQuery(['sort'=>null,'order'=>null,'filter'=>null]); ?>'"
                style="padding: 8px 15px; background: #00c853; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🔄 Reset
        </button>
        <button onclick="location.reload()"
                style="padding: 8px 15px; background: rgba(255,255,255,0.15); color: white; border: none; border-radius: 5px; cursor: pointer;">
            ⟳ Refresh
        </button>
    </div>
</div>

<div class="gallery-container" id="galleryContainer">
    <?php if (count($photos) === 0): ?>
        <div class="no-results">
            <h3>Tidak ada foto untuk filter ini</h3>
            <p style="opacity:.85;">Pastikan nama file diawali <b>1_</b> untuk Leyangan atau <b>2_</b> untuk Pak Tommy.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($photos as $photo): ?>
    <div class="photo-card"
         data-filename="<?php echo strtolower($photo['filename']); ?>"
         data-owner="<?php echo strtolower($photo['owner']); ?>"
         onclick="openModal('<?php echo $photo['url']; ?>', '<?php echo htmlspecialchars($photo['filename'], ENT_QUOTES); ?>', '<?php echo $photo['size']; ?> KB', '<?php echo htmlspecialchars($photo['modified'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($photo['owner'], ENT_QUOTES); ?>')">
        <img src="<?php echo $photo['url']; ?>" alt="<?php echo htmlspecialchars($photo['filename']); ?>" class="photo-thumbnail">
        <div class="photo-info">
            <div class="badge"><?php echo htmlspecialchars($photo['owner']); ?><?php echo $photo['id'] ? " (ID: ".$photo['id'].")" : ""; ?></div>
            <h3><?php echo htmlspecialchars($photo['filename']); ?></h3>
            <p>📅 <?php echo htmlspecialchars($photo['modified']); ?></p>
            <p>💾 <?php echo (int)$photo['size']; ?> KB</p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="noResults" class="no-results" style="display: none;">
    <h3>Tidak ada foto yang cocok dengan pencarian Anda</h3>
</div>

<div id="photoModal" class="modal">
    <div class="modal-controls">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <a id="downloadLink" href="" download class="download-btn">⬇️ Download</a>
    </div>
    <div class="modal-content">
        <img id="modalImage" src="" alt="">
    </div>
    <div class="modal-info">
        <h3 id="modalTitle"></h3>
        <p id="modalDetails"></p>
    </div>
</div>

<footer>© Monitor Kamera Aeroponik v2.1</footer>

<script>
// Fungsi untuk membuka modal
function openModal(url, filename, size, modified, owner) {
    document.getElementById('modalImage').src = url;
    document.getElementById('modalTitle').textContent = filename;
    document.getElementById('modalDetails').innerHTML =
        `👤 ${owner} &nbsp;&nbsp;|&nbsp;&nbsp; 📅 ${modified} &nbsp;&nbsp;|&nbsp;&nbsp; 💾 ${size}`;

    const downloadLink = document.getElementById('downloadLink');
    downloadLink.href = url;
    downloadLink.setAttribute('download', filename);

    document.getElementById('photoModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('photoModal').style.display = 'none';
}

// Filter client-side berdasarkan pencarian (tetap jalan walau sudah filter server-side)
function filterPhotos() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const photoCards = document.querySelectorAll('.photo-card');
    const noResults = document.getElementById('noResults');
    let hasResults = false;

    photoCards.forEach(card => {
        const filename = card.getAttribute('data-filename');
        if (filename.includes(searchInput)) {
            card.style.display = '';
            hasResults = true;
        } else {
            card.style.display = 'none';
        }
    });

    noResults.style.display = hasResults ? 'none' : 'block';
}

// Urutkan foto (jaga filter yang sedang aktif)
function sortPhotos() {
    const sortSelect = document.getElementById('sortSelect');
    const [sort, order] = sortSelect.value.split('_');

    const url = new URL(window.location.href);
    url.searchParams.set('sort', sort);
    url.searchParams.set('order', order);
    window.location.href = url.toString();
}

// Apply filter dropdown (jaga sort yg sedang aktif)
function applyFilter() {
    const filterValue = document.getElementById('filterSelect').value;

    const url = new URL(window.location.href);
    if (filterValue === "0") url.searchParams.delete('filter');
    else url.searchParams.set('filter', filterValue);

    window.location.href = url.toString();
}

// Tutup modal dengan ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeModal();
});
</script>

</body>
</html>
