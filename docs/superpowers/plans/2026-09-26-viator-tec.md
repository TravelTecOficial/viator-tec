# Viator Tec — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin WordPress "Viator Tec" que mostra passeios da Viator (Partner API, afiliado básico) em `/passeios/`, `/passeios/<destino>/` e `/passeios/p/<código>-<slug>/` no site redeturistica.com.br, com botão "Reservar na Viator" levando o código de afiliado.

**Architecture:** O servidor do WordPress chama a API da Viator (`wp_remote_request`), guarda as respostas em transients (12 h, com cópia de reserva de 7 dias) e renderiza as páginas por regras de rewrite próprias, dentro do tema (Hello + header/footer do Theme Builder). A nota/avaliações (conteúdo protegido pela Viator) chegam só por um JS externo + rota REST, ambos bloqueados no robots.txt. Funções puras (mapeamento e HTML) ficam separadas das que falam com WordPress/HTTP, para serem testadas em php-wasm.

**Tech Stack:** PHP 7.4+ (site roda 8.2.33), WordPress 6+, JS puro, testes em php-wasm (Node, mesmo esquema do `padrao/plugin-roteiros/tests/`), Python para empacotar e para a conferência no site publicado. Repositório GitHub `TravelTecOficial/viator-tec` com atualização automática por release.

**Spec:** `RedeTuristica/docs/modulos/viator.md`

## Global Constraints

- Pasta do projeto: `C:\Users\User\Documents\Wordpress\padrao\plugin-viator\`; o plugin em si fica em `viator-tec/` dentro dela; slug do plugin `viator-tec`, arquivo principal `viator-tec/viator-tec.php`.
- Prefixo de funções `vtec_`, constantes `VTEC_`, option `vtec_opcoes`, REST namespace `viator-tec/v1`.
- PHP 7.4 compatível: sem `match`, `str_contains`, argumentos nomeados, tipos union.
- Cabeçalhos em toda chamada: `exp-api-key`, `Accept: application/json;version=2.0`, `Accept-Language: pt-BR`, `Content-Type: application/json`.
- Base sandbox `https://api.sandbox.viator.com/partner`; produção `https://api.viator.com/partner`.
- Moeda BRL; textos em pt-BR; campanha padrão `redeturistica-passeios` (vai como query `campaign-value` em `GET /products/{code}`; a Viator devolve `&campaign=` no `productUrl`).
- Cache: busca 12 h, produto 12 h, preço 12 h, câmbio 12 h, destinos 7 dias; reserva 7 dias.
- Chave da API nunca no HTML, em JS, em log, em arquivo versionado ou no chat. Local: `config.local.json → sites.RedeTuristica.viator.{sandboxKey, productionKey}`.
- Avaliações (nota, total) nunca no HTML da página: só via `assets/protegido.js` + `GET /wp-json/viator-tec/v1/protegido`, ambos em `Disallow` no robots.txt.
- Visual: laranja `#E98300`, texto `#3C3D3D`, borda `#D9D9D9`, raio 8px.
- Aviso fixo nas páginas de destino e de passeio: "A reserva e o pagamento são feitos no site da Viator."
- Testes no site publicado (regra do dono: nada de localhost), com a chave sandbox e **sem link no menu** até o dono aprovar.
- A pasta `Wordpress/` não é git; o repositório é só `padrao/plugin-viator/`.

## Review Focus

1. **Destino ou código inexistente na URL** (`/passeios/xyz/`, `/passeios/p/000/`) → página 404 do tema, nunca erro fatal ou página em branco. Teste: Task 7 (`vtec_resolver_pagina`) e Task 10 (conferência).
2. **API fora do ar / 429 / chave errada** → com cache de reserva mostra o conteúdo antigo; sem cache mostra "Passeios indisponíveis no momento" com status 200. Teste: Task 3 (cache) e Task 6 (`vtec_html_indisponivel` na página).
3. **Produto sem imagem, sem preço, sem duração ou com duração só textual** → card/página renderiza sem "R$ 0,00", sem `<img src="">`. Teste: Task 4.
4. **Textos com HTML/aspas vindos da Viator** (título com `"` ou `<`) → sempre escapados. Teste: Task 6.
5. **Título com acento e caracteres especiais no slug** (`Passeio à "Ilha" & Mar`) → URL válida, e a rota aceita a URL com qualquer slug depois do código. Teste: Task 4 (`vtec_url_produto`) e Task 7 (regex da rota).

---

## Estrutura de arquivos

```
padrao/plugin-viator/
  README.md                         — o que é, como testar, como publicar versão
  empacotar.py                      — gera dist/viator-tec.zip
  .gitignore                        — dist/, tests/node_modules/
  viator-tec/
    viator-tec.php                  — cabeçalho, constantes, requires, ativação
    readme.txt                      — histórico de versões
    includes/
      opcoes.php                    — opções, sanitização, destino por slug, chave/base atual
      api.php                       — cliente HTTP da Viator (erros, 429)
      cache.php                     — transients com reserva e "geração" para limpar
      dados.php                     — funções puras: preço, duração, imagem, URL, card, view do produto, nota
      servico.php                   — buscar destino, produto, preço a partir de, destinos da Viator
      render.php                    — funções puras que devolvem HTML
      rotas.php                     — rewrite, query vars, template, 404, SEO, robots.txt
      rest.php                      — /protegido e /mais
      admin.php                     — Configurações › Viator Tec
      atualizador.php               — atualização automática pelo GitHub (cópia adaptada do Voucher Tec)
    templates/pagina.php            — get_header + conteúdo + get_footer
    assets/viator-tec.css
    assets/viator-tec.js            — carregar mais + galeria
    assets/protegido.js             — busca as notas pela REST
  tests/
    package.json, run.mjs
    fixtures/{search,product,schedules,destinations,exchange}.json  (já baixados do sandbox em 26/09)
    php/bootstrap.php
    php/*.test.php
RedeTuristica/scripts/viator_conferir.py  — conferência no site publicado
```

---

### Task 1: Esqueleto, repositório e rodador de testes

**Files:**
- Create: `padrao/plugin-viator/.gitignore`, `README.md`, `viator-tec/viator-tec.php`, `viator-tec/readme.txt`, `tests/package.json`, `tests/run.mjs`, `tests/php/bootstrap.php`, `tests/php/estrutura.test.php`

**Interfaces:**
- Produces: constantes `VTEC_VERSION`, `VTEC_DIR`, `VTEC_URL`, `VTEC_BASENAME`; helpers de teste `ok()`, `igual()`, `contem()`, `nao_contem()`, `fixture($nome)`; stubs WP listados no bootstrap.

- [ ] **Step 1: git init e .gitignore**

```bash
cd C:/Users/User/Documents/Wordpress/padrao/plugin-viator && git init -b main
printf 'dist/\ntests/node_modules/\n' > .gitignore
```

- [ ] **Step 2: `tests/package.json`** (mesmas dependências do Voucher Tec)

```json
{
  "name": "viator-tec-testes",
  "private": true,
  "type": "module",
  "description": "Testes do plugin Viator Tec (PHP 7.4 e 8.2 por php-wasm). Não vai no zip.",
  "scripts": { "test": "node run.mjs" },
  "devDependencies": { "@php-wasm/node": "*", "@php-wasm/universal": "*" }
}
```

Rodar: `cd tests && npm install` (copiar `package-lock.json` do Voucher Tec antes, para travar as versões: `cp ../../plugin-roteiros/tests/package-lock.json . && npm install`).

- [ ] **Step 3: `tests/run.mjs`**

```js
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
```

- [ ] **Step 4: `tests/php/bootstrap.php`** (stubs do WordPress usados pelo plugin inteiro; tasks seguintes não precisam mexer aqui)

```php
<?php
// Base dos testes: constantes, stubs mínimos do WordPress e verificadores. Não vai no zip.
define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'VTEC_TESTE', true );

$GLOBALS['vt_falhas']     = 0;
$GLOBALS['vt_opcoes']     = array();
$GLOBALS['vt_transients'] = array();   // chave => [valor, ttl]
$GLOBALS['vt_http']       = array();   // fila de respostas falsas para wp_remote_request
$GLOBALS['vt_http_log']   = array();   // [url, args] de cada chamada
$GLOBALS['vt_ganchos']    = array();   // gancho => [callbacks]
$GLOBALS['vt_regras']     = array();   // add_rewrite_rule
$GLOBALS['vt_sono']       = array();

function ok( $cond, $msg ) {
	echo ( $cond ? 'OK    ' : 'FALHA ' ) . $msg . "\n";
	if ( ! $cond ) { $GLOBALS['vt_falhas']++; }
}
function igual( $esperado, $atual, $msg ) {
	$bom = $esperado === $atual;
	echo ( $bom ? 'OK    ' : 'FALHA ' ) . $msg . "\n";
	if ( ! $bom ) {
		echo '      esperado: ' . var_export( $esperado, true ) . "\n      atual:    " . var_export( $atual, true ) . "\n";
		$GLOBALS['vt_falhas']++;
	}
}
function contem( $trecho, $texto, $msg ) { ok( false !== strpos( (string) $texto, $trecho ), $msg ); }
function nao_contem( $trecho, $texto, $msg ) { ok( false === strpos( (string) $texto, $trecho ), $msg ); }
function fixture( $nome ) { return json_decode( file_get_contents( '/f/' . $nome . '.json' ), true ); }
function resposta_falsa( $codigo, $corpo, $cabecalhos = array() ) {
	$GLOBALS['vt_http'][] = array( 'response' => array( 'code' => $codigo ), 'body' => is_string( $corpo ) ? $corpo : json_encode( $corpo ), 'headers' => $cabecalhos );
}

class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function remove_accents( $s ) {
	return strtr( $s, array( 'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ç'=>'C' ) );
}
function sanitize_title( $s ) {
	$s = strtolower( remove_accents( (string) $s ) );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	return trim( $s, '-' );
}
function home_url( $p = '' ) { return 'https://exemplo.test' . $p; }
function admin_url( $p = '' ) { return 'https://exemplo.test/wp-admin/' . $p; }
function get_bloginfo( $k ) { return 'Rede Turística'; }
function wp_trim_words( $t, $n = 55, $mais = '…' ) {
	$p = preg_split( '/\s+/', trim( strip_tags( $t ) ) );
	return count( $p ) > $n ? implode( ' ', array_slice( $p, 0, $n ) ) . $mais : implode( ' ', $p );
}

function get_option( $k, $padrao = false ) { return array_key_exists( $k, $GLOBALS['vt_opcoes'] ) ? $GLOBALS['vt_opcoes'][ $k ] : $padrao; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['vt_opcoes'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['vt_transients'][ $k ] ) ? $GLOBALS['vt_transients'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['vt_transients'][ $k ] = array( $v, $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['vt_transients'][ $k ] ); return true; }

function wp_remote_request( $url, $args ) {
	$GLOBALS['vt_http_log'][] = array( $url, $args );
	if ( ! $GLOBALS['vt_http'] ) { return new WP_Error( 'http_request_failed', 'sem resposta falsa na fila' ); }
	return array_shift( $GLOBALS['vt_http'] );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) && isset( $r['headers'][ $h ] ) ? $r['headers'][ $h ] : ''; }
function vtec_dormir( $s ) { $GLOBALS['vt_sono'][] = $s; }

function add_action( $g, $cb, $p = 10, $n = 1 ) { $GLOBALS['vt_ganchos'][ $g ][] = $cb; }
function add_filter( $g, $cb, $p = 10, $n = 1 ) { $GLOBALS['vt_ganchos'][ $g ][] = $cb; }
function add_rewrite_rule( $regex, $destino, $pos = 'bottom' ) { $GLOBALS['vt_regras'][ $regex ] = $destino; }
function register_activation_hook( $f, $cb ) {}
function plugin_dir_path( $f ) { return '/p/'; }
function plugin_dir_url( $f ) { return 'https://exemplo.test/wp-content/plugins/viator-tec/'; }
function plugin_basename( $f ) { return 'viator-tec/viator-tec.php'; }
```

- [ ] **Step 5: `tests/php/estrutura.test.php` (falha: plugin ainda não existe)**

```php
<?php
require '/t/bootstrap.php';
require '/p/viator-tec.php';
ok( defined( 'VTEC_VERSION' ), 'VTEC_VERSION definida' );
contem( 'Plugin Name: Viator Tec', file_get_contents( '/p/viator-tec.php' ), 'cabeçalho do plugin' );
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas', 'rest', 'admin', 'atualizador' ) as $f ) {
	ok( file_exists( "/p/includes/$f.php" ), "includes/$f.php existe" );
}
```

- [ ] **Step 6: rodar e ver falhar** — `cd tests && node run.mjs estrutura` → ERRO FATAL (arquivo não existe).

- [ ] **Step 7: `viator-tec/viator-tec.php`**

