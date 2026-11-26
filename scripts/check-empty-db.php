<?php
// Fresh install guard. Supports MySQL/MariaDB only.
$url = parse_url((string) getenv('BC_DB_URL'));
if (!$url || !in_array($url['scheme'] ?? '', ['mysql','mysqli'], true) || empty($url['host']) || empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
  fwrite(STDERR, "FEJL: Kun mysql://user:pass@host/db understøttes.\n"); exit(1);
}
try {
  $db = rawurldecode(ltrim($url['path'], '/'));
  if (!preg_match('/^[A-Za-z0-9_]+$/D', $db) || !preg_match('/^[A-Za-z0-9.:-]+$/D', $url['host'])) { throw new RuntimeException(); }
  $pdo = new PDO('mysql:host='.$url['host'].';port='.($url['port']??3306).';dbname='.$db, rawurldecode($url['user']??''), rawurldecode($url['pass']??''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  if ($pdo->query('SHOW FULL TABLES')->fetch()) { fwrite(STDERR,"FEJL: Databasen er ikke tom.\n"); exit(1); }
} catch (Throwable) { fwrite(STDERR,"FEJL: Kunne ikke kontrollere tom database.\n"); exit(1); }
echo "OK: Tom database.\n";
