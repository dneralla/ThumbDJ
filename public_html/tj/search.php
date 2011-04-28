<?php

// search.php
// search performs all the search functions we could ever want
//  for example, it can do the last.fm search and then the grooveshark
//  search for us automagically if we like

class search
{
	private $echonest;
	private $gsapi;
	private $lastfm;
	private $nameThreshhold;
	private $artistThreshhold;
	private $searchPath;
	private $result;
	

	// default constructor
	function __construct() {

		require_once ("./gsapi.php");
		require_once ("./echonest.php");
		require_once ("./last_fm.php");
		$ini_array = parse_ini_file("../thumbdj.ini");

		// definitions for gsapi
		define("GSKEY", $ini_array["GSKEY"]);
		define("GSSECRET", $ini_array["GSSECRET"]);
		
		define("LFMKEY", $ini_array["LFMKEY"]);
		define("LFMSECRET", $ini_array["LFMSECRET"]);
		
		
		
		$this->nameThreshhold = $this->artistThreshhold = 100;
//		$this->nameThreshhold = $this->artistThreshhold = 0;
		$this->searchPath = "";

	}


	// gsSearch
	// gsSearch returns an array with:
	//  song name (name)
	//  song artist (artist)
	//	 song id (id)
	//  song found (found)
	//
	//	This is the main search used for thumbdj
	function gsSearch($query) {
		$this->searchPath = "";
				
		// perform grooveshark search
		// and echonest search
		$this->gsapi = new gsapi(GSKEY, GSSECRET);
		$this->echonest = new EchoNest();
		$this->result = array("found" => false);

		$this->searchPath .= " starting query ".$query." -> ";  
			
		$query = $this->extractSongArtist($query);

		// is it a song/artist pair? will be if $query is an array...
		if (is_array($query)) {
			$this->searchPath .= " is a song artist pair -> ";  
			
			$this->result = $this->gsSearchAndPick($query["name"], $query["artist"]);
			$this->handleResult();
		} else if (is_string($query)) {	
			$this->searchPath .= " is a regular query -> ";  
				
			$this->result = $this->echoSearch($query);

		
			if (!$this->result["found"]) {
				$this->searchPath .= " was not found by echonest... trying grooveshark";
				$this->result = $this->gsSearchAndPick($query);
				$this->handleResult();
				//return $this->result;
			} else if (($this->result["nameMatch"] < $this->nameThreshhold) && ($this->result["artistMatch"] < $this->artistThreshhold)) {
				// if the match is not good enough fall back to the gs search
				$this->searchPath .= " did not pass the echo threshhold (doing gsSearch on original query) -> ";  
				
				$this->result = $this->gsSearchAndPick($query);
				$this->handleResult();

			} else {
				$this->searchPath .= " passed the echo threshhold (doing gsSearch on echo result) -> ";
					
				$this->result = $this->gsSearchAndPick($this->result["name"], $this->result["artist"]);
				$this->handleResult();
			}
		}

		/**
		*	Add other data to grooveshark result... album art, genre, etc
		**/
		if ($this->result["found"]) {
			$this->lastfm = new LastFm(LFMKEY, LFMSECRET);
			$lastfmResult = $this->lastfm->trackSearch($this->result["name"],$this->result["artist"], 1);
			if ($lastfmResult["results"]["opensearch:totalResults"] !== "0") {
				$this->result["albumArtURL"] = $lastfmResult["results"]["trackmatches"]["track"]["image"][3]["#text"];

			} else {
//TODO handle this	
//				echo "no last fm result!";
			}
			
		}
		// default is returning nothing found
		return $this->result;

	}

	function handleResult () {
		if ($this->result["found"]){
			$this->searchPath .= " was found and added";  
		}
		else {
			$this->searchPath .= " was not found";
		}
	}
	
	// echoSearch
	// given just a query echoSearch returns an array with
	// name (song name)
	// artist
	// nameMatch (percent match of song name and query)
	// and artistMatch (percent match of artist and query)
	// returns NULL on failure or no results found
	function echoSearch ($query) {		
		// do echo search
		$echoResult = $this->echonest->songSearch($query);
		
		if (!empty($echoResult["response"]["songs"])) {
			
		// do matching
		$songName = $echoResult["response"]["songs"][0]["title"];
		$songArtist = $echoResult["response"]["songs"][0]["artist_name"];
		$nameMatch;
		$artistMatch;
		similar_text(strtoupper($query), strtoupper($songName), $nameMatch);
		similar_text(strtoupper($query), strtoupper($songArtist), $artistMatch);
		
		// assemble array
	
			$tempArr = array();
			$tempArr["name"]	= $songName;
			$tempArr["artist"] = $songArtist;
			$tempArr["nameMatch"] = $nameMatch;
			$tempArr["artistMatch"] = $artistMatch;
			$tempArr["found"] = true;
			return $tempArr;
		} else 
			return array("found" => false);
	}
	