```php
<?php
/**
 * Plugin Name: Viator Tec
 * Description: Passeios da Viator (Partner API, afiliado) em /passeios/: destinos, lista de passeios e página do passeio com o botão Reservar na Viator.
 * Version:     0.1.0
 * Author:      TravelTec
 * Text Domain: viator-tec
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTEC_VERSION', '0.1.0' );
define( 'VTEC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VTEC_URL', plugin_dir_url( __FILE__ ) );
define( 'VTEC_BASENAME', plugin_basename( __FILE__ ) );

foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas', 'rest', 'admin', 'atualizador' ) as $vtec_arquivo ) {
	require_once VTEC_DIR . 'includes/' . $vtec_arquivo . '.php';
}

register_activation_hook( __FILE__, function () {
	vtec_rotas();
	flush_rewrite_rules();
	update_option( 'vtec_rotas_versao', VTEC_VERSION );
} );
```

Criar os 10 arquivos de `includes/` com só o cabeçalho (serão preenchidos nas próximas tasks):

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

Em `rotas.php`, por enquanto, também `function vtec_rotas() {}` (a Task 7 substitui).

- [ ] **Step 8: `viator-tec/readme.txt`**

```
=== Viator Tec ===
Requer: WordPress 6+, tema Hello Elementor (cabeçalho/rodapé pelo Theme Builder), PHP 7.4+.

Instalação: Plugins > Adicionar novo > Enviar plugin > viator-tec.zip > Ativar.
Configurações > Viator Tec: ambiente (sandbox/produção), chaves, campanha e destinos.

0.1.0 (26/09/2026): primeira versão — /passeios/, /passeios/<destino>/, /passeios/p/<código>-<slug>/.
```

- [ ] **Step 9: rodar** — `node run.mjs estrutura` → `TUDO OK` em 7.4 e 8.2.

- [ ] **Step 10: commit**

```bash
git add -A && git commit -m "0.1.0: esqueleto do plugin e testes em php-wasm"
```

---

### Task 2: Opções e sanitização

**Files:**
- Modify: `viator-tec/includes/opcoes.php`
- Test: `tests/php/opcoes.test.php`

**Interfaces:**
- Produces:
  - `vtec_opcoes_padrao(): array` — `['ambiente'=>'sandbox','chave_sandbox'=>'','chave_producao'=>'','campanha'=>'redeturistica-passeios','por_pagina'=>12,'destinos'=>[]]`
  - `vtec_opcoes(): array` — padrão + option `vtec_opcoes`
  - `vtec_sanitizar_opcoes(array $entrada, array $atual): array` — entrada do formulário (`ambiente`, `chave_sandbox`, `chave_producao`, `campanha`, `por_pagina`, `destino_id[]`, `destino_nome[]`, `destino_slug[]`, `destino_foto[]`)
  - `vtec_destino_por_slug(string $slug): ?array` — `['id'=>int,'nome'=>string,'slug'=>string,'foto'=>string]`
  - `vtec_chave_atual(): string`, `vtec_base_url(): string`

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
require '/p/includes/opcoes.php';

igual( 'sandbox', vtec_opcoes()['ambiente'], 'padrão é sandbox' );
igual( 'https://api.sandbox.viator.com/partner', vtec_base_url(), 'base sandbox' );

$atual = vtec_opcoes_padrao();
$atual['chave_sandbox'] = 'CHAVE-VELHA';
$s = vtec_sanitizar_opcoes( array(
	'ambiente' => 'producao', 'chave_sandbox' => '', 'chave_producao' => ' nova-prod ', 'campanha' => 'rede tur+1', 'por_pagina' => '999',
	'destino_id' => array( '684', '', '479', 'abc' ), 'destino_nome' => array( 'Las Vegas', 'Sem id', 'Paris', 'X' ),
	'destino_slug' => array( '', '', 'Paris!', '' ), 'destino_foto' => array( 'https://x.test/a.jpg', '', '', '' ),
), $atual );
igual( 'producao', $s['ambiente'], 'ambiente produção' );
igual( 'CHAVE-VELHA', $s['chave_sandbox'], 'chave vazia no formulário mantém a salva' );
igual( 'nova-prod', $s['chave_producao'], 'chave nova sem espaços' );
igual( 'rede-tur-1', $s['campanha'], 'campanha só com letras, números e hífen' );
igual( 50, $s['por_pagina'], 'por página no máximo 50' );
igual( 2, count( $s['destinos'] ), 'linhas sem id numérico ou sem nome são descartadas' );
igual( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/a.jpg' ), $s['destinos'][0], 'slug gerado pelo nome' );
igual( 'paris', $s['destinos'][1]['slug'], 'slug informado é limpo' );

$s2 = vtec_sanitizar_opcoes( array( 'ambiente' => 'hack', 'destino_id' => array( '1', '2', '3' ), 'destino_nome' => array( 'Rio', 'Rio', 'P' ), 'destino_slug' => array( '', '', 'p' ) ), $atual );
igual( 'sandbox', $s2['ambiente'], 'ambiente inválido vira sandbox' );
igual( array( 'rio', 'rio-2', 'p-2' ), array_column( $s2['destinos'], 'slug' ), 'slugs únicos e "p" é reservado' );

update_option( 'vtec_opcoes', $s );
igual( 'Las Vegas', vtec_destino_por_slug( 'las-vegas' )['nome'], 'destino por slug' );
igual( null, vtec_destino_por_slug( 'nao-existe' ), 'slug inexistente devolve null' );
igual( 'nova-prod', vtec_chave_atual(), 'chave do ambiente atual' );
igual( 'https://api.viator.com/partner', vtec_base_url(), 'base produção' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs opcoes` → ERRO FATAL (`vtec_opcoes` indefinida).

- [ ] **Step 3: implementação `includes/opcoes.php`**

```php
<?php
/** Opções do plugin (Configurações › Viator Tec), guardadas na option vtec_opcoes. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_opcoes_padrao() {
	return array(
		'ambiente'       => 'sandbox',
		'chave_sandbox'  => '',
		'chave_producao' => '',
		'campanha'       => 'redeturistica-passeios',
		'por_pagina'     => 12,
		'destinos'       => array(),
	);
}

function vtec_opcoes() {
	return array_merge( vtec_opcoes_padrao(), (array) get_option( 'vtec_opcoes', array() ) );
}

/**
 * Limpa o que veio do formulário. Chave em branco mantém a que já estava salva
 * (o campo de senha nunca é preenchido de volta na tela).
 */
function vtec_sanitizar_opcoes( $entrada, $atual ) {
	$s = vtec_opcoes_padrao();

	$s['ambiente'] = ( isset( $entrada['ambiente'] ) && 'producao' === $entrada['ambiente'] ) ? 'producao' : 'sandbox';
	foreach ( array( 'chave_sandbox', 'chave_producao' ) as $campo ) {
		$nova        = isset( $entrada[ $campo ] ) ? trim( sanitize_text_field( $entrada[ $campo ] ) ) : '';
		$s[ $campo ] = '' !== $nova ? $nova : ( isset( $atual[ $campo ] ) ? $atual[ $campo ] : '' );
	}
	$campanha      = isset( $entrada['campanha'] ) ? trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $entrada['campanha'] ) ), '-' ) : '';
	$s['campanha'] = '' !== $campanha ? substr( $campanha, 0, 200 ) : $s['campanha'];
	$por_pagina      = isset( $entrada['por_pagina'] ) ? (int) $entrada['por_pagina'] : 12;
	$s['por_pagina'] = min( 50, max( 1, $por_pagina ) );

	$ids    = isset( $entrada['destino_id'] ) ? (array) $entrada['destino_id'] : array();
	$usados = array();
	foreach ( $ids as $i => $id ) {
		$id   = trim( (string) $id );
		$nome = isset( $entrada['destino_nome'][ $i ] ) ? trim( sanitize_text_field( $entrada['destino_nome'][ $i ] ) ) : '';
		if ( ! ctype_digit( $id ) || '' === $nome ) {
			continue;
		}
		$slug = sanitize_title( isset( $entrada['destino_slug'][ $i ] ) && '' !== trim( $entrada['destino_slug'][ $i ] ) ? $entrada['destino_slug'][ $i ] : $nome );
		$base = $slug;
		$n    = 2;
		while ( 'p' === $slug || isset( $usados[ $slug ] ) ) {
			$slug = $base . '-' . $n++;
		}
		$usados[ $slug ] = true;
		$s['destinos'][] = array(
			'id'   => (int) $id,
			'nome' => $nome,
			'slug' => $slug,
			'foto' => isset( $entrada['destino_foto'][ $i ] ) ? esc_url_raw( trim( $entrada['destino_foto'][ $i ] ) ) : '',
		);
	}
	return $s;
}

function vtec_destino_por_slug( $slug ) {
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		if ( $d['slug'] === $slug ) {
			return $d;
		}
	}
	return null;
}

function vtec_chave_atual() {
	$o = vtec_opcoes();
	return 'producao' === $o['ambiente'] ? $o['chave_producao'] : $o['chave_sandbox'];
}

function vtec_base_url() {
	return 'producao' === vtec_opcoes()['ambiente'] ? 'https://api.viator.com/partner' : 'https://api.sandbox.viator.com/partner';
}
```

- [ ] **Step 4: rodar** — `node run.mjs opcoes` → TUDO OK.
- [ ] **Step 5: commit** — `git add -A && git commit -m "opções: ambiente, chaves, campanha e destinos"`

---

### Task 3: Cliente da API e cache

**Files:**
- Modify: `viator-tec/includes/api.php`, `viator-tec/includes/cache.php`
- Test: `tests/php/api.test.php`, `tests/php/cache.test.php`

**Interfaces:**
- Consumes: `vtec_chave_atual()`, `vtec_base_url()` (Task 2).
- Produces:
  - `vtec_api(string $metodo, string $caminho, ?array $corpo = null, array $query = []): array|WP_Error`
  - `vtec_cache(string $chave, int $ttl, callable $gerar): mixed|WP_Error` — devolve cache; senão gera; se gerar der erro, devolve a reserva (7 dias) ou o erro
  - `vtec_limpar_cache(): void` — incrementa a option `vtec_cache_geracao`

- [ ] **Step 1: `tests/php/api.test.php`**

```php
<?php
require '/t/bootstrap.php';
require '/p/includes/opcoes.php';
require '/p/includes/api.php';

$r = vtec_api( 'GET', '/destinations' );
igual( 'vtec_sem_chave', $r->get_error_code(), 'sem chave não chama a API' );
igual( 0, count( $GLOBALS['vt_http_log'] ), 'nenhuma chamada sem chave' );

update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K-TESTE' ) );
resposta_falsa( 200, array( 'ok' => 1 ) );
$r = vtec_api( 'POST', '/products/search', array( 'a' => 1 ), array( 'campaign-value' => 'x y' ) );
igual( array( 'ok' => 1 ), $r, 'devolve o JSON decodificado' );
list( $url, $args ) = $GLOBALS['vt_http_log'][0];
igual( 'https://api.sandbox.viator.com/partner/products/search?campaign-value=x+y', $url, 'URL com query' );
igual( 'K-TESTE', $args['headers']['exp-api-key'], 'cabeçalho da chave' );
igual( 'application/json;version=2.0', $args['headers']['Accept'], 'cabeçalho de versão' );
igual( 'pt-BR', $args['headers']['Accept-Language'], 'português' );
igual( '{"a":1}', $args['body'], 'corpo em JSON' );

resposta_falsa( 429, '{}', array( 'retry-after' => '3' ) );
resposta_falsa( 200, array( 'depois' => 1 ) );
igual( array( 'depois' => 1 ), vtec_api( 'GET', '/x' ), '429 espera e tenta de novo uma vez' );
igual( array( 3 ), $GLOBALS['vt_sono'], 'esperou o Retry-After' );

resposta_falsa( 429, '{}' );
resposta_falsa( 429, '{}' );
igual( 'vtec_http_429', vtec_api( 'GET', '/x' )->get_error_code(), 'segundo 429 vira erro' );

resposta_falsa( 400, array( 'code' => 'BAD_REQUEST', 'message' => 'Invalid product code: X' ) );
$e = vtec_api( 'GET', '/products/X' );
igual( 'vtec_http_400', $e->get_error_code(), 'erro HTTP vira WP_Error' );
igual( 'Invalid product code: X', $e->get_error_message(), 'mensagem da Viator preservada' );

resposta_falsa( 200, 'não é json' );
igual( 'vtec_json', vtec_api( 'GET', '/x' )->get_error_code(), 'JSON inválido vira erro' );
nao_contem( 'K-TESTE', $e->get_error_message(), 'a chave nunca vai na mensagem de erro' );
```

- [ ] **Step 2: `tests/php/cache.test.php`**

```php
<?php
require '/t/bootstrap.php';
require '/p/includes/cache.php';

