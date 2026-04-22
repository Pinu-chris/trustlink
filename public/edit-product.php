<?php
// edit-product.php
session_start();

require_once __DIR__ . '/../config/db.php';

use TrustLink\Config\Database;

// Get product ID from query string
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("No product selected.");
}

// Fetch product using your Database class
try {
    $stmt = Database::getInstance()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        die("Product not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Product - <?php echo htmlspecialchars($product['name']); ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    padding: 20px;
}
.container {
    max-width: 600px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 0 12px rgba(0,0,0,0.1);
}
h2 { margin-bottom: 20px; }
form label { display: block; margin-top: 15px; font-weight: bold; }
form input[type="text"],
form input[type="number"],
form input[type="file"] {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    border-radius: 4px;
    border: 1px solid #ccc;
}
form button {
    margin-top: 20px;
    padding: 10px 15px;
    border: none;
    background: #28a745;
    color: white;
    border-radius: 5px;
    cursor: pointer;
}
form button:hover { background: #218838; }
img.preview {
    display: block;
    margin-top: 10px;
    max-width: 200px;
    border-radius: 5px;
}
</style>
</head>
<body>

<div class="container">
    <h2>Edit Product</h2>

    <form method="POST" action="update-product.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

        <label for="price">Price (KES)</label>
        <input type="number" id="price" name="price" value="<?php echo $product['price']; ?>" required>

        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" value="<?php echo $product['quantity']; ?>" required>

        <label for="unit">Unit</label>
        <input type="text" id="unit" name="unit" value="<?php echo htmlspecialchars($product['unit']); ?>" required>

        <label for="image">Image</label>
        <input type="file" id="image" name="image">
        <?php if (!empty($product['image'])): ?>
            <img class="preview" src="../assets/images/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image">
        <?php endif; ?>

        <button type="submit">Update Product</button>
    </form>
</div>

</body>
</html>