(function () {
  var cfg = window.vtecCfg || {};
  function carregar() {
    var alvos = Array.prototype.filter.call(document.querySelectorAll('.vtec-nota[data-codigo]'), function (el) { return !el.dataset.ok; });
    if (!alvos.length) return;
    var codigos = alvos.map(function (el) { return el.dataset.codigo; });
    fetch(cfg.rest + 'protegido?codigos=' + encodeURIComponent(codigos.join(','))).then(function (r) { return r.json(); }).then(function (d) {
      alvos.forEach(function (el) {
        el.dataset.ok = '1';
        var n = d[el.dataset.codigo];
        if (n) el.innerHTML = '<b>★ ' + n.nota.toFixed(1).replace('.', ',') + '</b> (' + n.total.toLocaleString('pt-BR') + ' avaliações)';
      });
    }).catch(function () {});
  }
  document.addEventListener('DOMContentLoaded', carregar);
  document.addEventListener('vtec:cards', carregar);
})();
