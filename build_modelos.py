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
    # Foto como <img>: fundo dinâmico sai no CSS do modelo, igual para todos os cards da página.
    foto = els[0]["elements"][0]
    foto["settings"]["__dynamic__"].pop("background_image", None)
    foto["elements"] = [widget("image", {"__dynamic__": {"image": tag("vtec-imagem")}, "image_size": "full", "_css_classes": "rt-card-img"})]
    corpo = els[0]["elements"][1]["elements"]  # título + subtítulo
    corpo.insert(1, texto_dinamico("vtec-nota", "rt-card-nota", {"text_color": "#555555"}))
    css = ps.get("custom_css", "").replace(".e-loop-item:has(.rt-card)", ".vtec-grade-item:has(.rt-card)") + (
        "\n/* foto do card como imagem (Viator Tec) */\n"
        ".rt-card-foto{position:relative;overflow:hidden}\n"
        ".rt-card-foto .rt-card-img,.rt-card-foto .rt-card-img .elementor-widget-container{position:absolute;inset:0;margin:0}\n"
        ".rt-card-foto .rt-card-img img{width:100%;height:100%;object-fit:cover;display:block}\n")
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
