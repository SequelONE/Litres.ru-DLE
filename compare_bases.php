<?php

	include("config.php");
	include("config_litres.php");
	include("functions.php");
	
	index_local_data();
	
	//электронные книги
	compare_local_global(0);
	
	//электронные pdf книги
	compare_local_global(4);
	
	//аудиокниги
	compare_local_global(1);
	
	//сравнение автора и серии
	compare_local_global_by_sequnces(0);
	
	//сравнение автора и "сборников" и т.п.
	compare_local_global_by_collections(0);
	

	file_put_contents_curl($stats_url,array('partner_data' => json_encode(collect_stats())));
	
	echo 'finished';
?>