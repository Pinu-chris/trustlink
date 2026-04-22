<?php

try {
    $conn = new PDO("pgsql:host=localhost;port=5432;dbname=trustlink", "postgres", "Nasiuma.12?");

    echo "Connected successfully!";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

?>