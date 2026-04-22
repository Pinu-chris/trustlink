<?php
echo "<pre><h3>Project Directory Map</h3>";

// Get the absolute path of the current folder
$currentDir = __DIR__;
echo "Current Directory (public): " . $currentDir . "\n";

// Go up one level to the root
$rootDir = dirname($currentDir);
echo "Root Directory (trustlink): " . $rootDir . "\n\n";

function mapDirectory($dir, $prefix = '') {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        echo $prefix . (is_dir($path) ? "📁 " : "📄 ") . $item . "\n";
        
        // Only go 2 levels deep to keep it readable
        if (is_dir($path) && strlen($prefix) < 10) {
            mapDirectory($path, $prefix . "    ");
        }
    }
}

mapDirectory($rootDir);
echo "</pre>";
?>