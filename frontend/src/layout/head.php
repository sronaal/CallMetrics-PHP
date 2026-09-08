<?php
$headTitle = htmlspecialchars($title ?? APP_NAME);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $headTitle ?></title>

    <!-- Fonts: Inter / JetBrains Mono / Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Design system -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">

    <?php
    if (!empty($extraCss)) {
        foreach ((array) $extraCss as $css) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">' . PHP_EOL;
        }
    }
    ?>
</head>
<body>
