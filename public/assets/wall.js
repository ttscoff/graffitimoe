(function () {
  'use strict';

  var POLL_MS = 10000;
  var MIN_POLL_GAP_MS = 2000;
  var PAGE = 10;
  var wall = document.querySelector('.wall');
  var grid = document.querySelector('.wall-grid');
  var empty = document.querySelector('.wall-empty');
  if (!wall || !grid || !empty) return;

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
  var loadingOlder = false;
  var exhausted = false;
  var lastPollAt = 0;
  var sentinel = document.querySelector('.wall-sentinel');

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
    var id = escapeHtml(String(msg.id));
    el.innerHTML =
      '<div class="terminal-bar">' +
      '<span class="terminal-dot terminal-dot-red"></span>' +
      '<span class="terminal-dot terminal-dot-yellow"></span>' +
      '<span class="terminal-dot terminal-dot-green"></span>' +
      '<a class="terminal-title terminal-title-link" href="/id/' + id + '">msg #' + id + '</a>' +
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

  function hasId(id) {
    return !!grid.querySelector('.terminal[data-id="' + String(id) + '"]');
  }

  function oldestId() {
    var nodes = grid.querySelectorAll('.terminal[data-id]');
    if (!nodes.length) return null;
    return parseInt(nodes[nodes.length - 1].getAttribute('data-id'), 10);
  }

  /** Prepend sprays missing from the DOM; never remove older loaded frames. */
  function prependNew(messages) {
    if (!Array.isArray(messages)) return;

    for (var i = messages.length - 1; i >= 0; i--) {
      var msg = messages[i];
      if (!hasId(msg.id)) {
        grid.insertBefore(buildFrame(msg), grid.firstChild);
      }
    }

    setEmpty(grid.querySelectorAll('.terminal').length === 0);
  }

  function appendOlder(messages) {
    if (!Array.isArray(messages)) return;
    messages.forEach(function (msg) {
      if (!hasId(msg.id)) {
        grid.appendChild(buildFrame(msg));
      }
    });
    setEmpty(grid.querySelectorAll('.terminal').length === 0);
  }

  function poll() {
    if (document.visibilityState === 'hidden') return;
    if (inFlight || loadingOlder) return;

    inFlight = true;
    fetch('/recent', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('recent ' + r.status);
        return r.json();
      })
      .then(function (data) {
        prependNew(data);
        lastPollAt = Date.now();
      })
      .catch(function () { /* ignore transient errors */ })
      .then(function () {
        inFlight = false;
      });
  }

  function loadOlder() {
    if (exhausted || loadingOlder || inFlight) return;
    var before = oldestId();
    if (!before) return;
    loadingOlder = true;
    fetch('/recent?before=' + before + '&limit=' + PAGE, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
      .then(function (r) {
        if (!r.ok) throw new Error('older ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!Array.isArray(data) || data.length < PAGE) exhausted = true;
        appendOlder(data || []);
      })
      .catch(function () { /* ignore */ })
      .then(function () {
        loadingOlder = false;
      });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState !== 'visible') return;
    if (inFlight || loadingOlder) return;
    if (Date.now() - lastPollAt < MIN_POLL_GAP_MS) return;
    poll();
  });

  setInterval(poll, POLL_MS);

  if (sentinel && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) loadOlder();
    }, { rootMargin: '200px' }).observe(sentinel);
  }

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
      var flagged = text.trim() === 'Flagged.';
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
