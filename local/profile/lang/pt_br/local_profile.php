<?php

/**
 * Plugin strings (pt_br)
 *
 * @package   local_profile
 */
$string['pluginname'] = 'Perfil';
$string['profileupdated'] = 'Perfil atualizado!';
$string['profileupdatefailed'] = 'Falha na atualização do perfil!';
$string['nothingtoupdate'] = 'Nenhuma alteração para salvar.';
$string['profile'] = 'Perfil';
$string['editprofile'] = 'Editar perfil';
$string['email'] = 'Email';
$string['city'] = 'Cidade';
$string['country'] = 'País';
$string['profilepicture'] = 'Foto de perfil';
$string['chooseotheravatar'] = 'Escolha outro avatar';
$string['chooseyouravatar'] = 'Escolha seu avatar';
$string['back'] = 'Voltar';
$string['newpassword'] = 'Nova senha';
$string['repeatpassword'] = 'Repetir senha';
$string['minpasswordlength'] = 'Senha deve ter pelo menos 4 caracteres';
$string['repeatpasswordmustmatch'] = 'Senha repetida deve ser igual à senha';
$string['crontask'] = 'Matricula os estudantes em novos cursos de formato eixo';
$string['configurations'] = 'Configurações';
$string['configurationstxt'] = 'Altere o avatar do seu perfil ou o seu plano de estudos nesta página.';
$string['savechanges'] = 'Salvar Alterações';
$string['studyplan'] = 'Plano de Estudos';
$string['changeonlyonce'] = 'Você pode alterar seu plano de estudos.';
$string['changeanytime'] = 'Você pode alterar seu plano de estudos.';
$string['cannotchange'] = 'Você não pode mais alterar seu plano pois já o alterou uma vez.';
$string['yourcurrentplan'] = 'Seu plano atual: ';
$string['plannotselected'] = 'Ainda não selecionado';
$string['selectnewplan'] = 'Selecione um novo plano';
$string['plantxt'] = 'Plano {$a->name} ({$a->months} meses)';
$string['choosethebest'] = 'Escolha o plano que melhor se adapta ao seu ritmo de aprendizado.';
$string['plan'] = 'Plano';
$string['profilepicture'] = 'Foto de perfil';
$string['chooseanotheravatar'] = 'Escolha outro avatar';
$string['chooseyouravatar'] = 'Escolha seu avatar';
$string['membersince'] = 'Membro desde {$a}';
$string['dateformat'] = '%d de %B de %Y';
$string['task_enrol_next_eixo'] = 'Reprocessar matrícula automática no próximo Eixo';

// Admin: diagnóstico de auto-progressão de Eixos.
$string['admincategory'] = 'Perfil';
$string['progcheckpageheading'] = 'Verificação de auto-progressão de Eixos';
$string['progcheckintro'] = 'Lista os alunos que concluíram um Eixo mas nunca foram matriculados no próximo Eixo visível (uma falha na auto-progressão). Você pode reparar um aluno individual ou todos do relatório; o reparo é idempotente e apenas cria a matrícula que está faltando.';
$string['progcheckuseridlabel'] = 'ID do usuário (opcional)';
$string['progcheckuseridplaceholder'] = 'Todos os alunos';
$string['progcheckrunbutton'] = 'Executar verificação';
$string['progchecknone'] = 'Nenhuma falha de auto-progressão encontrada. Todos os alunos que concluíram um Eixo estão matriculados no próximo.';
$string['progcheckfound'] = '{$a} falha(s) de auto-progressão encontrada(s).';
$string['progcheckdownloadcsv'] = 'Baixar CSV';
$string['progcheckrepair'] = 'Reparar';
$string['progcheckrepairall'] = 'Reparar todos';
$string['progcheckrepairallconfirm'] = 'Reexecutar a matrícula automática para todos os alunos do relatório? Isso apenas cria as matrículas que faltam no próximo Eixo.';
$string['progcheckrepairedone'] = 'Reparo concluído para o usuário {$a->userid}: {$a->created} nova(s) matrícula(s) criada(s).';
$string['progcheckrepairedall'] = 'Reparo concluído: {$a} nova(s) matrícula(s) criada(s).';
$string['progcheckcoluser'] = 'Aluno';
$string['progcheckcolusername'] = 'Usuário';
$string['progcheckcolemail'] = 'E-mail';
$string['progcheckcolcompleted'] = 'Eixo concluído';
$string['progcheckcolcompletedon'] = 'Concluído em';
$string['progcheckcolnext'] = 'Próximo Eixo faltante';
$string['progcheckcolretry'] = 'Retentativa automática';
$string['progcheckcolaction'] = 'Ação';
$string['progcheckretryqueued'] = 'Agendada para {$a->time} (tentativa {$a->attempt})';
$string['progcheckretrynone'] = 'Nenhuma agendada';
