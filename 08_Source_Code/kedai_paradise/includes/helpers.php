<?php
/**
 * Shared Helper Functions
 * Kedai Paradise POS
 */

const SALES_FILTER = "t.status <> 'void'";

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function t(string $key): string {
    static $lang = null;
    if ($lang === null) {
        $langFile = dirname(__DIR__) . '/lang/id.php';
        $lang = file_exists($langFile) ? require $langFile : [];
    }
    return $lang[$key] ?? $key;
}

function formatRupiah(int $n): string {
    return 'Rp' . number_format($n, 0, ',', '.');
}

function formatDateTimeWib(string $dt): string {
    if (empty($dt)) return '-';
    $timestamp = strtotime($dt);
    return date('d/m/Y H:i', $timestamp) . ' WIB';
}

function redirect(string $url): never {
    header("Location: " . $url);
    exit;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): never {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

function saveUploadedImage(array $file, string $subdir, string $prefix, int $maxBytes): string {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Parameter file tidak valid.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Tidak ada file yang diunggah.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Gagal mengunggah file. Kode error: ' . $file['error']);
    }
    if ($file['size'] > $maxBytes) {
        throw new Exception('Ukuran file terlalu besar. Maksimal ' . ($maxBytes / 1024 / 1024) . ' MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    
    if (!array_key_exists($mime, $allowedMimes)) {
        throw new Exception('Format file tidak didukung. Hanya format JPEG dan PNG yang diizinkan.');
    }

    $ext = $allowedMimes[$mime];
    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    
    $targetDir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $destination = $targetDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Gagal menyimpan file ke server.');
    }

    return $subdir . '/' . $filename;
}