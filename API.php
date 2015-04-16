<?php
	include "db.php";
	include "functions.php";
	
	//$conn = getConnection();
	$searching = 0;
	$searchCategory = array();
	$searchFilter = "";
	$err = "";
	$PAGE_SIZE = 8;
	$PAGE_BEFORE_AFTER = 3;
	$PAGE_DELEMITER = "...";
	$CURRENT_PAGE = 1;
	$SITE_URL = "http://wlu.ca/";
	$ascDesc = "ASC";
	$orderBy = "n";
	$showImage = "yes";
	$items = array();
	$totalPages = 0;
	$totalResults = 0;

	if( isset($_REQUEST) && isset($_REQUEST['page']) && $_REQUEST['page'] != "" ){
		if( $_REQUEST['page'] < 1){
			$CURRENT_PAGE = 1;
			$err  = "PAGING STARTS AT 1";
		}else{
			$CURRENT_PAGE = $_REQUEST['page'];
		}
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
		}else if( $_REQUEST['orderBy'] == "ua" ){ //publish date
			$orderBy = "ua";	
		}else{
			$orderBy = "n"; //name
		}
	}

	if( isset($_REQUEST) && isset($_REQUEST['itemsPerPage']) && $_REQUEST['itemsPerPage'] != "" ){
		if( $_REQUEST['itemsPerPage'] > 0 && $_REQUEST['itemsPerPage'] <= 30 ){ //creation date
			$PAGE_SIZE = (int)$_REQUEST['itemsPerPage'];
		}
	}
	
	if( isset($_REQUEST) && isset($_REQUEST['showImage']) && $_REQUEST['showImage'] != "" ){
		if( $_REQUEST['showImage'] != "yes" ){
			$showImage = "no";
		}
	}
	
	//show image
	//
	
	
	
	if( $searching && $searchCategory != ""){
		$conn = getConnection();
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
								md.`created_at`,
								md.`updated_at`,
								(SELECT GROUP_CONCAT(mci.`value`) FROM `metadata_custom` mci WHERE mci.`field` = 'tags' AND mci.`page_id` = mdc.`page_id` group by mdc.`page_id`) as `tags`
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
								md.`last_published_at` != 'NULL'
								AND
								mdc.`field` = 'tags' 
								AND 
								mdc.`value` LIKE :category
								AND	(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search) 
								
							ORDER BY
								".($orderBy == "n" ? "`name`" : ($orderBy == "ua" ? "`updated_at`" :( $orderBy == "lpd" ? '`last_published_at`' : '`created_at`' ) ) )."
							".$ascDesc."
							LIMIT :start, :pageSize";
							
							$query = $conn->prepare($query);	
							
							$category = $searchCategory[0];
							if( $category == "ALL" ){
								$category = "%%";
							}
							
							$query->bindParam(":category", $category);
							
					}else if(is_array($searchCategory) && sizeof($searchCategory) > 1){
						//Multiple Categories
						
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
									`created_at`,
									`updated_at`,
									`tags`
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
									md.`created_at`,
									md.`updated_at`,
									(SELECT GROUP_CONCAT(mci.`value`) FROM `metadata_custom` mci WHERE mci.`field` = 'tags' AND mci.`page_id` = mdc.`page_id` group by mdc.`page_id`) as `tags`
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
									md.`last_published_at` != 'NULL'
									AND
									mdc.`field` = 'tags' 
									AND 
									mdc.`value` LIKE ".$category."
									AND	(p.`content` LIKE :search OR md.`display_name` LIKE :search OR md.`title` LIKE :search)"; 
						}			

						$query.=" ) mul
								ORDER BY
									".($orderBy == "n" ? "`name`" : ($orderBy == "ua" ? "`updated_at`" :( $orderBy == "lpd" ? '`last_published_at`' : '`created_at`' ) ) )."
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
						
						//echo "<p>Displaying Results: ".( ($CURRENT_PAGE-1) * $PAGE_SIZE). ' to '. $to.' of: '.$totalResults.'</p><br />';
						
						
						
						while( $row = $query->fetch() ){
							
							//$item = array();
							
							$title = utf8_encode($row["display_name"]);
							if( trim($title) == "" ){
								$title = utf8_encode($row["title"]);
							}
							
							$url = $SITE_URL.$row["path"].'.html';
							
							//echo '<p><a target="_blank" href="'.$url.'">'.$title.'</a><br />';
							
							if( $showImage == "yes" ){
								$pageHTML = file_get_contents($url);
								$doc = new DOMDocument();
								
								//keep the errors away in parsing
								libxml_use_internal_errors(true);
								$doc->loadHTML($pageHTML);
								libxml_clear_errors();
								$xpath = new DomXpath($doc);
								$pageImage = "";
								$trueImageURL = "";
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
									//echo '<img src="'.$trueImageURL.'" alt="'.$pageImage["alt"].'" />'; 
								}
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
							
								//echo $summary;
								
								
							//echo '</p>';
							$tags = $row['tags'];
							
							$items[] = array("title"=> $title, "summary"=> $summary, "image"=>$trueImageURL,"tags"=>$tags,"url"=>$url,"created_at"=>$row["created_at"],"updated_at"=>$row["updated_at"],"last_published_at"=>$row["last_published_at"]);
							
						}
						
						
						
						$totalPages = 0;
						if( $totalResults > $query->rowCount()  ){
							$totalPages = round_up($totalResults / $PAGE_SIZE);
						}
						
						ob_clean();
						
						if($CURRENT_PAGE > $totalPages || $CURRENT_PAGE < 1 ){
							$err = "PAGE OUT OF BOUNDS";
						}
						
						$response = array("category"=>$searchCategory,"currentPage"=>$CURRENT_PAGE,"totalPages"=>$totalPages,"itemsPerPage"=>$PAGE_SIZE,"TotalResults" => $totalResults,"NumResults"=>sizeof($items),"results"=>$items, "showImage"=>$showImage, "Errors" => $err  );
						header("Access-Control-Allow-Origin: *");
						header('Content-Type: application/json');
						echo json_encode($response);
						exit;
						
						//pa( $response );
						
						
					}
				}else{
					ob_clean();
					
					$err = "No Results Found";
					
					$response = array("category"=>$searchCategory,"currentPage"=>$CURRENT_PAGE,"totalPages"=>$totalPages,"itemsPerPage"=>$PAGE_SIZE,"TotalResults" => $totalResults,"NumResults"=>sizeof($items),"results"=>$items, "Errors" => $err  );
					header("Access-Control-Allow-Origin: *");
					header('Content-Type: application/json');
					echo json_encode($response);
					exit;
				}
			}
	}else{
		ob_clean();
		
		$err = "Empty Request";
		
		$response = array("category"=>$searchCategory,"currentPage"=>$CURRENT_PAGE,"totalPages"=>$totalPages,"itemsPerPage"=>$PAGE_SIZE,"TotalResults" => $totalResults,"NumResults"=>sizeof($items),"results"=>$items, "Errors" => $err  );
		header("Access-Control-Allow-Origin: *");
		header('Content-Type: application/json');
		echo json_encode($response);
		exit;
	}
?>