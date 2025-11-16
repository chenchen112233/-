<?php
// 簡單的上傳介面 + uploads 目錄瀏覽（外觀更新為現代卡片風）
ini_set('display_errors',0);
error_reporting(0);

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

function toUtf8($s){
    if ($s === null) return '';
    if (mb_detect_encoding($s, 'UTF-8', true)) return $s;
    $try = @mb_convert_encoding($s, 'UTF-8', 'CP950');
    if ($try !== false && mb_detect_encoding($try, 'UTF-8', true)) return $try;
    $encs = ['BIG5','GBK','GB2312','ISO-8859-1'];
    foreach ($encs as $enc) {
        $t = @mb_convert_encoding($s, 'UTF-8', $enc);
        if ($t !== false && mb_detect_encoding($t, 'UTF-8', true)) return $t;
    }
    $t = @iconv('CP950','UTF-8//IGNORE',$s);
    if ($t !== false) return $t;
    return $s;
}

$folder = isset($_GET['folder']) ? rawurldecode($_GET['folder']) : '';
$folder = str_replace(['..','/','\\'], '', $folder);
$folderPath = $folder ? $baseDir . DIRECTORY_SEPARATOR . $folder : $baseDir;
?>
<!doctype html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8">
  <title>Uploads - 管理介面</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{
      --bg: #ffffff;        /* 頁面底色：純白 */
      --card: #ffffff;      /* 卡片底色 */
      --primary: #FF8A65;
      --muted: #9E9E9E;
      --shadow: rgba(0,0,0,0.04); /* 更淡的陰影 */
      --accent: #4DB6AC;
      --subtle: #f6f7f8;    /* 次要區塊淺灰 */
    }
    *{box-sizing:border-box;font-family: "Microsoft JhengHei", Arial, sans-serif}
    body{background:var(--bg);margin:0;padding:24px;color:#333}
    .wrap{max-width:1100px;margin:0 auto}
    header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
    .brand{display:flex;align-items:center;gap:12px}
    .logo{width:48px;height:48px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
    h1{font-size:20px;margin:0}
    .grid{display:grid;grid-template-columns:280px 1fr;gap:18px}
    .card{background:var(--card);border-radius:12px;padding:16px;box-shadow:0 6px 18px var(--shadow)}
    .upload-area{border:2px dashed #eee;border-radius:10px;padding:18px;text-align:center;background:var(--subtle)}
    .upload-btn{background:var(--primary);color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer}
    .folder-list a{display:block;padding:10px;border-radius:8px;color:#333;text-decoration:none;margin-bottom:6px}
    .folder-list a.active{background:#fff4f0;border:1px solid #ffd8c8}
    .file-list .file-row{display:flex;align-items:center;justify-content:space-between;padding:8px;border-radius:8px;border-bottom:1px dashed #f0f0f0}
    .file-list .file-row:last-child{border-bottom:0}
    .file-link{color:var(--accent);text-decoration:none}
    .controls{display:flex;gap:8px;align-items:center}
    .btn{padding:8px 10px;border-radius:8px;border:0;cursor:pointer}
    .btn.ghost{background:transparent;border:1px solid #eee}
    .small{font-size:13px;color:var(--muted)}
    .checkbox{margin-right:8px}
    @media (max-width:820px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <div class="brand">
        <div class="logo">
          <img src="assets/monster.png" alt="小書怪" style="width:48px;height:48px;border-radius:10px;object-fit:cover;display:block">
        </div>
         <div>
           <h1>PDF管理</h1>
         </div>
       </div>
      <div class="controls">
        <button class="btn" id="backBtn"><i class="fa fa-arrow-left"></i> 回上一頁</button>
        <button class="btn ghost" id="refreshBtn"><i class="fa fa-sync"></i> 重新整理</button>
        <button class="btn" id="deleteSelectedBtn"><i class="fa fa-trash"></i> 刪除選取</button>
        <button class="btn" id="deleteFoldersBtn"><i class="fa fa-folder-minus"></i> 刪除資料夾</button>
      </div>
    </header>

    <div class="grid">
      <!-- 左側：上傳與資料夾 -->
      <div>
        <div class="card" style="margin-bottom:12px">
          <h3 style="margin:0 0 8px 0">上傳 PDF</h3>
          <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
            <div class="upload-area">
              <div style="margin-bottom:8px"><i class="fa fa-cloud-upload-alt" style="font-size:28px;color:var(--primary)"></i></div>
              <div class="small">拖放或選擇檔案上傳（PDF）</div>
              <div style="margin-top:12px">
                <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf" required>
              </div>
              <div style="margin-top:10px;text-align:left">
                <label class="small">放到子資料夾（可空白，自動使用日期）</label>
                <input type="text" name="target_folder" id="target_folder" placeholder="例如 my_notes" style="width:100%;padding:8px;border-radius:8px;border:1px solid #eee;margin-top:6px">
              </div>
              <div style="margin-top:10px">
                <button type="submit" class="upload-btn">上傳</button>
              </div>
            </div>
          </form>
        </div>

        <div class="card">
          <h3 style="margin:0 0 8px 0">資料夾</h3>
          <div class="folder-list" id="folderList" style="margin-top:8px;max-height:56vh;overflow:auto">
            <?php
              $dirs = array_filter(scandir($baseDir), function($d) use($baseDir){
                return $d !== '.' && $d !== '..' && is_dir($baseDir . DIRECTORY_SEPARATOR . $d);
              });
              if (empty($dirs)) {
                echo '<div class="small">尚無資料夾</div>';
              } else {
                foreach ($dirs as $d) {
                  $display = toUtf8($d);
                  $link = 'index7.php?folder=' . rawurlencode($display);
                  $active = ($display === $folder) ? 'active' : '';
                  // 加入可以勾選的 checkbox（data-name 傳回後端）
                  echo '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
                  echo '<input type="checkbox" class="chk-folder" data-name="'.htmlspecialchars($display,ENT_QUOTES,'UTF-8').'">';
                  echo '<a class="'. $active .'" href="'.htmlspecialchars($link,ENT_QUOTES,'UTF-8').'">📁 '.htmlspecialchars($display,ENT_QUOTES,'UTF-8').'</a>';
                  echo '</label>';
                }
              }
            ?>
          </div>
        </div>
      </div>

      <!-- 右側：檔案列表 -->
      <div>
        <div class="card">
          <?php if ($folder && is_dir($folderPath)): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <div>
                <h3 style="margin:0">檔案： <?php echo htmlspecialchars($folder); ?></h3>
                <div class="small" id="folderInfo"><?php echo htmlspecialchars(basename($folderPath)); ?> — <?php echo intval(count(array_filter(scandir($folderPath), function($f) use($folderPath){ return $f!=='.'&&$f!=='..' && is_file($folderPath.DIRECTORY_SEPARATOR.$f); }))); ?> 個檔案</div>
              </div>
              <div>
                <a class="btn ghost" href="index7.php">← 回資料夾列表</a>
              </div>
            </div>

            <div class="file-list" id="fileList">
              <?php
                $files = array_values(array_filter(scandir($folderPath), function($f) use($folderPath){
                  return $f !== '.' && $f !== '..' && is_file($folderPath . DIRECTORY_SEPARATOR . $f);
                }));
                if (empty($files)) {
                  echo '<div class="small">此資料夾沒有檔案</div>';
                } else {
                  foreach ($files as $f) {
                    $displayFile = toUtf8($f);
                    $pathUtf8 = ($folder !== '' ? $folder . '/' : '') . $displayFile;
                    $viewLink = 'view.php?file=' . rawurlencode($pathUtf8);
                    echo '<div class="file-row">';
                    echo '<div style="display:flex;align-items:center">';
                    echo '<input class="checkbox chk-file" type="checkbox" data-path="'.htmlspecialchars($pathUtf8,ENT_QUOTES,'UTF-8').'">';
                    echo '<a class="file-link" target="_blank" href="'.htmlspecialchars($viewLink,ENT_QUOTES,'UTF-8').'">📄 '.htmlspecialchars($displayFile,ENT_QUOTES,'UTF-8').'</a>';
                    echo '</div>';
                    echo '<div class="small">'.date("Y-m-d", filemtime($folderPath.DIRECTORY_SEPARATOR.$f)).' · '.round(filesize($folderPath.DIRECTORY_SEPARATOR.$f)/1024,1).' KB</div>';
                    echo '</div>';
                  }
                }
              ?>
            </div>
          <?php else: ?>
            <h3 style="margin:0 0 8px 0">選取資料夾以檢視檔案</h3>
            <div class="small">點左側資料夾即可展開並在新分頁打開 PDF。</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    // 回上一頁按鈕行為：若有瀏覽歷史則返回，否則導回 index.php（或指定頁面）
    document.getElementById('backBtn').addEventListener('click', function (e) {
  e.preventDefault();
  // 直接導向首頁（不使用 history.back）
  location.href = 'index.html';
});

    document.getElementById('refreshBtn').addEventListener('click', function(){ location.reload(); });
    
    document.getElementById('deleteSelectedBtn').addEventListener('click', async function(){
      const fileChecks = Array.from(document.querySelectorAll('.chk-file')).filter(c => c.checked).map(c => c.dataset.path);
      const total = fileChecks.length;
      if (total === 0) { alert('請先勾選要刪除的檔案'); return; }
      if (!confirm('確定要刪除選取的 ' + total + ' 個檔案？此動作不可復原。')) return;
      const failed = [];
      for (const p of fileChecks) {
        try {
          const resp = await fetch('delete_item1.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ type:'file', path: p })
          });
          const j = await resp.json();
          if (!j.ok) failed.push(p + (j.error ? ' ('+j.error+')' : ''));
        } catch (e) { failed.push(p + ' (' + e.message + ')'); }
      }
      if (failed.length > 0) {
        alert('部分項目刪除失敗：\n' + failed.join('\n'));
      } else {
        alert('選取項目已刪除');
        location.reload();
      }
    });

    // 刪除資料夾（使用 delete_item1.php, type: 'folder', path: 資料夾名稱）
    document.getElementById('deleteFoldersBtn').addEventListener('click', async function(){
      const folders = Array.from(document.querySelectorAll('.chk-folder')).filter(c => c.checked).map(c => c.dataset.name);
      if (folders.length === 0) { alert('請先勾選要刪除的資料夾'); return; }
      if (!confirm('確定要刪除選取的 ' + folders.length + ' 個資料夾？資料夾內檔案也會一併刪除，此動作不可復原。')) return;
      const failed = [];
      for (const name of folders) {
        try {
          const resp = await fetch('delete_item1.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ type:'folder', path: name })
          });
          const j = await resp.json();
          if (!j.ok) failed.push(name + (j.error ? ' ('+j.error+')' : ''));
        } catch (e) { failed.push(name + ' (' + e.message + ')'); }
      }
      if (failed.length > 0) {
        alert('部分資料夾刪除失敗：\n' + failed.join('\n'));
      } else {
        alert('選取資料夾已刪除');
        location.reload();
      }
    });

    // 簡單拖放上傳 UX（非必需）
    const uploadFile = document.getElementById('pdf_file');
    const uploadForm = document.getElementById('uploadForm');
    uploadForm.addEventListener('submit', function(){ /* 交給 upload.php 處理 */ });

    // 支援點擊整行勾選（for usability）
    document.querySelectorAll('.file-row').forEach(function(r){
      r.addEventListener('click', function(e){
        if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'a') return;
        const cb = r.querySelector('.chk-file');
        if (cb) { cb.checked = !cb.checked; }
      });
    });

    // 左側資料夾清單：點選 label 任一處也能切換 checkbox（避免點到 a 時誤觸）
    document.getElementById('folderList').addEventListener('click', function(e){
      const tgt = e.target;
      if (tgt.classList && tgt.classList.contains('chk-folder')) return; // 本身 checkbox
      // 如果點在 label（非 a），切換 checkbox
      const label = tgt.closest('label');
      if (label && !tgt.closest('a')) {
        const cb = label.querySelector('.chk-folder');
        if (cb) cb.checked = !cb.checked;
      }
    });
  </script>
</body>
</html>