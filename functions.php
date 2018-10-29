<?php
	
	//ставим 1251 локаль т.к. внешние csv в 1251
	@setlocale(LC_ALL, array("Russian_Russia.1251","ru_RU.CP1251","ru_RU.cp1251","ru_RU","RU","rus_RUS.1251"));
	
	function collect_stats(){

		global $table_prefix, $partner_id, $partner_domain, $partner_contact, $partner_pass;
		
		$data = array();
		
		//id (lfrom) площадки
		$data['partner_id'] = $partner_id;
		
		//домен партнера
		$data['partner_domain'] = $partner_domain;
		
		//контакт партнера
		$data['partner_contact'] = $partner_contact;
		
		//подпись
		$data['partner_hash'] = md5($partner_pass);
		
		//время запуска
		$data['timestamp'] = time();
		
		//перебираем типы материалов
		//0-книги, 1-аудиокниги, 4 - pdf-книги, 11 - книги на английском, 12 - бумажные книги
		$types_array = array(0,1,4,11,12);
		foreach ($types_array as $type){
			//кол-во записей в таблице литресных данных
			$q = "SELECT hub_id FROM litres_data WHERE options&2 AND type = " . $type;
			$res = $mysqli->query($q);
			$data['litres_data_count_' . $type] = $res->num_rows;
			
			//время последнего обновления таблицы литресных данных (по полю updated)
			$q = "SELECT MAX(`updated`) AS updated FROM litres_data WHERE type = " . $type;
			$res = $mysqli->query($q);
			$row = $res->fetch_array(MYSQLI_ASSOC);
			$data['litres_data_updated_' . $type] = strtotime($row['updated']);
		}
		
		//кол-во локальных книг
		$q = "SELECT id FROM " . $table_prefix . "post";
		$res = $mysqli->query($q);
		$data['local_data_count'] = $res->num_rows;
		
		//кол-во совпавших с литрес книг
		$q = "SELECT id FROM litres_local_data WHERE litresed = 1";
		$res = $mysqli->query($q);
		$data['litresed_count'] = $res->num_rows;
		
		return $data;

	}
	
	
	function top_sales_hub_ids($url){
		$ids = array();
		
		$s = file_get_contents($url);
		$xml = simplexml_load_string($s);

		foreach ($xml->shop->offers->offer as $value){
			$ids[] = ((int)$value->attributes()->id);
		}
		
		return $ids;		
	}
	
	function file_get_contents_curl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
	}
	
	function file_put_contents_curl($url,$post_data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
	}
	
	function multineedle_stripos($haystack, $needles, $offset=0){
		foreach($needles as $needle) {
			if (stripos($haystack, $needle, $offset) !== false){
				$found[$needle] = stripos($haystack, $needle, $offset);
			}
		}
		return (isset($found) ? $found : false);
	}
	
	function explode_xfields($data){
		//преобразует строку xfields в двумерный массив
		$xfields_array = explode('||',$data);
		foreach ($xfields_array as $xfield){
			$xfield_array = explode('|',$xfield);
			$xfields[$xfield_array[0]] = $xfield_array[1];
		}
		return is_array($xfields) ? $xfields : false;
	}

	function implode_xfields($data){
		//преобразует двумерный массив в строку xfields
		foreach ($data as $key => $value){
			$xfields[] = $key . '|' . $value;
		}
		
		if (!is_array($xfields)) return false;
		
		$xfields_string = implode('||',$xfields);
		
		return $xfields_string;
	}
	
	function delete_litres_book($hub_id){
		//удаляем книги путем простановки options=0
		$q = "UPDATE `litres_data` SET options=0 WHERE hub_id=" . $hub_id;
		$mysqli->query($q);
	}
	
	function index_local_data(){
		//функция нужна для правильного полнотекстого индексирования.
		//используется если клиентские данные в кодировке 1251

		global $table_prefix;
		
		//$q = "TRUNCATE TABLE litres_local_data";
		//$mysqli->query($q);

		$q = "SELECT * FROM `" . $table_prefix . "post`";

		$result = $mysqli->query($q);

		if ($result->num_rows > 0){
			while ($row = $result->fetch_array(MYSQLI_ASSOC)){
				echo $row['id'] . "\r\n";
				//$xfields = '';
				//if ($row['xfields'] != '') $xfields = explode_xfields($row['xfields']);

				$matches = ''; $author = ''; $title = '';
				
				
				$needle = 'Автор(<\/b>)?\:';
				
				if (preg_match("/" . $needle . "(.{3,})</iuU",$row['full_story'],$matches)){
					$matches[2] = strip_tags($matches[2]);
					$matches[2] = str_replace(':','',$matches[2]);
					$matches[2] = stripslashes($matches[2]);
					$matches[2] = trim($matches[2]);
					$author = $matches[2];
				}
				else{
					$author = str_ireplace('</p><p>','</p> <p>',$row['full_story']);
					//$author = strip_tags($author);
				}

				/*
				$matches = '';
				if (preg_match("/Название(.{3,})</iuU",$row['full_story'],$matches) !== false){
					$matches[1] = strip_tags($matches[1]);
					$matches[1] = str_replace(':','',$matches[1]);
					$matches[1] = stripslashes($matches[1]);
					$matches[1] = trim($matches[1]);
					$title = $matches[1];
					var_dump($title);
				}
				*/
				
				$title = $row['title'];
				
				//локальные категории:
				$categories_audio = array(37,55,56,57,58,59,60);
				$categories_text = array();
				
				$categories = explode(',',$row['category']);
				foreach ($categories as $category){
					if (in_array($category,$categories_audio)){
						$local_book_type = 1;
						continue;
					}
					else{
						$local_book_type = 0;
					}
				}
				
				$q = "INSERT INTO litres_local_data SET 
						id = " . $row['id'] . ",
						author = '" . $mysqli->real_escape_string($author) . "',
						title = '" . $mysqli->real_escape_string($title) . "',
						type = " . $local_book_type . ",
						litresed = " . (isset($xfields['litres']) && $xfields['litres'] != '' ? 1 : 0) . "
						ON DUPLICATE KEY UPDATE
						author = '" . $mysqli->real_escape_string($author) . "',
						title = '" . $mysqli->real_escape_string($title) . "',
						type = " . $local_book_type . "";
				$mysqli->query($q);
			}
		}
	}
	
	function compare_local_global($book_type = 0){

		global $partner_id, $table_prefix;

		include('dictionary.php');
		
		//находим максимальный Id в `litres_local_data` чтобы проверять только хвост
		//ночью проверяем полные базы
		$max_local_book_id = 0;
		if (date("H") > 5){
			$q = "SELECT MAX(id) AS max_id FROM `litres_local_data`";
			$res_m = $mysqli->query($q);
			$r_m = $res_m->fetch_array(MYSQLI_ASSOC);
			$max_local_book_id = $r_m['max_id'];
		}
		//------------------------

		$q = "SELECT * FROM `litres_data` WHERE 
					`type` = " . $book_type . "
					AND hub_id > 0
					AND (options&2 OR can_preorder = 1)

					ORDER BY updated DESC

				";

		$result = $mysqli->query($q);

		if ($result->num_rows > 0){

			while ($row = $result->fetch_array(MYSQLI_ASSOC)){
				
				$litres_link = ''; $book_title_t = ''; $book_title_1 = ''; $book_title_2 = '';
				
				if (mb_strlen($row['author_sname'],'utf8') > 2 && mb_strlen($row['book_title'],'utf8') > 2){
					$row['book_title'] = trim(strtr($row['book_title'], $repl_ar));
					
					if (stripos($row['book_title'],'.') !== false){
						$book_title_t = explode('.',$row['book_title']);
						foreach ($book_title_t as $key => $value){
							$value = trim($value);
							if (mb_strlen($value,'utf8') < 2) $book_title_t[$key] = '';
						}
						$book_title_t = array_values(array_diff($book_title_t, array('')));
						$book_title_1 = trim($book_title_t[0]);
						//if (end($book_title_t) && count($book_title_t) > 1) $book_title_2 = trim(end($book_title_t));
						//if (count($book_title_t) > 1) $book_title_2 = trim($book_title_t[1]);
					}
					elseif (stripos($row['book_title'],':') !== false){
						$book_title_t = explode(':',$row['book_title']);
						foreach ($book_title_t as $key => $value){
							$value = trim($value);
							if (mb_strlen($value,'utf8') < 2) $book_title_t[$key] = '';
						}
						$book_title_t = array_diff($book_title_t, array(''));
						$book_title_1 = trim($book_title_t[0]);

						//if (end($book_title_t) && count($book_title_t) > 1) $book_title_2 = trim(end($book_title_t));
					}
					elseif (stripos($row['book_title'],'!') !== false){
						$book_title_t = explode('!',$row['book_title']);
						foreach ($book_title_t as $key => $value){
							$value = trim($value);
							if (mb_strlen($value,'utf8') < 2) $book_title_t[$key] = '';
						}
						$book_title_t = array_diff($book_title_t, array(''));
						$book_title_1 = trim($book_title_t[0]);

						//if (end($book_title_t) && count($book_title_t) > 1) $book_title_2 = trim(end($book_title_t));
					}
					else{
						$book_title_1 = $row['book_title'];
					}
					
					if (mb_strlen($book_title_1,'utf8') < 5) $book_title_1 = $row['book_title'];
					
					//для автора "Коллектив авторов" используем полное название, иначе много левых совпадений
					if (stripos($row['author_name'],'Коллектив') !== false || stripos($row['author_sname'],'Коллектив') !== false) $book_title_1 = $row['book_title'];

					//escape
					$book_title_1 = $mysqli->real_escape_string($book_title_1);
					$book_title_2 = $mysqli->real_escape_string($book_title_2);
					$row['author_sname'] = $mysqli->real_escape_string($row['author_sname']);
					$row['author_name'] = $mysqli->real_escape_string($row['author_name']);
					$row['second_author_sname'] = $mysqli->real_escape_string($row['second_author_sname']);
					
					$q = "SELECT litres_local_data.*, " . $table_prefix . "post.xfields, " . $table_prefix . "post.full_story
							FROM litres_local_data
							JOIN dle_post USING (id)
							WHERE
							litresed = 0
							AND litres_local_data.id > " . ($max_local_book_id - 10000) . "
							AND litres_local_data.type = " . ($book_type == 1 ? "1" : "0") . "
							AND
							(
							" . (mb_strlen($book_title_1,'utf8') > 4 ? "MATCH(litres_local_data.title) AGAINST ('\"" . $book_title_1 . "\"' IN BOOLEAN MODE)" : "litres_local_data.title RLIKE '[[:<:]]" . $book_title_1 . "[[:>:]]'") . "
							" . (mb_strlen($book_title_2,'utf8') > 6 ? " OR MATCH(litres_local_data.title) AGAINST ('\"" . $book_title_2 . "\"' IN BOOLEAN MODE)" : "") . "
							
							)
							AND
							(
								" . (mb_strlen($row['author_sname'],'utf8') > 3 ? "MATCH(litres_local_data.author) AGAINST ('\"" . $row['author_sname'] . "\"' IN BOOLEAN MODE)" : "litres_local_data.author like '%" . $row['author_sname'] . "%'") . "
								" .
								(
								$row['second_author_sname'] != '' ?
								"OR
									(MATCH(litres_local_data.author) AGAINST ('\"" . $row['second_author_sname'] . "\"' IN BOOLEAN MODE))
									" 
								: ""						
								)
							. "
							)
						";
					$res = $mysqli->query($q);

					if ($res->num_rows > 0){
						while ($r = $res->fetch_array(MYSQLI_ASSOC)){
						
							//создаем бекап записи в таблице `dle_post_original`
							$q = "INSERT INTO dle_post_original (SELECT * FROM dle_post WHERE id = " . $r['id'] . ")";
							$mysqli->query($q);
						
							$litres_link = 'https://www.litres.ru/' . ($row['litres_url'] != '' ? $row['litres_url'] . '?lfrom=' : 'pages/biblio_book/?art=' . $row['hub_id'] . '&lfrom=' ) . $partner_id;
							
							$q = "UPDATE `litres_local_data` SET
									litresed = 1
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							
							$q = "UPDATE `litres_data` SET
								local_book_id = " . $r['id'] . "
								WHERE hub_id = " . $row['hub_id'];
							$mysqli->query($q);
							
							
							$xfields = '';
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
							
							echo $q = "UPDATE `dle_post` SET
								xfields = '" . $mysqli->real_escape_string($xfields_str) . "'
								WHERE id = " . $r['id'];
							echo "<br><br>";
							$mysqli->query($q);
							
							
							//вырезаем ссылки на скачивание из текста полной новости
							if (stripos($r['full_story'],'<!--QuoteBegin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--QuoteBegin--\>.+\<\!--QuoteEEnd--\>/i","",$r['full_story']);
							}
										
							if (stripos($r['full_story'],'<!--dle_leech_begin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--dle_leech_begin--\>.+\<\!--dle_leech_end--\>/i","",$r['full_story']);
							}
								
							$q = "UPDATE `dle_post` SET
									full_story = '" . $mysqli->real_escape_string($r['full_story']) . "'
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							//-----------------------------------------------------
						}
					}
					$res->free;
				}
				echo ($k++)."\r\n";
			}
		}
		
		//убираем служебный индекс
		//$q = "ALTER TABLE `" . $table_prefix . "post` DROP INDEX `full_story`";
		//$mysqli->query($q);
		//-------------------------
		
		return true;
		
	}
	
	function compare_local_global_by_sequnces($book_type = 0){
		//проверка по фамилии автора и названию серии
		
		global $partner_id, $table_prefix;

		include('dictionary.php');
		
		//находим максимальный Id в `litres_local_data` чтобы проверять только хвост
		$q = "SELECT MAX(id) AS max_id FROM `litres_local_data`";
		$res_m = $mysqli->query($q);
		$r_m = $res_m->fetch_array(MYSQLI_ASSOC);
		$max_local_book_id = $r_m['max_id'];
		//------------------------
		
		$q = "SELECT * FROM `litres_data` WHERE 
					`type` = " . $book_type . "
					AND sequences != ''
					AND hub_id > 0
					AND (options&2 OR can_preorder = 1)

					GROUP BY sequences, author_sname
				";

		$result = $mysqli->query($q);

		if ($result->num_rows > 0){

			while ($row = $result->fetch_array(MYSQLI_ASSOC)){
				
				$litres_link = ''; $seq_title_t = ''; $seq_title_1 = ''; $seq_title_2 = '';
				
				$row['sequences'] = preg_replace("/\\s*\\([^()]*\\)\\s*/is","",$row['sequences']);

				if (mb_strlen($row['author_sname'],'utf8') > 3 && mb_strlen($row['sequences'],'utf8') > 3){
					
					$row['sequences'] = trim(strtr($row['sequences'], $repl_seq_ar));
					
					if (0 && stripos($row['sequences'],'.') !== false){
						$seq_title_t = explode('.',$row['sequences']);
						foreach ($seq_title_t as $key => $value){
							$value = trim($value);
							if (mb_strlen($value,'utf8') < 2) $seq_title_t[$key] = '';
						}
						$seq_title_t = array_values(array_diff($seq_title_t, array('')));
						$seq_title_1 = trim($seq_title_t[0]);
					}
					else{
						$seq_title_1 = $row['sequences'];
					}
					
					if (mb_strlen($seq_title_1,'utf8') < 3) $seq_title_1 = $row['sequences'];

					//escape
					$seq_title_1 = $mysqli->real_escape_string($seq_title_1);
					$row['author_sname'] = $mysqli->real_escape_string($row['author_sname']);

					$q = "SELECT litres_local_data.*, " . $table_prefix . "post.xfields, " . $table_prefix . "post.full_story
							FROM litres_local_data
							JOIN dle_post USING (id)
							WHERE
							litresed = 0
							AND litres_local_data.id > " . ($max_local_book_id - 10000) . "
							AND litres_local_data.type = " . ($book_type == 1 ? "1" : "0") . "
							AND
							(
							MATCH(litres_local_data.title) AGAINST ('\"" . $seq_title_1 . "\"' IN BOOLEAN MODE) OR litres_local_data.title like '%" . $seq_title_1 . "%'
							)
							AND
							(
								" . (mb_strlen($row['author_sname'],'utf8') > 3 ? "MATCH(litres_local_data.author) AGAINST ('\"" . $row['author_sname'] . "\"' IN BOOLEAN MODE)" : "litres_local_data.author like '%" . $row['author_sname'] . "%'") . "
							)
						";
					$res = $mysqli->query($q);

					if ($res->num_rows > 0){
						while ($r = $res->fetch_array(MYSQLI_ASSOC)){
						
							//создаем бекап записи в таблице `dle_post_original`
							$q = "INSERT INTO dle_post_original (SELECT * FROM dle_post WHERE id = " . $r['id'] . ")";
							$mysqli->query($q);
						
							$litres_link = 'https://www.litres.ru/' . ($row['litres_url'] != '' ? $row['litres_url'] . '?lfrom=' : 'pages/biblio_book/?art=' . $row['hub_id'] . '&lfrom=' ) . $partner_id;
							
							$q = "UPDATE `litres_local_data` SET
									litresed = 1
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							
	
							$xfields = '';
							if ($r['xfields'] != '') $xfields = explode_xfields($r['xfields']);
							$xfields['litres_link'] = $litres_link;
							
							/*							
							if ($book_type == 0){
								$xfields['hub_id'] = $row['hub_id'];
							}
							elseif($book_type == 1){
								$xfields['hub_id_audio'] = $row['hub_id'];
							}
							*/
							
							//собираем поля xfields в кучу
							$xfields_str = implode_xfields($xfields);
							
							echo $q = "UPDATE `dle_post` SET
								xfields = '" . $mysqli->real_escape_string($xfields_str) . "'
								WHERE id = " . $r['id'];
							echo "<br><br>";
							$mysqli->query($q);
							
							
							//вырезаем ссылки на скачивание из текста полной новости
							if (stripos($r['full_story'],'<!--QuoteBegin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--QuoteBegin--\>.+\<\!--QuoteEEnd--\>/i","",$r['full_story']);
							}
										
							if (stripos($r['full_story'],'<!--dle_leech_begin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--dle_leech_begin--\>.+\<\!--dle_leech_end--\>/i","",$r['full_story']);
							}
								
							$q = "UPDATE `dle_post` SET
									full_story = '" . $mysqli->real_escape_string($r['full_story']) . "'
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							//-----------------------------------------------------
						}
					}
					$res->free;
				}
				echo ($k++)."\r\n";
			}
		}
		
		return true;
		
	}
	
	function compare_local_global_by_collections($book_type = 0){

		global $partner_id, $table_prefix;
		
		//находим максимальный Id в `litres_local_data` чтобы проверять только хвост
		$q = "SELECT MAX(id) AS max_id FROM `litres_local_data`";
		$res_m = $mysqli->query($q);
		$r_m = $res_m->fetch_array(MYSQLI_ASSOC);
		$max_local_book_id = $r_m['max_id'];
		//------------------------

		$q = "SELECT * FROM `litres_data` WHERE 
					`type` = " . $book_type . "
					AND hub_id > 0
					AND (options&2 OR can_preorder = 1)
					AND author_sname != 'Сборник'
					GROUP BY hub_author_id
				";

		$result = $mysqli->query($q);

		if ($result->num_rows > 0){

			while ($row = $result->fetch_array(MYSQLI_ASSOC)){
				
				$litres_link = '';
				
				if (mb_strlen($row['author_sname'],'utf8') > 3){
					
					//escape
					$row['author_sname'] = $mysqli->real_escape_string($row['author_sname']);
					$row['author_name'] = $mysqli->real_escape_string($row['author_name']);
					
					$q = "SELECT litres_local_data.*, " . $table_prefix . "post.xfields, " . $table_prefix . "post.full_story
							FROM litres_local_data
							JOIN dle_post USING (id)
							WHERE
							litresed = 0
							AND litres_local_data.id > " . ($max_local_book_id - 10000) . "
							AND litres_local_data.type = " . ($book_type == 1 ? "1" : "0") . "
							AND
							(
							MATCH(litres_local_data.title) AGAINST ('\"сборник\"' IN BOOLEAN MODE)
							OR
							MATCH(litres_local_data.title) AGAINST ('\"собрание\"' IN BOOLEAN MODE)
							OR
							MATCH(litres_local_data.title) AGAINST ('\"книги\"' IN BOOLEAN MODE)
							OR
							MATCH(litres_local_data.title) AGAINST ('\"книг\"' IN BOOLEAN MODE)
							)
							AND
							(
								" . ((mb_strlen($row['author_sname'],'utf8') > 3) ? "MATCH(litres_local_data.author) AGAINST ('\"" . $row['author_sname'] . "\"' IN BOOLEAN MODE) " : "litres_local_data.author like '%" . $row['author_sname'] . "%' ") . "
								
							)
						";
					$res = $mysqli->query($q);

					if ($res->num_rows > 0){
						while ($r = $res->fetch_array(MYSQLI_ASSOC)){
						
							//создаем бекап записи в таблице `dle_post_original`
							$q = "INSERT INTO dle_post_original (SELECT * FROM dle_post WHERE id = " . $r['id'] . ")";
							$mysqli->query($q);
						
							$litres_link = 'https://www.litres.ru/' . ($row['litres_a_url'] != '' ? $row['litres_a_url'] . '?lfrom=' : 'pages/biblio_authors/?subject=' . $row['hub_author_id'] . '&lfrom=' ) . $partner_id;
							
							$q = "UPDATE `litres_local_data` SET
									litresed = 1
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							
							
							$xfields = '';
							if ($r['xfields'] != '') $xfields = explode_xfields($r['xfields']);
							$xfields['litres_link'] = $litres_link;
							
							/*
							if ($book_type == 0){
								$xfields['hub_id'] = $row['hub_id'];
							}
							elseif($book_type == 1){
								$xfields['hub_id_audio'] = $row['hub_id'];
							}
							*/
							
							//собираем поля xfields в кучу
							$xfields_str = implode_xfields($xfields);
							
							$q = "UPDATE `dle_post` SET
								xfields = '" . $mysqli->real_escape_string($xfields_str) . "'
								WHERE id = " . $r['id'];
							$mysqli->query($q);
							
							
							//вырезаем ссылки на скачивание из текста полной новости
							if (stripos($r['full_story'],'<!--QuoteBegin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--QuoteBegin--\>.+\<\!--QuoteEEnd--\>/i","",$r['full_story']);
							}
										
							if (stripos($r['full_story'],'<!--dle_leech_begin-->') !== false){
								$r['full_story'] = preg_replace("/\<\!--dle_leech_begin--\>.+\<\!--dle_leech_end--\>/i","",$r['full_story']);
							}
								
							$q = "UPDATE `dle_post` SET
									full_story = '" . $mysqli->real_escape_string($r['full_story']) . "'
									WHERE id = " . $r['id'];
							$mysqli->query($q);
							//-----------------------------------------------------
						}
					}
					$res->free;
				}
				echo ($k++)."\r\n";
			}
		}
		
		return true;
		
	}
	
	function compare_local_global_magazines(){

		global $partner_id, $table_prefix;
		
		//массив журналов, составляется вручную
		$magazines = array(
			'Playboy' 			=> 'https://www.litres.ru/serii-knig/zhurnal-playboy/',
			'Burda'				=> 'https://www.litres.ru/serii-knig/zhurnal-burda/',
			'Chip'				=> 'https://www.litres.ru/serii-knig/zhurnal-chip/',
			'Quattroruote'		=> 'https://www.litres.ru/serii-knig/zhurnal-quattroruote/',
			'Salon de Luxe'		=> 'https://www.litres.ru/serii-knig/zhurnal-salon-de-luxe/',
			'SALON-interior'	=> 'https://www.litres.ru/serii-knig/zhurnal-salon-de-luxe/',
			'Verena'			=> 'https://www.litres.ru/serii-knig/zhurnal-verena/',
			'Автомир'			=> 'https://www.litres.ru/serii-knig/zhurnal-avtomir/',
			'Добрые советы'		=> 'https://www.litres.ru/serii-knig/zhurnal-dobrye-sovety/',
			'Идеи Вашего Дома'	=> 'https://www.litres.ru/serii-knig/zhurnal-idei-vashego-doma-specvypusk/',
			'Моё любимое хобби'	=> 'https://www.litres.ru/burda/',
			'Мой прекрасный сад'	=> 'https://www.litres.ru/serii-knig/zhurnal-moy-prekrasnyy-sad/',
			'Мой ребенок'		=> 'https://www.litres.ru/serii-knig/zhurnal-moy-rebenok/',
			'Отдохни'			=> 'https://www.litres.ru/serii-knig/zhurnal-otdohni/',
			'Сабрина'			=> 'https://www.litres.ru/serii-knig/zhurnal-sabrina/',
			'Лиза'				=> 'https://www.litres.ru/serii-knig/zhurnal-liza/'
			
		);

		foreach ($magazines as $mag_title => $link){
				
					
			//escape
			$mag_title = $mysqli->real_escape_string($mag_title);

			$q = "SELECT litres_local_data.*, " . $table_prefix . "post.xfields, " . $table_prefix . "post.full_story
					FROM litres_local_data
					JOIN dle_post USING (id)
					WHERE
					litresed = 0
					AND litres_local_data.type = 2
					AND 
						MATCH(litres_local_data.title) AGAINST ('\"" . $mag_title . "\"' IN BOOLEAN MODE)
					";
			$res = $mysqli->query($q);

			if ($res->num_rows > 0){
				while ($r = $res->fetch_array(MYSQLI_ASSOC)){
					
					$litres_link = '';
					
					//создаем бекап записи в таблице `dle_post_original`
					$q = "INSERT INTO dle_post_original (SELECT * FROM dle_post WHERE id = " . $r['id'] . ")";
					$mysqli->query($q);
						
					$litres_link = $link . '?lfrom=' . $partner_id;
							
					$q = "UPDATE `litres_local_data` SET
							litresed = 1
							WHERE id = " . $r['id'];
					$mysqli->query($q);
							
					$xfields = '';
					if ($r['xfields'] != '') $xfields = explode_xfields($r['xfields']);
					$xfields['litres_link'] = $litres_link;
							
					//собираем поля xfields в кучу
					$xfields_str = implode_xfields($xfields);
					
					$q = "UPDATE `dle_post` SET
							xfields = '" . $mysqli->real_escape_string($xfields_str) . "'
							WHERE id = " . $r['id'];
					$mysqli->query($q);
							
							
					//вырезаем ссылки на скачивание из текста полной новости
					if (stripos($r['full_story'],'<!--QuoteBegin-->') !== false){
						$r['full_story'] = preg_replace("/\<\!--QuoteBegin--\>.+\<\!--QuoteEEnd--\>/i","",$r['full_story']);
					}
										
					if (stripos($r['full_story'],'<!--dle_leech_begin-->') !== false){
						$r['full_story'] = preg_replace("/\<\!--dle_leech_begin--\>.+\<\!--dle_leech_end--\>/i","",$r['full_story']);
					}
								
					$q = "UPDATE `dle_post` SET
							full_story = '" . $mysqli->real_escape_string($r['full_story']) . "'
							WHERE id = " . $r['id'];
					$mysqli->query($q);
					//-----------------------------------------------------
				}
			}
		}
		
		return true;
		
	}
	
	class picture {
	     
	    private $image_file;
	     
	    public $image;
	    public $image_type;
	    public $image_width;
	    public $image_height;
	     
	     
	    public function __construct($image_file) {
	        $this->image_file=$image_file;
	        $image_info = getimagesize($this->image_file);
	        $this->image_width = $image_info[0];
	        $this->image_height = $image_info[1];
	        switch($image_info[2]) {
	            case 1: $this->image_type = 'gif'; break;//1: IMAGETYPE_GIF
	            case 2: $this->image_type = 'jpeg'; break;//2: IMAGETYPE_JPEG
	            case 3: $this->image_type = 'png'; break;//3: IMAGETYPE_PNG
	            case 4: $this->image_type = 'swf'; break;//4: IMAGETYPE_SWF
	            case 5: $this->image_type = 'psd'; break;//5: IMAGETYPE_PSD
	            case 6: $this->image_type = 'bmp'; break;//6: IMAGETYPE_BMP
	            case 7: $this->image_type = 'tiffi'; break;//7: IMAGETYPE_TIFF_II (порядок байт intel)
	            case 8: $this->image_type = 'tiffm'; break;//8: IMAGETYPE_TIFF_MM (порядок байт motorola)
	            case 9: $this->image_type = 'jpc'; break;//9: IMAGETYPE_JPC
	            case 10: $this->image_type = 'jp2'; break;//10: IMAGETYPE_JP2
	            case 11: $this->image_type = 'jpx'; break;//11: IMAGETYPE_JPX
	            case 12: $this->image_type = 'jb2'; break;//12: IMAGETYPE_JB2
	            case 13: $this->image_type = 'swc'; break;//13: IMAGETYPE_SWC
	            case 14: $this->image_type = 'iff'; break;//14: IMAGETYPE_IFF
	            case 15: $this->image_type = 'wbmp'; break;//15: IMAGETYPE_WBMP
	            case 16: $this->image_type = 'xbm'; break;//16: IMAGETYPE_XBM
	            case 17: $this->image_type = 'ico'; break;//17: IMAGETYPE_ICO
	            default: $this->image_type = ''; break;
	        }
	        $this->fotoimage();
	    }
	     
	    private function fotoimage() {
	        switch($this->image_type) {
	            case 'gif': $this->image = imagecreatefromgif($this->image_file); break;
	            case 'jpeg': $this->image = imagecreatefromjpeg($this->image_file); break;
	            case 'png': $this->image = imagecreatefrompng($this->image_file); break;
	        }
	    }
	     
	    public function autoimageresize($new_w, $new_h) {
	        $difference_w = 0;
	        $difference_h = 0;
	        if($this->image_width < $new_w && $this->image_height < $new_h) {
	            $this->imageresize($this->image_width, $this->image_height);
	        }
	        else {
	            if($this->image_width > $new_w) {
	                $difference_w = $this->image_width - $new_w;
	            }
	            if($this->image_height > $new_h) {
	                $difference_h = $this->image_height - $new_h;
	            }
	                if($difference_w > $difference_h) {
	                    $this->imageresizewidth($new_w);
	                }
	                elseif($difference_w < $difference_h) {
	                    $this->imageresizeheight($new_h);
	                }
	                else {
	                    $this->imageresize($new_w, $new_h);
	                }
	        }
	    }
	     
	    public function percentimagereduce($percent) {
	        $new_w = $this->image_width * $percent / 100;
	        $new_h = $this->image_height * $percent / 100;
	        $this->imageresize($new_w, $new_h);
	    }
	     
	    public function imageresizewidth($new_w) {
	        $new_h = $this->image_height * ($new_w / $this->image_width);
	        $this->imageresize($new_w, $new_h);
	    }
	     
	    public function imageresizeheight($new_h) {
	        $new_w = $this->image_width * ($new_h / $this->image_height);
	        $this->imageresize($new_w, $new_h);
	    }
	     
	    public function imageresize($new_w, $new_h) {
	        $new_image = imagecreatetruecolor($new_w, $new_h);
	        imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $new_w, $new_h, $this->image_width, $this->image_height);
	        $this->image_width = $new_w;
	        $this->image_height = $new_h;
	        $this->image = $new_image;
	    }
	     
	    public function imagesave($image_type='jpeg', $image_file=NULL, $image_compress=100, $image_permiss='') {
	        if($image_file==NULL) {
	            switch($this->image_type) {
	                case 'gif': header("Content-type: image/gif"); break;
	                case 'jpeg': header("Content-type: image/jpeg"); break;
	                case 'png': header("Content-type: image/png"); break;
	            }
	        }
	        switch($this->image_type) {
	            case 'gif': imagegif($this->image, $image_file); break;
	            case 'jpeg': imagejpeg($this->image, $image_file, $image_compress); break;
	            case 'png': imagepng($this->image, $image_file); break;
	        }
	        if($image_permiss != '') {
	            chmod($image_file, $image_permiss);
	        }
	    }
	     
	    public function imageout() {
	        imagedestroy($this->image);
	    }
	     
	    public function __destruct() {
	         
	    }
	     
	}
	
?>