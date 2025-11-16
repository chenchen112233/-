# 清理並複製前端檔案到 public/（Windows PowerShell）
# 使用方式：
#   cd C:\xampp\htdocs
#   powershell -ExecutionPolicy Bypass -File .\copy_to_public.ps1

$root = Split-Path -Parent $MyInvocation.MyCommand.Definition
$public = Join-Path $root 'public'

Write-Host "來源：" $root
Write-Host "目標：" $public

# 建 public 目錄（若不存在）
if (-not (Test-Path $public)) {
    New-Item -ItemType Directory -Path $public | Out-Null
}

# 清空 public 內現有檔案（保留 public 本身）
Get-ChildItem -Path $public -Force | ForEach-Object {
    try { Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue } catch {}
}

# 複製根目錄的靜態檔（html, css, js）
Get-ChildItem -Path $root -File -Include *.html,*.css,*.js -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -notin @('server.js','package.json','package-lock.json') } |
    ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $public -Force
        Write-Host "Copied file:" $_.Name
    }

# 要複製的資料夾清單（依需求可增減）
$dirs = @('assets','css','js','img','fonts','note')

foreach ($d in $dirs) {
    $src = Join-Path $root $d
    $dst = Join-Path $public $d
    if (Test-Path $src) {
        # 使用 robocopy 複製資料夾（/E = 包含子資料夾，即使空也複製；/NFL /NDL /NJH /NJS 抑制大量輸出）
        $rc = Start-Process -FilePath robocopy -ArgumentList @("'$src'","'$dst'","/E","/COPY:DAT","/R:2","/W:1","/NFL","/NDL","/NJH","/NJS") -NoNewWindow -Wait -PassThru
        if ($rc.ExitCode -lt 8) {
            Write-Host "Copied dir:" $d
        } else {
            Write-Warning "robocopy failed for $d (code $($rc.ExitCode))"
        }
    }
}

Write-Host "`n複製完成。請檢查 public/ 是否含有你要的前端檔案。"