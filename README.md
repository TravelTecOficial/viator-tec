# Viator Tec

Plugin WordPress: passeios da Viator (Partner API v2, afiliado) em `/passeios/`.
Módulo e decisões: `Wordpress/RedeTuristica/docs/modulos/viator.md`.

- Testes: `cd tests && npm install && node run.mjs` (PHP 7.4 e 8.2 por php-wasm).
- Conferência no site: `python ../../RedeTuristica/scripts/viator_conferir.py`.
- Versão nova: subir `Version:` e `VTEC_VERSION`, entrada no `readme.txt`, `python empacotar.py`,
  commit, push e `gh release create vX.Y.Z dist/viator-tec.zip`. O anexo tem de se chamar `viator-tec.zip`.
- A chave da API nunca entra no repositório.
