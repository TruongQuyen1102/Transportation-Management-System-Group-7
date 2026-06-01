  </main><!-- /page-content -->

  <footer class="app-footer">
    <span>© 2025 <strong>LogiTrack Pro</strong> — Logistics Management System</span>
    <span>v1.0.0 &nbsp;|&nbsp; <?= date('d M Y, H:i') ?></span>
  </footer>
</div><!-- /main-content -->

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- App JS -->
<script src="<?= (defined('BASE_URL') ? BASE_URL : '') . '/assets/js/app.js' ?>"></script>
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $s): ?>
    <script><?= $s ?></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
