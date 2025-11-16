<?php
$file = $_GET['file'] ?? '';
if (!$file) { echo "沒有指定檔案"; exit; }
$pdf_path = "uploads/" . $file;
$annotation_file = "annotations/" . $file . ".json";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>PDF 標記 - <?php echo htmlspecialchars($file); ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
// 設定 PDF.js worker 路徑（消除警告並改善穩定性）
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
</script>
<style>
  body { background:#f5f5f5; font-family:sans-serif; text-align:center; }
  #pdf-container { position:relative; display:inline-block; }
  canvas { border:1px solid #ccc; background:white; }
  #toolbar {
    margin:10px; display:flex; flex-wrap:wrap;
    justify-content:center; gap:8px; align-items:center;
  }
  button { padding:6px 10px; font-size:14px; }
  input[type=color], input[type=range] { vertical-align:middle; }
</style>
</head>
<body>
  <h2>PDF 標記 - <?php echo htmlspecialchars($file); ?></h2>
  <div id="toolbar">
    <button id="prevBtn">⬅️ 上一頁</button>
    <span id="pageInfo"></span>
    <button id="nextBtn">➡️ 下一頁</button>
    |
    <button id="penBtn">✏️ 畫筆</button>
    <button id="eraseBtn">🧽 橡皮擦</button>
    |
    顏色：<input type="color" id="colorPicker" value="#ff0000">
    粗細：<input type="range" id="sizePicker" min="1" max="20" value="2">
    |
    <button id="saveBtn">💾 保存標記</button>
    <button id="screenshotBtn">📷 截圖並儲存</button>
    <button id="clearBtn">🧹 清除本頁</button>
    |
    <button id="gptDetectBtn">🤖 GPT 偵測標記</button>
    <button id="gptChatBtn">💬 與 GPT 對話</button>
  </div>

  <div id="pdf-container">
    <canvas id="pdf-canvas"></canvas>
  </div>

<script>
const url = "<?php echo $pdf_path; ?>";
const annFile = "annotations/<?php echo $file; ?>.json";
const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');

// 將變數提升為 script 全域可用（避免 block scope 導致事件處理器找不到變數）
let annotations = {};          // 載入後會被 fetch 覆寫
let drawing = false;           // 是否在繪製中
let currentLine = [];          // 當前筆跡座標陣列
let currentTool = 'pen';       // 'pen' 或 'eraser'
let penColor = '#ff0000';
let penSize = 2;

const colorEl = document.getElementById('colorPicker');
const sizeEl  = document.getElementById('sizePicker');
if (colorEl) penColor = colorEl.value;
if (sizeEl)  penSize  = parseInt(sizeEl.value || '2', 10) || 2;

// 同步 UI 控制值與監聽
if (colorEl) {
  colorEl.value = penColor;
  colorEl.addEventListener('input', e => penColor = e.target.value);
}
if (sizeEl) {
  sizeEl.value = penSize;
  sizeEl.addEventListener('input', e => penSize = parseInt(e.target.value||'2',10) || 2);
}

// 暴露（若其他程式段需要檢視）
window.__pdfAnnotations = annotations;
window.__pdfDrawingState = () => ({ drawing, currentTool });
    // 先嘗試從 save_last_page.php 取得最後觀看頁數，若失敗則預設為 1
    let pdfDoc, pageNum = 1, viewport;
    (async function initPdf() {
      try {
        const resp = await fetch('save_last_page.php?file=' + encodeURIComponent("<?php echo $file; ?>"), { cache: 'no-store' });
        if (resp && resp.ok) {
          const j = await resp.json();
          if (j && j.ok && j.page) {
            pageNum = parseInt(j.page, 10) || 1;
            console.log('restore last page ->', pageNum);
          }
        }
      } catch (e) {
        console.warn('restore last page failed', e);
        // fallback 不中斷：pageNum 保持 1
      }

      // 載入 PDF（在嘗試還原後再載入）
      pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        renderPage();
      }).catch(err => {
        console.error('pdf load error', err);
        alert('載入 PDF 失敗，請檢查檔案路徑或伺服器設定');
      });
    })();

    function renderPage() {
      pdfDoc.getPage(pageNum).then(page => {
        viewport = page.getViewport({ scale: 1.5 });
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        const renderContext = { canvasContext: ctx, viewport: viewport };
        // 清畫布再 render
        ctx.clearRect(0,0,canvas.width,canvas.height);
        page.render(renderContext).promise.then(() => {
          drawAnnotations();
          updatePageInfo();
        });
      });
    }

    function updatePageInfo() {
      document.getElementById('pageInfo').textContent = `第 ${pageNum} 頁 / 共 ${pdfDoc.numPages} 頁`;
      // 每次切頁更新時記錄（非同步、使用 navigator.sendBeacon 優先）
      saveLastPage();
    }

    // 新增：儲存最後觀看頁面的函式（sendBeacon 為主，fetch keepalive 備援）
    function saveLastPage() {
      try {
        const url = 'save_last_page.php';
        // use FormData for sendBeacon compatibility
        const fd = new FormData();
        fd.append('file', "<?php echo htmlspecialchars($file, ENT_QUOTES); ?>");
        fd.append('page', String(pageNum || 1));
        if (navigator.sendBeacon) {
          navigator.sendBeacon(url, fd);
          return;
        }
        // fallback: keepalive fetch
        fetch(url, { method: 'POST', body: fd, keepalive: true }).catch(()=>{});
      } catch (e) {
        console.error('saveLastPage error', e);
      }
    }

    // 在使用者關閉或離開時也記錄一次（pagehide 與 beforeunload）
    window.addEventListener('pagehide', function(){ saveLastPage(); });
    window.addEventListener('beforeunload', function(){ saveLastPage(); });


    function drawAnnotations() {
      // 直接在顯示 canvas 上畫已儲存的標記（不做複雜混合）
      ctx.save();
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      (annotations[pageNum] || []).forEach(obj => {
        ctx.beginPath();
        ctx.strokeStyle = obj.color || '#000';
        ctx.lineWidth = obj.size || 2;
        obj.line.forEach((p, i) => {
          if (i === 0) ctx.moveTo(p.x, p.y);
          else ctx.lineTo(p.x, p.y);
        });
        ctx.stroke();
        ctx.closePath();
      });
      ctx.restore();
    }

    // 滑鼠事件（簡化：只有畫筆與橡皮擦）
    canvas.addEventListener('mousedown', e => {
      drawing = true;
      currentLine = [{x:e.offsetX, y:e.offsetY}];
      ctx.beginPath();
      ctx.moveTo(e.offsetX, e.offsetY);
    });

    canvas.addEventListener('mousemove', e => {
      if (!drawing) return;
      if (currentTool === "eraser") {
        eraseAt(e.offsetX, e.offsetY);
        return;
      }
      // 畫筆
      ctx.globalCompositeOperation = 'source-over';
      ctx.globalAlpha = 1.0;
      ctx.lineWidth = penSize;
      ctx.strokeStyle = penColor;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.lineTo(e.offsetX, e.offsetY);
      ctx.stroke();
      currentLine.push({x:e.offsetX, y:e.offsetY});
    });

    canvas.addEventListener('mouseup', () => {
      if (!drawing) return;
      drawing = false;
      ctx.closePath();
      ctx.globalCompositeOperation = 'source-over';
      ctx.globalAlpha = 1.0;
      if (currentLine.length > 1 && currentTool !== "eraser") {
        if (!annotations[pageNum]) annotations[pageNum] = [];
        annotations[pageNum].push({
          tool: currentTool,
          color: penColor,
          size: penSize,
          line: currentLine
        });
      }
      currentLine = [];
    });

    function eraseAt(x, y) {
      const radius = 10;
      if (!annotations[pageNum]) return;
      annotations[pageNum] = annotations[pageNum].filter(obj => {
        return !obj.line.some(p => Math.hypot(p.x - x, p.y - y) < radius);
      });
      redraw();
    }

    function redraw() {
      pdfDoc.getPage(pageNum).then(page => {
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const renderContext = { canvasContext: ctx, viewport: viewport };
        page.render(renderContext).promise.then(drawAnnotations);
      });
    }

    // 工具切換
    document.getElementById('penBtn').onclick = () => currentTool = "pen";
    document.getElementById('eraseBtn').onclick = () => currentTool = "eraser";

    // 頁面控制
    document.getElementById('prevBtn').onclick = () => { if (pageNum > 1) { pageNum--; renderPage(); } };
    document.getElementById('nextBtn').onclick = () => { if (pageNum < pdfDoc.numPages) { pageNum++; renderPage(); } };

    // 清除當前頁
    document.getElementById('clearBtn').onclick = () => {
      annotations[pageNum] = [];
      redraw();
    };

    // 保存
    document.getElementById('saveBtn').onclick = () => {
      fetch('save_annotation.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          file: "<?php echo $file; ?>",
          annotations: annotations
        })
      }).then(r => r.text()).then(t => alert(t));
    };

    // 截圖並儲存
    document.getElementById('screenshotBtn').onclick = async () => {
      try {
        // disable 按鈕避免重覆點擊
        const btn = document.getElementById('screenshotBtn');
        btn.disabled = true;
        btn.textContent = '儲存中...';

        // 取得目前 canvas 圖（包含已畫的標記）
        const dataUrl = canvas.toDataURL('image/png');

        const resp = await fetch('save_screenshot.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            file: "<?php echo $file; ?>",
            page: pageNum,
            image: dataUrl
          })
        });
        const j = await resp.json();
        if (j.ok) {
          alert('截圖已儲存：' + (j.path || j.id));
        } else {
          console.error(j);
          alert('儲存失敗：' + (j.error || '未知錯誤'));
        }
      } catch (e) {
        console.error(e);
        alert('連線錯誤，無法儲存截圖');
      } finally {
        const btn = document.getElementById('screenshotBtn');
        btn.disabled = false;
        btn.textContent = '📷 截圖並儲存';
      }
    }

