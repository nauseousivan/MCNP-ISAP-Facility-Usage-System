<?php
// init_uploads.php

echo "<pre>";

// Initialize uploads directory structure
$directories = [
    'uploads',
    'uploads/profiles',
    'uploads/covers' // Added the covers directory
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        // The `true` parameter creates parent directories if they don't exist.
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir. Please check folder permissions.\n";
        }
    } else {
        echo "Directory already exists: $dir\n";
    }
}

echo "\nUploads directory structure initialized successfully!\n";
echo "You can now upload profile pictures and cover photos.\n";

echo "</pre>";
?>