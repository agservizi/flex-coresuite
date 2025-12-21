        </div>
    </main>
    <?php if (!empty($bottomNav)): ?>
        <nav class="app-bottombar border-top bg-body" aria-label="Navigazione principale">
            <div class="d-flex justify-content-around align-items-center py-2">
                <?php echo $bottomNav; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo asset_url('/assets/js/app.js'); ?>"></script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?php echo asset_url('/public/sw.js'); ?>')
    .then(registration => console.log('SW registered'))
    .catch(error => console.log('SW registration failed'));
}
</script>
</body>
</html>