// 顏色、粗細
document.getElementById("colorPicker").oninput = e => penColor = e.target.value;
document.getElementById("sizePicker").oninput = e => penSize = e.target.value;

// 載入標記
fetch(annFile)
  .then(r => r.ok ? r.json() : {})
  .then(data => { annotations = data; })
  .catch(() => {});

// -------- 新增：GPT 偵測標記功能（只處理目前頁） --------
function bboxFromLine(line) {
  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
  line.forEach(p => {
    minX = Math.min(minX, p.x); minY = Math.min(minY, p.y);
    maxX = Math.max(maxX, p.x); maxY = Math.max(maxY, p.y);
  });
  const pad = 4;
  return { x: minX - pad, y: minY - pad, w: (maxX - minX) + pad*2, h: (maxY - minY) + pad*2 };
}

function getTextInBBox(page, bbox) {
  return page.getTextContent().then(tc => {
    const pieces = [];
    tc.items.forEach(item => {
      const tx = item.transform[4];
      const ty = item.transform[5];
      const vp = viewport.convertToViewportPoint(tx, ty);
      const x = vp[0], y = vp[1];
      if (x >= bbox.x && x <= (bbox.x + bbox.w) && y >= bbox.y && y <= (bbox.y + bbox.h)) {
        pieces.push(item.str);
      }
    });
    return pieces.join(' ');
  });
}

