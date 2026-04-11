<?php
/**
 * SecureShare API - Simple Backend for Shared Hosting
 * Handles encrypted blobs and metadata with auto-cleanup.
 * 
 * NOTE: Suppressing internal errors to ensure valid JSON response.
 */

error_reporting(0); // Suppress all errors/warnings to keep JSON clean
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$storage_dir = 'uploads/';
$max_file_size = 100 * 1024 * 1024; // 100MB limit
$expiry_time = 24 * 60 * 60; // 24 hours

// Ensure storage directory exists and is writable
if (!file_exists($storage_dir)) {
    if (!mkdir($storage_dir, 0755, true)) {
        echo json_encode(['error' => 'Storage directory missing and cannot be created. Check permissions.']);
        exit;
    }
}

if (!is_writable($storage_dir)) {
    echo json_encode(['error' => 'Storage directory is not writable. Check permissions (chmod 755 or 777).']);
    exit;
}

// Function to delete old files
function cleanup($dir, $expiry) {
    foreach (glob($dir . "*") as $file) {
        if (filemtime($file) < time() - $expiry) {
            @unlink($file); // Suppress error if file is already gone
        }
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        // Run cleanup on every upload
        cleanup($storage_dir, $expiry_time);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        // Check for upload errors
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $err_code = $_FILES['file']['error'] ?? 'MISSING';
            $msg = 'Upload failed with error code: ' . $err_code;
            if ($err_code == UPLOAD_ERR_INI_SIZE) $msg = 'File exceeds upload_max_filesize in php.ini';
            if ($err_code == UPLOAD_ERR_FORM_SIZE) $msg = 'File exceeds MAX_FILE_SIZE in HTML form';
            
            http_response_code(400);
            echo json_encode(['error' => $msg]);
            break;
        }

        $file = $_FILES['file'];
        if ($file['size'] > $max_file_size) {
            http_response_code(413);
            echo json_encode(['error' => 'File too large (limit 100MB)']);
            break;
        }

        $id = bin2hex(random_bytes(16));
        $metadata = [
            'id' => $id,
            'name' => $_POST['name'] ?? 'encrypted_file',
            'type' => $_POST['type'] ?? 'application/octet-stream',
            'size' => $file['size'],
            'uploaded_at' => time()
        ];

        if (move_uploaded_file($file['tmp_name'], $storage_dir . $id)) {
            if (file_put_contents($storage_dir . $id . '.json', json_encode($metadata))) {
                echo json_encode(['id' => $id]);
            } else {
                @unlink($storage_dir . $id);
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save metadata. Check disk space/permissions.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file. Check uploads/ folder permissions.']);
        }
        break;

    case 'info':
        $id = $_GET['id'] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID format']);
            break;
        }

        $meta_file = $storage_dir . $id . '.json';
        if (file_exists($meta_file)) {
            echo file_get_contents($meta_file);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found or expired (autodeleted)']);
        }
        break;

    case 'download':
        $id = $_GET['id'] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID format']);
            break;
        }

        $file_path = $storage_dir . $id;
        $meta_file = $file_path . '.json';

        if (file_exists($file_path)) {
            // Send the file
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file_path));
            header('Content-Disposition: attachment; filename="' . $id . '"');
            readfile($file_path);

            // AUTO-DELETE after download
            @unlink($file_path);
            if (file_exists($meta_file)) @unlink($meta_file);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action requested']);
        break;
}
