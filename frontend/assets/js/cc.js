/* ============================================================
   cc.js — interacciones del módulo Call Center (Dashboard CC,
   Colas, Agentes CC). Simulación 100% client-side:
   - Filtro de búsqueda sobre tablas (colas / agentes CC).
   - Tabs del Dashboard CC (Activas / En Cola).
   - Autoscroll de la tabla de llamadas activas (pausable).
   ============================================================ */

(function () {
  'use strict';

  function domReady(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  domReady(function () {
    /* ---- Filtro de búsqueda genérico sobre un tbody dado ---- */
    function bindFilter(inputId, tbodyId, cellIndexes, noRowsId) {
      var input = document.getElementById(inputId);
      var tbody = document.getElementById(tbodyId);
      if (!input || !tbody) { return; }
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr[data-row]'), function (row) {
          var hit = false;
          cellIndexes.forEach(function (idx) {
            var cell = row.children[idx];
            if (cell && cell.textContent.toLowerCase().indexOf(q) !== -1) { hit = true; }
          });
          row.style.display = hit ? '' : 'none';
          if (hit) { visible++; }
        });
        var noRows = document.getElementById(noRowsId);
        if (noRows) { noRows.style.display = visible === 0 ? '' : 'none'; }
      });
    }

    bindFilter('ccQueueSearch', 'ccQueueRows', [0, 1, 4], 'ccQueueNoRows');
    bindFilter('ccAgentSearch', 'ccAgentRows', [0], 'ccAgentNoRows');

    /* ---- Tabs del Dashboard CC ---- */
    document.querySelectorAll('.cm-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-target');
        document.querySelectorAll('.cm-tab').forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        var panel = document.getElementById(target);
        if (panel) {
          document.querySelectorAll('.cm-tab-panel').forEach(function (p) { p.classList.add('d-none'); });
          panel.classList.remove('d-none');
        }
      });
    });

    /* ---- Autoscroll de llamadas activas (pausable) ---- */
    var scrollBox = document.getElementById('ccActiveScroll');
    var pauseBtn = document.getElementById('ccPauseScroll');
    if (scrollBox && pauseBtn) {
      var frozen = false;
      pauseBtn.addEventListener('click', function () {
        frozen = !frozen;
        pauseBtn.classList.toggle('active', frozen);
        pauseBtn.querySelector('i').className = frozen ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        if (!frozen) { scrollBox.scrollTop = scrollBox.scrollHeight; }
      });
    }
  });
})();