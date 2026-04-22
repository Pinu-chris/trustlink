<?php
// 1. Load the database manager
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

header('Content-Type: application/json');

try {
    // 2. Directly access the Singleton instance via the full namespace
    // This bypasses the need for the global db() function which is failing
    $db = \TrustLink\Config\Database::getInstance(); 

    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // 3. Get limit from URL
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;

    // 4. PostgreSQL Query (Randomized)
        // Change this line:
        $query = "SELECT p.*, u.name as farmer_name 
                FROM products p 
                LEFT JOIN users u ON p.farmer_id = u.id 
                WHERE p.status = true  -- Use 'true' without quotes for boolean
                ORDER BY RANDOM() 
                LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Success Response
    echo json_encode([
        "success" => true,
        "message" => "Recommended products retrieved successfully",
        "status_code" => 200,
        "data" => $products
    ]);

} catch (Exception $e) {
    // Prevents JSON.parse error in api.js by returning valid JSON
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "API Error: " . $e->getMessage(),
        "status_code" => 500,
        "data" => []
    ]);
}