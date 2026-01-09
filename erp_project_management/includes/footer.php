<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="container text-center">
      <small class="small muted">&copy; <?php echo date('Y'); ?> iServe. All rights reserved.</small>
    </div>
  </div>
  <!-- Minimal theme: load last so it can override global styles -->
  <?php $BASE_URL = $BASE_URL ?? '/church_system-main_m'; ?>
  <link rel="stylesheet" href="<?php echo $BASE_URL; ?>/css/minimal.css" />
</footer> 