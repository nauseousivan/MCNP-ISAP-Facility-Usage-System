<?php
// Initialize uploads directory structure
$directories = [
    'uploads',
    'uploads/profiles'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir\n";
        }
    } else {
        echo "Directory already exists: $dir\n";
    }
    
    // Create .htaccess to allow image access
    $htaccess_path = $dir . '/.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif)$\">\n";
        $htaccess_content .= "    Order Allow,Deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            echo "Created .htaccess in: $dir\n";
        }
    }
}

echo "\nUploads directory structure initialized successfully!\n";
echo "You can now upload profile pictures.\n";
?>
