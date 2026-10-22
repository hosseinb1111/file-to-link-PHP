<?php
/**
 * file-to-link — single-file PHP uploader
 * https://github.com/hosseinb1111/file-to-link-PHP
 *
 * @author  Hossein Seyed Bagheri
 * @license MIT
 */

declare(strict_types=1);

/* =========================================================================
 * CONFIG — edit these four constants only
 * ========================================================================= */
const DB_HOST = 'sqlXXX.infinityfree.com';   // your MySQL host
const DB_NAME = 'if0_XXXXXXXX_drops';        // database name
const DB_USER = 'if0_XXXXXXXX';              // MySQL user (NOT the db name!)
const DB_PASS = 'your-password-here';

const MAX_UPLOAD = 50 * 1024 * 1024;         // 50 MB
const CODE_LEN   = 8;                        // length of the random code

/* =========================================================================
 * DATABASE
 * ========================================================================= */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>database error</title>
<style>
body{font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f6f7;color:#1a1a1f;padding:40px 20px}
.wrap{max-width:600px;margin:0 auto}
h1{font-size:20px;margin:0 0 12px}
pre{background:#fff;border:1px solid #d4d4d9;border-radius:8px;padding:14px;overflow:auto;font-size:13px;color:#a11818}
ul{color:#4b4b52;padding-left:20px}
li{margin:6px 0}
code{background:#eee;padding:1px 5px;border-radius:4px;font-size:.92em}
</style></head><body><div class="wrap">
<h1>database connection failed</h1>
<p>Edit the four constants at the top of <code>index.php</code>:</p>
<pre>{$msg}</pre>
<ul>
<li><code>DB_HOST</code> — copy from control panel &rarr; MySQL Databases</li>
<li><code>DB_USER</code> — the <strong>user</strong> (<code>if0_XXXXXXXX</code>), not the db name</li>
<li><code>DB_NAME</code> — the <strong>database</strong> (<code>if0_XXXXXXXX_drops</code>)</li>
<li><code>DB_PASS</code> — reset it in the control panel if unsure</li>
</ul></div></body></html>
HTML;
        exit;
    }
    return $pdo;
}

function ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS `drops` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `code`       VARCHAR(32)  NOT NULL,
        `filename`   VARCHAR(255) DEFAULT NULL,
        `mime`       VARCHAR(190) DEFAULT NULL,
        `size`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `is_text`    TINYINT(1)   NOT NULL DEFAULT 0,
        `content`    LONGBLOB,
        `created_at` DATETIME     NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

/* =========================================================================
 * HELPERS
 * ========================================================================= */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base(): string
{
    $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($d === '.' || $d === '/' || $d === '') ? '' : $d;
}

function u(string $p): string
{
    return base() . $p;
}

function rand_code(int $n): string
{
    $a = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    $max = strlen($a) - 1;
    for ($i = 0; $i < $n; $i++) {
        $s .= $a[random_int(0, $max)];
    }
    return $s;
}

function new_code(): string
{
    $st = db()->prepare('SELECT 1 FROM drops WHERE code = ?');
    for ($i = 0; $i < 10; $i++) {
        $c = rand_code(CODE_LEN);
        $st->execute([$c]);
        if (!$st->fetchColumn()) {
            return $c;
        }
    }
    return rand_code(CODE_LEN + 6);
}

function human(int $n): string
{
    if ($n < 1024)    return $n . ' B';
    if ($n < 1048576) return round($n / 1024, 1) . ' KB';
    return round($n / 1048576, 2) . ' MB';
}

/* =========================================================================
 * LAYOUT
 * ========================================================================= */
function start(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    $favicon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'"
             . "%3E%3Cpath d='M16 3c-4 6-9 11-9 16a9 9 0 0 0 18 0c0-5-5-10-9-16z' fill='%232563eb'/%3E%3C/svg%3E";

    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    echo "<title>" . h($title) . "</title>";
    echo "<link rel=\"icon\" type=\"image/svg+xml\" href=\"" . $favicon . "\">";
    ?>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; min-height: 100%; }
  body {
    font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: #f6f6f7; color: #1a1a1f;
    padding: 40px 20px;
  }

  :root {
    --line:    #d4d4d9;
    --muted:   #6b6b73;
    --text:    #1a1a1f;
    --accent:  #2563eb;
    --fact-bg: #ffffff;
  }

  .wrap { max-width: 560px; margin: 0 auto; }

  h1 { font-size: 20px; font-weight: 600; margin: 0 0 4px; letter-spacing: -.01em; }
  .sub { color: #6b6b73; margin: 0 0 24px; font-size: 14px; }

  .drop {
    display: block; width: 100%;
    border: 1.5px dashed #d4d4d9; border-radius: 8px;
    padding: 36px 20px; text-align: center; cursor: pointer;
    background: #fff; transition: .15s; font: inherit; color: inherit;
  }
  .drop:hover, .drop.over { border-color: #2563eb; background: #f5f8ff; }
  .drop .big { font-weight: 500; margin-bottom: 2px; }
  .drop .small { color: #6b6b73; font-size: 13px; }

  .or { text-align: center; color: #9a9aa3; font-size: 12px; margin: 20px 0; }

  textarea {
    width: 100%; border: 1px solid #d4d4d9; background: #fff;
    border-radius: 8px; padding: 12px; color: inherit; outline: none;
    font: 13px/1.5 ui-monospace, Menlo, Consolas, monospace;
    resize: vertical; min-height: 100px;
  }
  textarea:focus { border-color: #2563eb; }
  .row-between {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 8px; font-size: 12px; color: #6b6b73;
  }

  button, .btn {
    display: inline-block; padding: 7px 14px; border-radius: 6px;
    border: 1px solid #d4d4d9; background: #fff; color: #1a1a1f;
    font: inherit; font-size: 14px; font-weight: 500; cursor: pointer;
    text-decoration: none; transition: .12s;
  }
  button:hover, .btn:hover { background: #f3f3f5; text-decoration: none; }
  .primary { background: #2563eb; border-color: #2563eb; color: #fff; }
  .primary:hover { background: #1d4fd7; border-color: #1d4fd7; }
  button:disabled { opacity: .5; cursor: not-allowed; }

  .progress { display: none; margin-top: 16px; }
  .progress.on { display: block; }
  .progress .info {
    display: flex; justify-content: space-between;
    font-size: 12px; color: #6b6b73; margin-bottom: 6px;
  }
  .progress .track { height: 4px; background: #e3e3e8; border-radius: 2px; overflow: hidden; }
  .progress .fill {
    height: 100%; width: 0; background: #2563eb;
    transition: width .1s linear;
  }

  .result {
    display: none; margin-top: 16px; padding: 12px; border-radius: 8px;
    border: 1px solid #d4d4d9; background: #fff;
  }
  .result.on { display: block; }
  .result.ok  { border-color: #86d9a4; background: #f1fbf4; }
  .result.err { border-color: #f0a9a9; background: #fdf2f2; color: #a11818; }
  .result .label {
    font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    color: #6b6b73; margin-bottom: 6px;
  }
  .result.ok .label { color: #16a34a; }
  .result .row-between { margin-top: 0; gap: 6px; }
  .result input {
    flex: 1; min-width: 0; padding: 7px 10px;
    border: 1px solid #d4d4d9; border-radius: 6px;
    background: #fff; color: inherit;
    font: 13px ui-monospace, Menlo, Consolas, monospace;
  }

  footer {
    margin-top: 40px; text-align: center;
    font-size: 12px; color: #9a9aa3;
  }

  .meta {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
  }
  .meta .name { font-weight: 600; word-break: break-all; margin-bottom: 2px; }
  .meta .info { font-size: 13px; color: #6b6b73; }
  .meta .actions { display: flex; gap: 6px; }

  .preview {
    border: 1px solid #d4d4d9; border-radius: 8px; background: #fff;
    padding: 14px; max-height: 60vh; overflow: auto;
  }
  .preview pre {
    margin: 0;
    font: 13px/1.55 ui-monospace, Menlo, Consolas, monospace;
    white-space: pre-wrap; word-break: break-word;
  }
  .preview img { max-width: 100%; display: block; margin: 0 auto; }
  .preview .note { color: #6b6b73; text-align: center; padding: 30px 0; }

  .back {
    display: inline-block; color: #6b6b73; font-size: 13px;
    margin-bottom: 20px;
  }
  .back:hover { color: #1a1a1f; text-decoration: none; }

  .nf { text-align: center; padding: 60px 20px; }
  .nf h1 { font-size: 48px; font-weight: 700; margin: 0 0 8px; }
  .nf p  { color: #6b6b73; margin: 0 0 20px; }

  /* ---- footer credit ---- */
  .footer-bottom {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px;
    max-width: 560px;
    margin: 12px auto 0;
    padding-top: 14px;
    border-top: 1px solid var(--line, #d4d4d9);
    color: var(--muted, #6b6b73);
    font-size: .7rem;
    line-height: 1.7;
    text-align: center;
  }

  .footer-bottom .heart,
  .footer-bottom .coffee {
    display: inline-block;
    font-size: 1em;
    line-height: 1;
    transform-origin: center;
  }

  .footer-bottom .heart { animation: heart-beat 1.8s ease-in-out infinite; }

  @keyframes heart-beat {
    0%, 100% { transform: scale(1); }
    15%      { transform: scale(1.25); }
    30%      { transform: scale(1); }
    45%      { transform: scale(1.15); }
  }

  .footer-bottom .coffee { animation: coffee-bob 2.6s ease-in-out infinite; }

  @keyframes coffee-bob {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    40%      { transform: translateY(-2px) rotate(-6deg); }
    70%      { transform: translateY(0) rotate(3deg); }
  }

  .footer-bottom .credit-name {
    color: var(--text, #1a1a1f);
    font-weight: 700;
  }

  .github-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin-left: 2px;
    color: var(--text, #1a1a1f);
    background: var(--fact-bg, #fff);
    border: 1px solid var(--line, #d4d4d9);
    border-radius: 50%;
    text-decoration: none;
    transition: transform .22s ease, background .22s ease,
                color .22s ease, border-color .22s ease;
  }
  .github-link:hover {
    transform: scale(1.12) translateY(-1px);
    background: var(--accent, #2563eb);
    color: #fff;
    border-color: var(--accent, #2563eb);
  }
  .github-link:active        { transform: scale(.94); }
  .github-link:focus-visible { outline: 2px solid var(--accent, #2563eb); outline-offset: 3px; }
  .github-link svg { display: block; width: 14px; height: 14px; fill: currentColor; }

  @media (prefers-reduced-motion: reduce) {
    .footer-bottom .heart,
    .footer-bottom .coffee { animation: none !important; }
    .github-link           { transition: none !important; }
  }

  @media (max-width: 400px) {
    .footer-bottom { font-size: .65rem; }
  }
</style>
</head><body>
<?php
}

function end_page(): void
{
    echo '<footer>small drop tool · php ' . PHP_VERSION . '</footer>';

    echo <<<'HTML'
<div class="footer-bottom">
  <span>Created with</span>
  <span class="heart" role="img" aria-label="love">❤️</span>
  <span>&amp;</span>
  <span class="coffee" role="img" aria-label="coffee">☕</span>
  <span>by</span>
  <span class="credit-name">Hossein Seyed Bagheri</span>
  <a
    class="github-link"
    href="https://github.com/hosseinb1111/file-to-link-PHP"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="View project on GitHub"
    title="View on GitHub"
  >
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
         aria-hidden="true" focusable="false">
      <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
    </svg>
  </a>
</div>
HTML;

    echo '</body></html>';
}

/* =========================================================================
 * PAGES
 * ========================================================================= */
function page_home(): void
{
    $mb = (int)round(MAX_UPLOAD / 1048576);
    start('drop');
    ?>
<div class="wrap">
  <h1>drop</h1>
  <p class="sub">Files and text get stored and you receive a short link. Max <?= $mb ?> MB.</p>

  <button type="button" id="drop" class="drop" data-url="<?= h(u('/api/upload')) ?>">
    <input type="file" id="file" style="position:absolute;width:0;height:0;opacity:0;pointer-events:none">
    <div class="big">Drop a file, or click to browse</div>
    <div class="small">up to <?= $mb ?> MB</div>
  </button>

  <div class="or">or</div>

  <div>
    <textarea id="text" placeholder="paste some text here..." spellcheck="false"></textarea>
    <div class="row-between">
      <span id="count">0 chars</span>
      <button id="saveText" class="primary">save text</button>
    </div>
  </div>

  <div id="progress" class="progress">
    <div class="info"><span id="pct">0%</span><span id="psize"></span></div>
    <div class="track"><div class="fill" id="fill"></div></div>
  </div>

  <div id="result" class="result">
    <div class="label">link</div>
    <div class="row-between">
      <input id="linkInput" type="text" readonly>
      <button id="copyBtn">copy</button>
      <a id="openBtn" class="btn primary" target="_blank" rel="noopener">open</a>
    </div>
  </div>
</div>

<script>
(function () {
  var MAX = <?= MAX_UPLOAD ?>;
  var drop = document.getElementById('drop'),
      file = document.getElementById('file'),
      text = document.getElementById('text'),
      count = document.getElementById('count'),
      progress = document.getElementById('progress'),
      fill = document.getElementById('fill'),
      pct = document.getElementById('pct'),
      psize = document.getElementById('psize'),
      result = document.getElementById('result'),
      linkInput = document.getElementById('linkInput'),
      copyBtn = document.getElementById('copyBtn'),
      openBtn = document.getElementById('openBtn'),
      uploadUrl = drop.dataset.url;

  function fmt(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    return (n / 1048576).toFixed(2) + ' MB';
  }

  text.addEventListener('input', function () {
    count.textContent = text.value.length.toLocaleString() + ' chars';
  });

  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) {
      e.preventDefault();
      drop.classList.add('over');
    });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) {
      e.preventDefault();
      drop.classList.remove('over');
    });
  });
  drop.addEventListener('drop', function (e) {
    var f = e.dataTransfer.files[0];
    if (f) doFile(f);
  });
  drop.addEventListener('click', function () { file.click(); });
  file.addEventListener('change', function () {
    if (file.files[0]) doFile(file.files[0]);
  });

  document.getElementById('saveText').addEventListener('click', function () {
    if (!text.value.trim()) {
      showErr('type some text first');
      return;
    }
    var fd = new FormData();
    fd.append('text', text.value);
    send(fd, 'saving text');
  });

  function doFile(f) {
    if (f.size > MAX) {
      showErr('file is ' + fmt(f.size) + ', max ' + fmt(MAX));
      return;
    }
    var fd = new FormData();
    fd.append('file', f);
    send(fd, f.name);
  }

  function send(fd, label) {
    result.className = 'result';
    progress.classList.add('on');
    fill.style.width = '0%';
    pct.textContent = '0%';
    psize.textContent = label;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', uploadUrl);
    xhr.upload.onprogress = function (e) {
      if (!e.lengthComputable) return;
      var p = Math.round((e.loaded / e.total) * 100);
      fill.style.width = p + '%';
      pct.textContent = p + '%';
      psize.textContent = fmt(e.loaded) + ' / ' + fmt(e.total);
    };
    xhr.onload = function () {
      progress.classList.remove('on');
      var data;
      try { data = JSON.parse(xhr.responseText); }
      catch (err) { showErr('server returned garbage'); return; }
      if (!data.ok) { showErr(data.error || 'upload failed'); return; }
      showOk(data);
    };
    xhr.onerror = function () {
      progress.classList.remove('on');
      showErr('network error');
    };
    xhr.send(fd);
  }

  function esc(s) {
    return String(s).replace(/[<>&"]/g, function (c) {
      return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' })[c];
    });
  }

  function showOk(data) {
    var full = new URL(data.url, location.href).href;
    linkInput.value = full;
    openBtn.href = full;
    result.className = 'result on ok';
    linkInput.select();
  }
  function showErr(msg) {
    result.className = 'result on err';
    result.innerHTML = '<div class="label">error</div><div>' + esc(msg) + '</div>';
  }

  copyBtn.addEventListener('click', function () {
    var done = function () {
      copyBtn.textContent = 'copied';
      setTimeout(function () { copyBtn.textContent = 'copy'; }, 1200);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(linkInput.value).then(done, done);
    } else {
      linkInput.select();
      document.execCommand('copy');
      done();
    }
  });
})();
</script>
    <?php
    end_page();
}

function page_view(string $code): void
{
    ensure_schema();
    $st = db()->prepare('SELECT * FROM drops WHERE code = ?');
    $st->execute([$code]);
    $row = $st->fetch();

    if (!$row) {
        page_404();
        return;
    }

    $name   = $row['filename'] ?: 'file';
    $mime   = $row['mime']     ?: 'application/octet-stream';
    $isText = (int)$row['is_text'] === 1;
    $raw    = u('/raw/' . $row['code']);
    $dl     = u('/dl/'  . $row['code']);

    start($name);
    ?>
<div class="wrap">
  <a class="back" href="<?= h(u('/')) ?>">← new drop</a>

  <div class="meta">
    <div>
      <div class="name"><?= h($name) ?></div>
      <div class="info">
        <?= h(human((int)$row['size'])) ?> ·
        <?= h($mime) ?> ·
        <?= h($row['created_at']) ?> UTC
      </div>
    </div>
    <div class="actions">
      <a class="btn primary" href="<?= h($dl) ?>">download</a>
      <a class="btn" href="<?= h($raw) ?>" target="_blank" rel="noopener">raw</a>
    </div>
  </div>

  <?php if ($isText): ?>
    <div class="preview"><pre id="preview"><?= h((string)$row['content']) ?></pre></div>
    <div class="row-between">
      <span></span>
      <button id="copyText">copy text</button>
    </div>
    <script>
      document.getElementById('copyText').addEventListener('click', function () {
        var t = document.getElementById('preview').textContent;
        var btn = document.getElementById('copyText');
        var done = function () {
          btn.textContent = 'copied';
          setTimeout(function () { btn.textContent = 'copy text'; }, 1200);
        };
        if (navigator.clipboard) {
          navigator.clipboard.writeText(t).then(done, done);
        } else {
          var ta = document.createElement('textarea');
          ta.value = t;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
          done();
        }
      });
    </script>
  <?php elseif (strpos($mime, 'image/') === 0): ?>
    <div class="preview"><img src="<?= h($raw) ?>" alt=""></div>
  <?php else: ?>
    <div class="preview"><div class="note"><?= h($mime) ?> file — use the download button above.</div></div>
  <?php endif; ?>
</div>
    <?php
    end_page();
}

function page_404(): void
{
    http_response_code(404);
    start('404');
    ?>
<div class="wrap">
  <div class="nf">
    <h1>404</h1>
    <p>The link is wrong, or the item was removed.</p>
    <a class="btn primary" href="<?= h(u('/')) ?>">back home</a>
  </div>
</div>
    <?php
    end_page();
}

/* =========================================================================
 * ACTIONS
 * ========================================================================= */
function do_upload(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        ensure_schema();

        // If the POST body was bigger than post_max_size, PHP empties $_POST
        // and $_FILES entirely — detect that case and report it clearly.
        if (empty($_FILES) && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new RuntimeException(
                'Upload too big for this server (post_max_size). ' .
                'Lower MAX_UPLOAD or raise post_max_size in the control panel.'
            );
        }

        $file = $_FILES['file'] ?? null;
        $text = isset($_POST['text']) ? (string)$_POST['text'] : '';

        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errs = [
                    UPLOAD_ERR_INI_SIZE   => 'file exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE  => 'file exceeds form MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL    => 'upload was interrupted',
                    UPLOAD_ERR_NO_TMP_DIR => 'no temp directory available',
                    UPLOAD_ERR_CANT_WRITE => 'failed to write temp file',
                    UPLOAD_ERR_EXTENSION  => 'upload blocked by PHP extension',
                ];
                throw new RuntimeException($errs[$file['error']] ?? ('upload error #' . $file['error']));
            }
            if ($file['size'] > MAX_UPLOAD) {
                throw new RuntimeException('file too big (max ' . round(MAX_UPLOAD / 1048576) . ' MB)');
            }
            $data = file_get_contents($file['tmp_name']);
            if ($data === false) {
                throw new RuntimeException('could not read uploaded file');
            }
            $name   = basename((string)$file['name']) ?: 'file';
            $mime   = function_exists('mime_content_type')
                      ? (mime_content_type($file['tmp_name']) ?: 'application/octet-stream')
                      : ($file['type'] ?: 'application/octet-stream');
            $isText = 0;

        } elseif (trim($text) !== '') {
            $data = $text;
            if (strlen($data) > MAX_UPLOAD) {
                throw new RuntimeException('text too big');
            }
            $name   = 'snippet.txt';
            $mime   = 'text/plain; charset=utf-8';
            $isText = 1;

        } else {
            throw new RuntimeException('nothing to save');
        }

        $code = new_code();

        $st = db()->prepare(
            'INSERT INTO drops (code, filename, mime, size, is_text, content, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->bindValue(1, $code);
        $st->bindValue(2, $name);
        $st->bindValue(3, $mime);
        $st->bindValue(4, strlen($data), PDO::PARAM_INT);
        $st->bindValue(5, $isText, PDO::PARAM_INT);
        $st->bindValue(6, $data, PDO::PARAM_LOB);
        $st->bindValue(7, gmdate('Y-m-d H:i:s'));
        $st->execute();

        echo json_encode([
            'ok'   => true,
            'code' => $code,
            'url'  => u('/' . $code),
        ]);

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

function serve(string $code, bool $download): void
{
    ensure_schema();
    $st = db()->prepare('SELECT * FROM drops WHERE code = ?');
    $st->execute([$code]);
    $row = $st->fetch();

    if (!$row) {
        page_404();
        return;
    }

    $mime = $row['mime'] ?: 'application/octet-stream';
    $name = $row['filename'] ?: 'file';

    // Force attachment for potentially executable MIMEs, even on /raw/.
    $ctype = strtolower(trim(explode(';', $mime)[0]));
    $dangerous = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml',
        'application/xml', 'text/xml',
        'application/javascript', 'text/javascript', 'application/x-javascript',
    ];
    $forceDownload = $download || in_array($ctype, $dangerous, true);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen((string)$row['content']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=31536000, immutable');

    if ($forceDownload) {
        $safe = preg_replace('/[^\w.\- ]+/u', '_', $name) ?: 'file';
        header('Content-Disposition: attachment; filename="' . $safe . '"');
    }

    echo (string)$row['content'];
}

/* =========================================================================
 * ROUTER
 * ========================================================================= */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri    = is_string($uri) ? rawurldecode($uri) : '/';

$b = base();
if ($b !== '' && ($uri === $b || strpos($uri, $b . '/') === 0)) {
    $uri = substr($uri, strlen($b));
}
$uri = '/' . trim($uri, '/');

// API
if ($uri === '/api/upload' && $method === 'POST') {
    do_upload();
    exit;
}

// Pages
if ($method === 'GET' || $method === 'HEAD') {

    if ($uri === '/') {
        page_home();
        exit;
    }

    if (preg_match('#^/raw/([A-Za-z0-9]{1,32})$#', $uri, $m)) {
        serve($m[1], false);
        exit;
    }

    if (preg_match('#^/dl/([A-Za-z0-9]{1,32})$#', $uri, $m)) {
        serve($m[1], true);
        exit;
    }

    if (preg_match('#^/([A-Za-z0-9]{1,32})$#', $uri, $m)) {
        page_view($m[1]);
        exit;
    }
}

// Fallback
page_404();