$chamadas = 0;
$gerar = function () use ( &$chamadas ) { $chamadas++; return array( 'n' => $chamadas ); };
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, $gerar ), 'gera na primeira vez' );
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, $gerar ), 'usa o cache na segunda' );
igual( 1, $chamadas, 'gerou uma vez só' );

// cache principal venceu, API falhou -> usa a reserva
foreach ( array_keys( $GLOBALS['vt_transients'] ) as $k ) {
	if ( '_r' !== substr( $k, -2 ) ) { delete_transient( $k ); }
}
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, function () { return new WP_Error( 'vtec_http_500', 'fora' ); } ), 'erro usa a reserva' );

// sem reserva -> devolve o erro e não guarda nada
$r = vtec_cache( 'b', 3600, function () { return new WP_Error( 'vtec_http_500', 'fora' ); } );
ok( is_wp_error( $r ), 'sem reserva devolve o erro' );

vtec_limpar_cache();
igual( array( 'n' => 2 ), vtec_cache( 'a', 3600, $gerar ), 'limpar cache força gerar de novo' );
```

- [ ] **Step 3: rodar e ver falhar** — `node run.mjs api` e `node run.mjs cache` → ERRO FATAL.

- [ ] **Step 4: `includes/api.php`**

```php
<?php
/** Cliente HTTP da Viator Partner API v2. Nunca expõe a chave em mensagens. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vtec_dormir' ) ) {
	function vtec_dormir( $segundos ) {
		sleep( $segundos );
	}
}

function vtec_api( $metodo, $caminho, $corpo = null, $query = array() ) {
	$chave = vtec_chave_atual();
	if ( '' === $chave ) {
		return new WP_Error( 'vtec_sem_chave', 'Chave da Viator não configurada.' );
	}
	$url = vtec_base_url() . $caminho . ( $query ? '?' . http_build_query( $query ) : '' );
	$args = array(
		'method'  => $metodo,
		'timeout' => 20,
		'headers' => array(
			'exp-api-key'     => $chave,
			'Accept'          => 'application/json;version=2.0',
			'Accept-Language' => 'pt-BR',
			'Content-Type'    => 'application/json',
		),
	);
	if ( null !== $corpo ) {
		$args['body'] = wp_json_encode( $corpo );
	}

	for ( $tentativa = 1; $tentativa <= 2; $tentativa++ ) {
		$r = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'vtec_rede', 'Sem resposta da Viator: ' . $r->get_error_message() );
		}
		$codigo = (int) wp_remote_retrieve_response_code( $r );
		if ( 429 === $codigo && 1 === $tentativa ) {
			// Limite da janela de 10 s: espera o que a Viator pedir (no máximo 5 s) e tenta uma vez.
			vtec_dormir( min( 5, max( 1, (int) wp_remote_retrieve_header( $r, 'retry-after' ) ) ) );
			continue;
		}
		break;
	}

	$dados = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $codigo < 200 || $codigo >= 300 ) {
		$msg = is_array( $dados ) && isset( $dados['message'] ) ? (string) $dados['message'] : 'HTTP ' . $codigo;
		return new WP_Error( 'vtec_http_' . $codigo, $msg, array( 'status' => $codigo ) );
	}
	if ( ! is_array( $dados ) ) {
		return new WP_Error( 'vtec_json', 'Resposta inválida da Viator.' );
	}
	return $dados;
}
```

- [ ] **Step 5: `includes/cache.php`**

```php
<?php
/**
 * Cache das respostas da Viator em transients. Cada valor tem uma cópia de reserva de 7 dias,
 * usada quando a API falha depois que o cache principal venceu. "Limpar cache" só troca a
 * geração: as chaves antigas deixam de ser lidas e vencem sozinhas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_cache_chave( $chave ) {
	return 'vtec_' . (int) get_option( 'vtec_cache_geracao', 1 ) . '_' . md5( $chave );
}

function vtec_cache( $chave, $ttl, $gerar ) {
	$k = vtec_cache_chave( $chave );
	$v = get_transient( $k );
	if ( false !== $v ) {
		return $v;
	}
	$novo = call_user_func( $gerar );
	if ( is_wp_error( $novo ) ) {
		$reserva = get_transient( $k . '_r' );
		return false !== $reserva ? $reserva : $novo;
	}
	set_transient( $k, $novo, $ttl );
	set_transient( $k . '_r', $novo, 7 * DAY_IN_SECONDS );
	return $novo;
}

function vtec_limpar_cache() {
	update_option( 'vtec_cache_geracao', (int) get_option( 'vtec_cache_geracao', 1 ) + 1, false );
}
```

- [ ] **Step 6: rodar** — `node run.mjs api && node run.mjs cache` → TUDO OK.
- [ ] **Step 7: commit** — `git add -A && git commit -m "cliente da API (429, erros) e cache com reserva"`

---

### Task 4: Funções puras de dados

**Files:**
- Modify: `viator-tec/includes/dados.php`
- Test: `tests/php/dados.test.php`

**Interfaces:**
- Produces:
  - `vtec_preco_brl($valor): string` — `'R$ 1.234,50'` ou `''`
  - `vtec_minutos(int $m): string`, `vtec_duracao_texto(?array $d): string`
  - `vtec_imagem(?array $imagens, int $largura_min): string`, `vtec_galeria(?array $imagens, int $max = 10): string[]`
  - `vtec_url_produto(string $codigo, string $titulo): string`
  - `vtec_codigo_valido(string $codigo): bool` — `/^[A-Za-z0-9_]{3,30}$/`
  - `vtec_nota(?array $reviews): array` — `['nota'=>float,'total'=>int]` (aceita o formato da busca e do produto)
  - `vtec_card(array $p): array` — `['codigo','titulo','url','imagem','duracao','preco','cancelamento_gratis'(bool),'nota','total']`
  - `vtec_produto_view(array $p, ?float $preco): array` — `['codigo','titulo','paragrafos'(string[]),'galeria','duracao','preco','inclusoes','exclusoes','cancelamento','encontro','informacoes','link','nota','total']`

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
require '/p/includes/dados.php';

igual( 'R$ 954,87', vtec_preco_brl( 954.87 ), 'preço simples' );
igual( 'R$ 1.234,50', vtec_preco_brl( '1234.5' ), 'milhar e centavos' );
igual( '', vtec_preco_brl( 0 ), 'zero não mostra preço' );
igual( '', vtec_preco_brl( null ), 'sem preço' );

igual( '45 min', vtec_minutos( 45 ), 'minutos' );
igual( '1h30', vtec_minutos( 90 ), 'hora e meia' );
igual( '6h', vtec_minutos( 360 ), 'horas cheias' );
igual( '2 dias', vtec_minutos( 2880 ), 'dias' );
igual( '6h a 10h', vtec_duracao_texto( array( 'variableDurationFromMinutes' => 360, 'variableDurationToMinutes' => 600 ) ), 'duração variável' );
igual( '3h', vtec_duracao_texto( array( 'fixedDurationInMinutes' => 180 ) ), 'duração fixa' );
igual( 'Flexível', vtec_duracao_texto( array( 'unstructuredDuration' => 'Flexível' ) ), 'duração textual' );
igual( '', vtec_duracao_texto( null ), 'sem duração' );

$busca = fixture( 'search' );
$p     = $busca['products'][0];
$img   = vtec_imagem( $p['images'], 480 );
ok( '' !== $img && false !== strpos( $img, 'tripadvisor.com' ), 'imagem de capa escolhida' );
igual( '', vtec_imagem( array(), 480 ), 'sem imagens devolve vazio' );
igual( '', vtec_imagem( null, 480 ), 'imagens nulas' );

igual( 'https://exemplo.test/passeios/p/56549P1-passeio-a-ilha-mar/', vtec_url_produto( '56549P1', 'Passeio à "Ilha" & Mar' ), 'URL do produto com slug limpo' );
ok( vtec_codigo_valido( '56549P1' ) && vtec_codigo_valido( '5010SYDNEY' ), 'códigos válidos' );
ok( ! vtec_codigo_valido( '../x' ) && ! vtec_codigo_valido( 'ab' ), 'códigos inválidos' );

igual( array( 'nota' => 5.0, 'total' => 207 ), vtec_nota( $p['reviews'] ), 'nota da busca' );
$prod = fixture( 'product' );
igual( array( 'nota' => 5.0, 'total' => 207 ), vtec_nota( $prod['reviews'] ), 'nota do produto (soma das fontes)' );
igual( array( 'nota' => 0.0, 'total' => 0 ), vtec_nota( null ), 'sem avaliações' );

$c = vtec_card( $p );
igual( '56549P1', $c['codigo'], 'card: código' );
igual( 'R$ 954,87', $c['preco'], 'card: preço em R$' );
igual( '6h a 10h', $c['duracao'], 'card: duração' );
ok( true === $c['cancelamento_gratis'], 'card: cancelamento grátis' );
igual( 'https://exemplo.test/passeios/p/56549P1-vale-privado-do-fogo-caminhadas-e-aventura/', $c['url'], 'card: URL no site' );
$sem = vtec_card( array( 'productCode' => 'X123', 'title' => 'T', 'pricing' => array( 'summary' => array( 'fromPrice' => 10 ), 'currency' => 'USD' ) ) );
igual( '', $sem['preco'], 'preço em outra moeda não aparece como R$' );
igual( '', $sem['imagem'], 'card sem imagem' );

$v = vtec_produto_view( $prod, 5065.2 );
igual( 'Vale Privado do Fogo Caminhadas e Aventura', $v['titulo'], 'view: título' );
ok( count( $v['paragrafos'] ) >= 2, 'view: descrição em parágrafos' );
ok( count( $v['galeria'] ) >= 1 && count( $v['galeria'] ) <= 10, 'view: galeria até 10' );
contem( 'Guia profissional', implode( '|', $v['inclusoes'] ), 'view: inclusões' );
igual( array( 'Gorjetas' ), $v['exclusoes'], 'view: exclusões' );
contem( '24 horas', $v['cancelamento'], 'view: cancelamento' );
contem( 'Moapa', $v['encontro'], 'view: ponto de encontro' );
igual( 'R$ 5.065,20', $v['preco'], 'view: preço' );
contem( 'pid=', $v['link'], 'view: link de afiliado vindo da API' );
igual( '', vtec_produto_view( $prod, null )['preco'], 'view sem preço' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs dados`.

- [ ] **Step 3: `includes/dados.php`**

```php
<?php
/** Funções puras: transformam as respostas da Viator no que as páginas mostram. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_preco_brl( $valor ) {
	if ( ! is_numeric( $valor ) || (float) $valor <= 0 ) {
		return '';
	}
	return 'R$ ' . number_format( (float) $valor, 2, ',', '.' );
}

function vtec_minutos( $m ) {
	$m = (int) $m;
	if ( $m >= 1440 && 0 === $m % 1440 ) {
		$d = intdiv( $m, 1440 );
		return $d . ( 1 === $d ? ' dia' : ' dias' );
	}
	$h = intdiv( $m, 60 );
	$r = $m % 60;
	if ( 0 === $h ) {
		return $r . ' min';
	}
	return $h . 'h' . ( $r ? sprintf( '%02d', $r ) : '' );
}

function vtec_duracao_texto( $d ) {
	if ( ! is_array( $d ) ) {
		return '';
	}
	if ( isset( $d['fixedDurationInMinutes'] ) ) {
		return vtec_minutos( $d['fixedDurationInMinutes'] );
	}
	if ( isset( $d['variableDurationFromMinutes'], $d['variableDurationToMinutes'] ) ) {
		return vtec_minutos( $d['variableDurationFromMinutes'] ) . ' a ' . vtec_minutos( $d['variableDurationToMinutes'] );
	}
	return isset( $d['unstructuredDuration'] ) ? (string) $d['unstructuredDuration'] : '';
}

/** Menor variante com largura >= $largura_min da imagem de capa (ou a maior, se nenhuma alcança). */
function vtec_imagem( $imagens, $largura_min ) {
	if ( ! is_array( $imagens ) || ! $imagens ) {
		return '';
	}
	$capa = $imagens[0];
	foreach ( $imagens as $i ) {
		if ( ! empty( $i['isCover'] ) ) {
			$capa = $i;
			break;
		}
	}
	return vtec_variante( $capa, $largura_min );
}

function vtec_variante( $imagem, $largura_min ) {
	$variantes = isset( $imagem['variants'] ) ? $imagem['variants'] : array();
	usort( $variantes, function ( $a, $b ) {
		return (int) $a['width'] - (int) $b['width'];
	} );
	foreach ( $variantes as $v ) {
		if ( (int) $v['width'] >= $largura_min ) {
			return (string) $v['url'];
		}
	}
	return $variantes ? (string) end( $variantes )['url'] : '';
}

