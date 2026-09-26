# Viator Tec — Fase 2 (Elementor) — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** As telas `/passeios/`, `/passeios/<destino>/` e `/passeios/p/<código>-<slug>/` passam a ser modelos do Elementor editáveis, no visual dos roteiros (#5302, #5357, #5301), com os dados da Viator ao vivo por campos dinâmicos e um widget de grade.

**Architecture:** O plugin guarda um "contexto atual" (destino e/ou passeio) num global. Campos dinâmicos do Elementor (grupo "Viator") e o shortcode `[vtec campo=""]` leem esse contexto por uma função pura (`vtec_valor_campo`). O widget "Viator – Grade" busca a lista (destinos ou passeios), define o contexto de cada item e renderiza um modelo de card do Elementor por item. As rotas da fase 1 continuam; em vez do HTML fixo, renderizam o modelo escolhido no painel (sem modelo → HTML da fase 1). Cinco modelos são gerados por script a partir dos modelos de roteiros exportados do site e instalados pelo plugin.

**Tech Stack:** PHP 7.4+/8.2, WordPress, Elementor 4.3 + PRO Elements (APIs de dynamic tags e widgets do core), JS puro, testes php-wasm + jsdom, Python para gerar os modelos.

**Spec:** `RedeTuristica/docs/modulos/viator.md` (seção "Fase 2 — Elementor")

## Global Constraints

- Tudo da fase 1 continua valendo (prefixo `vtec_`, PHP 7.4, chave nunca exposta, nota só via `protegido.js`, cache, 404, SEO).
- Nomes dos campos dinâmicos: `vtec-<campo>` com `_` trocado por `-`; grupo `viator`, título "Viator".
- Widget: nome `vtec-grade`, título "Viator – Grade".
- Chaves dos modelos: `card_destino`, `card_passeio`, `destinos`, `destino`, `produto` (option `vtec_opcoes['modelos']`).
- Modelos gerados só a partir de `_ref/5302.json`, `_ref/5357.json`, `_ref/5301.json` (exportados do site em 26/09).
- Texto do preço: `a partir de R$ X, por pessoa` (o script `.rt-preco` dos roteiros formata).
- Galeria no mesmo HTML dos roteiros: `<div class="rt-galeria"><div class="rt-galeria-track">…<img>…</div></div>`.
- Instalar modelos **não** sobrescreve modelo existente (o dono pode ter editado); "Reinstalar" cria cópias novas.
- Versão do plugin desta fase: `0.3.0`.

## Review Focus

1. **Contexto vazando entre cards** (card N mostrando dados do card N-1, ou a página do destino perdendo o destino depois da grade) → cada card restaura o contexto anterior. Teste: Task 2 (`vtec_render_card` restaura).
2. **Cache de elementos do Elementor** servindo o mesmo card para todos os itens → widget declara `is_dynamic_content` e o filtro marca elementos com tags `vtec-` como dinâmicos. Teste: Task 4 (filtro) + Task 6 (conferência: títulos diferentes nos cards).
3. **Modelo apagado ou Elementor desativado** → a rota volta ao HTML da fase 1, nunca página vazia. Teste: Task 3 (`vtec_render_pagina` com renderizador vazio).
4. **Editor do Elementor sem passeio atual** → campos mostram passeio de exemplo (ou texto de exemplo fixo se não houver destino/chave), nunca erro. Teste: Task 1 (`vtec_contexto_exemplo` sem destinos).
5. **Texto da Viator com HTML/aspas nos campos dinâmicos** → escapado. Teste: Task 1.

---

## Estrutura de arquivos (novos e alterados)

```
viator-tec/includes/contexto.php     (novo)  contexto atual, exemplo, vtec_valor_campo, galeria/ul, shortcode
viator-tec/includes/grade.php        (novo)  itens da grade, HTML da grade, render de card/página pelo Elementor
viator-tec/includes/elementor.php    (novo)  ganchos: registra grupo, tags e widget; filtro de cache
viator-tec/includes/elementor-classes.php (novo) classes que estendem o Elementor (só carregado com Elementor)
viator-tec/includes/instalador.php   (novo)  instala os 5 modelos
viator-tec/includes/sitemap.php      (novo)  /passeios-sitemap.xml + índice do Rank Math
viator-tec/templates/modelos/*.json  (novo, gerado por build_modelos.py)
build_modelos.py                     (novo)
viator-tec/includes/{dados,render,rotas,rest,opcoes,admin}.php, templates/pagina.php, assets/viator-tec.{js,css}, viator-tec.php (alterados)
tests/php/{contexto,grade,elementor,instalador,sitemap}.test.php (novos) + ajustes em rotas/rest/opcoes
```

---

### Task 1: Contexto e valores dos campos

**Files:**
- Create: `viator-tec/includes/contexto.php`, `tests/php/contexto.test.php`
- Modify: `viator-tec/includes/dados.php` (view ganha `imagem` e `url`), `viator-tec/viator-tec.php` (require)

**Interfaces:**
- Produces: `vtec_definir_contexto(?array $ctx): void`, `vtec_contexto(): ?array`, `vtec_contexto_ou_exemplo(): array`, `vtec_contexto_exemplo(): array`, `vtec_valor_campo(string $campo, ?array $ctx): string`, `vtec_campos_tags(): array` (`campo => [titulo, 'texto'|'imagem'|'url']`), `vtec_html_galeria(array $urls, string $alt): string`, `vtec_html_ul(array $itens): string`. Contexto = `['destino'=>?array, 'produto'=>?array, 'ordem'=>?string]`; `produto` é um card (Task 4 da fase 1) ou uma view (`vtec_produto_view`).

- [ ] **Step 1: teste `tests/php/contexto.test.php`**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto' ) as $f ) { require "/p/includes/$f.php"; }

// sem destinos e sem chave: exemplo fixo, sem chamar a API
$ex = vtec_contexto_exemplo();
igual( 'Nome do passeio (exemplo)', $ex['produto']['titulo'], 'exemplo fixo sem destinos' );
igual( 0, count( $GLOBALS['vt_http_log'] ), 'exemplo sem destinos não chama a API' );
igual( $ex, vtec_contexto_ou_exemplo(), 'sem contexto usa o exemplo' );

$view = vtec_produto_view( fixture( 'product' ), 957.54 );
ok( '' !== $view['imagem'] && '' !== $view['url'], 'view tem imagem e url' );
$d   = array( 'id' => 684, 'nome' => 'Las <Vegas>', 'slug' => 'las-vegas', 'foto' => 'https://x.test/f.jpg' );
$ctx = array( 'destino' => $d, 'produto' => $view );
vtec_definir_contexto( $ctx );
igual( $ctx, vtec_contexto_ou_exemplo(), 'contexto definido vence o exemplo' );

igual( 'Vale Privado do Fogo Caminhadas e Aventura', vtec_valor_campo( 'titulo', $ctx ), 'título' );
igual( 'a partir de R$ 957,54, por pessoa', vtec_valor_campo( 'preco', $ctx ), 'preço no formato dos roteiros' );
contem( '<p>Passe o dia', vtec_valor_campo( 'descricao', $ctx ), 'descrição em parágrafos' );
contem( '<div class="rt-galeria"', vtec_valor_campo( 'galeria', $ctx ), 'galeria no HTML dos roteiros' );
contem( '<div class="rt-galeria-track"', vtec_valor_campo( 'galeria', $ctx ), 'trilho da galeria' );
contem( '<li>Guia profissional</li>', vtec_valor_campo( 'inclusoes', $ctx ), 'inclusões em lista' );
contem( '<li>Gorjetas</li>', vtec_valor_campo( 'exclusoes', $ctx ), 'exclusões em lista' );
contem( '24 horas', vtec_valor_campo( 'cancelamento', $ctx ), 'cancelamento' );
contem( 'Moapa', vtec_valor_campo( 'encontro', $ctx ), 'ponto de encontro' );
igual( '<span class="vtec-nota" data-codigo="56549P1"></span>', vtec_valor_campo( 'nota', $ctx ), 'nota só como espaço' );
contem( 'pid=', vtec_valor_campo( 'link_reserva', $ctx ), 'link de afiliado' );
igual( $view['url'], vtec_valor_campo( 'url_passeio', $ctx ), 'url do passeio' );
contem( 'tripadvisor.com', vtec_valor_campo( 'imagem', $ctx ), 'foto do passeio' );
igual( 'Las &lt;Vegas&gt;', vtec_valor_campo( 'destino_nome', $ctx ), 'nome do destino escapado' );
igual( 'https://exemplo.test/passeios/las-vegas/', vtec_valor_campo( 'destino_url', $ctx ), 'url do destino' );
igual( 'https://x.test/f.jpg', vtec_valor_campo( 'destino_foto', $ctx ), 'foto do destino' );
igual( '', vtec_valor_campo( 'nao-existe', $ctx ), 'campo desconhecido vazio' );

$card = vtec_card( fixture( 'search' )['products'][0] );
$card['titulo'] = 'Tour "top" <b>';
$c2 = array( 'destino' => $d, 'produto' => $card );
igual( 'Tour &quot;top&quot; &lt;b&gt;', vtec_valor_campo( 'titulo', $c2 ), 'título do card escapado' );
igual( '', vtec_valor_campo( 'descricao', $c2 ), 'card não tem descrição' );
igual( '', vtec_valor_campo( 'preco', array( 'destino' => $d, 'produto' => array( 'preco' => '' ) ) ), 'sem preço não mostra "a partir de"' );
igual( '', vtec_html_galeria( array(), 'x' ), 'galeria vazia' );
igual( '', vtec_html_ul( array() ), 'lista vazia' );

$campos = vtec_campos_tags();
igual( 17, count( $campos ), '17 campos dinâmicos' );
igual( 'imagem', $campos['imagem'][1], 'foto é campo de imagem' );
igual( 'url', $campos['link_reserva'][1], 'reservar é campo de URL' );
vtec_definir_contexto( null );
igual( null, vtec_contexto(), 'contexto limpo' );
```

- [ ] **Step 2: rodar e ver falhar** — `node tests/run.mjs contexto` → ERRO FATAL.

- [ ] **Step 3: em `includes/dados.php`, `vtec_produto_view` ganha duas chaves** (antes de `'nota'`):

```php
		'imagem'       => vtec_imagem( isset( $p['images'] ) ? $p['images'] : null, 1200 ),
		'url'          => vtec_url_produto( $p['productCode'], $p['title'] ),
