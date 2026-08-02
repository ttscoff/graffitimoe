(function () {
  'use strict';

  var POLL_MS = 10000;
  var MAX = 10;
  var grid = document.querySelector('.wall-grid');
  var empty = document.querySelector('.wall-empty');
  if (!grid || !empty) return;

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function cssClass(color, bold) {
    var c = 'term-' + (color || 'default');
    if (bold) c += ' term-bold';
    return c;
  }

  function buildFrame(msg) {
    var el = document.createElement('div');
    el.className = 'terminal terminal-enter';
    el.setAttribute('data-id', String(msg.id));
    el.innerHTML =
      '<div class="terminal-bar">' +
      '<span class="terminal-dot terminal-dot-red"></span>' +
      '<span class="terminal-dot terminal-dot-yellow"></span>' +
      '<span class="terminal-dot terminal-dot-green"></span>' +
      '<span class="terminal-title">msg #' + escapeHtml(String(msg.id)) + '</span>' +
      '</div>' +
      '<pre class="terminal-body ' + escapeHtml(cssClass(msg.color, !!msg.bold)) + '">' +
      escapeHtml(msg.body) +
      '</pre>';
    return el;
  }

  function setEmpty(isEmpty) {
    if (isEmpty) empty.removeAttribute('hidden');
    else empty.setAttribute('hidden', '');
  }

  function reconcile(messages) {
    if (!Array.isArray(messages)) return;

    var serverIds = messages.map(function (m) { return String(m.id); });
    var existing = Array.prototype.slice.call(grid.querySelectorAll('.terminal[data-id]'));
    var byId = {};
    existing.forEach(function (node) {
      byId[node.getAttribute('data-id')] = node;
    });

    // Remove gone
    existing.forEach(function (node) {
      var id = node.getAttribute('data-id');
      if (serverIds.indexOf(id) === -1) node.remove();
    });

    // Prepend new in reverse so final order is newest-first
    for (var i = messages.length - 1; i >= 0; i--) {
      var msg = messages[i];
      var id = String(msg.id);
      if (!byId[id]) {
        var frame = buildFrame(msg);
        grid.insertBefore(frame, grid.firstChild);
        byId[id] = frame;
      }
    }

    // Reorder to match server list exactly
    messages.forEach(function (msg) {
      var node = byId[String(msg.id)];
      if (node) grid.appendChild(node);
    });

    // Cap (server already <=10; safety)
    while (grid.querySelectorAll('.terminal').length > MAX) {
      var last = grid.querySelector('.terminal:last-child');
      if (!last) break;
      last.remove();
    }

    setEmpty(grid.querySelectorAll('.terminal').length === 0);
  }

  function poll() {
    if (document.visibilityState === 'hidden') return;
    fetch('/recent', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('recent ' + r.status);
        return r.json();
      })
      .then(reconcile)
      .catch(function () { /* ignore transient errors */ });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') poll();
  });

  setInterval(poll, POLL_MS);
})();