document.getElementById('gptDetectBtn').onclick = async () => {
  if (!annotations[pageNum] || annotations[pageNum].length === 0) {
    alert('此頁沒有標記');
    return;
  }
  const page = await pdfDoc.getPage(pageNum);
  const items = [];
  for (let i = 0; i < annotations[pageNum].length; i++) {
    const obj = annotations[pageNum][i];
    const bbox = bboxFromLine(obj.line);
    const text = (await getTextInBBox(page, bbox)).trim();
    items.push({ index: i, page: pageNum, bbox: bbox, text: text });
  }

  const resp = await fetch('annot_gpt.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ file: "<?php echo $file; ?>", items: items })
  });
  const data = await resp.json();
  if (!data.ok) {
    alert('GPT 偵測失敗：' + (data.error || JSON.stringify(data)));
    return;
  }
  const out = data.results.map(r => `標記 #${r.index} (頁 ${r.page}):\n- 摘要: ${r.summary}\n- 文字: ${r.extracted_text || '(無擷取文字)'}\n`).join('\n\n');
  const w = window.open('', '_blank', 'width=600,height=600,scrollbars=yes');
  w.document.body.innerText = out;
};
// -------- 以上為 GPT 偵測標記功能 --------

document.currentScript && (function(){
  const modalHtml = `
  <div id="gptModal" style="display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:720px; max-width:95%; height:540px; background:#fff; border:1px solid #ccc; z-index:9999; box-shadow:0 8px 24px rgba(0,0,0,0.2);">
    <div style="padding:8px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee;">
      <strong>與 GPT 對話（針對本頁標記）</strong>
      <div>
        <button id="gptModalClose">關閉</button>
      </div>
    </div>
    <div style="display:flex; height:calc(100% - 96px);">
      <div id="gptContext" style="width:280px; border-right:1px solid #eee; padding:8px; overflow:auto; font-size:13px;">
        <div style="font-weight:600; margin-bottom:6px;">標記摘要</div>
        <div id="gptContextBody" style="font-size:13px; color:#333;"></div>
      </div>
      <div style="flex:1; display:flex; flex-direction:column;">
        <div id="gptChatArea" style="flex:1; padding:8px; overflow:auto; background:#fafafa;"></div>
        <div style="padding:8px; border-top:1px solid #eee; display:flex; gap:8px;">
          <input id="gptInput" type="text" placeholder="輸入訊息..." style="flex:1; padding:6px;" />
          <button id="gptSend">送出</button>
        </div>
      </div>
    </div>
  </div>
  `;
  const wrap = document.createElement('div');
  wrap.innerHTML = modalHtml;
  document.body.appendChild(wrap);
})();

