<?php

ini_set('log_errors', 'On');
ini_set('error_log', 'php_errors.log');

	include("config.php");
	include("config_litres.php");
	include("functions.php");
	

	$allowed_genres = "1";

	/*AND local_book_id_litres_catalog IS NULL*/
	/*AND ydisk_book_url != ''*/
	/*AND hub_id IN (" . $top_ids . ")*/
	/*AND has_trial>0*/
	
	$manual_hub_ids = top_sales_hub_ids('https://www.litres.ru/static/ds/topsales3days.yml');

	$q = "(SELECT * FROM `litres_data` 
					WHERE
					`type` = 0 AND
					`hub_id` > 0
					
					AND local_book_id IS NULL
					AND local_book_id_litres_catalog IS NULL

					AND genre != ''
					AND (" . $allowed_genres . ")
					AND options&2
		
					AND hub_id IN (" . implode(',',$manual_hub_ids) . ")
					
					LIMIT 10)
				
				UNION
	
			(SELECT * FROM `litres_data` 
				WHERE
				`type` = 0 AND
				`hub_id` > 0 AND (`lang` = 'ru')
					
				AND local_book_id IS NULL
				AND local_book_id_litres_catalog IS NULL

				AND genre != ''
				AND (" . $allowed_genres . ")
				AND options&2
									
				ORDER BY date_inserted DESC
				LIMIT 5)
			";

	$result = $mysqli->query($q);

	while ($row = $result->fetch_array(MYSQLI_ASSOC)){
		if($row['local_book_id'] == NULL){
			$litres_link = 'https://www.litres.ru/' . ($row['litres_url'] != '' ? $row['litres_url'] . '?lfrom=' : 'pages/biblio_book/?art=' . $row['hub_id'] . '&lfrom=' ) . $partner_id;

			//жанры
			/*
			$genres = explode('|',$row['genre'],2);
			$rec_cat = $mysqli->query("SELECT local_category FROM `litres_genres_relation` WHERE local_category > 0 AND litres_token IN ('" . $genres[0] . "','" . $genres[1] . "')");

			if ($rec_cat->num_rows == 0){
				echo 'нет жанра в локальной таблице (' . $row['hub_id'] . ')';
				//делаем пометку что книгу нельзя импортнуть из-за отсутствия жанра чтобы в след раз опять не дергать эту книгу
				$q = "UPDATE `litres_data` SET 
							local_book_id_litres_catalog = '-1'
							WHERE hub_id = " . $row['hub_id'];
				$mysqli->query($q);
				continue;
			}	//если не нашли локальный жанр, то пропускаем книгу

			$r_cat = $rec_cat->fetch_array(MYSQLI_ASSOC);
			$local_categories = $r_cat['local_category'];
			*/
			$local_categories = 99;
			
			$title = stripslashes(trim($row['author_name'] . ' ' . $row['author_sname']) . ' - ' . $row['book_title']);
			
			//перекодировку не убирать!
			$alt_name = totranslit(mb_convert_encoding(stripslashes($row['book_title']),'windows-1251','UTF-8'), true, false );

			$dir_name = date("Y-m");
			@mkdir(ROOT_DIR . '/uploads/posts/' . $dir_name);
			@mkdir(ROOT_DIR . '/uploads/posts/' . $dir_name . '/thumbs');
			
			$pic_name = time() . "_" . $row['hub_id'] . '.jpg';
			
			$full_story = '<div style="text-align:center;"><!--dle_image_begin:http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '|--><img src="http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '" alt="Джейн Фэллон - Дважды два - четыре" title="' . $title . '" /><!--dle_image_end--></div><br />
			<div style="text-align:center;">' . nl2br(mb_substr($row['annotation'],0,400,'utf-8')) . '<br /><br />
			<b>Название:</b> ' . trim($row['book_title']) . '<br />
			<b>Автор:</b> ' . trim($row['author_name'] . ' ' . $row['author_sname']) . '<br />
			' . ($row['publisher'] != '' ? '<b>Издательство:</b> ' . $row['publisher'] . '<br />' : '') . '
			' . ($row['publ_year'] > 0 ? '<b>Год:</b> ' . $row['publ_year'] . '<br />' : '') . '
			<b>Формат:</b> RTF/FB2<br />
			<b>Язык:</b> Русский
			</div><br /><br />';
			/*<div style="text-align:center;"><b>Скачать ' . $title . ' [' . ($row['publ_year'] > 0 ? $row['publ_year'] . ', ' : '') . 'RTF/FB2]</b></div><br />*/
			
			
			$short_story = '<div style="text-align:center;"><!--dle_image_begin:http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '|--><img src="http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '" alt="Джейн Фэллон - Дважды два - четыре" title="' . $title . '" /><!--dle_image_end--></div><br />
			<div style="text-align:center;">' . nl2br(mb_substr($row['annotation'],0,200,'utf-8')) . '</div>';
			
			$xfields = array(); $xfields_str = '';

			if ($r['xfields'] != '') $xfields = explode_xfields($r['xfields']);
			$xfields['litres_link'] = $litres_link;
														
			if ($book_type == 0){
				$xfields['hub_id'] = $row['hub_id'];
			}
			elseif($book_type == 1){
				$xfields['hub_id_audio'] = $row['hub_id'];
			}
							
			//собираем поля xfields в кучу
			$xfields_str = implode_xfields($xfields);
							
			//создаем ключевики (функция возвращает глоб переменную $metatags['keywords'])
			//create_keywords($full_story);
			
			if ($row['local_book_id_litres_catalog'] == NULL){
				echo $q = "INSERT INTO dle_post SET
					autor = 'litres',
					date = '" . date("Y-m-d H:i:s") . "',
					short_story = '" . $mysqli->real_escape_string($short_story) . "',
					full_story = '" . $mysqli->real_escape_string($full_story) . "',
					xfields = '" . $mysqli->real_escape_string($xfields_str) . "',
					title = '" . $mysqli->real_escape_string($title) . "',
					descr  = '',
					keywords = '',
					category = '" . $local_categories . "',
					alt_name = '" . $alt_name . "',
					comm_num = 0,
					allow_comm = 1,
					allow_main = 0,
					approve = 1,
					fixed = 0,
					allow_br = 1,
					symbol = '',
					tags = '',
					metatitle = '" . $mysqli->real_escape_string($title) . "'
					";
				$mysqli->query($q);
				$local_book_id = $mysqli->insert_id;
				
				echo $q = "INSERT INTO dle_images SET
					images = '" . $dir_name . "/" . $pic_name . "',
					news_id = " . $local_book_id . ",
					author = 'litres',
					date = '" . time() . "',
					metatitle = '" . $mysqli->real_escape_string($title) . "'
					";
				$mysqli->query($q);
				
			}
			else{
				echo $q = "UPDATE dle_post SET
					short_story = '" . $mysqli->real_escape_string($short_story) . "',
					full_story = '" . $mysqli->real_escape_string($full_story) . "',
					xfields = '" . $mysqli->real_escape_string($xfields_str) . "',
					title = '" . $mysqli->real_escape_string($row['book_title']) . "',
					descr  = '',
					category = '" . $local_categories . "'
					WHERE id = " . $row['local_book_id_litres_catalog'];
				$mysqli->query($q);
				$local_book_id = $row['local_book_id_litres_catalog'];
			}
			
			echo $q = "UPDATE `litres_data` SET 
						local_book_id_litres_catalog = " . $local_book_id . "
						WHERE hub_id = " . $row['hub_id'];
			$mysqli->query($q);
		}
		else{
			$local_book_id = ($row['local_book_id_litres_catalog'] > 0 ? $row['local_book_id_litres_catalog'] : $row['local_book_id']);
		}
		
		//вставка доп данных
		$q = "SELECT * FROM dle_post_extras WHERE news_id = " . $local_book_id . " LIMIT 1";
		$res_extra = $mysqli->query($q);
		//вставляем данные только если новая книга и записи еще нет
		if ($res_extra->num_rows == 0){
			$mysqli->query("INSERT IGNORE INTO dle_post_extras (news_id,user_id) VALUES (". $local_book_id .",103342)");
		}
		
		/*$q = "INSERT INTO litres_local SET
				local_id = " . $local_book_id . ",
				hub_id = " . $row['hub_id'] . ",
				litres_link = '" . $litres_link . "'
				ON DUPLICATE KEY UPDATE
				hub_id = " . $row['hub_id'] . ",
				litres_link = '" . $litres_link . "'";
		$mysqli->query($q);*/
		
		//файлы
		//обложка
		$cover_id = $row['litres_id'];
		while (strlen($cover_id) < 8){
			$cover_id = '0' . $cover_id;
		}
		$cover_path = 'http://www.litres.ru/static/bookimages/' . $cover_id[0] . $cover_id[1] . '/' . $cover_id[2] . $cover_id[3] . '/' . $cover_id[4] . $cover_id[5] . '/' . $cover_id . '.bin.dir/' . $cover_id . '.cover.' . $row['cover_ext'];

		$new_image = new picture($cover_path);
		$new_image->imageresizewidth(240);

		$new_image->imagesave($new_image->image_type, ROOT_DIR . '/uploads/posts/' . $dir_name . '/' . $pic_name, 85);
		$new_image->imageout();
		/*
		$new_image = new picture($cover_path);
		$new_image->imageresizewidth(150);
		$new_image->imagesave($new_image->image_type, ROOT_DIR . '/uploads/posts/' . $dir_name . '/thumbs/' . $pic_name, 85);
		$new_image->imageout();
		*/
		//ждем между книгами, чтобы не было банна
		sleep(3);
	}

?>