<?php
/*
 * rename-files.php — Universal File Renamer
 * ------------------------------------------------------------------------
 * One self-contained file. No database, no plugins, no other files needed.
 * Works on any web host that runs PHP 7.4 or newer (Apache, Nginx, IONOS,
 * GoDaddy, Bluehost, SiteGround, cPanel hosts, etc.).
 *
 * HOW TO USE ON ANY WEBSITE
 *   1. Change TOOL_PASSWORD below (the page refuses to run until you do).
 *   2. Upload this file to the folder that holds the folders you want to
 *      work on — usually the site root (public_html, htdocs, www...).
 *   3. Visit  https://your-site.com/rename-files.php  and log in.
 *   4. Pick a folder, type what to find (use * as a wild card), Preview,
 *      then Rename. "Undo last batch" puts names back.
 *   5. When finished, delete this file from the server or rename it to
 *      something hard to guess.
 *
 * Every batch is logged to .rename-log.json inside the folder it changed.
 *
 * Built by Geo Geller with Claude, a non-human child of Anthropic.
 */

// ============ SETTINGS — the only part you need to edit ==================
const USE_PASSWORD  = true;                     // true = login required, false = open to anyone
const TOOL_PASSWORD = 'change-this-password';   // CHANGE THIS before uploading
const TOOL_TITLE    = 'Rename Files';           // heading shown on the page
const ROOT_DIR      = __DIR__;                  // folders below this can be chosen
const FOLDER_DEPTH  = 3;                        // how many levels of subfolders to list
// =========================================================================

const LOG_NAME      = '.rename-log.json';

// File types offered as checkboxes. Ticked by default: images, audio, video.
const EXT_GROUPS = [
    'Images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'bmp', 'heic', 'svg'],
    'Audio'  => ['wav', 'mp3', 'm4a', 'aac', 'ogg', 'flac', 'aif', 'aiff'],
    'Video'  => ['mp4', 'mov', 'm4v', 'avi', 'webm', 'mkv', 'wmv'],
    'Pages'  => ['htm', 'html'],
    'Text & documents' => ['txt', 'pdf', 'doc', 'docx', 'rtf'],
];
const DEFAULT_ON = ['Images', 'Audio', 'Video'];

// Scripts and server files are NEVER renamed, whatever is ticked.
const NEVER_RENAME = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc',
    'htaccess', 'htpasswd', 'user.ini', 'ini', 'js', 'mjs', 'css', 'json', 'xml',
    'py', 'pl', 'cgi', 'sh', 'bash', 'rb', 'asp', 'aspx', 'jsp', 'sql', 'log', 'env', 'conf', 'config'];
// ------------------------------------------------------------------------

if (PHP_VERSION_ID < 70400) {
    exit('This tool needs PHP 7.4 or newer. This server runs PHP ' . PHP_VERSION . '.');
}
session_name('renamer_' . substr(md5(__FILE__), 0, 8));   // won't clash with other site logins
session_start();
$base = realpath(ROOT_DIR);
$self = basename(__FILE__);
$msg = '';
$err = '';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---- login / logout ----
if (isset($_POST['password'])) {
    if (hash_equals(TOOL_PASSWORD, $_POST['password'])) {
        $_SESSION['rename_ok'] = true;
    } else {
        sleep(2);   // slows down anyone guessing passwords
        $err = 'Wrong password.';
    }
}
if (isset($_GET['logout'])) { unset($_SESSION['rename_ok']); }
$authed = !USE_PASSWORD
    || (!empty($_SESSION['rename_ok']) && TOOL_PASSWORD !== 'change-this-password');

// ---- folder handling: only folders inside this script's directory ----
function safe_dir($base, $rel) {
    $rel = trim(str_replace('\\', '/', $rel), '/');
    $path = $rel === '' ? $base : realpath($base . '/' . $rel);
    if ($path === false || !is_dir($path)) return false;
    if ($path !== $base && strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) return false;
    return $path;
}

function list_subdirs($base) {
    $out = [''];   // '' = the starting folder itself
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $it->setMaxDepth(FOLDER_DEPTH - 1);
    foreach ($it as $f) {
        if ($f->isDir() && $f->getFilename()[0] !== '.') {
            $out[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($base))), '/');
        }
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

