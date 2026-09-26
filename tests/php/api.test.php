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
