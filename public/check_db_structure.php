<?php
session_name('trustlink_session');
session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    die('Please login first: <a href="login.php">Login</a>');
}

require_once __DIR__ . '/../config/db.php';
use TrustLink\Config\Database;

echo "<h2>Database Structure Check</h2>";

try {
    $db = Database::getInstance();
    
    // Check if products table exists (PostgreSQL way)
    $stmt = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'products')");
    $exists = $stmt->fetchColumn();
    
    if (!$exists) {
        echo "<p style='color:red'>❌ Products table does NOT exist!</p>";
        echo "<h3>Creating products table...</h3>";
        
        // Create products table for PostgreSQL
        $createTable = "
        CREATE TABLE IF NOT EXISTS products (
            id SERIAL PRIMARY KEY,
            farmer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL,
            status BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createTable);
        echo "<p style='color:green'>✅ Products table created successfully!</p>";
    } else {
        echo "<p style='color:green'>✅ Products table exists</p>";
    }
    
    // Show table structure (PostgreSQL way)
    echo "<h3>Products Table Structure:</h3>";
    $stmt = $db->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'products'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "<td>{$col['column_default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if user exists
    echo "<h3>Current User:</h3>";
    $userId = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p style='color:green'>✅ User found: ID={$user['id']}, Name={$user['name']}, Role={$user['role']}</p>";
    } else {
        echo "<p style='color:red'>❌ User not found with ID: $userId</p>";
    }
    
    // Check existing products
    echo "<h3>Existing Products:</h3>";
    $stmt = $db->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $products = $stmt->fetchAll();
    
    if (count($products) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Quantity</th><th>Unit</th><th>Status</th></tr>";
        foreach ($products as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['name']}</td>";
            echo "<td>{$p['price']}</td>";
            echo "<td>{$p['quantity']}</td>";
            echo "<td>{$p['unit']}</td>";
            echo "<td>" . ($p['status'] ? 'Active' : 'Inactive') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No products found for this farmer.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>