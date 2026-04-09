<?php
// ------------------------------------------
// Mr.X WordPress Auto Downloader 2025
// ------------------------------------------

error_reporting(0);
set_time_limit(0);

echo "<pre>";
echo "------------------------------------------\n";
echo " Mr.X WordPress Auto Downloader 2025\n";
echo "------------------------------------------\n\n";

// WordPress download URL
$wp_url = "https://wordpress.org/latest.zip";
$wp_zip = "wordpress.zip";

echo "[+] Downloading WordPress package...\n";
$download = file_put_contents($wp_zip, fopen($wp_url, 'r'));
if (!$download) {
    exit("[-] Failed to download WordPress.\n");
}
echo "[+] Download complete: $wp_zip\n";

echo "[+] Extracting files...\n";
$zip = new ZipArchive;
if ($zip->open($wp_zip) === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    echo "[+] Extraction complete.\n";
} else {
    exit("[-] Failed to extract ZIP file.\n");
}

// Move extracted files from wordpress/ folder to current directory
$wp_dir = __DIR__ . '/wordpress';
if (is_dir($wp_dir)) {
    $files = scandir($wp_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            rename($wp_dir . '/' . $file, __DIR__ . '/' . $file);
        }
    }
    rmdir($wp_dir);
    echo "[+] Moved WordPress files to current directory.\n";
}

unlink($wp_zip);
echo "[+] Removed ZIP file.\n";
echo "\n[✓] WordPress has been successfully downloaded and extracted here.\n";
echo "------------------------------------------\n";
echo "</pre>";
?>
