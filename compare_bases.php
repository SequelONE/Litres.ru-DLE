<?php

	include("config.php");
	include("config_litres.php");
	include("functions.php");
	
	index_local_data();
	
	//����������� �����
	compare_local_global(0);
	
	//����������� pdf �����
	compare_local_global(4);
	
	//����������
	compare_local_global(1);
	
	//��������� ������ � �����
	compare_local_global_by_sequnces(0);
	
	//��������� ������ � "���������" � �.�.
	compare_local_global_by_collections(0);
	

	file_put_contents_curl($stats_url,array('partner_data' => json_encode(collect_stats())));
	
	echo 'finished';
?>