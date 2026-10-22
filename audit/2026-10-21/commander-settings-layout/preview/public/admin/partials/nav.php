<?php
/** @var string $activePage */
$activePage = $activePage ?? '';
$topbarExtraHtml = $topbarExtraHtml ?? '';
$navItems = [
    'maintenance' => ['label' => 'Maintenance',  'href' => 'maintenance.php'],
    'dashboard'   => ['label' => 'Siti',         'href' => 'index.php?view=sites'],
    'licenses'    => ['label' => 'Licenze',      'href' => 'licenses.php'],
    'analytics'   => ['label' => 'Analytics',    'href' => 'analytics.php'],
    'woocommerce' => ['label' => 'WooCommerce',  'href' => 'woocommerce.php'],
    'import'      => ['label' => 'Importa',      'href' => 'import.php'],
    'settings'    => ['label' => 'Impostazioni', 'href' => 'settings.php'],
];
$adminCssPath = dirname(__DIR__, 2) . '/assets/css/admin.css';
$adminCssVersion = is_file($adminCssPath) ? (string) filemtime($adminCssPath) : '1';
?>
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= e($adminCssVersion) ?>">
<div class="mc-topbar">
    <div class="brand">Marrison Commander</div>
    <?php if ($topbarExtraHtml !== ''): ?>
        <?= $topbarExtraHtml ?>
    <?php endif; ?>
    <div class="mc-nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= e($item['href']) ?>" class="<?= $activePage === $key ? 'active' : '' ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
        <a href="logout.php" class="mc-logout">Logout</a>
    </div>
</div>
