<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Portuguese (Brazil) strings for local_dashboard.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Dashboard';
$string['dashboard'] = 'Perfil';
$string['courseduration'] = 'Previsão de conclusão de curso';
$string['coursedurationlong'] = 'O plano {$a->planname} estabelece uma estimativa de {$a->planmonths} meses para a conclusão completa do curso.';
$string['months'] = '{$a} meses';
$string['planname'] = 'Plano {$a}';
$string['pannel'] = 'Painel';
$string['gamificationrules'] = 'Regras de Gamificação';
$string['preferences'] = 'Preferências';
$string['yourprogress'] = 'Seu Progresso';
$string['yourlearningjourney'] = 'Sua Jornada de Aprendizado';
$string['trackyourprogress'] = 'Acompanhe seu progresso em cada eixo';
$string['eixograde'] = '{$a}';
$string['specializationscompleted'] = '{$a} Eixo(s) Completo(s)';
$string['specializationinprogress'] = '{$a} em Andamento';
$string['levelinprogress'] = 'Nível {$a}';
$string['ofspecialization'] = 'Do {$a}';
$string['progressdescription'] = '{$a->name}, você está no Nível {$a->level} do {$a->specialization}. Continue assim!';
$string['newlevels'] = '+{$a} níveis este mês';
$string['overview'] = 'Visão Geral';
$string['onaverage'] = 'de aproveitamento';
$string['averagedesc'] = 'O aproveitamento é baseado no seu desempenho nas atividades avaliativas.';
$string['doingwell'] = 'Você está indo muito bem!';
$string['keys'] = 'Chaves';
$string['newthismonth'] = '+{$a} esse mês';
$string['completedalllevels'] = 'Você completou todos os níveis do {$a}';
$string['currentspecialization'] = 'Você está no Nível {$a->level} do {$a->specializationname}';
$string['confroomalwaysavailable'] = '{$a} sempre disponível - você pode acessar a qualquer momento';
$string['confroomcompleted'] = 'Você completou todos os conteúdos da {$a}';
$string['confroomlabel_line1'] = 'Sala de';
$string['confroomlabel_line2'] = 'Conferências';
$string['seeallnotifications'] = 'Ver todas as notificações';
$string['lastnotifications'] = 'Últimas Notificações';
$string['youroverallprogress'] = 'Seu progresso Geral';
$string['overallprogresshowto'] = 'O percentual considera a carga horária de cada Eixo (e da Sala de Conferências) somente após a conclusão das atividades e o alcance da nota mínima de {$a}% no curso e em cada disciplina (seção) com avaliação.';
$string['platformgamificationrules'] = 'Regras de Gamificação da Plataforma';
$string['watchtolearn'] = 'Assista ao vídeo abaixo para entender como funciona o sistema de gamificação da plataforma, incluindo como ganhar chaves, conquistar badges e avançar nos rankings.';
$string['locked'] = 'Bloqueado';
$string['available'] = '{$a} disponível';
$string['belowmingrade'] = 'Você concluiu o {$a}, mas não atingiu a nota mínima de 60% no curso ou em alguma disciplina';
$string['notenroled'] = 'Não Inscrito';
$string['place'] = 'Lugar';
$string['amongstudents'] = ' entre {$a} alunos';
$string['similarperformance'] = 'dos alunos têm desempenho similar';
$string['coursecompleted'] = '% do Curso concluído.';
$string['expectedprogress'] = 'De acordo com seu plano, o esperado seria {$a}% de conclusão.';
$string['badpace'] = 'Aumente o seu ritmo! Você já concluiu {$a->currentprogress}% do curso, mas, considerando seu plano atual ({$a->months} meses), o esperado seria {$a->expectedprogress}% de conclusão.';
$string['goodpace'] = 'Mantenha o ritmo! Você já concluiu {$a->currentprogress}% do curso, e, considerando seu ritmo atual ({$a->months} meses), o esperado seria {$a->expectedprogress}% de conclusão.';
$string['badgeboard'] = 'Quadro de Medalhas';
$string['badgedesc'] = 'Suas conquistas nos eixos do curso';

// Relatório administrativo de ranking.
$string['admincategory'] = 'Dashboard';
$string['rankingpageheading'] = 'Ranking de alunos';
$string['rankingintro'] = 'Ranking de moedas de todos os alunos ativos, do melhor para o pior. Visível apenas para administradores.';
$string['rankingtotalstudents'] = 'Alunos ativos: {$a}';
$string['rankingdownloadcsv'] = 'Baixar CSV';
$string['rankingnostudents'] = 'Nenhum aluno ativo encontrado.';
$string['rankingcolposition'] = 'Posição';
$string['rankingcolname'] = 'Nome';
$string['rankingcolusername'] = 'Usuário';
$string['rankingcolemail'] = 'E-mail';
$string['rankingcolcoins'] = 'Moedas';
$string['rankingcolfinalgrade'] = 'Nota final';
$string['rankinggradenote'] = 'Cada coluna de Eixo mostra a nota consolidada do curso para os alunos que concluíram aquele Eixo; um Eixo não concluído conta como 0. A nota final é a soma das notas de todos os Eixos. Os alunos são classificados por moedas; empates em moedas são desempatados pela maior nota final.';

// Carga horária dos cursos (peso do progresso).
$string['workloadpageheading'] = 'Carga horária dos cursos';
$string['workloadintro'] = 'Informe a carga horária (em horas) de cada Eixo e de cada curso Sala de Conferências. '
    . 'O progresso exibido no dashboard (realizado e esperado) é calculado de forma proporcional: um curso com o '
    . 'dobro de horas contribui com o dobro para a porcentagem geral. Deixe o campo vazio ou zero para excluir o '
    . 'curso do cálculo. Se nenhuma carga horária estiver configurada, o sistema usa o padrão anterior: 90% para '
    . 'os Eixos e 10% para a Sala (divididos igualmente dentro de cada grupo).';
$string['workloadeixos'] = 'Eixos (format_specialization)';
$string['workloadsala'] = 'Sala de Conferências (format_saladeconferencias)';
$string['workloadhours'] = 'Carga horária (horas)';
$string['workloadhours_help'] = 'Número de horas deste curso. Usado para calcular a participação dele na porcentagem geral de progresso do dashboard.';
$string['workloadnocourses'] = 'Nenhum curso encontrado para este formato.';
$string['workloadinvalid'] = 'Informe um número maior ou igual a zero.';
$string['workloadsaved'] = 'Carga horária dos cursos salva.';
$string['workloadfallbacknotice'] = 'Nenhuma carga horária configurada ainda. O progresso usa o peso padrão: 90% para os Eixos e 10% para a Sala de Conferências.';
$string['workloadsummary'] = '{$a->courses} curso(s) configurado(s), total de {$a->hours} horas. As porcentagens de progresso são proporcionais a esses valores.';
