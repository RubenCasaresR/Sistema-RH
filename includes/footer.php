<?php if (ob_get_level()) { ob_end_flush(); } ?>
<?php if (isLoggedIn()): ?>
    </main>
</div> <!-- .app-layout -->
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>"></script>
<?php if (isset($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?= APP_URL ?>/assets/js/<?= $js ?>.js?v=<?= APP_VERSION ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-open').forEach(function(m) { m.classList.remove('modal-open'); });
        document.querySelectorAll('#deleteModal').forEach(function(m) { if (m.style.display === 'flex') m.style.display = 'none'; });
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        var form = e.target.closest('form');
        if (form) { var btn = form.querySelector('button[type="submit"]'); if (btn) btn.click(); }
    }
});
</script>
</body>
</html>
