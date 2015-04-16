<?php
	error_reporting(E_ALL);

	include "db.php";
	include "functions.php";
	
	$conn = getConnection();
	$searching = 0;
	$searchCategory = array();
	$searchFilter = "";
	
	$PAGE_SIZE = 10;
	$PAGE_BEFORE_AFTER = 3;
	$PAGE_DELEMITER = "...";
	$CURRENT_PAGE = 1;
	$SITE_URL = "http://wlu.ca/";
	$ascDesc = "ASC";
	$orderBy = "n";
	$showImage = "yes";
	
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'){
		
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
			}else if( $_POST['orderBy'] == "ua" ){ //updated at
				$orderBy = "ua";	
			}else{
				$orderBy = "n"; //name
			}
		}
		
		if( ! is_array($searchCategory) || sizeof($searchCategory) == 0  ){
			$searchCategory[] = "ALL";
		}
		
		if( isset($_POST) && isset($_POST['itemsPerPage']) && $_POST['itemsPerPage'] != "" ){
			if( $_POST['itemsPerPage'] > 0 && $_POST['itemsPerPage'] <= 30 ){ //creation date
				$PAGE_SIZE = $_POST['itemsPerPage'];
			}
		}
		
		if( isset($_POST) && isset($_POST['showImage']) && $_POST['showImage'] != "" ){
			if( trim($_POST['showImage']) != "yes" ){ //creation date
				$showImage = "no";
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
	<style>
		#items{ background-position: center center; background-image: url('css/gif-load.gif'); background-repeat: no-repeat; width: 100%; min-height: 600px;}
		#items.loaded{ background-image: none; }
		#items .item{ float: left; margin: 5px; width: 400px; height: 400px; overflow: hidden; }
		#items .item img{ max-width:350px; max-height: 350px;}
		.clear{ clear: both; }
		#loadMore{cursor: pointer;}

	</style>
	<form method="post" action="index_api.php">
	
		Categories
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
		Search Pre-Filter
		<input type="text" name="search" value="<?php echo $searchFilter; ?>" placeholder="keyword to search for" />
		Order by:
		<select name="orderBy">
			<option value="n" <?php echo ($orderBy == "n") ? 'selected' : ''; ?>>Name</option>
			<option value="cd" <?php echo ($orderBy == "cd") ? 'selected' : ''; ?>>Creation Date</option>
			<option value="lpd" <?php echo ($orderBy == "lpd") ? 'selected' : ''; ?>>Last Publish Date</option>
			<option value="ua" <?php echo ($orderBy == "ua") ? 'selected' : ''; ?>>Updated at</option>
		</select>
		<select name="ascDesc">
			<option value="Ascending" <?php echo ($ascDesc == "ASC") ? 'selected' : ''; ?>>Ascending</option>
			<option value="Descending" <?php echo ($ascDesc != "ASC") ? 'selected' : ''; ?>>Descending</option>
		</select>
		Items Per Load
		<select name="itemsPerPage">
		<?php
			for($x=5; $x<35; $x+=5){ //5 - 30
				echo '<option value="'.$x.'"'.($x==$PAGE_SIZE ? ' selected':'').'>'.$x.'</option>';
			}
		?>
		</select>
		Show Image
		<select name="showImage">
			<option value="yes" <?php echo ($showImage == "yes") ? 'selected' : ''; ?>>Yes</option>
			<option value="no"  <?php echo ($showImage == "no") ? 'selected' : ''; ?>>No</option>
		</select>
		
		<input type="submit" value="Submit"/>
	</form>

	<hr />
	
	API String: <?php echo '?page=1&category='.implode(",",$searchCategory).'&search='.$searchFilter.'&ascDesc='.$ascDesc.'&orderBy='.$orderBy; ?>
	
	
	<?php
		$apiRequest = '&category='.implode(",",$searchCategory).'&search='.$searchFilter.'&ascDesc='.$ascDesc.'&orderBy='.$orderBy.'&itemsPerPage='.$PAGE_SIZE.'&showImage='.$showImage; //need the ?page = x in JS
	?>
	<script>
	$(function(){	
		var onPage = 1;
		var apiStr = "<?php echo $apiRequest; ?>";
		
		//console.log("performing Query;")
		
		function loadMore(){
			$('#loadMore').html("<img src='css/gif-load.gif' />");
			
			$.getJSON( "http://205.189.20.167:85/api.php?page=" + onPage + apiStr, function( data ) {
			 //console.log( data );
			 
			 if( typeof data !== 'undefined' ){
				var numResults = data["NumResults"];
				var totalResults = data["TotalResults"];
				var category =  data["category"];
				var currentPage = data["currentPage"];
				var itemsPerPage = data["itemsPerPage"];
				var totalPages = data["totalPages"];
				var results = data["results"];
				var showImage = data["showImage"];
				
				if( currentPage == onPage && onPage < totalPages){
					onPage++;
					$('#loadMore').html("Load More");
				}else{
					$('#loadMore').remove();
				}
				
				var aHtml = "";
				
				if( results.length == numResults){
					for(var r=0; r < numResults; r++){
						//console.log( results[r]);
						aHtml += '<div class="item"><a target="_blank" href="' + results[r]["url"] + '">' + ( showImage == "yes" ? '<img src="' + results[r]["image"] + '"/>' : results[r]["title"]) + '</a><p>' + results[r]["summary"] + '</p><p>Tags:' + results[r]["tags"] + '<br />last_published_at: ' + results[r]["last_published_at"] + '<br />created_at: '  +  results[r]["created_at"] + '<br />updated_at: ' +  results[r]["updated_at"] + '</p></div>';
					}
				}
				$('#items').append(aHtml);
				
			 }
			  $('#items').addClass('loaded');
			});
		}
		
		loadMore();
		
		$('#loadMore').click(function(){
			loadMore();
		});
		
	});
	</script>
	<div id="items">
		
	</div>
	<div class="clear"></div>
	<div id="loadMore"></div>
	
<hr />