// 開啟對話：收集本頁標記與擷取文字（若無標記會提示）
async function openGptChat() {
  if (!annotations[pageNum] || annotations[pageNum].length === 0) {
    alert('此頁沒有標記可供對話。');
    return;
  }
  // 先在左側顯示標記摘要（index、顏色、筆跡 bbox）
  const parts = [];
  const page = await pdfDoc.getPage(pageNum);
  for (let i = 0; i < annotations[pageNum].length; i++) {
    const obj = annotations[pageNum][i];
    const bbox = bboxFromLine(obj.line);
    const text = (await getTextInBBox(page, bbox)).trim();
    parts.push({ index: i, tool: obj.tool, color: obj.color, text: text || '(無擷取文字)', bbox });
  }
  const ctxBody = document.getElementById('gptContextBody');
  ctxBody.innerHTML = parts.map(p => `<div style="margin-bottom:8px;"><strong>#${p.index}</strong> ${p.tool} <br><span style="color:#666;font-size:12px;">"${escapeHtml(p.text)}"</span></div>`).join('');

  // 初始化聊天區並放入 system prompt（將標記內容作為上下文）
  const chatArea = document.getElementById('gptChatArea');
  chatArea.innerHTML = '';
  appendMsg('system', '你現在是文件標記助理。以下為本頁標記內容，使用者會就這些標記向你提問：\n' + parts.map(p => `#${p.index}: ${p.text}`).join('\n'));

  // 顯示 modal
  document.getElementById('gptModal').style.display = 'block';
  document.getElementById('gptInput').focus();

  // 儲存上下文以供送訊使用
  window.__gpt_chat_context = { file: "<?php echo $file; ?>", page: pageNum, items: parts, messages: [] };
}

