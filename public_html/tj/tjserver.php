<?php
//tjserver recieves text message requests.
// it searches grooveshark for the query
// and inserts into the database the first song result
// Before public release, it should implement sessions
// It might also be possible to store raw queries into the database
// and forward them on to the javascript to look up songs with

	require ("./search.php");
	require ("./txt.php");
	require ("./commands.php");

	//TODO get rid of the below
	error_reporting (E_ALL);
	ini_set('display_errors', 'On');


	// Defined in INI file
	$ini_array = parse_ini_file("../thumbdj.ini");

	//definitions for database
	define("DBHOST", $ini_array["DBHOST"]);
	define("DBUSER", $ini_array["DBUSER"]);
	define("DBPASS", $ini_array["DBPASS"]);
	define("DBNAME", $ini_array["DBNAME"]);

	// global variable -- declared here must also be declared with 'global' within functions
	$search;
	$link;
	$message;	


	//connect to database
	$link = mysql_connect(DBHOST, DBUSER, DBPASS)
       or die('Could not connect: '.mysql_error());
	mysql_select_db(DBNAME) or die('Could not select database');

	
	$message = $_REQUEST['Body'];

	// Process the message
//$commands = array();
/**
	$commands = array(
						array("word" => ":)", "callback" => "voteUp")
						array("word" => "panda", "callback" => "fuckShitUp")
						);
**/
	$commands = array(
						array("word" => "test", "callback" => "testCommands"),
						array("word" => ":)", "callback" => "voteUp"),
						array("word" => ":(", "callback" => "voteDown"),
						array("word" => "skip", "callback" => "skip")
//						array("word" => "panda", "callback" => "fuckShitUp")
						);
	processMessage($message, $commands);


	function tieSession($session) {
		$phonenumber = ltrim($_REQUEST['From'],"+");
		//session match was found, so we update the association db only if it's a valid session
		$sqlquery = "SELECT * FROM keepalive WHERE session='".$session."'";
		$mysqlResult = mysql_query($sqlquery);

		if (mysql_fetch_row($mysqlResult) != false) { // session name is in use... we can associate
			$sqlquery = "REPLACE INTO association (phonenumber, session) VALUES ('".$phonenumber."', '".$session."')";
			return mysql_query($sqlquery);
		} else return false;
	}

	// handleSongQuery takes in a string which contains the query
	//		it calls a search on the query and adds them to the database
	//		it returns an array with song information, and a boolean of whether it was found
	//			$result["id"] = 568292; $result["artist"] = "shakira";
	//			$result["name"] = "she-wolf"; $result["found"] = true;
	// returns false on error
	function handleSongQuery($query) {
		global $search;

		// instantiate search object
		$search = new search();
		$phonenumber = ltrim($_REQUEST['From'],"+");

		//get the session they are associated with
		$sqlquery = "SELECT session FROM association WHERE phonenumber='".$phonenumber."'";
		if (!($row = mysql_fetch_array(mysql_query($sqlquery)))) {
			respondSessionNotFound();
			return false;
		}
		$session = $row['session'];

		// search takes a queries and
		// returns array with song name/artist/id/found
		if (!is_string($query)){
			return false;
		}

		$result = $search->gsSearch($query);	

		if ($result["found"]) {
			// INSERT INTO LOGS
	
//			$sqlquery = "INSERT INTO logs (phonenumber, session, message, songid, fail) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$result["id"]."', '0')";
			$result["phonenumber"] = $phonenumber;
			
			// setup JSON object
			// example object for adding songs, may not be exact:
			//     "addSong":{"user":{"phonenumber":"phoneNumString"}, "song":{"name":"songName", "artist":"songArtist", "id":songID},  "timeAdded":timestamp}
			$user = $result;
			unset($user["found"]); // not necessary in the json
			unset($user["phonenumber"]); // used elsewhere
			$commands = array("addSong"=>array("song"=>$user, "user"=>array("phone"=>$phonenumber), "time"=>time()));
			$commands = json_encode($commands);

			$sqlquery = "INSERT INTO logs2 (phonenumber, session, message, commands) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$commands."')";
			mysql_query($sqlquery);	

			// INSERT INTO SESSIONDATA
			$sqlquery = "INSERT INTO sessionData2 (phonenumber, session, message, commands) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$commands."')";
//			$sqlquery = "INSERT INTO sessionData (phonenumber, session, message, songid, fail) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$result["id"]."', '0')";
			mysql_query($sqlquery);	
		} else {
			// INSERT INTO LOGS
//			$sqlquery = "INSERT INTO logs (phonenumber, session, message, songid, fail) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '', '1')";
			$commands = array("songNotFound"=>$result);
			$commands = json_encode($commands);
			$sqlquery = "INSERT INTO logs2 (phonenumber, session, message, commands) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$commands."')";
			mysql_query($sqlquery);	

			// INSERT INTO SESSIONDATA
//			$sqlquery = "INSERT INTO sessionData (phonenumber, session, message, songid, fail) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '', '1')";
//			mysql_query($sqlquery);	
		}
	return $result;
	}


	/**
	* sendToServer push the json message to the backend
	*/
	function sendToServer($json) {
		$phonenumber = ltrim($_REQUEST['From'],"+");
		//get the session they are associated with
		$sqlquery = "SELECT session FROM association WHERE phonenumber='".$phonenumber."'";
		if (!($row = mysql_fetch_array(mysql_query($sqlquery)))) {
			respondSessionNotFound();
			return false;
		}
		$session = $row['session'];

		$sqlquery = "INSERT INTO logs2 (phonenumber, session, message, commands) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$json."')";
		mysql_query($sqlquery);	

	// INSERT INTO SESSIONDATA
		$sqlquery = "INSERT INTO sessionData2 (phonenumber, session, message, commands) VALUES ('".$phonenumber."', '".$session."', '".$_REQUEST["Body"]."', '".$json."')";
		mysql_query($sqlquery);	
	}

mysql_close($link);
