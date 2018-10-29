<?php
	ini_set('error_reporting', E_ALL);
	ini_set ('display_errors', 1);
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	ini_set('mysql.connect_timeout','0');
	ini_set("mysql.trace_mode","On");
	ini_set('max_execution_time', 1000);
	@set_time_limit(0);
	ini_set('memory_limit', '1500M');
	
	define ( 'DATALIFEENGINE', true );
	define ( 'ROOT_DIR', dirname ( __FILE__ ) . '/../../..' );
	define ( 'ENGINE_DIR', ROOT_DIR . '/engine' );
	
	define ("COLLATE", "utf8");
	
	require_once ENGINE_DIR . '/classes/mysql.php';
	require_once ENGINE_DIR . '/data/dbconfig.php';
	require_once ROOT_DIR . '/language/Russian/website.lng';
	require_once ENGINE_DIR . '/modules/functions.php';
	//require_once ENGINE_DIR . '/modules/litres/rus-to-lat.php';
	
	$mysqli = new mysqli(DBHOST,DBUSER,DBPASS,DBNAME);

    /* проверка соединения */
    if ($mysqli->connect_errno) {
        printf("‘оединение не установлено: %s\n", $mysqli->connect_error);
        exit();
    }
	
	$table_prefix = PREFIX . '_';
	
	
?>