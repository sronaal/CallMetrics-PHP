/* ============================================================
   events.js — interacciones de la pagina Eventos Asterisk.

   WebSocket real + fallback a simulacion cuando no hay conexion.
   - Payload expandible: toggle de fila extra con el JSON formateado.
   - Stream en tiempo real: recibe eventos del WS o genera sinteticos.
   - Autoscroll pausable: sigue al ultimo evento salvo pausa del usuario.
   - "Cargar mas": revela eventos mas antiguos desde el pool.
   - Buffer max 200: al exceder, descarta el evento mas antiguo.
   ============================================================ */
(function () {
  'use strict';

  var MAX_BUFFER = 200;
  var PER_PAGE = 10;

  var poolEl = document.getElementById('cmEventsPool');
  if (!poolEl) { return; }

  var pool = [];
  try { pool = JSON.parse(poolEl.textContent || '[]'); } catch (e) { pool = []; }
  if (!pool.length) { return; }

  var tbody = document.getElementById('eventsTableRows');
  var container = document.getElementById('eventsScrollContainer');
  var loadMoreBtn = document.getElementById('eventsLoadMore');
  var autoscrollBtn = document.getElementById('eventsAutoscrollBtn');
  var statusIndicator = document.getElementById('wsStatusIndicator');

  var buffer = pool.slice(0, PER_PAGE);
  var offset = PER_PAGE;
  var autoscroll = true;
  var liveId = 100000;
  var usingRealWS = false;

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function relativeTime(ts) {
    var diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 0) { return 'ahora'; }
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

  /* ---- Payload expandible ---- */
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

  /* ---- Buffer max ---- */
  function trimBuffer() {
    while (buffer.length > MAX_BUFFER) {
      var oldest = buffer.pop();
      var row = tbody.querySelector('tr.cm-event-row[data-event-id="' + oldest.id + '"]');
      if (row) { row.remove(); }
      var payloadRow = tbody.querySelector('tr.cm-events-payload-row[data-event-id="' + oldest.id + '"]');
      if (payloadRow) { payloadRow.remove(); }
    }
  }

  /* ---- Agregar evento al DOM ---- */
  function addEvent(ev) {
    buffer.unshift(ev);
    tbody.insertBefore(buildRow(ev), tbody.firstChild);
    trimBuffer();
    var noRows = document.getElementById('eventsNoRows');
    if (noRows) { noRows.remove(); }
    if (autoscroll && container) { container.scrollTop = 0; }
  }

  /* ---- Mapear evento WS a formato de display ---- */
  function mapWSEvent(wsData) {
    var eventData = wsData.data || {};
    var amiEvent = eventData.ami_event || eventData.evento || wsData.event || '';
    var datos = eventData.datos || eventData;
    var callid = eventData.callid || datos.id_unico || '';

    // Determinar severidad según tipo de evento
    var severidad = 'info';
    if (amiEvent === 'Hangup' || amiEvent === 'call_ended') {
      severidad = 'warning';
    } else if (amiEvent === 'error' || amiEvent === 'call_failed') {
      severidad = 'error';
    }

    return {
      id: 'ws-' + (++liveId),
      timestamp: wsData.timestamp || Math.floor(Date.now() / 1000),
      severidad: severidad,
      tipo: wsData.type || 'AMI',
      pbx: 'PBX-' + (eventData.pbx_id || '?'),
      evento: amiEvent || wsData.event || 'evento',
      payload: datos
    };
  }

  /* ---- Conectar WebSocket real ---- */
  if (typeof window.CallMetricsWS !== 'undefined') {
    // Obtener tenant_id de la pagina (si existe)
    var tenantIdEl = document.getElementById('cmTenantId');
    var tenantId = tenantIdEl ? parseInt(tenantIdEl.value, 10) : null;

    if (tenantId) {
      var ws = new CallMetricsWS({ tenantId: tenantId });

      ws.on('call_event', function (data) {
        usingRealWS = true;
        addEvent(mapWSEvent(data));
      });

      ws.on('pbx_health', function (data) {
        usingRealWS = true;
        // Los eventos de health tambien se muestran
        addEvent({
          id: 'ws-health-' + (++liveId),
          timestamp: data.timestamp || Math.floor(Date.now() / 1000),
          severidad: 'info',
          tipo: 'HEALTH',
          pbx: 'PBX-' + (data.data?.pbx_id || '?'),
          evento: 'pbx_health',
          payload: data.data || {}
        });
      });

      ws.on('queue_update', function (data) {
        usingRealWS = true;
        addEvent({
          id: 'ws-queue-' + (++liveId),
          timestamp: data.timestamp || Math.floor(Date.now() / 1000),
          severidad: 'info',
          tipo: 'QUEUE',
          pbx: 'PBX-' + (data.data?.pbx_id || '?'),
          evento: data.data?.evento || 'queue_update',
          payload: data.data || {}
        });
      });

      ws.on('connected', function () {
        if (statusIndicator) {
          statusIndicator.innerHTML = '<i class="bi bi-circle-fill text-success me-1"></i>Conectado';
        }
      });

      ws.on('disconnected', function () {
        if (statusIndicator) {
          statusIndicator.innerHTML = '<i class="bi bi-circle-fill text-danger me-1"></i>Desconectado';
        }
      });

      ws.on('reconnecting', function () {
        if (statusIndicator) {
          statusIndicator.innerHTML = '<i class="bi bi-circle-fill text-warning me-1"></i>Reconectando...';
        }
      });

      ws.connect();

      // Exponer globalmente para debug
      window._cmWS = ws;
    }
  }

  /* ---- Fallback: stream sintetico si no hay WS en 5 segundos ---- */
  setTimeout(function () {
    if (usingRealWS) return; // Ya hay conexion real

    console.log('[events.js] Sin conexion WS, usando stream sintetico');
    if (statusIndicator) {
      statusIndicator.innerHTML = '<i class="bi bi-circle-fill text-secondary me-1"></i>Simulado';
    }

    var severidades = ['info', 'info', 'info', 'warning', 'warning', 'error'];

    function syntheticEvent() {
      var tpl = pool[Math.floor(Math.random() * pool.length)];
      return {
        id: 'live-' + (++liveId),
        timestamp: Math.floor(Date.now() / 1000),
        severidad: severidades[Math.floor(Math.random() * severidades.length)],
        tipo: tpl.tipo,
        pbx: tpl.pbx,
        evento: 'Evento en vivo: ' + (tpl.evento || 'actualizacion del canal'),
        payload: Object.assign({ simulado: true }, tpl.payload || {})
      };
    }

    setInterval(function () {
      if (usingRealWS) return; // Si se conecto el WS, parar la simulacion
      addEvent(syntheticEvent());
    }, 5000);
  }, 5000);

  /* ---- Autoscroll pausable ---- */
  autoscrollBtn.addEventListener('click', function () {
    autoscroll = !autoscroll;
    autoscrollBtn.innerHTML = autoscroll
      ? '<i class="bi bi-play-circle me-1"></i>Autoscroll: Activo'
      : '<i class="bi bi-pause-circle me-1"></i>Autoscroll: Pausado';
  });

  /* ---- "Cargar mas" ---- */
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
      loadMoreBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>No hay mas eventos';
    }
  });
})();
