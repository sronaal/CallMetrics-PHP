<?php
require_once __DIR__ . '/../config.php';
$title = 'CallMetric Pro — Observabilidad PBX';
$activeNav = 'inicio';
$extraCss = [BASE_URL . 'assets/css/landing.css'];
$extraJs = [BASE_URL . 'assets/js/landing.js'];
ob_start();

/* ---------------- Data ---------------- */
$featureList = [
    ['icon' => 'bi-activity', 'title' => 'Monitoreo en Tiempo Real', 'tag' => 'Core', 'desc' => 'Dashboards actualizados cada segundo con métricas de ASR, ACD, concurrencia y estado de agentes en todos tus PBX.', 'color' => '#4f6ef7'],
    ['icon' => 'bi-bell', 'title' => 'Alertas Inteligentes', 'tag' => 'IA', 'desc' => 'Detección proactiva con reglas configurables y ML. Recibe alertas por Slack, email o webhook antes de que el problema escale.', 'color' => '#a78bfa'],
    ['icon' => 'bi-server', 'title' => 'Salud de Infraestructura', 'tag' => 'Infraestructura', 'desc' => 'Monitoriza CPU, RAM, latencia SIP y estado de trunks en tiempo real. Compatible con Asterisk, FreePBX y cualquier PABX SIP.', 'color' => '#10b981'],
    ['icon' => 'bi-bar-chart', 'title' => 'Reportes Avanzados', 'tag' => 'Analytics', 'desc' => 'Análisis histórico con hasta 2 años de retención. Exporta a PDF, Excel o conecta con tu BI via API REST.', 'color' => '#f59e0b'],
    ['icon' => 'bi-globe', 'title' => 'Multi-Tenant y Multi-PBX', 'tag' => 'Escala', 'desc' => 'Gestiona cientos de clientes y PBX desde una sola interfaz. Aislamiento total de datos y permisos granulares por rol.', 'color' => '#0ea5a0'],
    ['icon' => 'bi-shield-check', 'title' => 'Seguridad Enterprise', 'tag' => 'Seguridad', 'desc' => 'SOC 2 Type II, cifrado end-to-end, SSO con SAML 2.0, logs de auditoría y cumplimiento GDPR / LOPD incluidos.', 'color' => '#f43f5e'],
];

$productTabs = [
    [
        'label' => 'Dashboard en Vivo', 'icon' => 'bi-activity',
        'headline' => 'Visibilidad total, sin retrasos',
        'desc' => 'Dashboards configurables que se actualizan cada segundo. Agrupa PBX por región, cliente o tecnología. Comparte vistas con tu equipo con un solo clic.',
        'points' => ['Concurrencia de llamadas en tiempo real', 'Estado de colas y agentes', 'Métricas SIP: ASR, ACD, MOS'],
    ],
    [
        'label' => 'Sistema de Alertas', 'icon' => 'bi-bell',
        'headline' => 'Detecta antes de que fallen',
        'desc' => 'Define umbrales críticos, usa nuestras reglas de ML precalibradas o construye las tuyas. Notificaciones multicanal con correlación de eventos.',
        'points' => ['Alertas por reglas o ML', 'Slack, email, webhook, PagerDuty', 'Correlación de incidencias automática'],
    ],
    [
        'label' => 'Reportes Históricos', 'icon' => 'bi-bar-chart',
        'headline' => 'Convierte datos en decisiones',
        'desc' => 'Hasta 2 años de histórico con resolución por minuto. Genera reportes ejecutivos automáticos y exporta para tus reuniones de dirección.',
        'points' => ['Tendencias y comparativas de período', 'Exportación a PDF, Excel, CSV', 'Envío automático por email semanal'],
    ],
];

$testimonialList = [
    ['quote' => 'Reducimos el tiempo de detección de incidencias de 45 minutos a menos de 2 minutos. CallMetric Pro es indispensable para nuestro equipo de operaciones.', 'name' => 'María García', 'role' => 'CTO · Telecom Solutions LATAM', 'avatar' => 'MG', 'color' => '#4f6ef7'],
    ['quote' => 'Con 23 PBX distribuidos en 8 países, necesitábamos visibilidad centralizada. CallMetric Pro nos la dio en una tarde de implementación.', 'name' => 'Carlos Rodríguez', 'role' => 'Director de IT · Grupo Financiero Norte', 'avatar' => 'CR', 'color' => '#10b981'],
    ['quote' => 'El nivel de detalle en las métricas SIP es impresionante. Ninguna otra solución ofrece este grado de observabilidad para infraestructura Asterisk.', 'name' => 'Ana Martínez', 'role' => 'Arquitecta de Sistemas · Contact Center México', 'avatar' => 'AM', 'color' => '#a78bfa'],
];

