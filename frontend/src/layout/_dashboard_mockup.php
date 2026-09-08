<?php
/* ============================================================
   _dashboard_mockup.php — static dashboard preview (pure CSS/SVG).
   Reused by the landing hero and the product showcase.
   ============================================================ */

static $mockupInstance = 0;
$mockupInstance++;
$mockupUid = 'mm' . $mockupInstance;

$mockupAreaData = [12, 45, 78, 134, 198, 231, 247, 212, 189, 238, 225, 198, 142, 89];
$mockupAreaMax = 247;
$mockupAreaW = 300;
$mockupAreaH = 60;
$mockupAreaBase = 52;
$mockupAreaPts = [];
foreach ($mockupAreaData as $mockupI => $mockupV) {
    $mockupX = $mockupI * ($mockupAreaW / (count($mockupAreaData) - 1));
    $mockupY = $mockupAreaBase - ($mockupV / $mockupAreaMax) * 44;
    $mockupAreaPts[] = round($mockupX, 1) . ',' . round($mockupY, 1);
}
$mockupAreaLine = 'M' . implode(' L', $mockupAreaPts);
$mockupAreaFill = $mockupAreaLine . ' L' . $mockupAreaW . ',' . $mockupAreaBase . ' L0,' . $mockupAreaBase . ' Z';

$mockupBars = [
    ['a' => 187, 'e' => 23],
    ['a' => 134, 'e' => 8],
    ['a' => 98, 'e' => 15],
    ['a' => 203, 'e' => 31],
];
$mockupBarMax = 203;
$mockupBarBase = 52;
$mockupBarCols = count($mockupBars);

$mockupKpis = [
    ['label' => 'Activas', 'value' => '247', 'color' => '#4ade80'],
    ['label' => 'ASR', 'value' => '83.4%', 'color' => '#60a5fa'],
    ['label' => 'ACD', 'value' => '4:28', 'color' => '#fbbf24'],
    ['label' => 'Agentes', 'value' => '89/120', 'color' => '#60a5fa'],
];

$mockupRows = [
    ['origin' => '+34 612 345 678', 'dest' => 'Cola Ventas', 'dur' => '4:32', 'status' => 'Activa', 'color' => '#4ade80'],
    ['origin' => '+1 555 234 5678', 'dest' => 'Cola Soporte', 'dur' => '12:47', 'status' => 'En espera', 'color' => '#fbbf24'],
    ['origin' => '+34 654 789 012', 'dest' => 'Ext. 1042', 'dur' => '2:15', 'status' => 'Grabando', 'color' => '#f87171'],
];

