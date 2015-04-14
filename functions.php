<?php
	function round_up($number, $precision = 0){
		$fig = (int) str_pad('1', $precision, '0');
		return (ceil($number * $fig) / $fig);
	}
	
	function getSummary($string, $searchFilter, $summaryLength){
		$string = strip_tags($string, '<p></p><br><br/>');
		$start = 0;
		if( $searchFilter != "" ){
			$pos = strpos( $string, $searchFilter);
			
			$end = strlen( $string );
			if ($pos === false) {
				//string not found
				$string = substr( $string, 0, $summaryLength);
			}else{
				if( $pos - round_up($summaryLength/2) > 0){
					$start = $pos - round_up($summaryLength/2);
				}else{
					$start = 0;
				}
				
				if( $pos + round_up($summaryLength/2) > $end){
					//end is end
				}else{
					$end = $pos + round_up($summaryLength/2);
				}
				$string = substr($string, $start, $summaryLength);
			}
		}else{
			$string = substr( $string, 0, $summaryLength);
		}
		$string = str_replace('<p>', ' ', $string);
		$string = str_replace('</p>', ' ', $string);
		
		$string = str_replace('<br>', ' ', $string);
		$string = str_replace('<br/>', ' ', $string);
		
		$string = str_replace($searchFilter, '<b>'.$searchFilter.'</b>', $string);
		
		if( $start > 0){
			$string = '...'.$string;
		}
		
		return $string.'...';
	}
	
	function pa($arr){ echo '<pre>'.print_r($arr,true).'</pre>'; }
?>