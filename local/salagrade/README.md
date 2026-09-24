# Nota automática da Sala de Conferências (`local_salagrade`)

Atribui **nota 100** no livro de notas da Sala de Conferências quando o aluno conclui todos os vídeos obrigatórios visíveis. Cria o item de nota sozinho — não é necessário criar tarefa, questionário nem outro recurso.

## O que o plugin faz

- Detecta cursos com formato **Sala de Conferências** (`saladeconferencias`).
- Cria (se ainda não existir) um item **manual** no livro de notas:
  - Nome: **Conclusão dos vídeos**
  - Identificador (`idnumber`): `local_salagrade`
  - Tipo: valor, mínimo 0, máximo 100
- Grava **100** para cada aluno que concluiu todos os vídeos obrigatórios.
- Não altera o `local_studypace`. Não dispara medalhas nem chaves.

## Quando o aluno recebe 100

Contam como obrigatórios os módulos `video` que estão:

- visíveis (`cm.visible = 1`)
- com rastreamento de conclusão ativado
- não marcados para exclusão

Vídeos ocultos (“em breve”) **não** entram no conjunto e **não** impedem a nota 100.

A conclusão do vídeo precisa ser **COMPLETE** ou **COMPLETE PASS**. Conclusão com falha (FAIL) não conta.

Se não houver nenhum vídeo visível com conclusão ativada, ninguém recebe nota.

## O que o plugin não faz

- Não cria atividade na página do curso — só o item no livro de notas.
- Não baixa a nota se um vídeo novo for publicado depois (quem já tem 100 permanece com 100).
- Não sobrescreve nota **bloqueada**.
- Não redispara conclusão de curso, medalhas ou chaves. Quem já tinha a Sala concluída no studypace continua como estava; só ganha a nota 100 no livro.
- Qualquer matrícula **ativa** (incluindo professores) que tenha assistido todos os vídeos visíveis recebe 100.

## Instalação

1. Copie a pasta `local/salagrade` para o Moodle de produção.
2. Acesse **Administração do site → Notificações** (`/admin/index.php`) e conclua a instalação.
3. Confira no livro de notas da Sala se o item **Conclusão dos vídeos** apareceu (ele surge na primeira sincronização).
4. Ajuste a **agregação do total do curso** e a opção **excluir notas vazias**, para o total ficar 100 quando esse for o item que vale (ou o único).
5. Enfileire o backfill fora de pico: **Administração do site → Notas → Sincronizar notas da Sala**.

Não crie outro item manual com o mesmo propósito — ficaria duplicado.

## Como a nota é atribuída

| Caminho | Quando |
|---|---|
| Observer | Depois que o aluno conclui um vídeo (e depois do studypace, para não interferir em medalhas/chaves) |
| Tarefa agendada | Todos os dias às **03:20** |
| Página admin | **Notas → Sincronizar notas da Sala** (capacidade `moodle/site:config`) |

A sincronização em lote é idempotente: quem já tem 100 é ignorado. A primeira execução em muitos milhares de matrículas pode demorar; as seguintes são baratas.

Se outra sincronização completa estiver em andamento, a tarefa agendada pula a execução; a tarefa adhoc da página admin tenta de novo.

## Dependências

- `format_saladeconferencias`
- `local_studypace` (mínimo `2026052616`) — o plugin **não modifica** o studypace; a dependência existe porque a Sala usa o mesmo critério de vídeos obrigatórios visíveis.

Versão atual: ver `version.php`. Histórico: `versionlog.txt`.

## Arquivos principais

| Arquivo | Função |
|---|---|
| `classes/grader.php` | Cria o item de nota e grava 100 |
| `classes/observer.php` | Reage à conclusão de vídeo |
| `classes/task/sync_grades.php` | Cron diário |
| `classes/task/sync_grades_adhoc.php` | Backfill enfileirado na página admin |
| `sync.php` / `settings.php` | Página **Sincronizar notas da Sala** |
