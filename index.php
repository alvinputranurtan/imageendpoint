<?php
// Set timezone ke Jakarta Indonesia
date_default_timezone_set('Asia/Jakarta');

// Folder penyimpanan foto
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
// FILTER DINAMIS
// - Ambil prefix sebelum underscore "_" pertama pada nama file
// - "-" dianggap spasi
// - filter memakai key string (lowercase), "" = semua
// Contoh: "Pak-Tommy_2026-01-13_12-00-00.jpg" -> "pak tommy"
// =========================
$filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : ''; // '' = semua

function getPrefixBeforeFirstUnderscore(string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME); // tanpa ekstensi
    $pos = strpos($base, '_');
    if ($pos === false) {
        return $base;
    }

    return substr($base, 0, $pos);
}

function normalizeFilterKey(string $prefix): string
{
    $s = str_replace('-', ' ', $prefix);           // "-" dianggap spasi
    $s = preg_replace('/\s+/', ' ', $s);          // rapihin spasi
    $s = trim($s);

    return mb_strtolower($s, 'UTF-8');            // key lowercase
}

function makeFilterLabel(string $prefix): string
{
    $s = str_replace('-', ' ', $prefix);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = trim($s);

    return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8'); // label rapi
}

function getTimestampFromFilename(string $filename): ?int
{
    if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/', $filename, $m)) {
        $datetime = $m[1].' '.str_replace('-', ':', $m[2]); // YYYY-MM-DD HH:MM:SS
        $ts = strtotime($datetime);

        return ($ts !== false) ? $ts : null;
    }

    return null;
}

// Siapkan data untuk setiap foto (sekaligus apply filter server-side)
// Sekaligus kumpulkan opsi filter dinamis
$photos = [];
$filterOptions = []; // key => label

foreach ($files as $file) {
    $filename = basename($file);

    $prefixRaw = getPrefixBeforeFirstUnderscore($filename);
    $filterKey = normalizeFilterKey($prefixRaw);
    $filterLabel = makeFilterLabel($prefixRaw);

    // kumpulkan opsi filter (untuk dropdown)
    if ($filterKey !== '') {
        $filterOptions[$filterKey] = $filterLabel;
    }

    // apply filter server-side
    if ($filter !== '' && $filterKey !== $filter) {
        continue;
    }

    $url = 'foto/'.$filename;
    $filesize = round(filesize($file) / 1024); // KB

    $nameTs = getTimestampFromFilename($filename);
    $ts = $nameTs ?? filemtime($file);

    $photos[] = [
        'filter_key' => $filterKey,
        'owner' => $filterLabel,
        'filename' => $filename,
        'url' => $url,
        'size' => $filesize,
        'modified' => date('d M Y, H:i', $ts),
        'modified_ts' => $ts,
    ];
}

// urutkan opsi filter A-Z
asort($filterOptions, SORT_NATURAL | SORT_FLAG_CASE);

// Ambil parameter sorting dari URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

function sortPhotos(&$photos, $sort, $order)
{
    usort($photos, function ($a, $b) use ($sort, $order) {
        $result = 0;

        if ($sort === 'name') {
            $result = strcmp($a['filename'], $b['filename']);
        } elseif ($sort === 'size') {
            $result = $a['size'] - $b['size'];
        } elseif ($sort === 'date') {
            $result = $b['modified_ts'] - $a['modified_ts'];
        }

        return ($order === 'asc') ? -$result : $result;
    });
}

sortPhotos($photos, $sort, $order);
$totalPhotos = count($photos);

function buildQuery(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        }
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

    <!-- CSS dipisah -->
    <link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>

<div class="header">
    <h1>📷 Galeri Foto</h1>
    <p>
        Total: <?php echo $totalPhotos; ?> foto
        <?php if ($filter !== '') { ?>
            • Filter: <b><?php echo htmlspecialchars($filterOptions[$filter] ?? $filter); ?></b>
        <?php } ?>
    </p>
</div>