// 辅助：顯示訊息
function appendMsg(role, text) {
    const chatArea = document.getElementById('gptChatArea');
    const el = document.createElement('div');
    el.className = 'gpt-msg ' + role;

    const contentEl = document.createElement('div');
    contentEl.className = 'gpt-msg-content';
    contentEl.textContent = text || '';
    el.appendChild(contentEl);

    if (role === 'assistant') {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gpt-add-note-btn';
        btn.textContent = '加入筆記';
        btn.style.marginLeft = '8px';
        btn.onclick = async () => {
            // 優先抓取使用者選取文字（限定在此訊息內），沒有則用整段
            let selected = '';
            try {
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0) {
                    selected = sel.toString().trim();
                    const anchorNode = sel.anchorNode;
                    if (anchorNode && !contentEl.contains(anchorNode)) selected = '';
                }
            } catch(e){ selected = ''; }

            // 優先選取文字，否則用訊息整段（去頭尾空白）
            const noteContent = (selected || (text || '')).trim();
            if (!noteContent) { alert('沒有可存的文字'); return; }

            const title = (prompt('筆記標題（可空白）：', noteContent.slice(0,60)) || '').trim();

            // debug: 在送出前印出 payload（DevTools Console → Console）
            const payload = {
                file: window.__gpt_chat_context?.file || '',
                page: window.__gpt_chat_context?.page || 0,
                title: title,
                content: noteContent
            };
            console.log('save_note payload:', payload);

            try {
                const resp = await fetch('save_note.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload)
                });
                // 若後端回傳非 JSON（例如舊版回傳純文字），也用 text() 看看
                const textResp = await resp.text();
                let j;
                try { j = JSON.parse(textResp); } catch(e) { j = null; }

                if (j && j.ok) {
                    alert('已儲存筆記');
                } else if (j) {
                    console.warn('save_note failed', j);
                    alert('儲存失敗：' + (j.error || 'unknown'));
                } else {
                    // 非 JSON 回應：在 Console 顯示完整回應並提示
                    console.warn('save_note non-json response:', textResp);
                    alert('伺服器回應無法解析，請查看 DevTools → Network / Console。');
                }
            } catch (e) {
                console.error(e);
                alert('連線錯誤，無法儲存筆記');
            }
        };
        el.appendChild(btn);
    }

    chatArea.appendChild(el);
    chatArea.scrollTop = chatArea.scrollHeight;
}

// 送出訊息到後端（後端需呼叫 OpenAI 或相容 API），改為呼叫 annot_gpt.php，並加上 loading/error 處理
async function sendGptMessage(userText) {
  if (!window.__gpt_chat_context) return;
  appendMsg('user', userText);
  window.__gpt_chat_context.messages.push({ role: 'user', content: userText });

  const payload = {
    file: window.__gpt_chat_context.file,
    page: window.__gpt_chat_context.page,
    items: window.__gpt_chat_context.items,
    messages: window.__gpt_chat_context.messages
  };

  const sendBtn = document.getElementById('gptSend');
  const inputEl = document.getElementById('gptInput');
  sendBtn.disabled = true;
  const oldPlaceholder = inputEl.placeholder;
  inputEl.placeholder = '傳送中...';

  try {
    const resp = await fetch('annot_gpt.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    const text = await resp.text();
    // 嘗試解析成 JSON，失敗則把原始文字顯示並在 console 輸出完整內容
    let data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('annot_gpt 非 JSON 回應：', text);
      appendMsg('assistant', '伺服器回應非 JSON（請看 DevTools Console 或 Network）。回應片段：\n' + text.slice(0,1000));
      return;
    }

    if (!data.ok) {
      appendMsg('assistant', '伺服器錯誤：' + (data.error || JSON.stringify(data)));
      console.warn('annot_gpt error payload:', data);
      return;
    }

    appendMsg('assistant', data.reply || '(無回覆)');
    window.__gpt_chat_context.messages.push({ role: 'assistant', content: data.reply || '' });
  } catch (err) {
    appendMsg('assistant', '連線錯誤：' + err.message);
    console.error(err);
  } finally {
    sendBtn.disabled = false;
    inputEl.placeholder = oldPlaceholder;
    inputEl.focus();
  }
}

// 綁定按鈕
document.getElementById('gptChatBtn').onclick = openGptChat;
document.getElementById('gptModalClose').onclick = () => { document.getElementById('gptModal').style.display = 'none'; }

// 送出鍵
document.getElementById('gptSend').onclick = () => {
  const v = document.getElementById('gptInput').value.trim();
  if (!v) return;
  document.getElementById('gptInput').value = '';
  sendGptMessage(v);
};
// Enter 鍵也送出
document.getElementById('gptInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('gptSend').click(); }
});

// 小工具：逃脫 HTML（顯示用）
function escapeHtml(s) {
  return (s+'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// -------- 新增結束 --------
</script>
</body>
</html>
