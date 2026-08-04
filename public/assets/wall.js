(function () {
  'use strict';

  var POLL_MS = 10000;
  var MIN_POLL_GAP_MS = 2000;
  var wall = document.querySelector('.wall');
  var grid = document.querySelector('.wall-grid');
  var empty = document.querySelector('.wall-empty');
  if (!wall || !grid || !empty) return;

  var MAX = parseInt(wall.getAttribute('data-wall-max') || '10', 10) || 10;
  var isAdmin = wall.getAttribute('data-admin') === '1';
  var csrfToken = wall.getAttribute('data-csrf') || '';
  var ownedIds = (wall.getAttribute('data-owned') || '')
    .split(',')
    .map(function (s) { return parseInt(s, 10); })
    .filter(function (n) { return n > 0; });
  var flaggedIds = (wall.getAttribute('data-flagged-ids') || '')
    .split(',')
    .map(function (s) { return parseInt(s, 10); })
    .filter(function (n) { return n > 0; });

  var inFlight = false;
  var lastPollAt = 0;

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

  function renderBody(msg) {
    var spans = msg.spans;
    if (Array.isArray(spans) && spans.length > 0) {
      var perRunBold = spans.some(function (run) {
        return Object.prototype.hasOwnProperty.call(run, 'b');
      });
      return spans.map(function (run) {
        var runBold = perRunBold ? !!run.b : !!msg.bold;
        return (
          '<span class="' +
          escapeHtml(cssClass(run.c, runBold)) +
          '">' +
          escapeHtml(run.t) +
          '</span>'
        );
      }).join('');
    }
    return escapeHtml(msg.body);
  }

  function outerClass(msg) {
    var spans = msg.spans;
    if (Array.isArray(spans) && spans.length > 0) {
      return '';
    }
    return cssClass(msg.color, !!msg.bold);
  }

  function canDelete(id) {
    if (isAdmin) return true;
    return ownedIds.indexOf(Number(id)) !== -1;
  }

  function hasFlagged(id) {
    return flaggedIds.indexOf(Number(id)) !== -1;
  }

  function flagActionsHtml(msg) {
    if (!csrfToken) return '';
    var id = escapeHtml(String(msg.id));
    var mine = hasFlagged(msg.id);
    return (
      '<form class="wall-flag" method="post" action="/flag">' +
      '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
      '<input type="hidden" name="id" value="' + id + '">' +
      '<input type="hidden" name="next" value="/add">' +
      '<button type="submit" class="wall-flag-btn' + (mine ? ' is-flagged-by-me' : '') +
      '" title="' + (mine ? 'Remove your flag' : 'Flag this spray') + '">flag</button>' +
      '</form>'
    );
  }

  function adminActionsHtml(msg) {
    if (!csrfToken || !canDelete(msg.id)) return '';
    var id = escapeHtml(String(msg.id));
    var html = '';
    if (isAdmin && msg.flagged) {
      html +=
        '<span class="flag-badge" title="Flagged as low-effort or test">flagged</span>' +
        '<form class="wall-approve" method="post" action="/admin">' +
        '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
        '<input type="hidden" name="id" value="' + id + '">' +
        '<input type="hidden" name="approve" value="1">' +
        '<input type="hidden" name="next" value="/add">' +
        '<button type="submit" class="wall-approve-btn" title="Clear flag">approve</button>' +
        '</form>';
    }
    html +=
      '<form class="wall-delete" method="post" action="' + (isAdmin ? '/admin' : '/delete') + '">' +
      '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
      '<input type="hidden" name="id" value="' + id + '">' +
      '<input type="hidden" name="next" value="/add">' +
      '<button type="submit" class="wall-delete-btn" title="' +
      (isAdmin ? 'Delete this spray' : 'Delete your spray') +
      '">delete</button>' +
      '</form>';
    return html;
  }

  function buildFrame(msg) {
    var el = document.createElement('div');
    el.className = 'terminal terminal-enter' + (isAdmin && msg.flagged ? ' is-flagged' : '');
    el.setAttribute('data-id', String(msg.id));
    if (msg.flagged) el.setAttribute('data-flagged', '1');
    el.addEventListener('animationend', function () {
      el.classList.remove('terminal-enter');
    }, { once: true });
    var outer = outerClass(msg);
    el.innerHTML =
      '<div class="terminal-bar">' +
      '<span class="terminal-dot terminal-dot-red"></span>' +
      '<span class="terminal-dot terminal-dot-yellow"></span>' +
      '<span class="terminal-dot terminal-dot-green"></span>' +
      '<span class="terminal-title">msg #' + escapeHtml(String(msg.id)) + '</span>' +
      flagActionsHtml(msg) +
      adminActionsHtml(msg) +
      '</div>' +
      '<pre class="terminal-body' + (outer ? ' ' + escapeHtml(outer) : '') + '">' +
      renderBody(msg) +
      '</pre>';
    return el;
  }

  function setEmpty(isEmpty) {
    if (isEmpty) empty.removeAttribute('hidden');
    else empty.setAttribute('hidden', '');
  }

  function domIdSequence() {
    return Array.prototype.slice.call(grid.querySelectorAll('.terminal[data-id]'))
      .map(function (node) { return node.getAttribute('data-id'); });
  }

  function sequencesMatch(a, b) {
    if (a.length !== b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i] !== b[i]) return false;
    }
    return true;
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

    // Reorder only when DOM sequence differs from server list
    if (!sequencesMatch(domIdSequence(), serverIds)) {
      messages.forEach(function (msg) {
        var node = byId[String(msg.id)];
        if (node) grid.appendChild(node);
      });
    }

    while (grid.querySelectorAll('.terminal').length > MAX) {
      var last = grid.querySelector('.terminal:last-child');
      if (!last) break;
      last.remove();
    }

    setEmpty(grid.querySelectorAll('.terminal').length === 0);
  }

  function poll() {
    if (document.visibilityState === 'hidden') return;
    if (inFlight) return;

    inFlight = true;
    fetch('/recent', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('recent ' + r.status);
        return r.json();
      })
      .then(function (data) {
        reconcile(data);
        lastPollAt = Date.now();
      })
      .catch(function () { /* ignore transient errors */ })
      .then(function () {
        inFlight = false;
      });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState !== 'visible') return;
    if (inFlight) return;
    if (Date.now() - lastPollAt < MIN_POLL_GAP_MS) return;
    poll();
  });

  setInterval(poll, POLL_MS);

  grid.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.classList || !form.classList.contains('wall-flag')) return;
    e.preventDefault();
    var btn = form.querySelector('.wall-flag-btn');
    var id = parseInt(form.querySelector('input[name="id"]').value, 10);
    var body = new URLSearchParams(new FormData(form));
    fetch('/flag', {
      method: 'POST',
      headers: { Accept: 'text/plain' },
      body: body,
      credentials: 'same-origin',
    }).then(function (res) {
      if (!res.ok) throw new Error('flag failed');
      return res.text();
    }).then(function (text) {
      var flagged = text.indexOf('Flagged') !== -1;
      if (flagged) {
        if (!hasFlagged(id)) flaggedIds.push(id);
        if (btn) btn.classList.add('is-flagged-by-me');
      } else {
        flaggedIds = flaggedIds.filter(function (n) { return n !== id; });
        if (btn) btn.classList.remove('is-flagged-by-me');
      }
      if (btn) {
        btn.title = flagged ? 'Remove your flag' : 'Flag this spray';
      }
    }).catch(function () {
      form.submit();
    });
  });
})();
