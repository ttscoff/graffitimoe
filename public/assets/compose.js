(function () {
  'use strict';

  var MAX = 1000;
  var body = document.getElementById('body');
  var counter = document.getElementById('char-count');
  var spray = document.querySelector('.spray-btn');
  if (!body || !counter || !spray) return;

  function update() {
    var n = body.value.length;
    counter.textContent = n + ' / ' + MAX;
    var over = n > MAX;
    counter.classList.toggle('is-over', over);
    spray.disabled = over;
  }

  body.addEventListener('input', update);
  update();
})();
