<?php
require SRC_PATH . '/layout/head.php';
?>

<!-- Fixed navbar -->
<nav class="landing-navbar" id="landingNavbar">
    <div class="landing-navbar-inner">
        <a class="navbar-logo" href="#hero" aria-label="CallMetric Pro — inicio">
            <span class="navbar-logo-box"><i class="bi bi-telephone-fill"></i></span>
            <span>
                <span class="navbar-logo-name">CallMetric</span>
                <span class="navbar-logo-pro">PRO</span>
            </span>
        </a>

        <div class="navbar-links">
            <a class="navbar-link" href="#producto">Producto</a>
            <a class="navbar-link" href="#features">Características</a>
            <a class="navbar-link" href="#precios">Precios</a>
            <a class="navbar-link" href="#recursos">Documentación</a>
        </div>

        <div class="navbar-cta">
            <a class="btn-nav-login" href="<?= BASE_URL ?>auth.php">Iniciar sesión</a>
            <a class="btn-nav-cta" href="<?= BASE_URL ?>auth.php">Comenzar gratis →</a>
        </div>

        <button class="navbar-toggle" id="navbarToggle" type="button" aria-label="Abrir menú" aria-expanded="false">
            <i class="bi bi-list" id="navbarToggleIcon"></i>
        </button>
    </div>

    <!-- Mobile panel -->
    <div class="navbar-mobile" id="navbarMobile">
        <a class="navbar-mobile-link" href="#producto">Producto</a>
        <a class="navbar-mobile-link" href="#features">Características</a>
        <a class="navbar-mobile-link" href="#precios">Precios</a>
        <a class="navbar-mobile-link" href="#recursos">Documentación</a>
        <a class="navbar-mobile-link" href="<?= BASE_URL ?>auth.php">Iniciar sesión</a>
        <a class="navbar-mobile-cta" href="<?= BASE_URL ?>auth.php">Comenzar gratis →</a>
    </div>
</nav>

<main>
<?= $content ?>
</main>

<!-- Footer -->
<footer class="landing-footer" id="recursos">
    <div class="landing-container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <span class="footer-brand-box"><i class="bi bi-telephone-fill"></i></span>
                    <span class="footer-brand-name">CallMetric Pro</span>
                </div>
                <p class="footer-tagline">Observabilidad empresarial para infraestructura PBX Asterisk y FreePBX.</p>
                <div class="footer-os">
                    <span class="footer-os-dot"></span>
                    <span>Todos los sistemas operativos</span>
                </div>
            </div>

            <div>
                <div class="footer-col-title">Producto</div>
                <a class="footer-link" href="#producto">Dashboard</a>
                <a class="footer-link" href="#producto">Alertas</a>
                <a class="footer-link" href="#producto">Reportes</a>
                <a class="footer-link" href="#">API</a>
                <a class="footer-link" href="#">Integraciones</a>
            </div>

            <div>
                <div class="footer-col-title">Empresa</div>
                <a class="footer-link" href="#">Sobre nosotros</a>
                <a class="footer-link" href="#">Blog</a>
                <a class="footer-link" href="#">Casos de éxito</a>
                <a class="footer-link" href="#">Partners</a>
                <a class="footer-link" href="#">Contacto</a>
            </div>

            <div>
                <div class="footer-col-title">Legal</div>
                <a class="footer-link" href="#">Privacidad</a>
                <a class="footer-link" href="#">Términos de uso</a>
                <a class="footer-link" href="#">GDPR / LOPD</a>
                <a class="footer-link" href="#">SLA</a>
                <a class="footer-link" href="#">Seguridad</a>
            </div>

            <div>
                <div class="footer-col-title">Recursos</div>
                <a class="footer-link" href="#">Documentación</a>
                <a class="footer-link" href="#">Status page</a>
                <a class="footer-link" href="#">Changelog</a>
                <a class="footer-link" href="#">Comunidad</a>
                <a class="footer-link" href="#">Soporte</a>
            </div>
        </div>

        <div class="footer-bottom">
            <span class="footer-copy">© 2026 CallMetric Pro. Todos los derechos reservados.</span>
            <span class="footer-heart">Hecho con ♥ para equipos de VoIP</span>
        </div>
    </div>
</footer>

<?php
require SRC_PATH . '/layout/footer.php';
?>
