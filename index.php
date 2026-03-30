<?php
// index.php - Text File Manager
// PHP 8.2.28 / Xserver & XAMPP compatible

$baseUrl = 'https://22p2.motisan.info';
$mdDir = __DIR__ . '/md';
$txtDir = __DIR__ . '/txt';

// ディレクトリ作成
if (!is_dir($mdDir)) mkdir($mdDir, 0755, true);
if (!is_dir($txtDir)) mkdir($txtDir, 0755, true);

$message = '';
$messageType = '';

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // テキストからファイル作成
    if ($action === 'create') {
        $content = $_POST['content'] ?? '';
        $filename = trim($_POST['filename'] ?? '');
        $ext = $_POST['ext'] ?? 'md';

        if ($ext !== 'md' && $ext !== 'txt') $ext = 'md';

        if ($content === '') {
            $message = 'テキストが空です。';
            $messageType = 'danger';
        } else {
            if ($filename === '') {
                $filename = date('Ymd_His');
            }
            // ファイル名サニタイズ（拡張子除去・安全な文字のみ）
            $filename = preg_replace('/\\.[^.]*$/', '', $filename);
            $filename = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $filename);
            if ($filename === '') $filename = date('Ymd_His');

            $dir = $ext === 'md' ? $mdDir : $txtDir;
            $filepath = $dir . '/' . $filename . '.' . $ext;

            // 衝突回避
            $counter = 1;
            while (file_exists($filepath)) {
                $filepath = $dir . '/' . $filename . '_' . $counter . '.' . $ext;
                $counter++;
            }

            file_put_contents($filepath, $content);
            $message = 'ファイルを作成しました: ' . basename($filepath);
            $messageType = 'success';
        }
    }

    // ファイルアップロード
    if ($action === 'upload') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $origName = $_FILES['file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($ext !== 'md' && $ext !== 'txt') {
                $message = 'mdまたはtxtファイルのみアップロード可能です。';
                $messageType = 'danger';
            } else {
                $dir = $ext === 'md' ? $mdDir : $txtDir;
                $safeName = preg_replace('/[^a-zA-Z0-9_\\-\\.]/', '_', $origName);
                $filepath = $dir . '/' . $safeName;

                // 衝突回避
                $base = preg_replace('/\\.[^.]*$/', '', $safeName);
                $counter = 1;
                while (file_exists($filepath)) {
                    $filepath = $dir . '/' . $base . '_' . $counter . '.' . $ext;
                    $counter++;
                }

                move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
                $message = 'アップロード完了: ' . basename($filepath);
                $messageType = 'success';
            }
        } else {
            $message = 'ファイルを選択してください。';
            $messageType = 'danger';
        }
    }

    // ファイル削除
    if ($action === 'delete') {
        $type = $_POST['type'] ?? '';
        $file = $_POST['file'] ?? '';
        // ディレクトリトラバーサル防止
        $file = basename($file);
        if (($type === 'md' || $type === 'txt') && $file !== '') {
            $dir = $type === 'md' ? $mdDir : $txtDir;
            $filepath = $dir . '/' . $file;
            if (file_exists($filepath)) {
                unlink($filepath);
                $message = '削除しました: ' . $file;
                $messageType = 'success';
            }
        }
    }
}

// ダウンロード処理（GET）
if (isset($_GET['download'])) {
    $type = $_GET['type'] ?? '';
    $file = basename($_GET['download']);
    if (($type === 'md' || $type === 'txt') && $file !== '') {
        $dir = $type === 'md' ? $mdDir : $txtDir;
        $filepath = $dir . '/' . $file;
        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
}

// ファイル一覧取得
function getFiles(string $dir, string $type, string $baseUrl): array {
    $files = [];
    if (!is_dir($dir)) return $files;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $filepath = $dir . '/' . $f;
        if (!is_file($filepath)) continue;
        $content = file_get_contents($filepath);
        $excerpt = mb_substr(trim($content), 0, 20, 'UTF-8');
        if (mb_strlen(trim($content), 'UTF-8') > 20) $excerpt .= '…';
        $files[] = [
            'name' => $f,
            'type' => $type,
            'url' => $baseUrl . '/' . $type . '/' . rawurlencode($f),
            'content' => $content,
            'excerpt' => $excerpt,
            'size' => filesize($filepath),
            'modified' => filemtime($filepath),
        ];
    }
    // 更新日時降順
    usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
    return $files;
}

