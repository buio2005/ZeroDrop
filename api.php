<?php
/**
 * ZeroDrop API - Simple Backend for Shared Hosting
 * Handles encrypted blobs and metadata with auto-cleanup and rate limiting.
 * 
 * NOTE: Suppressing internal errors to ensure valid JSON response.
 */

error_reporting(0); // Suppress all errors/warnings to keep JSON clean
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$storage_dir = 'uploads/';
$rate_limit_dir = 'uploads/.ratelimit/';
$max_file_size = 100 * 1024 * 1024; // 100MB limit
$expiry_time = 24 * 60 * 60; // 24 hours
$rate_limit_max = 5; // Max 5 uploads
$rate_limit_window = 600; // per 10 minutes (600 seconds)

// Ensure storage and rate limit directories exist
if (!file_exists($storage_dir)) {
    mkdir($storage_dir, 0755, true);
}
if (!file_exists($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0755, true);
    // Create .htaccess to hide rate limit files
    file_put_contents($rate_limit_dir . '.htaccess', 'Deny from all');
}

// Privacy-focused Rate Limiting (IP hashing)
function check_rate_limit($dir, $max, $window) {
    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR']);
    $limit_file = $dir . $ip_hash . '.json';
    
    $now = time();
    $history = [];
    
    if (file_exists($limit_file)) {
        $history = json_decode(file_get_contents($limit_file), true) ?: [];
    }
    
    // Filter out old timestamps
    $history = array_filter($history, function($ts) use ($now, $window) {
        return $ts > ($now - $window);
    });
    
    if (count($history) >= $max) {
        return false;
    }
    
    // Add current timestamp and save
    $history[] = $now;
    file_put_contents($limit_file, json_encode(array_values($history)));
    return true;
}

// Function to delete old files
function cleanup($dir, $expiry) {
    foreach (glob($dir . "*") as $file) {
        if (is_file($file) && filemtime($file) < time() - $expiry) {
            @unlink($file);
        }
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        // Run cleanup on every upload
        cleanup($storage_dir, $expiry_time);
        cleanup($rate_limit_dir, 3600); // Cleanup rate limit files older than 1 hour

        // 1. Check Rate Limit
        if (!check_rate_limit($rate_limit_dir, $rate_limit_max, $rate_limit_window)) {
            http_response_code(429);
            echo json_encode(['error' => 'Troppi upload. Riprova tra 10 minuti.']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Errore durante l\'upload del file.']);
            break;
        }

        $file = $_FILES['file'];
        
        // 2. MIME Validation (Sicurezza)
        // Anche se i file sono criptati, verifichiamo che non vengano inviati file potenzialmente pericolosi 
        // che l'hosting potrebbe interpretare se mal configurato.
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', '7z', 'rar', 'mp4', 'mp3', 'txt', 'mov', 'wav', 'heic', 'psd'];
        $file_info = pathinfo($_POST['name'] ?? '');
        $extension = strtolower($file_info['extension'] ?? '');

        if (!in_array($extension, $allowed_extensions)) {
            http_response_code(403);
            echo json_encode(['error' => 'Estensione file non consentita per motivi di sicurezza.']);
            break;
        }

        if ($file['size'] > $max_file_size) {
            http_response_code(413);
            echo json_encode(['error' => 'File troppo grande (max 100MB)']);
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
            file_put_contents($storage_dir . $id . '.json', json_encode($metadata));
            echo json_encode(['id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Impossibile salvare il file sul server.']);
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
            echo json_encode(['error' => 'File non trovato o scaduto']);
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
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file_path));
            header('Content-Disposition: attachment; filename="' . $id . '"');
            readfile($file_path);

            @unlink($file_path);
            if (file_exists($meta_file)) @unlink($meta_file);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File non trovato']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
        break;
}
