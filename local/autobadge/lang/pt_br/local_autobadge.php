<?php

// General strings
$string['pluginname'] = 'Medalhas Automáticas';
$string['messageprovider:autobadgecontrol'] = 'Concessão e expiração de Medalhas Automáticas';

// Messages to user
$string['awardmessage'] = 'Mensagem a ser enviada na concessão de medalha';
$string['awardmessage_desc'] = 'Mensagem a ser enviada ao estudante sempre que ele/ela receber uma nova medalha. Pode ser um texto limpo ou em formato Moodle-auto, incluindo tags HTML e multilinguagem. As seguintes variáveis podem ser incluídas na mensagem: Nome da medalha {$a->badgename}, Descrição da medalha {$a->badgedescription}, Nome do curso {$a->coursename}, Nome completo do estudante {$a->fullname} e Primeiro nome do estudante {$a->firstname}';
$string['awardmessage_default'] = 'Parabéns! Você recebeu a medalha {$a->badgedescription} por ter completado o conteúdo obrigatório do {$a->coursename}.';
$string['awardsubject'] = 'Assunto da mensagem a ser enviada na concessão de medalha';
$string['awardsubject_desc'] = 'Assunto da mensagem a ser enviada ao estudante sempre que ele/ela receber uma nove medalha. Em formato de texto limpo, pode conter as seguintes variáveis: Nome da medalha {$a->badgename}, Descrição da medalha {$a->badgedescription}, Nome do curso {$a->coursename}, Nome completo do estudante {$a->fullname} e Primeiro nome do estudante {$a->firstname}';
$string['awardsubject_default'] = '+1 Medalha';
