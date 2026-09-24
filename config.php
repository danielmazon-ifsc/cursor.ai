<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle-pdi';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://localhost/moodle-pdi';
$CFG->dataroot  = '/Users/mazon/moodledatapdi';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// PHPUnit (local dev). See local/bin/run-plugin-tests.sh
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/Users/mazon/moodledatapdi-phpunit';

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
