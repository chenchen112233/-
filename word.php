<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>Word 編輯器</title>
    <style>
        body { background: #f3f4f6; }
        .editor {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0001;
            padding: 2em;
            min-height: 600px;
            font-size: 18px;
            outline: none;
        }
        .toolbar { text-align: right; margin-bottom: 1em; }
        .toolbar button { margin-left: 8px; }
    </style>
</head>
<body>
    <div class="toolbar">
        <button id="saveBtn">儲存</button>
        <button id="getHighlightBtn">取得標記內容</button>
        <button id="gptNoteBtn">GPT整理筆記</button>
        <button id="aiFormatBtn">AI 智慧排版</button>
        <!-- 已移除： <button id="saveGptNoteBtn">儲存筆記到資料庫</button> -->
        <!-- Audiobook / TTS controls (只保留語音選單與播放/暫停) -->
        <select id="voiceSelect" style="min-width:180px"></select>
        <button id="ttsPlayBtn">▶ 重新</button>
        <button id="ttsPauseBtn">⏸ 暫停</button>
    </div>
    <div id="editor" class="editor" contenteditable="true"></div>
    <div id="gptReply" style="
        position: fixed;
        top: 80px;
        right: 40px;
        width: 350px;
        min-height: 200px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px #0002;
        padding: 1.5em;
        font-size: 16px;
        color: #333;
        z-index: 1000;
        overflow-y: auto;
        display: none;
    ">
        <button onclick="this.parentNode.style.display='none'" style="float:right;">✕</button>
        <div id="gptReplyContent"></div>
    </div>
    <script>
    // 取得檔名
    const params = new URLSearchParams(location.search);
    const filename = params.get('file');
    if (filename) {
        if (filename.toLowerCase().endsWith('.pdf')) {
            // PDF 用 iframe 顯示
            document.getElementById('editor').outerHTML =
                `<iframe src="uploads/${encodeURIComponent(filename)}" width="100%" height="600px"></iframe>`;
        } else {
            // 其他檔案用 AJAX 載入文字
            fetch('word_content.php?file=' + encodeURIComponent(filename))
                .then(res => res.text())
                .then(text => {
                    document.getElementById('editor').innerHTML = text;
                })
                .catch(() => {
                    document.getElementById('editor').innerText = '無法載入檔案內容';
                });
        }
    } else {
        document.getElementById('editor').innerText = '未指定檔案';
    }

    // 螢光筆標記功能（僅限 TXT 類型）
    function enableHighlight() {
        const editor = document.getElementById('editor');
        if (!editor) return;
        editor.addEventListener('mouseup', function () {
            const selection = window.getSelection();
            if (selection.rangeCount > 0 && !selection.isCollapsed) {
                const range = selection.getRangeAt(0);
                if (editor.contains(range.commonAncestorContainer)) {
                    const mark = document.createElement('mark');
                    mark.appendChild(range.extractContents());
                    range.insertNode(mark);
                    selection.removeAllRanges();
                }
            }
        });
        // 點擊 <mark> 可移除標記
        editor.addEventListener('click', function(e) {
            if (e.target.tagName === 'MARK') {
                const mark = e.target;
                const parent = mark.parentNode;
                while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
                parent.removeChild(mark);
            }
        });
    }
    enableHighlight();

    document.getElementById('saveBtn').addEventListener('click', async function() {
        if (filename && !filename.toLowerCase().endsWith('.pdf')) {
            const content = document.getElementById('editor').innerHTML;
            try {
                const res = await fetch('save_word.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({file: filename, content: content})
                });
                const msg = await res.text();
                alert('檔案已儲存：' + msg);
            } catch (e) {
                console.error('save_word error', e);
                alert('儲存檔案失敗');
            }

            // 若有 GPT 結果（顯示在 gptReplyContent），一併儲存到資料庫
            try {
                const gptText = (document.getElementById('gptReplyContent') || {}).innerText || '';
                if (gptText.trim() !== '') {
                    const r = await fetch('save_note.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({note: gptText})
                    });
                    const t = await r.text();
                    alert('GPT 筆記已儲存：' + t);
                }
            } catch (e) {
                console.error('save_note error', e);
                alert('儲存 GPT 筆記失敗');
            }
        } else {
            alert('PDF 檔案無法儲存標記！');
        }
    });

    // 變更：GPT整理筆記只列出有標記的條列，不呼叫外部 API
    document.getElementById('gptNoteBtn').addEventListener('click', function() {
        const highlights = getHighlightedText();
        if (highlights.length === 0) {
            alert('沒有標記內容！');
            return;
        }
        // 建立簡潔條列（每行一項），不做額外說明
        const bulletList = highlights.map(h => '-- ' + h).join('\n');
        const gptReplyContent = document.getElementById('gptReplyContent');
        gptReplyContent.innerText = bulletList;
        document.getElementById('gptReply').style.display = 'block';
    });

    function getHighlightedText() {
        const editor = document.getElementById('editor');
        const marks = editor.querySelectorAll('mark');
        let highlights = [];
        marks.forEach(mark => {
            highlights.push(mark.textContent);
        });
        return highlights;
    }
    </script>
    <script>
    // --- TTS / 有聲書功能 (only Chinese, non-Google voices) ---
    (function () {
        if (!('speechSynthesis' in window)) return; // not supported

        const synth = window.speechSynthesis;
        const voiceSelect = document.getElementById('voiceSelect');
        const playBtn = document.getElementById('ttsPlayBtn');
        const pauseBtn = document.getElementById('ttsPauseBtn');
        let voices = [];
        let currentUtterance = null;
        let isPlaying = false;
        let isPaused = false;

        function isChineseVoice(v) {
            if (!v || !v.lang) return false;
            const lang = v.lang.toLowerCase();
            if (lang.startsWith('zh')) return true; // zh, zh-cn, zh-tw, etc.
            if (v.name && v.name.toLowerCase().includes('chinese')) return true;
            return false;
        }

        function isGoogleVoice(v) {
            if (!v || !v.name) return false;
            return v.name.toLowerCase().includes('google');
        }

        function loadVoices() {
            const all = synth.getVoices() || [];
            // 只保留中文且非 Google 的語音
            voices = all.filter(v => isChineseVoice(v) && !isGoogleVoice(v));

            // fallback: 如果沒中文非Google語音，嘗試找任一非 Google 的語音
            if (voices.length === 0) {
                const nonGoogle = all.filter(v => !isGoogleVoice(v));
                if (nonGoogle.length > 0) voices = [nonGoogle[0]];
            }

            // 最後仍無語音時使用全部語音的第一個（極端 fallback）
            if (voices.length === 0 && all.length > 0) voices = [all[0]];

            voiceSelect.innerHTML = '';
            voices.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.name;
                opt.textContent = v.name + ' (' + v.lang + ')';
                voiceSelect.appendChild(opt);
            });
        }

        loadVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = loadVoices;
        }

        function getSelectedOrFullText() {
            const editor = document.getElementById('editor');
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed && editor.contains(sel.anchorNode)) {
                return sel.toString();
            }
            return editor.innerText || editor.textContent || '';
        }

        function updateButtons() {
            if (!playBtn || !pauseBtn) return;
            if (isPlaying) {
                playBtn.textContent = '▶ 重新';
                if (isPaused) pauseBtn.textContent = '▶ 繼續';
                else pauseBtn.textContent = '⏸ 暫停';
            } else {
                playBtn.textContent = '▶ 重新';
                pauseBtn.textContent = '⏸ 暫停';
            }
        }

        function speakText(text) {
            if (!text) return;
            stopSpeaking();
            const utter = new SpeechSynthesisUtterance(text);
            const selectedVoiceName = voiceSelect.value;
            const v = voices.find(x => x.name === selectedVoiceName) || voices[0];
            if (v) utter.voice = v;
            utter.rate = 1;
            utter.volume = 1;
            utter.onend = function () { currentUtterance = null; isPlaying = false; isPaused = false; updateButtons(); };
            utter.onerror = function (e) { console.error('TTS error', e); currentUtterance = null; isPlaying = false; isPaused = false; updateButtons(); };
            currentUtterance = utter;
            isPlaying = true;
            isPaused = false;
            updateButtons();
            synth.speak(utter);
        }

        function stopSpeaking() {
            try {
                if (synth.speaking || synth.paused) synth.cancel();
            } catch (e) { console.error(e); }
            currentUtterance = null;
            isPlaying = false;
            isPaused = false;
            updateButtons();
        }

        playBtn.addEventListener('click', function () {
            if (synth.paused) {
                synth.resume();
                isPaused = false;
                isPlaying = true;
                updateButtons();
                return;
            }
            const text = getSelectedOrFullText();
            if (!text) { alert('沒有可播放的文字'); return; }
            speakText(text);
        });

        pauseBtn.addEventListener('click', function () {
            if (synth.speaking && !synth.paused) {
                try { synth.pause(); } catch (e) { console.error(e); }
                isPaused = true;
                isPlaying = true;
            } else if (synth.paused) {
                try { synth.resume(); } catch (e) { console.error(e); }
                isPaused = false;
                isPlaying = true;
            }
            updateButtons();
        });

        // initial UI state
        updateButtons();
    })();
    </script>
    <script>
    // AI 智慧排版按鈕行為
    document.getElementById('aiFormatBtn').addEventListener('click', async function () {
        const editor = document.getElementById('editor');
        if (!editor) return alert('找不到編輯器');
        const rawHtml = editor.innerHTML;
        const rawText = editor.innerText || editor.textContent || '';
        if (!rawText.trim()) return alert('內容為空，無法排版');

        const btn = this;
        btn.disabled = true;
        const oldText = btn.textContent;
        btn.textContent = '排版中...';

        try {
            const res = await fetch('ai_format.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ html: rawHtml, text: rawText, filename: (new URLSearchParams(location.search)).get('file') || '' })
            });
            const data = await res.json();
            if (!data || !data.success) {
                return alert('排版失敗：' + (data && data.error ? data.error : '伺服器錯誤'));
            }
            // 顯示預覽並詢問是否套用
            const previewWin = window.open('', '_blank', 'width=800,height=600');
            previewWin.document.write('<title>AI 排版預覽</title><div style="padding:16px;font-family:Arial,sans-serif">' + (data.formatted_html || ('<pre>' + (data.formatted_text || '') + '</pre>')) + '</div>');
            if (confirm('已在新視窗開啟預覽，是否將 AI 排版結果套用到編輯器？')) {
                if (data.formatted_html) editor.innerHTML = data.formatted_html;
                else editor.textContent = data.formatted_text || '';
            }
        } catch (e) {
            console.error('ai_format error', e);
            alert('網路或伺服器錯誤，請查看 Console 與 Network');
        } finally {
            btn.disabled = false;
            btn.textContent = oldText;
        }
    });

    // 新增：呼叫後端 GPT 分析被標記文字（放在現有 script 的末端）
    document.getElementById('getHighlightBtn').addEventListener('click', async function () {
        const highlights = getHighlightedText();
        if (highlights.length === 0) return alert('沒有標記內容！');

        // 組成 items 傳給 annot_gpt.php（後端會優先用 OpenAI，沒有則用 Google）
        const items = highlights.map((txt, idx) => ({ index: idx, page: 0, text: txt }));

        try {
            const resp = await fetch('annot_gpt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: filename || '', items: items })
            });
            const data = await resp.json();
            if (!data || !data.ok) {
                console.error('annot_gpt error', data);
                return alert('GPT 偵測失敗：' + (data && data.error ? data.error : '伺服器錯誤'));
            }

            // 顯示結果到右側面板（gptReplyContent）
            const results = data.results || [];
            const lines = results.map(r => {
                return `標記 #${r.index}：\n摘要：${r.summary || ''}\n擷取文字：${r.extracted_text || r.extractedText || ''}`;
            }).join('\n\n----------------\n\n');

            const gptReplyContent = document.getElementById('gptReplyContent');
            gptReplyContent.innerText = lines || '無結果';
            document.getElementById('gptReply').style.display = 'block';
        } catch (e) {
            console.error('call annot_gpt failed', e);
            alert('呼叫 GPT 時發生錯誤，請查看 Console');
        }
    });
    </script>
</body>
</html>
