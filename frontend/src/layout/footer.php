    <!-- Bootstrap 5.3.3 JS bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    if (!empty($extraJs)) {
        foreach ((array) $extraJs as $js) {
            echo '<script src="' . htmlspecialchars($js) . '"></script>' . PHP_EOL;
        }
    }
    ?>
</body>
</html>
