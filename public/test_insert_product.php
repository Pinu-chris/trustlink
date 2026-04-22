<?php
session_name('trustlink_session');
session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    die('Please login first: <a href="login.php">Login</a>');
}

require_once __DIR__ . '/../config/db.php';
use TrustLink\Config\Database;

$userId = $_SESSION['user_id'];

echo "<h2>Test Product Insert (PostgreSQL)</h2>";
echo "<p>User ID: $userId</p>";

try {
    $db = Database::getInstance();
    
    // Test data - NOTE: status is BOOLEAN, so use true/false not 'active'
    $name = 'Test Product ' . date('Y-m-d H:i:s');
    $category = 'vegetables';
    $description = 'Test product description';
    $price = 100.00;
    $quantity = 10;
    $unit = 'kg';
    $status = true;  // BOOLEAN value
    
    echo "<h3>Attempting to insert:</h3>";
    echo "<pre>";
    echo "farmer_id: $userId\n";
    echo "name: $name\n";
    echo "category: $category\n";
    echo "description: $description\n";
    echo "price: $price\n";
    echo "quantity: $quantity\n";
    echo "unit: $unit\n";
    echo "status: " . ($status ? 'true' : 'false') . "\n";
    echo "</pre>";
    
    // PostgreSQL compatible insert
    $sql = "INSERT INTO products (farmer_id, name, category, description, price, quantity, unit, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            RETURNING id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $name, $category, $description, $price, $quantity, $unit, $status]);
    $result = $stmt->fetch();
    
    if ($result) {
        $productId = $result['id'];
        echo "<p style='color:green'>✅ Product inserted successfully! ID: $productId</p>";
        
        // Verify the insert
        $verify = $db->prepare("SELECT * FROM products WHERE id = ?");
        $verify->execute([$productId]);
        $product = $verify->fetch();
        echo "<h3>Verified Product:</h3>";
        echo "<pre>";
        print_r($product);
        echo "</pre>";
        
    } else {
        echo "<p style='color:red'>❌ Insert failed</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    echo "<h3>Full Error Details:</h3>";
    echo "<pre>";
    print_r($e);
    echo "</pre>";
}
?>