<?php $themeColor = setting('brand_color') ?: '#b3261e'; ?>
<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="<?= h($themeColor) ?>">
<meta name="application-name" content="Mailing B&amp;C">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Mailing B&amp;C">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('sw.php', { scope: './' }).catch(function () {}); });
}
</script>
