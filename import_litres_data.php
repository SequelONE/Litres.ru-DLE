<?php

	ini_set('memory_limit', '1600M');
	ini_set('max_execution_time','1200');
	ini_set('mysql.connect_timeout', 1000);
	
	//$exclude_genres = array('prose_rus_classic','prose_su_classics','antique_ant','antique_east','antique_russian','antique_european','foreign_antique','literature_18','literature_19','antique_myths','antique');
	
	include("config.php");
	include("config_litres.php");
	include("functions.php");

	//перебираем все типы материалов, которые нужно импортировать
	//0-книги, 1-аудиокниги, 4 - pdf-книги, 11 - книги на английском, 12 - бумажные книги
	$types_array = array(0,1,4,11,12);
	foreach ($types_array as $type){
		
		$k = 0;
		
		$q = "SELECT MAX(`updated`) + INTERVAL 1 SECOND AS start_date FROM litres_data WHERE type=" . $type;
		$res = $mysqli->query($q);
		if ($res->num_rows > 0){
			$row = $res->fetch_array(MYSQLI_ASSOC);
			if ($row['start_date']){
				$start_date = $row['start_date'];
			}
			else{
				$start_date = "2018-02-19";
			}
		}
		else{
			$start_date = "2018-10-29";
		}
		//echo $start_date; exit;
		//$start_date = date("Y-m-d", time()-11*86400);
		//$end_date = date("Y-m-d", time()+1*86400);
		
		//$start_date = "2017-05-20";
		//$end_date = "2017-05-18";
		
		//ручной импорт определенной книги
		//$uuid = '';
		//$uuid = 'b13a1490-168c-11e2-86b3-b737ee03444a';
		
		$secret_key = 'D7psdo2s*Xjsoq3WdsoSWWvoo';
		$place = 'YABK';
		$timestamp = time();
		
		$url = 'https://partnersdnld.litres.ru/get_fresh_book/?place=' . $place . ($uuid != '' ? '&checkpoint=' . $start_date . '&uuid=' . $uuid : '&checkpoint=' . $start_date . '&endpoint=' . $end_date) . '&sha=' . hash('sha256',$timestamp.':'.$secret_key.':'.$start_date) . '&timestamp=' . $timestamp . '&type=' . $type . '&limit=20000';
		$url = str_replace(' ', '+', $url);
		
		$s = file_get_contents_curl($url);

		$s = str_ireplace('updated-book','updated_book',$s);
		$s = str_ireplace('removed-book','removed_book',$s);
		$s = str_ireplace('title-info','title_info',$s);
		$s = str_ireplace('publish-info','publish_info',$s);
		$s = str_ireplace('first-name','first_name',$s);
		$s = str_ireplace('last-name','last_name',$s);
		$s = str_ireplace('book-title','book_title',$s);
		$s = str_ireplace('full-name-rodit','full_name_rodit',$s);
		$s = str_ireplace('src-lang','src_lang',$s);
		
		$xml = simplexml_load_string($s);
		//удаление литресовских книг
		//если книга на литресе удалилась то значит можем ее опять размещать в том виде что была в библиотеке, поэтому восстанавливаем данные о книге из бекапа
		foreach ($xml->removed_book as $value){
			if (!$mysqli->ping()) {
                printf ("Ошибка: %s\n", $mysqli->error);
            }
			$hub_id = $value->attributes()->id;
			delete_litres_book($hub_id);
			/* закрываем соединение */
            $mysqli->close();
		}
		//--------------------------------
		
		//добавление новых литресовских книг
		foreach ($xml->updated_book as $value){
			if (!$mysqli->ping()) {
                printf ("Ошибка: %s\n", $mysqli->error);
                /* закрываем соединение */
                $mysqli->close();
            }
			$hub_id = $value->attributes()->id;
			if ($value->attributes()->must_import == 0 && ($type != 12)){
				delete_litres_book($hub_id);
			}
			//elseif ((($value->attributes()->must_import == 1) && ($value->attributes()->allow_sell > 0)) || $type == 12){
			elseif ((($value->attributes()->must_import == 1)) || $type == 12){
				$litres_id = $value->attributes()->file_id;
				$options = $value->attributes()->options;
				$updated = $value->attributes()->updated;
				$price = $value->attributes()->price;
				$has_trial = $value->attributes()->has_trial;
				echo $global_book_id = $value->attributes()->external_id;
				echo "\r\n";
				$book_url = $value->attributes()->url;
				$contract_title = $value->attributes()->contract_title;
				
				//у книги может быть несколько авторов
				foreach ($value->title_info->author as $authors_node)
				{
					$author_data = array();
					if ($authors_node->id != '')
					{
						$global_auth_id = strval($authors_node->id);
						$author_data['global_auth_id'] = strval($authors_node->id);

						$author_data['first_name'] = strval($authors_node->first_name);
						$author_data['last_name'] = strval($authors_node->last_name);

						$authors_maindata["$global_auth_id"] = $author_data;
					}
					unset($author_data);
				}
				unset($authors_node);
				
				//у книги может быть несколько авторов, выбираем главного (из доп поля об авторах)
				$authors_extdata = array();
				foreach ($value->authors->author as $authors_node)
				{
					$author_data = array();
					//relation = 0 - значит автор (бывают еще редакторы, издатели и т.п.)
					if (($authors_node->attributes()->id != '') && (intval($authors_node->relation) == 0))
					{
						$global_auth_id = strval($authors_node->attributes()->id);
						$author_data['global_auth_id'] = strval($authors_node->attributes()->id);
						$author_data['first_name'] = strval($authors_node->first_name);
						$author_data['last_name'] = strval($authors_node->last_name);
						$author_data['subject_id'] = intval($authors_node->subject_id);
						$author_data['url'] = strval($authors_node->url);
						$author_data['full_name_rodit'] = strval($authors_node->full_name_rodit);
						$author_data['lvl'] = intval($authors_node->lvl);
						$authors_extdata["$global_auth_id"] = $author_data;
					}
					unset($author_data);
				}
				unset($authors_node);

				if (count($authors_extdata) > 1){	//если у книги больше одного автора
					//сортировка многомерного массива по полю lvl для определения "главного автора"
					foreach($authors_extdata as $c=>$key){
						$sort_lvl[] = $key['lvl'];
					}
					array_multisort($sort_lvl, SORT_DESC, $authors_extdata);
					unset($sort_lvl);
				}
				//--------------------------------------------
				
				//если бумажные книги то доступен только доп массив авторов. делаем его главным
				if($type == 12){
					$authors_maindata = $authors_extdata;
					unset($authors_extdata);
				}
				
				$author_extdata = array_values($authors_extdata);
				$author_maindata = array_values($authors_maindata);
				
				//$author_extdata[0] - главный автор
				//$author_extdata[1] - второй автор
				$hub_author_id = $author_extdata[0]['subject_id'] > 0 ? $author_extdata[0]['subject_id'] : $value->attributes()->subject_id;
				//var_dump($authors_maindata);
				//var_dump($author_extdata);
				$author_url = $author_extdata[0]['url'];
				$author_rodit = $author_extdata[0]['full_name_rodit'];
				$author_lvl = $author_extdata[0]['lvl'];
				
				//глобальный id главного автора
				$global_auth_id = ($author_extdata[0]['global_auth_id'] != '' && array_key_exists($author_extdata[0]['global_auth_id'],$authors_maindata)) ? $author_extdata[0]['global_auth_id'] : $author_maindata[0]['global_auth_id'];
				//глобальный id второго атовра
				$global_second_auth_id = ($author_extdata[1]['global_auth_id'] != '' && array_key_exists($author_extdata[1]['global_auth_id'],$authors_maindata)) ? $author_extdata[1]['global_auth_id'] : $author_maindata[1]['global_auth_id'];
				
				$author_name = $authors_maindata[$global_auth_id]['first_name'];
				$author_sname = $authors_maindata[$global_auth_id]['last_name'];
				$second_author_name = $authors_maindata[$global_second_auth_id]['first_name'];
				$second_author_sname = $authors_maindata[$global_second_auth_id]['last_name'];
				unset($author_extdata); unset($authors_extdata); unset($author_maindata); unset($authors_maindata);
				
				$book_title = $value->title_info->book_title;
				$annotation = $value->title_info->annotation->p;
				$cover_ext = $value->attributes()->cover;
				$publisher = $value->publish_info->contact_title != '' ? $value->publish_info->contact_title : $value->publish_info->publisher;
				$publ_year = $value->publish_info->year;
				$src_lang = $value->title_info->src_lang;
				$lang = $value->attributes()->lang;
				
				$reteller = $value->title_info->reteller->first_name . ' ' . $value->title_info->reteller->last_name;
				$reader = $value->title_info->reader->nickname;
				$release_date = $value->title_info->date;

				if ($type == 12){
					$genre = $value->in_genre->title;
				}
				else{
					$genre = $value->title_info->genre;
					/*
					if ($value->title_info->genre[1] <> ''){
						$genre .= '|' . $value->title_info->genre[1];
					}
						
					if ($value->title_info->genre[2] <> ''){
						$genre .= '|' . $value->title_info->genre[2];
					}
					*/
				}
				
				$genre_names_array = array();
				foreach ($value->genres->genre as $genres_node){
					if($genres_node->attributes()->title != ''){
						$genre_names_array[] = $genres_node->attributes()->title;
					}
				}
				$genre_names = implode(', ',$genre_names_array);
				
				$sequences_names_array = array();
				foreach ($value->sequences->sequence as $sequences_node){
					if($sequences_node->attributes()->name != ''){
						$sequences_names_array[] = $sequences_node->attributes()->name;
					}
				}
				$sequences_names = implode(', ',$sequences_names_array);
				
				$q = "INSERT INTO `litres_data" . ($type == 12 ? '_paper' : '') . "` SET 
						hub_id = ".$hub_id.",
						litres_id = ".$litres_id.",
						litres_url = '" . $mysqli->real_escape_string($book_url) . "',
						" . ($hub_author_id !='' ? "hub_author_id = " . $hub_author_id . "," : "") . "
						litres_a_url = '" . $mysqli->real_escape_string($author_url) . "',
						global_book_id = '".$global_book_id."',
						has_trial = '".$has_trial."',
						author_name = '" . $mysqli->real_escape_string($author_name) . "',
						author_sname = '" . $mysqli->real_escape_string($author_sname) . "',
						litres_a_rod = '" . $mysqli->real_escape_string($author_rodit) . "',
						author_lvl = '" . $author_lvl . "'," .
						($second_author_name != '' ? "second_author_name = '" . $mysqli->real_escape_string($second_author_name) . "'," : "") . 
						($second_author_sname != '' ? "second_author_sname = '" . $mysqli->real_escape_string($second_author_sname) . "'," : "") . "
						book_title = '" . $mysqli->real_escape_string($book_title) . "',
						genre = '".$genre."',
						genre_names = '".$genre_names."',
						sequences = '".$mysqli->real_escape_string($sequences_names)."',
						options = ".$options.",
						must_import = " . $value->attributes()->must_import . ",
						you_can_sell = " . $value->attributes()->you_can_sell . ",
						can_preorder = " . $value->attributes()->can_preorder . ",
						lang = '" . $lang . "',
						price = '".$price."',
						type = ".$type.",
						annotation = '" . $mysqli->real_escape_string($annotation) . "',
						cover_ext = '".$cover_ext."',
						contract_title = '" . $mysqli->real_escape_string($contract_title) . "',
						publisher = '" . $mysqli->real_escape_string($publisher) . "',
						publ_year = '" . $publ_year . "',
						reteller = '".$reteller."',
						reader = '".$reader."',
						release_date = '".$release_date."',
						updated = '" . $updated . "',
						date_inserted = NOW()
						ON DUPLICATE KEY UPDATE
						litres_id = ".$litres_id.",
						litres_url = '" . $mysqli->real_escape_string($book_url) . "',
						" . ($hub_author_id !='' ? "hub_author_id = " . $hub_author_id . "," : "") . "
						litres_a_url = '" . $mysqli->real_escape_string($author_url) . "',
						global_book_id = '".$global_book_id."',
						has_trial = '".$has_trial."',
						author_name = '" . $mysqli->real_escape_string($author_name) . "',
						author_sname = '" . $mysqli->real_escape_string($author_sname) . "',
						litres_a_rod = '" . $mysqli->real_escape_string($author_rodit) . "',
						author_lvl = '" . $author_lvl . "'," .
						($second_author_name != '' ? "second_author_name = '" . $mysqli->real_escape_string($second_author_name) . "'," : "") . 
						($second_author_sname != '' ? "second_author_sname = '" . $mysqli->real_escape_string($second_author_sname) . "'," : "") . "
						book_title = '" . $mysqli->real_escape_string($book_title) . "',
						genre = '".$genre."',
						genre_names = '".$genre_names."',
						sequences = '".$mysqli->real_escape_string($sequences_names)."',
						options = ".$options.",
						must_import = " . $value->attributes()->must_import . ",
						you_can_sell = " . $value->attributes()->you_can_sell . ",
						can_preorder = " . $value->attributes()->can_preorder . ",
						lang = '" . $lang . "',
						price = '".$price."',
						type = ".$type.",
						annotation = '" . $mysqli->real_escape_string($annotation) . "',
						cover_ext = '".$cover_ext."',
						contract_title = '" . $mysqli->real_escape_string($contract_title) . "',
						publisher = '" . $mysqli->real_escape_string($publisher) . "',
						publ_year = '".$publ_year."',
						reteller = '".$reteller."',
						reader = '".$reader."',
						release_date = '".$release_date."',
						updated = '".$updated."'";
												
					$res = $mysqli->query($q);
					if(!$res){echo 'not inserted: ' . $q; exit;}
					$k++;
				
				/* закрываем соединение */
                $mysqli->close();
				//новую книгу сразу сравниваем с локальной базой
				//compare_local_global($hub_id);
				//----------------------------------------------
			}
		}
		echo "\r\n" . $k." inserted\r\n";
	}

?>