-- =============================================================================
-- Verificação de chaves e medalhas (gamificação PDI)
-- =============================================================================
-- Substitua @userid e @courseid pelos valores reais (ex.: 136 e 8).
-- Prefixo das tabelas: em instalações padrão é mdl_ (ajuste se o seu for outro).
--
-- Uso recomendado: apenas SELECT em produção.
-- A seção "DEV/STAGING" no final ALTERA dados — não rode em produção.
-- =============================================================================

SET @userid := 136;
SET @courseid := 8;

-- 1) Ritmo de estudo (curso + atividades obrigatórias rastreadas)
SELECT target, targetid, level, estimatedcompletion,
       FROM_UNIXTIME(estimatedcompletion) AS prazo,
       actualcompletion,
       FROM_UNIXTIME(actualcompletion) AS concluido_em
  FROM mdl_local_studypace
 WHERE userid = @userid
   AND (courseid = @courseid OR (target = 'course' AND targetid = @courseid))
 ORDER BY target DESC, level ASC;

-- 2) Critérios obrigatórios do curso (/course/completion.php)
SELECT ccc.id, ccc.moduleinstance AS cmid, cm.id AS cm_exists
  FROM mdl_course_completion_criteria ccc
  LEFT JOIN mdl_course_modules cm ON cm.id = ccc.moduleinstance
 WHERE ccc.course = @courseid
   AND ccc.criteriatype = 4;

-- 3) Conclusão Moodle das atividades do curso
SELECT cm.id AS cmid, m.name AS modname, cmc.completionstate,
       FROM_UNIXTIME(cmc.timemodified) AS quando
  FROM mdl_course_modules cm
  JOIN mdl_modules m ON m.id = cm.module
  LEFT JOIN mdl_course_modules_completion cmc
    ON cmc.coursemoduleid = cm.id AND cmc.userid = @userid
 WHERE cm.course = @courseid
   AND cm.completion > 0
 ORDER BY cm.id;

-- 4) Medalhas: existe e foi concedida? (nome = shortname do curso)
SELECT c.shortname,
       b.id AS badge_id, b.name AS badge_name,
       bi.id AS issued_id, FROM_UNIXTIME(bi.dateissued) AS emitida_em
  FROM mdl_course c
  LEFT JOIN mdl_badge b ON b.name = c.shortname OR b.name = CONCAT(c.shortname, 'master')
  LEFT JOIN mdl_badge_issued bi ON bi.badgeid = b.id AND bi.userid = @userid
 WHERE c.id = @courseid;

-- 5) Chaves creditadas neste curso (eventos planned_completion)
SELECT cl.id, cl.amount, cl.action, FROM_UNIXTIME(cl.transactiontime) AS quando
  FROM mdl_coin_ledger cl
 WHERE cl.userid = @userid
   AND cl.courseid = @courseid
 ORDER BY cl.transactiontime DESC;

-- 6) Saldo total de chaves do usuário
SELECT cs.courseid, cs.coins
  FROM mdl_coin_stack cs
 WHERE cs.userid = @userid;

-- 7) Configuração de gatilhos (plugin Coins) — valor em mdl_config
SELECT name, value
  FROM mdl_config
 WHERE name = 'local_coin_triggersjson';

-- =============================================================================
-- DEV / STAGING APENAS — simular "concluiu no prazo" para testar Recalcular
-- =============================================================================
-- Cenário: aluno já fez todas as 5 atividades obrigatórias no Moodle, mas o
-- curso ainda não tem actualcompletion na linha target=course.
--
-- Passos:
--   1) Rode os SELECT acima e confirme CMs concluídas = CMs rastreadas.
--   2) Rode o bloco abaixo (comente se não for ambiente de teste).
--   3) php admin/cli/upgrade.php  (se ainda não subiu local_studypace 2026052607)
--   4) Na web: Recalcular Ritmo de Estudo → usuário @userid
--   5) Confira se marcou curso, matriculou no Eixo 2, e se chaves/medalha aparecem.
--
-- IMPORTANTE: Se actualcompletion do CURSO já estiver preenchido, o Recalcular
-- NÃO re-dispara planned_completion (evita medalha/chave duplicada). Para repetir
-- o teste de gamificação, use um usuário NOVO ou limpe os registros de teste.
-- =============================================================================

/*
-- Estender prazo para "no prazo"
UPDATE mdl_local_studypace
   SET estimatedcompletion = UNIX_TIMESTAMP() + (90 * 86400)
 WHERE userid = @userid
   AND target = 'course'
   AND targetid = @courseid;

-- Garantir que todas as cm-rows obrigatórias estão concluídas no studypace
UPDATE mdl_local_studypace
   SET actualcompletion = UNIX_TIMESTAMP()
 WHERE userid = @userid
   AND courseid = @courseid
   AND target = 'cm'
   AND actualcompletion IS NULL;

-- Permitir que o repair marque o curso (só se ainda NULL)
UPDATE mdl_local_studypace
   SET actualcompletion = NULL
 WHERE userid = @userid
   AND target = 'course'
   AND targetid = @courseid;
*/
