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

  domReady(function () {
    /* ---- Tabs CDR ---- */
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