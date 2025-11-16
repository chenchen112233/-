<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>文件上傳網站</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; }
        .container { max-width: 400px; margin: 80px auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px #0001; }
        .btn { background: #FF8BA7; color: #fff; border: none; padding: 0.5rem 1.5rem; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #ff6f91; }
        #preview { background: #f3f4f6; border: 1px solid #ddd; padding: 1em; margin-top: 1em; min-height: 100px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h2>上傳文件</h2>
        <form action="uploads.php" method="post" enctype="multipart/form-data">
            <input type="file" name="myfile" required>
            <br><br>
            <button class="btn" type="submit">上傳</button>
        </form>
        <?php if (isset($_GET['msg'])): ?>
            <p style="color:green;"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        <?php endif; ?>

        <h3 style="margin-top:2em;">已上傳檔案</h3>
        <button id="deleteSelectedBtn" class="btn" type="button" style="margin-bottom:10px;background:#f87171;">刪除選取</button>
        <ul id="fileList">
            <?php
            $dir = __DIR__ . '/uploads';
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                if (count($files) === 0) {
                    echo "<li>目前沒有檔案</li>";
                } else {
                    foreach ($files as $file) {
                        $safeFile = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
                        echo "<li data-filename=\"$safeFile\">";
                        // 加上 checkbox（每個檔案不是獨立刪除按鈕）
                        echo "<input type=\"checkbox\" class=\"file-checkbox\" data-filename=\"$safeFile\" id=\"cb_" . md5($safeFile) . "\"> ";
                        echo "<label for=\"cb_" . md5($safeFile) . "\"><a href=\"#\" class=\"file-link\" data-filename=\"$safeFile\">$safeFile</a></label>";
                        echo "</li>";
                    }
                }
            } else {
                echo "<li>目前沒有檔案</li>";
            }
            ?>
        </ul>

        <h3>檔案內容預覽</h3>
        <!-- 按鈕已移除 -->
        <div id="preview">請點選上方檔案名稱預覽內容</div>
                <button id="openWordBtn" class="btn" type="button" style="margin-top:10px;">在 Word 編輯器開啟</button>
    </div>
       <script>
    document.addEventListener('DOMContentLoaded', function () {
        let currentSelectedFile = null;
        const preview = document.getElementById('preview');

        // 檔案連結點擊預覽（保留）
        document.querySelectorAll('.file-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const filename = this.getAttribute('data-filename');
                currentSelectedFile = filename;
                if (filename.toLowerCase().endsWith('.pdf')) {
                    preview.innerHTML = `<iframe src="uploads/${encodeURIComponent(filename)}" width="100%" height="500px"></iframe>`;
                } else {
                    fetch('preview.php?file=' + encodeURIComponent(filename))
                        .then(res => res.text())
                        .then(text => { preview.textContent = text; })
                        .catch(() => { preview.textContent = '無法載入檔案內容'; });
                }
            });
        });

        // 刪除選取（單一 endpoint 支援多檔刪除）
        document.getElementById('deleteSelectedBtn').addEventListener('click', function () {
            const checked = Array.from(document.querySelectorAll('.file-checkbox:checked'));
            if (checked.length === 0) {
                alert('請先勾選要刪除的檔案');
                return;
            }
            const files = checked.map(cb => cb.dataset.filename);
            if (!confirm('確定要刪除 ' + files.length + ' 個檔案？此動作無法復原。')) return;

            const btn = this;
            btn.disabled = true;
            btn.textContent = '刪除中...';

            fetch('delete_upload.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ files: files })
            })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('delete_upload.php 非 JSON 回應：', text);
                    throw new Error('伺服器回傳格式錯誤');
                }
            })
            .then(data => {
                if (data && data.success) {
                    // 移除已刪除的 li，同步 preview
                    (data.deleted || []).forEach(fname => {
                        const li = document.querySelector('li[data-filename="' + CSS.escape(fname) + '"]');
                        if (li) li.remove();
                        if (currentSelectedFile === fname) {
                            currentSelectedFile = null;
                            preview.textContent = '請點選上方檔案名稱預覽內容';
                        }
                    });
                    // 顯示未刪除或錯誤
                    if (data.failed && Object.keys(data.failed).length > 0) {
                        let msg = '部分檔案刪除失敗：\n';
                        for (const f in data.failed) msg += f + '：' + data.failed[f] + '\n';
                        alert(msg);
                    } else {
                        alert('已刪除選取的檔案');
                    }
                } else {
                    alert('刪除失敗：' + (data && data.error ? data.error : '伺服器錯誤'));
                    console.error('delete_upload error response:', data);
                }
            })
            .catch(err => {
                console.error('刪除請求錯誤：', err);
                alert('網路或伺服器錯誤，請查看 Console 與 Network。');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = '刪除選取';
            });
        });

        // 開啟 Word 編輯器按鈕（維持）
        document.getElementById('openWordBtn').addEventListener('click', function () {
            if (currentSelectedFile) {
                window.open('word.php?file=' + encodeURIComponent(currentSelectedFile), '_blank');
            } else {
                alert('請先點選一個檔案！');
            }
        });
    });
    </script>
</body>
</html>