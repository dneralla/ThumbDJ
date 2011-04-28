<?php
	$platforms = array("retro.grooveshark.com" => "grooveshark_R.js", "listen.grooveshark.com" => "grooveshark.js");
	if ($platforms[$_REQUEST['platform']]) {
		echo $_GET['callback'].'('.json_encode(array("platform" => $platforms[$_REQUEST['platform']])).')';
	} else {
		echo $_GET['callback'].'('.json_encode(array("platform" => "fail")).')';
	}
?>
