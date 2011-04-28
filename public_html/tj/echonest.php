<?php

// echonest.php
// echonest provides access functions to echonest api
// search performs all the search functions we could ever want
//  for example, it can do the last.fm search and then the grooveshark
//  search for us automagically if we like

class EchoNest
{

	// default constructor
	function __construct() {
	
		$ini_array = parse_ini_file("../thumbdj.ini");

		// definitions for echonest
		define("ECHOKEY", $ini_array["ECHOKEY"]);
	}


	// songSearch
	// songSearch takes a query to search for
	// returns a search object as an associative array
	function songSearch($query) {
		$results = file_get_contents(
			"http://developer.echonest.com/api/v4/song/search?api_key=".ECHOKEY."&format=json&results=1&sort=song_hotttnesss-desc&combined=".urlencode($query));	
		return json_decode($results, true);
	}
	

	// NOTE: unfortunately not a lot of tracks are on echonest... to make sure you have one you must do a song search from the track bucket

	// getSongProfile
	//	getSongProfile returns an array with various properties of the
	//		song ranging from name, to tempo, to danceability
	//	id is the echonest track id
	function getSongProfile($id) {
		$results = file_get_contents(
			"http://developer.echonest.com/api/v4/track/profile?api_key=".ECHOKEY."&format=json&id=".urlencode($id));	
		return json_decode($results, true);
	}


	function doSongAnalysis($id) {
		$destination = "http://developer.echonest.com/api/v4/track/analyze";
		 
		$eol = "\r\n";
		$data = '';
		 
		$mime_boundary=md5(time());
		 
		$data .= '--' . $mime_boundary . $eol;
		$data .= 'Content-Disposition: form-data; name="api_key"' . $eol . $eol;
		$data .= ECHOKEY . $eol;
		$data .= '--' . $mime_boundary . $eol;
		$data .= 'Content-Disposition: form-data; name="format"' . $eol . $eol;
		$data .= "json" . $eol;
		$data .= '--' . $mime_boundary . $eol;
		$data .= 'Content-Disposition: form-data; name="id"' . $eol . $eol;
		$data .= $id . $eol;
		$data .= '--' . $mime_boundary . $eol;
		$data .= "--" . $mime_boundary . "--" . $eol . $eol; // finish with two eol's!!
		 
		$params = array('http' => array(
								'method' => 'POST',
								'header' => 'Content-Type: multipart/form-data; boundary=' . $mime_boundary . $eol,
								'content' => $data
							));
		 
		$ctx = stream_context_create($params);
		$results = @file_get_contents($destination, FILE_TEXT, $ctx);

		return json_decode($results, true);
	}

}