function vtec_galeria( $imagens, $max = 10 ) {
	$urls = array();
	foreach ( is_array( $imagens ) ? $imagens : array() as $i ) {
		$u = vtec_variante( $i, 720 );
		if ( '' !== $u ) {
			$urls[] = $u;
		}
		if ( count( $urls ) >= $max ) {
			break;
		}
	}
	return $urls;
}

function vtec_url_produto( $codigo, $titulo ) {
	return home_url( '/passeios/p/' . $codigo . '-' . sanitize_title( $titulo ) . '/' );
}

function vtec_codigo_valido( $codigo ) {
	return (bool) preg_match( '/^[A-Za-z0-9_]{3,30}$/', (string) $codigo );
}

/** Nota e total de avaliações; a busca traz combinedAverageRating, o produto só as fontes. */
function vtec_nota( $reviews ) {
	if ( ! is_array( $reviews ) ) {
		return array( 'nota' => 0.0, 'total' => 0 );
	}
	if ( isset( $reviews['combinedAverageRating'] ) ) {
		return array( 'nota' => round( (float) $reviews['combinedAverageRating'], 1 ), 'total' => (int) ( isset( $reviews['totalReviews'] ) ? $reviews['totalReviews'] : 0 ) );
	}
	$total = 0;
	$soma  = 0.0;
	foreach ( isset( $reviews['sources'] ) ? $reviews['sources'] : array() as $f ) {
		$total += (int) $f['totalCount'];
		$soma  += (float) $f['averageRating'] * (int) $f['totalCount'];
	}
	return array( 'nota' => $total ? round( $soma / $total, 1 ) : 0.0, 'total' => $total );
}

function vtec_card( $p ) {
	$preco = isset( $p['pricing']['currency'], $p['pricing']['summary']['fromPrice'] ) && 'BRL' === $p['pricing']['currency']
		? vtec_preco_brl( $p['pricing']['summary']['fromPrice'] ) : '';
	$nota  = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
	return array(
		'codigo'              => (string) $p['productCode'],
		'titulo'              => (string) $p['title'],
		'url'                 => vtec_url_produto( $p['productCode'], $p['title'] ),
		'imagem'              => vtec_imagem( isset( $p['images'] ) ? $p['images'] : null, 480 ),
		'duracao'             => vtec_duracao_texto( isset( $p['duration'] ) ? $p['duration'] : null ),
		'preco'               => $preco,
		'cancelamento_gratis' => isset( $p['flags'] ) && in_array( 'FREE_CANCELLATION', (array) $p['flags'], true ),
		'nota'                => $nota['nota'],
		'total'               => $nota['total'],
	);
}

function vtec_textos_itens( $itens ) {
	$textos = array();
	foreach ( is_array( $itens ) ? $itens : array() as $i ) {
		foreach ( array( 'otherDescription', 'description', 'typeDescription' ) as $campo ) {
			if ( ! empty( $i[ $campo ] ) ) {
				$textos[] = (string) $i[ $campo ];
				break;
			}
		}
	}
	return $textos;
}

function vtec_produto_view( $p, $preco ) {
	$descricao  = isset( $p['description'] ) ? (string) $p['description'] : '';
	$paragrafos = array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $descricao ) ) ) );
	$duracao    = isset( $p['itinerary']['duration'] ) ? $p['itinerary']['duration'] : null;
	$nota       = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
	return array(
		'codigo'       => (string) $p['productCode'],
		'titulo'       => (string) $p['title'],
		'paragrafos'   => $paragrafos,
		'galeria'      => vtec_galeria( isset( $p['images'] ) ? $p['images'] : null ),
		'duracao'      => vtec_duracao_texto( $duracao ),
		'preco'        => null === $preco ? '' : vtec_preco_brl( $preco ),
		'inclusoes'    => vtec_textos_itens( isset( $p['inclusions'] ) ? $p['inclusions'] : null ),
		'exclusoes'    => vtec_textos_itens( isset( $p['exclusions'] ) ? $p['exclusions'] : null ),
		'cancelamento' => isset( $p['cancellationPolicy']['description'] ) ? (string) $p['cancellationPolicy']['description'] : '',
		'encontro'     => isset( $p['logistics']['start'][0]['description'] ) ? (string) $p['logistics']['start'][0]['description'] : '',
		'informacoes'  => vtec_textos_itens( isset( $p['additionalInfo'] ) ? $p['additionalInfo'] : null ),
		'link'         => isset( $p['productUrl'] ) ? (string) $p['productUrl'] : '',
		'nota'         => $nota['nota'],
		'total'        => $nota['total'],
	);
}
```

- [ ] **Step 4: rodar** — `node run.mjs dados` → TUDO OK. Se o teste de exclusões falhar porque a fixture tem mais itens, ajustar o esperado ao que `fixture('product')['exclusions']` contém (a fixture é o dado real de 26/09).
- [ ] **Step 5: commit** — `git add -A && git commit -m "dados: preço, duração, imagem, nota, card e página do passeio"`

---

### Task 5: Serviço (busca, produto, preço, destinos) e notas guardadas

**Files:**
- Modify: `viator-tec/includes/servico.php`
- Test: `tests/php/servico.test.php`

**Interfaces:**
- Consumes: `vtec_api`, `vtec_cache`, `vtec_card`, `vtec_nota`, `vtec_codigo_valido`, `vtec_opcoes`.
- Produces:
  - `vtec_buscar(int $destino_id, string $ordem, int $inicio, int $qtd): array|WP_Error` — `['cards'=>array[], 'total'=>int]`; `$ordem` ∈ `avaliacao` | `preco` (outro valor → `avaliacao`); guarda as notas dos cards
  - `vtec_produto(string $codigo): array|WP_Error` — resposta crua de `GET /products/{code}?campaign-value=<campanha>`; guarda a nota
  - `vtec_preco_a_partir(string $codigo): ?float` — `schedules.summary.fromPrice` convertido para BRL por `/exchange-rates`
  - `vtec_destinos_viator(): array|WP_Error` — `[['id'=>int,'nome'=>string,'tipo'=>string]]`
  - `vtec_filtrar_destinos(array $lista, string $termo, int $max = 20): array`
  - `vtec_guardar_nota(string $codigo, float $nota, int $total): void`, `vtec_ler_nota(string $codigo): ?array`

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K' ) );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_buscar( 684, 'preco', 1, 3 );
igual( 1309, $r['total'], 'total da busca' );
igual( 3, count( $r['cards'] ), 'três cards' );
$corpo = json_decode( $GLOBALS['vt_http_log'][0][1]['body'], true );
igual( array( 'sort' => 'PRICE', 'order' => 'ASCENDING' ), $corpo['sorting'], 'ordem por preço' );
igual( '684', $corpo['filtering']['destination'], 'destino como texto' );
igual( 'BRL', $corpo['currency'], 'moeda BRL' );
igual( array( 'nota' => 5.0, 'total' => 207 ), vtec_ler_nota( '56549P1' ), 'nota guardada para o JS protegido' );

vtec_buscar( 684, 'preco', 1, 3 );
igual( 1, count( $GLOBALS['vt_http_log'] ), 'segunda busca igual vem do cache' );
resposta_falsa( 200, fixture( 'search' ) );
vtec_buscar( 684, 'qualquer', 1, 3 );
igual( array( 'sort' => 'TRAVELER_RATING', 'order' => 'DESCENDING' ), json_decode( $GLOBALS['vt_http_log'][1][1]['body'], true )['sorting'], 'ordem inválida vira avaliação' );

ok( is_wp_error( vtec_produto( '../etc' ) ), 'código inválido nem chama a API' );
resposta_falsa( 200, fixture( 'product' ) );
$p = vtec_produto( '56549P1' );
igual( '56549P1', $p['productCode'], 'produto' );
contem( '/products/56549P1?campaign-value=redeturistica-passeios', end( $GLOBALS['vt_http_log'] )[0], 'campanha na chamada do produto' );

resposta_falsa( 200, fixture( 'schedules' ) );   // USD 180
resposta_falsa( 200, fixture( 'exchange' ) );    // 5.30484035
igual( 954.87, vtec_preco_a_partir( '56549P1' ), 'preço convertido de USD para BRL' );
resposta_falsa( 500, '{}' );
igual( null, vtec_preco_a_partir( 'OUTRO1' ), 'erro no preço devolve null' );

resposta_falsa( 200, fixture( 'destinations' ) );
$d = vtec_destinos_viator();
ok( count( $d ) >= 3 && isset( $d[0]['id'], $d[0]['nome'], $d[0]['tipo'] ), 'destinos da Viator' );
igual( array( 'Las Vegas' ), array_column( vtec_filtrar_destinos( $d, 'vegas' ), 'nome' ), 'filtro por nome, sem maiúsculas' );
igual( array(), vtec_filtrar_destinos( $d, '' ), 'termo vazio não lista nada' );
```

Antes de rodar, conferir em `fixtures/destinations.json` que Las Vegas (684) está lá e ajustar o esperado do filtro ao conteúdo real.

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs servico`.

- [ ] **Step 3: `includes/servico.php`**

```php
<?php
/** O que as páginas pedem à Viator, já com cache. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_guardar_nota( $codigo, $nota, $total ) {
	if ( $total > 0 ) {
		set_transient( 'vtec_nota_' . $codigo, array( 'nota' => (float) $nota, 'total' => (int) $total ), 12 * HOUR_IN_SECONDS );
	}
}

function vtec_ler_nota( $codigo ) {
	$n = get_transient( 'vtec_nota_' . $codigo );
	return is_array( $n ) ? $n : null;
}

function vtec_buscar( $destino_id, $ordem, $inicio, $qtd ) {
	$ordens = array(
		'avaliacao' => array( 'sort' => 'TRAVELER_RATING', 'order' => 'DESCENDING' ),
		'preco'     => array( 'sort' => 'PRICE', 'order' => 'ASCENDING' ),
	);
	$corpo = array(
		'filtering'  => array( 'destination' => (string) (int) $destino_id ),
		'sorting'    => isset( $ordens[ $ordem ] ) ? $ordens[ $ordem ] : $ordens['avaliacao'],
		'pagination' => array( 'start' => max( 1, (int) $inicio ), 'count' => min( 50, max( 1, (int) $qtd ) ) ),
		'currency'   => 'BRL',
	);
	$r = vtec_cache( 'busca|' . wp_json_encode( $corpo ), 12 * HOUR_IN_SECONDS, function () use ( $corpo ) {
		return vtec_api( 'POST', '/products/search', $corpo );
	} );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$cards = array_map( 'vtec_card', isset( $r['products'] ) ? $r['products'] : array() );
	foreach ( $cards as $c ) {
		vtec_guardar_nota( $c['codigo'], $c['nota'], $c['total'] );
	}
	return array( 'cards' => $cards, 'total' => (int) ( isset( $r['totalCount'] ) ? $r['totalCount'] : 0 ) );
}

function vtec_produto( $codigo ) {
	if ( ! vtec_codigo_valido( $codigo ) ) {
		return new WP_Error( 'vtec_codigo', 'Código de passeio inválido.' );
	}
	$campanha = vtec_opcoes()['campanha'];
	$p = vtec_cache( 'produto|' . $codigo . '|' . $campanha, 12 * HOUR_IN_SECONDS, function () use ( $codigo, $campanha ) {
		return vtec_api( 'GET', '/products/' . $codigo, null, array( 'campaign-value' => $campanha ) );
	} );
	if ( ! is_wp_error( $p ) ) {
		$n = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
		vtec_guardar_nota( $codigo, $n['nota'], $n['total'] );
	}
	return $p;
}

/** "A partir de" em BRL: o calendário vem na moeda do fornecedor, convertida pelo câmbio da Viator. */
function vtec_preco_a_partir( $codigo ) {
	$s = vtec_cache( 'agenda|' . $codigo, 12 * HOUR_IN_SECONDS, function () use ( $codigo ) {
		return vtec_api( 'GET', '/availability/schedules/' . $codigo );
	} );
	if ( is_wp_error( $s ) || ! isset( $s['summary']['fromPrice'], $s['currency'] ) ) {
		return null;
	}
	$valor = (float) $s['summary']['fromPrice'];
	if ( 'BRL' === $s['currency'] ) {
		return round( $valor, 2 );
	}
	$moeda = (string) $s['currency'];
	$c = vtec_cache( 'cambio|' . $moeda, 12 * HOUR_IN_SECONDS, function () use ( $moeda ) {
		return vtec_api( 'POST', '/exchange-rates', array( 'sourceCurrencies' => array( $moeda ), 'targetCurrencies' => array( 'BRL' ) ) );
	} );
	if ( is_wp_error( $c ) || empty( $c['rates'][0]['rate'] ) ) {
		return null;
	}
	return round( $valor * (float) $c['rates'][0]['rate'], 2 );
}

