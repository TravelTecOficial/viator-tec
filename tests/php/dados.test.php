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