	// gsSearchAndPick
	// takes either a song name and artist or just the query
	// returns an array with "name", "artist", and "id" fields or NULL on failure or not found
	function gsSearchAndPick ($queryOrSongName, $artist = "") {
		// do a search on grooveshark with parameters
		// if only a query was passed...
		$query = $queryOrSongName . " " . $artist;
		$gsResults = NULL;
		for ($i = 0; $i < 3; $i++) {
			$gsResults = $this->gsapi->getSongSearchResults($query, 10);
			if ($gsResults != NULL)
				return $this->pickBestGS($gsResults, $queryOrSongName, $artist);
			
			$this->searchPath .= " got NULL gsResults (maybe we asked too fast, or gs is down... sleep 5 and try max 3 times) -> ";
			sleep (5);
		}

		return array("found" => false);
				
	}
	
	// pickBestGS
	// given an array of grooveshark results, a query, or song name and artist
	// pickBestGS returns an array with "name", "artist", and "id" fields of
	// the grooveshark result closest to the query or song name/artist pair
	// returns NULL on failure (or if the array is empty)
	function pickBestGS ($gsResults, $queryOrSongName, $artist = "") {
		$bestGrooveIndex = NULL;
		$bestGroovePercent = -1;
		
		
		// if no artist, only match to query
		if ($artist == "") {
			foreach ($gsResults["songs"] as $gsIndex=>$gsQuery)
			{
				$songName = $gsResults["songs"][$gsIndex]["SongName"];
				$songArtist = $gsResults["songs"][$gsIndex]["ArtistName"];
				$bestName = $bestArtist = 0;
				similar_text(strtoupper($queryOrSongName), strtoupper($songName), $bestName);
				similar_text(strtoupper($queryOrSongName), strtoupper($songArtist), $bestArtist);
				$tempBestGroovePercent = ($bestName >= $bestArtist) ? $bestName : $bestArtist;
				if ($tempBestGroovePercent > $bestGroovePercent) {
					$bestGroovePercent = $tempBestGroovePercent;
					$bestGrooveIndex = $gsIndex;
				}
			}
					
		} else {	// if there is an artist, match to both
			foreach ($gsResults["songs"] as $gsIndex=>$gsQuery)
			{
				$songName = $gsResults["songs"][$gsIndex]["SongName"];
				$songArtist = $gsResults["songs"][$gsIndex]["ArtistName"];
				$bestName = $bestArtist = 0;
				similar_text(strtoupper($queryOrSongName), strtoupper($songName), $bestName);
				similar_text(strtoupper($artist), strtoupper($songArtist), $bestArtist);
				$tempBestGroovePercent = $bestName + $bestArtist;
				if ($tempBestGroovePercent > $bestGroovePercent) {
					$bestGroovePercent = $tempBestGroovePercent;
					$bestGrooveIndex = $gsIndex;
				}
			}		
		}
		

		if ($bestGrooveIndex !== NULL) {
			$tempArr = array();
			$tempArr["name"]	= $gsResults["songs"][$bestGrooveIndex]["SongName"];
			$tempArr["artist"] = $gsResults["songs"][$bestGrooveIndex]["ArtistName"];
			$tempArr["id"] = $gsResults["songs"][$bestGrooveIndex]["SongID"];
			$tempArr["found"] = true;
			return $tempArr;
		} else 
			return array("found" => false);
	}

	/**
	* see if we can extract artist name and song name from each query
	* this returns the same array, if the song/artist could be
	*		extracted it will replace the query with an array that
	*		has the fields "name" and artist
	**/
	function extractSongArtist($query) {
		$query = explode("by", $query, 2);
		if (sizeof($query) == 2) {
			$query["name"] = $query[0];
			$query["artist"] = $query[1];
			
		} else {
			$query = $query[0];
		}

		return $query;
	}

	/**
	 * setThreshholds
	 * currently used for testing/tweaking setThreshholds changes the 
	 * 	percent match needed by echonest... and therefore changing the 
	 *	"path" the search takes
	 * @param $nameThreshhold
	 * @param $artistThreshhold
	 * @return void
	 * @author Islam Sharabash
	 **/
	function setThreshholds($nameThreshhold, $artistThreshhold) {
		$this->nameThreshhold = $nameThreshhold;
		$this->artistThreshhold = $artistThreshhold;
	}
	
	/**
	 * getLastPath is used for error checking and search testing.
	 * 	the variable $searchPath is a string that is updated with
	 *	the "path" the search takes throughout the algorithm
	 *
	 * @return $searchPath
	 * @author Islam Sharabash
	 **/
	function getLastPath () {
		return $this->searchPath;
	}
	
	/**
	* outputPath is used for outputting the last searchPath
	* from getLastPath
	* @return void
	* @author Hani Sharabash
	**/
	function outputPath () {
		$f = fopen("searchPath.txt", "w");
		fwrite($f, $this->getLastPath());
		fclose($f);
	}

}