<div class="toolbar">
    <div class="search-box">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Cari foto...">
    </div>

    <div class="sort-options">
        <span>Filter:</span>
        <select id="filterSelect">
            <option value="" <?php echo ($filter === '') ? 'selected' : ''; ?>>Semua</option>
            <?php foreach ($filterOptions as $key => $label) { ?>
                <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"
                        <?php echo ($filter === $key) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php } ?>
        </select>

        <span>Urutkan:</span>
        <select id="sortSelect">
            <option value="date_desc" <?php echo ($sort == 'date' && $order == 'desc') ? 'selected' : ''; ?>>Terbaru</option>
            <option value="date_asc" <?php echo ($sort == 'date' && $order == 'asc') ? 'selected' : ''; ?>>Terlama</option>
            <option value="name_asc" <?php echo ($sort == 'name' && $order == 'asc') ? 'selected' : ''; ?>>Nama (A-Z)</option>
            <option value="name_desc" <?php echo ($sort == 'name' && $order == 'desc') ? 'selected' : ''; ?>>Nama (Z-A)</option>
            <option value="size_asc" <?php echo ($sort == 'size' && $order == 'asc') ? 'selected' : ''; ?>>Ukuran (Kecil-Besar)</option>
            <option value="size_desc" <?php echo ($sort == 'size' && $order == 'desc') ? 'selected' : ''; ?>>Ukuran (Besar-Kecil)</option>
        </select>

        <button id="resetBtn"
                data-reset-url="?<?php echo buildQuery(['sort' => null, 'order' => null, 'filter' => null]); ?>">
            🔄 Reset
        </button>
        <button id="refreshBtn">⟳ Refresh</button>
    </div>
</div>

<div class="gallery-container" id="galleryContainer">
    <?php if (count($photos) === 0) { ?>
        <div class="no-results">
            <h3>Tidak ada foto untuk filter ini</h3>
            <p style="opacity:.85;">
                Pastikan nama file menggunakan format <b>NAMA_...</b>
                (contoh: <b>Pak-Tommy_2026-01-13_12-00-00.jpg</b>).
                Tanda <b>-</b> akan dianggap spasi.
            </p>
        </div>
    <?php } ?>

    <?php foreach ($photos as $photo) { ?>
        <div class="photo-card"
             data-url="<?php echo htmlspecialchars($photo['url'], ENT_QUOTES); ?>"
             data-filename="<?php echo htmlspecialchars(mb_strtolower($photo['filename'], 'UTF-8'), ENT_QUOTES); ?>"
             data-filename-raw="<?php echo htmlspecialchars($photo['filename'], ENT_QUOTES); ?>"
             data-size="<?php echo (int) $photo['size']; ?> KB"
             data-modified="<?php echo htmlspecialchars($photo['modified'], ENT_QUOTES); ?>"
             data-owner="<?php echo htmlspecialchars($photo['owner'], ENT_QUOTES); ?>">
            <img src="<?php echo $photo['url']; ?>"
                 alt="<?php echo htmlspecialchars($photo['filename']); ?>"
                 class="photo-thumbnail">
            <div class="photo-info">
                <div class="badge"><?php echo htmlspecialchars($photo['owner']); ?></div>
                <h3><?php echo htmlspecialchars($photo['filename']); ?></h3>
                <p>📅 <?php echo htmlspecialchars($photo['modified']); ?></p>
                <p>💾 <?php echo (int) $photo['size']; ?> KB</p>
            </div>
        </div>
    <?php } ?>
</div>

<div class="show-more-wrap" id="showMoreWrap" style="display:none;">
    <button type="button" id="showMoreBtn" class="show-more-btn">Show more</button>
    <div class="show-more-info" id="showMoreInfo"></div>
</div>

<div id="noResults" class="no-results" style="display:none;"></div>

<div id="noResults" class="no-results" style="display:none;">
    <h3>Tidak ada foto yang cocok dengan pencarian Anda</h3>
</div>

<div id="photoModal" class="modal">
    <div class="modal-controls">
        <button class="modal-close" id="closeModalBtn" type="button">&times;</button>

        <div class="modal-actions">
            <button id="rotateBtn" class="action-btn" type="button">🔁 Rotate</button>
            <a id="downloadLink" href="" download class="action-btn">⬇️ Download</a>
        </div>
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

<!-- JS dipisah -->
<script src="assets/app.js?v=1"></script>
</body>
</html>
