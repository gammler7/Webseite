<?php
/**
 * Interne Besuchsübersicht (Passwortschutz).
 * Nicht in die Navigation einbinden; URL nur für dich merken.
 * Optional Datei umbenennen (z. B. intern-7f3a-logs.php), damit die URL schwerer zu raten ist.
 */
header('X-Robots-Tag: noindex, nofollow');

$configPath = __DIR__ . '/private/log_config.php';
if (!is_file($configPath)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Logs</title></head><body>';
    echo '<p>Bitte <code>private/log_config.example.php</code> nach <code>private/log_config.php</code> kopieren und ein Passwort eintragen.</p>';
    echo '</body></html>';
    exit;
}

$config = require $configPath;
$password = isset($config['admin_password']) ? (string) $config['admin_password'] : '';

session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: logs.php');
    exit;
}

if (isset($_POST['password']) && $password !== '') {
    if (hash_equals($password, (string) $_POST['password'])) {
        $_SESSION['besuch_log_ok'] = true;
    }
}

$authorized = !empty($_SESSION['besuch_log_ok']);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Besuchsprotokoll</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0b0b0f; color: #e5e7eb; margin: 0; padding: 1.25rem; }
    h1 { font-size: 1.25rem; margin: 0 0 1rem; }
    form { margin-bottom: 1rem; }
    input[type="password"] { padding: 0.5rem 0.65rem; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #fff; width: min(280px, 100%); }
    button { padding: 0.5rem 1rem; border-radius: 8px; border: none; background: #3b82f6; color: #fff; font-weight: 600; cursor: pointer; }
    button.secondary { background: #374151; margin-left: 0.5rem; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th, td { text-align: left; padding: 0.45rem 0.5rem; border-bottom: 1px solid #27272a; vertical-align: top; word-break: break-word; }
    th { color: #9ca3af; font-weight: 600; }
    .filter { margin: 1rem 0; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .filter input[type="text"] { padding: 0.45rem 0.6rem; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #fff; width: min(360px, 100%); }
    .hint { color: #9ca3af; font-size: 0.8rem; margin-top: 1.5rem; max-width: 48rem; line-height: 1.5; }
  </style>
</head>
<body>
<?php if (!$authorized): ?>
  <h1>Besuchsprotokoll</h1>
  <form method="post" action="">
    <label for="password">Passwort</label><br />
    <input type="password" id="password" name="password" required autocomplete="current-password" />
    <button type="submit">Anmelden</button>
  </form>
<?php else:
    $logFile = __DIR__ . '/private/visits.log';
    $filter = isset($_GET['besucher']) ? trim((string) $_GET['besucher']) : '';
    ?>
  <h1>Besuchsprotokoll</h1>
  <p><a href="?logout=1" style="color:#93c5fd;">Abmelden</a></p>
  <form class="filter" method="get" action="">
    <label for="besucher">Besucher-ID filtern</label>
    <input type="text" id="besucher" name="besucher" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Teil der ID" />
    <button type="submit">Filtern</button>
    <?php if ($filter !== ''): ?>
      <a href="logs.php" style="color:#93c5fd;">Filter zurücksetzen</a>
    <?php endif; ?>
  </form>
  <?php
  if (!is_readable($logFile)) {
      echo '<p>Noch keine Einträge (oder Datei <code>private/visits.log</code> fehlt).</p>';
  } else {
      $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      if ($lines === false || count($lines) === 0) {
          echo '<p>Noch keine Einträge.</p>';
      } else {
          $lines = array_reverse($lines);
          $rows = [];
          foreach ($lines as $line) {
              $row = json_decode($line, true);
              if (!is_array($row)) {
                  continue;
              }
              $vid = isset($row['visitor']) ? (string) $row['visitor'] : '';
              if ($filter !== '' && strpos($vid, $filter) === false) {
                  continue;
              }
              $rows[] = $row;
          }
          if (count($rows) === 0) {
              echo '<p>Keine Treffer für diesen Filter.</p>';
          } else {
              echo '<table><thead><tr><th>Zeit (UTC)</th><th>Besucher-ID</th><th>Seite</th><th>IP</th><th>Browser (Auszug)</th></tr></thead><tbody>';
              foreach ($rows as $row) {
                  $t = isset($row['t']) ? htmlspecialchars((string) $row['t'], ENT_QUOTES, 'UTF-8') : '';
                  $v = isset($row['visitor']) ? htmlspecialchars((string) $row['visitor'], ENT_QUOTES, 'UTF-8') : '';
                  $p = isset($row['page']) ? htmlspecialchars((string) $row['page'], ENT_QUOTES, 'UTF-8') : '';
                  $ip = isset($row['ip']) ? htmlspecialchars((string) $row['ip'], ENT_QUOTES, 'UTF-8') : '';
                  $ua = isset($row['ua']) ? htmlspecialchars(substr((string) $row['ua'], 0, 80), ENT_QUOTES, 'UTF-8') : '';
                  echo '<tr><td>' . $t . '</td><td>' . $v . '</td><td>' . $p . '</td><td>' . $ip . '</td><td>' . $ua . '</td></tr>';
              }
              echo '</tbody></table>';
          }
      }
  }
  ?>
  <p class="hint">
    Die Besucher-ID stammt aus dem Cookie <code>webseite_besuch_id</code> (bzw. LocalStorage-Fallback).
    IP-Adressen können personenbezogen sein; speichern Sie nur, was Ihrer Datenschutzerklärung entspricht.
    Die Datei <code>private/visits.log</code> ist per <code>.htaccess</code> nicht direkt aus dem Web abrufbar; die Seite <code>logs.php</code> ist nur durch das Passwort geschützt (URL trotzdem merken oder Datei umbenennen).
  </p>
<?php endif; ?>
</body>
</html>
