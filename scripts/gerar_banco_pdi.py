#!/usr/bin/env python3
"""Gera o Banco de Respostas PDI reorganizado e o Relatório de Melhorias."""

from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Pt, RGBColor


OUT_DIR = Path("/agent/docs/pdi")
ARTIFACT_DIR = Path("/opt/cursor/artifacts")


def set_run_font(run, size=11, bold=False, color=None):
    run.font.name = "Calibri"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    run.font.size = Pt(size)
    run.bold = bold
    if color is not None:
        run.font.color.rgb = color


def add_heading(doc, text, level=1):
    h = doc.add_heading(text, level=level)
    for run in h.runs:
        run.font.color.rgb = RGBColor(0x1F, 0x4E, 0x79)
    return h


def add_para(doc, text, bold=False, italic=False, size=11):
    p = doc.add_paragraph()
    run = p.add_run(text)
    set_run_font(run, size=size, bold=bold)
    run.italic = italic
    p.paragraph_format.space_after = Pt(6)
    return p


def add_bullet(doc, text):
    p = doc.add_paragraph(text, style="List Bullet")
    for run in p.runs:
        set_run_font(run)
    return p


def add_response(doc, title, body_lines, when_to_use=None):
    add_heading(doc, title, level=2)
    if when_to_use:
        p = doc.add_paragraph()
        r1 = p.add_run("Quando usar: ")
        set_run_font(r1, bold=True, size=10, color=RGBColor(0x5B, 0x5B, 0x5B))
        r2 = p.add_run(when_to_use)
        set_run_font(r2, size=10, color=RGBColor(0x5B, 0x5B, 0x5B))
        r2.italic = True

    for line in body_lines:
        if isinstance(line, tuple):
            kind, text = line
            if kind == "bullet":
                add_bullet(doc, text)
            elif kind == "blank":
                doc.add_paragraph()
            else:
                add_para(doc, text)
        else:
            add_para(doc, line)


# ---------------------------------------------------------------------------
# Conteúdo reorganizado
# ---------------------------------------------------------------------------

