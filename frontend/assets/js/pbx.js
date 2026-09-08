/* ============================================================
   pbx.js — interacciones de la página Centrales PBX.

   Simulación 100% client-side (sin backend):
   - Validación del formulario de registro (requeridos + puerto 1-65535).
   - Tras validar, genera ID/TOKEN/AGENT y muestra el modal de
     configuración del agente de monitoreo.
   - Botones de copiado (con fallback si Clipboard API no está en
     contexto seguro).
   - Borrado de fila con confirmación.
   ============================================================ */

(function () {
  'use strict';

  function domReady(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function randomId(prefix) {
    return prefix + '-' + Math.random().toString(16).slice(2, 10);
  }

  function copyText(text, done) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); if (done) done(); });
    } else {
      fallbackCopy(text);
      if (done) done();
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(ta);
  }

  domReady(function () {
    var form = document.getElementById('pbxCreateForm');
    var createModalEl = document.getElementById('pbxCreateModal');
    var createModal = bootstrap.Modal.getOrCreateInstance(createModalEl);
    var revealModalEl = document.getElementById('pbxRevealModal');
    var revealModal = bootstrap.Modal.getOrCreateInstance(revealModalEl);
    var tbody = document.getElementById('pbxTableRows');

    /* ---- Registro + validación ---- */
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();

      var nombre = document.getElementById('pbxNombre');
      var host = document.getElementById('pbxHost');
      var puerto = document.getElementById('pbxPuerto');
      var ok = true;

      [nombre, host, puerto].forEach(function (input) {
        input.classList.remove('is-invalid');
      });

      if (!nombre.value.trim() || nombre.value.trim().length < 2) {
        nombre.classList.add('is-invalid');
        ok = false;
      }
      if (!host.value.trim()) {
        host.classList.add('is-invalid');
        ok = false;
      }
      var port = parseInt(puerto.value, 10);
      if (isNaN(port) || port < 1 || port > 65535) {
        puerto.classList.add('is-invalid');
        ok = false;
      }

      if (!ok) {
        return;
      }

      /* Genera credenciales del agente */
      var pbxId = randomId('pbx');
      var token = 'cmtk_' + randomId('token').slice(3);
      var agenteId = 'cmag_' + randomId('agent').slice(4);

      /* Inserta fila simulada al inicio de la tabla */
      var nuevaFila = document.createElement('tr');
      nuevaFila.setAttribute('data-pbx-id', pbxId);
      nuevaFila.innerHTML =
        '<td class="cell-dest fw-semibold">' + escapeHtml(nombre.value.trim()) + '</td>' +
        '<td class="cell-origin">' + escapeHtml(host.value.trim()) + '</td>' +
        '<td class="cell-pbx">' + port + '</td>' +
        '<td><span class="cm-badge cm-badge-ok">En línea</span></td>' +
        '<td class="cell-origin">—</td>' +
        '<td class="cell-duration live">ahora</td>' +
        '<td><div class="cm-row-actions">' +
          '<a class="cm-row-action" href="#" title="Ver detalle"><i class="bi bi-eye"></i></a>' +
          '<button class="cm-row-action danger" type="button" title="Eliminar" ' +
            'data-bs-toggle="modal" data-bs-target="#pbxDeleteModal" ' +
            'data-pbx-name="' + escapeHtml(nombre.value.trim()) + '">' +
            '<i class="bi bi-trash"></i></button></div></td>';
      tbody.insertBefore(nuevaFila, tbody.firstChild);
      var vacio = document.getElementById('pbxNoRows');
      if (vacio) { vacio.remove(); }

      /* Llena y muestra el modal de credenciales */
      document.getElementById('revealPbxId').textContent = pbxId;
      document.getElementById('revealToken').textContent = token;
      document.getElementById('revealAgente').textContent = agenteId;
      createModal.hide();
      revealModal.show();

      form.reset();
    });

    function escapeHtml(value) {
      var div = document.createElement('div');
      div.textContent = value;
      return div.innerHTML;
    }

    /* ---- Copiado individual + copiar todo ---- */
    var copiarTodoBtn = document.getElementById('copiarTodo');
    if (copiarTodoBtn) {
      copiarTodoBtn.addEventListener('click', function () {
        var texto =
          'AGENT_ID=' + document.getElementById('revealAgente').textContent +
          '\nTOKEN_REGISTRO=' + document.getElementById('revealToken').textContent +
          '\nPBX_ID=' + document.getElementById('revealPbxId').textContent;
        copyText(texto);
      });
    }

    document.querySelectorAll('.cm-reveal-copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var source = document.getElementById(btn.getAttribute('data-copy'));
        if (source) { copyText(source.textContent); }
      });
    });

    /* ---- Borrado con confirmación ---- */
    var deleteConfirm = document.getElementById('pbxDeleteConfirm');
    var deleteModalEl = document.getElementById('pbxDeleteModal');
    var deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
    var pendingName = null;
    var pendingRow = null;

    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('.cm-row-action.danger');
      if (!btn) { return; }
      pendingName = btn.getAttribute('data-pbx-name') || 'este PBX';
      pendingRow = btn.closest('tr');
      document.getElementById('pbxDeleteName').textContent = pendingName;
    });

    deleteConfirm.addEventListener('click', function () {
      if (pendingRow) {
        pendingRow.remove();
      }
      pendingRow = null;
      pendingName = null;
      deleteModal.hide();
      if (!tbody.querySelector('tr:not(#pbxNoRows)')) {
        tbody.innerHTML = '<tr id="pbxNoRows"><td colspan="7" class="text-center text-muted py-4">' +
          'No hay centrales PBX que coincidan con la búsqueda.</td></tr>';
      }
    });
  });
})();