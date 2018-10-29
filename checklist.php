<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<h3>НЕСОВПАДЕНИЯ</h3>
<?php
	
	include("config.php");
	
	$q = "SELECT count(*) AS c FROM `dle_post`";
	$result = $mysqli->query($q);
	$row = $result->fetch_array(MYSQLI_ASSOC);
	$total_count = $row['c'];
	
	$q = "SELECT id FROM `dle_post`
			LEFT JOIN litres_local_data USING (id)
			WHERE
				litresed > 0
			";
	$result = $mysqli->query($q);
	$litresed_count = $result->num_rows;
	
	while ($row = $result->fetch_array(MYSQLI_ASSOC)){
		$ids[] = $row['id'];
	}
	$ids = implode(',',$ids);
	
	$q = "SELECT * FROM `dle_post`
			WHERE
				id NOT IN (" . $ids . ")

			ORDER BY title";
	$result = $mysqli->query($q);
	
	echo 'В базе: ' . $total_count . ' | ' . 'Совпало: ' . $litresed_count . ' | ' . 'Не совпало: ' . ($total_count - $litresed_count) . "<br><br>"; 
	
	
	while ($row = $result->fetch_array(MYSQLI_ASSOC)){

		$author = '';
		
		$needle = 'Автор';
				
		if (preg_match("/" . $needle . "(.{3,})</iuU",$row['full_story'],$matches) !== false){
			$matches[1] = strip_tags($matches[1]);
			$matches[1] = str_replace(':','',$matches[1]);
			$matches[1] = stripslashes($matches[1]);
			$matches[1] = trim($matches[1]);
			$author = $matches[1];
		}

		echo $row['id'] . '|' . $row['title'] . '|' . $author . "<br>";
	}
?>

</body>
</html>