CATEGORIES = [
    {
        "name": "1. Inscrição e Elegibilidade",
        "desc": "Critérios de participação, inscrição na EV.G, vínculo SIAPE e comprovantes.",
        "items": [
            {
                "title": "Critérios de inscrição e orientações de acesso",
                "when": "Participante não consegue se inscrever ou pergunta se tem direito ao PDI.",
                "body": [
                    "Olá,",
                    "Para acessar o PDI, o servidor deve:",
                    ("bullet", "Possuir cadastro ativo na plataforma da Escola Virtual de Governo (EV.G), com CPF válido;"),
                    ("bullet", "Possuir matrícula SIAPE ativa (na maioria dos casos);"),
                    ("bullet", "Estar em estágio probatório, com data de entrada em exercício a partir de 7 de fevereiro de 2025;"),
                    ("bullet", "Ser servidor público federal recém-ingresso em cargo efetivo de nível superior (para o PDI-NS) ou de nível intermediário (para o PDI-NI) no Poder Executivo Federal."),
                    "Acesse a página do PDI e se inscreva:",
                    ("bullet", "Nível superior: https://www.escolavirtual.gov.br/programa/315"),
                    ("bullet", "Nível intermediário: https://www.escolavirtual.gov.br/programa/276"),
                    "Caso atenda aos critérios mencionados, mas ainda esteja enfrentando dificuldades de acesso, pedimos que abra um novo chamado por meio do link: https://minha.enap.gov.br/",
                    "No chamado, informe os seguintes dados:",
                    ("bullet", "Nome completo"),
                    ("bullet", "Matrícula SIAPE"),
                    ("bullet", "CPF"),
                    ("bullet", "E-mail"),
                    ("bullet", "Cargo"),
                    ("bullet", "Nível (superior ou intermediário)"),
                    ("bullet", "Órgão"),
                    ("bullet", "Data de exercício"),
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Comprovante de inscrição na Área do Aluno",
                "when": "Participante pergunta se está inscrito ou solicita comprovante de inscrição/matrícula.",
                "body": [
                    "Olá,",
                    "Se você já realizou sua inscrição, poderá obter o comprovante seguindo os passos:",
                    ("bullet", "Acesse https://www.escolavirtual.gov.br."),
                    ("bullet", "No canto superior direito, clique em “Entrar” para fazer login na Secretaria Virtual da EV.G."),
                    ("bullet", "Em seguida, clique sobre o seu nome."),
                    ("bullet", "Selecione a opção “Área do Aluno”."),
                    ("bullet", "Na seção “Em andamento”, clique em “Programas em andamento” → “Programas exclusivos”."),
                    ("bullet", "Clique em “Comprovante de Inscrição” para imprimir o comprovante, ou em “Acessar o Programa” para abrir o ambiente virtual do curso."),
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Vinculação automática da inscrição ao SIAPE",
                "when": "Participante pergunta se a inscrição foi vinculada ao SIAPE.",
                "body": [
                    "Olá,",
                    "Nosso sistema possui integração automática com o SIAPE.",
                    "Ao realizar o login na plataforma e tentar efetuar a inscrição no PDI, o sistema valida automaticamente se o CPF informado possui vínculo SIAPE elegível para participação no programa, não sendo necessária qualquer ação adicional por parte do usuário para essa vinculação.",
                    "Se você já realizou sua inscrição, poderá acessar o PDI seguindo os passos:",
                    ("bullet", "Acesse https://www.escolavirtual.gov.br."),
                    ("bullet", "No canto superior direito, clique em “Entrar” para fazer login na Secretaria Virtual da EV.G."),
                    ("bullet", "Em seguida, clique sobre o seu nome."),
                    ("bullet", "Selecione a opção “Área do Aluno”."),
                    ("bullet", "Na seção “Em andamento”, clique em “Programas em andamento” → “Programas exclusivos”."),
                    ("bullet", "Clique em “Comprovante de Inscrição” ou em “Acessar o Programa”."),
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Inscrição restrita a servidores em estágio probatório",
                "when": "Pessoa deseja se inscrever, mas não está em estágio probatório ou ingressou antes do decreto.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "Informamos que o Programa de Desenvolvimento Inicial (PDI) é destinado exclusivamente aos servidores públicos federais recém-ingressos em cargo efetivo no Poder Executivo Federal, durante o estágio probatório.",
                    "Conforme o Decreto nº 12.374, de 6 de fevereiro de 2025, a participação no Programa de Desenvolvimento Inicial passou a ser obrigatória como parte integrante do estágio probatório. Assim, apenas os servidores que ingressaram em exercício a partir da vigência desse decreto estão habilitados a participar do PDI.",
                    "Dessa forma, os servidores que entraram em exercício antes da publicação do referido decreto não estão aptos a realizar o Programa.",
                    "Para mais informações, consulte nossa Central de Ajuda: Perguntas Frequentes da EV.G.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Encaminhamento interno de inscrição (PDI-DEX / erro 1969)",
                "when": "Uso interno: problemas de inscrição que exigem encaminhamento à equipe de gestão ou erro 1969.",
                "body": [
                    "Orientação interna:",
                    ("bullet", "Casos gerais de inscrição/gestão de vínculo: encaminhar para Suzana (PDI-DEX)."),
                    ("bullet", "Erro 1969: encaminhar para Neila."),
                ],
            },
        ],
    },
    {
        "name": "2. Gestão de Vínculo e Cancelamento (PDI-DEX)",
        "desc": "Solicitações de RH ou servidor sobre mudança de vínculo ou cancelamento — encaminhamento interno.",
        "items": [
            {
                "title": "Encaminhamento de mudança de vínculo",
                "when": "Servidor ou RH solicita alteração de vínculo do PDI.",
                "body": [
                    "Orientação interna:",
                    "Enviar chamado para Suzana Gazhale – PDI-DEX, pois já existe um módulo para gestão do PDI.",
                ],
            },
            {
                "title": "Encaminhamento de cancelamento de inscrição",
                "when": "Servidor ou RH solicita cancelamento de inscrição no PDI.",
                "body": [
                    "Orientação interna:",
                    "Enviar chamado para Suzana Gazhale – PDI-DEX, pois já existe um módulo para gestão do PDI.",
                ],
            },
        ],
    },
    {
        "name": "3. Acesso à Plataforma e Suporte Técnico",
        "desc": "Erros de acesso, indisponibilidade, inconsistências técnicas e orientações de uso do ambiente.",
        "items": [
            {
                "title": "Situação técnica já normalizada",
                "when": "Erro já corrigido; orientar nova tentativa.",
                "body": [
                    "Prezado(a),",
                    "A situação relatada já foi normalizada.",
                    "Pedimos, por gentileza, que tente novamente realizar a ação desejada.",
                    "Caso o problema persista, solicitamos que nos informe por meio da abertura de um novo chamado, para que possamos verificar a situação junto à equipe técnica.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI/ENAP",
                ],
            },
            {
                "title": "Equipe ciente do erro e atuando na correção",
                "when": "Incidente conhecido; equipe já trabalhando na correção.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato.",
                    "Informamos que já estamos cientes do erro mencionado e nossa equipe já está atuando para aprimorar o sistema e evitar novas ocorrências. Pedimos, por gentileza, que aguarde e tente acessar novamente após algum tempo.",
                    "Caso o problema persista, por favor entre em contato novamente para que possamos verificar com mais detalhes.",
                    "Permanecemos à disposição para eventuais esclarecimentos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Ocorrência encaminhada à equipe técnica",
                "when": "Problema em análise técnica; pedir que aguarde e tente novamente.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato.",
                    "Informamos que a situação relatada já foi encaminhada à equipe técnica, que está verificando a ocorrência no sistema. Solicitamos, por gentileza, que aguarde e tente realizar o acesso em breve, pois atualizações podem levar algum tempo para serem efetivadas.",
                    "Permanecemos à disposição para eventuais esclarecimentos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Eixo liberado e disciplinas disponíveis",
                "when": "Participante relatava eixo bloqueado; conteúdo já liberado.",
                "body": [
                    "Prezado(a),",
                    "Informamos que o problema técnico relatado já foi solucionado. O eixo foi liberado e as disciplinas já estão disponíveis para acesso na plataforma, sendo possível realizar as atividades normalmente.",
                    "Caso encontre qualquer outra dificuldade, permanecemos à disposição para auxiliá-lo(a).",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Inconsistências técnicas solucionadas sem prejuízo à certificação",
                "when": "Problemas no Moodle/plataforma já corrigidos; reforçar que não há prejuízo acadêmico.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Informamos que as inconsistências anteriormente relatadas foram analisadas por nossa equipe técnica e já foram devidamente solucionadas.",
                    "Reforçamos que as inconsistências identificadas não causarão qualquer prejuízo à sua certificação, ao histórico de participação, ao desempenho avaliativo ou ao cumprimento dos requisitos do programa. Todas as atividades concluídas permanecem registradas e serão consideradas normalmente para fins de validação e emissão do certificado.",
                    "Quanto à conclusão do Programa de Desenvolvimento Inicial – Nível Superior (PDI-NS), informamos que ainda serão disponibilizados conteúdos obrigatórios, como os ciclos de palestras e as atividades da Sala de Conferências, que integram a carga horária do programa e estão em fase de implementação no ambiente virtual de aprendizagem (Moodle). Após a conclusão de todas as etapas obrigatórias, o certificado será disponibilizado automaticamente na plataforma, sem necessidade de qualquer solicitação adicional.",
                    "Agradecemos pela compreensão e por ter nos comunicado as dificuldades encontradas. O seu relato foi importante para a identificação e correção das ocorrências, contribuindo para o aprimoramento contínuo da plataforma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Inconsistências solucionadas — confirmação simples",
                "when": "Versão curta: inconsistências corrigidas; pedir novo contato se persistir.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Informamos que as inconsistências anteriormente relatadas foram analisadas por nossa equipe técnica e já foram devidamente solucionadas.",
                    "Caso o problema persista, por favor, entre em contato conosco novamente.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Inconsistências solucionadas com orientação sobre Plano de Estudos",
                "when": "Problema técnico resolvido e participante também questiona alteração de plano.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Informamos que as inconsistências anteriormente relatadas foram analisadas por nossa equipe técnica e já foram devidamente solucionadas.",
                    "Quanto ao seu Plano de Estudos, a plataforma solicita que o aluno faça a escolha do plano assim que acessa o ambiente e permite, ainda, uma alteração posterior.",
                    "Caso ainda deseje realizar uma nova alteração, por favor, encaminhe um novo chamado, informando o plano para o qual deseja migrar.",
                    "Agradecemos pela compreensão e por ter nos comunicado as dificuldades encontradas. O seu relato foi importante para a identificação e correção das ocorrências, contribuindo para o aprimoramento contínuo da plataforma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Correções em andamento",
                "when": "Problema conhecido; correção ainda em curso.",
                "body": [
                    "Prezado(a),",
                    "Estamos providenciando as correções necessárias.",
                    "Agradecemos pela compreensão e por ter nos comunicado as dificuldades encontradas. O seu relato foi importante para a identificação e correção das ocorrências, contribuindo para o aprimoramento contínuo da plataforma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Solicitação de detalhes sobre falha de acesso",
                "when": "Não há indício de problema no cadastro; pedir mais informações e print.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato.",
                    "Verificamos seus dados e, até o momento, não identificamos nenhum problema em seu acesso. Para que possamos entender melhor o que está acontecendo, poderia nos fornecer mais informações sobre o problema?",
                    ("bullet", "Em qual momento o problema ocorre?"),
                    ("bullet", "É exibida alguma mensagem de erro?"),
                    ("bullet", "O erro acontece ao informar a senha ou em outra etapa do acesso?"),
                    "Se possível, pedimos também que anexe um print (captura de tela) do erro apresentado. Essas informações nos ajudarão a identificar a causa e orientar você da melhor forma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Progresso confirmado e pedido de detalhes sobre acesso",
                "when": "Participante concluiu eixos (resta Sala de Conferências), mas relata dificuldade de acesso sem evidência no cadastro.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Verificamos que você concluiu todos os Eixos, restando apenas a conclusão dos vídeos disponíveis na Sala de Conferências.",
                    "Sobre o seu cadastro, até o momento, não identificamos nenhum problema com o seu acesso.",
                    "Você poderia nos informar com mais detalhes qual dificuldade está encontrando? O problema está relacionado à senha ou aparece alguma mensagem de erro ao tentar acessar?",
                    "Se possível, envie também um print (captura de tela) do erro. Assim, conseguimos analisar melhor a situação e orientá-lo(a) da forma mais adequada.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Recomendação de uso em computador ou notebook",
                "when": "Participante relata dificuldade de visualização ou navegação em celular.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo contato.",
                    "O ambiente virtual de aprendizagem foi desenvolvido para proporcionar uma melhor experiência de estudos em dispositivos com telas maiores, como computadores e notebooks. Em telas pequenas, como as de celulares, alguns conteúdos e recursos podem apresentar limitações de visualização e navegação.",
                    "Por isso, recomendamos, sempre que possível, que você utilize um computador ou notebook e reserve um momento exclusivo para os estudos. Dessa forma, será possível aproveitar melhor os conteúdos e os recursos disponíveis no curso.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Alteração de avatar no perfil",
                "when": "Participante pergunta como trocar a foto/avatar.",
                "body": [
                    "Prezado(a),",
                    "Para alterar seu avatar, clique na sua foto, no canto superior direito da tela, e selecione “Configurações” no menu que será exibido.",
                    "Na página de Configurações, clique em “Escolha outro avatar” e selecione a imagem desejada. Em seguida, clique em “Salvar alterações”, localizado na parte superior da página.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
    {
        "name": "4. Certificação, Progresso e Comprovação",
        "desc": "Emissão de certificado, percentual no Moodle/Integra, declaração parcial e comprovação ao órgão.",
        "items": [
            {
                "title": "Certificado aguardando ciclo de palestras e sincronização Integra",
                "when": "Participante concluiu os eixos, vê 100% no Integra e não consegue emitir certificado.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Caso você tenha concluído todos os Eixos do Programa de Desenvolvimento Inicial – Nível Superior e a consulta de progresso na plataforma Integra (https://integra.enap.gov.br/consulta/pdi_superior_cpf) esteja exibindo 100% de conclusão, informamos que, neste momento, ainda não é possível emitir o certificado.",
                    "O percentual de 100% exibido no Integra é temporário, pois o conteúdo referente ao ciclo de palestras, que compõe a carga horária obrigatória do programa, ainda não foi adicionado ao cálculo de progresso dessa plataforma. Após a sincronização do Integra com o ambiente virtual do curso (Moodle), o progresso passará a refletir corretamente o andamento do programa, sendo exibido em torno de 93% para os alunos que já concluíram até o Eixo 6. Esse percentual permanecerá até que o ciclo de palestras seja disponibilizado e posteriormente concluído.",
                    "Esclarecemos que o progresso apresentado no ambiente virtual do curso (Moodle) está correto. A sincronização dessas informações com o Integra será realizada em breve, uma vez que esse processo ainda não foi concluído.",
                    "A etapa de disponibilização das palestras encontra-se em fase de implementação pela equipe responsável. Assim que todas as palestras forem disponibilizadas e concluídas, será possível finalizar o programa normalmente.",
                    "O certificado será disponibilizado automaticamente na plataforma após a conclusão de todos os requisitos, não sendo necessário realizar qualquer solicitação em outro sistema.",
                    "Enquanto essa etapa não é concluída, orientamos que seja apresentada à sua instituição uma captura de tela (print) do ambiente virtual do curso (Moodle), demonstrando o progresso e as atividades já concluídas, como forma de comprovação temporária.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Acompanhamento de progresso pelo órgão via Integra",
                "when": "Participante solicita declaração de cumprimento percentual do PDI.",
                "body": [
                    "Olá,",
                    "Agradecemos o contato.",
                    "Conforme definição do Ministério da Gestão e da Inovação em Serviços Públicos (MGI), em conjunto com a Escola Nacional de Administração Pública (ENAP), a consulta e o acompanhamento do progresso no Programa de Desenvolvimento Inicial (PDI) do servidor devem ser realizados pelo próprio órgão, por meio da ferramenta disponível no endereço: https://integra.enap.gov.br/consulta.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Certificado condicionado à liberação das palestras",
                "when": "Dúvida geral sobre emissão de certificado enquanto palestras não estão disponíveis.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Contudo, para a conclusão do Programa de Desenvolvimento Inicial – Nível Superior (PDI-NS) e a consequente emissão do certificado, ainda é necessário aguardar a liberação do ciclo de palestras, que faz parte da carga horária do curso. Essa etapa encontra-se em fase de implementação pela equipe responsável. Assim que todas as palestras forem disponibilizadas, será possível finalizar o programa normalmente. O certificado será disponibilizado automaticamente na plataforma, não sendo necessário solicitar por outro sistema.",
                    "Para fins de comprovação parcial, orientamos que seja utilizada captura de tela (print) da plataforma, demonstrando o andamento e as atividades já concluídas, caso essa forma de comprovação seja aceita pela sua instituição.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Inexistência de certificado ou declaração parcial",
                "when": "Solicitação de declaração/certificado parcial de horas ou conclusão parcial.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato e compreendemos a situação apresentada.",
                    "Informamos que não é emitida declaração ou certificado de conclusão parcial do PDI-NS. A certificação oficial somente é gerada após a conclusão de todas as etapas que compõem a carga horária do programa, incluindo o ciclo de palestras, que ainda se encontra em fase de implementação.",
                    "Para fins de comprovação parcial, orientamos que seja utilizada captura de tela (print) da plataforma, demonstrando o andamento e as atividades já concluídas, caso essa forma de comprovação seja aceita pela sua instituição.",
                    "Agradecemos pela compreensão e permanecemos à disposição para esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Conclusão do programa após conteúdos complementares",
                "when": "Participante pergunta se o curso acabou ou quando poderá concluir.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato.",
                    "Informamos que ainda serão disponibilizados conteúdos complementares, como os ciclos de palestras e atividades na Sala de Conferências, que integram a carga horária do PDI-NS e estão em fase de implementação.",
                    "Quanto ao certificado, após a conclusão de todas as etapas obrigatórias do programa, ele será disponibilizado automaticamente na plataforma, não sendo necessário solicitar por outro sistema.",
                    "Permanecemos à disposição.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Diferença de percentual entre Moodle e Integra",
                "when": "Participante questiona divergência de progresso entre Moodle e Integra.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "No Ambiente Virtual de Aprendizagem (Moodle), o progresso é atualizado de forma gradual, à medida que cada atividade é concluída. Já no Integra, o percentual é atualizado por Eixo Temático concluído, ou seja, o sistema registra o avanço apenas quando todas as atividades obrigatórias de um eixo são finalizadas.",
                    "Após a inclusão dos vídeos na Sala de Conferências (palestras e minicursos), houve um recálculo dos percentuais, o que também pode ter ocasionado alterações no progresso apresentado.",
                    "A Enap está trabalhando para integrar as informações dos dois ambientes em tempo real.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Novos conteúdos e integração Moodle–Integra",
                "when": "Dúvidas sobre conclusão do curso e liberação do certificado após novos conteúdos.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "Em relação à conclusão do curso, informamos:",
                    ("bullet", "A Enap ainda vai disponibilizar novos conteúdos (palestras e minicursos). Somente após a realização desses conteúdos o curso poderá ser concluído."),
                    ("bullet", "Após o processamento dos dados de quem concluiu o curso, o link para emissão do certificado deverá ser disponibilizado."),
                    ("bullet", "A Enap está trabalhando para integrar as informações dos dois ambientes (Moodle e Integra) em tempo real."),
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Certificação, palestras e comprovação parcial via Integra",
                "when": "Combinação: certificado pendente de palestras + orientação de comprovação/progresso ao órgão.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Contudo, para a conclusão do Programa de Desenvolvimento Inicial – Nível Superior (PDI-NS) e a consequente emissão do certificado, ainda é necessário aguardar a liberação do ciclo de palestras, que faz parte da carga horária do curso. Essa etapa encontra-se em fase de implementação pela equipe responsável. Assim que todas as palestras forem disponibilizadas, será possível finalizar o programa normalmente. O certificado será disponibilizado automaticamente na plataforma, não sendo necessário solicitar por outro sistema.",
                    "Conforme definição do Ministério da Gestão e da Inovação em Serviços Públicos (MGI), em conjunto com a Escola Nacional de Administração Pública (ENAP), a consulta e o acompanhamento do progresso no Programa de Desenvolvimento Inicial (PDI) do servidor devem ser realizados pelo próprio órgão, por meio da ferramenta disponível no endereço: https://integra.enap.gov.br/consulta. Para fins de comprovação parcial, orientamos que seja utilizada captura de tela (print) da plataforma, demonstrando o andamento e as atividades já concluídas.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Percentual no Integra já atualizado",
                "when": "Problema de atualização de progresso no Integra já normalizado.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "A situação já foi normalizada. Solicitamos, por gentileza, que verifique novamente o seu percentual de conclusão do PDI na ferramenta Integra.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Orientação para emissão do certificado no Moodle",
                "when": "Participante já cumpriu requisitos e precisa saber onde emitir o certificado.",
                "body": [
                    "Prezado(a),",
                    "Você poderá emitir o seu certificado desde que tenha atingido a nota mínima exigida nos eixos e cumprido a carga horária mínima, conforme a duração do curso.",
                    "Acesse a página inicial disponível no ambiente Moodle do curso; ao lado estará o botão de Certificado.",
                    "Agradecemos pela compreensão e permanecemos à disposição para eventuais dúvidas.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Instabilidade na emissão do certificado já normalizada",
                "when": "Falha pontual no sistema de certificado já corrigida.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "Informamos que o sistema responsável pela emissão do certificado apresentou instabilidade na data relatada. A situação já foi normalizada.",
                    "Solicitamos, por gentileza, que tente realizar o procedimento novamente. Caso o problema persista, entre em contato conosco novamente para que possamos auxiliá-lo(a).",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Confirmação de conclusão do curso",
                "when": "Verificação indica que o participante já concluiu o programa.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Verificamos que você conseguiu acessar a plataforma e, inclusive, já concluiu o curso. Parabéns pela conclusão!",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Eixos concluídos — pendência na Sala de Conferências",
                "when": "Participante concluiu os eixos e falta apenas concluir os vídeos da Sala de Conferências.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Verificamos que você conseguiu acessar a plataforma e que já concluiu todos os Eixos, restando apenas a conclusão dos vídeos disponíveis na Sala de Conferências.",
                    "Após a conclusão de todas as etapas obrigatórias, o certificado será disponibilizado automaticamente na plataforma.",
                    "Agradecemos pela compreensão e por nos comunicar as dificuldades encontradas. Seu relato foi importante para a identificação e correção das ocorrências, contribuindo para o aprimoramento contínuo da plataforma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Nota mínima não atingida — recuperação disponível",
                "when": "Eixos concluídos, mas disciplinas sem nota mínima; orientar recuperação.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Verificamos que você concluiu todos os Eixos. Contudo, conforme estabelecido no Projeto Pedagógico, para a certificação, é necessário cumprir os seguintes requisitos mínimos:",
                    ("bullet", "realizar a navegação nos materiais do programa, cumprindo todas as ações solicitadas em cada disciplina;"),
                    ("bullet", "avaliação satisfatória no desempenho de todas as disciplinas, com obtenção de nota mínima de 60% em relação à nota total de cada disciplina;"),
                    ("bullet", "avaliação satisfatória no desempenho geral no programa, com obtenção de nota mínima de 60% em relação à nota global do programa."),
                    "Nesse sentido, nos Eixos 5 e 6, as disciplinas 3 não atingiram a nota mínima. Em ambos os casos, está disponível o questionário de recuperação.",
                    "Caso não consiga atingir a nota mínima na recuperação, entre em contato novamente para que possamos reabrir os questionários e possibilitar uma nova tentativa.",
                    "Após a conclusão de todas as etapas obrigatórias, o certificado será disponibilizado automaticamente na plataforma.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais e desejamos uma excelente continuidade em sua jornada de estudos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Certificado disponível após cumprimento da carga horária mínima",
                "when": "Participante ainda não atingiu o prazo/carga horária mínima para emissão.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato e o detalhamento das informações apresentadas.",
                    "Informamos que será necessário aguardar o prazo para o cumprimento da carga horária mínima exigida pelo curso. A partir da próxima semana, seu certificado estará disponível para emissão.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
    {
        "name": "5. Palestras e Sala de Conferências",
        "desc": "Disponibilização, acompanhamento e confirmação de visualização das palestras.",
        "items": [
            {
                "title": "Acompanhamento de visualização das palestras em implementação",
                "when": "Dúvida sobre check/confirmação de visualização das palestras (meio termo).",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Informamos que a funcionalidade de acompanhamento e confirmação de visualização das palestras da Sala de Conferências está atualmente em processo de implementação pela equipe responsável. Em breve, esse recurso estará disponível aos participantes do PDI-NS.",
                    "Agradecemos pela compreensão e permanecemos à disposição para quaisquer esclarecimentos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Ciclo de palestras em fase de implementação",
                "when": "Participante pergunta quando as palestras serão liberadas.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Informamos que essa etapa encontra-se em fase de implementação pela equipe responsável.",
                    "Queremos tranquilizá-lo(a): não haverá qualquer prejuízo ao seu progresso nem à certificação. Assim que as palestras forem disponibilizadas, será possível finalizar o programa normalmente. O certificado será disponibilizado automaticamente na plataforma, não sendo necessário solicitar por outro sistema.",
                    "Para fins de comprovação parcial, orientamos que seja utilizada captura de tela (print) da plataforma, demonstrando o andamento e as atividades já concluídas, caso essa forma de comprovação seja aceita pela sua instituição.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
    {
        "name": "6. Avaliações, Notas e Tentativas",
        "desc": "Nota mínima, recuperação, reabertura de questionários e erros em questões.",
        "items": [
            {
                "title": "Nota mínima por disciplina e avaliação de recuperação",
                "when": "Participante não atingiu a nota e pergunta sobre aprovação/recuperação.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e pela dedicação ao Programa de Desenvolvimento Inicial (PDI).",
                    "Esclarecemos que a nota mínima para aprovação é 6,0 (60%) em cada disciplina do curso, e não apenas no eixo como um todo. Caso não tenha atingido 6,0, realize a avaliação de recuperação, que oferece uma nova oportunidade de alcançar a nota mínima necessária.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Análise de possível erro em questão avaliativa",
                "when": "Participante aponta erro em questão do questionário.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato e o interesse em esclarecer as dúvidas sobre o Programa de Desenvolvimento Inicial (PDI) – Nível Superior.",
                    "A equipe responsável já está analisando a situação. Assim que a análise for concluída, eventuais ajustes serão realizados diretamente na plataforma. Agradecemos pela compreensão e pelo cuidado em acompanhar o andamento do curso.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Possibilidade de refazer disciplina após recuperação",
                "when": "Participante não atingiu nota mínima mesmo após recuperação.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato e o interesse em esclarecer as dúvidas sobre o Programa de Desenvolvimento Inicial (PDI) – Nível Superior.",
                    "A equipe responsável já está ciente desse fato e analisando a situação. Eventuais ajustes serão realizados diretamente na plataforma. Esclarecemos que, caso o cursista não alcance a nota mínima de 60% em determinada disciplina, mesmo após realizar a avaliação de recuperação, haverá a possibilidade de refazer apenas a disciplina em questão, sem a necessidade de refazer todo o curso.",
                    "Agradecemos pela compreensão e pelo cuidado em acompanhar o andamento do curso.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Refazer apenas a disciplina sem retomar o curso inteiro",
                "when": "Versão objetiva sobre refazer disciplina após não atingir 60%.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "Esclarecemos que, caso o cursista não alcance a nota mínima de 60% em determinada disciplina, mesmo após realizar a avaliação de recuperação, haverá a possibilidade de refazer apenas a disciplina em questão, sem a necessidade de refazer todo o curso do Programa de Desenvolvimento Inicial – Nível Superior.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Reabertura de tentativa no questionário",
                "when": "Após análise, questionário reconfigurado para nova tentativa.",
                "body": [
                    "Prezado(a),",
                    "Informamos que sua solicitação foi analisada e atendida.",
                    "O questionário da disciplina foi reconfigurado para que você possa realizar uma nova tentativa, visando à obtenção da nota mínima exigida, em conformidade com as normas vigentes e observados os prazos estabelecidos para conclusão do PDI.",
                    "Agradecemos o contato e desejamos sucesso na continuidade de sua trajetória de desenvolvimento.",
                    "Permanecemos à disposição para quaisquer esclarecimentos adicionais.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
    {
        "name": "7. Plano de Estudos e Materiais Didáticos",
        "desc": "Alteração do plano de estudos e localização de materiais de apoio.",
        "items": [
            {
                "title": "Download dos e-books por disciplina",
                "when": "Participante pergunta onde encontrar material de apoio/PDF.",
                "body": [
                    "Prezado(a),",
                    "Esperamos que esta mensagem o(a) encontre bem.",
                    "Em relação à sua dúvida, informamos que a plataforma disponibiliza um material ao final de cada disciplina: dentro de cada eixo, há um e-book disponível para download em formato PDF, que funciona como material de apoio e consolidação dos conteúdos abordados.",
                    "Recomendamos que você acesse cada disciplina concluída dentro do respectivo eixo para localizar e baixar os e-books correspondentes.",
                    "Caso tenha qualquer dificuldade em encontrar os materiais, ficamos à disposição para auxiliá-lo(a).",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Alteração do Plano de Estudos de 12 para 24 meses",
                "when": "Participante deseja mudar o plano de estudos.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o contato.",
                    "Você pode alterar seu Plano de Estudos de 12 para 24 meses. Para isso, acesse o ambiente, clique em “Meu Perfil” e, em seguida, no botão “Preferências”.",
                    "Nessa página, é possível alterar o plano de estudos uma única vez. Após selecionar o novo plano, clique em “Salvar alterações” para confirmar a mudança.",
                    "Permanecemos à disposição.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
    {
        "name": "8. Sobre o Programa e Dúvidas Gerais",
        "desc": "Institucional, escopo do canal, carteira de estudante, gamificação, licenças e demais orientações.",
        "items": [
            {
                "title": "Escopo do canal de atendimento do PDI",
                "when": "Contato fora do escopo ou dúvida não especificada.",
                "body": [
                    "Prezado(a),",
                    "Informamos que este canal de contato é destinado exclusivamente a assuntos relacionados ao Programa de Desenvolvimento Inicial (PDI).",
                    "Poderia, por gentileza, esclarecer qual é a sua dúvida ou solicitação específica em relação ao PDI? Dessa forma, poderemos auxiliá-lo(a) de maneira mais adequada.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Chamado já tratado anteriormente",
                "when": "Mensagem/chamado repetido já resolvido.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato e parabenizamos pelo avanço no curso.",
                    "Informamos que as mensagens já foram resolvidas.",
                    "Agradecemos pela compreensão.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "O que é o PDI e quem o oferece",
                "when": "Pergunta institucional sobre o programa, responsáveis e obrigatoriedade.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos pelo seu contato.",
                    "O PDI é uma iniciativa da Escola Nacional de Administração Pública (Enap) em parceria com o Ministério da Gestão e Inovação em Serviços Públicos (MGI), para a formação e acolhimento dos novos servidores públicos federais. Seu objetivo é promover conhecimentos de gestão e administração pública, desenvolver competências e construir uma cultura de serviço público orientada por valores republicanos, éticos e de compromisso com a qualidade.",
                    "O PDI é destinado a servidores públicos federais recém-ingressos, em estágio probatório. Existem duas versões do programa: uma para cargos de Nível Superior (PDI-NS) e outra para cargos de Nível Intermediário (PDI-NI).",
                    "O Decreto nº 12.374, de 6 de fevereiro de 2025, tornou obrigatória a participação no Programa de Desenvolvimento Inicial como parte integrante do estágio probatório para servidores do Poder Executivo Federal. Os servidores que entraram em exercício antes da publicação deste decreto não estão obrigados a cursar o PDI.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Cursos da EV.G não geram carteira de estudante",
                "when": "Participante pergunta se tem direito à carteira/ID estudantil.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato.",
                    "Informamos que realizar cursos na plataforma da Escola Virtual Gov (EV.G), mantida pela Escola Nacional de Administração Pública (ENAP), não dá direito à emissão de carteira de estudante.",
                    "A carteira de estudante (ID Estudantil) no Brasil é destinada apenas a pessoas que estão regularmente matriculadas em instituições de ensino formal, como por exemplo:",
                    ("bullet", "Ensino fundamental ou médio"),
                    ("bullet", "Curso técnico"),
                    ("bullet", "Graduação"),
                    ("bullet", "Pós-graduação"),
                    "Os cursos da EV.G são classificados como cursos de capacitação e formação complementar, que oferecem certificado de conclusão, mas não caracterizam vínculo estudantil formal.",
                    "Caso tenha outras dúvidas, ficamos à disposição.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Análise de inconsistência em figura ou conteúdo visual",
                "when": "Participante aponta problema em figura/imagem do material.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato.",
                    "A equipe responsável já está analisando a situação. Assim que a análise for concluída, eventuais ajustes serão realizados diretamente na plataforma. Agradecemos pela compreensão e pelo cuidado em acompanhar o andamento do curso.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Gamificação e notificação de chaves sem impacto acadêmico",
                "when": "Participante questiona pontuação, chaves ou notificação de gamificação.",
                "body": [
                    "Prezado(a),",
                    "A plataforma utiliza alguns recursos visuais e mecanismos de incentivo à participação, como pontuações, chaves ou outros elementos simbólicos relacionados às atividades do ambiente de aprendizagem. Essas funcionalidades fazem parte de uma estratégia de gamificação, cujo objetivo é apenas estimular o engajamento dos participantes durante o curso.",
                    "A notificação recebida informando que “chaves foram retiradas da sua pilha” está relacionada a esses mecanismos internos da plataforma. No entanto, é importante destacar que esse tipo de ação não representa uma penalização acadêmica, nem interfere no seu desempenho, progresso ou resultado final no curso.",
                    "Esses elementos funcionam apenas como indicadores de participação dentro da dinâmica da plataforma, sem qualquer impacto na avaliação, certificação ou conclusão das atividades.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
            {
                "title": "Acesso durante licença médica — orientação pelo RH do órgão",
                "when": "Participante em licença médica/maternidade pergunta se pode ou deve cursar o PDI.",
                "body": [
                    "Prezado(a),",
                    "Agradecemos o seu contato.",
                    "Em relação ao início do curso durante a licença médica, o sistema do PDI não possui nenhum bloqueio que impeça o acesso ou a realização das atividades. No entanto, a orientação sobre a realização de atividades durante o período de licença médica ou licença-maternidade deve ser fornecida pela área de Gestão de Pessoas (RH) do seu órgão, pois essa definição depende das normas internas e da situação funcional da servidora.",
                    "Assim, recomendamos que consulte o RH da sua instituição para verificar a orientação aplicável ao seu caso.",
                    "Permanecemos à disposição para quaisquer esclarecimentos.",
                    "Atenciosamente,\nEquipe PDI-NS",
                ],
            },
        ],
    },
]


# ---------------------------------------------------------------------------
# Relatório de melhorias (mapeado às entradas originais)
# ---------------------------------------------------------------------------

IMPROVEMENTS = [
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "1. Não consigo me inscrever no PDI",
        "title": "Critérios de inscrição e orientações de acesso",
        "items": [
            "Separar claramente critérios de elegibilidade (quem pode) de passos de inscrição (como fazer).",
            "Atualizar a data de exercício se a regra mudar; hoje está fixa em 7/fev/2025.",
            "Padronizar assinatura (PDI-NS vs PDI/ENAP) e incluir versão para PDI-NI quando aplicável.",
            "Transformar a lista de dados do chamado em checklist reutilizável.",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "2. Estou inscrito no PDI? Como obter o meu comprovante de inscrição?",
        "title": "Comprovante de inscrição na Área do Aluno",
        "items": [
            "A última frase fica incompleta (“será aberto o ambiente virtual do curso,”).",
            "Unificar com o item 33 (comprovante de matrícula), que é praticamente idêntico.",
            "Incluir o que fazer se a inscrição não aparecer em “Programas exclusivos”.",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "3. Minha inscrição foi vinculada ao meu SIAPE?",
        "title": "Vinculação automática da inscrição ao SIAPE",
        "items": [
            "A resposta mistura vinculação SIAPE com o passo a passo de acesso — melhor separar.",
            "Há duplicata parcial com o item 34; consolidar em uma única resposta canônica.",
            "Esclarecer o que ocorre quando o SIAPE não é elegível (mensagem esperada / próximo passo).",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "4. Conclui todos os Eixos do PDI e não consigo emitir meu certificado",
        "title": "Certificado aguardando ciclo de palestras e sincronização Integra",
        "items": [
            "Texto muito longo e situacional (percentuais 100%/93%, sincronização pendente).",
            "Remover ou atualizar trechos temporários assim que palestras e Integra estiverem estáveis.",
            "Criar versão curta para atendimento rápido e versão completa para casos recorrentes.",
            "Confirmar se o link do Integra (pdi_superior_cpf) também cobre PDI-NI.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "5. Gostaria de uma declaração de cumprimento de percentual do PDI",
        "title": "Acompanhamento de progresso pelo órgão via Integra",
        "items": [
            "Explicar em uma frase por que a Enap não emite declaração individual.",
            "Incluir orientação ao servidor sobre como o RH/órgão consulta o Integra.",
            "Assinatura incompleta (“Atenciosamente.” sem equipe).",
        ],
    },
    {
        "cat": "Gestão de Vínculo e Cancelamento (PDI-DEX)",
        "orig": "6. Servidor ou RH solicita mudança de vínculo do PDI (PDI-DEX)",
        "title": "Encaminhamento de mudança de vínculo",
        "items": [
            "É só orientação interna; criar também um texto ao usuário (“seu pedido foi encaminhado…”).",
            "Evitar nome pessoal como único critério; preferir área/função + canal formal.",
            "Documentar o que o módulo de gestão do PDI permite e o fluxo esperado.",
        ],
    },
    {
        "cat": "Gestão de Vínculo e Cancelamento (PDI-DEX)",
        "orig": "7. Servidor ou RH solicita cancelamento de inscrição no PDI (PDI-DEX)",
        "title": "Encaminhamento de cancelamento de inscrição",
        "items": [
            "Idêntico ao item 6; diferenciar requisitos (motivos aceitos, documentos, prazos).",
            "Informar se o cancelamento é irreversível e impactos no estágio probatório.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "8. ERRO",
        "title": "Situação técnica já normalizada",
        "items": [
            "Título original genérico demais; manter o novo título descritivo.",
            "Pedir print, horário e URL da tela se o erro persistir.",
            "Padronizar assinatura com as demais respostas.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "9. CERTIFICADO",
        "title": "Certificado condicionado à liberação das palestras",
        "items": [
            "Sobreposição forte com itens 4, 14 e 35 — unificar em 1–2 modelos.",
            "Remover datas/prazos temporários quando desatualizados.",
        ],
    },
    {
        "cat": "Palestras e Sala de Conferências",
        "orig": "10. CHECK PALESTRA/meio termo",
        "title": "Acompanhamento de visualização das palestras em implementação",
        "items": [
            "Esclarecer o que o participante deve fazer enquanto o check não existe.",
            "Atualizar quando a funcionalidade estiver liberada (passos de uso).",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "11. SUPORTE TÉCNICO",
        "title": "Equipe ciente do erro e atuando na correção",
        "items": [
            "Há duas respostas “SUPORTE TÉCNICO” (11 e 19); diferenciar por estágio do incidente.",
            "Incluir prazo estimado ou janela de retry quando possível.",
            "Usar linguagem inclusiva (“Prezado(a)”).",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "12. CANAL PDI",
        "title": "Escopo do canal de atendimento do PDI",
        "items": [
            "Ajustar plural (“Poderiam”) para singular, padrão do restante.",
            "Acrescentar exemplos do que o canal atende e o que deve ir a outros canais.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "13. JÁ É POSSÍVEL ACESSAR",
        "title": "Eixo liberado e disciplinas disponíveis",
        "items": [
            "Tornar parametrizável (qual eixo/disciplina) para evitar texto genérico.",
            "Incluir link direto de acesso quando houver.",
        ],
    },
    {
        "cat": "Palestras e Sala de Conferências",
        "orig": "14. QUANDO SAI AS PALESTRAS",
        "title": "Ciclo de palestras em fase de implementação",
        "items": [
            "Corrigir gramática: “Informamos, contudo, essa etapa…” → “Informamos, contudo, que essa etapa…”.",
            "Corrigir “pelo sua instituição” → “pela sua instituição”.",
            "Evitar duplicar o bloco de comprovação parcial já presente em outras respostas.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "15. REPETIDO",
        "title": "Chamado já tratado anteriormente",
        "items": [
            "Referenciar o número do chamado ou a data da resposta anterior.",
            "Remover “parabenizamos pelo avanço” se o tema não for progresso no curso.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "16. PARCIAL DE HORAS E CURSO",
        "title": "Inexistência de certificado ou declaração parcial",
        "items": [
            "Deixar explícito se print do Moodle é a única alternativa aceita pela Enap/MGI.",
            "Atualizar trecho sobre palestras “em implementação” quando o ciclo estiver completo.",
        ],
    },
    {
        "cat": "Avaliações, Notas e Tentativas",
        "orig": "17. TENTATIVAS NOTAS",
        "title": "Nota mínima por disciplina e avaliação de recuperação",
        "items": [
            "Frase quebrada: “No seu caso, de não atingir 6,0 realizar a avaliação…” — reescrever.",
            "Padronizar “6,0” e “60%” em todas as respostas de nota.",
            "Indicar onde encontrar o questionário de recuperação na plataforma.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "18. FIM DO CURSO",
        "title": "Conclusão do programa após conteúdos complementares",
        "items": [
            "Alinhar com as respostas de palestras/certificado para evitar mensagens conflitantes.",
            "Informar se há prazo máximo de conclusão ligado ao plano 12/24 meses.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "19. SUPORTE TÉCNICO",
        "title": "Ocorrência encaminhada à equipe técnica",
        "items": [
            "Diferenciar claramente do item 11 (ciente + aguardar vs. encaminhado).",
            "Oferecer identificador do incidente quando houver.",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "20. INSCRIÇÃO",
        "title": "Encaminhamento interno de inscrição (PDI-DEX / erro 1969)",
        "items": [
            "Texto interno incompleto; documentar o significado do erro 1969.",
            "Criar resposta ao usuário e playbook interno separados.",
        ],
    },
    {
        "cat": "Avaliações, Notas e Tentativas",
        "orig": "21. ERRO NA QUESTÃO",
        "title": "Análise de possível erro em questão avaliativa",
        "items": [
            "Pedir identificação da disciplina, questão e print.",
            "Informar se a nota será recalculada automaticamente após correção.",
        ],
    },
    {
        "cat": "Avaliações, Notas e Tentativas",
        "orig": "22. NOTA MÍNIMA",
        "title": "Possibilidade de refazer disciplina após recuperação",
        "items": [
            "Corrigir “desde fato” → “desse fato”.",
            "Mistura “estamos analisando” com regra pedagógica; separar os dois blocos.",
            "Unificar com o item 23 (conteúdo quase idêntico).",
        ],
    },
    {
        "cat": "Avaliações, Notas e Tentativas",
        "orig": "23. NOTA 6",
        "title": "Refazer apenas a disciplina sem retomar o curso inteiro",
        "items": [
            "Consolidar com 22 em uma única resposta canônica.",
            "Incluir o fluxo prático: como solicitar a reabertura da disciplina.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "24. QUEM FAZ?",
        "title": "O que é o PDI e quem o oferece",
        "items": [
            "Bom conteúdo institucional; pode virar FAQ público da Central de Ajuda.",
            "Padronizar grafia Enap/ENAP e MGI.",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "24. Quero me inscrever, mas não estou no estágio probatório",
        "title": "Inscrição restrita a servidores em estágio probatório",
        "items": [
            "Corrigir numeração duplicada (dois itens “24”).",
            "Trocar “consulte nossa Central de Ajuda: Perguntas Frequentes da EV.G” por URL direta.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "25. Tenho direito a Carteira de Estudante?",
        "title": "Cursos da EV.G não geram carteira de estudante",
        "items": [
            "Resposta clara e completa; pouco a melhorar.",
            "Opcional: link para legislação/órgão emissor da ID Estudantil.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "26. FIGURA",
        "title": "Análise de inconsistência em figura ou conteúdo visual",
        "items": [
            "Texto idêntico ao de erro em questão; especializar para conteúdo visual.",
            "Pedir eixo, disciplina e print da figura.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "27. GAMIFICAÇÃO",
        "title": "Gamificação e notificação de chaves sem impacto acadêmico",
        "items": [
            "Resposta boa; acrescentar se as chaves/pontos têm algum uso futuro ou são só simbólicos.",
        ],
    },
    {
        "cat": "Plano de Estudos e Materiais Didáticos",
        "orig": "28. MATERIAL",
        "title": "Download dos e-books por disciplina",
        "items": [
            "Corrigir pontuação da frase longa sobre disponibilidade do material.",
            "Trocar “o senhor” por linguagem inclusiva (“você”).",
            "Indicar o nome exato do botão/seção do e-book na interface.",
        ],
    },
    {
        "cat": "Plano de Estudos e Materiais Didáticos",
        "orig": "29. ALTERAÇÃO DE PLANO DE ESTUDOS",
        "title": "Alteração do Plano de Estudos de 12 para 24 meses",
        "items": [
            "Usar “Prezado(a)” em vez de só “Prezada”.",
            "Esclarecer se a mudança 24→12 também é possível e o que ocorre após a alteração única.",
            "Alinhar com a resposta órfã que pede novo chamado para migração adicional.",
        ],
    },
    {
        "cat": "Avaliações, Notas e Tentativas",
        "orig": "30. TENTATIVAS DO QUESTIONÁRIO",
        "title": "Reabertura de tentativa no questionário",
        "items": [
            "Parametrizar disciplina/questionário no texto.",
            "Informar prazo para realizar a nova tentativa.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "31. PERCENTUAL",
        "title": "Diferença de percentual entre Moodle e Integra",
        "items": [
            "Remover previsão “até o final deste mês de julho” (desatualizada).",
            "Manter apenas a regra de cálculo (atividade a atividade vs. por eixo).",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "32. PLANO ANUAL",
        "title": "Novos conteúdos e integração Moodle–Integra",
        "items": [
            "Título original pouco claro (“PLANO ANUAL”); o conteúdo é sobre conclusão/certificado.",
            "Remover menções a “fim de julho”.",
            "Unificar com outras respostas de conclusão.",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "33. COMPROVANTE DE MATRÍCULA",
        "title": "Comprovante de inscrição na Área do Aluno",
        "items": [
            "Duplicata do item 2; manter uma só resposta com aliases de busca.",
            "Completar o passo a passo (login inicial faltava no original).",
        ],
    },
    {
        "cat": "Inscrição e Elegibilidade",
        "orig": "34. Estou inscrito no PDI? Minha inscrição foi vinculada ao meu SIAPE?",
        "title": "Vinculação automática da inscrição ao SIAPE",
        "items": [
            "Versão incompleta do item 3; descartar após consolidação.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "35. Certificado atual com final das palestras e comprovação parcial",
        "title": "Certificação, palestras e comprovação parcial via Integra",
        "items": [
            "Combina três temas; preferir respostas modulares (certificado + Integra + print).",
            "Remover “até o fim de julho”.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "36. Atualização do progresso no integra",
        "title": "Percentual no Integra já atualizado",
        "items": [
            "Incluir o link do Integra na resposta.",
            "Orientar limpar cache/aguardar sincronização se ainda divergir.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "37. Daniel moodle",
        "title": "Inconsistências técnicas solucionadas sem prejuízo à certificação",
        "items": [
            "Remover nome pessoal do título (“Daniel moodle”).",
            "Texto longo; criar versão curta para casos sem pendência de palestras.",
        ],
    },
    {
        "cat": "Sobre o Programa e Dúvidas Gerais",
        "orig": "38. Licença médica",
        "title": "Acesso durante licença médica — orientação pelo RH do órgão",
        "items": [
            "Incluir licença-paternidade e outras afastamentos, se a regra for a mesma.",
            "Usar linguagem inclusiva (“da servidora/do servidor”).",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "39. Certificado atual",
        "title": "Orientação para emissão do certificado no Moodle",
        "items": [
            "Corrigir “Acesse o seu iniciar” → “Acesse a página inicial”.",
            "Detalhar caminho completo até o botão Certificado.",
            "Conflita com respostas que dizem que o certificado ainda não está disponível — marcar claramente a condição de uso.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "(sem título) Instabilidade na emissão do certificado",
        "title": "Instabilidade na emissão do certificado já normalizada",
        "items": [
            "Dar título e categoria no banco; estava órfã no original.",
            "Pedir horário aproximado e print se persistir.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "(sem título) Já concluiu o curso",
        "title": "Confirmação de conclusão do curso",
        "items": [
            "Indicar onde baixar o certificado após a conclusão.",
            "Evitar uso se ainda houver pendências (Sala de Conferências).",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "(sem título) Eixos concluídos — Sala de Conferências",
        "title": "Eixos concluídos — pendência na Sala de Conferências",
        "items": [
            "Havia duplicata quase idêntica no final do arquivo; manter uma só.",
            "Incluir caminho na plataforma para a Sala de Conferências.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Inconsistências solucionadas",
        "title": "Inconsistências solucionadas — confirmação simples",
        "items": [
            "Diferenciar da versão longa com garantia de certificação.",
            "Registrar no banco como resposta curta de “já corrigido”.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Inconsistências + Plano de Estudos",
        "title": "Inconsistências solucionadas com orientação sobre Plano de Estudos",
        "items": [
            "Separar em duas respostas (técnico + plano) para reutilização.",
            "Alinhar regra de alteração única com o item 29.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Correções em andamento",
        "title": "Correções em andamento",
        "items": [
            "Acrescentar o que o usuário pode/não pode fazer enquanto aguarda.",
            "Evitar promessa genérica sem estimativa quando o incidente for conhecido.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "(sem título) Nota mínima não atingida nos Eixos 5 e 6",
        "title": "Nota mínima não atingida — recuperação disponível",
        "items": [
            "Parametrizar eixos/disciplinas (hoje fixos em 5 e 6 / disciplina 3).",
            "Bom modelo pedagógico; usar como template variável.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Uso em celular",
        "title": "Recomendação de uso em computador ou notebook",
        "items": [
            "Listar recursos conhecidos com limitação no mobile, se houver.",
            "Manter tom acolhedor; evitar parecer proibição absoluta.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Alterar avatar",
        "title": "Alteração de avatar no perfil",
        "items": [
            "Passo a passo claro; validar se os nomes dos menus ainda batem com a UI atual.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Pedido de detalhes de acesso",
        "title": "Solicitação de detalhes sobre falha de acesso",
        "items": [
            "Bom modelo de triagem; reutilizar como padrão para erros não reproduzidos.",
        ],
    },
    {
        "cat": "Acesso à Plataforma e Suporte Técnico",
        "orig": "(sem título) Progresso + dúvida de acesso",
        "title": "Progresso confirmado e pedido de detalhes sobre acesso",
        "items": [
            "Mistura status acadêmico com triagem de acesso; preferir focar no problema relatado.",
        ],
    },
    {
        "cat": "Certificação, Progresso e Comprovação",
        "orig": "(sem título) Carga horária mínima / próxima semana",
        "title": "Certificado disponível após cumprimento da carga horária mínima",
        "items": [
            "Evitar “a partir da próxima semana” genérico; preferir data ou regra objetiva.",
            "Explicar o que é a carga horária mínima (dias/horas) para o usuário entender o motivo.",
        ],
    },
]


def build_banco():
    doc = Document()
    style = doc.styles["Normal"]
    style.font.name = "Calibri"
    style.font.size = Pt(11)

    title = doc.add_heading("Banco de Respostas — Atendimento PDI", level=0)
    for run in title.runs:
        run.font.color.rgb = RGBColor(0x1F, 0x4E, 0x79)

    add_para(
        doc,
        "Documento reorganizado por categorias, com títulos descritivos (sem formulação de pergunta) "
        "para apoiar o atendimento do Programa de Desenvolvimento Inicial (PDI).",
        italic=True,
        size=10,
    )
    add_para(
        doc,
        "Como usar: localize a categoria, escolha o título que corresponde à situação e copie o texto. "
        "O campo “Quando usar” ajuda a selecionar a resposta correta.",
        size=10,
    )

    add_heading(doc, "Sumário de categorias", level=1)
    for cat in CATEGORIES:
        add_bullet(doc, f"{cat['name']} ({len(cat['items'])} respostas) — {cat['desc']}")

    for cat in CATEGORIES:
        add_heading(doc, cat["name"], level=1)
        add_para(doc, cat["desc"], italic=True, size=10)
        for item in cat["items"]:
            add_response(doc, item["title"], item["body"], when_to_use=item.get("when"))

    path = OUT_DIR / "Banco_de_Respostas_PDI_Reorganizado.docx"
    doc.save(path)
    return path


def build_relatorio():
    doc = Document()
    style = doc.styles["Normal"]
    style.font.name = "Calibri"
    style.font.size = Pt(11)

    title = doc.add_heading("Relatório de Melhorias — Banco de Respostas PDI", level=0)
    for run in title.runs:
        run.font.color.rgb = RGBColor(0x1F, 0x4E, 0x79)

    add_para(
        doc,
        "Análise das entradas originais do banco de respostas, com recomendações de melhoria "
        "por item e observações transversais.",
        italic=True,
        size=10,
    )

    add_heading(doc, "1. Achados gerais", level=1)
    gerais = [
        "O arquivo misturava títulos em forma de pergunta, rótulos internos em CAIXA ALTA e respostas sem título no final.",
        "Havia numeração inconsistente (itens sem Heading, dois “24”, salto de 30 para 35, ausência de 20 como Heading).",
        "Várias respostas sobre certificado/palestras/Integra se sobrepõem e citam prazos temporários (ex.: “fim de julho”).",
        "Existiam duplicatas claras (comprovante 2≈33; SIAPE 3≈34; nota mínima 22≈23; Sala de Conferências repetida).",
        "Orientações internas (PDI-DEX, erro 1969, nomes de pessoas) estavam misturadas com textos ao público.",
        "Assinaturas e vocativos variavam (Equipe PDI-NS, PDI/ENAP, Prezado/Prezada/Prezados).",
        "Alguns textos tinham erros gramaticais ou frases incompletas.",
    ]
    for g in gerais:
        add_bullet(doc, g)

    add_heading(doc, "2. Reorganização aplicada", level=1)
    for cat in CATEGORIES:
        add_bullet(doc, f"{cat['name']}: {len(cat['items'])} respostas.")

    add_heading(doc, "3. Melhorias por pergunta/resposta original", level=1)
    current_cat = None
    for row in IMPROVEMENTS:
        if row["cat"] != current_cat:
            current_cat = row["cat"]
            add_heading(doc, current_cat, level=2)
        add_heading(doc, row["orig"], level=3)
        p = doc.add_paragraph()
        r1 = p.add_run("Título proposto: ")
        set_run_font(r1, bold=True, size=10)
        r2 = p.add_run(row["title"])
        set_run_font(r2, size=10)
        for tip in row["items"]:
            add_bullet(doc, tip)

    add_heading(doc, "4. Prioridades recomendadas", level=1)
    prioridades = [
        "Alta — Remover/atualizar prazos temporários e unificar o bloco certificado + palestras + Integra.",
        "Alta — Separar respostas públicas de playbooks internos (DEX, erro 1969).",
        "Média — Eliminar duplicatas e criar aliases de busca (tags) em vez de cópias.",
        "Média — Padronizar vocativo, assinatura, nota mínima (6,0 / 60%) e links oficiais.",
        "Baixa — Parametrizar campos (eixo, disciplina, data) nos modelos de atendimento.",
    ]
    for prio in prioridades:
        add_bullet(doc, prio)

    path = OUT_DIR / "Relatorio_Melhorias_Banco_Respostas_PDI.docx"
    doc.save(path)
    return path


def main():
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    ARTIFACT_DIR.mkdir(parents=True, exist_ok=True)
    banco = build_banco()
    relatorio = build_relatorio()
    # Cópias para artefatos da walkthrough
    for src in (banco, relatorio):
        dest = ARTIFACT_DIR / src.name
        dest.write_bytes(src.read_bytes())
    total = sum(len(c["items"]) for c in CATEGORIES)
    print(f"Banco: {banco} ({total} respostas em {len(CATEGORIES)} categorias)")
    print(f"Relatório: {relatorio} ({len(IMPROVEMENTS)} itens analisados)")


if __name__ == "__main__":
    main()
