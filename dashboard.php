<?php
/**
 * Dashboard placeholder (admin and cashier). The full dashboard is built on Day 7.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle = t('js_dashboard') . ' - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <h1 class="text-2xl font-bold text-[#1B4D3E]"><?= e(t('js_dashboard')) ?></h1>
    <p class="text-sm text-gray-600 mt-1"><?= e(t('dashboard_welcome')) ?>, <?= e($_SESSION['name']) ?>.</p>
    <p class="text-sm text-gray-500 mt-4"><?= e(t('dashboard_soon')) ?></p>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
