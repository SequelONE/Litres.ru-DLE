<?php
	function totranslit($var, $lower = true, $punkt = true) {
		$langtranslit = array(
							'а' => 'a', 'б' => 'b', 'в' => 'v',
							'г' => 'g', 'д' => 'd', 'е' => 'e',
							'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
							'и' => 'i', 'й' => 'y', 'к' => 'k',
							'л' => 'l', 'м' => 'm', 'н' => 'n',
							'о' => 'o', 'п' => 'p', 'р' => 'r',
							'с' => 's', 'т' => 't', 'у' => 'u',
							'ф' => 'f', 'х' => 'h', 'ц' => 'c',
							'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
							'ь' => '', 'ы' => 'y', 'ъ' => '',
							'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
							"ї" => "yi", "є" => "ye",
							'А' => 'A', 'Б' => 'B', 'В' => 'V',
							'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
							'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
							'И' => 'I', 'Й' => 'Y', 'К' => 'K',
							'Л' => 'L', 'М' => 'M', 'Н' => 'N',
							'О' => 'O', 'П' => 'P', 'Р' => 'R',
							'С' => 'S', 'Т' => 'T', 'У' => 'U',
							'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
							'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
							'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
							'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
							"Ї" => "yi", "Є" => "ye",
							
							"." => ""
							);
		if ( is_array($var) ) return "";
		$var = str_replace(chr(0), '', $var);
		if (!is_array ( $langtranslit ) OR !count( $langtranslit ) ) {
			$var = trim( strip_tags( $var ) );
			if ( $punkt ) $var = preg_replace( "/[^a-z0-9\_\-.]+/mi", "", $var );
			else $var = preg_replace( "/[^a-z0-9\_\-]+/mi", "", $var );
			$var = preg_replace( '#[.]+#i', '.', $var );
			$var = str_ireplace( ".php", ".ppp", $var );
			if ( $lower ) $var = strtolower( $var );
			return $var;
		}
		
		$var = trim( strip_tags( $var ) );
		$var = preg_replace( "/\s+/ms", "-", $var );
		$var = str_replace( "/", "-", $var );
		$var = strtr($var, $langtranslit);
		if ( $punkt ) $var = preg_replace( "/[^a-z0-9\_\-.]+/mi", "", $var );
		else $var = preg_replace( "/[^a-z0-9\_\-]+/mi", "", $var );
		$var = preg_replace( '#[\-]+#i', '-', $var );
		$var = preg_replace( '#[.]+#i', '.', $var );
		if ( $lower ) $var = strtolower( $var );
		$var = str_ireplace( ".php", "", $var );
		$var = str_ireplace( ".php", ".ppp", $var );
		
		if( strlen( $var ) > 200 ) {
			
			$var = substr( $var, 0, 200 );
			
			if( ($temp_max = strrpos( $var, '-' )) ) $var = substr( $var, 0, $temp_max );
		
		}
		return $var;
	}

?>