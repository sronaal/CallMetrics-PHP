/* ============================================================
   events.js — interacciones de la página Eventos Asterisk.

   Simulación 100% client-side (sin backend ni WebSocket, según
   design del port):
   - Payload expandible: toggle de fila extra con el JSON formateado.
   - Stream simulado: genera un evento nuevo cada pocos segundos
     reutilizando las plantillas del mock embebido en #cmEventsPool.
   - Autoscroll pausable: sigue al último evento salvo pausa del
     usuario.
   - "Cargar más": revela eventos más antiguos desde el pool.
   - Buffer máx 200: al exceder, descarta el evento más antiguo
     (el último del array, que es la fila más vieja del DOM).
   ============================================================ */
(function () {
  'use strict';

  var MAX_BUFFER = 200;
  var PER_PAGE = 10;

  var poolEl = document.getElementById('cmEventsPool');
  if (!poolEl) {
    // Página estática (page > 1) o sin datos: no hay interacciones.
    return;
  }

  var pool = [];
  try { pool = JSON.parse(poolEl.textContent || '[]'); } catch (e) { pool = []; }
  if (!pool.length) { return; }

  var tbody = document.getElementById('eventsTableRows');
  var container = document.getElementById('eventsScrollContainer');
  var loadMoreBtn = document.getElementById('eventsLoadMore');
  var autoscrollBtn = document.getElementById('eventsAutoscrollBtn');

  var buffer = pool.slice(0, PER_PAGE); // Espejo de la primera página renderizada.
  var offset = PER_PAGE;                // Siguiente índice del pool para "Cargar más".
  var autoscroll = true;
  var liveId = 100000;

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function relativeTime(ts) {
    var diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60) { return 'ahora'; }
    if (diff < 3600) { return 'hace ' + Math.floor(diff / 60) + ' min'; }
    if (diff < 86400) { return 'hace ' + Math.floor(diff / 3600) + 'h'; }
    return 'hace ' + Math.floor(diff / 86400) + 'd';
  }

  var sevClass = { info: 'cm-badge-info', warning: 'cm-badge-warn', error: 'cm-badge-bad' };
  var sevLabel = { info: 'Info', warning: 'Advertencia', error: 'Error' };

  function severityBadge(sev) {
    var cls = sevClass[sev] || 'cm-badge-muted';
    var label = sevLabel[sev] || sev;
    return '<span class="cm-badge ' + cls + '">' + label + '</span>';
  }

  function buildRow(ev) {
    var payloadJson = JSON.stringify(ev.payload || {});
    var tr = document.createElement('tr');
    tr.className = 'cm-event-row';
    tr.setAttribute('data-event-id', ev.id);
    tr.setAttribute('data-payload', payloadJson);
    tr.innerHTML =
      '<td class="cell-pbx live">' + esc(relativeTime(ev.timestamp)) + '</td>' +
      '<td>' + severityBadge(ev.severidad) + '</td>' +
      '<td>' + esc(ev.tipo) + '</td>' +
      '<td class="cell-pbx">' + esc(ev.pbx) + '</td>' +
      '<td>' + esc(ev.evento) + '</td>' +
      '<td class="text-end">' +
        '<button type="button" class="cm-row-action cm-events-toggle" title="Ver payload del evento">' +
        '<i class="bi bi-chevron-down"></i></button></td>';
    return tr;
  }

  /* ---- Payload expandible (fila extra con JSON formateado) ---- */
  tbody.addEventListener('click', function (e) {
    var btn = e.target.closest('.cm-events-toggle');
    if (!btn) { return; }
    var row = btn.closest('.cm-event-row');
    if (!row) { return; }
    var id = row.getAttribute('data-event-id');
    var expanded = tbody.querySelector('tr.cm-events-payload-row[data-event-id="' + id + '"]');
    if (expanded) {
      expanded.remove();
      btn.querySelector('i').className = 'bi bi-chevron-down';
      return;
    }
    var payload = {};
    try { payload = JSON.parse(row.getAttribute('data-payload') || '{}'); } catch (err) { /* raw */ }
    var payloadRow = document.createElement('tr');
    payloadRow.className = 'cm-events-payload-row';
    payloadRow.setAttribute('data-event-id', id);
    payloadRow.innerHTML = '<td colspan="6">' +
      '<pre class="m-0 cm-events-payload">' + esc(JSON.stringify(payload, null, 2)) + '</pre></td>';
    row.insertAdjacentElement('afterend', payloadRow);
    btn.querySelector('i').className = 'bi bi-chevron-up';
  });

  /* ---- Buffer máx 200: descarta el evento más antiguo ---- */
  function trimBuffer() {
    while (buffer.length > MAX_BUFFER) {
      var oldest = buffer.pop(); // El más antiguo vive al final (lista newest-first).
      var row = tbody.querySelector('tr.cm-event-row[data-event-id="' + oldest.id + '"]');
      if (row) { row.remove(); }
      var payloadRow = tbody.querySelector('tr.cm-events-payload-row[data-event-id="' + oldest.id + '"]');
      if (payloadRow) { payloadRow.remove(); }
    }
  }

  /* ---- Stream simulado (el canal AMI real llegaría por WebSocket) ---- */
  var severidades = ['info', 'info', 'info', 'warning', 'warning', 'error'];

  function syntheticEvent() {
    var tpl = pool[Math.floor(Math.random() * pool.length)];
    return {
      id: 'live-' + (++liveId),
      timestamp: Math.floor(Date.now() / 1000),
      severidad: severidades[Math.floor(Math.random() * severidades.length)],
      tipo: tpl.tipo,
      pbx: tpl.pbx,
      evento: 'Evento en vivo: ' + (tpl.evento || 'actualización del canal'),
      payload: Object.assign({ simulado: true }, tpl.payload || {})
    };
  }

  setInterval(function () {
    var ev = syntheticEvent();
    buffer.unshift(ev);
    tbody.insertBefore(buildRow(ev), tbody.firstChild);
    trimBuffer();
    var noRows = document.getElementById('eventsNoRows');
    if (noRows) { noRows.remove(); }
    if (autoscroll && container) { container.scrollTop = 0; }
  }, 5000);

  /* ---- Autoscroll pausable ---- */
  autoscrollBtn.addEventListener('click', function () {
    autoscroll = !autoscroll;
    autoscrollBtn.innerHTML = autoscroll
      ? '<i class="bi bi-play-circle me-1"></i>Autoscroll: Activo'
      : '<i class="bi bi-pause-circle me-1"></i>Autoscroll: Pausado';
  });

  /* ---- "Cargar más": revela eventos más antiguos del pool ---- */
  loadMoreBtn.addEventListener('click', function () {
    var room = MAX_BUFFER - buffer.length;
    if (room <= 0) {
      loadMoreBtn.disabled = true;
      return;
    }
    var next = pool.slice(offset, offset + PER_PAGE);
    if (next.length > room) { next = next.slice(0, room); }
    offset += next.length;
    buffer = buffer.concat(next);
    var frag = document.createDocumentFragment();
    next.forEach(function (ev) { frag.appendChild(buildRow(ev)); });
    tbody.appendChild(frag);
    if (offset >= pool.length) {
      loadMoreBtn.disabled = true;
      loadMoreBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>No hay más eventos';
    }
  });
})();