$planList = [
    [
        'name' => 'Starter', 'color' => '#5a6d94', 'highlight' => false, 'badge' => null,
        'annual' => 79, 'monthly' => 99, 'custom' => false,
        'desc' => 'Para equipos pequeños que empiezan con monitoreo.',
        'features' => ['Hasta 5 agentes', '1 PBX', 'Alertas básicas por email', '30 días de histórico', 'Dashboard estándar'],
    ],
    [
        'name' => 'Professional', 'color' => '#4f6ef7', 'highlight' => true, 'badge' => 'Más popular',
        'annual' => 239, 'monthly' => 299, 'custom' => false,
        'desc' => 'El plan preferido por equipos de operaciones en crecimiento.',
        'features' => ['Hasta 50 agentes', 'Hasta 3 PBX', 'Alertas inteligentes + Slack/webhook', '90 días de histórico', 'Dashboards personalizados', 'API REST + acceso multi-usuario'],
    ],
    [
        'name' => 'Enterprise', 'color' => '#a78bfa', 'highlight' => false, 'badge' => null,
        'annual' => null, 'monthly' => null, 'custom' => true,
        'desc' => 'Solución completa para grandes organizaciones y MSP.',
        'features' => ['Agentes ilimitados', 'PBX ilimitados', 'Alertas con ML + correlación', 'Histórico ilimitado (2+ años)', 'SSO / SAML 2.0 + RBAC', 'SLA 99.99% · Soporte dedicado'],
    ],
];

$statsList = [
    ['target' => 1000000, 'duration' => 2000, 'format' => 'millions', 'label' => 'Llamadas procesadas al día', 'icon' => 'bi-telephone-inbound', 'color' => '#4f6ef7'],
    ['target' => 9999, 'duration' => 2200, 'format' => 'percent', 'label' => 'Uptime garantizado por SLA', 'icon' => 'bi-shield-check', 'color' => '#10b981'],
    ['target' => 50, 'duration' => 1800, 'format' => 'ms', 'label' => 'Tiempo de alerta promedio', 'icon' => 'bi-lightning-charge', 'color' => '#f59e0b'],
    ['target' => 500, 'duration' => 2000, 'format' => 'plus', 'label' => 'Empresas en producción', 'icon' => 'bi-people', 'color' => '#a78bfa'],
];

$logoList = [
    'Telecom Solutions', 'Grupo Financiero Norte', 'Contact Center MX',
    'Infraestructura TI LATAM', 'VoIP Networks SA', 'CX Enterprise Group',
    'Soluciones PBX Colombia', 'TelcoHub Argentina',
];
?>

<!-- ================= HERO ================= -->
<section class="hero" id="hero">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-blue"></div>
    <div class="hero-orb hero-orb-cyan"></div>
    <div class="hero-orb hero-orb-green"></div>

    <div class="hero-badge hero-badge-a">
        <div class="hero-badge-box">
            <div class="hero-badge-label">Llamadas activas</div>
            <div class="hero-badge-value"><span class="hero-dot green"></span>247</div>
        </div>
    </div>
    <div class="hero-badge hero-badge-b">
        <div class="hero-badge-box">
            <div class="hero-badge-label">Alerta crítica</div>
            <div class="hero-badge-value red"><i class="bi bi-bell"></i> Cola saturada</div>
        </div>
    </div>
    <div class="hero-badge hero-badge-c">
        <div class="hero-badge-box">
            <div class="hero-badge-label">ASR 24h</div>
            <div class="hero-badge-value cyan">83.4%</div>
        </div>
    </div>

    <div class="hero-inner">
        <div class="hero-pill">
            <span class="hero-pill-chip">Nuevo</span>
            Integración nativa con Asterisk 20.x &amp; FreePBX 17
            <i class="bi bi-chevron-right hero-pill-chev"></i>
        </div>

        <h1 class="hero-title">
            Observabilidad total<br>
            para tu infraestructura <span class="gradient-text-pbx">PBX</span>
        </h1>

        <p class="hero-sub">Monitoriza en tiempo real cada llamada, agente y cola de tu sistema Asterisk/FreePBX. Detecta problemas antes de que impacten a tus clientes.</p>

        <div class="hero-cta">
            <a class="btn-hero-primary" href="<?= BASE_URL ?>auth.php">Comenzar gratis <i class="bi bi-arrow-right"></i></a>
            <a class="btn-hero-secondary" href="<?= BASE_URL ?>dashboard.php"><i class="bi bi-telephone-inbound"></i> Ver demo en vivo</a>
        </div>

        <div class="hero-live">
            <span class="hero-dot green"></span>
            <span class="live-counter font-mono" id="liveCounter">1.247.832</span>
            <span>llamadas procesadas hoy · 0 instalación requerida</span>
        </div>
    </div>

    <div class="hero-mockup-wrap">
        <div class="hero-mockup-fade"></div>
        <?php require SRC_PATH . '/layout/_dashboard_mockup.php'; ?>
    </div>
