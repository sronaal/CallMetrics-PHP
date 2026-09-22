/* ============================================================
   cdr.js — interacciones de la página CDR (Registro de Llamadas).
   Simulación 100% client-side:
   - Tabs: Llamadas / Colas / Agentes / Global.
   - Export CSV cliente-side (Blob + <a download>, cero dependencias).
   ============================================================ */

(function () {
  'use strict';

  function domReady(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  function showTab(targetId) {
    document.querySelectorAll('.cm-tab').forEach(function (t) {
      t.classList.toggle('active', t.getAttribute('data-target') === targetId);
    });
    document.querySelectorAll('.cm-tab-panel').forEach(function (p) {
      p.classList.toggle('d-none', p.id !== targetId);
    });
  }

  function getParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  domReady(function () {
    /* ---- Tab URL sync: si la URL trae ?tab=X, abrir ese tab ---- */
    var initialTab = getParam('tab');
    if (initialTab) {
      var targetId = 'cdrPanel' + initialTab.charAt(0).toUpperCase() + initialTab.slice(1);
      var el = document.getElementById(targetId);
      if (el) showTab(targetId);
    }

    /* ---- Tabs CDR ---- */
    document.querySelectorAll('.cm-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        showTab(tab.getAttribute('data-target'));
      });
    });

    /* ---- Export CSV: tabla #cdrTable a CSV ---- */
    var exportBtn = document.getElementById('cdrExportBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        var table = document.getElementById('cdrTable');
        if (!table) { return; }
        var rows = [];
        Array.prototype.forEach.call(table.querySelectorAll('thead tr, tbody tr'), function (tr) {
          var cells = [];
          Array.prototype.forEach.call(tr.querySelectorAll('th, td'), function (cell) {
            var txt = cell.textContent.replace(/\s+/g, ' ').trim();
            cells.push('"' + txt.replace(/"/g, '""') + '"');
          });
          rows.push(cells.join(','));
        });
        var csv = rows.join('\n');
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'cdr-export.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      });
    }
  });
})();