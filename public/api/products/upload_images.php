<?php
/**
 * TRUSTLINK - Upload Product Images API (Production Ready)
 * Version: 1.2 | March 2026
 * 
 * Description: Upload images for a specific product
 * Features:
 * - Multiple image upload (up to 5 per product)
 * - Image validation (type, size)
 * - Primary image selection (auto‑set first image if none exists)
 * - Image order management
 * - Ownership validation
 * 
 * HTTP Method: POST
 * Endpoint: /api/products/upload_images.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * - Content-Type: multipart/form-data
 * 
 * Form Data:
 * - product_id: Product ID
 * - images[]: Image files (max 5, 5MB each)
 * - is_primary: Index of primary image (optional)
 * 
 * Response:
 * - 200: Images uploaded successfully
 * - 400: Validation errors
 * - 401: Unauthorized
 * - 403: Not owner
 * - 404: Product not found
 */

// Disable error display to avoid HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header immediately
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\FileUpload;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require farmer role
$auth = new AuthMiddleware();
$user = $auth->requireFarmer();

// Get product ID
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($productId <= 0) {
    Response::badRequest('Product ID is required');
}

try {
    $db = Database::getInstance();
    
    // Verify product ownership
    $stmt = $db->prepare("
        SELECT id, name, farmer_id, status
        FROM products 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        Response::notFound('Product');
    }
    
    if ($product['farmer_id'] != $user['id']) {
        Response::forbidden('You do not own this product');
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['images'])) {
        Response::badRequest('No images uploaded');
    }
    
    $files = $_FILES['images'];
    $uploadedImages = [];
    $errors = [];
    
    // Limit number of images
    $maxImages = 5;
    if (count($files['name']) > $maxImages) {
        Response::badRequest("Maximum {$maxImages} images per product");
    }
    
    // Get existing images count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $existingCount = $stmt->fetch()['count'];
    
    if ($existingCount + count($files['name']) > $maxImages) {
        Response::badRequest("Product already has {$existingCount} images. Maximum {$maxImages} total.");
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../assets/images/uploads/products/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            Response::serverError('Failed to create upload directory');
        }
    }
    
    // Process each image
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "File '{$files['name'][$i]}' upload failed (error code: {$files['error'][$i]})";
            continue;
        }
        
        $file = [
            'name' => $files['name'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'size' => $files['size'][$i],
            'type' => $files['type'][$i]
        ];
        
        // Validate file size
        if ($file['size'] > FileUpload::MAX_SIZE) {
            $errors[] = "File '{$file['name']}' exceeds " . FileUpload::getMaxSizeReadable();
            continue;
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, FileUpload::ALLOWED_TYPES)) {
            $errors[] = "File '{$file['name']}' has unsupported type. Allowed: JPEG, PNG, WEBP";
            continue;
        }
        
        // Validate extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, FileUpload::ALLOWED_EXTENSIONS)) {
            $errors[] = "File '{$file['name']}' has invalid extension. Allowed: jpg, jpeg, png, webp";
            continue;
        }
        
        // Generate unique filename
        $filename = 'product_' . $productId . '_' . time() . '_' . $i . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Process image (resize)
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($file['tmp_name']);
                break;
        }
        
        if ($sourceImage) {
            // Resize to max 800x800 (maintain aspect ratio)
            $maxWidth = 800;
            $maxHeight = 800;
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);
            
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $newWidth = (int) ($origWidth * $ratio);
            $newHeight = (int) ($origHeight * $ratio);
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            
            // Save resized image
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($resizedImage, $filepath, 85);
                    break;
                case 'image/png':
                    imagepng($resizedImage, $filepath, 8);
                    break;
                case 'image/webp':
                    imagewebp($resizedImage, $filepath, 85);
                    break;
            }
            
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
        } else {
            // Fallback: move file as-is (though this shouldn't happen)
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $errors[] = "Failed to move uploaded file '{$file['name']}'";
                continue;
            }
        }
        
        // ============================================================
        // ============================================================
                // PRIMARY IMAGE LOGIC (AUTO-SET FIRST IMAGE IF NONE EXISTS)
                // ============================================================
                // Check if this image should be primary based on form data
                $isPrimary = (isset($_POST['is_primary']) && (int) $_POST['is_primary'] === $i);
                
                // If no primary is selected via form, and this is the first image,
                // check if the product already has any primary image.
                if (!$isPrimary && $i == 0) {
                    // First, check if product already has a primary image
                    $stmtCheck = $db->prepare("SELECT EXISTS (SELECT 1 FROM product_images WHERE product_id = ? AND is_primary = true)");
                    $stmtCheck->execute([$productId]);
                    $hasPrimary = $stmtCheck->fetchColumn();
                    
                    error_log("DEBUG: product $productId, image $i, hasPrimary = " . ($hasPrimary ? 'true' : 'false') . ", isPrimary before auto = false");
                    
                    if (!$hasPrimary) {
                        $isPrimary = true;
                        error_log("Auto-setting image #{$i} as primary for product {$productId} (no existing primary)");
                    }
                } else {
                    error_log("DEBUG: product $productId, image $i, isPrimary from form = " . ($isPrimary ? 'true' : 'false'));
                }
                
                // Convert boolean to integer for safe insertion
                $isPrimaryInt = $isPrimary ? 1 : 0;
                error_log("DEBUG: Inserting with isPrimaryInt = $isPrimaryInt");
                
        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO product_images (product_id, image_url, is_primary, display_order, created_at)
            VALUES (?, ?, ?, ?, NOW())
            RETURNING id
        ");
        $stmt->execute([$productId, $filename, $isPrimaryInt, $i]);
        $imageId = $stmt->fetch()['id'];
        
        $uploadedImages[] = [
            'id' => $imageId,
            'url' => (getenv('APP_URL') ?: 'http://localhost/trustfiles') . '/assets/images/uploads/products/' . $filename,
            'is_primary' => $isPrimary
        ];
        
        // If this is primary, unset other primary flags (to be safe)
        if ($isPrimary) {
            $stmt = $db->prepare("
                UPDATE product_images 
                SET is_primary = false 
                WHERE product_id = ? AND id != ?
            ");
            $stmt->execute([$productId, $imageId]);
        }
    }
    
    // Return errors if any and no successful uploads
    if (!empty($errors) && empty($uploadedImages)) {
        Response::validationError(['images' => $errors], 'Image upload failed');
    }
    
    // Log activity (optional)
    if (!empty($uploadedImages)) {
        try {
            // Check if activity_logs table exists before inserting
            $tableCheck = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'activity_logs')");
            if ($tableCheck->fetchColumn()) {
                $logData = [
                    'user_id' => $user['id'],
                    'action' => 'product_images_uploaded',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => json_encode([
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'images_uploaded' => count($uploadedImages)
                    ])
                ];
                Database::insert('activity_logs', $logData);
            }
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    // Success response
    Response::success([
        'product_id' => $productId,
        'product_name' => $product['name'],
        'images' => $uploadedImages,
        'errors' => $errors,
        'uploaded_count' => count($uploadedImages),
        'failed_count' => count($errors)
    ], count($uploadedImages) . ' image(s) uploaded successfully');
    
} catch (\PDOException $e) {
    error_log("Upload images PDO error: " . $e->getMessage());
    Response::serverError('Database error: ' . $e->getMessage());
} catch (\Exception $e) {
    error_log("Upload images general error: " . $e->getMessage());
    Response::serverError('Failed to upload images: ' . $e->getMessage());
}
?>