function vtec_destinos_viator() {
	$r = vtec_cache( 'destinos', 7 * DAY_IN_SECONDS, function () {
		return vtec_api( 'GET', '/destinations' );
	} );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$lista = array();
	foreach ( isset( $r['destinations'] ) ? $r['destinations'] : array() as $d ) {
		$lista[] = array( 'id' => (int) $d['destinationId'], 'nome' => (string) $d['name'], 'tipo' => (string) $d['type'] );
	}
	return $lista;
}

function vtec_filtrar_destinos( $lista, $termo, $max = 20 ) {
	$termo = strtolower( remove_accents( trim( (string) $termo ) ) );
	if ( '' === $termo ) {
		return array();
	}
	$achados = array();
	foreach ( $lista as $d ) {
		if ( false !== strpos( strtolower( remove_accents( $d['nome'] ) ), $termo ) ) {
			$achados[] = $d;
			if ( count( $achados ) >= $max ) {
				break;
			}
		}
	}
	return $achados;
}
```

- [ ] **Step 4: rodar** — `node run.mjs servico` → TUDO OK.
- [ ] **Step 5: commit** — `git add -A && git commit -m "serviço: busca, produto, preço em BRL e destinos"`

---

### Task 6: HTML das páginas (render) + CSS + JS

**Files:**
- Modify: `viator-tec/includes/render.php`
- Create: `viator-tec/assets/viator-tec.css`, `viator-tec/assets/viator-tec.js`, `viator-tec/assets/protegido.js`
- Test: `tests/php/render.test.php`

**Interfaces:**
- Consumes: formatos de `vtec_card()` e `vtec_produto_view()` (Task 4).
- Produces (todas devolvem string HTML, sem efeitos colaterais):
  - `vtec_html_destinos(array $destinos): string` — cada item `['nome','slug','foto']`
  - `vtec_html_cards(array $cards): string`
  - `vtec_html_destino(array $destino, array $cards, int $total, string $ordem, int $por_pagina): string`
  - `vtec_html_produto(array $v): string`
  - `vtec_html_indisponivel(): string`
  - `vtec_html_nota_vazia(string $codigo): string` — `<span class="vtec-nota" data-codigo="..."></span>`

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'dados', 'render' ) as $f ) { require "/p/includes/$f.php"; }

$h = vtec_html_destinos( array( array( 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/f.jpg' ), array( 'nome' => 'Sem <foto>', 'slug' => 'sem-foto', 'foto' => '' ) ) );
contem( 'href="https://exemplo.test/passeios/las-vegas/"', $h, 'link do destino' );
contem( 'Sem &lt;foto&gt;', $h, 'nome escapado' );
nao_contem( 'src=""', $h, 'sem img vazia' );

$card = array( 'codigo' => 'A1B', 'titulo' => 'Tour "top" <b>', 'url' => 'https://exemplo.test/passeios/p/A1B-tour/', 'imagem' => '', 'duracao' => '', 'preco' => '', 'cancelamento_gratis' => true, 'nota' => 4.9, 'total' => 321 );
$c = vtec_html_cards( array( $card ) );
contem( 'Tour &quot;top&quot; &lt;b&gt;', $c, 'título escapado' );
nao_contem( 'src=""', $c, 'card sem imagem não tem img vazia' );
nao_contem( 'R$', $c, 'card sem preço não mostra R$' );
nao_contem( '321', $c, 'total de avaliações fora do HTML' );
nao_contem( '4,9', $c, 'nota fora do HTML' );
contem( 'data-codigo="A1B"', $c, 'lugar da nota para o JS protegido' );
contem( 'Cancelamento grátis', $c, 'selo de cancelamento' );

$d = vtec_html_destino( array( 'nome' => 'Las Vegas', 'slug' => 'las-vegas' ), array( $card ), 30, 'preco', 12 );
contem( '<h1', $d, 'título da página' );
contem( 'Passeios em Las Vegas', $d, 'título com destino' );
contem( 'data-inicio="13"', $d, 'carregar mais começa no 13' );
contem( 'A reserva e o pagamento são feitos no site da Viator.', $d, 'aviso fixo' );
contem( 'aria-current="true"', $d, 'ordem atual marcada' );
nao_contem( 'vtec-mais', vtec_html_destino( array( 'nome' => 'X', 'slug' => 'x' ), array( $card ), 1, 'avaliacao', 12 ), 'sem botão quando cabe tudo' );
contem( 'Nenhum passeio', vtec_html_destino( array( 'nome' => 'X', 'slug' => 'x' ), array(), 0, 'avaliacao', 12 ), 'destino vazio' );

$v = vtec_produto_view( fixture( 'product' ), 5065.2 );
$p = vtec_html_produto( $v );
contem( 'Reservar na Viator', $p, 'botão reservar' );
contem( 'target="_blank" rel="noopener sponsored"', $p, 'nova aba e rel de afiliado' );
contem( esc_url( $v['link'] ), $p, 'link de afiliado' );
contem( 'R$ 5.065,20', $p, 'preço' );
contem( 'A reserva e o pagamento são feitos no site da Viator.', $p, 'aviso fixo' );
contem( 'data-codigo="56549P1"', $p, 'nota pelo JS' );
nao_contem( '207', $p, 'total de avaliações fora do HTML' );
$v['preco'] = '';
nao_contem( 'A partir de', vtec_html_produto( $v ), 'sem preço não mostra "a partir de"' );
contem( 'indisponíveis no momento', vtec_html_indisponivel(), 'mensagem de indisponível' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs render`.

- [ ] **Step 3: `includes/render.php`**

```php
<?php
/** HTML das páginas. Só recebe dados prontos e devolve texto; tudo escapado. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTEC_AVISO', 'A reserva e o pagamento são feitos no site da Viator.' );

function vtec_html_nota_vazia( $codigo ) {
	return '<span class="vtec-nota" data-codigo="' . esc_attr( $codigo ) . '"></span>';
}

function vtec_html_img( $url, $alt, $classe ) {
	return '' === $url ? '<div class="' . esc_attr( $classe ) . ' vtec-sem-foto"></div>'
		: '<img class="' . esc_attr( $classe ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
}

function vtec_html_destinos( $destinos ) {
	$h = '<div class="vtec"><h1 class="vtec-titulo">Passeios e ingressos</h1><div class="vtec-grade vtec-grade-destinos">';
	foreach ( $destinos as $d ) {
		$h .= '<a class="vtec-destino" href="' . esc_url( home_url( '/passeios/' . $d['slug'] . '/' ) ) . '">'
			. vtec_html_img( $d['foto'], $d['nome'], 'vtec-destino-foto' )
			. '<span class="vtec-destino-nome">' . esc_html( $d['nome'] ) . '</span></a>';
	}
	return $h . '</div></div>';
}

function vtec_html_cards( $cards ) {
	$h = '';
	foreach ( $cards as $c ) {
		$h .= '<a class="vtec-card" href="' . esc_url( $c['url'] ) . '">'
			. vtec_html_img( $c['imagem'], $c['titulo'], 'vtec-card-foto' )
			. '<span class="vtec-card-corpo">'
			. vtec_html_nota_vazia( $c['codigo'] )
			. '<span class="vtec-card-titulo">' . esc_html( $c['titulo'] ) . '</span>'
			. ( '' !== $c['duracao'] ? '<span class="vtec-card-duracao">' . esc_html( $c['duracao'] ) . '</span>' : '' )
			. ( $c['cancelamento_gratis'] ? '<span class="vtec-selo">Cancelamento grátis</span>' : '' )
			. ( '' !== $c['preco'] ? '<span class="vtec-card-preco">a partir de <strong>' . esc_html( $c['preco'] ) . '</strong></span>' : '' )
			. '</span></a>';
	}
	return $h;
}

function vtec_html_destino( $destino, $cards, $total, $ordem, $por_pagina ) {
	$base = home_url( '/passeios/' . $destino['slug'] . '/' );
	$h    = '<div class="vtec"><p class="vtec-voltar"><a href="' . esc_url( home_url( '/passeios/' ) ) . '">← Todos os destinos</a></p>'
		. '<h1 class="vtec-titulo">Passeios em ' . esc_html( $destino['nome'] ) . '</h1>'
		. '<p class="vtec-aviso">' . esc_html( VTEC_AVISO ) . '</p>';
	if ( ! $cards ) {
		return $h . '<p class="vtec-vazio">Nenhum passeio encontrado neste destino.</p></div>';
	}
	$h .= '<nav class="vtec-ordem">Ordenar: ';
	foreach ( array( 'avaliacao' => 'Mais bem avaliados', 'preco' => 'Menor preço' ) as $valor => $rotulo ) {
		$url = 'avaliacao' === $valor ? $base : $base . '?ordem=' . $valor;
		$h  .= '<a href="' . esc_url( $url ) . '"' . ( $valor === $ordem ? ' aria-current="true"' : '' ) . '>' . esc_html( $rotulo ) . '</a> ';
	}
	$h .= '</nav><div class="vtec-grade vtec-grade-cards">' . vtec_html_cards( $cards ) . '</div>';
	if ( $total > count( $cards ) ) {
		$h .= '<button type="button" class="vtec-mais" data-destino="' . esc_attr( $destino['slug'] ) . '" data-ordem="' . esc_attr( $ordem ) . '" data-inicio="' . ( count( $cards ) + 1 ) . '" data-por-pagina="' . (int) $por_pagina . '">Carregar mais passeios</button>';
	}
	return $h . '</div>';
}

function vtec_html_lista( $titulo, $itens ) {
	if ( ! $itens ) {
		return '';
	}
	$h = '<h2>' . esc_html( $titulo ) . '</h2><ul class="vtec-lista">';
	foreach ( $itens as $i ) {
		$h .= '<li>' . esc_html( $i ) . '</li>';
	}
	return $h . '</ul>';
}

function vtec_html_produto( $v ) {
	$h = '<div class="vtec vtec-produto"><h1 class="vtec-titulo">' . esc_html( $v['titulo'] ) . '</h1>' . vtec_html_nota_vazia( $v['codigo'] );
	if ( $v['galeria'] ) {
		$h .= '<div class="vtec-galeria">';
		foreach ( $v['galeria'] as $i => $url ) {
			$h .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $v['titulo'] ) . '"' . ( $i ? ' loading="lazy"' : '' ) . '>';
		}
		$h .= '</div>';
	}
	$h .= '<div class="vtec-produto-colunas"><div class="vtec-produto-texto">';
	foreach ( $v['paragrafos'] as $par ) {
		$h .= '<p>' . esc_html( $par ) . '</p>';
	}
	$h .= vtec_html_lista( 'O que está incluído', $v['inclusoes'] )
		. vtec_html_lista( 'Não está incluído', $v['exclusoes'] )
		. ( '' !== $v['encontro'] ? '<h2>Ponto de encontro</h2><p>' . esc_html( $v['encontro'] ) . '</p>' : '' )
		. vtec_html_lista( 'Informações importantes', $v['informacoes'] )
		. ( '' !== $v['cancelamento'] ? '<h2>Cancelamento</h2><p>' . esc_html( $v['cancelamento'] ) . '</p>' : '' )
		. '</div><aside class="vtec-reserva">'
		. ( '' !== $v['preco'] ? '<p class="vtec-reserva-preco">A partir de <strong>' . esc_html( $v['preco'] ) . '</strong> por pessoa</p>' : '' )
		. ( '' !== $v['duracao'] ? '<p class="vtec-reserva-duracao">Duração: ' . esc_html( $v['duracao'] ) . '</p>' : '' )
		. ( '' !== $v['link'] ? '<a class="vtec-botao" href="' . esc_url( $v['link'] ) . '" target="_blank" rel="noopener sponsored">Reservar na Viator</a>' : '' )
		. '<p class="vtec-aviso">' . esc_html( VTEC_AVISO ) . '</p></aside></div></div>';
	return $h;
}

function vtec_html_indisponivel() {
	return '<div class="vtec"><p class="vtec-vazio">Passeios indisponíveis no momento. Tente de novo em alguns minutos.</p></div>';
}
```

- [ ] **Step 4: rodar** — `node run.mjs render` → TUDO OK.

- [ ] **Step 5: `assets/viator-tec.css`**

