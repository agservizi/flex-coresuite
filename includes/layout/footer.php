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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
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
