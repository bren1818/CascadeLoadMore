<?php
	function round_up($number, $precision = 0){
		$fig = (int) str_pad('1', $precision, '0');
		return (ceil($number * $fig) / $fig);
	}
?>