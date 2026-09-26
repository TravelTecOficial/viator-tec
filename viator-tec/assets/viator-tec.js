(function () {
  var cfg = window.vtecCfg || {};
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.vtec-mais');
    if (!b) return;
    var grade = b.previousElementSibling;
    b.disabled = true;
    var d = b.dataset;
    var url = cfg.rest + 'mais?fonte=' + encodeURIComponent(d.fonte || 'destino') +
      '&destino=' + encodeURIComponent(d.destino || '') + '&ordem=' + encodeURIComponent(d.ordem || '') +
      '&inicio=' + d.inicio + '&modelo=' + (d.modelo || 0) + '&qtd=' + (d.qtd || 0);
    fetch(url).then(function (r) { return r.json(); }).then(function (res) {
      grade.insertAdjacentHTML('beforeend', res.html || '');
      document.dispatchEvent(new CustomEvent('vtec:cards'));
      if (res.proximo) { b.dataset.inicio = res.proximo; b.disabled = false; } else { b.remove(); }
    }).catch(function () { b.disabled = false; });
  });
})();
