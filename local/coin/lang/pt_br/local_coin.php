<?php

// General strings
$string['pluginname'] = 'Moedas';
$string['crontask'] = 'Expiração das Moedas';

// Settings strings
$string['coindenomination'] = 'Nome da moeda';
$string['coindenomination_desc'] = 'Nome pelo qual as moedas serão referenciadas neste site.';
$string['coindenomination_default'] = 'Chave';
$string['coindenomination_plural'] = 'Plural do nome da moeda';
$string['coindenomination_plural_desc'] = 'Plural do nome pelo qual as moedas serão referenciadas neste site.';
$string['coindenomination_plural_default'] = 'Chaves';
$string['expirationperiod'] = 'Período de expiração das moedas';
$string['expirationperiod_desc'] = 'Número de dias após serem concedidas a partir do qual as moedas perderão seu valor.';
$string['coinsawarding'] = 'Eventos que concedem moedas';
$string['coinsawarding_desc'] = 'Especifique quais eventos concedem moedas e quantas moedas concedem, bem como a quem as moedas serão concedidas: ao usuário que executou a ação ou ao usuário afetado pela ação. Este campo deve conter uma tabela associativa no formato JSON, cada linha contendo um nome de evento, quantidade de moedas a serem concedidas e a quem serão concedidas, se ao usuário que executou a ação ou ao usuário afetado pela ação (userid/relateduserid). Exemplo: {"\\\\\\core\\\\\\event\\\\\\course_viewed":{"amount":1,"userid":"userid"},"\\\\\\mod_page\\\\\\event\\\\\\course_module_viewed":{"amount":5,"userid":"relateduserid"}}';
$string['coinsawarding_default'] = '{"\\\\local_studypace\\\\event\\\\planned_completion":{"amount":10,"userid":"relateduserid"},"\\\\local_studypace\\\\event\\\\planned_completion_high_grade":{"amount":20,"userid":"relateduserid"}}';
$string['coinscancelling'] = 'Eventos que cancelarão concessão de moedas';
$string['coinscancelling_desc'] = 'Especifique quais eventos cancelarão concessão de moedas e de quem a concessão será cancelada: do usuário que executou a ação ou do usuário afetado pela ação. Este campo deve conter uma tabela associativa no formato JSON, cada linha contendo um nome de evento e dea quem serão canceladas, se do usuário que executou a ação ou do usuário afetado pela ação (userid/relateduserid). Exemplo: {"\\\\\\core\\\\\\event\\\\\\course_viewed":{"userid":"userid"},"\\\\\\mod_page\\\\\\event\\\\\\course_module_viewed":{"userid":"relateduserid"}}';
$string['coinscancelling_default'] = '{}';
$string['coinconfig'] = 'Configuração de Moedas';
$string['coinledger'] = 'Extrato de Moedas';
$string['coinsbalance'] = 'Saldo de Moedas';

// Messages to user
$string['awardmessage'] = 'Menssagem a ser enviada quando moedas forem concedidas';
$string['awardmessage_desc'] = 'Mensagem a ser enviada ao usuário sempre que ele receber novas moedas. Pode ser um texto pleno ou no formato Moodle-auto, incluindo tags HTML e multi-linguagem. As seguintes variáveis podem ser incluídas na mensagem: Quantidade de moedas {$a->amount}, Motivo das moedas {$a->reason}, Nome completo do usuário {$a->fullname} e Primeiro nome do usuário {$a->firstname}';
$string['awardmessage_default'] = 'Parabéns! Você recebeu {$a->amount} chaves por ter completado o conteúdo obrigatório do eixo.';
$string['awardsubject'] = 'Assunto da mensagem a ser enviada quando moedas forem concedidas';
$string['awardsubject_desc'] = 'Assunto da mensagem a ser enviada ao usuário sempre que ele receber novas moedas. Em formato de texto pleno, pode conter as seguintes variáveis: Quantidade de moedas {$a->amount}, Motivo das moedas {$a->reason}, Nome completo do usuário {$a->fullname} e Primeiro nome do usuário {$a->firstname}';
$string['awardsubject_default'] = '+{$a->amount} Chaves';
$string['cancelmessage'] = 'Mensagem a ser enviada quando moedas forem canceladas';
$string['cancelmessage_desc'] = 'Mensagem a ser enviada ao usuário sempre que ele tiver moedas canceladas. Pode ser um texto pleno ou no formato Moodle-auto, incluindo tags HTML e multi-linguagem. As seguintes variáveis podem ser incluídas na mensagem: Quantidade de moedas {$a->amount}, Motivo das moedas {$a->reason}, Nome completo do usuário {$a->fullname} e Primeiro nome do usuário {$a->firstname}';
$string['cancelmessage_default'] = 'Olá {$a->firstname},

Informamos que {$a->amount} chave(s) foram retiradas da sua pilha.

{$a->reason}

Seu time de aprendizado';
$string['cancelreason_expired'] = 'As chaves expiraram após o prazo de validade configurado no curso.';
$string['cancelmessage_expired'] = 'Olá {$a->firstname},

{$a->amount} chave(s) expiraram e foram retiradas da sua pilha, conforme o prazo de validade do curso.

