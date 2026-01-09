<?php

header('Content-Type: application/json');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST']);
    exit;
}

// Validate token
$token = $_POST['token'] ?? '';
if ($token !== 'RAHASIA123') {   // samakan token
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check file
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

// Valid MIME
$allowed = ['image/jpeg', 'image/png'];
if (!in_array($_FILES['file']['type'], $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file']);
    exit;
}

// Max 5MB
if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large']);
    exit;
}

// Save Folder
$folder = __DIR__.'/foto/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// Use filename sent by client
$filename = $_POST['filename'] ?? null;
if (!$filename) {
    // fallback
    $timestamp = date('Y-m-d_H-i-s');
    $ms = (int) (microtime(true) * 1000) % 1000;
    $filename = "fallback_{$timestamp}_{$ms}.jpg";
}

// sanitize
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

$target = $folder.$filename;

// Save
if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    echo json_encode([
        'status' => 'success',
        'filename' => $filename,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save']);
}
