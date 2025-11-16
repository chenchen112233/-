<?php
// Ensure HTTP responses are served as UTF-8
header('Content-Type: text/html; charset=utf-8');

$mysqli = new mysqli('127.0.0.1', 'root', '', 'notebook');
$notes = [];
if ($mysqli && !$mysqli->connect_errno) {
    // 使用 utf8mb4 讀取，避免中文亂碼
    $mysqli->set_charset('utf8mb4');
    // 只讀出尚未放入資料夾的筆記（folder 為 NULL 或空字串）
    $result = $mysqli->query("SELECT * FROM notes WHERE (folder IS NULL OR folder = '') ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
    }
    $mysqli->close();
    
}

// 嘗試把從資料庫讀出的欄位轉為 UTF-8（若非 UTF-8）以修正亂碼顯示
if (!function_exists('ensure_utf8_php')) {
    function ensure_utf8_php($s) {
        if ($s === null) return '';
        // 若已經是 UTF-8
        if (mb_check_encoding($s, 'UTF-8')) return $s;
        // 嘗試偵測常見編碼並轉換
        $enc = mb_detect_encoding($s, ['UTF-8','GBK','CP936','ISO-8859-1','Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') {
            return mb_convert_encoding($s, 'UTF-8', $enc);
        }
        // fallback: treat as ISO-8859-1/CP1252
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
}

foreach ($notes as &$n) {
    if (isset($n['title'])) $n['title'] = ensure_utf8_php($n['title']);
    if (isset($n['content'])) $n['content'] = ensure_utf8_php($n['content']);
}
unset($n);

// 讀取 notes 後（在 $notes 填好、$mysqli 關閉之後）加入：
// 取得 screenshots（最近 20 筆），排除已經放到 note/ 的項目
$screenshots = [];
$mysqli2 = @new mysqli('127.0.0.1', 'root', '', 'notebook');
if ($mysqli2 && !$mysqli2->connect_errno) {
    $mysqli2->set_charset('utf8mb4');
    $r = $mysqli2->query("SELECT id, pdf_file, page, path, created_at FROM screenshots WHERE path NOT LIKE 'note/%' ORDER BY id DESC LIMIT 20");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $screenshots[] = $row;
        }
        $r->free();
    }
    $mysqli2->close();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>閱讀筆記 - 怪獸閱讀</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <style>
        :where([class^="ri-"])::before {
            content: "\f3c2";
        }

        .note-content img {
            max-width: 100%;
            height: auto;
        }

        .note-content {
            min-height: 300px;
        }

        .color-tag {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
        }

        /* 圖片 wrapper 控制（新增） */
        .img-wrap {
            display: inline-block;
            position: relative;
            resize: both;
            overflow: auto;
            min-width: 80px;
            min-height: 60px;
            max-width: 100%;
            border: 1px dashed rgba(0,0,0,0.08);
            padding: 4px;
            box-sizing: border-box;
            background: #fff;
        }
        .img-wrap img { display:block; width:100%; height:auto; user-select:none; pointer-events:auto; }
        .img-resize-handle { position:absolute; width:12px; height:12px; right:6px; bottom:6px; background:rgba(0,0,0,0.28); border-radius:2px; cursor:se-resize; z-index:10; touch-action:none; }
        .img-controls {
            position: absolute;
            right: 6px;
            top: 6px;
            display:flex;
            gap:6px;
            z-index: 11;
        }
        .img-controls button{
            background: rgba(0,0,0,0.5);
            color: #fff;
            border: none;
            padding: 2px 6px;
            font-size:12px;
            border-radius:4px;
            cursor:pointer;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF8BA7',
                        secondary: '#93C6E7'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow-sm fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="#" class="text-2xl font-['Pacifico'] text-primary">
                        <img src="https://public.readdy.ai/ai/img_res/fa76a3a4d43763feac5ba72194c4a76f.jpg" alt="小怪獸"
                            class="h-10 w-10 inline-block align-middle" />
                    </a>
                    <div class="ml-10 flex items-center space-x-4">
                        <a href="http://192.168.0.55//index.html#"
                            class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm">首頁</a>
                        <a href="http://192.168.0.55//index4.html#"
                            class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm ">番茄鐘</a>
                        <a href="#" class="text-primary border-b-2 border-primary px-3 py-2 rounded-md text-sm">閱讀筆記</a>
                    </div>
                </div>
                <div class="flex items-center">
                     <div class="ml-4 flex items-center">
                         <a id="userBtn" href="index0.html"
                            class="inline-flex items-center gap-3 text-white bg-[#FAD1E3] px-4 py-1 rounded-full hover:shadow transition-shadow">
                             <span id="userAvatar"
                                   class="inline-flex items-center justify-center w-7 h-7 bg-[#FF8BA7] text-white rounded-full text-xs font-semibold">
                                   <i class="ri-user-line"></i>
                             </span>
                             <span id="userPoints" class="font-medium text-sm">123</span>
                         </a>
                     </div>
                 </div>
            </div>
        </div>
    </nav>
    <div class="pt-16 flex h-[calc(100vh-4rem)]">
        <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <button id="newNoteBtn"
                    class="w-full bg-primary text-white px-4 py-2 !rounded-button flex items-center justify-center hover:bg-primary/90">
                    <i class="ri-add-line mr-2"></i>新增筆記
                </button>
            </div>
            <div class="p-4 border-b border-gray-200">
                <div class="mb-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">資料夾管理</span>
                        <small class="text-xs text-gray-400">檔案會儲存在 note/（C:\xampp\htdocs\note）</small>
                    </div>
                    <div class="mt-3 flex space-x-2">
                        <input id="newFolderInput" type="text" placeholder="輸入新資料夾名稱" class="flex-1 px-3 py-2 border rounded text-sm" />
                        <button id="createFolderBtn" class="px-3 py-2 bg-primary text-white rounded text-sm">新增資料夾</button>
                    </div>
                    <div id="folderMsg" class="text-xs text-red-500 mt-2" style="display:none;"></div>
                </div>
                <div class="mt-3">
                    <div class="text-xs text-gray-500 mb-2">現有資料夾（note/）</div>
                    <div id="folderList" class="flex flex-col space-y-2">
                    <?php
                    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'note';
                    $folders = [];
                    if (is_dir($baseDir)) {
                        foreach (scandir($baseDir) as $d) {
                            if ($d === '.' || $d === '..') continue;
                            if (is_dir($baseDir . DIRECTORY_SEPARATOR . $d)) $folders[] = $d;
                        }
                    }
                    if (empty($folders)) {
                        echo '<div class="text-xs text-gray-400">尚無資料夾</div>';
                    } else {
                        foreach ($folders as $fd) {
                            $display = htmlspecialchars($fd, ENT_QUOTES, 'UTF-8');
                            echo '<div class="folder-item px-2 py-1 rounded hover:bg-gray-50 text-sm flex items-center justify-between" data-folder="'.htmlspecialchars($display, ENT_QUOTES, 'UTF-8').'">';
                            echo '<div class="flex items-center space-x-2"><span class="folder-name">'.$display.'</span></div>';
                            // 刪除按鈕（會由前端綁定，呼叫 delete_item.php）
                            echo '<div class="flex items-center space-x-2">';
                            echo '<button class="folder-delete-btn text-gray-400 hover:text-red-600" data-folder="'.htmlspecialchars($display, ENT_QUOTES, 'UTF-8').'" title="刪除資料夾" style="background:none;border:none;padding:6px;cursor:pointer">';
                            echo '<i class="ri-delete-bin-line" aria-hidden="true"></i>';
                            echo '</button>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>

                <!-- 新增：資料夾內容列表（點開資料夾後會載入這裡） -->
                <div id="folderContents" class="mt-4 p-2 border rounded bg-white" style="display:none;">
                    <div id="folderContentsTitle" class="text-sm font-medium mb-2">資料夾內容</div>
                    <!-- 改為單欄列出，每個檔案佔一整列 -->
                    <div id="folderFiles" class="flex flex-col space-y-2"></div>
                    <div id="folderEmpty" class="text-xs text-gray-400 mt-2" style="display:none;">此資料夾沒有檔案</div>
                </div>
            </div>
            <!-- 截圖縮圖區 -->
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">截圖（最近）</span>
                    <a href="screenshots_list.php" class="text-xs text-gray-400">更多</a>
                </div>
                <div class="flex flex-wrap">
                    <?php if (empty($screenshots)): ?>
                        <div class="text-xs text-gray-400">尚無截圖</div>
                    <?php else: ?>
                        <?php foreach ($screenshots as $s):
                            $img = htmlspecialchars($s['path'], ENT_QUOTES, 'UTF-8');
                            $title = htmlspecialchars(basename($s['path']).' p'.$s['page'], ENT_QUOTES, 'UTF-8');
                            $dataId = intval($s['id']);
                        ?>
                            <!-- 改為不開新分頁：改用 data-src，點擊後在主區顯示 -->
                            <a href="#" class="screenshot-thumb" data-id="<?php echo $dataId;?>" data-src="<?php echo $img;?>" title="<?php echo $title; ?>" onclick="return false;">
                                <img src="<?php echo $img;?>" class="w-20 h-20 object-cover mr-2 mb-2 rounded border" alt="<?php echo $title;?>">
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div class="space-y-2 p-2">
                    <div class="p-3 bg-primary/5 !rounded-lg cursor-pointer">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-900">深夜食堂：第一章讀後感</span>
                            <span class="color-tag bg-red-400"></span>
                        </div>
                        <p class="text-xs text-gray-500 line-clamp-2">在這個寧靜的深夜，當城市的喧囂逐漸褪去，一家小小的深夜食堂卻燈火通明...</p>
                        <div class="mt-2 text-xs text-gray-400">2025/03/06 更新</div>
                    </div>
                    <div class="p-3 hover:bg-gray-50 !rounded-lg cursor-pointer">
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 overflow-auto bg-white">
            <div class="max-w-4xl mx-auto p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input type="text" value="深夜食堂：第一章讀後感"
                            class="text-2xl font-bold text-gray-900 border-none focus:outline-none focus:ring-2 focus:ring-primary/20 !rounded-lg px-2">
                        <span class="color-tag ml-4" data-color="red"></span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button class="text-primary hover:underline" id="saveToDbBtn">
                            儲存到資料庫
                        </button>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="ri-share-line ri-lg"></i>
                        </button>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="ri-delete-bin-line ri-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-6 flex items-center text-sm text-gray-500">
                </div>

                <div class="border-t border-gray-200 pt-6">

                    <!-- 修正：截圖大預覽區（加入 previewText 元素） -->
                    <div id="screenshotPreview" class="mb-4 border rounded p-2" style="min-height:320px;background:#fff;display:none;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <div style="font-weight:600">截圖預覽</div>
                            <div>
                                <button id="insertToNoteBtn" style="margin-right:8px;">加入筆記</button>
                                <button id="downloadImgBtn" style="margin-right:8px;">下載</button>
                                <button id="closePreviewBtn">關閉</button>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                            <img id="previewImg" src="" alt="截圖預覽" style="max-width:100%; max-height:78vh; display:none;">
                            <!-- 新增：文字檔預覽容器 -->
                            <div id="previewText" style="display:none; width:100%; max-height:78vh; overflow:auto; white-space:pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Courier New', monospace; font-size:14px; color:#222; padding:10px; background:#fafafa; border-radius:6px;"></div>
                            <div id="previewEmpty" style="color:#777; display:none;">尚未選取截圖，點左側縮圖在此顯示</div>
                        </div>
                    </div>

                    <div class="flex space-x-2 mb-4">
                        <!-- toolbar ... -->
                    </div>

                    <div class="note-content prose max-w-none" contenteditable="true">
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- 使用者狀態檢查（與 session.php / logout.php 整合） ---
            (function () {
                const base = window.location.origin;
                const userBtn = document.getElementById('userBtn');
                const userText = document.getElementById('userText');
                async function refreshUser() {
                    try {
                        const res = await fetch(base + '/session.php', { credentials: 'include' });
                        const j = await res.json();
                        const userBtn = document.getElementById('userBtn');
                        const userAvatar = document.getElementById('userAvatar');
                        const userPoints = document.getElementById('userPoints');
                        if (j.ok && j.username) {
                            // 顯示 points（若有）否則顯示 username；avatar 顯示小人圖示
                            userPoints.textContent = (j.points !== undefined && j.points !== null) ? String(j.points) : j.username;
                            userAvatar.innerHTML = '<i class="ri-user-line"></i>';
                            userBtn.href = '#';
                            userBtn.onclick = async function (e) {
                                e.preventDefault();
                                if (!confirm('確定要登出嗎？')) return;
                                const out = await fetch(base + '/logout.php', { method: 'POST', credentials: 'include' });
                                const jo = await out.json();
                                if (jo.ok) location.reload();
                                else alert('登出失敗');
                            };
                        } else {
                            userAvatar.innerHTML = '<i class="ri-user-line"></i>';
                            userPoints.textContent = '登入';
                            userBtn.href = base + '/index0.html';
                            userBtn.onclick = null;
                        }
                    } catch (err) {
                        console.error('session fetch error', err);
                        const userBtn = document.getElementById('userBtn');
                        const userAvatar = document.getElementById('userAvatar');
                        const userPoints = document.getElementById('userPoints');
                        userAvatar.innerHTML = '<i class="ri-user-line"></i>';
                        userPoints.textContent = '登入';
                        if (userBtn) userBtn.href = base + '/index0.html';
                    }
                }
                refreshUser();
            })();
            // --- end 使用者狀態檢查 ---

            // 簡潔、語法安全的初始化腳本（避免大型 inline script 的語法錯誤）
            var notes = [];
            try {
                var _b64 = '<?php echo base64_encode(json_encode($notes, JSON_UNESCAPED_UNICODE)); ?>';
                var _jsonStr = '';
                if (typeof TextDecoder !== 'undefined') {
                    var bytes = Uint8Array.from(atob(_b64), function (c) { return c.charCodeAt(0); });
                    try {
                        _jsonStr = new TextDecoder('utf-8').decode(bytes);
                    } catch (e) {
                        // fallback
                        _jsonStr = decodeURIComponent(escape(atob(_b64)));
                    }
                } else {
                    // older browsers
                    _jsonStr = decodeURIComponent(escape(atob(_b64)));
                }
                notes = JSON.parse(_jsonStr) || [];
                // expose for debugging
                try { window.__notes = notes; } catch(e) {}
                console.log('index2: parsed notes length=', notes.length, 'first.title=', notes.length?notes[0].title:null);
            } catch (err) {
                console.error('index2: failed to parse notes JSON', err);
                notes = [];
            }

            var currentNoteId = notes.length > 0 ? String(notes[0].id) : null;
            var newNoteBtn = document.getElementById('newNoteBtn');
            if (!newNoteBtn) {
                // 建立簡單的 debug 按鈕
                var d = document.createElement('button');
                d.id = 'debugNewNoteBtn';
                d.textContent = 'DEBUG: 新增筆記';
                d.style.position = 'fixed'; d.style.bottom = '20px'; d.style.right = '20px';
                d.style.zIndex = '9999'; d.style.padding = '8px 12px'; d.style.background = '#FF8BA7';
                d.style.color = '#fff'; d.style.border = 'none'; d.style.borderRadius = '6px';
                document.body.appendChild(d);
                newNoteBtn = d;
            }

            var noteList = document.querySelector('.space-y-2.p-2');
            var mainTitle = document.querySelector('main input[type="text"].text-2xl');
            var mainContent = document.querySelector('.note-content');

            // 新增：在預覽時暫時關閉/還原筆記編輯區
            function setNoteDisabled(disabled) {
                if (!mainContent) mainContent = document.querySelector('.note-content');
                if (!mainContent) return;
                if (disabled) {
                    mainContent.dataset.prevEditable = mainContent.getAttribute('contenteditable') || 'true';
                    // 儲存原始內容（僅儲存一次）
                    if (!mainContent.dataset.origHtmlSaved) {
                        mainContent.dataset.origHtml = mainContent.innerHTML || '';
                        mainContent.dataset.origHtmlSaved = '1';
                    }
                    mainContent.setAttribute('contenteditable', 'false');
                    mainContent.style.pointerEvents = 'none';
                    mainContent.style.opacity = '0.0'; // 完全不可見
                    // 顯示替代提示（可改為空白）
                    mainContent.innerHTML = '<div class="text-xs text-gray-400 p-4">筆記內容已隱藏（預覽中）</div>';
                } else {
                    mainContent.setAttribute('contenteditable', mainContent.dataset.prevEditable || 'true');
                    mainContent.style.pointerEvents = '';
                    mainContent.style.opacity = '';
                    if (mainContent.dataset.origHtmlSaved) {
                        mainContent.innerHTML = mainContent.dataset.origHtml || '';
                        delete mainContent.dataset.origHtmlSaved;
                        delete mainContent.dataset.origHtml;
                    }
                }
            }

            // 新增：建立新筆記並關閉預覽的共用函式
            async function createNoteAndClose(title, htmlContent, callerBtn) {
                try {
                    if (callerBtn) callerBtn.disabled = true;
                    const payload = { title: title || '新筆記', content: htmlContent || '<p>這裡可以輸入筆記內容...</p>' };
                    const res = await fetch('note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const j = await res.json();
                    if (!j || !j.success) {
                        alert('建立筆記失敗：' + (j && j.error ? j.error : '未知錯誤'));
                        return;
                    }
                    // 若成功，將新筆記加入本地 notes 陣列並顯示
                    const newId = j.id ? String(j.id) : String(Date.now());
                    const newNote = { id: newId, title: payload.title, content: payload.content };
                    // 把新筆記放到陣列最前面
                    notes.unshift(newNote);
                    currentNoteId = newId;
                    renderNoteList();
                    showNote(newNote);

                    // 關閉預覽並還原編輯區
                    try {
                        const closeBtn = document.getElementById('closePreviewBtn');
                        // 直接呼叫 close handler 的行為：隱藏 preview 並還原
                        const preview = document.getElementById('screenshotPreview');
                        if (preview) preview.style.display = 'none';
                        setNoteDisabled(false);
                        // focus 並放 caret 在新筆記末端
                        const noteEl = document.querySelector('.note-content');
                        if (noteEl) {
                            noteEl.focus();
                            const range = document.createRange();
                            range.selectNodeContents(noteEl);
                            range.collapse(false);
                            const sel = window.getSelection();
                            sel.removeAllRanges();
                            sel.addRange(range);
                        }
                    } catch (e) { console.error('close after create error', e); }
                } catch (err) {
                    console.error('createNoteAndClose error', err);
                    alert('建立筆記發生錯誤，請看 Console');
                } finally {
                    if (callerBtn) callerBtn.disabled = false;
                }
            }

            function renderNoteList() {
                if (!noteList) return;
                noteList.innerHTML = '';
                for (var i = 0; i < notes.length; i++) {
                    var note = notes[i] || {};
                    var div = document.createElement('div');
                    // 新：加入 note-item class 並設定 draggable
                    div.className = 'note-item p-3 bg-primary/10 !rounded-lg cursor-pointer' + (String(note.id) === String(currentNoteId) ? ' ring-2 ring-primary' : '');
                    div.setAttribute('data-id', note.id);
                    div.setAttribute('draggable', 'true');

                    // 標題與 snippet
                    var snippet = (note.content || '').replace(/<[^>]+>/g, '').slice(0, 30) || '這裡可以輸入筆記內容...';
                    var title = note.title || snippet;
                    div.innerHTML = '<div class="flex items-center justify-between mb-2"><span class="text-sm font-medium text-gray-900">' + title + '</span></div>';

                    // 新：在建立時綁定 dragstart（確保每個元素都有正確 payload）
                    div.addEventListener('dragstart', function (e) {
                        var id = this.getAttribute('data-id');
                        if (!id) return;
                        try {
                            e.dataTransfer.setData('application/json', JSON.stringify({ type: 'note', id: id }));
                            e.dataTransfer.effectAllowed = 'move';
                        } catch (err) {
                            console.error('dragstart setData error', err);
                        }
                    });

                    // 點擊顯示筆記
                    div.addEventListener('click', function () {
                        var id = this.getAttribute('data-id');
                        var noteObj = notes.find(function (n) { return String(n.id) === String(id); });
                        if (noteObj) showNote(noteObj);
                    });

                    noteList.appendChild(div);
                }
            }

            function showNote(note) {
                if (!mainTitle || !mainContent) return;
                currentNoteId = note && note.id ? String(note.id) : null;
                mainTitle.value = note.title || '';
                mainContent.innerHTML = note.content || '<p>這裡可以輸入筆記內容...</p>';
            }

            // 新增筆記（簡單版：建立後重新載入以保證同步）
            if (newNoteBtn && !window.__newNoteBound) {
                newNoteBtn.addEventListener('click', function () {
                    console.log('index2: simple newNote clicked');
                    fetch('note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ title: '新筆記', content: '<p>這裡可以輸入筆記內容...</p>' })
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        console.log('index2: simple note.php response', data);
                        if (data && data.success) location.reload();
                        else alert('新增失敗');
                    })
                    .catch(function (err) { console.error('index2: simple fetch error', err); alert('新增發生錯誤，請看 Console'); });
                });
                window.__newNoteBound = true;
            }

            // 左側清單點擊事件代理
            if (noteList) {
                noteList.addEventListener('click', function (e) {
                    var item = e.target.closest('div[data-id]');
                    if (!item) return;
                    var id = item.getAttribute('data-id');
                    var note = notes.find(function (n) { return String(n.id) === String(id); });
                    if (note) showNote(note);
                });
            }

            // 初始渲染與預覽首筆
            renderNoteList();
            if (notes.length > 0) showNote(notes[0]);

            // 編輯內容變更同步到 notes 陣列
            if (mainTitle) {
                mainTitle.addEventListener('input', function () {
                    if (!currentNoteId) return;
                    var note = notes.find(function (n) { return String(n.id) === String(currentNoteId); });
                    if (!note) return;
                    note.title = mainTitle.value;
                    renderNoteList();
                });
            }
            if (mainContent) {
                mainContent.addEventListener('input', function () {
                    if (!currentNoteId) return;
                    var note = notes.find(function (n) { return String(n.id) === String(currentNoteId); });
                    if (!note) return;
                    note.content = mainContent.innerHTML;
                    renderNoteList();
                });
            }

            // 儲存到資料庫（AJAX，不重新載入）
            var saveToDbBtn = document.getElementById('saveToDbBtn');
            if (saveToDbBtn) {
                saveToDbBtn.addEventListener('click', function () {
                    if (!currentNoteId) return alert('沒有選取筆記');
                    var note = notes.find(function (n) { return String(n.id) === String(currentNoteId); });
                    if (!note) return alert('找不到筆記');
                    // send note (note.php handles insert or update)
                    fetch('note.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(note) })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            console.log('index2: save response', data);
                            if (data && data.success) {
                                // if server returned a new id (insert), update it locally
                                if (data.id) {
                                    note.id = String(data.id);
                                    currentNoteId = String(data.id);
                                }
                                renderNoteList();
                                alert('儲存成功');
                            } else {
                                alert('儲存失敗：' + (data && data.error ? data.error : '未知錯誤'));
                            }
                        }).catch(function (err) { console.error('index2: save fetch error', err); alert('儲存發生錯誤，請看 Console'); });
                });
            }

            // 刪除目前筆記（綁定右上垃圾桶圖示）
            (function attachDeleteHandler() {
                var delIcon = document.querySelector('main .ri-delete-bin-line');
                var delBtn = delIcon ? delIcon.parentElement : null;
                if (!delBtn) return;
                delBtn.addEventListener('click', function () {
                    if (!currentNoteId) return;
                    if (!confirm('確定要刪除這則筆記嗎？')) return;
                    fetch('delete_note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: currentNoteId })
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            // 從 notes 陣列移除並更新 UI
                            notes = notes.filter(function (n) { return String(n.id) !== String(currentNoteId); });
                            // 選擇下一筆或清空主畫面
                            if (notes.length > 0) {
                                currentNoteId = String(notes[0].id);
                                showNote(notes[0]);
                            } else {
                                currentNoteId = null;
                                if (mainTitle) mainTitle.value = '';
                                if (mainContent) mainContent.innerHTML = '<p>這裡可以輸入筆記內容...</p>';
                            }
                            renderNoteList();
                        } else {
                            alert('刪除失敗：' + (data && data.error ? data.error : 'unknown'));
                        }
                    })
                    .catch(function (err) { console.error('index2: delete fetch error', err); alert('刪除發生錯誤，請看 Console'); });
                });
            })();

            // 點擊側欄縮圖時顯示到主區（使用事件代理）；圖片與文字分開
            document.querySelector('.p-4.border-b.border-gray-200 .flex.flex-wrap')?.addEventListener('click', function(e){
                const a = e.target.closest('.screenshot-thumb');
                if (!a) return;
                const src = a.dataset.src;
                if (!src) return;

                const preview = document.getElementById('screenshotPreview');
                const img = document.getElementById('previewImg');
                const empty = document.getElementById('previewEmpty');

                // 顯示預覽容器與 loading 提示
                preview.style.display = 'block';
                // 隱藏並停用筆記編輯區（完全看不到內容）
                setNoteDisabled(true);
                 img.style.display = 'none';
                 empty.style.display = 'block';
                 empty.textContent = '載入中...';

                img.onload = function(){
                    empty.style.display = 'none';
                    img.style.display = '';
                    img.dataset.currentSrc = src;
                    // 設定下載按鈕連結
                    document.getElementById('downloadImgBtn').onclick = function(){
                        const link = document.createElement('a');
                        link.href = src;
                        link.download = src.split('/').pop();
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                    };
                    // 插入到筆記按鈕（將 <img> 插入到 note-content）
                    document.getElementById('insertToNoteBtn').onclick = function(){
                        const btn = this;
                        const imgHtml = '<img src="'+src+'" alt="插入圖片" style="max-width:100%;">';
                        createNoteAndClose('新筆記', imgHtml, btn);
                    };
                };
                img.onerror = function(){
                    img.style.display = 'none';
                    empty.style.display = 'block';
                    empty.textContent = '載入圖片失敗';
                };
                // 觸發載入
                img.src = src;
            });

            // 關閉預覽：強制還原編輯區並把 caret 放到末端
            document.getElementById('closePreviewBtn')?.addEventListener('click', function(){
                const preview = document.getElementById('screenshotPreview');
                const img = document.getElementById('previewImg');
                preview.style.display = 'none';
                if (img) { img.src = ''; img.style.display = 'none'; }

                try {
                    const note = document.querySelector('.note-content');
                    if (!note) return;

                    // 強制還原可編輯狀態與樣式
                    note.setAttribute('contenteditable', 'true');
                    note.style.pointerEvents = '';
                    note.style.opacity = '';

                    // 若先前有儲存的原始 HTML，還原它
                    if (note.dataset.origHtmlSaved) {
                        note.innerHTML = note.dataset.origHtml || '';
                        delete note.dataset.origHtmlSaved;
                        delete note.dataset.origHtml;
                    }

                    // focus 並把 caret 放到內容末端
                    note.focus();
                    const range = document.createRange();
                    range.selectNodeContents(note);
                    range.collapse(false);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                    note.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (err) {
                    console.error('closePreview restore error', err);
                    // fallback：直接解鎖編輯區
                    try {
                        const note = document.querySelector('.note-content');
                        if (note) {
                            note.setAttribute('contenteditable', 'true');
                            note.style.pointerEvents = '';
                            note.style.opacity = '';
                        }
                    } catch(e){}
                }
            });

            // helper：顯示 folder contents
  async function loadFolder(folder) {
    const contents = document.getElementById('folderContents');
    const filesEl = document.getElementById('folderFiles');
    const titleEl = document.getElementById('folderContentsTitle');
    const emptyEl = document.getElementById('folderEmpty');

    contents.style.display = 'block';
    titleEl.textContent = '載入中...';
    filesEl.innerHTML = '';
    emptyEl.style.display = 'none';

    function isImageByName(name) {
      return /\.(jpe?g|png|gif|webp|bmp|svg)(\?.*)?$/i.test(name);
    }

    try {
      const res = await fetch('list_folder.php?folder=' + encodeURIComponent(folder));
      const j = await res.json();
      if (!j.ok) { titleEl.textContent = '讀取失敗'; return; }
      titleEl.textContent = '資料夾：' + folder;
      const items = j.files || [];
      if (items.length === 0) {
        emptyEl.style.display = 'block';
        filesEl.innerHTML = '';
        return;
      }

      filesEl.innerHTML = '';
      items.forEach(function(it) {
        // row container
        const row = document.createElement('div');
        row.className = 'folder-file p-2 border rounded flex items-center justify-between text-sm';
        row.style.gap = '8px';
        row.dataset.name = it.name;
        // set src and isImage for preview handler
        const rel = it.rel || (it.path || ('note/' + folder + '/' + it.name));
        row.dataset.src = rel;
        row.dataset.isImage = isImageByName(it.name) ? '1' : '0';

        // left: filename (顯示檔名，點擊顯示預覽)
        const left = document.createElement('div');
        left.style.flex = '1';
        left.style.overflow = 'hidden';
        const link = document.createElement('a');
        link.href = rel;
        link.target = '_blank';
        link.textContent = it.name; // 只顯示檔名
        link.className = 'text-blue-600 hover:underline truncate';
        link.style.display = 'inline-block';
        link.style.maxWidth = '100%';
        // prevent default navigation; 使用預覽處理
        link.addEventListener('click', function(e){
          e.preventDefault();
          row.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        });
        left.appendChild(link);
        row.appendChild(left);

        // right: 刪除按鈕（僅保留刪除）
        const right = document.createElement('div');
        right.className = 'flex items-center space-x-2';

        const delBtn = document.createElement('button');
        delBtn.className = 'file-delete-btn text-xs text-red-600 px-2 py-1 border rounded';
        delBtn.textContent = '刪除';
        delBtn.title = '刪除此檔案';
        delBtn.addEventListener('click', async function(e){
          e.preventDefault();
          e.stopPropagation();
          if (!confirm('確定要刪除檔案：' + it.name + '？此動作不可復原。')) return;
          delBtn.disabled = true;
          try {
            const targetPath = folder + '/' + it.name;
            const resp = await fetch('delete_item.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ type: 'file', path: targetPath })
            });
            const jj = await resp.json();
            if (jj && jj.ok) {
              row.style.transition = 'opacity 160ms ease, height 160ms ease';
              row.style.opacity = '0';
              const h = row.getBoundingClientRect().height + 'px';
              row.style.height = h;
              void row.offsetHeight;
              row.style.height = '0px';
              setTimeout(() => {
                row.remove();
                if (!filesEl.querySelector('.folder-file')) {
                  emptyEl.style.display = 'block';
                }
              }, 180);
            } else {
              alert('刪除失敗：' + (jj && (jj.message || jj.error) ? (jj.message || jj.error) : '未知錯誤'));
            }
          } catch (err) {
            console.error('delete file error', err);
            alert('刪除發生錯誤：' + (err.message || err));
          } finally {
            delBtn.disabled = false;
          }
        });
        right.appendChild(delBtn);

        row.appendChild(right);
        filesEl.appendChild(row);
      });
    } catch (e) {
      titleEl.textContent = '讀取錯誤';
      console.error(e);
    }
  }

  // folder click：載入內容並標示 active（點同一個可收回）
  document.querySelectorAll('.folder-item').forEach(function(f){
    f.addEventListener('click', function(){
      const contents = document.getElementById('folderContents');
      const folder = f.dataset.folder;
      // 判斷目前是否已經是 active
      const isActive = f.classList.contains('ring-2') && f.classList.contains('ring-primary');

      // 移除所有 active 樣式
      document.querySelectorAll('.folder-item').forEach(function(x){ x.classList.remove('ring-2','ring-primary'); });

      if (isActive) {
        // 已經是開啟狀態 -> 收回
        if (contents) contents.style.display = 'none';
      } else {
        // 不是 -> 標示為 active 並載入內容
        f.classList.add('ring-2','ring-primary');
        if (folder) loadFolder(folder);
      }
    });
  });

  // folder file click：顯示到預覽區（修正 TXT 檔案預覽）
  document.getElementById('folderFiles')?.addEventListener('click', function(e){
    const fileEl = e.target.closest('.folder-file');
    if (!fileEl) return;
    const src = fileEl.dataset.src;
    const isImg = fileEl.dataset.isImage === '1';
    const preview = document.getElementById('screenshotPreview');
    const img = document.getElementById('previewImg');
    const previewTextEl = document.getElementById('previewText');
    const empty = document.getElementById('previewEmpty');

    // 顯示預覽容器
    preview.style.display = 'block';
    // 關閉筆記編輯區，避免在預覽時誤編輯
    setNoteDisabled(true);

    // 先隱藏所有預覽元素
    img.style.display = 'none';
    if (previewTextEl) previewTextEl.style.display = 'none';
    empty.style.display = 'none';

    if (isImg) {
        // 圖片處理邏輯
        empty.style.display = 'block';
        empty.textContent = '載入中...';
        
        img.onload = function(){
            empty.style.display = 'none';
            img.style.display = '';
        };
        img.onerror = function(){
            img.style.display = 'none';
            empty.style.display = 'block';
            empty.textContent = '載入失敗';
        };
        img.src = src;
        
        // 設定下載與加入筆記按鈕
        document.getElementById('downloadImgBtn').onclick = function(){
            const link = document.createElement('a');
            link.href = src;
            link.download = src.split('/').pop();
            document.body.appendChild(link);
            link.click();
            link.remove();
        };
        
        document.getElementById('insertToNoteBtn').onclick = function(){
            const btn = this;
            const imgHtml = '<img src="'+src+'" alt="插入圖片" style="max-width:100%;">';
            createNoteAndClose('新筆記', imgHtml, btn);
        };
    } else {
        // 非圖片檔案：TXT 等文字檔預覽
        const filename = src.split('/').pop() || '';
        const ext = (filename.split('.').pop() || '').toLowerCase().split('?')[0];

        // 支援的純文字副檔名
        const textExts = ['txt','md','csv','log','json','xml','html','htm','php','js','css'];
        
        if (textExts.indexOf(ext) !== -1 && previewTextEl) {
            // 顯示文字預覽
            previewTextEl.style.display = 'block';
            previewTextEl.textContent = '載入中...';
            
            // 讀取文字檔案內容
            fetch(src)
                .then(function(res){
                    if (!res.ok) throw new Error('讀取失敗: ' + res.status);
                    return res.text();
                })
                .then(function(txt){
                    previewTextEl.textContent = txt || '(空檔案)';
                })
                .catch(function(err){
                    console.error('載入文字檔案錯誤', err);
                    previewTextEl.textContent = '載入失敗：' + (err.message || '未知錯誤');
                });

            // 設定插入到筆記功能
            document.getElementById('insertToNoteBtn').onclick = function(){
                const note = document.querySelector('.note-content');
                if (!note) return alert('找不到筆記編輯區');
                
                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.background = '#f7f7f7';
                pre.style.padding = '12px';
                pre.style.borderRadius = '6px';
                pre.style.border = '1px solid #e5e5e5';
                pre.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, "Courier New", monospace';
                pre.style.fontSize = '14px';
                pre.style.overflow = 'auto';
                pre.textContent = previewTextEl.textContent || '';
                
                // 插入到編輯區
                const selection = window.getSelection();
                if (selection && selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    if (!note.contains(range.startContainer)) {
                        note.appendChild(pre);
                        note.appendChild(document.createElement('p'));
                    } else {
                        range.insertNode(pre);
                        range.setStartAfter(pre);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                } else {
                    note.appendChild(pre);
                    note.appendChild(document.createElement('p'));
                }
                alert('文字內容已加入筆記。');
            };
        } else {
            // 其他非文字檔案
            empty.style.display = 'block';
            empty.textContent = '檔案類型：' + filename + '（可下載）';
            document.getElementById('insertToNoteBtn').onclick = function(){
                alert('此檔案類型無法直接插入筆記。');
            };
        }

        // 下載按鈕（適用所有非圖片檔案）
        document.getElementById('downloadImgBtn').onclick = function(){
            const link = document.createElement('a');
            link.href = src;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
        };
    }
  });
        });
    </script>
    <!-- Fallback script: ensures "新增筆記" button works even if the big script above has a parse error -->
    <script>
        (function () {
            try {
                if (window.__newNoteBound) return; // 已綁定，跳過 fallback
                const btn = document.getElementById('newNoteBtn') || document.getElementById('debugNewNoteBtn');
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    console.log('index2: fallback newNote clicked');
                    fetch('note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ title: '新筆記', content: '<p>這裡可以輸入筆記內容...</p>' })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log('index2: fallback note.php response', data);
                        if (data && data.success) {
                            location.reload();
                        } else {
                            alert('新增失敗 (fallback)');
                        }
                    })
                    .catch(err => {
                        console.error('index2: fallback fetch error', err);
                        alert('新增發生錯誤 (fallback)，請看 Console');
                    });
                });
                window.__newNoteBound = true;
            } catch (e) {
                console.error('index2: fallback script error', e);
            }
        })();
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function(){
  const createBtn = document.getElementById('createFolderBtn');
  const input = document.getElementById('newFolderInput');
  const msg = document.getElementById('folderMsg');
  if (createBtn && input) {
    createBtn.addEventListener('click', async function(){
      const name = input.value.trim();
      msg.style.display = 'none';
      if (!name) { msg.textContent = '請輸入資料夾名稱'; msg.style.display='block'; return; }
      createBtn.disabled = true;
      try {
        const res = await fetch('create_folder.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ folder: name })
        });
        const j = await res.json();
        if (j.ok) {
          location.reload();
        } else {
          msg.textContent = j.error || '建立失敗';
          msg.style.display = 'block';
        }
      } catch (e) {
        msg.textContent = '連線失敗，無法建立資料夾';
        msg.style.display = 'block';
      } finally {
        createBtn.disabled = false;
      }
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // make notes draggable
  function enableNotesDrag() {
    const noteList = document.querySelector('.space-y-2.p-2');
    if (!noteList) return;
    noteList.querySelectorAll('div[data-id]').forEach(function(div){
      div.setAttribute('draggable','true');
      div.addEventListener('dragstart', function(e){
        e.dataTransfer.setData('application/json', JSON.stringify({type:'note', id: div.getAttribute('data-id')}));
        e.dataTransfer.effectAllowed = 'move';
      });
    });
  }

  // make screenshot thumbs draggable
  function enableThumbsDrag() {
    document.querySelectorAll('.screenshot-thumb').forEach(function(a){
      a.setAttribute('draggable','true');
      a.addEventListener('dragstart', function(e){
        // we also store data-id if available via dataset
        const id = a.dataset.id || null;
        const src = a.dataset.src || (a.querySelector('img') ? a.querySelector('img').src : '');
        e.dataTransfer.setData('application/json', JSON.stringify({type:'screenshot', id: id, src: src}));
        e.dataTransfer.effectAllowed = 'move';
      });
    });
  }

  // enable folder drop targets
  function enableFolderDrops() {
    document.querySelectorAll('.folder-item').forEach(function(f){
      f.addEventListener('dragover', function(e){ e.preventDefault(); e.dataTransfer.dropEffect='move'; f.classList.add('drop-hover'); });
      f.addEventListener('dragleave', function(){ f.classList.remove('drop-hover'); });
      f.addEventListener('drop', async function(e){
        e.preventDefault(); f.classList.remove('drop-hover');
        let raw = e.dataTransfer.getData('application/json');
        if (!raw) return alert('無法取得拖曳內容');
        let obj;
        try { obj = JSON.parse(raw); } catch (err) { return alert('拖曳資料格式錯誤'); }
        const targetFolder = f.dataset.folder;
        if (!targetFolder) return alert('目標資料夾錯誤');

        if (obj.type === 'note' && obj.id) {
          // move note (update DB folder 欄位)
          try {
            const res = await fetch('move_note.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: obj.id, folder: targetFolder }) });
            const j = await res.json();
            if (j.ok) {
              // 移除對應 note 元素（若你有用 data-id 的話）
              const noteEl = document.querySelector('div[data-id="'+obj.id+'"]');
              if (noteEl) noteEl.remove();
            } else {
              alert('移動筆記失敗: ' + (j.error||'unknown'));
            }
          } catch (e) { alert('連線錯誤，無法移動筆記'); }
        } else if (obj.type === 'screenshot' && (obj.id || obj.src)) {
          try {
            const res = await fetch('move_screenshot.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: obj.id, src: obj.src, folder: targetFolder }) });
            const j = await res.json();
            if (j.ok) {
              // 移除對應縮圖節點（優先用 id，否則用 src 比對）
              let thumb = null;
              if (obj.id) thumb = document.querySelector('.screenshot-thumb[data-id="'+obj.id+'"]');
              if (!thumb) thumb = Array.from(document.querySelectorAll('.screenshot-thumb')).find(a => a.dataset.src === obj.src);
              if (thumb) thumb.remove();
            } else {
              alert('移動圖片失敗: ' + (j.error||'unknown'));
            }
          } catch (e) { alert('連線錯誤，無法移動圖片'); }
        } else {
          alert('不支援的拖曳項目');
        }
      });
    });
  }

  // init
  enableNotesDrag();
  enableThumbsDrag();
  enableFolderDrops();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // 事件代理：設定 dragstart 資料
  document.body.addEventListener('dragstart', function(e){
    const note = e.target.closest('.note-item');
    if (!note) return;
    const id = note.dataset.id;
    if (!id) return;
    const payload = { type: 'note', id: id };
    try {
      e.dataTransfer.setData('application/json', JSON.stringify(payload));
      e.dataTransfer.effectAllowed = 'move';
    } catch (err) {
      console.error('dragstart setData error', err);
    }
  });

  // 若檔案中後面還有另一個 folder-item drop handler，請用以下修正替換那一段：
  document.querySelectorAll('.folder-item').forEach(function(f){
    f.addEventListener('dragover', function(e){ e.preventDefault(); e.dataTransfer.dropEffect = 'move'; f.classList.add('drop-hover'); });
    f.addEventListener('dragleave', function(){ f.classList.remove('drop-hover'); });
    f.addEventListener('drop', async function(e){
      e.preventDefault(); f.classList.remove('drop-hover');
      let raw = e.dataTransfer.getData('application/json');
      if (!raw) return alert('無拖曳資料');
      let obj;
      try { obj = JSON.parse(raw); } catch (err) { return alert('拖曳資料格式錯誤'); }
      if (obj.type === 'note' && obj.id) {
        try {
          const res = await fetch('move_note.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: obj.id, folder: f.dataset.folder }) });
          const j = await res.json();
          if (j.ok) {
            const el = document.querySelector('.note-item[data-id="'+obj.id+'"]');
            if (el) el.remove();
          } else {
            alert('移動筆記失敗：' + (j.error || 'unknown'));
          }
        } catch (err) {
          alert('連線錯誤，無法移動筆記');
        }
      }
      // 其他 type 處理...
    });
  });
});
</script>
<script>
(function(){
  if (window.__folderDeleteBound) return;
  window.__folderDeleteBound = true;

  function findFolderElement(folder) {
    // 優先用 CSS.escape 防止選取問題，若不存在則以 dataset 比對
    if (window.CSS && CSS.escape) {
      try {
        return document.querySelector('.folder-item[data-folder="' + CSS.escape(folder) + '"]');
      } catch (e) { /* fallback below */ }
    }
    const els = document.querySelectorAll('.folder-item');
    for (let i=0;i<els.length;i++){
      if (els[i].dataset && els[i].dataset.folder === folder) return els[i];
    }
    return null;
  }

  const list = document.getElementById('folderList');
  if (!list) return;

  list.addEventListener('click', async function(e){
    const btn = e.target.closest('.folder-delete-btn');
    if (!btn) return;
    e.preventDefault();

    const folder = btn.dataset.folder;
    if (!folder) { alert('找不到資料夾名稱'); return; }
    if (!confirm('確定要刪除資料夾「' + folder + '」及其所有內容？此動作不可復原。')) return;

    btn.disabled = true;
    try {
      const resp = await fetch('delete_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'folder', path: folder })
      });
      const j = await resp.json();
      if (j && j.ok) {
        // 從 UI 移除資料夾項目
        const el = findFolderElement(folder);
        if (el) {
          // 可加淡出動畫（非必要）
          el.style.transition = 'opacity 200ms ease, height 200ms ease';
          el.style.opacity = '0';
          // 讓高度平滑收縮（取得當前高度才能平滑）
          const h = el.getBoundingClientRect().height + 'px';
          el.style.height = h;
          // force reflow
          void el.offsetHeight;
          el.style.height = '0px';
          setTimeout(() => el.remove(), 220);
        }
        else {
          // 若找不到對應 DOM，直接重整或通知使用者
          // location.reload();
        }
      } else {
        alert('刪除失敗：' + (j && (j.message || j.error) ? (j.message || j.error) : '未知錯誤'));
      }
    } catch (err) {
      console.error('delete folder error', err);
      alert('刪除發生錯誤：' + (err.message || err));
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
</body>

</html>

<?php /* notes already loaded at top */ ?>