// Turn the "find" text into a pattern. A * means "any run of letters, numbers,
// hyphens or underscores" and always stops at the period before the extension.
function find_pattern($find, $caseSensitive) {
    $parts = array_map(fn($p) => preg_quote($p, '/'), explode('*', $find));
    return '/' . implode('[A-Za-z0-9_\-]*', $parts) . '/' . ($caseSensitive ? '' : 'i');
}

function ext_of($name) {
    $dot = strrpos($name, '.');
    return $dot === false ? '' : strtolower(substr($name, $dot + 1));
}

function plan_renames($dir, $find, $replace, $append, $caseSensitive, $protectFront, $self, $allowed) {
    $plan = [];
    $files = scandir($dir);
    $existing = array_flip($files);
    $pattern = $find !== '' ? find_pattern($find, $caseSensitive) : null;

    foreach ($files as $name) {
        if ($name === '.' || $name === '..' || $name[0] === '.') continue;
        if (!is_file("$dir/$name") || $name === $self || $name === LOG_NAME) continue;
        $ext0 = ext_of($name);
        if ($ext0 === '' || in_array($ext0, NEVER_RENAME, true) || !in_array($ext0, $allowed, true)) continue;

        // work on the name only, never the extension
        $dot  = strrpos($name, '.');
        $stem = $dot === false ? $name : substr($name, 0, $dot);
        $ext  = $dot === false ? '' : substr($name, $dot);

        $newStem = $stem;
        $marked  = h($stem);
        $status  = 'ok';

        if ($pattern) {
            if (preg_match_all($pattern, $stem, $m, PREG_OFFSET_CAPTURE)) {
                $hits = array_filter($m[0], fn($x) => $x[0] !== '');
                if ($hits) {
                    $first = reset($hits);
                    if ($protectFront && $first[1] === 0) {
                        $status = 'at front of name — left alone';
                    } else {
                        $newStem = preg_replace($pattern, $replace, $stem);
                        $marked  = preg_replace_callback($pattern,
                            fn($x) => $x[0] === '' ? '' : '<span class="del">' . h($x[0]) . '</span>', $stem);
                        // re-escape the text between highlights
                        $marked = preg_replace_callback('/(<span class="del">.*?<\/span>)|([^<]+)/s',
                            fn($x) => $x[1] !== '' ? $x[1] : h($x[2]), $marked);
                    }
                }
            }
        }

        if ($append !== '' && $status === 'ok'
            && strtolower(substr($newStem, -strlen($append))) !== strtolower($append)) {
            $newStem .= $append;
        }

        $new = $newStem . $ext;
        if ($new === $name && $status === 'ok') continue;
        if ($status === 'ok') {
            if (trim($newStem) === '')         $status = 'would leave no name — left alone';
            elseif (isset($existing[$new]))    $status = 'name already exists';
        }
        $plan[] = ['old' => $name, 'new' => $status === 'ok' ? $new : $name,
                   'marked' => $marked . h($ext), 'status' => $status];
    }
    // two files that would end up with the same new name
    $counts = array_count_values(array_column(array_filter($plan, fn($p) => $p['status'] === 'ok'), 'new'));
    foreach ($plan as &$p) {
        if ($p['status'] === 'ok' && $counts[$p['new']] > 1) $p['status'] = 'two files would get this name';
    }
    return $plan;
}

// ---- inputs ----
$folder   = $_POST['folder']  ?? ($_GET['folder'] ?? '');
$find     = $_POST['find']    ?? '';
$replace  = $_POST['replace'] ?? '';
$append   = $_POST['append']  ?? '';
$caseSens = isset($_POST['case']) || !isset($_POST['action']);
$protect  = isset($_POST['protect']) || !isset($_POST['action']);
$otherExt = $_POST['other_ext'] ?? '';
if (isset($_POST['action'])) {
    $allowed = array_map('strtolower', (array)($_POST['ext'] ?? []));
} else {
    $allowed = [];
    foreach (DEFAULT_ON as $g) $allowed = array_merge($allowed, EXT_GROUPS[$g]);
}
$checkedBoxes = $allowed;
foreach (preg_split('/[\s,]+/', strtolower($otherExt), -1, PREG_SPLIT_NO_EMPTY) as $e) {
    $allowed[] = ltrim($e, '.*');
}
$allowed = array_values(array_diff(array_unique($allowed), NEVER_RENAME));
$action   = $_POST['action']  ?? '';
$plan     = [];
$dir      = $authed ? safe_dir($base, $folder) : false;

