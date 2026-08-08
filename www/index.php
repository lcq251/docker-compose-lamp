<?php
$db = null;
$dbError = '';
try {
    $db = new PDO(
        'mysql:host=database;dbname=' . ($_ENV['MYSQL_DATABASE'] ?? 'docker') . ';charset=utf8mb4',
        $_ENV['MYSQL_USER'] ?? 'docker',
        $_ENV['MYSQL_PASSWORD'] ?? 'docker',
        [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dbVersion = $db->query('SELECT VERSION()')->fetchColumn();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$projects = array_values(array_filter(
    glob(__DIR__ . '/*', GLOB_ONLYDIR) ?: [],
    fn($p) => !str_starts_with(basename($p), '.')
));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LAMP Stack - localhost</title>
<link rel="stylesheet" href="/dash/assets/css/bulma.min.css">
</head>
<body>
<section class="hero is-medium is-info is-bold">
  <div class="hero-body">
    <div class="container has-text-centered">
      <h1 class="title">LAMP Stack</h1>
      <h2 class="subtitle"><?= apache_get_version() ?> &middot; PHP <?= phpversion() ?></h2>
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="columns">
      <div class="column">
        <h3 class="title is-4">服务状态</h3>
        <table class="table is-striped is-fullwidth">
          <tr><th>Web 服务</th><td><span class="tag is-success">运行中</span></td></tr>
          <tr><th>PHP</th><td><?= phpversion() ?></td></tr>
          <tr><th>MySQL</th>
            <td><?= $db
                ? '<span class="tag is-success">连接正常 (' . htmlspecialchars($dbVersion) . ')</span>'
                : '<span class="tag is-danger">连接失败</span><br><small>' . htmlspecialchars($dbError) . '</small>' ?></td>
          </tr>
        </table>
      </div>
      <div class="column">
        <h3 class="title is-4">入口</h3>
        <ul>
          <li><a href="/dash/">LAMP 仪表盘 /dash/</a></li>
          <li><a href="/dash/phpinfo.php">PHP 信息 /dash/phpinfo.php</a></li>
          <li><a href="/dash/test_db.php">数据库测试 /dash/test_db.php</a></li>
        </ul>
        <h3 class="title is-4">项目目录</h3>
        <?php if ($projects): ?>
        <ul><?php foreach ($projects as $p): ?><li><a href="/<?= rawurlencode(basename($p)) ?>/">/<?= htmlspecialchars(basename($p)) ?>/</a></li><?php endforeach; ?></ul>
        <?php else: ?><p class="has-text-grey">（暂无子项目，www/ 下新建目录即可出现）</p><?php endif; ?>
      </div>
    </div>
  </div>
</section>
</body>
</html>
