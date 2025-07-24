<?php
// Load Bootstrap Icons only once if not already loaded
if (!defined('BOOTSTRAP_ICONS_INCLUDED')) {
  define('BOOTSTRAP_ICONS_INCLUDED', true);
  echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">';
}
?>

<a href="../pages/dashboard.php" class="btn btn-outline-secondary mb-3">
  <i class="bi bi-arrow-left"></i> Back to Dashboard
</a>