</section>

<!-- ================= LOGO STRIP ================= -->
<section class="logo-strip">
    <div class="landing-container">
        <p class="logo-strip-label">Utilizado por equipos de +500 empresas en LATAM</p>
    </div>
    <div class="marquee-track" aria-hidden="true">
        <?php for ($logoI = 0; $logoI < 16; $logoI++): ?>
            <?php $logoName = $logoList[$logoI % count($logoList)]; ?>
            <div class="marquee-item">
                <span class="marquee-item-icon"><i class="bi bi-globe2"></i></span>
                <span class="marquee-item-name"><?= htmlspecialchars($logoName) ?></span>
            </div>
        <?php endfor; ?>
    </div>
</section>

<!-- ================= STATS ================= -->
<section class="section section-bg-gradient" id="stats">
    <div class="landing-container">
        <div class="section-head">
            <h2>Los números lo dicen todo</h2>
            <p>Infraestructura diseñada para escalar con tu negocio</p>
        </div>
        <div class="stats-grid">
            <?php foreach ($statsList as $stat): ?>
                <div class="stat-card" style="--stat:<?= htmlspecialchars($stat['color']) ?>">
                    <div class="stat-accent"></div>
                    <div class="stat-icon" style="background:<?= htmlspecialchars($stat['color']) ?>18">
                        <i class="bi <?= htmlspecialchars($stat['icon']) ?>" style="color:<?= htmlspecialchars($stat['color']) ?>"></i>
                    </div>
                    <span class="stat-value" data-target="<?= (int) $stat['target'] ?>" data-duration="<?= (int) $stat['duration'] ?>" data-format="<?= htmlspecialchars($stat['format']) ?>">0</span>
                    <span class="stat-label"><?= htmlspecialchars($stat['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= FEATURES ================= -->
<section class="section section-bg-solid" id="features">
    <div class="landing-container">
        <div class="section-head">
            <span class="section-eyebrow"><i class="bi bi-stars"></i> Características</span>
            <h2>Todo lo que tu equipo necesita</h2>
            <p>Desde métricas en tiempo real hasta análisis histórico profundo, una sola plataforma.</p>
        </div>
        <div class="features-grid">
            <?php foreach ($featureList as $feature): ?>
                <div class="feature-card" style="--fc:<?= htmlspecialchars($feature['color']) ?>">
                    <div class="feature-head">
                        <div class="feature-icon" style="background:<?= htmlspecialchars($feature['color']) ?>18">
                            <i class="bi <?= htmlspecialchars($feature['icon']) ?>"></i>
                        </div>
                        <span class="feature-tag" style="background:<?= htmlspecialchars($feature['color']) ?>15"><?= htmlspecialchars($feature['tag']) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($feature['title']) ?></h3>
                    <p><?= htmlspecialchars($feature['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= PRODUCT ================= -->
<section class="section section-bg-gradient" id="producto">
    <div class="landing-container">
        <div class="section-head">
            <h2>Diseñado para supervisores exigentes</h2>
            <p>Potente sin ser complejo. Productivo desde el primer día.</p>
        </div>

        <div class="product-tabs" role="tablist">
            <?php foreach ($productTabs as $tabIndex => $tab): ?>
                <button class="product-tab <?= $tabIndex === 0 ? 'active' : '' ?>" type="button" data-tab="<?= $tabIndex ?>" role="tab" aria-selected="<?= $tabIndex === 0 ? 'true' : 'false' ?>">
                    <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i>
                    <?= htmlspecialchars($tab['label']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="product-body">
            <div class="product-panes">
                <?php foreach ($productTabs as $tabIndex => $tab): ?>
                    <div class="product-pane <?= $tabIndex === 0 ? 'active' : '' ?>" data-pane="<?= $tabIndex ?>" role="tabpanel">
                        <h3><?= htmlspecialchars($tab['headline']) ?></h3>
                        <p class="product-pane-desc"><?= htmlspecialchars($tab['desc']) ?></p>
                        <ul class="product-points">
                            <?php foreach ($tab['points'] as $point): ?>
                                <li class="product-point">
                                    <span class="product-point-check"><i class="bi bi-check"></i></span>
                                    <span><?= htmlspecialchars($point) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn-product" href="<?= BASE_URL ?>dashboard.php">Ver en acción <i class="bi bi-arrow-right"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="product-visual">
                <div class="product-glow"></div>
                <?php require SRC_PATH . '/layout/_dashboard_mockup.php'; ?>
            </div>
        </div>
    </div>
</section>

<!-- ================= TESTIMONIALS ================= -->
<section class="section section-bg-solid" id="testimonios">
    <div class="landing-container">
        <div class="section-head">
            <div class="testimonial-stars" style="justify-content:center;margin-bottom:20px">
                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
            </div>
            <h2>Lo que dicen nuestros clientes</h2>
            <p>4.9/5 estrellas · Más de 300 reseñas verificadas</p>
        </div>
        <div class="testimonials-grid">
            <?php foreach ($testimonialList as $testimonial): ?>
                <div class="testimonial-card" style="--tc:<?= htmlspecialchars($testimonial['color']) ?>">
                    <div class="testimonial-accent"></div>
                    <div class="testimonial-stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-quote">&ldquo;<?= htmlspecialchars($testimonial['quote']) ?>&rdquo;</p>
                    <div class="testimonial-author">
                        <span class="testimonial-avatar"><?= htmlspecialchars($testimonial['avatar']) ?></span>
                        <span>
                            <span class="testimonial-name d-block"><?= htmlspecialchars($testimonial['name']) ?></span>
                            <span class="testimonial-role d-block"><?= htmlspecialchars($testimonial['role']) ?></span>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= PRICING ================= -->
<section class="section section-bg-gradient" id="precios">
    <div class="landing-container">
        <div class="section-head">
            <h2>Precios simples y transparentes</h2>
            <p style="margin-bottom:32px">Sin sorpresas. Cancela cuando quieras.</p>
            <div class="billing-toggle" role="group" aria-label="Período de facturación">
                <button class="billing-btn" type="button" data-billing="mensual" aria-pressed="false">Mensual</button>
                <button class="billing-btn active" type="button" data-billing="anual" aria-pressed="true">
                    Anual <span class="billing-badge">−20%</span>
                </button>
            </div>
        </div>

        <div class="plans-grid">
            <?php foreach ($planList as $plan): ?>
                <div class="plan-card <?= $plan['highlight'] ? 'highlight' : '' ?>" style="--pc:<?= htmlspecialchars($plan['color']) ?>">
                    <?php if ($plan['highlight']): ?>
                        <div class="plan-accent"></div>
                    <?php endif; ?>
                    <?php if ($plan['badge']): ?>
                        <span class="plan-badge"><?= htmlspecialchars($plan['badge']) ?></span>
                    <?php endif; ?>

                    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>

                    <?php if ($plan['custom']): ?>
                        <div class="plan-price-custom">A medida</div>
                    <?php else: ?>
                        <div class="plan-price-row">
                            <span class="plan-price" data-annual="<?= (int) $plan['annual'] ?>" data-monthly="<?= (int) $plan['monthly'] ?>">€<?= (int) $plan['annual'] ?></span>
                            <span class="plan-price-suffix">/mes</span>
                        </div>
                    <?php endif; ?>

                    <p class="plan-desc"><?= htmlspecialchars($plan['desc']) ?></p>

                    <a class="plan-cta <?= $plan['highlight'] ? 'highlight' : '' ?>" href="<?= BASE_URL ?>auth.php">
                        <?= $plan['custom'] ? 'Hablar con ventas' : 'Empezar ahora' ?>
                    </a>

                    <ul class="plan-features">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li class="plan-feature">
                                <span class="plan-feature-check"><i class="bi bi-check"></i></span>
                                <span><?= htmlspecialchars($feature) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= CTA ================= -->
<section class="cta-section">
    <div class="cta-glow"></div>
    <div class="cta-inner">
        <div class="cta-icon"><i class="bi bi-lightning-charge-fill"></i></div>
        <h2 class="cta-title">
            Empieza a monitorear<br>
            <span class="cta-title-gradient">en 5 minutos</span>
        </h2>
        <p class="cta-body">
            Conecta tu primer PBX Asterisk y empieza a ver métricas en tiempo real.<br>
            Sin tarjeta de crédito. Sin contratos. Sin complicaciones.
        </p>
        <div class="cta-buttons">
            <a class="btn-cta-primary" href="<?= BASE_URL ?>auth.php">Crear cuenta gratis <i class="bi bi-arrow-right"></i></a>
            <a class="btn-cta-secondary" href="#"><i class="bi bi-telephone-inbound"></i> Hablar con ventas</a>
        </div>
        <p class="cta-trust">✓ Trial gratuito 14 días &nbsp;·&nbsp; ✓ Soporte en español &nbsp;·&nbsp; ✓ Sin permanencia</p>
    </div>
</section>

<?php
$content = ob_get_clean();
require SRC_PATH . '/layout/public.php';
?>
