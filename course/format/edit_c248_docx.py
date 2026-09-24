#!/usr/bin/env python3
"""Keep only Módulo titles, Unidade subtitles, and ODA exercise tables."""

from __future__ import annotations

import copy
import re
import sys
from pathlib import Path

from docx import Document
from docx.oxml.ns import qn


MODULO_RE = re.compile(r"^Módulo\s+\d+\s*:", re.IGNORECASE)
UNIDADE_RE = re.compile(r"^Unidade\s+\d+\s*:", re.IGNORECASE)
EXERCICIO_RE = re.compile(
    r"Exerc[ií]cio de Avalia(?:ç|c)[aã]o da Aprendizagem\s*ODA de refer[eê]ncia",
    re.IGNORECASE,
)


def paragraph_text(element) -> str:
    texts = []
    for node in element.iter(qn("w:t")):
        if node.text:
            texts.append(node.text)
    return "".join(texts).strip()


def table_text(element) -> str:
    texts = []
    for node in element.iter(qn("w:t")):
        if node.text:
            texts.append(node.text)
    return "".join(texts)


def keep_paragraph(element) -> bool:
    text = paragraph_text(element)
    if not text:
        return False
    return bool(MODULO_RE.match(text) or UNIDADE_RE.match(text))


def keep_table(element) -> bool:
    return bool(EXERCICIO_RE.search(table_text(element)))


def filter_document(source: Path, destination: Path) -> dict:
    doc = Document(str(source))
    body = doc.element.body
    kept = {"modulos": 0, "unidades": 0, "tabelas": 0, "removed": 0}

    for child in list(body):
        tag = child.tag.split("}")[-1] if "}" in child.tag else child.tag
        keep = False

        if tag == "p":
            text = paragraph_text(child)
            if MODULO_RE.match(text):
                kept["modulos"] += 1
                keep = True
            elif UNIDADE_RE.match(text):
                kept["unidades"] += 1
                keep = True
        elif tag == "tbl" and keep_table(child):
            kept["tabelas"] += 1
            keep = True

        if not keep:
            body.remove(child)
            kept["removed"] += 1

    doc.save(str(destination))
    return kept


def main() -> int:
    if len(sys.argv) < 2:
        source = Path(__file__).resolve().parent / "C248_RCI_Refletir_para_nao_Repetir_v5.docx"
    else:
        source = Path(sys.argv[1]).expanduser().resolve()

    if not source.is_file():
        print(f"Arquivo não encontrado: {source}", file=sys.stderr)
        return 1

    destination = source.with_name(source.stem + "_filtrado.docx")
    if len(sys.argv) >= 3:
        destination = Path(sys.argv[2]).expanduser().resolve()

    stats = filter_document(source, destination)
    print(f"Salvo em: {destination}")
    print(
        f"Módulos: {stats['modulos']}, "
        f"Unidades: {stats['unidades']}, "
        f"Tabelas de exercício: {stats['tabelas']}, "
        f"Elementos removidos: {stats['removed']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
