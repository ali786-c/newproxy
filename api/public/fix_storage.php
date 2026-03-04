<?php
/**
 * Fix Laravel Storage Symlink for cPanel
 * Upload this to your public folder (e.g. public_html/api/) and visit: 
 * https://upgraderproxy.com/api/fix_storage.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Laravel Storage Link Fixer (cPanel Optimized)</h2>";

$publicDir = __DIR__;
$storageLink = $publicDir . '/storage';
// Relative path from public/ to storage/app/public/
$relativeTarget = '../storage/app/public';
$absoluteTarget = realpath($publicDir . '/' . $relativeTarget);

echo "<b>Current Directory:</b> $publicDir <br>";
echo "<b>Storage Link Path (to be created):</b> $storageLink <br>";
echo "<b>Target Path (Relative):</b> $relativeTarget <br>";
echo "<b>Target Path (Absolute):</b> " . ($absoluteTarget ?: "<span style='color:red'>NOT FOUND!</span>") . " <br><br>";

// 1. Remove existing link/folder
if (file_exists($storageLink) || is_link($storageLink)) {
    echo "Found existing storage entry. Removing it...<br>";
    if (is_link($storageLink)) {
        if (unlink($storageLink)) {
            echo "Deleted existing symlink.<br>";
        } else {
            echo "<span style='color:red'>Failed to delete existing symlink.</span><br>";
        }
    } else {
        $backupName = $storageLink . '_backup_' . time();
        if (rename($storageLink, $backupName)) {
            echo "Renamed existing directory to $backupName<br>";
        } else {
            echo "<span style='color:red'>Failed to rename existing directory.</span><br>";
        }
    }
}

// 2. Create the relative symlink
try {
    // We use relative path for symlink - this is MUCH safer on cPanel
    chdir($publicDir);
    if (symlink($relativeTarget, 'storage')) {
        echo "<h3 style='color:green'>SUCCESS!</h3> The RELATIVE storage link has been created successfully.<br>";
        echo "Check your images now: <a href='https://upgraderproxy.com/api/storage/proofs/'>Verify Storage Access</a>";
    } else {
        echo "<h3 style='color:red'>FAILED!</h3> Could not create symlink using relative path.<br>";
        
        // Try absolute as fallback
        if ($absoluteTarget && symlink($absoluteTarget, 'storage')) {
             echo "<h3 style='color:orange'>PARTIAL SUCCESS!</h3> Relative failed, but ABSOLUTE storage link was created.<br>";
        }
    }
} catch (Exception $e) {
    echo "<h3 style='color:red'>ERROR:</h3> " . $e->getMessage();
}

// 3. Permissions Check
if ($absoluteTarget) {
    $perms = substr(sprintf('%o', fileperms($absoluteTarget)), -4);
    echo "<br><b>Target Permissions:</b> $perms (Should be 0755 or 0775)";
}