```

- [ ] **Step 4: `includes/contexto.php`**

```php
<?php
/**
 * "Passeio/destino atual" das telas feitas no Elementor. Campos dinâmicos, shortcode [vtec] e a grade
 * leem daqui. Sem contexto (editor do Elementor), usa um passeio de exemplo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_definir_contexto( $ctx ) {
	$GLOBALS['vtec_contexto'] = $ctx;
}

function vtec_contexto() {
	return isset( $GLOBALS['vtec_contexto'] ) && is_array( $GLOBALS['vtec_contexto'] ) ? $GLOBALS['vtec_contexto'] : null;
}

function vtec_contexto_ou_exemplo() {
	$c = vtec_contexto();
	return $c ? $c : vtec_contexto_exemplo();
}

/** 1º passeio do 1º destino da lista; sem destino/chave, um exemplo fixo (o editor nunca fica vazio). */
function vtec_contexto_exemplo() {
	static $exemplo = null;
	if ( null !== $exemplo ) {
		return $exemplo;
	}
	$o       = vtec_opcoes();
	$destino = $o['destinos'] ? $o['destinos'][0] : null;
	$produto = null;
	if ( $destino ) {
		$r = vtec_buscar( $destino['id'], 'avaliacao', 1, 1 );
		if ( ! is_wp_error( $r ) && $r['cards'] ) {
			$codigo = $r['cards'][0]['codigo'];
			$p      = vtec_produto( $codigo );
			if ( ! is_wp_error( $p ) ) {
				$produto = vtec_produto_view( $p, vtec_preco_a_partir( $codigo ) );
			}
		}
	}
	if ( ! $produto ) {
		$produto = array(
			'codigo' => '', 'titulo' => 'Nome do passeio (exemplo)',
			'paragrafos' => array( 'Descrição do passeio. Cadastre um destino em Configurações › Viator Tec para ver dados reais.' ),
			'galeria' => array(), 'duracao' => '3h', 'preco' => 'R$ 199,00', 'inclusoes' => array( 'Guia' ),
			'exclusoes' => array( 'Gorjetas' ), 'cancelamento' => 'Cancelamento grátis até 24 horas antes.',
			'encontro' => 'Ponto de encontro do passeio.', 'informacoes' => array(), 'link' => '', 'nota' => 0.0,
			'total' => 0, 'imagem' => '', 'url' => '',
		);
	}
	if ( ! $destino ) {
		$destino = array( 'id' => 0, 'nome' => 'Destino (exemplo)', 'slug' => '', 'foto' => '' );
	}
	if ( '' === $destino['foto'] ) {
		$destino['foto'] = $produto['imagem'];
	}
	$exemplo = array( 'destino' => $destino, 'produto' => $produto, 'ordem' => 'avaliacao' );
	return $exemplo;
}

function vtec_html_ul( $itens ) {
	if ( ! $itens ) {
		return '';
	}
	$h = '<ul>';
	foreach ( $itens as $i ) {
		$h .= '<li>' . esc_html( $i ) . '</li>';
	}
	return $h . '</ul>';
}

/** Mesmo HTML da galeria dos roteiros: o script da página do roteiro transforma em carrossel com lightbox. */
function vtec_html_galeria( $urls, $alt ) {
	if ( ! $urls ) {
		return '';
	}
	$h = '<div class="rt-galeria" style="padding:4px 0"><div class="rt-galeria-track" style="display:flex;gap:12px;width:max-content;flex-wrap:nowrap;--rt-duracao:40s">';
	foreach ( $urls as $u ) {
		$h .= '<img src="' . esc_url( $u ) . '" alt="' . esc_attr( $alt ) . '" style="height:220px;width:auto;flex:0 0 auto;border-radius:8px" />';
	}
	return $h . '</div></div>';
}

function vtec_campos_tags() {
	return array(
		'titulo'       => array( 'Título do passeio', 'texto' ),
		'descricao'    => array( 'Descrição', 'texto' ),
		'preco'        => array( 'Preço (a partir de)', 'texto' ),
		'duracao'      => array( 'Duração', 'texto' ),
		'galeria'      => array( 'Galeria de fotos', 'texto' ),
		'inclusoes'    => array( 'O que inclui', 'texto' ),
		'exclusoes'    => array( 'Não inclui', 'texto' ),
		'encontro'     => array( 'Ponto de encontro', 'texto' ),
		'cancelamento' => array( 'Cancelamento', 'texto' ),
		'informacoes'  => array( 'Informações importantes', 'texto' ),
		'nota'         => array( 'Nota (avaliações)', 'texto' ),
		'destino_nome' => array( 'Nome do destino', 'texto' ),
		'imagem'       => array( 'Foto do passeio', 'imagem' ),
		'destino_foto' => array( 'Foto do destino', 'imagem' ),
		'url_passeio'  => array( 'Link da página do passeio', 'url' ),
		'link_reserva' => array( 'Link Reservar na Viator', 'url' ),
		'destino_url'  => array( 'Link da página do destino', 'url' ),
	);
}

