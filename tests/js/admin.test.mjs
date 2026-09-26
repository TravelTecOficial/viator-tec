// Autocompletar do painel (assets/admin.js) em jsdom, com fetch falso.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';

const JS = fs.readFileSync(new URL('../../viator-tec/assets/admin.js', import.meta.url), 'utf8');
const linha = (id = '', nome = '') => `<tr><td><input name="destino_id[]" value="${id}"></td><td><input name="destino_nome[]" value="${nome}"></td><td><input name="destino_slug[]"></td><td><input name="destino_foto[]"></td></tr>`;

function montar(linhas) {
  const dom = new JSDOM(`<form><input id="vtec-procurar"><ul id="vtec-sugestoes" hidden></ul><span id="vtec-aviso-destino"></span><table><tbody id="vtec-destinos">${linhas}</tbody></table></form>`, { runScripts: 'outside-only' });
  const w = dom.window;
  w.vtecAdmin = { rest: '/wp-json/viator-tec/v1/destinos', nonce: 'n' };
  w.fetch = async () => ({ json: async () => [{ id: 479, nome: 'Paris', rotulo: 'Paris (França)', slug: 'paris' }] });
  w.eval(JS);
  return w;
}
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
async function digitarEEscolher(w, texto) {
  const campo = w.document.getElementById('vtec-procurar');
  campo.value = texto;
  campo.dispatchEvent(new w.Event('input'));
  await esperar(320);
  campo.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Enter' }));
}

test('escolher preenche a primeira linha livre e deixa uma em branco', async () => {
  const w = montar(linha('684', 'Las Vegas') + linha());
  await digitarEEscolher(w, 'par');
  const ids = [...w.document.querySelectorAll('[name="destino_id[]"]')].map((i) => i.value);
  assert.deepEqual(ids, ['684', '479', '']);
  assert.equal(w.document.querySelectorAll('[name="destino_slug[]"]')[1].value, 'paris');
  assert.match(w.document.getElementById('vtec-aviso-destino').textContent, /Paris adicionado/);
});

test('mostra o rótulo com o país na lista', async () => {
  const w = montar(linha());
  const campo = w.document.getElementById('vtec-procurar');
  campo.value = 'par';
  campo.dispatchEvent(new w.Event('input'));
  await esperar(320);
  assert.equal(w.document.querySelector('#vtec-sugestoes li').textContent, 'Paris (França)');
});

test('destino repetido não entra de novo', async () => {
  const w = montar(linha('479', 'Paris') + linha());
  await digitarEEscolher(w, 'par');
  const ids = [...w.document.querySelectorAll('[name="destino_id[]"]')].map((i) => i.value);
  assert.deepEqual(ids, ['479', '']);
  assert.match(w.document.getElementById('vtec-aviso-destino').textContent, /já está na lista/);
});
