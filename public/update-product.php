<?php
// update-product.php
session_start();

require_once __DIR__ . '/../config/db.php';

use TrustLink\Config\Database;

// Helper function for redirect with message
function redirect($url, $msg = null) {
    if ($msg) $_SESSION['flash'] = $msg;
    header("Location: $url");
    exit;
}

// Check POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my-products.php', 'Invalid request method.');
}

// Validate product ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    redirect('my-products.php', 'Invalid product ID.');
}

// Sanitize and validate inputs
$name = trim($_POST['name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$unit = trim($_POST['unit'] ?? '');

if (!$name || $price <= 0 || $quantity < 0 || !$unit) {
    redirect("edit-product.php?id=$id", 'Please provide valid product details.');
}

// Handle image upload
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg','jpeg','png','gif'];
    $fileTmp = $_FILES['image']['tmp_name'];
    $fileName = basename($_FILES['image']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        redirect("edit-product.php?id=$id", 'Invalid image format. Allowed: jpg, png, gif.');
    }

    // Generate unique name
    $imageName = 'product_' . $id . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../assets/images/uploads/products/';

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!move_uploaded_file($fileTmp, $uploadDir . $imageName)) {
        redirect("edit-product.php?id=$id", 'Failed to upload image.');
    }

    // Optionally delete old image
    $oldImage = Database::fetchOne("SELECT image FROM products WHERE id = ?", [$id]);
    if (!empty($oldImage['image']) && file_exists($uploadDir . $oldImage['image'])) {
        @unlink($uploadDir . $oldImage['image']);
    }
}

// Build data array for update
$data = [
    'name' => $name,
    'price' => $price,
    'quantity' => $quantity,
    'unit' => $unit,
];
if ($imageName) $data['image'] = $imageName;

try {
    Database::update('products', $data, 'id = ?', [$id]);
    redirect('my-products.php', 'Product updated successfully!');
} catch (Exception $e) {
    redirect("edit-product.php?id=$id", 'Database error: ' . $e->getMessage());
}
