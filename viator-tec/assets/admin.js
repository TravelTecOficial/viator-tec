// Configurações › Viator Tec: autocompletar de destinos. Escolher um destino preenche uma linha da tabela.
(function () {
  var cfg = window.vtecAdmin || {};
  var campo = document.getElementById('vtec-procurar');
  var lista = document.getElementById('vtec-sugestoes');
  var aviso = document.getElementById('vtec-aviso-destino');
  var tabela = document.getElementById('vtec-destinos');
  if (!campo || !lista || !tabela) return;

  var espera = null;
  var ultima = 0;
  var itens = [];
  var ativo = -1;

  function fechar() { lista.hidden = true; lista.innerHTML = ''; itens = []; ativo = -1; }

  function mostrar(dados) {
    itens = Array.isArray(dados) ? dados : [];
    lista.innerHTML = '';
    if (!itens.length) {
      var vazio = document.createElement('li');
      vazio.textContent = 'Nenhum destino encontrado';
      vazio.style.cursor = 'default';
      lista.appendChild(vazio);
    }
    itens.forEach(function (d, i) {
      var li = document.createElement('li');
      li.textContent = d.rotulo;
      li.addEventListener('mousedown', function (e) { e.preventDefault(); escolher(i); });
      lista.appendChild(li);
    });
    lista.hidden = false;
  }

  function buscar() {
    var termo = campo.value.trim();
    if (termo.length < 2) { fechar(); return; }
    var n = ++ultima;
    fetch(cfg.rest + '?termo=' + encodeURIComponent(termo), { headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (n === ultima) mostrar(d); })
      .catch(function () { if (n === ultima) mostrar([]); });
  }

  function linhaLivre() {
    var linhas = tabela.querySelectorAll('tr');
    for (var i = 0; i < linhas.length; i++) {
      if (!linhas[i].querySelector('[name="destino_id[]"]').value.trim()) return linhas[i];
    }
    var nova = linhas[linhas.length - 1].cloneNode(true);
    nova.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
    tabela.appendChild(nova);
    return nova;
  }

  function escolher(i) {
    var d = itens[i];
    if (!d) return;
    var ja = Array.prototype.some.call(tabela.querySelectorAll('[name="destino_id[]"]'), function (inp) { return inp.value.trim() === String(d.id); });
    if (ja) {
      aviso.textContent = d.nome + ' já está na lista.';
    } else {
      var tr = linhaLivre();
      tr.querySelector('[name="destino_id[]"]').value = d.id;
      tr.querySelector('[name="destino_nome[]"]').value = d.nome;
      tr.querySelector('[name="destino_slug[]"]').value = d.slug;
      tr.classList.add('vtec-novo');
      linhaLivre(); // sempre sobra uma linha em branco
      aviso.textContent = d.nome + ' adicionado — clique em Salvar.';
    }
    campo.value = '';
    fechar();
    campo.focus();
  }

  campo.addEventListener('input', function () { clearTimeout(espera); espera = setTimeout(buscar, 250); });
  campo.addEventListener('keydown', function (e) {
    if (lista.hidden) return;
    var lis = lista.querySelectorAll('li');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      ativo = Math.max(0, Math.min(itens.length - 1, ativo + (e.key === 'ArrowDown' ? 1 : -1)));
      lis.forEach(function (li, k) { li.classList.toggle('ativo', k === ativo); });
    } else if (e.key === 'Enter') {
      e.preventDefault(); // não enviar o formulário
      escolher(ativo >= 0 ? ativo : 0);
    } else if (e.key === 'Escape') {
      fechar();
    }
  });
  campo.addEventListener('blur', function () { setTimeout(fechar, 150); });
})();