```css
.vtec{max-width:1200px;margin:0 auto;padding:32px 16px 64px;color:#3C3D3D}
.vtec a{color:inherit;text-decoration:none}
.vtec-titulo{font-size:clamp(26px,4vw,38px);margin:8px 0 12px}
.vtec-aviso{font-size:14px;opacity:.8;margin:0 0 20px}
.vtec-voltar a{color:#E98300;font-weight:600}
.vtec-grade{display:grid;gap:20px;grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
.vtec-destino,.vtec-card{display:flex;flex-direction:column;border:1px solid #D9D9D9;border-radius:8px;overflow:hidden;background:#fff;transition:box-shadow .2s}
.vtec-destino:hover,.vtec-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.08)}
.vtec-destino-foto,.vtec-card-foto{width:100%;aspect-ratio:3/2;object-fit:cover;display:block}
.vtec-sem-foto{background:#F4F4F4}
.vtec-destino-nome{padding:14px 16px;font-weight:700;font-size:18px}
.vtec-card-corpo{display:flex;flex-direction:column;gap:6px;padding:14px 16px 18px;flex:1}
.vtec-card-titulo{font-weight:700;line-height:1.3}
.vtec-card-duracao{font-size:14px;opacity:.8}
.vtec-selo{align-self:flex-start;font-size:12px;font-weight:600;color:#1a7f37;background:#e9f7ee;border-radius:8px;padding:2px 8px}
.vtec-card-preco{margin-top:auto;font-size:14px}
.vtec-card-preco strong{font-size:18px;color:#E98300}
.vtec-nota{font-size:14px;min-height:1em}
.vtec-nota b{color:#E98300}
.vtec-ordem{margin:0 0 16px;font-size:14px}
.vtec-ordem a{margin-left:8px;padding:4px 10px;border:1px solid #D9D9D9;border-radius:8px}
.vtec-ordem a[aria-current]{border-color:#E98300;color:#E98300;font-weight:600}
.vtec-mais,.vtec-botao{display:block;margin:28px auto 0;padding:14px 28px;background:#E98300;color:#fff!important;border:0;border-radius:8px;font-weight:700;font-size:16px;cursor:pointer;text-align:center}
.vtec-mais[disabled]{opacity:.6;cursor:wait}
.vtec-vazio{padding:40px 0;font-size:18px}
.vtec-galeria{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;margin:12px 0 24px;border-radius:8px}
.vtec-galeria img{height:360px;width:auto;max-width:90%;object-fit:cover;scroll-snap-align:start;border-radius:8px}
.vtec-produto-colunas{display:grid;grid-template-columns:1fr 340px;gap:32px;align-items:start}
.vtec-produto-texto h2{font-size:22px;margin:28px 0 10px}
.vtec-lista{padding-left:20px}
.vtec-reserva{position:sticky;top:110px;border:1px solid #D9D9D9;border-radius:8px;padding:20px}
.vtec-reserva-preco strong{font-size:26px;color:#E98300}
.vtec-reserva .vtec-botao{margin:16px 0 12px}
@media (max-width:860px){.vtec-produto-colunas{grid-template-columns:1fr}.vtec-reserva{position:static;order:-1}.vtec-galeria img{height:240px}}
```

- [ ] **Step 6: `assets/viator-tec.js`** (carregar mais)

```js
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
```

- [ ] **Step 7: `assets/protegido.js`** (notas; bloqueado no robots.txt)

```js
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
```

- [ ] **Step 8: commit** — `git add -A && git commit -m "render: destinos, cards, destino e página do passeio + CSS/JS"`

---

### Task 7: Rotas, template, 404, SEO e robots.txt

**Files:**
- Modify: `viator-tec/includes/rotas.php`
- Create: `viator-tec/templates/pagina.php`
- Test: `tests/php/rotas.test.php`

**Interfaces:**
- Consumes: `vtec_destino_por_slug`, `vtec_opcoes`, `vtec_buscar`, `vtec_produto`, `vtec_preco_a_partir`, `vtec_produto_view`, `vtec_codigo_valido`, `vtec_url_produto`, `vtec_imagem`, render (Task 6).
- Produces:
  - `vtec_rotas(): void` — 3 regras de rewrite
  - `vtec_resolver_pagina(string $pagina, string $destino, string $codigo, string $ordem): array` — `['status'=>200|404, 'titulo'=>string, 'descricao'=>string, 'canonica'=>string, 'html'=>string]`
  - `vtec_robots(string $txt): string`
  - global `$GLOBALS['vtec_atual']` com o resultado de `vtec_resolver_pagina` durante a requisição

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );

vtec_rotas();
$regras = $GLOBALS['vt_regras'];
igual( 'index.php?vtec_pagina=destinos', $regras['^passeios/?$'], 'regra /passeios/' );
$regra_produto = '^passeios/p/([A-Za-z0-9_]+)(?:-[^/]*)?/?$';
ok( isset( $regras[ $regra_produto ] ), 'regra do produto' );
ok( 1 === preg_match( '#' . $regra_produto . '#', 'passeios/p/56549P1-passeio-a-ilha-mar/', $m ) && '56549P1' === $m[1], 'regex pega o código com qualquer slug' );
ok( 1 === preg_match( '#' . $regra_produto . '#', 'passeios/p/56549P1/' ), 'regex aceita sem slug' );
ok( isset( $regras['^passeios/([^/]+)/?$'] ), 'regra do destino' );
igual( array( '^passeios/?$', $regra_produto, '^passeios/([^/]+)/?$' ), array_keys( $regras ), 'produto antes do destino' );

$r = vtec_resolver_pagina( 'destino', 'nao-existe', '', '' );
igual( 404, $r['status'], 'destino fora da lista = 404' );
igual( 404, vtec_resolver_pagina( 'produto', '', '../x', '' )['status'], 'código inválido = 404' );
resposta_falsa( 400, array( 'message' => 'Invalid product code: ZZZ999' ) );
igual( 404, vtec_resolver_pagina( 'produto', '', 'ZZZ999', '' )['status'], 'código inexistente na Viator = 404' );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', 'preco' );
igual( 200, $r['status'], 'destino ok' );
contem( 'Passeios em Las Vegas', $r['html'], 'html do destino' );
igual( 'Passeios em Las Vegas', $r['titulo'], 'título SEO' );
igual( 'https://exemplo.test/passeios/las-vegas/', $r['canonica'], 'canônica sem ?ordem' );

resposta_falsa( 500, '{}' );
vtec_limpar_cache();
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', '' );
igual( 200, $r['status'], 'API fora sem cache: 200 com aviso' );
contem( 'indisponíveis no momento', $r['html'], 'mensagem de indisponível' );

resposta_falsa( 200, fixture( 'product' ) );
resposta_falsa( 200, fixture( 'schedules' ) );
resposta_falsa( 200, fixture( 'exchange' ) );
$r = vtec_resolver_pagina( 'produto', '', '56549P1', '' );
igual( 200, $r['status'], 'produto ok' );
contem( 'Reservar na Viator', $r['html'], 'html do produto' );
igual( 'https://exemplo.test/passeios/p/56549P1-vale-privado-do-fogo-caminhadas-e-aventura/', $r['canonica'], 'canônica do produto' );
ok( strlen( $r['descricao'] ) > 50 && strlen( $r['descricao'] ) <= 170, 'descrição SEO curta' );

$robots = vtec_robots( "User-agent: *\nDisallow: /wp-admin/\n" );
contem( 'Disallow: /wp-json/viator-tec/v1/protegido', $robots, 'robots bloqueia a REST protegida' );
contem( 'Disallow: /wp-content/plugins/viator-tec/assets/protegido.js', $robots, 'robots bloqueia o JS protegido' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs rotas`.

- [ ] **Step 3: `includes/rotas.php`**

```php
<?php
/** Endereços /passeios/..., template dentro do tema, 404, SEO (Rank Math) e robots.txt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_rotas() {
	add_rewrite_rule( '^passeios/?$', 'index.php?vtec_pagina=destinos', 'top' );
	add_rewrite_rule( '^passeios/p/([A-Za-z0-9_]+)(?:-[^/]*)?/?$', 'index.php?vtec_pagina=produto&vtec_codigo=$matches[1]', 'top' );
	add_rewrite_rule( '^passeios/([^/]+)/?$', 'index.php?vtec_pagina=destino&vtec_destino=$matches[1]', 'top' );
}

function vtec_pagina_404() {
	return array( 'status' => 404, 'titulo' => '', 'descricao' => '', 'canonica' => '', 'html' => '' );
}

/** Monta a página pedida. Não depende do WordPress além das opções e do cache — por isso é testável. */
function vtec_resolver_pagina( $pagina, $destino, $codigo, $ordem ) {
	$o = vtec_opcoes();

	if ( 'destinos' === $pagina ) {
		$destinos = array();
		foreach ( $o['destinos'] as $d ) {
			if ( '' === $d['foto'] ) {
				$r         = vtec_buscar( $d['id'], 'avaliacao', 1, 1 );
				$d['foto'] = ! is_wp_error( $r ) && $r['cards'] ? $r['cards'][0]['imagem'] : '';
			}
			$destinos[] = $d;
		}
		return array(
			'status'    => 200,
			'titulo'    => 'Passeios e ingressos',
			'descricao' => 'Passeios, ingressos e experiências nos principais destinos, com reserva segura pela Viator.',
			'canonica'  => home_url( '/passeios/' ),
			'html'      => vtec_html_destinos( $destinos ),
		);
	}

	if ( 'destino' === $pagina ) {
		$d = vtec_destino_por_slug( $destino );
		if ( ! $d ) {
			return vtec_pagina_404();
		}
		$ordem = 'preco' === $ordem ? 'preco' : 'avaliacao';
		$r     = vtec_buscar( $d['id'], $ordem, 1, $o['por_pagina'] );
		return array(
			'status'    => 200,
			'titulo'    => 'Passeios em ' . $d['nome'],
			'descricao' => 'Os melhores passeios e ingressos em ' . $d['nome'] . ', com reserva segura pela Viator.',
			'canonica'  => home_url( '/passeios/' . $d['slug'] . '/' ),
			'html'      => is_wp_error( $r ) ? vtec_html_indisponivel() : vtec_html_destino( $d, $r['cards'], $r['total'], $ordem, $o['por_pagina'] ),
		);
	}

	if ( 'produto' === $pagina ) {
		if ( ! vtec_codigo_valido( $codigo ) ) {
			return vtec_pagina_404();
		}
		$p = vtec_produto( $codigo );
		if ( is_wp_error( $p ) ) {
			$dados = $p->get_error_code() === 'vtec_http_400' || $p->get_error_code() === 'vtec_http_404';
			return $dados ? vtec_pagina_404() : array(
				'status' => 200, 'titulo' => 'Passeio', 'descricao' => '', 'canonica' => '', 'html' => vtec_html_indisponivel(),
			);
		}
		$v = vtec_produto_view( $p, vtec_preco_a_partir( $codigo ) );
		return array(
			'status'    => 200,
			'titulo'    => $v['titulo'],
			'descricao' => mb_substr( wp_trim_words( implode( ' ', $v['paragrafos'] ), 30, '' ), 0, 160 ),
			'canonica'  => vtec_url_produto( $v['codigo'], $v['titulo'] ),
			'html'      => vtec_html_produto( $v ),
		);
	}

	return vtec_pagina_404();
}

function vtec_robots( $txt ) {
	return rtrim( $txt ) . "\n\n# Viator Tec: conteúdo protegido (regra da Viator)\n"
		. "Disallow: /wp-json/viator-tec/v1/protegido\n"
		. "Disallow: /wp-content/plugins/viator-tec/assets/protegido.js\n";
}

if ( defined( 'VTEC_TESTE' ) ) {
	return; // daqui para baixo só ganchos do WordPress
}

add_action( 'init', 'vtec_rotas' );

// Atualização automática não roda a ativação: regrava os links quando a versão muda.
add_action( 'init', function () {
	if ( get_option( 'vtec_rotas_versao' ) !== VTEC_VERSION ) {
		flush_rewrite_rules();
		update_option( 'vtec_rotas_versao', VTEC_VERSION );
	}
}, 99 );

add_filter( 'query_vars', function ( $vars ) {
	return array_merge( $vars, array( 'vtec_pagina', 'vtec_destino', 'vtec_codigo' ) );
} );

// A requisição só tem query vars nossas: sem isso o WordPress a trataria como a home do blog.
add_action( 'parse_query', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'vtec_pagina' ) ) {
		$q->is_home = false;
		$q->is_archive = false;
		$q->is_singular = false;
		$q->is_page = false;
		$q->set( 'posts_per_page', 1 );
		$q->set( 'no_found_rows', true );
	}
} );

add_action( 'template_redirect', function () {
	$pagina = get_query_var( 'vtec_pagina' );
	if ( ! $pagina ) {
		return;
	}
	$GLOBALS['vtec_atual'] = vtec_resolver_pagina(
		$pagina,
		(string) get_query_var( 'vtec_destino' ),
		(string) get_query_var( 'vtec_codigo' ),
		isset( $_GET['ordem'] ) ? sanitize_key( wp_unslash( $_GET['ordem'] ) ) : ''
	);
	if ( 404 === $GLOBALS['vtec_atual']['status'] ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		unset( $GLOBALS['vtec_atual'] );
		return;
	}
	status_header( 200 );
}, 5 );