if ($authed && $action) {
    if ($dir === false) {
        $err = 'That folder isn\'t inside this site.';
    } elseif ($action !== 'preview' && !is_writable($dir)) {
        $err = 'The server won\'t let this script change files in that folder (folder permissions). Ask your host or set the folder to 755.';
    } elseif ($action === 'undo') {
        $logFile = "$dir/" . LOG_NAME;
        $log = is_file($logFile) ? json_decode(file_get_contents($logFile), true) : [];
        $last = $log ? array_pop($log) : null;
        if (!$last) {
            $err = 'Nothing to undo in this folder.';
        } else {
            $n = 0;
            foreach (array_reverse($last['renames']) as $r) {
                if (is_file("$dir/{$r['new']}") && !file_exists("$dir/{$r['old']}")
                    && rename("$dir/{$r['new']}", "$dir/{$r['old']}")) $n++;
            }
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg = "Undid $n rename(s) from {$last['time']}.";
        }
    } elseif (!$allowed) {
        $err = 'Tick at least one file type.';
    } elseif ($find === '' && $append === '') {
        $err = 'Type the text to find, or something to add to the end.';
    } elseif (preg_match('#[/\\\\]#', $replace . $find . $append)) {
        $err = 'Slashes aren\'t allowed in file names.';
    } else {
        $plan = plan_renames($dir, $find, $replace, $append, $caseSens, $protect, $self, $allowed);
        if ($action === 'rename') {
            $done = []; $failed = 0;
            foreach ($plan as $p) {
                if ($p['status'] !== 'ok') continue;
                if (rename("$dir/{$p['old']}", "$dir/{$p['new']}")) {
                    $done[] = ['old' => $p['old'], 'new' => $p['new']];
                } else $failed++;
            }
            if ($done) {
                $logFile = "$dir/" . LOG_NAME;
                $log = is_file($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
                $log[] = ['time' => date('Y-m-d H:i:s'), 'find' => $find, 'replace' => $replace, 'append' => $append, 'renames' => $done];
                file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            $skipped = count($plan) - count($done) - $failed;
            $msg = count($done) . " file(s) renamed." .
                   ($skipped ? " $skipped skipped (see reasons below)." : '') .
                   ($failed ? " $failed failed (check folder permissions)." : '');
            $plan = array_values(array_filter($plan, fn($p) => $p['status'] !== 'ok'));
        }
    }
}
$okCount = count(array_filter($plan, fn($p) => $p['status'] === 'ok'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h(TOOL_TITLE) ?></title>
<style>
  body { background:#111; color:#eee; font:18px/1.5 Georgia, serif; margin:0; padding:24px; }
  .wrap { max-width:900px; margin:0 auto; }
  h1 { font-weight:normal; margin-top:0; }
  label { display:block; margin:14px 0 4px; }
  input[type=text], input[type=password], select {
    width:100%; box-sizing:border-box; padding:10px; font-size:18px;
    background:#222; color:#fff; border:1px solid #555; border-radius:4px; }
  .row { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
  button { padding:10px 18px; font-size:17px; border:0; border-radius:4px; cursor:pointer; }
  .preview { background:#2d5d8a; color:#fff; }
  .go { background:#2e7d32; color:#fff; }
  .undo { background:#555; color:#fff; }
  .msg { background:#1e3d1e; padding:10px 14px; border-radius:4px; margin:16px 0; }
  .err { background:#5a1e1e; padding:10px 14px; border-radius:4px; margin:16px 0; }
  table { width:100%; border-collapse:collapse; margin-top:20px; font-size:15px; }
  td, th { padding:6px 8px; border-bottom:1px solid #333; text-align:left; word-break:break-all; }
  .bad { color:#f88; }
  .del { background:#6a2222; text-decoration:line-through; }
  .small { color:#999; font-size:14px; }
  a { color:#8cf; }
  fieldset.types { border:1px solid #444; border-radius:4px; margin-top:18px; padding:10px 14px; }
  .group { margin:8px 0; }
  label.inline { display:inline-block; margin:4px 14px 0 0; white-space:nowrap; }
</style>
</head>
<body><div class="wrap">
<h1><?= h(TOOL_TITLE) ?></h1>

<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

<?php if (!USE_PASSWORD): ?>
  <div class="err">Open version — no password. Anyone who finds this page can rename files.
  Delete it from the server as soon as you're done.</div>
<?php endif; ?>

<?php if (USE_PASSWORD && TOOL_PASSWORD === 'change-this-password'): ?>
  <div class="err">Open this file in a text editor and change TOOL_PASSWORD near the top before using it.</div>

<?php elseif (!$authed): ?>
  <form method="post">
    <label>Password</label>
    <input type="password" name="password" autofocus>
    <div class="row"><button class="preview">Enter</button></div>
  </form>

<?php else: ?>
  <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>
  <form method="post">
    <label>Folder</label>
    <select name="folder">
      <?php foreach (list_subdirs($base) as $d): ?>
        <option value="<?= h($d) ?>" <?= $d === trim($folder, '/') ? 'selected' : '' ?>><?= $d === '' ? '(this folder)' : h($d) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Find this text in file names</label>
    <input type="text" name="find" value="<?= h($find) ?>" placeholder="_IMG_*">
    <div class="small">Use * as a wild card for any letters, numbers, hyphens or underscores.
    It always stops at the period, so the extension (.jpg, .png…) is never changed.
    Example: <b>_IMG_*</b> removes _IMG_2600, _IMG_0042-2, _IMG_A7 and so on.</div>

    <label>Replace with <span class="small">(leave blank to delete it)</span></label>
    <input type="text" name="replace" value="<?= h($replace) ?>">

    <label>Add to end of name <span class="small">(optional, e.g. _by_geo_geller — goes before the extension, skipped if already there)</span></label>
    <input type="text" name="append" value="<?= h($append) ?>">

    <fieldset class="types">
      <legend>File types to rename</legend>
      <?php foreach (EXT_GROUPS as $group => $exts): ?>
        <div class="group"><b><?= h($group) ?>:</b>
          <a href="#" onclick="return tick(this,true)">all</a> /
          <a href="#" onclick="return tick(this,false)">none</a><br>
          <?php foreach ($exts as $e): ?>
            <label class="inline"><input type="checkbox" name="ext[]" value="<?= h($e) ?>"
              <?= in_array($e, $checkedBoxes, true) ? 'checked' : '' ?>> .<?= h($e) ?></label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <label>Other extensions <span class="small">(comma separated, e.g. psd, ai, srt)</span></label>
      <input type="text" name="other_ext" value="<?= h($otherExt) ?>">
      <div class="small" style="margin-top:8px">Never renamed, whatever is ticked:
        .<?= h(implode(' .', NEVER_RENAME)) ?> — and files with no extension.
        Careful with .htm/.html: renaming a page breaks any links pointing to it.</div>
    </fieldset>

    <label><input type="checkbox" name="case" <?= $caseSens ? 'checked' : '' ?>> Match upper/lower case exactly</label>
    <label><input type="checkbox" name="protect" <?= $protect ? 'checked' : '' ?>> Leave a file alone if the text is at the very start of its name (e.g. IMG_2600.jpg or IMG_2600_Title.jpg)</label>

    <div class="row">
      <button class="preview" name="action" value="preview">Preview</button>
      <?php if ($action === 'preview' && $okCount): ?>
        <button class="go" name="action" value="rename"
          onclick="return confirm('Rename <?= $okCount ?> file(s)?')">Rename <?= $okCount ?> file(s)</button>
      <?php endif; ?>
      <button class="undo" name="action" value="undo"
        onclick="return confirm('Undo the last rename batch in this folder?')">Undo last batch</button>
    </div>
  </form>

  <?php if ($action === 'preview' && !$plan && !$err): ?>
    <div class="msg">No file names in this folder contain that text.</div>
  <?php endif; ?>

  <?php if ($plan): ?>
    <table>
      <tr><th>Current name</th><th>New name</th><th></th></tr>
      <?php foreach ($plan as $p): ?>
        <tr>
          <td><?= $p['marked'] ?></td>
          <td><?= $p['status'] === 'ok' ? h($p['new']) : '—' ?></td>
          <td class="<?= $p['status'] === 'ok' ? '' : 'bad' ?>"><?= $p['status'] === 'ok' ? '✓' : h($p['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <p class="small" style="margin-top:30px">Only files directly in the chosen folder, of the ticked types, are renamed (not subfolders).
  Hidden files and this script are never touched. <?php if (USE_PASSWORD): ?><a href="?logout=1">Log out</a><?php endif; ?></p>
<?php endif; ?>
<p class="small" style="margin-top:40px;border-top:1px solid #333;padding-top:12px">
  Built by Geo Geller with Claude, a non-human child of Anthropic.</p>
</div>
<script>
function tick(link, on) {
  link.parentNode.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = on; });
  return false;
}
</script>
</body>
</html>
