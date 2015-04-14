<?php
	error_reporting(E_ALL);

	include "db.php";
	include "functions.php";
	
	$conn = getConnection();
	$searching = 0;
	$searchCategory = array();
	$searchFilter = "";
	
	$PAGE_SIZE = 8;
	$PAGE_BEFORE_AFTER = 3;
	$PAGE_DELEMITER = "...";
	$CURRENT_PAGE = 1;
	$SITE_URL = "http://wlu.ca/";
	$ascDesc = "ASC";
	$orderBy = "n";
	
	function pa($arr){ echo '<pre>'.print_r($arr,true).'</pre>'; }
	
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'){
		
		//pa($_POST);
		
		$searching = 1;
		if( isset($_POST) && isset($_POST['category']) && $_POST['category'] != "" ){
			$searchCategory = $_POST['category'];
		}
		
		if( isset($_POST) && isset($_POST['search']) && $_POST['search'] != "" ){
			$searchFilter = $_POST['search'];
		}
		
		if( isset($_POST) && isset($_POST['ascDesc']) && $_POST['ascDesc'] != "" ){
			if( $_POST['ascDesc'] == "Ascending" ){
				$ascDesc = "ASC";
			}else if( $_POST['ascDesc'] == "Descending" ){
				$ascDesc = "DESC";
			}else{
				$ascDesc = "ASC";
			}
		}
		
		if( isset($_POST) && isset($_POST['orderBy']) && $_POST['orderBy'] != "" ){
			if( $_POST['orderBy'] == "cd" ){ //creation date
				$orderBy = "cd";
			}else if( $_POST['orderBy'] == "lpd" ){ //publish date
				$orderBy = "lpd";
			}else{
				$orderBy = "n"; //name
			}
		}
		
		if( ! is_array($searchCategory) || sizeof($searchCategory) == 0  ){
			$searchCategory[] = "ALL";
		}
		
	}else{

		if( isset($_REQUEST) && isset($_REQUEST['page']) && $_REQUEST['page'] != "" ){
			$CURRENT_PAGE = $_REQUEST['page'];
		}else{
			$CURRENT_PAGE = 1;
		}
		
		if( isset($_REQUEST) && isset($_REQUEST['category']) && $_REQUEST['category'] != "" ){
			$searching = 1;
			$searchCategory = explode(",",$_REQUEST['category']);
		}else{
			$searchCategory[] = "ALL";
		}
		
		if( isset($_REQUEST) && isset($_REQUEST['search']) && $_REQUEST['search'] != "" ){
			$searchFilter = $_REQUEST['search'];
		}
		
		if( isset($_REQUEST) && isset($_REQUEST['ascDesc']) && $_REQUEST['ascDesc'] != "" ){
			if( $_REQUEST['ascDesc'] == "ASC" ){
				$ascDesc = "ASC";
			}else if( $_REQUEST['ascDesc'] == "DESC" ){
				$ascDesc = "DESC";
			}else{
				$ascDesc = "ASC";
			}
		}
		
		if( isset($_REQUEST) && isset($_REQUEST['orderBy']) && $_REQUEST['orderBy'] != "" ){
			if( $_REQUEST['orderBy'] == "cd" ){ //creation date
				$orderBy = "cd";
			}else if( $_REQUEST['orderBy'] == "lpd" ){ //publish date
				$orderBy = "lpd";
			}else{
				$orderBy = "n"; //name
			}
		}
	
	}
	
	$query = $conn->prepare("SELECT DISTINCT(`value`) as `category` FROM `metadata_custom` WHERE `field` = 'tags'");
	
	$categories = array();
	$categories[] = "ALL";
	if( $query->execute() ){
		while( $result = $query->fetch() ){
			if( trim($result["category"]) != ""  ){
				$categories[] = trim($result["category"]);
			}
		}
	}
	?>
	
	<link rel="stylesheet" href="css/chosen.min.css" />
	<script src="js/jquery-1.11.2.min.js"></script>
	<script src="js/chosen.jquery.min.js"></script>
	<script>
		$(function(){
			$('select[name="category[]"]').chosen();
		});
	</script>
	
	<form method="post" action="index.php">
	
	
		<select name="category[]" multiple>
		<?php
			if( sizeof($categories) > 0){
				foreach($categories as $cat){
					if( $searching ){
						echo '<option value="'.$cat.'"'.(in_array($cat,$searchCategory) ? ' selected' : '').'>'.$cat.'</option>';
					}else{
						echo '<option value="'.$cat.'">'.$cat.'</option>';
					}
				}
			}
		?>
		</select>
		<input type="text" name="search" value="<?php echo $searchFilter; ?>" placeholder="keyword to search for" />
		Order by:
		<select name="orderBy">
			<option value="n" <?php echo ($orderBy == "n") ? 'selected' : ''; ?>>Name</option>
			<option value="cd" <?php echo ($orderBy == "cd") ? 'selected' : ''; ?>>Creation Date</option>
			<option value="lpd" <?php echo ($orderBy == "lpd") ? 'selected' : ''; ?>>Last Publish Date</option>
		</select>
		<select name="ascDesc">
			<option value="Ascending" <?php echo ($ascDesc == "ASC") ? 'selected' : ''; ?>>Ascending</option>
			<option value="Descending" <?php echo ($ascDesc != "ASC") ? 'selected' : ''; ?>>Descending</option>
		</select>
		
		<input type="submit" value="Submit"/>
	</form>

	<hr />
	
	<form method="post" action="index.php">
	
	<style>
		.pageNav{ margin: 3px 3px; display: inline-block; }
		.pageNav.current{ font-weight: bold; }
		.imgContainer{ height: 270px; width: 270px; display: table-cell; text-align: center; vertical-align: middle; }
	</style>
	
	<?php
	//echo '<pre>'.print_r($categories,true).'</pre>';
	if( $searching && $searchCategory != ""){
		echo '<input type="hidden" value="'.implode(",",$searchCategory).'" />';
		
		if( $searchFilter != "" ){
			echo "<p>Search for &ldquo;".$searchFilter."&rdquo;  in: ".implode(", ",$searchCategory).'</p>';
		}else{
			echo "<p>Listing items in: ".implode(", ",$searchCategory).'</p>';
		}
		
		//$cats = "";
		if( is_array($searchCategory) && sizeof($searchCategory) == 1 ){
			/* One Category Search */
			
			$query = "SELECT 
				Count(`page_id`) as `cnt` 
				FROM 
					`metadata_custom` mdc 
				INNER JOIN 
					`page` p ON p.`id` = mdc.`page_id` 
				INNER JOIN 
					`metadata` md ON md.`id` = p.`metadata_id` 
				WHERE 
					mdc.`field` = 'tags' 
					AND 
					mdc.`value` LIKE :category
					AND 
					(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search )";
			
			$count = $conn->prepare($query);
			
			$category = $searchCategory[0];
			if( $category == "ALL" ){
				$category = "%%";
			}
			//$cats = $category;
			$count->bindParam(":category", $category);
			
		}else if(is_array($searchCategory) && sizeof($searchCategory) > 1){
			/* Multiple Category Search */

			$query = "SELECT
				Count(`page_id`) as `cnt`
				FROM (";
					for($x=0; $x < sizeof($searchCategory); $x++ ){
						
						if( $x > 0){
							$query .= " UNION DISTINCT ";
						}
						
						$category = ":cat".$x;
						$search =  ":search";
						
						$query .= " 
								SELECT 
									mdc.`page_id` 
								FROM 
									`metadata_custom` mdc 
								INNER JOIN 
									`page` p ON p.`id` = mdc.`page_id` 
								INNER JOIN 
									`metadata` md ON md.`id` = p.`metadata_id` 
								WHERE 
									mdc.`field` = 'tags' 
								AND 
									mdc.`value` LIKE ".$category."  
								AND 
									(p.`content` LIKE ".$search." OR md.`display_name` LIKE ".$search." OR md.`title` LIKE ".$search." ) ";
					}
					
			$query .= ") as gr";
			
			$count = $conn->prepare($query);
			
			for($y = 0; $y < sizeof($searchCategory); $y++){
				$var = ":cat".$y;
				$count->bindParam($var, $searchCategory[$y] );
			}
			
			//$cats = implode( ",", $searchCategory );
		}else{
			//error
			echo "Error: No Category";
			exit;
		}

		$ss = '%'.$searchFilter.'%';
		$count->bindParam(":search", $ss);
		
		
		
		if( $count->execute() ){
			
			$count = $count->fetch();
			$totalResults = $count["cnt"];

			if( $totalResults > 0 ){
				
				if( is_array($searchCategory) && sizeof($searchCategory) == 1 ){
				
				$query = 
						"SELECT 
							mdc.`page_id`,
							p.`name`,
							p.`cms_id`,
							p.`content`,
							p.`path`,
							md.`display_name`,
							md.`title`,
							md.`description`,
							md.`last_published_at`,
							md.`created_at`
						FROM 
							`metadata_custom` mdc 
						INNER JOIN 
							`page` p 
						ON
							p.`id` = mdc.`page_id`
						INNER JOIN 
							`metadata` md 
						ON
							md.`id` = p.`metadata_id`
						WHERE 
							mdc.`field` = 'tags' 
							AND 
							mdc.`value` LIKE :category
							AND	(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search) 
							
						ORDER BY
							".($orderBy == "n" ? "p.`name`" : ( $orderBy == "lpd" ? 'md.`last_published_at`' : 'md.`created_at`' ) )."
						".$ascDesc."
						LIMIT :start, :pageSize";
						
						$query = $conn->prepare($query);	
						
						$category = $searchCategory[0];
						if( $category == "ALL" ){
							$category = "%%";
						}
						
						$query->bindParam(":category", $category);
						
				}else if(is_array($searchCategory) && sizeof($searchCategory) > 1){
					
					//echo "Multiple Cats";
					//exit;
					
					$query = "SELECT 
								`page_id`,
								`name`,
								`cms_id`,
								`content`,
								`path`,
								`display_name`,
								`title`,
								`description`,
								`last_published_at`,
								`created_at`
							FROM 
							(";
							
					for($x=0; $x < sizeof($searchCategory); $x++ ){
						if( $x > 0){
							$query .= " UNION DISTINCT ";
						}
						$category = ":cat".$x;
						$query .=	" SELECT 
								mdc.`page_id`,
								p.`name`,
								p.`cms_id`,
								p.`content`,
								p.`path`,
								md.`display_name`,
								md.`title`,
								md.`description`,
								md.`last_published_at`,
								md.`created_at`
							FROM 
								`metadata_custom` mdc 
							INNER JOIN 
								`page` p 
							ON
								p.`id` = mdc.`page_id`
							INNER JOIN 
								`metadata` md 
							ON
								md.`id` = p.`metadata_id`
							WHERE 
								mdc.`field` = 'tags' 
								AND 
								mdc.`value` LIKE ".$category."
								AND	(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search)"; 
					}			

					$query.=" ) mul
							ORDER BY
								".($orderBy == "n" ? "`name`" : ( $orderBy == "lpd" ? '`last_published_at`' : '`created_at`' ) )."
							".$ascDesc."
							LIMIT :start, :pageSize";
							
					$query = $conn->prepare($query);	
					
					for($y = 0; $y < sizeof($searchCategory); $y++){
						$var = ":cat".$y;
						$query->bindParam($var, $searchCategory[$y] );
					}
							
					
					
					
				}		
						
						
							
				
				$start = ($CURRENT_PAGE-1) * $PAGE_SIZE;
				
				$query->bindParam(":start", $start, PDO::PARAM_INT);
				$ss = '%'.$searchFilter.'%';
				$query->bindParam(":search", $ss);
				$query->bindParam(":pageSize", $PAGE_SIZE, PDO::PARAM_INT);
				
				if( $query->execute() ){
					
					$to = ((($CURRENT_PAGE-1) * $PAGE_SIZE) + $query->rowCount());
					$to = ($to > $totalResults ? $totalResults : $to);
					
					echo "<p>Displaying Results: ".( ($CURRENT_PAGE-1) * $PAGE_SIZE). ' to '. $to.' of: '.$totalResults.'</p><br />';
					
					while( $row = $query->fetch() ){
						$title = utf8_encode($row["display_name"]);
						if( trim($title) == "" ){
							$title = utf8_encode($row["title"]);
						}
						
						$url = $SITE_URL.$row["path"].'.html';
						
						echo '<p><a target="_blank" href="'.$url.'">'.$title.'</a><br />';
						
					
						$pageHTML = file_get_contents($url);
						$doc = new DOMDocument();
						
						//keep the errors away in parsing
						libxml_use_internal_errors(true);
						$doc->loadHTML($pageHTML);
						libxml_clear_errors();
						$xpath = new DomXpath($doc);
						$pageImage = "";
						//use xpath selector to 
						$images = $xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' row ')]//div[contains(concat(' ',normalize-space(@class),' '),' row ')]//img");
						foreach($images as $image){
							$pageImage = array(
								'alt' => $image->getAttribute("alt"),
								'src' => $image->getAttribute("src")
							);
							break;
						}

						
						if(  strpos($pageImage["src"], "hawk-map-404.jpg") !== false){
							$pageImage = "";
						}
						
						if( $pageImage != ""){
							$trueImageURL =  'http://wlu.ca/images'.substr( $pageImage["src"], (strpos($pageImage["src"], "/images") + 7) );
							echo '<span class="imgContainer"><img style="width: 100%" src="'.$trueImageURL.'" alt="'.$pageImage["alt"].'" /></span><br />'; 
						}
						
						//echo '<pre>'.print_r($pageImages, true).'</pre>'; 
						
						
						
						//parse the html from $row["content"]
							//find string, cut from 100chars before to 100chars after,
							//strip the html entities
							//strip the html characters
							//bold the word
							//return the string
							
							$summaryLength = 250;
							$string = $row["content"];
							//use page data
							$summary = getSummary($string, $searchFilter, $summaryLength);
							if( $summary == "..."){
								//use meta-data description
								$summary = getSummary($row["description"], $searchFilter, $summaryLength);
							}
							
							if( $summary == "..."){
								//use display name
								$summary = getSummary(utf8_encode($row["display_name"]), $searchFilter, $summaryLength);
							}
							
							if( $summary == "..."){
								//use title
								$summary = getSummary(utf8_encode($row["title"]), $searchFilter, $summaryLength);
							}	
						
							echo $summary;
							
							
						echo '</p>';
					}
					
					if( $totalResults > $query->rowCount()  ){
						$totalPages = round_up($totalResults / $PAGE_SIZE);
						
						$pagingPages = array();
						//$pagingPages[] = 1;
						
						for( $x = 1; $x <= $PAGE_BEFORE_AFTER; $x++ ){
							if( $x < $totalPages ){
								$pagingPages[] = $x;
							}
						}
						
						if( ($CURRENT_PAGE - $PAGE_BEFORE_AFTER) >  ( $PAGE_BEFORE_AFTER) )  {
							$pagingPages[] = $PAGE_DELEMITER;
						}
						
						
						
						for( $x = ($CURRENT_PAGE - $PAGE_BEFORE_AFTER); $x < ($CURRENT_PAGE + $PAGE_BEFORE_AFTER);  $x++ ){
							if( $x < $totalPages && $x > 1){
								$pagingPages[] = $x;
							}
						}
						
						if( ($CURRENT_PAGE + $PAGE_BEFORE_AFTER) <  ($totalPages - $PAGE_BEFORE_AFTER) )  {
							$pagingPages[] = $PAGE_DELEMITER.'.';
						}
						
						for( $x = ($totalPages - $PAGE_BEFORE_AFTER); $x <= $totalPages; $x++ ){
							if( $x > 1 && $x <= $totalPages){
								$pagingPages[] = $x;
							}
						}
						
						$pagingPages = array_unique ( $pagingPages );
						
						echo '<div class="pageNavgation">';
							foreach( $pagingPages as $page ){
								echo '<span class="pageNav '.($page==$CURRENT_PAGE?'current':'').'">';
								if( $page != $PAGE_DELEMITER && $page != $PAGE_DELEMITER.'.' ){
									echo '<a href="?page='.$page.'&category='.implode(",",$searchCategory).'&search='.$searchFilter.'&ascDesc='.$ascDesc.'&orderBy='.$orderBy.'">'.$page.'</a>';
								}else{
									echo $PAGE_DELEMITER;
								}
								echo '</span>';
							}
						echo '</div>';
						
					}
				}
			}else{
				
				echo "No Results Found";
				
			}
		}
	}
	
	//APIFetch { curr_page , category, term, items_per_fetch }
		//API REPLY { curr_page, pages, }

?>
<hr />