add_filter( 'template_include', function ( $modelo ) {
	return isset( $GLOBALS['vtec_atual'] ) ? VTEC_DIR . 'templates/pagina.php' : $modelo;
}, 99 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! isset( $GLOBALS['vtec_atual'] ) ) {
		return;
	}
	wp_enqueue_style( 'viator-tec', VTEC_URL . 'assets/viator-tec.css', array(), VTEC_VERSION );
	wp_enqueue_script( 'viator-tec', VTEC_URL . 'assets/viator-tec.js', array(), VTEC_VERSION, true );
	wp_enqueue_script( 'viator-tec-protegido', VTEC_URL . 'assets/protegido.js', array(), VTEC_VERSION, true );
	wp_add_inline_script( 'viator-tec', 'window.vtecCfg=' . wp_json_encode( array( 'rest' => rest_url( 'viator-tec/v1/' ) ) ) . ';', 'before' );
} );

add_filter( 'body_class', function ( $classes ) {
	return isset( $GLOBALS['vtec_atual'] ) ? array_merge( $classes, array( 'vtec-pagina' ) ) : $classes;
} );

function vtec_titulo_seo() {
	return $GLOBALS['vtec_atual']['titulo'] . ' | ' . get_bloginfo( 'name' );
}
add_filter( 'pre_get_document_title', function ( $t ) {
	return isset( $GLOBALS['vtec_atual'] ) ? vtec_titulo_seo() : $t;
}, 99 );
add_filter( 'rank_math/frontend/title', function ( $t ) {
	return isset( $GLOBALS['vtec_atual'] ) ? vtec_titulo_seo() : $t;
}, 99 );
add_filter( 'rank_math/frontend/description', function ( $d ) {
	return isset( $GLOBALS['vtec_atual'] ) ? $GLOBALS['vtec_atual']['descricao'] : $d;
}, 99 );
add_filter( 'rank_math/frontend/canonical', function ( $c ) {
	return isset( $GLOBALS['vtec_atual'] ) && $GLOBALS['vtec_atual']['canonica'] ? $GLOBALS['vtec_atual']['canonica'] : $c;
}, 99 );
add_filter( 'rank_math/frontend/robots', function ( $r ) {
	return isset( $GLOBALS['vtec_atual'] ) ? array( 'index' => 'index', 'follow' => 'follow' ) : $r;
}, 99 );

add_filter( 'robots_txt', 'vtec_robots', 99 );
```

- [ ] **Step 4: `templates/pagina.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
echo '<main id="content" class="site-main vtec-main">' . $GLOBALS['vtec_atual']['html'] . '</main>'; // phpcs:ignore -- HTML já escapado em render.php
get_footer();
```

- [ ] **Step 5: rodar** — `node run.mjs rotas` → TUDO OK. Rodar tudo: `node run.mjs` → TUDO OK.
- [ ] **Step 6: commit** — `git add -A && git commit -m "rotas /passeios/, template, 404, SEO e robots.txt"`

---

### Task 8: REST (/protegido e /mais) e painel

**Files:**
- Modify: `viator-tec/includes/rest.php`, `viator-tec/includes/admin.php`
- Test: `tests/php/rest.test.php`

**Interfaces:**
- Consumes: `vtec_ler_nota`, `vtec_buscar`, `vtec_destino_por_slug`, `vtec_html_cards`, `vtec_sanitizar_opcoes`, `vtec_destinos_viator`, `vtec_filtrar_destinos`, `vtec_limpar_cache`, `vtec_opcoes`.
- Produces:
  - `vtec_rest_protegido(string $codigos_csv): array` — `[codigo => ['nota'=>float,'total'=>int]]`, no máximo 60 códigos, só válidos
  - `vtec_rest_mais(string $slug, string $ordem, int $inicio): array|WP_Error` — `['html'=>string,'proximo'=>int|null]`
  - Rotas `GET /wp-json/viator-tec/v1/protegido?codigos=` e `GET /wp-json/viator-tec/v1/mais?destino=&ordem=&inicio=` (públicas)
  - Página Configurações › Viator Tec (`options-general.php?page=viator-tec`), ações `admin-post.php?action=vtec_salvar` e `vtec_limpar`

- [ ] **Step 1: teste**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rest' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );

vtec_guardar_nota( 'A1B', 4.8, 99 );
igual( array( 'A1B' => array( 'nota' => 4.8, 'total' => 99 ) ), vtec_rest_protegido( 'A1B,SEMNOTA,../x' ), 'só códigos com nota e válidos' );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_rest_mais( 'las-vegas', 'avaliacao', 4 );
contem( 'vtec-card', $r['html'], 'cards da próxima página' );
igual( 7, $r['proximo'], 'próximo início' );
igual( 4, json_decode( $GLOBALS['vt_http_log'][0][1]['body'], true )['pagination']['start'], 'pede a partir do 4' );
igual( 'vtec_destino', vtec_rest_mais( 'xx', 'avaliacao', 4 )->get_error_code(), 'destino fora da lista' );

$busca = fixture( 'search' );
$busca['totalCount'] = 5;
resposta_falsa( 200, $busca );
igual( null, vtec_rest_mais( 'las-vegas', 'preco', 4 )['proximo'], 'última página não tem próximo' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs rest`.

- [ ] **Step 3: `includes/rest.php`**

```php
<?php
/**
 * Rotas REST públicas. /protegido entrega nota e total de avaliações (conteúdo que a Viator
 * proíbe indexar) e está em Disallow no robots.txt; /mais entrega a próxima página de cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_rest_protegido( $codigos_csv ) {
	$saida = array();
	foreach ( array_slice( explode( ',', (string) $codigos_csv ), 0, 60 ) as $c ) {
		$c = trim( $c );
		if ( vtec_codigo_valido( $c ) ) {
			$n = vtec_ler_nota( $c );
			if ( $n ) {
				$saida[ $c ] = $n;
			}
		}
	}
	return $saida;
}

function vtec_rest_mais( $slug, $ordem, $inicio ) {
	$d = vtec_destino_por_slug( $slug );
	if ( ! $d ) {
		return new WP_Error( 'vtec_destino', 'Destino não encontrado.', array( 'status' => 404 ) );
	}
	$qtd = vtec_opcoes()['por_pagina'];
	$r   = vtec_buscar( $d['id'], $ordem, $inicio, $qtd );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$proximo = $inicio + count( $r['cards'] );
	return array(
		'html'    => vtec_html_cards( $r['cards'] ),
		'proximo' => $r['cards'] && $proximo <= $r['total'] ? $proximo : null,
	);
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'viator-tec/v1', '/protegido', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			return rest_ensure_response( (object) vtec_rest_protegido( (string) $req->get_param( 'codigos' ) ) );
		},
	) );
	register_rest_route( 'viator-tec/v1', '/mais', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			return rest_ensure_response( vtec_rest_mais(
				sanitize_title( (string) $req->get_param( 'destino' ) ),
				sanitize_key( (string) $req->get_param( 'ordem' ) ),
				max( 1, (int) $req->get_param( 'inicio' ) )
			) );
		},
	) );
} );
```

Conferir o teste `proximo = 7`: a fixture tem 3 produtos e `totalCount` 1309 → 4 + 3 = 7 ≤ 1309 → 7. Com `totalCount` 5: 7 > 5 → null. ✔

- [ ] **Step 4: rodar** — `node run.mjs rest` → TUDO OK.

- [ ] **Step 5: `includes/admin.php`** (só WordPress; conferido no site na Task 10)

```php
<?php
/** Configurações › Viator Tec. */

if ( ! defined( 'ABSPATH' ) || defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'admin_menu', function () {
	add_options_page( 'Viator Tec', 'Viator Tec', 'manage_options', 'viator-tec', 'vtec_tela_admin' );
} );

add_action( 'admin_post_vtec_salvar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_salvar' );
	update_option( 'vtec_opcoes', vtec_sanitizar_opcoes( wp_unslash( $_POST ), vtec_opcoes() ), false );
	vtec_limpar_cache();
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&salvo=1' ) );
	exit;
} );

add_action( 'admin_post_vtec_limpar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_limpar' );
	vtec_limpar_cache();
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&limpo=1' ) );
	exit;
} );

function vtec_tela_admin() {
	$o     = vtec_opcoes();
	$busca = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
	echo '<div class="wrap"><h1>Viator Tec</h1>';
	if ( isset( $_GET['salvo'] ) ) {
		echo '<div class="notice notice-success"><p>Salvo. O cache foi limpo.</p></div>';
	}
	if ( isset( $_GET['limpo'] ) ) {
		echo '<div class="notice notice-success"><p>Cache limpo.</p></div>';
	}

	// Teste de conexão: primeira busca do primeiro destino.
	if ( $o['destinos'] ) {
		$t = vtec_buscar( $o['destinos'][0]['id'], 'avaliacao', 1, 1 );
		echo is_wp_error( $t )
			? '<div class="notice notice-error"><p>Conexão com a Viator: ' . esc_html( $t->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-info"><p>Conexão com a Viator OK (' . esc_html( 'producao' === $o['ambiente'] ? 'produção' : 'sandbox' ) . '): ' . (int) $t['total'] . ' passeios em ' . esc_html( $o['destinos'][0]['nome'] ) . '.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_salvar">';
	wp_nonce_field( 'vtec_salvar' );
	echo '<table class="form-table"><tr><th>Ambiente</th><td><select name="ambiente">'
		. '<option value="sandbox"' . selected( $o['ambiente'], 'sandbox', false ) . '>Sandbox (teste)</option>'
		. '<option value="producao"' . selected( $o['ambiente'], 'producao', false ) . '>Produção</option></select></td></tr>';
	foreach ( array( 'chave_sandbox' => 'Chave sandbox', 'chave_producao' => 'Chave de produção' ) as $campo => $rotulo ) {
		$ph = '' !== $o[ $campo ] ? '•••••••• (salva — deixe em branco para manter)' : 'cole a chave';
		echo '<tr><th>' . esc_html( $rotulo ) . '</th><td><input type="password" autocomplete="off" class="regular-text" name="' . esc_attr( $campo ) . '" placeholder="' . esc_attr( $ph ) . '"></td></tr>';
	}
	echo '<tr><th>Campanha</th><td><input class="regular-text" name="campanha" value="' . esc_attr( $o['campanha'] ) . '"></td></tr>'
		. '<tr><th>Passeios por página</th><td><input type="number" min="1" max="50" name="por_pagina" value="' . (int) $o['por_pagina'] . '"></td></tr></table>';

	echo '<h2>Destinos</h2><p>ID da Viator, nome exibido, endereço (/passeios/<em>slug</em>/) e foto (opcional — sem foto usa a do passeio mais bem avaliado).</p>'
		. '<table class="widefat striped"><thead><tr><th>ID Viator</th><th>Nome</th><th>Slug</th><th>URL da foto</th></tr></thead><tbody>';
	$linhas = array_merge( $o['destinos'], array_fill( 0, 3, array( 'id' => '', 'nome' => '', 'slug' => '', 'foto' => '' ) ) );
	foreach ( $linhas as $d ) {
		echo '<tr><td><input size="8" name="destino_id[]" value="' . esc_attr( $d['id'] ) . '"></td>'
			. '<td><input name="destino_nome[]" value="' . esc_attr( $d['nome'] ) . '"></td>'
			. '<td><input name="destino_slug[]" value="' . esc_attr( $d['slug'] ) . '"></td>'
			. '<td><input class="regular-text" name="destino_foto[]" value="' . esc_attr( $d['foto'] ) . '"></td></tr>';
	}
	echo '</tbody></table><p>Para tirar um destino, apague o ID e salve. Linhas em branco são ignoradas.</p>';
	submit_button( 'Salvar' );
	echo '</form>';

	echo '<h2>Procurar ID de destino</h2><form method="get"><input type="hidden" name="page" value="viator-tec">'
		. '<input name="busca" value="' . esc_attr( $busca ) . '" placeholder="ex.: Paris"> ';
	submit_button( 'Procurar', 'secondary', '', false );
	echo '</form>';
	if ( '' !== $busca ) {
		$lista = vtec_destinos_viator();
		if ( is_wp_error( $lista ) ) {
			echo '<p>Erro: ' . esc_html( $lista->get_error_message() ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( vtec_filtrar_destinos( $lista, $busca ) as $d ) {
				echo '<li><code>' . (int) $d['id'] . '</code> — ' . esc_html( $d['nome'] ) . ' (' . esc_html( $d['tipo'] ) . ')</li>';
			}
			echo '</ul>';
		}
	}

	echo '<h2>Cache</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_limpar">';
	wp_nonce_field( 'vtec_limpar' );
	submit_button( 'Limpar cache', 'secondary' );
	echo '</form></div>';
}
```

- [ ] **Step 6: rodar tudo** — `node run.mjs` → TUDO OK (o `admin.php` sai cedo com `VTEC_TESTE`).
- [ ] **Step 7: commit** — `git add -A && git commit -m "REST protegido/mais e painel Configurações › Viator Tec"`

---

### Task 9: Atualizador, empacotamento e release

