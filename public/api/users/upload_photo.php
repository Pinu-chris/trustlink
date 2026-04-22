<?php
/**
 * TRUSTLINK - Upload Profile Photo API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Upload and update user profile photo
 * Features:
 * - Image validation (type, size)
 * - Automatic image resizing
 * - Old photo cleanup
 * - Session validation required
 * 
 * HTTP Method: POST
 * Endpoint: /api/users/upload_photo.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * - Content-Type: multipart/form-data
 * 
 * Form Data:
 * - profile_photo: Image file (JPEG, PNG, WEBP, max 5MB)
 * 
 * Response:
 * - 200: Photo uploaded successfully
 * - 400: Invalid file
 * - 401: Unauthorized
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\FileUpload;
use TrustLink\Config\SuccessMessages;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// ============================================================================
// CHECK IF FILE WAS UPLOADED
// ============================================================================

if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
    Response::badRequest('No file uploaded');
}

$file = $_FILES['profile_photo'];

// ============================================================================
// FILE VALIDATION
// ============================================================================

$errors = [];

// Check upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
    Response::badRequest($errorMsg);
}

// Check file size
if ($file['size'] > FileUpload::MAX_SIZE) {
    $errors['profile_photo'] = 'File size must be less than ' . FileUpload::getMaxSizeReadable();
}

// Check file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, FileUpload::ALLOWED_TYPES)) {
    $errors['profile_photo'] = 'Allowed file types: JPEG, PNG, WEBP';
}

// Get file extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, FileUpload::ALLOWED_EXTENSIONS)) {
    $errors['profile_photo'] = 'Allowed extensions: jpg, jpeg, png, webp';
}

if (!empty($errors)) {
    Response::validationError($errors, 'File validation failed');
}

// ============================================================================
// GENERATE UNIQUE FILENAME
// ============================================================================

$uploadDir = __DIR__ . '/../../assets/images/uploads/profile/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'user_' . $user['id'] . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// ============================================================================
// PROCESS IMAGE (Resize if needed)
// ============================================================================

// Create image from uploaded file
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
    default:
        Response::badRequest('Unsupported image format');
}

// Get original dimensions
$origWidth = imagesx($sourceImage);
$origHeight = imagesy($sourceImage);

// Set max dimensions (500x500 profile photo)
$maxWidth = 500;
$maxHeight = 500;

// Calculate new dimensions (maintain aspect ratio)
$ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
$newWidth = (int) ($origWidth * $ratio);
$newHeight = (int) ($origHeight * $ratio);

// Create resized image
$resizedImage = imagecreatetruecolor($newWidth, $newHeight);

// Preserve transparency for PNG
if ($mimeType === 'image/png') {
    imagealphablending($resizedImage, false);
    imagesavealpha($resizedImage, true);
    $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
    imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
}

// Resize
imagecopyresampled(
    $resizedImage, $sourceImage,
    0, 0, 0, 0,
    $newWidth, $newHeight,
    $origWidth, $origHeight
);

// Save resized image
switch ($mimeType) {
    case 'image/jpeg':
        imagejpeg($resizedImage, $filepath, 90);
        break;
    case 'image/png':
        imagepng($resizedImage, $filepath, 9);
        break;
    case 'image/webp':
        imagewebp($resizedImage, $filepath, 90);
        break;
}

// Free memory
imagedestroy($sourceImage);
imagedestroy($resizedImage);

// ============================================================================
// DELETE OLD PHOTO
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Get current profile photo
    $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $currentUser = $stmt->fetch();
    
    // Delete old photo if exists
    if (!empty($currentUser['profile_photo'])) {
        $oldPhotoPath = $uploadDir . $currentUser['profile_photo'];
        if (file_exists($oldPhotoPath)) {
            unlink($oldPhotoPath);
        }
    }
    
    // ============================================================================
    // UPDATE DATABASE
    // ============================================================================
    
    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$filename, $user['id']]);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'profile_photo_uploaded',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode(['filename' => $filename])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    $photoUrl = getenv('APP_URL') . '/assets/images/uploads/profile/' . $filename;
    
    Response::success([
        'profile_photo' => $photoUrl,
        'filename' => $filename
    ], SuccessMessages::PHOTO_UPLOADED);
    
} catch (\PDOException $e) {
    // Delete uploaded file if database update fails
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    error_log("Upload profile photo error: " . $e->getMessage());
    Response::serverError('Failed to save profile photo', false, $e);
}