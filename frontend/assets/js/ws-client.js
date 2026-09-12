/* ============================================================
   ws-client.js — Cliente WebSocket para CallMetrics Pro

   Maneja la conexión persistente al servidor WebSocket del backend.
   Reconexión automática con backoff exponencial.
   Suscripción a canales por tenant.

   Uso:
     var ws = new CallMetricsWS({ tenantId: 1 });
     ws.on('call_event', function(data) { ... });
     ws.connect();
   ============================================================ */
(function () {
  'use strict';

  /**
   * Constructor del cliente WebSocket.
   *
   * @param {Object} opts
   * @param {number} opts.tenantId - ID del tenant a suscribir
   * @param {string} [opts.wsUrl]  - URL del WS server (default: ws://localhost:8081)
   */
  function CallMetricsWS(opts) {
    this.tenantId = opts.tenantId;
    this.wsUrl = opts.wsUrl || 'ws://localhost:8081';
    this.ws = null;
    this.connected = false;
    this.reconnecting = false;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 50;
    this.baseReconnectDelay = 1000; // 1s
    this.maxReconnectDelay = 30000; // 30s
    this.pingInterval = null;
    this.listeners = {};
    this.pendingSubscriptions = [];
    this._statusCallbacks = [];
  }

  /**
   * Registrar un listener para un tipo de evento.
   *
   * @param {string} event - Tipo: call_event, pbx_health, queue_update, dashboard_update, connected, disconnected, error
   * @param {Function} callback
   */
  CallMetricsWS.prototype.on = function (event, callback) {
    if (!this.listeners[event]) {
      this.listeners[event] = [];
    }
    this.listeners[event].push(callback);
    return this;
  };

  /**
   * Remover un listener.
   */
  CallMetricsWS.prototype.off = function (event, callback) {
    if (!this.listeners[event]) return this;
    this.listeners[event] = this.listeners[event].filter(function (cb) {
      return cb !== callback;
    });
    return this;
  };

  /**
   * Emitir un evento a todos los listeners registrados.
   */
  CallMetricsWS.prototype._emit = function (event, data) {
    var cbs = this.listeners[event] || [];
    for (var i = 0; i < cbs.length; i++) {
      try {
        cbs[i](data);
      } catch (e) {
        console.error('[CallMetricsWS] Error en listener:', e);
      }
    }
  };

  /**
   * Registrar callback de cambio de estado de conexión.
   */
  CallMetricsWS.prototype.onStatusChange = function (callback) {
    this._statusCallbacks.push(callback);
    return this;
  };

  /**
   * Notificar cambio de estado a todos los callbacks.
   */
  CallMetricsWS.prototype._notifyStatus = function (status) {
    for (var i = 0; i < this._statusCallbacks.length; i++) {
      try {
        this._statusCallbacks[i](status);
      } catch (e) {
        console.error('[CallMetricsWS] Error en status callback:', e);
      }
    }
  };

  /**
   * Conectar al servidor WebSocket.
   */
  CallMetricsWS.prototype.connect = function () {
    if (this.ws && (this.ws.readyState === WebSocket.CONNECTING || this.ws.readyState === WebSocket.OPEN)) {
      return;
    }

    var self = this;

    try {
      this.ws = new WebSocket(this.wsUrl);
    } catch (e) {
      console.error('[CallMetricsWS] Error creando WebSocket:', e);
      this._scheduleReconnect();
      return;
    }

    this.ws.onopen = function () {
      console.log('[CallMetricsWS] Conectado a ' + self.wsUrl);
      self.connected = true;
      self.reconnecting = false;
      self.reconnectAttempts = 0;
      self._startPing();
      self._emit('connected', { url: self.wsUrl });
      self._notifyStatus('connected');

      // Suscribirse al canal del tenant
      if (self.tenantId) {
        self.subscribe('tenant_' + self.tenantId);
      }
      // Suscribirse al canal de dashboard
      self.subscribe('dashboard');
    };

    this.ws.onmessage = function (event) {
      self._handleMessage(event.data);
    };

    this.ws.onclose = function (event) {
      console.log('[CallMetricsWS] Desconectado (code: ' + event.code + ')');
      self.connected = false;
      self._stopPing();
      self._emit('disconnected', { code: event.code, reason: event.reason });
      self._notifyStatus('disconnected');
      self._scheduleReconnect();
    };

    this.ws.onerror = function (error) {
      console.error('[CallMetricsWS] Error:', error);
      self._emit('error', { error: error });
      self._notifyStatus('error');
    };
  };

  /**
   * Desconectar del servidor WebSocket.
   */
  CallMetricsWS.prototype.disconnect = function () {
    this.maxReconnectAttempts = 0; // Evitar reconexión automática
    this._stopPing();
    if (this.ws) {
      this.ws.close(1000, 'Client disconnect');
    }
    this.connected = false;
    this._notifyStatus('disconnected');
  };

  /**
   * Manejar mensaje entrante del servidor.
   */
  CallMetricsWS.prototype._handleMessage = function (raw) {
    var data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      console.warn('[CallMetricsWS] Mensaje no JSON:', raw);
      return;
    }

    // Respuesta a autenticación
    if (data.action === 'authenticated') {
      console.log('[CallMetricsWS] Autenticado como:', data.type);
      return;
    }

    // Ack de suscripción
    if (data.action === 'subscribed') {
      console.log('[CallMetricsWS] Suscrito a canal:', data.channel);
      return;
    }

    // Pong
    if (data.action === 'pong') {
      return;
    }

    // Error del servidor
    if (data.error) {
      console.warn('[CallMetricsWS] Error del servidor:', data.error);
      return;
    }

    // Eventos de broadcast — emitir por tipo
    var type = data.type || '';
    switch (type) {
      case 'call_event':
        this._emit('call_event', data);
        break;
      case 'pbx_health':
        this._emit('pbx_health', data);
        break;
      case 'queue_update':
        this._emit('queue_update', data);
        break;
      case 'dashboard_update':
        this._emit('dashboard_update', data);
        break;
      default:
        this._emit('message', data);
        break;
    }
  };

  /**
   * Suscribirse a un canal.
   */
  CallMetricsWS.prototype.subscribe = function (channel) {
    if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ action: 'subscribe', channel: channel }));
    } else {
      // Guardar para suscribir al reconectar
      if (this.pendingSubscriptions.indexOf(channel) === -1) {
        this.pendingSubscriptions.push(channel);
      }
    }
    return this;
  };

  /**
   * Desuscribirse de un canal.
   */
  CallMetricsWS.prototype.unsubscribe = function (channel) {
    if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ action: 'unsubscribe', channel: channel }));
    }
    var idx = this.pendingSubscriptions.indexOf(channel);
    if (idx !== -1) {
      this.pendingSubscriptions.splice(idx, 1);
    }
    return this;
  };

  /**
   * Enviar un mensaje al servidor.
   */
  CallMetricsWS.prototype.send = function (data) {
    if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(typeof data === 'string' ? data : JSON.stringify(data));
      return true;
    }
    return false;
  };

  /**
   * Iniciar ping periódico para mantener la conexión viva.
   */
  CallMetricsWS.prototype._startPing = function () {
    this._stopPing();
    var self = this;
    this.pingInterval = setInterval(function () {
      if (self.connected && self.ws && self.ws.readyState === WebSocket.OPEN) {
        self.ws.send(JSON.stringify({ action: 'ping' }));
      }
    }, 30000); // Cada 30 segundos
  };

  /**
   * Detener ping periódico.
   */
  CallMetricsWS.prototype._stopPing = function () {
    if (this.pingInterval) {
      clearInterval(this.pingInterval);
      this.pingInterval = null;
    }
  };

  /**
   * Programar reconexión con backoff exponencial.
   */
  CallMetricsWS.prototype._scheduleReconnect = function () {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.warn('[CallMetricsWS] Máximo de intentos de reconexión alcanzado');
      this._notifyStatus('failed');
      return;
    }

    this.reconnecting = true;
    this.reconnectAttempts++;

    var delay = Math.min(
      this.baseReconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
      this.maxReconnectDelay
    );

    // Jitter: ±25%
    delay = delay * (0.75 + Math.random() * 0.5);

    console.log('[CallMetricsWS] Reconectando en ' + Math.round(delay) + 'ms (intento ' + this.reconnectAttempts + ')');
    this._notifyStatus('reconnecting');

    var self = this;
    setTimeout(function () {
      self.connect();
    }, delay);
  };

  // ---------------------------------------------------------------
  // Estado de conexión para UI
  // ---------------------------------------------------------------

  /**
   * Obtener estado actual de la conexión.
   * @returns {string} 'connected' | 'disconnected' | 'reconnecting' | 'error'
   */
  CallMetricsWS.prototype.getStatus = function () {
    if (this.connected) return 'connected';
    if (this.reconnecting) return 'reconnecting';
    return 'disconnected';
  };

  // ---------------------------------------------------------------
  // Exportar globalmente
  // ---------------------------------------------------------------
  window.CallMetricsWS = CallMetricsWS;
})();