**Files:**
- Create: `viator-tec/includes/atualizador.php` (cópia adaptada), `empacotar.py`, `README.md`
- Test: `tests/php/estrutura.test.php` (acrescentar verificações)

**Interfaces:**
- Consumes: `VTEC_VERSION`, `VTEC_BASENAME`.
- Produces: `dist/viator-tec.zip` com a pasta `viator-tec/` dentro; constantes `VTEC_REPO='TravelTecOficial/viator-tec'`, `VTEC_ANEXO='viator-tec.zip'`.

- [ ] **Step 1: acrescentar ao `estrutura.test.php`**

```php
$at = file_get_contents( '/p/includes/atualizador.php' );
contem( "'TravelTecOficial/viator-tec'", $at, 'atualizador aponta para o repositório certo' );
nao_contem( 'roteiros', strtolower( $at ), 'nada do Voucher Tec sobrou no atualizador' );
nao_contem( 'TT_', $at, 'sem constantes TT_' );
```

- [ ] **Step 2: rodar e ver falhar** — `node run.mjs estrutura`.

- [ ] **Step 3: copiar e adaptar o atualizador**

```bash
cd C:/Users/User/Documents/Wordpress/padrao/plugin-viator
sed -e 's/TT_ROTEIROS_GITHUB_TOKEN/VTEC_GITHUB_TOKEN/g' -e 's/TT_ROTEIROS_/VTEC_/g' -e 's/tt_roteiros_release/vtec_release/g' \
    -e 's/tt_roteiros_/vtec_/g' -e 's#TravelTecOficial/traveltec-roteiros#TravelTecOficial/viator-tec#g' \
    -e 's/traveltec-roteiros/viator-tec/g' -e 's/Voucher-Tec/Viator-Tec/g' -e 's/Voucher Tec/Viator Tec/g' \
    ../plugin-roteiros/traveltec-roteiros/includes/atualizador.php > viator-tec/includes/atualizador.php
grep -n -i "roteiros\|TT_\|voucher" viator-tec/includes/atualizador.php
```

Esperado: `grep` sem saída. Se sobrar algo (ex.: textos "Roteiros ›" de menu do Voucher Tec, linha ~167), abrir o arquivo e trocar à mão pelo equivalente do Viator Tec — o botão "Procurar atualização" pode ser removido; o cache é de 6 h. Conferir que `VTEC_CACHE` vale `'vtec_release'` e que o gancho `upgrader_process_complete` usa `VTEC_BASENAME`.

- [ ] **Step 4: `empacotar.py`**

```python
"""Gera dist/viator-tec.zip, o anexo da release do GitHub (o atualizador procura esse nome).

    python empacotar.py
"""
import re
import zipfile
from pathlib import Path

RAIZ = Path(__file__).parent
PLUGIN = RAIZ / "viator-tec"
INCLUIR = ["viator-tec.php", "readme.txt", "includes/*.php", "templates/*.php", "assets/*"]


def main():
    v = re.search(r"^\s*\*\s*Version:\s*(.+)$", (PLUGIN / "viator-tec.php").read_text(encoding="utf-8"), re.M).group(1).strip()
    (RAIZ / "dist").mkdir(exist_ok=True)
    destino = RAIZ / "dist" / "viator-tec.zip"
    arquivos = sorted({c for p in INCLUIR for c in PLUGIN.glob(p) if c.is_file()})
    with zipfile.ZipFile(destino, "w", zipfile.ZIP_DEFLATED) as z:
        for arq in arquivos:
            z.write(arq, f"viator-tec/{arq.relative_to(PLUGIN).as_posix()}")
    print(f"{destino}  —  versão {v}, {len(arquivos)} arquivos")


if __name__ == "__main__":
    main()
```

- [ ] **Step 5: `README.md`**

```markdown
# Viator Tec

Plugin WordPress: passeios da Viator (Partner API v2, afiliado) em `/passeios/`.
Módulo e decisões: `Wordpress/RedeTuristica/docs/modulos/viator.md`.

- Testes: `cd tests && npm install && node run.mjs` (PHP 7.4 e 8.2 por php-wasm).
- Conferência no site: `python ../../RedeTuristica/scripts/viator_conferir.py`.
- Versão nova: subir `Version:` e `VTEC_VERSION`, entrada no `readme.txt`, `python empacotar.py`,
  commit, push e `gh release create vX.Y.Z dist/viator-tec.zip`. O anexo tem de se chamar `viator-tec.zip`.
- A chave da API nunca entra no repositório.
```

- [ ] **Step 6: rodar** — `cd tests && node run.mjs` → TUDO OK; `python empacotar.py` → `versão 0.1.0, N arquivos`.
- [ ] **Step 7: commit, repositório e release** (repositório **público**; não há segredo no código)

```bash
git add -A && git commit -m "0.1.0: atualizador pelo GitHub e empacotamento"
gh repo create TravelTecOficial/viator-tec --public --source . --push --description "Passeios da Viator (Partner API) em sites WordPress"
gh release create v0.1.0 dist/viator-tec.zip --title "0.1.0" --notes "Primeira versão: /passeios/, destino e página do passeio."
```

---

### Task 10: Instalação no site e conferência (sandbox, sem menu)

**Files:**
- Create: `RedeTuristica/scripts/viator_conferir.py`
- Modify: `RedeTuristica/docs/modulos/viator.md`

**Interfaces:**
- Consumes: site `https://redeturistica.com.br` com o plugin ativo; `config.local.json → sites.RedeTuristica` (baseUrl, user, appPassword, viator.sandboxKey).

- [ ] **Step 1: instalação (o dono faz; a REST do WP não aceita zip)**

Pedir ao dono: Plugins › Adicionar novo › Enviar plugin › `padrao/plugin-viator/dist/viator-tec.zip` › Ativar. Conferir pela API:

```bash
cd C:/Users/User/Documents/Wordpress && python -c "
import json,base64,urllib.request,time
c=json.load(open('config.local.json',encoding='utf-8'))['sites']['RedeTuristica']
a=base64.b64encode((c['user']+':'+c['appPassword']).encode()).decode()
r=urllib.request.Request(c['baseUrl']+'/wp-json/wp/v2/plugins?_cb=%d'%time.time(),headers={'Authorization':'Basic '+a,'User-Agent':'Mozilla/5.0'})
print([(p['plugin'],p['status'],p['version']) for p in json.load(urllib.request.urlopen(r)) if 'viator' in p['plugin']])"
```

Esperado: `[('viator-tec/viator-tec', 'active', '0.1.0')]`.

- [ ] **Step 2: configurar (via painel ou pela option)** — o dono escolhe os destinos; sem resposta dele, começar com Las Vegas (684) para testar. A chave sandbox o dono cola no painel (Configurações › Viator Tec) — **não** gravar a chave por script nem mostrá-la no chat. Salvar.

- [ ] **Step 3: `RedeTuristica/scripts/viator_conferir.py`**

```python
"""Confere o Viator Tec no site publicado (leitura apenas).

    python RedeTuristica/scripts/viator_conferir.py [slug-do-destino]
"""
import json
import re
import sys
import time
import urllib.error
import urllib.request

BASE = "https://redeturistica.com.br"
UA = {"User-Agent": "Mozilla/5.0"}
falhas = 0


def get(caminho):
    sep = "&" if "?" in caminho else "?"
    req = urllib.request.Request(f"{BASE}{caminho}{sep}_cb={time.time():.0f}", headers=UA)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")


def ok(cond, msg):
    global falhas
    print(("OK    " if cond else "FALHA ") + msg)
    falhas += 0 if cond else 1


slug = sys.argv[1] if len(sys.argv) > 1 else "las-vegas"

s, h = get("/passeios/")
ok(s == 200 and "vtec-destino" in h, "/passeios/ lista os destinos")
ok("<header" in h.lower() or "elementor-location-header" in h, "/passeios/ tem o cabeçalho do site")

s, h = get(f"/passeios/{slug}/")
ok(s == 200 and "vtec-card" in h, f"/passeios/{slug}/ lista passeios")
ok("R$" in h, "preços em R$")
ok("A reserva e o pagamento são feitos no site da Viator." in h, "aviso fixo no destino")
ok(f"Passeios em" in h and "<title>" in h and "Rede Tur" in h, "título SEO")
ok("avaliações" not in h and not re.search(r"★", h), "nota fora do HTML do destino")
m = re.search(r'href="(https://redeturistica\.com\.br/passeios/p/[^"]+)"', h)
ok(bool(m), "card aponta para a página do passeio no site")

s, h = get(f"/passeios/{slug}/?ordem=preco")
ok(s == 200 and 'aria-current="true"' in h, "ordenar por preço")

if m:
    s, h = get(m.group(1).replace(BASE, ""))
    ok(s == 200 and "Reservar na Viator" in h, "página do passeio abre com o botão")
    link = re.search(r'class="vtec-botao" href="([^"]+)"', h)
    ok(bool(link) and "pid=" in link.group(1) and "mcid=" in link.group(1), "link de afiliado com pid/mcid")
    ok(bool(link) and "campaign=redeturistica-passeios" in link.group(1).replace("&amp;", "&"), "link com a campanha")
    ok('rel="noopener sponsored"' in h, "rel sponsored")
    ok("avaliações" not in h, "nota fora do HTML do passeio")
    ok('rel="canonical" href="' + m.group(1) in h, "canônica do passeio")
    codigo = re.search(r"/passeios/p/([A-Za-z0-9_]+)", m.group(1)).group(1)
    s, j = get(f"/wp-json/viator-tec/v1/protegido?codigos={codigo}")
    ok(s == 200 and codigo in j, "REST protegido devolve a nota")

s, h = get("/passeios/destino-que-nao-existe/")
ok(s == 404, "destino inexistente = 404")
s, h = get("/passeios/p/ZZZ999999-x/")
ok(s == 404, "passeio inexistente = 404")

s, h = get(f"/wp-json/viator-tec/v1/mais?destino={slug}&ordem=avaliacao&inicio=13")
ok(s == 200 and "vtec-card" in h, "carregar mais")

s, h = get("/robots.txt")
ok("Disallow: /wp-json/viator-tec/v1/protegido" in h, "robots.txt bloqueia a REST protegida")
ok("Disallow: /wp-content/plugins/viator-tec/assets/protegido.js" in h, "robots.txt bloqueia o JS protegido")

print("\nTUDO OK" if not falhas else f"\n{falhas} FALHA(S)")
sys.exit(1 if falhas else 0)
```

- [ ] **Step 4: rodar** — `cd C:/Users/User/Documents/Wordpress && python RedeTuristica/scripts/viator_conferir.py las-vegas` → `TUDO OK`. Se algo do SEO falhar (Rank Math ignorando o filtro em rotas virtuais), corrigir em `rotas.php`, subir versão 0.1.1 (Task 9, Steps 6–7) e clicar em "Atualizar agora" em Plugins (ou pedir ao dono).

- [ ] **Step 5: conferência visual** — no navegador do app, abrir `/passeios/`, `/passeios/las-vegas/` e uma página de passeio em desktop e em 375 px: cabeçalho legível sobre o conteúdo (fora da home a barra precisa ter fundo), cards alinhados, estrelas aparecendo, "Carregar mais" funcionando, botão Reservar abrindo a Viator em nova aba. Tirar print e mandar ao dono com `SendUserFile`.

- [ ] **Step 6: atualizar `RedeTuristica/docs/modulos/viator.md`** — marcar "Plugin rodando com a chave sandbox", preencher "Onde está" (`padrao/plugin-viator/`, repo `TravelTecOficial/viator-tec`, `scripts/viator_conferir.py`) e "Próximo passo: dono aprovar desktop e celular". Commit no repo do plugin: `git add -A && git commit -m "docs: plano executado até a conferência"`.

---

### Task 11: Promoção (só depois do "ok" do dono)

**Files:**
- Modify: `RedeTuristica/CHANGELOG.md`, `RedeTuristica/CLAUDE.md` (versão), `RedeTuristica/docs/modulos/viator.md`

- [ ] **Step 1:** dono troca o ambiente para **Produção** no painel (a chave de produção é colada por ele). Rodar `python RedeTuristica/scripts/viator_conferir.py` → TUDO OK.
- [ ] **Step 2:** item "Passeios" → `/passeios/` no menu #1078 (pela REST `wp/v2/menu-items`, com o dono de acordo); medir se o menu continua cabendo no desktop.
- [ ] **Step 3:** CHANGELOG com nova versão do site (ex.: v2.3 — "Passeios Viator em /passeios/"), atualizar a versão em `RedeTuristica/CLAUDE.md` e a exibida no site, e conferir na tela que mudou.
- [ ] **Step 4:** `viator.md`: etapa "no PROD" marcada, pendências zeradas, próximo passo.
- [ ] **Step 5:** `/graphify . --update` na pasta `Wordpress/`.
