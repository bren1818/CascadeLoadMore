<?php
	error_reporting(E_ALL);

	include "db.php";
	include "functions.php";
	
	$conn = getConnection();
	$searching = 0;
	$searchCategory = "";
	$searchFilter = "";
	
	$PAGE_SIZE = 8;
	$PAGE_BEFORE_AFTER = 3;
	$PAGE_DELEMITER = "...";
	$CURRENT_PAGE = 1;
	$SITE_URL = "http://wlu.ca/";
	
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'){
		$searching = 1;
		if( isset($_POST) && isset($_POST['category']) && $_POST['category'] != "" ){
			$searchCategory = $_POST['category'];
		}
		
		if( isset($_POST) && isset($_POST['search']) && $_POST['search'] != "" ){
			$searchFilter = $_POST['search'];
		}
	}


	if( isset($_REQUEST) && isset($_REQUEST['page']) && $_REQUEST['page'] != "" ){
		$CURRENT_PAGE = $_REQUEST['page'];
	}else{
		$CURRENT_PAGE = 1;
	}
	
	if( isset($_REQUEST) && isset($_REQUEST['category']) && $_REQUEST['category'] != "" ){
		$searching = 1;
		$searchCategory = $_REQUEST['category'];
	}
	
	if( isset($_REQUEST) && isset($_REQUEST['search']) && $_REQUEST['search'] != "" ){
		$searchFilter = $_REQUEST['search'];
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
	<form method="post" action="index.php">
		<select name="category">
		<?php
			if( sizeof($categories) > 0){
				foreach($categories as $cat){
					if( $searching ){
						echo '<option value="'.$cat.'"'.($cat==$searchCategory? ' selected' : '').'>'.$cat.'</option>';
					}else{
						echo '<option value="'.$cat.'">'.$cat.'</option>';
					}
				}
			}
		?>
		</select>
		<input type="text" name="search" value="<?php echo $searchFilter; ?>" placeholder="keyword to search for" />
		<input type="submit" value="Submit"/>
	</form>
	<hr />
	
	<form method="post" action="index.php">
	<style>
		.pageNav{ margin: 3px 3px; display: inline-block; }
		.pageNav.current{ font-weight: bold; }
	</style>
	<?php
	//echo '<pre>'.print_r($categories,true).'</pre>';
	if( $searching && $searchCategory != ""){
		echo '<input type="hidden" value="'.$searchCategory.'" />';
		
		if( $searchFilter != "" ){
			echo "<p>Search for &ldquo;".$searchFilter."&rdquo;  in: ".$searchCategory.'</p>';
		}else{
			echo "<p>Listing items in: ".$searchCategory.'</p>';
		}
		
		$count = $conn->prepare("SELECT Count(mdc.`page_id`) as `cnt`		
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
									mdc.`field` = 'tags' AND mdc.`value` LIKE :category 
									AND 
									(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search)
								");
		if( $searchCategory != "ALL" ){						
			$count->bindParam(":category", $searchCategory);
		}else{
			$cat = "%%";
			$count->bindParam(":category", $cat);
		}
		$ss = '%'.$searchFilter.'%';
		$count->bindParam(":search", $ss);
		
		if( $count->execute() ){
			
			$count = $count->fetch();
			
			$totalResults = $count["cnt"];
		
			if( $totalResults > 0 ){
				$query = $conn->prepare("SELECT 
											mdc.`page_id`,
											p.`name`,
											p.`cms_id`,
											p.`content`,
											p.`path`,
											md.`display_name`,
											md.`title`,
											md.`description` 
											
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
											mdc.`field` = 'tags' AND mdc.`value` LIKE :category
											AND
											(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search)
										ORDER BY
											p.`name`
										ASC
										
										LIMIT :start, :pageSize");
				
				if( $searchCategory != "ALL" ){						
					$query->bindParam(":category", $searchCategory);
				}else{
					$cat = "%%";
					$query->bindParam(":category", $cat);
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
						echo '<p><a target="_blank" href="'.$SITE_URL.$row["path"].'.html">'.$title.'</a><br /></p>';
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
									echo '<a href="?page='.$page.'&category='.$searchCategory.'&search='.$searchFilter.'">'.$page.'</a>';
								}else{
									echo $PAGE_DELEMITER;
								}
								echo '</span>';
							}
						echo '</div>';
						
					}
				}
			}
		}
	}
	

?>
<hr />