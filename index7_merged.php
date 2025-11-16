<?php
// 簡單整合版：PDF 管理 + 筆記側欄（前端示範資料）
// 請確保你已有 move_note.php / list_folder.php 可用於後端操作
?>
<!doctype html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>PDF + 筆記 管理（整合版）</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif}
    body{background:#FFF9E6;margin:0;color:#4E342E}
    .container{max-width:1200px;margin:20px auto;padding:16px}
    header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .main-grid{display:grid;grid-template-columns: 320px 1fr; gap:16px}
    .card{background:#fff;padding:14px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.06)}
    .folder-item{padding:8px;border-radius:8px;cursor:pointer;border:1px solid transparent;display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
    .folder-item.drop-hover{outline:2px dashed #FF8A65}
    .note-item{padding:8px;border-radius:6px;border:1px solid #eee;margin-bottom:6px;cursor:grab}
    .note-item.dragging{opacity:0.5}
    .note-preview{min-height:120px;border:1px dashed #eee;padding:8px;border-radius:6px;background:#fafafa}
    .pdf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:12px}
    .pdf-card{background:#fff;border-radius:10px;padding:10px;box-shadow:0 4px 12px rgba(0,0,0,0.05)}
    .small{font-size:13px;color:#666}
    .btn{background:#FF8A65;color:#fff;border:none;padding:6px 10px;border-radius:6px; cursor:pointer}
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;background:#FF8A65;border-radius:8px;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">書</div>
        <h1 style="margin:0;font-size:20px">小书怪 — PDF 與 筆記 管理</h1>
      </div>
      <div class="small">本頁為整合示範，後端需有 move_note.php / list_folder.php 支援</div>
    </header>

    <div class="main-grid">
      <!-- 左側：筆記 & 資料夾 -->
      <div>
        <div class="card" id="notesCard">
          <h3 style="margin-top:0">筆記列表</h3>
          <div id="noteList" style="max-height:360px;overflow:auto;padding-right:6px"></div>
          <hr />
          <h4 style="margin:8px 0 6px 0">筆記預覽</h4>
          <div id="notePreview" class="note-preview">點選左側筆記檢視（再次點同一筆則收起）</div>
        </div>

        <div class="card" style="margin-top:12px" id="foldersCard">
          <h3 style="margin-top:0">資料夾</h3>
          <div id="folderList"></div>
          <div style="margin-top:8px">
            <input id="newFolderInput" placeholder="新增資料夾名稱" style="padding:6px;border:1px solid #ddd;border-radius:6px;width:70%" />
            <button id="createFolderBtn" class="btn" style="padding:6px 8px;margin-left:8px">建立</button>
          </div>
        </div>
      </div>

      <!-- 右側：PDF 管理（簡化）-->
      <div>
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0">PDF 管理</h3>
            <div>
              <button id="uploadBtn" class="btn">上傳</button>
            </div>
          </div>

          <div style="margin-top:12px">
            <input id="searchInput" placeholder="搜尋 PDF..." style="padding:8px;border:1px solid #ddd;border-radius:6px;width:100%" />
          </div>

          <div id="pdfGrid" class="pdf-grid"></div>
        </div>

        <div class="card" style="margin-top:12px">
          <h4 style="margin:0 0 8px 0">資料夾內容預覽</h4>
          <div id="folderContents" class="note-preview">點選左側資料夾載入內容</div>
        </div>
      </div>
    </div>
  </div>

<script>
/* --- 模擬資料（實際可由後端動態產生） --- */
let notes = [
  {id: 101, title: "新筆記 A", content: "這是筆記 A 的內容"},
  {id: 102, title: "讀書筆記 B", content: "筆記 B 的重點..."},
  {id: 103, title: "心得 C", content: "C 的心得筆記文字"}
];

let folders = [
  {name: "作業", count: 3},
  {name: "學習", count: 2},
  {name: "雜物", count: 0}
];

let pdfFiles = [
  {id:1,name:"深度思考完整版.pdf",size:"2.3MB",date:"2025-03-10",folder:"學習"},
  {id:2,name:"專案報告.pdf",size:"1.2MB",date:"2025-04-01",folder:"作業"}
];

let currentNoteId = null;

/* --- render --- */
function renderNoteList(){
  const el = document.getElementById('noteList');
  el.innerHTML = '';
  notes.forEach(n=>{
    const d = document.createElement('div');
    d.className = 'note-item';
    d.draggable = true;
    d.dataset.id = n.id;
    d.innerHTML = '<div style="font-weight:600">'+escapeHtml(n.title)+'</div><div class="small">'+escapeHtml((n.content||'').slice(0,48))+'</div>';
    // toggle click: click same note again will collapse
    d.addEventListener('click', function(e){
      const id = this.dataset.id;
      if (String(currentNoteId) === String(id)){
        currentNoteId = null;
        document.getElementById('notePreview').innerText = '點選左側筆記檢視（再次點同一筆則收起）';
      } else {
        currentNoteId = id;
        const noteObj = notes.find(x=> String(x.id)===String(id));
        document.getElementById('notePreview').innerHTML = '<strong>'+escapeHtml(noteObj.title)+'</strong><p>'+escapeHtml(noteObj.content)+'</p>';
      }
      renderNoteList(); // refresh selection style
    });
    // dragstart 設定 payload
    d.addEventListener('dragstart', function(e){
      try{
        e.dataTransfer.setData('application/json', JSON.stringify({type:'note', id: n.id}));
        d.classList.add('dragging');
      }catch(err){}
    });
    d.addEventListener('dragend', function(){ d.classList.remove('dragging'); });
    // highlight selected
    if (String(currentNoteId) === String(n.id)) d.style.outline = '2px solid #FF8A65';
    el.appendChild(d);
  });
}

function renderFolders(){
  const el = document.getElementById('folderList');
  el.innerHTML = '';
  folders.forEach(f=>{
    const fi = document.createElement('div');
    fi.className = 'folder-item';
    fi.dataset.folder = f.name;
    fi.innerHTML = `<div style="display:flex;gap:8px;align-items:center"><i class="fas fa-folder" style="color:#FFD54F"></i><div>${escapeHtml(f.name)}</div></div><div class="small">${f.count} 件</div>`;
    // click load contents
    fi.addEventListener('click', ()=> loadFolder(f.name));
    // enable drop
    fi.addEventListener('dragover', e=>{ e.preventDefault(); fi.classList.add('drop-hover'); });
    fi.addEventListener('dragleave', ()=> fi.classList.remove('drop-hover'));
    fi.addEventListener('drop', async function(e){
      e.preventDefault(); fi.classList.remove('drop-hover');
      let raw = e.dataTransfer.getData('application/json');
      if (!raw) return alert('無拖曳資料');
      let obj;
      try{ obj = JSON.parse(raw); } catch{ return alert('拖曳資料格式錯誤'); }
      const target = this.dataset.folder;
      if (obj.type === 'note' && obj.id){
        try{
          const res = await fetch('move_note.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: obj.id, folder: target }) });
          const j = await res.json();
          if (j.ok){
            // 從前端移除該筆 note
            const idx = notes.findIndex(x=> String(x.id) === String(obj.id));
            if (idx !== -1) notes.splice(idx,1);
            // 找到目標資料夾數量 ++（若存在）
            const fld = folders.find(x=> x.name === target);
            if (fld) fld.count = (fld.count||0) + 1;
            renderNoteList();
            renderFolders();
            // 可選：載入資料夾內容
            loadFolder(target);
          } else {
            alert('後端移動失敗：' + (j.error || 'unknown'));
          }
        }catch(err){
          alert('網路錯誤，無法移動筆記');
        }
      } else {
        alert('不支援的拖曳類型');
      }
    });
    el.appendChild(fi);
  });
}

function renderPdfGrid(){
  const el = document.getElementById('pdfGrid');
  el.innerHTML = '';
  pdfFiles.forEach(p=>{
    const c = document.createElement('div'); c.className='pdf-card';
    c.innerHTML = `<div style="font-weight:600">${escapeHtml(p.name)}</div><div class="small">${p.size} • ${p.date}</div><div style="margin-top:8px"><button class="btn" onclick="alert('預覽: ${escapeHtml(p.name)}')">預覽</button></div>`;
    el.appendChild(c);
  });
}

/* --- folder contents load (calls list_folder.php) --- */
async function loadFolder(folder){
  document.getElementById('folderContents').innerText = '載入中...';
  try{
    const r = await fetch('list_folder.php?folder=' + encodeURIComponent(folder));
    const j = await r.json();
    if (!j.ok) { document.getElementById('folderContents').innerText = '取得內容失敗'; return; }
    const files = j.files || [];
    if (files.length === 0) {
      document.getElementById('folderContents').innerText = '此資料夾沒有檔案';
      return;
    }
    const html = files.map(f=>{
      const name = escapeHtml(f.name);
      const rel = encodeURI(f.rel);
      if (f.is_image) return `<div style="display:inline-block;width:120px;margin:6px;text-align:center"><img src="${rel}" style="width:100px;height:100px;object-fit:cover;border-radius:6px"><div class="small">${name}</div></div>`;
      return `<div style="padding:6px;border-bottom:1px solid #eee">${name} <a href="${rel}" target="_blank" class="small">下載/打開</a></div>`;
    }).join('');
    document.getElementById('folderContents').innerHTML = html;
  }catch(err){
    document.getElementById('folderContents').innerText = '載入錯誤';
  }
}

/* --- create folder (frontend only) --- */
document.addEventListener('DOMContentLoaded', function(){
  renderNoteList();
  renderFolders();
  renderPdfGrid();

  document.getElementById('createFolderBtn').addEventListener('click', function(){
    const name = document.getElementById('newFolderInput').value.trim();
    if (!name) return alert('請輸入資料夾名稱');
    if (folders.some(f=> f.name === name)) return alert('資料夾已存在');
    folders.push({name, count:0});
    document.getElementById('newFolderInput').value = '';
    renderFolders();
    alert('建立成功（本頁為前端示範，如果要在後端建立需呼叫 API）');
  });

  // 搜尋 PDF
  document.getElementById('searchInput').addEventListener('input', function(e){
    const term = e.target.value.trim().toLowerCase();
    if (!term) { renderPdfGrid(); return; }
    const filtered = pdfFiles.filter(p => p.name.toLowerCase().includes(term));
    const el = document.getElementById('pdfGrid'); el.innerHTML = '';
    filtered.forEach(p=>{
      const c = document.createElement('div'); c.className='pdf-card';
      c.innerHTML = `<div style="font-weight:600">${escapeHtml(p.name)}</div><div class="small">${p.size} • ${p.date}</div>`;
      el.appendChild(c);
    });
  });
});

/* --- utilities --- */
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>