/** Valor de um campo no contexto. Texto já escapado; URLs cruas (quem imprime escapa). */
function vtec_valor_campo( $campo, $ctx ) {
	$p = isset( $ctx['produto'] ) && is_array( $ctx['produto'] ) ? $ctx['produto'] : array();
	$d = isset( $ctx['destino'] ) && is_array( $ctx['destino'] ) ? $ctx['destino'] : array();
	$v = function ( $k ) use ( $p ) {
		return isset( $p[ $k ] ) && is_string( $p[ $k ] ) ? $p[ $k ] : '';
	};
	switch ( $campo ) {
		case 'titulo':
		case 'duracao':
			return esc_html( $v( $campo ) );
		case 'descricao':
			$h = '';
			foreach ( isset( $p['paragrafos'] ) ? $p['paragrafos'] : array() as $par ) {
				$h .= '<p>' . esc_html( $par ) . '</p>';
			}
			return $h;
		case 'preco':
			return '' !== $v( 'preco' ) ? 'a partir de ' . esc_html( $v( 'preco' ) ) . ', por pessoa' : '';
		case 'galeria':
			return vtec_html_galeria( isset( $p['galeria'] ) ? $p['galeria'] : array(), $v( 'titulo' ) );
		case 'inclusoes':
		case 'exclusoes':
		case 'informacoes':
			return vtec_html_ul( isset( $p[ $campo ] ) ? $p[ $campo ] : array() );
		case 'cancelamento':
		case 'encontro':
			return '' !== $v( $campo ) ? '<p>' . esc_html( $v( $campo ) ) . '</p>' : '';
		case 'nota':
			return '' !== $v( 'codigo' ) ? vtec_html_nota_vazia( $v( 'codigo' ) ) : '';
		case 'imagem':
			return $v( 'imagem' );
		case 'url_passeio':
			return $v( 'url' );
		case 'link_reserva':
			return $v( 'link' );
		case 'destino_nome':
			return esc_html( isset( $d['nome'] ) ? $d['nome'] : '' );
		case 'destino_url':
			return ! empty( $d['slug'] ) ? home_url( '/passeios/' . $d['slug'] . '/' ) : '';
		case 'destino_foto':
			return isset( $d['foto'] ) ? (string) $d['foto'] : '';
	}
	return '';
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

// [vtec campo="destino_nome"] — mesmo valor dos campos dinâmicos, para usar dentro de textos.
add_shortcode( 'vtec', function ( $atts ) {
	$atts = shortcode_atts( array( 'campo' => '' ), $atts, 'vtec' );
	return vtec_valor_campo( sanitize_key( $atts['campo'] ), vtec_contexto_ou_exemplo() );
} );
```

`sanitize_key` troca nada em `destino_nome` (só minúsculas, números, `_` e `-`).

- [ ] **Step 5: `viator-tec.php`** — lista de requires passa a ser:

```php
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade', 'rotas', 'rest', 'sitemap', 'instalador', 'elementor', 'admin', 'atualizador' ) as $vtec_arquivo ) {
```

e criar `grade.php`, `sitemap.php`, `instalador.php`, `elementor.php` só com o cabeçalho `<?php if ( ! defined( 'ABSPATH' ) ) { exit; }` (preenchidos nas próximas tasks). Acrescentar os 4 nomes à lista do `estrutura.test.php`.

- [ ] **Step 6: rodar** — `node tests/run.mjs` → TUDO OK.
- [ ] **Step 7: commit** — `git add -A && git commit -m "fase 2: contexto atual e valores dos campos dinâmicos"`

---

### Task 2: Grade (itens, HTML, render de card) + carregar mais

**Files:**
- Modify: `viator-tec/includes/grade.php`, `viator-tec/includes/render.php` (`vtec_html_destino` usa a grade; card de destino separado), `viator-tec/includes/rotas.php` (usa `vtec_destinos_com_foto`), `viator-tec/includes/rest.php` (`vtec_rest_mais` v2), `viator-tec/assets/viator-tec.js`, `viator-tec/assets/viator-tec.css`
- Test: `tests/php/grade.test.php`, ajustes em `tests/php/rest.test.php` e `tests/php/render.test.php`

**Interfaces:**
- Consumes: `vtec_buscar`, `vtec_destino_por_slug`, `vtec_opcoes`, `vtec_html_cards`, contexto (Task 1).
- Produces:
  - `vtec_destinos_com_foto(): array`
  - `vtec_itens_grade(string $fonte, string $slug, ?array $ctx, string $ordem, int $inicio, int $qtd): array|WP_Error` → `['itens'=>ctx[], 'total'=>int, 'destino'=>?array]`; `$fonte` ∈ `destinos|destino_atual|destino`
  - `vtec_renderizador(): callable` (global `$GLOBALS['vtec_renderizador']`, padrão `vtec_render_elementor`)
  - `vtec_render_elementor(int $modelo, bool $com_css = false): string`
  - `vtec_render_card(int $modelo, array $ctx): string` — restaura o contexto anterior
  - `vtec_card_padrao(array $ctx): string`
  - `vtec_html_grade(array $itens, int $total, int $modelo, array $op): string` — `$op`: `fonte, destino, ordem, qtd, mais_texto`
  - `vtec_html_ordem(array $destino, string $ordem): string`
  - `vtec_render_grade(array $s, array $ctx): string` — `$s` = configurações do widget (`fonte, destino, modelo, quantidade, ordem, mostrar_ordem, mais_texto`)
  - `vtec_html_destino_card(array $d): string` (render.php)
  - `vtec_rest_mais(string $slug, string $ordem, int $inicio, int $modelo = 0, string $fonte = 'destino', int $qtd = 0): array|WP_Error`

- [ ] **Step 1: teste `tests/php/grade.test.php`**

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array(
	array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/lv.jpg' ),
	array( 'id' => 712, 'nome' => 'Rio de Janeiro', 'slug' => 'rio-de-janeiro', 'foto' => 'https://x.test/rj.jpg' ),
) ) );

// renderizador falso: devolve o título do contexto no momento do render
$GLOBALS['vtec_renderizador'] = function ( $modelo ) {
	$c = vtec_contexto();
	return '[m' . $modelo . ':' . ( $c['produto'] ? $c['produto']['titulo'] : $c['destino']['nome'] ) . ']';
};

$r = vtec_itens_grade( 'destinos', '', null, '', 1, 10 );
igual( 2, $r['total'], 'destinos: total' );
igual( 'Rio de Janeiro', $r['itens'][1]['destino']['nome'], 'destinos: contexto de destino' );
igual( 1, count( vtec_itens_grade( 'destinos', '', null, '', 2, 10 )['itens'] ), 'destinos: paginação' );

resposta_falsa( 200, fixture( 'search' ) );
$pagina = array( 'destino' => vtec_destino_por_slug( 'las-vegas' ), 'produto' => null, 'ordem' => 'preco' );
$r = vtec_itens_grade( 'destino_atual', '', $pagina, 'preco', 1, 3 );
igual( 1309, $r['total'], 'destino atual: total da Viator' );
igual( '56549P1', $r['itens'][0]['produto']['codigo'], 'destino atual: contexto de passeio' );
igual( 'Las Vegas', $r['itens'][0]['destino']['nome'], 'passeio leva o destino junto' );
igual( array(), vtec_itens_grade( 'destino_atual', '', null, 'preco', 1, 3 )['itens'], 'sem destino atual: vazio' );
igual( array(), vtec_itens_grade( 'destino', 'xx', null, 'preco', 1, 3 )['itens'], 'destino fora da lista: vazio' );

vtec_definir_contexto( $pagina );
$h = vtec_html_grade( $r['itens'], $r['total'], 9, array( 'fonte' => 'destino', 'destino' => 'las-vegas', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'Carregar <mais>' ) );
contem( '<div class="vtec-grade-el">', $h, 'grade' );
igual( 3, substr_count( $h, '<div class="vtec-grade-item">' ), 'um item por passeio' );
contem( '[m9:Vale Privado do Fogo Caminhadas e Aventura]', $h, 'card renderizado com o contexto do passeio' );
igual( $pagina, vtec_contexto(), 'contexto da página restaurado depois dos cards' );
contem( 'data-inicio="4"', $h, 'carregar mais começa depois dos 3' );
contem( 'data-modelo="9"', $h, 'botão sabe o modelo' );
contem( 'data-fonte="destino"', $h, 'botão sabe a fonte' );
contem( 'Carregar &lt;mais&gt;', $h, 'texto do botão escapado' );
nao_contem( 'vtec-mais', vtec_html_grade( $r['itens'], 3, 9, array( 'fonte' => 'destino', 'destino' => 'x', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'x' ) ), 'sem botão quando cabe tudo' );
contem( 'Nenhum passeio', vtec_html_grade( array(), 0, 9, array( 'fonte' => 'destino', 'destino' => 'x', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'x' ) ), 'grade vazia' );

// sem modelo (ou modelo que não renderiza nada) usa o card padrão da fase 1
contem( 'vtec-card', vtec_render_card( 0, $r['itens'][0] ), 'card padrão de passeio' );
$GLOBALS['vtec_renderizador'] = function () { return '   '; };
contem( 'vtec-card', vtec_render_card( 5, $r['itens'][0] ), 'modelo vazio cai no card padrão' );
contem( 'vtec-destino', vtec_render_card( 0, array( 'destino' => vtec_destino_por_slug( 'rio-de-janeiro' ), 'produto' => null ) ), 'card padrão de destino' );

$o = vtec_html_ordem( vtec_destino_por_slug( 'las-vegas' ), 'preco' );
contem( 'href="https://exemplo.test/passeios/las-vegas/?ordem=preco" aria-current="true"', $o, 'ordem atual marcada' );

// widget: fonte destino_atual com ordem da URL quando "mostrar ordem" está ligado
$GLOBALS['vtec_renderizador'] = function ( $m ) { return '[card]'; };
resposta_falsa( 200, fixture( 'search' ) );
$w = vtec_render_grade( array( 'fonte' => 'destino_atual', 'modelo' => 3, 'quantidade' => 3, 'ordem' => 'avaliacao', 'mostrar_ordem' => 'yes', 'mais_texto' => 'Mais' ), $pagina );
contem( 'vtec-ordem', $w, 'widget mostra os botões de ordem' );
igual( 'PRICE', json_decode( end( $GLOBALS['vt_http_log'] )[1]['body'], true )['sorting']['sort'], 'ordem da URL vence a do widget' );
contem( '[card]', $w, 'widget usa o modelo' );
```

- [ ] **Step 2: rodar e ver falhar** — `node tests/run.mjs grade`.

- [ ] **Step 3: `includes/grade.php`**

```php
<?php
/** Widget "Viator – Grade": busca os itens, define o contexto de cada um e renderiza o modelo de card. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_destinos_com_foto() {
	$lista = array();
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		if ( '' === $d['foto'] ) {
			$r         = vtec_buscar( $d['id'], 'avaliacao', 1, 1 );
			$d['foto'] = ! is_wp_error( $r ) && $r['cards'] ? $r['cards'][0]['imagem'] : '';
		}
		$lista[] = $d;
	}
	return $lista;
}

function vtec_itens_grade( $fonte, $slug, $ctx, $ordem, $inicio, $qtd ) {
	$inicio = max( 1, (int) $inicio );
	$qtd    = min( 50, max( 1, (int) $qtd ) );
	if ( 'destinos' === $fonte ) {
		$todos = vtec_destinos_com_foto();
		$itens = array();
		foreach ( array_slice( $todos, $inicio - 1, $qtd ) as $d ) {
			$itens[] = array( 'destino' => $d, 'produto' => null );
		}
		return array( 'itens' => $itens, 'total' => count( $todos ), 'destino' => null );
	}
	$d = 'destino' === $fonte ? vtec_destino_por_slug( $slug ) : ( isset( $ctx['destino'] ) && is_array( $ctx['destino'] ) ? $ctx['destino'] : null );
	if ( ! $d || empty( $d['id'] ) ) {
		return array( 'itens' => array(), 'total' => 0, 'destino' => null );
	}
	$r = vtec_buscar( $d['id'], $ordem, $inicio, $qtd );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$itens = array();
	foreach ( $r['cards'] as $c ) {
		$itens[] = array( 'destino' => $d, 'produto' => $c );
	}
	return array( 'itens' => $itens, 'total' => $r['total'], 'destino' => $d );
}

function vtec_renderizador() {
	return isset( $GLOBALS['vtec_renderizador'] ) ? $GLOBALS['vtec_renderizador'] : 'vtec_render_elementor';
}

/** Conteúdo de um modelo do Elementor. Vazio se o Elementor não estiver ativo ou o modelo não existir. */
function vtec_render_elementor( $modelo, $com_css = false ) {
	if ( ! class_exists( '\Elementor\Plugin' ) || 'elementor_library' !== get_post_type( (int) $modelo ) ) {
		return '';
	}
	return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( (int) $modelo, $com_css );
}

function vtec_card_padrao( $ctx ) {
	if ( ! empty( $ctx['produto'] ) ) {
		return vtec_html_cards( array( $ctx['produto'] ) );
	}
	return ! empty( $ctx['destino'] ) ? vtec_html_destino_card( $ctx['destino'] ) : '';
}

function vtec_render_card( $modelo, $ctx ) {
	$antes = vtec_contexto();
	vtec_definir_contexto( $ctx );
	$html = $modelo ? (string) call_user_func( vtec_renderizador(), (int) $modelo ) : '';
	if ( '' === trim( $html ) ) {
		$html = vtec_card_padrao( $ctx );
	}
	vtec_definir_contexto( $antes );
	return $html;
}

function vtec_html_grade( $itens, $total, $modelo, $op ) {
	if ( ! $itens ) {
		return '<p class="vtec-vazio">Nenhum passeio encontrado.</p>';
	}
	$h = '<div class="vtec-grade-el">';
	foreach ( $itens as $ctx ) {
		$h .= '<div class="vtec-grade-item">' . vtec_render_card( $modelo, $ctx ) . '</div>';
	}
	$h .= '</div>';
	if ( $total > count( $itens ) ) {
		$h .= '<button type="button" class="vtec-mais"'
			. ' data-fonte="' . esc_attr( $op['fonte'] ) . '" data-destino="' . esc_attr( $op['destino'] ) . '"'
			. ' data-ordem="' . esc_attr( $op['ordem'] ) . '" data-modelo="' . (int) $modelo . '"'
			. ' data-qtd="' . (int) $op['qtd'] . '" data-inicio="' . ( count( $itens ) + 1 ) . '">'
			. esc_html( $op['mais_texto'] ) . '</button>';
	}
	return $h;
}

function vtec_html_ordem( $destino, $ordem ) {
	$base = home_url( '/passeios/' . $destino['slug'] . '/' );
	$h    = '<nav class="vtec-ordem">Ordenar: ';
	foreach ( array( 'avaliacao' => 'Mais bem avaliados', 'preco' => 'Menor preço' ) as $valor => $rotulo ) {
		$url = 'avaliacao' === $valor ? $base : $base . '?ordem=' . $valor;
		$h  .= '<a href="' . esc_url( $url ) . '"' . ( $valor === $ordem ? ' aria-current="true"' : '' ) . '>' . esc_html( $rotulo ) . '</a> ';
	}
	return $h . '</nav>';
}

function vtec_render_grade( $s, $ctx ) {
	$fonte = isset( $s['fonte'] ) ? (string) $s['fonte'] : 'destino_atual';
	$ordem = isset( $s['ordem'] ) && 'preco' === $s['ordem'] ? 'preco' : 'avaliacao';
	if ( 'destino_atual' === $fonte && ! empty( $s['mostrar_ordem'] ) && isset( $ctx['ordem'] ) && in_array( $ctx['ordem'], array( 'avaliacao', 'preco' ), true ) ) {
		$ordem = $ctx['ordem'];
	}
	$qtd = isset( $s['quantidade'] ) ? min( 50, max( 1, (int) $s['quantidade'] ) ) : 12;
	$r   = vtec_itens_grade( $fonte, isset( $s['destino'] ) ? (string) $s['destino'] : '', $ctx, $ordem, 1, $qtd );
	if ( is_wp_error( $r ) ) {
		return vtec_html_indisponivel();
	}
	$h = ( 'destinos' !== $fonte && ! empty( $s['mostrar_ordem'] ) && $r['destino'] ) ? vtec_html_ordem( $r['destino'], $ordem ) : '';
	return $h . vtec_html_grade( $r['itens'], $r['total'], isset( $s['modelo'] ) ? (int) $s['modelo'] : 0, array(
		'fonte'      => 'destinos' === $fonte ? 'destinos' : 'destino',
		'destino'    => $r['destino'] ? $r['destino']['slug'] : '',
		'ordem'      => $ordem,
		'qtd'        => $qtd,
		'mais_texto' => isset( $s['mais_texto'] ) && '' !== $s['mais_texto'] ? $s['mais_texto'] : 'Carregar mais',
	) );
}
```

- [ ] **Step 4: `includes/render.php`** — extrair o card de destino e fazer `vtec_html_destinos`/`vtec_html_destino` usarem a grade:

```php
function vtec_html_destino_card( $d ) {
	return '<a class="vtec-destino" href="' . esc_url( home_url( '/passeios/' . $d['slug'] . '/' ) ) . '">'
		. vtec_html_img( $d['foto'], $d['nome'], 'vtec-destino-foto' )
		. '<span class="vtec-destino-nome">' . esc_html( $d['nome'] ) . '</span></a>';
}

function vtec_html_destinos( $destinos ) {
	$h = '<div class="vtec"><h1 class="vtec-titulo">Passeios e ingressos</h1><div class="vtec-grade vtec-grade-destinos">';
	foreach ( $destinos as $d ) {
		$h .= vtec_html_destino_card( $d );
	}
	return $h . '</div></div>';
}

function vtec_html_destino( $destino, $cards, $total, $ordem, $por_pagina ) {
	$h = '<div class="vtec"><p class="vtec-voltar"><a href="' . esc_url( home_url( '/passeios/' ) ) . '">← Todos os destinos</a></p>'
		. '<h1 class="vtec-titulo">Passeios em ' . esc_html( $destino['nome'] ) . '</h1>'
		. '<p class="vtec-aviso">' . esc_html( VTEC_AVISO ) . '</p>';
	if ( ! $cards ) {
		return $h . '<p class="vtec-vazio">Nenhum passeio encontrado neste destino.</p></div>';
	}
	$itens = array();
	foreach ( $cards as $c ) {
		$itens[] = array( 'destino' => $destino, 'produto' => $c );
	}
	return $h . vtec_html_ordem( $destino, $ordem ) . vtec_html_grade( $itens, $total, 0, array(
		'fonte' => 'destino', 'destino' => $destino['slug'], 'ordem' => $ordem, 'qtd' => $por_pagina, 'mais_texto' => 'Carregar mais passeios',
	) ) . '</div>';
}
```

(remove a versão antiga de `vtec_html_destino`; o laço de ordem passa a ser `vtec_html_ordem` do `grade.php`). Em `render.test.php`, o teste `sem botão quando cabe tudo` continua; `data-inicio="13"` continua (12 cards). `render.test.php` precisa carregar `contexto` e `grade`: trocar a 1ª linha de requires por `foreach ( array( 'dados', 'render', 'contexto', 'grade' ) as $f )`.

- [ ] **Step 5: `includes/rotas.php`** — no ramo `destinos` de `vtec_resolver_pagina`, trocar o laço de fotos por `$destinos = vtec_destinos_com_foto();`. `rotas.test.php` passa a carregar também `contexto` e `grade`.

- [ ] **Step 6: `includes/rest.php`** — nova `vtec_rest_mais` e rota com os parâmetros novos:

```php
function vtec_rest_mais( $slug, $ordem, $inicio, $modelo = 0, $fonte = 'destino', $qtd = 0 ) {
	$qtd = $qtd ? min( 50, max( 1, (int) $qtd ) ) : vtec_opcoes()['por_pagina'];
	if ( 'destinos' !== $fonte && ! vtec_destino_por_slug( $slug ) ) {
		return new WP_Error( 'vtec_destino', 'Destino não encontrado.', array( 'status' => 404 ) );
	}
	$r = vtec_itens_grade( 'destinos' === $fonte ? 'destinos' : 'destino', $slug, null, $ordem, $inicio, $qtd );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$html = '';
	foreach ( $r['itens'] as $ctx ) {
		$html .= '<div class="vtec-grade-item">' . vtec_render_card( (int) $modelo, $ctx ) . '</div>';
	}
	$proximo = $inicio + count( $r['itens'] );
	return array( 'html' => $html, 'proximo' => $r['itens'] && $proximo <= $r['total'] ? $proximo : null );
}
```

callback da rota `/mais`:

```php
		'callback'            => function ( $req ) {
			return rest_ensure_response( vtec_rest_mais(
				sanitize_title( (string) $req->get_param( 'destino' ) ),
				sanitize_key( (string) $req->get_param( 'ordem' ) ),
				max( 1, (int) $req->get_param( 'inicio' ) ),
				(int) $req->get_param( 'modelo' ),
				'destinos' === $req->get_param( 'fonte' ) ? 'destinos' : 'destino',
				(int) $req->get_param( 'qtd' )
			) );
		},
```

`rest.test.php`: carregar `contexto` e `grade`; os testes existentes continuam válidos (`vtec-card` no html, `proximo` 7 e null).

- [ ] **Step 7: `assets/viator-tec.js`**

```js
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
```

- [ ] **Step 8: `assets/viator-tec.css`** — acrescentar:

```css
.vtec-grade-el{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:30px}
.vtec-grade-item{display:flex;flex-direction:column;min-width:0}
.vtec-grade-item > *{flex:1}
.vtec-grade-item .vtec-card,.vtec-grade-item .vtec-destino{height:100%}
```

- [ ] **Step 9: rodar** — `node tests/run.mjs` → TUDO OK.
- [ ] **Step 10: commit** — `git add -A && git commit -m "fase 2: grade com modelo de card, contexto por item e carregar mais"`

---

### Task 3: Rotas com modelo, opções dos modelos e sitemap

**Files:**
- Modify: `viator-tec/includes/rotas.php`, `viator-tec/templates/pagina.php`, `viator-tec/includes/opcoes.php`, `viator-tec/includes/sitemap.php`
- Test: `tests/php/rotas.test.php`, `tests/php/opcoes.test.php`, `tests/php/sitemap.test.php`

**Interfaces:**
- Produces:
  - `vtec_opcoes()['modelos']` = `['card_destino'=>int,'card_passeio'=>int,'destinos'=>int,'destino'=>int,'produto'=>int]` (padrão 0)
  - `vtec_resolver_pagina` devolve também `'contexto'` (array) e `'modelo'` (int)
  - `vtec_render_pagina(array $atual): string` — modelo pelo renderizador (com CSS) ou o `html` da fase 1
  - `vtec_urls_sitemap(): string[]`, `vtec_xml_sitemap(array $urls): string`, `vtec_indice_sitemap(string $xml): string`
  - regra de rewrite `^passeios-sitemap\.xml$` → `index.php?vtec_pagina=sitemap` (a primeira)

- [ ] **Step 1: testes** — acrescentar ao `opcoes.test.php`:

```php
igual( array( 'card_destino' => 0, 'card_passeio' => 0, 'destinos' => 0, 'destino' => 0, 'produto' => 0 ), vtec_opcoes_padrao()['modelos'], 'modelos padrão' );
$atual2 = vtec_opcoes_padrao();
$atual2['modelos']['produto'] = 77;
$m = vtec_sanitizar_opcoes( array( 'modelo_destino' => '55', 'modelo_destinos' => 'abc' ), $atual2 )['modelos'];
igual( 55, $m['destino'], 'modelo escolhido no painel' );
igual( 77, $m['produto'], 'modelo não enviado mantém o salvo' );
igual( 0, $m['destinos'], 'valor inválido vira 0' );
```

em `rotas.test.php`, trocar a verificação de ordem das regras por:

```php
igual( array( '^passeios-sitemap\.xml$', '^passeios/?$', $regra_produto, '^passeios/([^/]+)/?$' ), array_keys( $regras ), 'sitemap, lista, produto e destino, nessa ordem' );
```

e acrescentar no fim:

```php
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'modelos' => array( 'destino' => 41, 'produto' => 42 ), 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );
vtec_limpar_cache();
resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', 'preco' );
igual( 41, $r['modelo'], 'destino usa o modelo escolhido' );
igual( 'Las Vegas', $r['contexto']['destino']['nome'], 'contexto do destino' );
igual( 'preco', $r['contexto']['ordem'], 'contexto leva a ordem' );
$GLOBALS['vtec_renderizador'] = function ( $m, $css = false ) { $c = vtec_contexto(); return "[modelo $m:" . $c['destino']['nome'] . ( $css ? ':css' : '' ) . ']'; };
igual( '[modelo 41:Las Vegas:css]', vtec_render_pagina( $r ), 'página renderizada pelo modelo, com CSS' );
$GLOBALS['vtec_renderizador'] = function () { return ''; };
contem( 'Passeios em Las Vegas', vtec_render_pagina( $r ), 'modelo vazio: volta ao HTML da fase 1' );
$r['modelo'] = 0;
contem( 'Passeios em Las Vegas', vtec_render_pagina( $r ), 'sem modelo: HTML da fase 1' );
resposta_falsa( 200, fixture( 'product' ) );
resposta_falsa( 200, fixture( 'schedules' ) );
resposta_falsa( 200, fixture( 'exchange' ) );
$r = vtec_resolver_pagina( 'produto', '', '56549P1', '' );
igual( 42, $r['modelo'], 'produto usa o modelo escolhido' );
igual( 'R$ 954,87', $r['contexto']['produto']['preco'], 'contexto do produto com preço' );
```

`tests/php/sitemap.test.php`:

```php
<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'sitemap' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );
resposta_falsa( 200, fixture( 'search' ) );
$u = vtec_urls_sitemap();
igual( 'https://exemplo.test/passeios/', $u[0], 'lista de destinos' );
igual( 'https://exemplo.test/passeios/las-vegas/', $u[1], 'destino' );
igual( 5, count( $u ), 'lista + destino + 3 passeios' );
$x = vtec_xml_sitemap( array( 'https://exemplo.test/a?b=1&c=2' ) );
contem( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $x, 'urlset' );
contem( '<loc>https://exemplo.test/a?b=1&amp;c=2</loc>', $x, '& escapado para XML' );
contem( '<sitemap><loc>https://exemplo.test/passeios-sitemap.xml</loc>', vtec_indice_sitemap( '' ), 'entrada no índice do Rank Math' );
```

- [ ] **Step 2: rodar e ver falhar** — `node tests/run.mjs opcoes; node tests/run.mjs rotas; node tests/run.mjs sitemap`.

- [ ] **Step 3: `includes/opcoes.php`** — em `vtec_opcoes_padrao()` acrescentar
`'modelos' => array( 'card_destino' => 0, 'card_passeio' => 0, 'destinos' => 0, 'destino' => 0, 'produto' => 0 ),`
e em `vtec_sanitizar_opcoes`, antes do `return $s;`:

```php
	$salvos = isset( $atual['modelos'] ) && is_array( $atual['modelos'] ) ? $atual['modelos'] : array();
	foreach ( array_keys( $s['modelos'] ) as $chave ) {
		if ( isset( $entrada[ 'modelo_' . $chave ] ) ) {
			$s['modelos'][ $chave ] = ctype_digit( (string) $entrada[ 'modelo_' . $chave ] ) ? (int) $entrada[ 'modelo_' . $chave ] : 0;
		} elseif ( isset( $salvos[ $chave ] ) ) {
			$s['modelos'][ $chave ] = (int) $salvos[ $chave ];
		}
	}
```

e em `vtec_opcoes()` garantir que `modelos` salvo parcial herda as chaves: 

```php
function vtec_opcoes() {
	$o            = array_merge( vtec_opcoes_padrao(), (array) get_option( 'vtec_opcoes', array() ) );
	$o['modelos'] = array_merge( vtec_opcoes_padrao()['modelos'], (array) $o['modelos'] );
	return $o;
}
```

- [ ] **Step 4: `includes/rotas.php`**
  - `vtec_rotas()` ganha, como **primeira** linha: `add_rewrite_rule( '^passeios-sitemap\.xml$', 'index.php?vtec_pagina=sitemap', 'top' );`
  - Em `vtec_resolver_pagina`, cada `return array( 'status' => 200, ... )` ganha `'contexto'` e `'modelo'`:
    - destinos: `'contexto' => array( 'destino' => null, 'produto' => null ), 'modelo' => (int) $o['modelos']['destinos'],`
    - destino: `'contexto' => array( 'destino' => $d, 'produto' => null, 'ordem' => $ordem ), 'modelo' => (int) $o['modelos']['destino'],`
    - produto (sucesso): calcular `$preco = vtec_preco_a_partir( $codigo ); $v = vtec_produto_view( $p, $preco );` e `'contexto' => array( 'destino' => null, 'produto' => $v ), 'modelo' => (int) $o['modelos']['produto'],`
    - produto indisponível e `vtec_pagina_404()`: `'contexto' => array(), 'modelo' => 0`.
  - Nova função pura (antes do `if ( defined( 'VTEC_TESTE' ) )`):

```php
/** Conteúdo da página: o modelo do Elementor escolhido (com o contexto) ou, se não houver/vier vazio, o HTML da fase 1. */
function vtec_render_pagina( $atual ) {
	if ( ! empty( $atual['modelo'] ) ) {
		vtec_definir_contexto( $atual['contexto'] );
		$html = (string) call_user_func( vtec_renderizador(), (int) $atual['modelo'], true );
		if ( '' !== trim( $html ) ) {
			return $html;
		}
	}
	return $atual['html'];
}
```

  - No `template_redirect`, logo depois de `if ( ! $pagina ) { return; }`:

```php
	if ( 'sitemap' === $pagina ) {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo vtec_xml_sitemap( vtec_urls_sitemap() ); // phpcs:ignore -- XML escapado em vtec_xml_sitemap
		exit;
	}
```

  - Registro dos assets passa a ser separado do enqueue (o widget depende deles fora das rotas):

```php
add_action( 'init', function () {
	wp_register_style( 'viator-tec', VTEC_URL . 'assets/viator-tec.css', array(), VTEC_VERSION );
	wp_register_script( 'viator-tec', VTEC_URL . 'assets/viator-tec.js', array(), VTEC_VERSION, true );
	wp_register_script( 'viator-tec-protegido', VTEC_URL . 'assets/protegido.js', array(), VTEC_VERSION, true );
	wp_add_inline_script( 'viator-tec', 'window.vtecCfg=' . wp_json_encode( array( 'rest' => rest_url( 'viator-tec/v1/' ) ) ) . ';', 'before' );
	wp_add_inline_script( 'viator-tec-protegido', 'window.vtecCfg=window.vtecCfg||' . wp_json_encode( array( 'rest' => rest_url( 'viator-tec/v1/' ) ) ) . ';', 'before' );
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! isset( $GLOBALS['vtec_atual'] ) ) {
		return;
	}
	wp_enqueue_style( 'viator-tec' );
	wp_enqueue_script( 'viator-tec' );
	wp_enqueue_script( 'viator-tec-protegido' );
} );
```

(substitui o `wp_enqueue_scripts` da fase 1).

- [ ] **Step 5: `templates/pagina.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
echo '<main id="content" class="site-main vtec-main">' . vtec_render_pagina( $GLOBALS['vtec_atual'] ) . '</main>'; // phpcs:ignore -- HTML escapado em render.php / Elementor
get_footer();
```

- [ ] **Step 6: `includes/sitemap.php`**

```php
<?php
/** /passeios-sitemap.xml: lista de destinos, cada destino e os passeios da 1ª página. Entra no índice do Rank Math. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_urls_sitemap() {
	$o    = vtec_opcoes();
	$urls = array( home_url( '/passeios/' ) );
	foreach ( $o['destinos'] as $d ) {
		$urls[] = home_url( '/passeios/' . $d['slug'] . '/' );
		$r      = vtec_buscar( $d['id'], 'avaliacao', 1, $o['por_pagina'] );
		if ( ! is_wp_error( $r ) ) {
			foreach ( $r['cards'] as $c ) {
				$urls[] = $c['url'];
			}
		}
	}
	return array_values( array_unique( $urls ) );
}

function vtec_xml_sitemap( $urls ) {
	$x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	foreach ( $urls as $u ) {
		$x .= '<url><loc>' . htmlspecialchars( $u, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</loc></url>';
	}
	return $x . '</urlset>';
}

function vtec_indice_sitemap( $xml ) {
	return $xml . '<sitemap><loc>' . htmlspecialchars( home_url( '/passeios-sitemap.xml' ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</loc></sitemap>';
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_filter( 'rank_math/sitemap/index', 'vtec_indice_sitemap', 20 );
```

- [ ] **Step 7: rodar** — `node tests/run.mjs` → TUDO OK.
- [ ] **Step 8: commit** — `git add -A && git commit -m "fase 2: rotas renderizam o modelo do Elementor, opções de modelos e sitemap"`

---

### Task 4: Integração com o Elementor (campos dinâmicos, widget, cache)

**Files:**
- Modify: `viator-tec/includes/elementor.php`
- Create: `viator-tec/includes/elementor-classes.php`, `tests/php/elementor.test.php`

**Interfaces:**
- Consumes: `vtec_campos_tags`, `vtec_valor_campo`, `vtec_contexto_ou_exemplo`, `vtec_render_grade`, `vtec_opcoes`.
- Produces: 17 classes de tag `VTEC_Tag_<Campo>` (nome `vtec-<campo>`), `VTEC_Widget_Grade` (nome `vtec-grade`), `vtec_classes_tags(): array` (`campo => classe`), `vtec_elemento_dinamico(bool $atual, array $dados): bool`, `vtec_opcoes_modelos_select(): array`, `vtec_opcoes_destinos_select(): array`.

- [ ] **Step 1: teste `tests/php/elementor.test.php`** (stubs mínimos das classes do Elementor)

```php
<?php
namespace Elementor\Core\DynamicTags {
	abstract class Base_Tag { public function __construct( $d = array() ) {} }
	abstract class Tag extends Base_Tag {}
	abstract class Data_Tag extends Base_Tag {}
}
namespace Elementor\Modules\DynamicTags {
	class Module { const TEXT_CATEGORY = 'text'; const URL_CATEGORY = 'url'; const IMAGE_CATEGORY = 'image'; }
}
namespace Elementor {
	class Controls_Manager { const SELECT = 'select'; const NUMBER = 'number'; const TEXT = 'text'; const SWITCHER = 'switcher'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; }
	abstract class Widget_Base {
		public $s = array(); public $controles = array();
		public function __construct( $d = array(), $a = null ) {}
		protected function start_controls_section( $id, $a ) {}
		protected function end_controls_section() {}
		protected function add_control( $id, $a ) { $this->controles[ $id ] = $a; }
		protected function add_responsive_control( $id, $a ) { $this->controles[ $id ] = $a; }
		public function get_settings_for_display() { return $this->s; }
		public function registrar() { $this->register_controls(); }
		public function renderizar() { ob_start(); $this->render(); return ob_get_clean(); }
	}
}
namespace {
	require '/t/bootstrap.php';
	function get_posts( $a ) { return array( (object) array( 'ID' => 41, 'post_title' => 'Passeios – card de passeio' ) ); }
	foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade', 'elementor' ) as $f ) { require "/p/includes/$f.php"; }
	require '/p/includes/elementor-classes.php';

	$classes = vtec_classes_tags();
	igual( 17, count( $classes ), 'uma classe por campo' );
	$nomes = array();
	foreach ( $classes as $campo => $classe ) {
		$t = new $classe();
		$nomes[] = $t->get_name();
		igual( 'viator', $t->get_group(), "$campo no grupo Viator" );
	}
	igual( 17, count( array_unique( $nomes ) ), 'nomes únicos' );
	ok( in_array( 'vtec-link-reserva', $nomes, true ), 'nome com hífen' );

	$ctx = array( 'destino' => array( 'id' => 1, 'nome' => 'Rio', 'slug' => 'rio', 'foto' => 'https://x.test/r.jpg' ), 'produto' => array( 'titulo' => 'Cristo', 'url' => 'https://exemplo.test/passeios/p/X1-cristo/', 'imagem' => 'https://x.test/c.jpg', 'codigo' => 'X1' ) );
	vtec_definir_contexto( $ctx );
	$titulo = new VTEC_Tag_Titulo();
	ob_start(); $titulo->render(); igual( 'Cristo', ob_get_clean(), 'tag de texto imprime o valor' );
	igual( array( 'text' ), $titulo->get_categories(), 'categoria texto' );
	$img = new VTEC_Tag_Imagem();
	igual( array( 'id' => '', 'url' => 'https://x.test/c.jpg' ), $img->get_value(), 'tag de imagem devolve url' );
	igual( array( 'image' ), $img->get_categories(), 'categoria imagem' );
	$url = new VTEC_Tag_Url_Passeio();
	igual( 'https://exemplo.test/passeios/p/X1-cristo/', $url->get_value(), 'tag de url' );
	igual( array( 'url' ), $url->get_categories(), 'categoria url' );

	$w = new VTEC_Widget_Grade();
	$w->registrar();
	igual( 'vtec-grade', $w->get_name(), 'nome do widget' );
	ok( isset( $w->controles['fonte'], $w->controles['modelo'], $w->controles['colunas'] ), 'controles principais' );
	igual( array( '0' => '— Card padrão do plugin —', '41' => 'Passeios – card de passeio' ), $w->controles['modelo']['options'], 'lista de modelos' );
	$GLOBALS['vtec_renderizador'] = function () { return '[c]'; };
	update_option( 'vtec_opcoes', array( 'destinos' => array( array( 'id' => 1, 'nome' => 'Rio', 'slug' => 'rio', 'foto' => 'f' ) ) ) );
	$w->s = array( 'fonte' => 'destinos', 'modelo' => '41', 'quantidade' => 12 );
	contem( '<div class="vtec-grade-item">[c]</div>', $w->renderizar(), 'widget renderiza a grade' );

	ok( vtec_elemento_dinamico( false, array( 'settings' => array( '__dynamic__' => array( 'title' => '[elementor-tag id="1" name="vtec-titulo" settings="%7B%7D"]' ) ) ) ), 'tag vtec marca o elemento como dinâmico' );
	ok( ! vtec_elemento_dinamico( false, array( 'settings' => array( '__dynamic__' => array( 'title' => '[elementor-tag name="post-title"]' ) ) ) ), 'outras tags não mudam' );
	ok( vtec_elemento_dinamico( false, array( 'widgetType' => 'vtec-grade', 'settings' => array() ) ), 'a grade é dinâmica' );
}
```

- [ ] **Step 2: rodar e ver falhar** — `node tests/run.mjs elementor`.

- [ ] **Step 3: `includes/elementor.php`**

```php
<?php
/** Ganchos do Elementor. As classes (que estendem o Elementor) só carregam quando ele está ativo. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_classes_tags() {
	$classes = array();
	foreach ( array_keys( vtec_campos_tags() ) as $campo ) {
		$classes[ $campo ] = 'VTEC_Tag_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $campo ) ) );
	}
	return $classes;
}

/** Elementos com campos Viator (ou a grade) nunca entram no cache de elementos do Elementor. */
function vtec_elemento_dinamico( $atual, $dados ) {
	if ( $atual ) {
		return true;
	}
	if ( isset( $dados['widgetType'] ) && 'vtec-grade' === $dados['widgetType'] ) {
		return true;
	}
	$din = isset( $dados['settings']['__dynamic__'] ) ? wp_json_encode( $dados['settings']['__dynamic__'] ) : '';
	return false !== strpos( (string) $din, 'name=\"vtec-' ) || false !== strpos( (string) $din, 'name="vtec-' );
}

function vtec_opcoes_modelos_select() {
	$lista = array( '0' => '— Card padrão do plugin —' );
	foreach ( get_posts( array( 'post_type' => 'elementor_library', 'numberposts' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
		$lista[ (string) $p->ID ] = $p->post_title;
	}
	return $lista;
}

function vtec_opcoes_destinos_select() {
	$lista = array();
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		$lista[ $d['slug'] ] = $d['nome'];
	}
	return $lista;
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'elementor/dynamic_tags/register', function ( $gerente ) {
	require_once VTEC_DIR . 'includes/elementor-classes.php';
	$gerente->register_group( 'viator', array( 'title' => 'Viator' ) );
	foreach ( vtec_classes_tags() as $classe ) {
		$gerente->register( new $classe() );
	}
} );

add_action( 'elementor/widgets/register', function ( $gerente ) {
	require_once VTEC_DIR . 'includes/elementor-classes.php';
	$gerente->register( new VTEC_Widget_Grade() );
} );

add_filter( 'elementor/element/is_dynamic_content', 'vtec_elemento_dinamico', 10, 2 );
```

- [ ] **Step 4: `includes/elementor-classes.php`**

```php
<?php
/** Classes que estendem o Elementor. Carregado só pelos ganchos do Elementor (ou pelos testes, com stubs). */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as Tags;

abstract class VTEC_Tag_Texto extends \Elementor\Core\DynamicTags\Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::TEXT_CATEGORY ); }
	public function render() { echo vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ); } // phpcs:ignore -- escapado em vtec_valor_campo
}

abstract class VTEC_Tag_Link extends \Elementor\Core\DynamicTags\Data_Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::URL_CATEGORY ); }
	public function get_value( array $options = array() ) { return vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ); }
}

abstract class VTEC_Tag_Foto extends \Elementor\Core\DynamicTags\Data_Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::IMAGE_CATEGORY ); }
	public function get_value( array $options = array() ) { return array( 'id' => '', 'url' => vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ) ); }
}

class VTEC_Tag_Titulo extends VTEC_Tag_Texto { const CAMPO = 'titulo'; }
class VTEC_Tag_Descricao extends VTEC_Tag_Texto { const CAMPO = 'descricao'; }
class VTEC_Tag_Preco extends VTEC_Tag_Texto { const CAMPO = 'preco'; }
class VTEC_Tag_Duracao extends VTEC_Tag_Texto { const CAMPO = 'duracao'; }
class VTEC_Tag_Galeria extends VTEC_Tag_Texto { const CAMPO = 'galeria'; }
class VTEC_Tag_Inclusoes extends VTEC_Tag_Texto { const CAMPO = 'inclusoes'; }
class VTEC_Tag_Exclusoes extends VTEC_Tag_Texto { const CAMPO = 'exclusoes'; }
class VTEC_Tag_Encontro extends VTEC_Tag_Texto { const CAMPO = 'encontro'; }
class VTEC_Tag_Cancelamento extends VTEC_Tag_Texto { const CAMPO = 'cancelamento'; }
class VTEC_Tag_Informacoes extends VTEC_Tag_Texto { const CAMPO = 'informacoes'; }
class VTEC_Tag_Nota extends VTEC_Tag_Texto { const CAMPO = 'nota'; }
class VTEC_Tag_Destino_Nome extends VTEC_Tag_Texto { const CAMPO = 'destino_nome'; }
class VTEC_Tag_Imagem extends VTEC_Tag_Foto { const CAMPO = 'imagem'; }
class VTEC_Tag_Destino_Foto extends VTEC_Tag_Foto { const CAMPO = 'destino_foto'; }
class VTEC_Tag_Url_Passeio extends VTEC_Tag_Link { const CAMPO = 'url_passeio'; }
class VTEC_Tag_Link_Reserva extends VTEC_Tag_Link { const CAMPO = 'link_reserva'; }
class VTEC_Tag_Destino_Url extends VTEC_Tag_Link { const CAMPO = 'destino_url'; }

class VTEC_Widget_Grade extends \Elementor\Widget_Base {
	public function get_name() { return 'vtec-grade'; }
	public function get_title() { return 'Viator – Grade'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords() { return array( 'viator', 'passeios', 'grade', 'destinos' ); }
	public function get_style_depends() { return array( 'viator-tec' ); }
	public function get_script_depends() { return array( 'viator-tec', 'viator-tec-protegido' ); }
	protected function is_dynamic_content(): bool { return true; }

	protected function register_controls() {
		$this->start_controls_section( 'vtec_conteudo', array( 'label' => 'Conteúdo' ) );
		$this->add_control( 'fonte', array(
			'label' => 'Mostrar', 'type' => Controls_Manager::SELECT, 'default' => 'destino_atual',
			'options' => array( 'destinos' => 'Destinos da lista', 'destino_atual' => 'Passeios do destino atual', 'destino' => 'Passeios de um destino' ),
		) );
		$this->add_control( 'destino', array(
			'label' => 'Destino', 'type' => Controls_Manager::SELECT, 'options' => vtec_opcoes_destinos_select(), 'condition' => array( 'fonte' => 'destino' ),
		) );
		$this->add_control( 'modelo', array(
			'label' => 'Modelo do card', 'type' => Controls_Manager::SELECT, 'default' => '0', 'options' => vtec_opcoes_modelos_select(),
		) );
		$this->add_control( 'quantidade', array( 'label' => 'Quantidade', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => 12 ) );
		$this->add_control( 'ordem', array(
			'label' => 'Ordem', 'type' => Controls_Manager::SELECT, 'default' => 'avaliacao',
			'options' => array( 'avaliacao' => 'Mais bem avaliados', 'preco' => 'Menor preço' ), 'condition' => array( 'fonte!' => 'destinos' ),
		) );
		$this->add_control( 'mostrar_ordem', array(
			'label' => 'Botões de ordenar', 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => array( 'fonte!' => 'destinos' ),
		) );
		$this->add_control( 'mais_texto', array( 'label' => 'Texto do "carregar mais"', 'type' => Controls_Manager::TEXT, 'default' => 'Carregar mais' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'vtec_layout', array( 'label' => 'Layout', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'colunas', array(
			'label' => 'Colunas', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6,
			'default' => 3, 'tablet_default' => 2, 'mobile_default' => 1,
			'selectors' => array( '{{WRAPPER}} .vtec-grade-el' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
		) );
		$this->add_responsive_control( 'espaco', array(
			'label' => 'Espaço entre cards', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'default' => array( 'unit' => 'px', 'size' => 30 ),
			'selectors' => array( '{{WRAPPER}} .vtec-grade-el' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		echo vtec_render_grade( $this->get_settings_for_display(), vtec_contexto_ou_exemplo() ); // phpcs:ignore -- HTML escapado na grade
	}
}
```

- [ ] **Step 5: rodar** — `node tests/run.mjs elementor && node tests/run.mjs` → TUDO OK.
- [ ] **Step 6: commit** — `git add -A && git commit -m "fase 2: campos dinâmicos Viator e widget Viator – Grade"`

---

### Task 5: Modelos (gerador), instalador e painel

**Files:**
- Create: `build_modelos.py`, `viator-tec/templates/modelos/{card_destino,card_passeio,destinos,destino,produto}.json` (gerados), `tests/php/instalador.test.php`
- Modify: `viator-tec/includes/instalador.php`, `viator-tec/includes/admin.php`, `empacotar.py` (inclui `templates/modelos/*.json`)

**Interfaces:**
- Consumes: `vtec_opcoes`, `vtec_opcoes_modelos_select`.
- Produces: `vtec_modelos_chaves(): string[]`, `vtec_ler_modelo(string $chave): ?array` (`titulo, tipo, page_settings, content`), `vtec_preparar_modelo(array $modelo, array $ids): string` (JSON do `_elementor_data` com `{{MODELO_<chave>}}` trocado pelo ID), `vtec_instalar_modelos(bool $forcar = false): array|WP_Error`.

- [ ] **Step 1: `build_modelos.py`**

```python
"""Gera viator-tec/templates/modelos/*.json a partir dos modelos de roteiros exportados do site (_ref/).

    python build_modelos.py

Visual igual ao dos roteiros; os campos do WordPress (post-title, post-custom-field...) viram campos
Viator (vtec-*), e o Loop Grid vira o widget "Viator – Grade".
"""
import copy
import itertools
import json
import urllib.parse
from pathlib import Path

RAIZ = Path(__file__).parent
REF = RAIZ / "_ref"
SAIDA = RAIZ / "viator-tec" / "templates" / "modelos"
_tag_n = itertools.count(1)


def ref(num):
    d = json.loads((REF / f"{num}.json").read_text(encoding="utf-8"))
    return json.loads(d["meta"]["_elementor_data"]), d["meta"]["_elementor_page_settings"]


def tag(nome, settings=None):
    s = urllib.parse.quote(json.dumps(settings or {}, separators=(",", ":"), ensure_ascii=False), safe="")
    return f'[elementor-tag id="vt{next(_tag_n):05x}" name="{nome}" settings="{s}"]'


def cada(els):
    for e in els:
        yield e
        yield from cada(e.get("elements", []))


def trocar_tags(els, mapa):
    """mapa: trecho do tag antigo (ex.: 'post-title' ou 'subtitulo') -> nome do tag novo."""
    for e in cada(els):
        din = e.get("settings", {}).get("__dynamic__", {})
        for k, v in list(din.items()):
            for trecho, novo in mapa.items():
                if trecho in v:
                    din[k] = tag(novo)
                    break


def novos_ids(els, prefixo):
    n = itertools.count(1)
    for e in cada(els):
        e["id"] = f"{prefixo}{next(n):04x}"


def widget(tipo, settings):
    return {"id": "x", "elType": "widget", "widgetType": tipo, "settings": settings, "elements": []}


def texto_dinamico(nome, classe="", extra=None):
    s = {"__dynamic__": {"editor": tag(nome)}}
    if classe:
        s["_css_classes"] = classe
    s.update(extra or {})
    return widget("text-editor", s)


def salvar(chave, titulo, tipo, page_settings, content, prefixo):
    novos_ids(content, prefixo)
    SAIDA.mkdir(parents=True, exist_ok=True)
    dados = {"chave": chave, "titulo": titulo, "tipo": tipo, "page_settings": page_settings, "content": content}
    (SAIDA / f"{chave}.json").write_text(json.dumps(dados, ensure_ascii=False, indent=1), encoding="utf-8")
    print(chave, sum(1 for _ in cada(content)), "elementos")


def card_passeio():
    els, ps = ref(5357)
    els = copy.deepcopy(els)
    trocar_tags(els, {"post-featured-image": "vtec-imagem", "post-url": "vtec-url-passeio", "post-title": "vtec-titulo",
                      "subtitulo": "vtec-duracao", "preco_de_referencia": "vtec-preco"})
    for e in cada(els):
        if e.get("widgetType") == "theme-post-title":
            e["widgetType"] = "heading"
        if e.get("widgetType") == "button":
            e["settings"]["text"] = "Ver passeio"
    corpo = els[0]["elements"][1]["elements"]  # título + subtítulo
    corpo.insert(1, texto_dinamico("vtec-nota", "rt-card-nota", {"text_color": "#555555"}))
    css = ps.get("custom_css", "").replace(".e-loop-item:has(.rt-card)", ".vtec-grade-item:has(.rt-card)")
    return els, {"custom_css": css}


def card_destino():
    els, ps = card_passeio()
    els = copy.deepcopy(els)
    trocar_tags(els, {"vtec-imagem": "vtec-destino-foto", "vtec-url-passeio": "vtec-destino-url", "vtec-titulo": "vtec-destino-nome"})
    corpo = els[0]["elements"][1]["elements"]
    del corpo[1:]  # tira nota e duração
    preco = els[0]["elements"][2]["elements"][0]
    preco["settings"].pop("__dynamic__", None)
    preco["settings"]["editor"] = "<p>Passeios, ingressos e experiências</p>"
    els[0]["elements"][2]["elements"][1]["settings"]["text"] = "Ver passeios"
    return els, ps


def pagina_lista(destino_atual):
    els, ps = ref(5302)
    els = copy.deepcopy(els)
    cab = els[0]["elements"][0]
    if destino_atual:
        els[0]["elements"][0] = widget("text-editor", {"editor": (
            '<div class="blog-head-in"><p class="eyebrow">Passeios</p>'
            '<h1>Passeios em [vtec campo="destino_nome"]</h1>'
            '<p>Ingressos, passeios e experiências selecionados. A reserva e o pagamento são feitos no site da Viator.</p></div>')})
    else:
        cab["settings"]["html"] = (
            '<div class="blog-head-in">\n  <p class="eyebrow">Passeios</p>\n  <h1>Passeios e ingressos pelo mundo</h1>\n'
            '  <p>Escolha o destino e veja os passeios, ingressos e experiências. A reserva e o pagamento são feitos no site da Viator.</p>\n</div>')
    grade = els[1]["elements"][0]
    s = grade["settings"]
    els[1]["elements"][0] = widget("vtec-grade", {
        "fonte": "destino_atual" if destino_atual else "destinos",
        "modelo": "{{MODELO_card_passeio}}" if destino_atual else "{{MODELO_card_destino}}",
        "quantidade": 12 if destino_atual else 48,
        "ordem": "avaliacao",
        "mostrar_ordem": "yes" if destino_atual else "",
        "mais_texto": "Carregar mais passeios" if destino_atual else "Ver mais destinos",
        "colunas": s.get("columns", "3"), "colunas_tablet": s.get("columns_tablet", "2"), "colunas_mobile": s.get("columns_mobile", "1"),
        "espaco": s.get("column_gap", {"unit": "px", "size": 30, "sizes": []}),
    })
    css = ps.get("custom_css", "") + (
        "\n/* Viator Tec: ordenar e carregar mais no visual do site */\n"
        ".vtec-ordem{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 24px;font-size:14px}\n"
        ".vtec-ordem a{white-space:nowrap;padding:6px 14px;border:1px solid #D9D9D9;color:#3C3D3D}\n"
        ".vtec-ordem a[aria-current]{border-color:#E98300;color:#E98300;font-weight:600}\n"
        ".vtec-mais{display:block;margin:40px auto 0;padding:14px 32px;background:#E98300;color:#fff;border:0;"
        "font-weight:600;text-transform:uppercase;letter-spacing:1px;cursor:pointer}\n")
    return els, {"custom_css": css}


def pagina_produto():
    els, ps = ref(5301)
    els = copy.deepcopy(els)
    trocar_tags(els, {"post-featured-image": "vtec-imagem", "post-title": "vtec-titulo", "texto_de_ap": "vtec-descricao",
                      "preco_de_re": "vtec-preco", "galeria": "vtec-galeria"})
    for e in cada(els):
        s = e.get("settings", {})
        din = s.get("__dynamic__", {})
        if e.get("widgetType") == "heading" and "subtitulo" in din.get("title", ""):
            din.pop("title")
            s["title"] = "Sobre este passeio"
        if e.get("widgetType") == "button" and s.get("text") == "Reserve agora":
            s["text"] = "Reservar na Viator"
            s["link"] = {"url": "", "is_external": "on", "nofollow": "on", "custom_attributes": "rel|noopener sponsored"}
            s["__dynamic__"] = {"link": tag("vtec-link-reserva")}
            s["_css_classes"] = "vtec-reservar"
    # nota logo depois da linha laranja; aviso depois do botão
    esquerda = els[1]["elements"][0]["elements"]
    esquerda.insert(3, texto_dinamico("vtec-nota", "vtec-nota-produto"))
    lateral = els[1]["elements"][1]["elements"]
    i = next(k for k, e in enumerate(lateral) if e.get("widgetType") == "button")
    lateral.insert(i + 1, widget("text-editor", {"editor": '<p class="vtec-aviso">A reserva e o pagamento são feitos no site da Viator.</p>',
                                                 "typography_typography": "custom", "typography_font_size": {"unit": "px", "size": 13, "sizes": []}}))
    lateral.append(widget("html", {"html": (
        "<script>document.querySelectorAll('.vtec-reservar a').forEach(function(a){a.target='_blank';a.rel='noopener sponsored';});</script>")}))
    # abas: Inclui, Não inclui, Ponto de encontro, Cancelamento, Informações
    abas = next(e for e in cada(els) if e.get("widgetType") == "nested-tabs")
    campos = [("Inclui", "vtec-inclusoes"), ("Não inclui", "vtec-exclusoes"), ("Ponto de encontro", "vtec-encontro"),
              ("Cancelamento", "vtec-cancelamento"), ("Informações", "vtec-informacoes")]
    abas["settings"]["tabs"] = abas["settings"]["tabs"][:len(campos)]
    abas["elements"] = abas["elements"][:len(campos)]
    for (titulo, nome), item, filho in zip(campos, abas["settings"]["tabs"], abas["elements"]):
        item["tab_title"] = titulo
        editor = next(e for e in cada([filho]) if e.get("widgetType") == "text-editor")
        editor["settings"]["__dynamic__"] = {"editor": tag(nome)}
    css = ps.get("custom_css", "") + "\n.vtec-nota-produto .vtec-nota{font-size:15px;color:#555}\n.vtec-nota b{color:#E98300}\n"
    return els, {"custom_css": css}


def main():
    els, ps = card_passeio()
    salvar("card_passeio", "Passeios – card de passeio (Viator Tec)", "section", ps, els, "vcp")
    els, ps = card_destino()
    salvar("card_destino", "Passeios – card de destino (Viator Tec)", "section", ps, els, "vcd")
    els, ps = pagina_lista(False)
    salvar("destinos", "Passeios – lista de destinos (Viator Tec)", "page", ps, els, "vls")
    els, ps = pagina_lista(True)
    salvar("destino", "Passeios – página do destino (Viator Tec)", "page", ps, els, "vde")
    els, ps = pagina_produto()
    salvar("produto", "Passeios – página do passeio (Viator Tec)", "page", ps, els, "vpp")


if __name__ == "__main__":
    main()
```

- [ ] **Step 2: rodar** — `python build_modelos.py` → 5 linhas `<chave> N elementos`. Se algum índice (`els[1]["elements"][0]`...) não bater com a estrutura do `_ref`, ajustar o índice olhando o JSON (a estrutura foi conferida em 26/09: 5301 = hero, rt-single[esquerda, lateral], galeria, abas, divisor).

- [ ] **Step 3: `tests/php/instalador.test.php`**

```php
<?php
require '/t/bootstrap.php';
define( 'VTEC_DIR', '/p/' );
foreach ( array( 'opcoes', 'instalador' ) as $f ) { require "/p/includes/$f.php"; }

igual( array( 'card_destino', 'card_passeio', 'destinos', 'destino', 'produto' ), vtec_modelos_chaves(), 'cards antes das páginas' );
$ids_vistos = array();
foreach ( vtec_modelos_chaves() as $chave ) {
	$m = vtec_ler_modelo( $chave );
	ok( is_array( $m ) && '' !== $m['titulo'] && in_array( $m['tipo'], array( 'page', 'section' ), true ), "$chave: modelo válido" );
	$json = json_encode( $m['content'] );
	contem( 'vtec-', $json, "$chave: usa campos Viator" );
	foreach ( array( 'post-custom-field', 'post-title', 'post-url', 'post-featured-image', 'loop-grid', 'theme-post-title' ) as $velho ) {
		nao_contem( $velho, $json, "$chave: sem $velho" );
	}
	preg_match_all( '/"id":"([^"]+)"/', $json, $mm );
	igual( count( $mm[1] ), count( array_unique( $mm[1] ) ), "$chave: ids únicos" );
}
contem( 'vtec-grade', json_encode( vtec_ler_modelo( 'destino' )['content'] ), 'destino usa a grade' );
contem( 'vtec-link-reserva', json_encode( vtec_ler_modelo( 'produto' )['content'] ), 'produto tem o link Reservar' );
contem( 'vtec-galeria', json_encode( vtec_ler_modelo( 'produto' )['content'] ), 'produto tem a galeria' );
igual( null, vtec_ler_modelo( 'nao-existe' ), 'modelo inexistente' );

$dados = vtec_preparar_modelo( vtec_ler_modelo( 'destino' ), array( 'card_passeio' => 123 ) );
contem( '"modelo":"123"', $dados, 'placeholder trocado pelo ID do card' );
nao_contem( '{{MODELO_', $dados, 'nenhum placeholder sobrando' );
ok( is_array( json_decode( $dados, true ) ), 'JSON válido' );
```

- [ ] **Step 4: rodar e ver falhar** — `node tests/run.mjs instalador`.

- [ ] **Step 5: `includes/instalador.php`**

```php
<?php
/** Instala os 5 modelos do Elementor (templates/modelos/*.json). Nunca sobrescreve modelo existente. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_modelos_chaves() {
	return array( 'card_destino', 'card_passeio', 'destinos', 'destino', 'produto' );
}

function vtec_ler_modelo( $chave ) {
	$arquivo = VTEC_DIR . 'templates/modelos/' . basename( $chave ) . '.json';
	if ( ! file_exists( $arquivo ) ) {
		return null;
	}
	$m = json_decode( file_get_contents( $arquivo ), true );
	return is_array( $m ) ? $m : null;
}

function vtec_preparar_modelo( $modelo, $ids ) {
	$dados = wp_json_encode( $modelo['content'] );
	foreach ( vtec_modelos_chaves() as $chave ) {
		$dados = str_replace( '{{MODELO_' . $chave . '}}', (string) ( isset( $ids[ $chave ] ) ? (int) $ids[ $chave ] : 0 ), $dados );
	}
	return $dados;
}

function vtec_instalar_modelos( $forcar = false ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return new WP_Error( 'vtec_sem_elementor', 'O Elementor não está ativo.' );
	}
	$o   = vtec_opcoes();
	$ids = $o['modelos'];
	foreach ( vtec_modelos_chaves() as $chave ) {
		if ( ! $forcar && ! empty( $ids[ $chave ] ) && get_post( $ids[ $chave ] ) ) {
			continue;
		}
		$m = vtec_ler_modelo( $chave );
		if ( ! $m ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $m['titulo'] ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $m['tipo'] );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $id, '_elementor_page_settings', $m['page_settings'] );
		update_post_meta( $id, '_elementor_data', wp_slash( vtec_preparar_modelo( $m, $ids ) ) );
		wp_set_object_terms( $id, $m['tipo'], 'elementor_library_type' );
		$ids[ $chave ] = (int) $id;
	}
	$o['modelos'] = $ids;
	update_option( 'vtec_opcoes', $o, false );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}
	return $ids;
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

// Uma vez só, depois de atualizar para a 0.3.0: instala os modelos que faltam.
add_action( 'admin_init', function () {
	if ( '1' !== get_option( 'vtec_modelos_auto' ) && current_user_can( 'manage_options' ) && did_action( 'elementor/loaded' ) ) {
		update_option( 'vtec_modelos_auto', '1', false );
		vtec_instalar_modelos();
	}
} );
```

- [ ] **Step 6: `includes/admin.php`** — nova seção antes de "Cache" e ação de instalar:

```php
add_action( 'admin_post_vtec_instalar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_instalar' );
	$r = vtec_instalar_modelos( ! empty( $_POST['forcar'] ) );
	do_action( 'litespeed_purge_all' );
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&' . ( is_wp_error( $r ) ? 'erro_modelos=1' : 'modelos=1' ) ) );
	exit;
} );
```

Dentro do formulário principal (antes do `submit_button( 'Salvar' )`), a escolha dos modelos:

```php
	echo '<h2>Modelos do Elementor</h2><p>Qual modelo cada tela usa. "HTML do plugin" é a tela simples da primeira versão.</p><table class="form-table">';
	$modelos = vtec_opcoes_modelos_select();
	$modelos['0'] = '— HTML do plugin —';
	foreach ( array( 'destinos' => 'Lista de destinos (/passeios/)', 'destino' => 'Página do destino', 'produto' => 'Página do passeio' ) as $chave => $rotulo ) {
		echo '<tr><th>' . esc_html( $rotulo ) . '</th><td><select name="modelo_' . esc_attr( $chave ) . '">';
		foreach ( $modelos as $id => $titulo ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( (string) $o['modelos'][ $chave ], (string) $id, false ) . '>' . esc_html( $titulo ) . '</option>';
		}
		echo '</select>' . ( $o['modelos'][ $chave ] ? ' <a href="' . esc_url( admin_url( 'post.php?post=' . (int) $o['modelos'][ $chave ] . '&action=elementor' ) ) . '">Editar no Elementor</a>' : '' ) . '</td></tr>';
	}
	echo '</table><p class="description">Os cards de destino e de passeio são escolhidos no widget "Viator – Grade" de cada modelo.</p>';
```

Depois do formulário principal, antes da seção "Cache":

```php
	if ( isset( $_GET['modelos'] ) ) {
		echo '<div class="notice notice-success"><p>Modelos instalados.</p></div>';
	}
	if ( isset( $_GET['erro_modelos'] ) ) {
		echo '<div class="notice notice-error"><p>Não foi possível instalar: o Elementor está ativo?</p></div>';
	}
	echo '<h2>Instalar modelos</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_instalar">';
	wp_nonce_field( 'vtec_instalar' );
	echo '<p>Instala os modelos que faltam (lista, destino, passeio e os dois cards). Os que já existem não são alterados.</p>'
		. '<p><label><input type="checkbox" name="forcar" value="1"> Reinstalar todos (cria cópias novas; as atuais continuam na biblioteca)</label></p>';
	submit_button( 'Instalar modelos', 'secondary' );
	echo '</form>';
```

- [ ] **Step 7: `empacotar.py`** — `INCLUIR` ganha `"templates/modelos/*.json"`.
- [ ] **Step 8: rodar** — `node tests/run.mjs` → TUDO OK.
- [ ] **Step 9: commit** — `git add -A && git commit -m "fase 2: modelos gerados dos roteiros, instalador e escolha no painel"`

---

### Task 6: Versão, publicação e conferência no site

**Files:**
- Modify: `viator-tec/viator-tec.php`, `viator-tec/readme.txt`, `RedeTuristica/scripts/viator_conferir.py`, `RedeTuristica/docs/modulos/viator.md`

- [ ] **Step 1: versão 0.3.0** — `Version:` e `VTEC_VERSION` = `0.3.0`; `readme.txt`: `0.3.0 (26/09/2026): telas no Elementor no padrão dos roteiros — campos dinâmicos Viator, widget Viator – Grade, 5 modelos instalados; sitemap de passeios.`
- [ ] **Step 2: testes e pacote** — `cd tests && npm test` → TUDO OK / 0 fail; `python empacotar.py` → lista inclui `templates/modelos/*.json`.
- [ ] **Step 3: publicar** — commit, `git push`, `gh release create v0.3.0 dist/viator-tec.zip --repo TravelTecOficial/viator-tec --title "0.3.0" --notes "Telas no Elementor no padrão dos roteiros."`
- [ ] **Step 4: atualizar o site** — no navegador do app (sessão do dono): `wp-admin/update-core.php?force-check=1` → marcar Viator Tec → Atualizar plugins. Abrir Configurações › Viator Tec: conferir que os 5 modelos foram instalados (selects preenchidos) — se não, clicar "Instalar modelos". Salvar (limpa o LiteSpeed).
- [ ] **Step 5: `viator_conferir.py`** — acrescentar, antes do resumo:

```python
s, h = get("/passeios-sitemap.xml")
ok(s == 200 and "<urlset" in h and f"/passeios/{slug}/" in h, "sitemap de passeios")
s, h = get("/sitemap_index.xml")
ok("passeios-sitemap.xml" in h, "sitemap de passeios no índice do Rank Math")
s, h = get(f"/passeios/{slug}/")
titulos = re.findall(r'class="elementor-heading-title[^"]*"><a[^>]*>([^<]+)</a>', h)
ok(len(titulos) >= 2 and len(set(titulos)) == len(titulos), "cards do Elementor com títulos diferentes (sem cache repetindo)")
ok("vtec-grade-item" in h and "rt-card" in h, "destino renderizado pelo modelo do Elementor")
```

e trocar a checagem `"/passeios/ lista os destinos"` para aceitar os dois formatos: `ok(s == 200 and ("vtec-destino" in h or "vtec-grade-item" in h), ...)`.
Rodar `python RedeTuristica/scripts/viator_conferir.py las-vegas` → TUDO OK.

- [ ] **Step 6: conferência visual** — no navegador do app, desktop e 375 px: `/passeios/`, `/passeios/las-vegas/` e uma página de passeio, lado a lado com `/roteiros/` e uma página de roteiro: cabeçalho, cards (foto, título laranja, faixa escura com preço e botão), animação de hover, página do passeio (hero, "Sobre este passeio", linha laranja, caixa de preço formatada, botão, abas, galeria com lightbox), nota carregando. Abrir o modelo "Passeios – card de passeio" no Elementor e ver o passeio de exemplo nos campos.
- [ ] **Step 7: docs** — `viator.md`: etapa "Plano da fase 2 e execução" marcada, versões (0.3.0), onde está (modelos, `build_modelos.py`), próximo passo "dono aprovar". Commit no repo do plugin.
