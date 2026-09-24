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
 * Portuguese (Brazil) strings for local_salagrade.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Nota automática da Sala de Conferências';
$string['privacy:metadata'] = 'O plugin grava notas no livro de notas do Moodle; não armazena dados pessoais em tabelas próprias.';
$string['gradeitemname'] = 'Conclusão dos vídeos';
$string['admincategory'] = 'Nota automática da Sala';
$string['syncpageheading'] = 'Sincronizar notas da Sala';
$string['syncintro'] = 'Atribui nota 100 no livro de notas da Sala de Conferências quando o aluno concluiu todos os vídeos obrigatórios (os mesmos usados para a conclusão do curso). Inclui quem já terminou. A operação é idempotente: quem já tem 100 não é alterado.';
$string['syncqueued'] = 'Sincronização de notas enfileirada. O cron processa em segundo plano; você pode fechar esta página.';
$string['syncalreadyqueued'] = 'Já existe uma sincronização completa na fila ou em execução. Aguarde terminar antes de enfileirar outra.';
$string['syncnow'] = 'Enfileirar sincronização de notas';
$string['syncconfirm'] = 'Enfileirar uma tarefa em segundo plano para atribuir nota 100 a todos os alunos matriculados que já concluíram todos os vídeos obrigatórios da Sala?';
$string['synccourses'] = 'Cursos Sala de Conferências: {$a}';
$string['task_sync_grades'] = 'Sincronizar notas de conclusão da Sala de Conferências';
$string['task_sync_grades_adhoc'] = 'Sincronizar notas de conclusão da Sala de Conferências (fila)';