$mockupNavItems = [
    ['label' => 'Dashboard', 'active' => true, 'badge' => null],
    ['label' => 'En Vivo', 'active' => false, 'badge' => null],
    ['label' => 'Salud PBX', 'active' => false, 'badge' => null],
    ['label' => 'Alertas', 'active' => false, 'badge' => 5],
];
?>
<div class="dashboard-mockup">
    <!-- Browser chrome -->
    <div class="mockup-chrome">
        <div class="mockup-chrome-dots">
            <span class="mockup-chrome-dot" style="background:#ef4444"></span>
            <span class="mockup-chrome-dot" style="background:#f59e0b"></span>
            <span class="mockup-chrome-dot" style="background:#22c55e"></span>
        </div>
        <div class="mockup-chrome-url">app.callmetric.pro/dashboard</div>
        <div style="width:60px"></div>
    </div>

    <div class="mockup-body">
        <!-- Mini sidebar -->
        <div class="mockup-sidebar">
            <div class="mockup-sidebar-brand">
                <span class="mockup-sidebar-brand-box"><i class="bi bi-telephone-fill"></i></span>
                <span class="mockup-sidebar-brand-name">CallMetric</span>
            </div>
            <?php foreach ($mockupNavItems as $mockupItem): ?>
                <div class="mockup-nav-item <?= $mockupItem['active'] ? 'active' : 'inactive' ?>">
                    <span class="mockup-nav-icon">
                        <?php
                        if ($mockupItem['label'] === 'Dashboard') {
                            echo '<i class="bi bi-bar-chart"></i>';
                        } elseif ($mockupItem['label'] === 'En Vivo') {
                            echo '<i class="bi bi-telephone"></i>';
                        } elseif ($mockupItem['label'] === 'Salud PBX') {
                            echo '<i class="bi bi-server"></i>';
                        } else {
                            echo '<i class="bi bi-bell"></i>';
                        }
                        ?>
                    </span>
                    <?= htmlspecialchars($mockupItem['label']) ?>
                    <?php if ($mockupItem['badge'] !== null): ?>
                        <span class="mockup-nav-badge"><?= (int) $mockupItem['badge'] ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Main -->
        <div class="mockup-main">
            <div class="mockup-title">Dashboard de Supervisor</div>

            <!-- KPIs -->
            <div class="mockup-kpis">
                <?php foreach ($mockupKpis as $mockupKpi): ?>
                    <div class="mockup-kpi">
                        <div class="mockup-kpi-label"><?= htmlspecialchars($mockupKpi['label']) ?></div>
                        <div class="mockup-kpi-value" style="color:<?= htmlspecialchars($mockupKpi['color']) ?>"><?= htmlspecialchars($mockupKpi['value']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Charts -->
            <div class="mockup-charts">
                <div class="mockup-chart-box">
                    <div class="mockup-chart-label">Concurrencia 24h</div>
                    <div class="mockup-chart">
                        <svg viewBox="0 0 <?= $mockupAreaW ?> <?= $mockupAreaH ?>" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="mockupAreaGradient_<?= $mockupUid ?>" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.35"></stop>
                                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <path d="<?= $mockupAreaFill ?>" fill="url(#mockupAreaGradient_<?= $mockupUid ?>)"></path>
                            <path d="<?= $mockupAreaLine ?>" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"></path>
                        </svg>
                    </div>
                </div>
                <div class="mockup-chart-box">
                    <div class="mockup-chart-label">Colas</div>
                    <div class="mockup-chart">
                        <svg viewBox="0 0 300 60" preserveAspectRatio="none" aria-hidden="true">
                            <?php foreach ($mockupBars as $mockupBarI => $mockupBar): ?>
                                <?php
                                $mockupGs = $mockupBarI * (300 / $mockupBarCols);
                                $mockupH = $mockupBarBase - ($mockupBar['a'] / $mockupBarMax) * 40;
                                $mockupH2 = $mockupBarBase - ($mockupBar['e'] / $mockupBarMax) * 40;
                                $mockupXA = round($mockupGs + 22.5, 1);
                                $mockupXE = round($mockupGs + 39.5, 1);
                                ?>
                                <rect x="<?= $mockupXA ?>" y="<?= $mockupH ?>" width="13" height="<?= $mockupBarBase - $mockupH ?>" rx="2" fill="#3b82f6"></rect>
                                <rect x="<?= $mockupXE ?>" y="<?= $mockupH2 ?>" width="13" height="<?= $mockupBarBase - $mockupH2 ?>" rx="2" fill="#f59e0b"></rect>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="mockup-table">
                <div class="mockup-table-head">
                    <span class="mockup-table-dot"></span>
                    Llamadas en Tiempo Real
                </div>
                <?php foreach ($mockupRows as $mockupRow): ?>
                    <div class="mockup-table-row">
                        <span class="mockup-table-origin"><?= htmlspecialchars($mockupRow['origin']) ?></span>
                        <span class="mockup-table-dest"><?= htmlspecialchars($mockupRow['dest']) ?></span>
                        <span class="mockup-table-dur"><?= htmlspecialchars($mockupRow['dur']) ?></span>
                        <span class="mockup-table-status" style="background:<?= htmlspecialchars($mockupRow['color']) ?>18;color:<?= htmlspecialchars($mockupRow['color']) ?>"><?= htmlspecialchars($mockupRow['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