Seu time de aprendizado';
$string['cancelmessage_expired_setting'] = 'Mensagem quando chaves expiram (tarefa agendada)';
$string['cancelmessage_expired_setting_desc'] = 'Enviada à meia-noite quando o cron remove chaves antigas do extrato. Variáveis: {$a->firstname}, {$a->amount}, {$a->fullname}.';
$string['cancelsubject'] = 'Assunto da mensagem a ser enviada quando moedas forem canceladas';
$string['cancelsubject_desc'] = 'Assunto da mensagem a ser enviada ao usuário sempre moedas dele tiverem sido canceladas. Em formato de texto pleno, pode conter as seguintes variáveis: Quantidade de moedas {$a->amount}, Motivo das moedas {$a->reason}, Nome completo do usuário {$a->fullname} e Primeiro nome do usuário {$a->firstname}';
$string['cancelsubject_default'] = 'Chaves retiradas da sua pilha';

// Event strings
$string['eventcoinsawarded'] = 'Moedas concedidas';
$string['eventcoinscancelled'] = 'Concessão de moedas cancelada';
$string['eventcoinssubtracted'] = 'Moedas subtraídas';

// Message strings
$string['messageprovider:coinstackchanges'] = 'Messagens enviadas para informar aos usuários de mudanças nas pilhas de moedas destes';
// Deprecated: kept for older language caches; no longer used in code.
$string['secret'] = 'Segredo';

$string['coinsledger'] = 'Extrato de Moedas';
$string['startdate'] = 'Data de início';
$string['finishdate'] = 'Data de término';
$string['getledger'] = 'Gerar extrato';
$string['coins'] = 'Moedas';
$string['action'] = 'Ação';

// Upgrade health checks (admin UI).
$string['upgradecheckspageheading'] = 'Verificação de upgrade (chaves)';
$string['upgradechecksintro'] = 'Use esta página no lugar de consultas SQL manuais antes e depois de atualizar os plugins de gamificação (Moedas / Study Pace).';
$string['upgradechecksversionsheading'] = 'Versões instaladas';
$string['upgradechecksplugincol'] = 'Plugin';
$string['upgradechecksversioncol'] = 'Versão no banco';
$string['upgradechecksversionshint'] = 'Após copiar os arquivos, abra Administração do site → Notificações para aplicar o upgrade. local_coin esperado: 2026052641 ou superior.';
$string['upgradechecksbeforeheading'] = 'Antes do upgrade';
$string['upgradechecksbeforeintro'] = 'Duplicatas no extrato (coin_ledger) com a mesma combinação usuário + curso + ação. Se houver grupos duplicados, faça backup do banco antes de atualizar o plugin Moedas.';
$string['upgradechecksnoduplicates'] = 'Nenhuma duplicata encontrada no extrato. Pode prosseguir com o upgrade.';
$string['upgradechecksduplicatesfound'] = 'Foram encontrados {$a->groups} grupo(s) duplicados ({$a->extras} linha(s) extras no extrato).';
$string['upgradechecksduplicatehint'] = 'O upgrade do plugin Moedas remove as linhas extras e recalcula os saldos automaticamente.';
$string['upgradechecksafterheading'] = 'Depois do upgrade';
$string['upgradechecksafterintro'] = 'Compara o saldo (coin_stack) com a soma do extrato (coin_ledger) por usuário e curso.';
$string['upgradechecksbalancesok'] = 'Saldo e extrato estão alinhados.';
$string['upgradechecksmismatchfound'] = '{$a->count} combinação(ões) usuário/curso com saldo diferente do extrato.';
$string['upgradechecksorphansfound'] = '{$a->count} combinação(ões) com crédito no extrato sem linha de saldo — use o botão abaixo para corrigir.';
$string['upgradechecksusercol'] = 'Usuário';
$string['upgradecheckscoursecol'] = 'Curso (ID)';
$string['upgradechecksactioncol'] = 'Ação (extrato)';
$string['upgradechecksduplicatecountcol'] = 'Linhas duplicadas';
$string['upgradechecksstackcol'] = 'Saldo atual';
$string['upgradechecksledgercol'] = 'Soma do extrato';
$string['upgradecheckssampletruncated'] = 'Mostrando apenas os primeiros 50 registros.';
$string['upgradechecksrebuildhint'] = 'Se ainda houver divergência após o upgrade automático, use este botão para recalcular todos os saldos a partir do extrato.';
$string['upgradechecksrebuildbutton'] = 'Recalcular saldos a partir do extrato';
$string['upgradechecksrebuilddone'] = 'Recálculo concluído: {$a->updated} saldo(s) atualizado(s), {$a->inserted} criado(s), {$a->zeroed} zerado(s).';
$string['upgradechecksnextheading'] = 'Próximos passos';
$string['upgradechecksnextintro'] = 'Depois de confirmar os saldos:';
$string['upgradecheckslinknotifications'] = 'Administração do site → Notificações (aplicar upgrades pendentes)';
$string['upgradecheckslinkgaps'] = 'Study Pace → Lacunas de gamificação (relatório antes do reparo em lote)';
$string['upgradecheckslinkrepair'] = 'Study Pace → Recalcular e repor chaves (reparo por usuário)';
$string['upgradecheckslinkpurge'] = 'Administração do site → Purgar caches';