$mdFiles = getFiles($mdDir, 'md', $baseUrl);
$txtFiles = getFiles($txtDir, 'txt', $baseUrl);
$allFiles = array_merge($mdFiles, $txtFiles);
usort($allFiles, fn($a, $b) => $b['modified'] <=> $a['modified']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Text File Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .file-content-pre {
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 60vh;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        .copy-btn.copied {
            color: #198754 !important;
        }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 800px;">
    <h1 class="h4 mb-4">📄 Text File Manager</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- タブ -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-create" type="button">作成</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-upload" type="button">アップロード</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-files" type="button">一覧 (<?= count($allFiles) ?>)</button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-3">
        <!-- 作成タブ -->
        <div class="tab-pane fade show active" id="tab-create">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label">ファイル名（空欄で自動生成）</label>
                    <input type="text" name="filename" class="form-control" placeholder="例: my-context">
                </div>
                <div class="mb-3">
                    <label class="form-label">形式</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ext" id="ext-md" value="md" checked>
                            <label class="form-check-label" for="ext-md">.md</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="ext" id="ext-txt" value="txt">
                            <label class="form-check-label" for="ext-txt">.txt</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">内容</label>
                    <textarea name="content" class="form-control" rows="12" placeholder="テキストを入力..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">作成</button>
            </form>
        </div>

        <!-- アップロードタブ -->
        <div class="tab-pane fade" id="tab-upload">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="mb-3">
                    <label class="form-label">ファイル選択（.md / .txt のみ）</label>
                    <input type="file" name="file" class="form-control" accept=".md,.txt">
                </div>
                <button type="submit" class="btn btn-primary">アップロード</button>
            </form>
        </div>

        <!-- 一覧タブ -->
        <div class="tab-pane fade" id="tab-files">
            <?php if (empty($allFiles)): ?>
                <p class="text-muted my-3">ファイルがありません。</p>
            <?php else: ?>
                <div class="list-group my-2">
                    <?php foreach ($allFiles as $i => $f): ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start"
                             role="button" data-bs-toggle="modal" data-bs-target="#modal-<?= $i ?>">
                            <div class="me-2 overflow-hidden">
                                <div class="fw-semibold">
                                    <span class="badge bg-<?= $f['type'] === 'md' ? 'primary' : 'secondary' ?> me-1"><?= $f['type'] ?></span>
                                    <?= htmlspecialchars($f['name']) ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($f['excerpt']) ?></small>
                            </div>
                            <small class="text-muted text-nowrap"><?= date('m/d H:i', $f['modified']) ?></small>
                        </div>

                        <!-- モーダル -->
                        <div class="modal fade" id="modal-<?= $i ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <h5 class="modal-title text-truncate mb-0"><?= htmlspecialchars($f['name']) ?></h5>
                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn"
                                                    onclick="copyUrl(this, '<?= htmlspecialchars($f['url'], ENT_QUOTES) ?>')" title="URLコピー">
                                                📋
                                            </button>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="file-content-pre"><?= htmlspecialchars($f['content']) ?></div>
                                    </div>
                                    <div class="modal-footer justify-content-between">
                                        <form method="post" onsubmit="return confirm('削除しますか？')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type" value="<?= $f['type'] ?>">
                                            <input type="hidden" name="file" value="<?= htmlspecialchars($f['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                                        </form>
                                        <a href="?download=<?= rawurlencode($f['name']) ?>&type=<?= $f['type'] ?>"
                                           class="btn btn-sm btn-outline-primary">ダウンロード</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyUrl(btn, url) {
    navigator.clipboard.writeText(url).then(() => {
        btn.classList.add('copied');
        btn.textContent = '✅';
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.textContent = '📋';
        }, 1500);
    });
}
</script>
</body>
</html>