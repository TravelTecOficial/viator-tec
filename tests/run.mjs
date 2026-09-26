// Roda os testes PHP em PHP 7.4 e 8.2 (php-wasm, sem instalar PHP nem WordPress).
// Uso: node run.mjs [parte-do-nome]   |   PHP_VERSOES=8.2 node run.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const PLUGIN = path.resolve(AQUI, '../viator-tec');
const VERSOES = (process.env.PHP_VERSOES || '7.4,8.2').split(',');
const filtro = process.argv[2] || '';
const testes = fs.readdirSync(path.join(AQUI, 'php')).filter((f) => f.endsWith('.test.php') && f.includes(filtro));
if (!testes.length) { console.error('nenhum teste encontrado'); process.exit(1); }

function copiarPasta(php, origem, destino) {
  php.mkdir(destino);
  for (const f of fs.readdirSync(origem)) {
    const o = path.join(origem, f);
    if (fs.statSync(o).isDirectory()) copiarPasta(php, o, `${destino}/${f}`);
    else php.writeFile(`${destino}/${f}`, fs.readFileSync(o));
  }
}

let falhas = 0;
for (const versao of VERSOES) {
  console.log(`\n===== PHP ${versao} =====`);
  for (const arq of testes) {
    const php = new PHP(await loadNodeRuntime(versao, { emscriptenOptions: { processId: 1 } }));
    copiarPasta(php, PLUGIN, '/p');
    copiarPasta(php, path.join(AQUI, 'fixtures'), '/f');
    php.mkdir('/t');
    php.writeFile('/t/bootstrap.php', fs.readFileSync(path.join(AQUI, 'php', 'bootstrap.php')));
    php.writeFile(`/t/${arq}`, fs.readFileSync(path.join(AQUI, 'php', arq)));
    let r;
    try {
      r = await php.run({ code: `<?php require '/t/${arq}'; echo "\\nFALHAS=" . $GLOBALS['vt_falhas'];` });
    } catch (e) {
      const msg = (e.response && (e.response.text || e.response.errors)) || e.message;
      console.log(`--- ${arq}\nERRO FATAL DO PHP:\n${String(msg).split('\n').slice(0, 8).join('\n')}`);
      falhas++;
      continue;
    }
    process.stdout.write(`--- ${arq}\n${r.text}\n`);
    if (r.errors) console.log('stderr:', r.errors);
    const m = /FALHAS=(\d+)/.exec(r.text);
    if (!m || Number(m[1]) > 0) falhas++;
  }
}
console.log(falhas ? `\n${falhas} ARQUIVO(S) COM FALHA` : '\nTUDO OK');
process.exit(falhas ? 1 : 0);
