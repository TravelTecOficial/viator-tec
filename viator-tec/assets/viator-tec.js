(function () {
  var cfg = window.vtecCfg || {};
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.vtec-mais');
    if (!b) return;
    b.disabled = true;
    var url = cfg.rest + 'mais?destino=' + encodeURIComponent(b.dataset.destino) +
      '&ordem=' + encodeURIComponent(b.dataset.ordem) + '&inicio=' + b.dataset.inicio;
    fetch(url).then(function (r) { return r.json(); }).then(function (d) {
      var grade = document.querySelector('.vtec-grade-cards');
      grade.insertAdjacentHTML('beforeend', d.html || '');
      document.dispatchEvent(new CustomEvent('vtec:cards'));
      if (d.proximo) { b.dataset.inicio = d.proximo; b.disabled = false; } else { b.remove(); }
    }).catch(function () { b.disabled = false; });
  });
})();
