/* ============================================================
   landing.js — CallMetric Pro landing interactions
   ============================================================ */
(function () {
    'use strict';

    /* ---- Navbar scroll state ---- */
    var navbar = document.getElementById('landingNavbar');
    function onScroll() {
        if (!navbar) return;
        if (window.scrollY > 40) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---- Mobile menu ---- */
    var navToggle = document.getElementById('navbarToggle');
    var navMobile = document.getElementById('navbarMobile');
    var navIcon = document.getElementById('navbarToggleIcon');
    if (navToggle && navMobile) {
        navToggle.addEventListener('click', function () {
            var open = navMobile.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (navIcon) {
                navIcon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
            }
        });
    }

    /* ---- Count-up on view ---- */
    var easeOutCubic = function (p) {
        return 1 - Math.pow(1 - p, 3);
    };

    function formatValue(value, format) {
        switch (format) {
            case 'millions':
                return (value / 1e6).toFixed(1) + 'M+';
            case 'percent':
                return (value / 100).toFixed(2) + '%';
            case 'ms':
                return '<' + value + 'ms';
            case 'plus':
                return value + '+';
            default:
                return value.toLocaleString('es-ES');
        }
    }

    function runCountUp(el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        var duration = parseInt(el.getAttribute('data-duration'), 10) || 2000;
        var format = el.getAttribute('data-format') || '';
        var start = null;

        function step(ts) {
            if (start === null) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased = easeOutCubic(progress);
            var value = Math.floor(eased * target);
            el.textContent = formatValue(value, format);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        }

        window.requestAnimationFrame(step);
    }

    var statValues = document.querySelectorAll('.stat-value[data-target]');
    if ('IntersectionObserver' in window && statValues.length) {
        var statObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    runCountUp(entry.target);
                    statObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });
        statValues.forEach(function (el) {
            statObserver.observe(el);
        });
    } else {
        statValues.forEach(function (el) {
            el.textContent = formatValue(parseInt(el.getAttribute('data-target'), 10) || 0, el.getAttribute('data-format') || '');
        });
    }

    /* ---- Product tabs ---- */
    var productTabs = document.querySelectorAll('.product-tab');
    var productPanes = document.querySelectorAll('.product-pane');
    productTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var index = tab.getAttribute('data-tab');
            productTabs.forEach(function (t) {
                t.classList.toggle('active', t === tab);
            });
            productPanes.forEach(function (pane) {
                pane.classList.toggle('active', pane.getAttribute('data-pane') === index);
            });
        });
    });

    /* ---- Pricing billing toggle ---- */
    var billingBtns = document.querySelectorAll('.billing-btn');
    billingBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var billing = btn.getAttribute('data-billing');
            billingBtns.forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            document.querySelectorAll('.plan-price[data-annual][data-monthly]').forEach(function (price) {
                var value = billing === 'anual'
                    ? price.getAttribute('data-annual')
                    : price.getAttribute('data-monthly');
                price.textContent = '€' + value;
            });
        });
    });

    /* ---- Live counter ---- */
    var liveCounter = document.getElementById('liveCounter');
    if (liveCounter) {
        var count = 1247832;
        function tick() {
            count += Math.floor(Math.random() * 4) + 1;
            liveCounter.textContent = count.toLocaleString('es-ES');
        }
        setInterval(tick, 800);
    }
})();
