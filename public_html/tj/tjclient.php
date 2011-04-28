<?php
// tjclient.php
// tjclient recieves the request from the javascript at the user's machine.
// It queries the database, which we are using as a queue, and then clears it out.
// It can also get information from the log on a session id basis
// It returns a json array of songids to the user's javascript


  header('content-type: application/json; charset=utf-8');

	$ini_array = parse_ini_file("../thumbdj.ini");

	//definitions for database
	define("DBHOST", $ini_array["DBHOST"]);
	define("DBUSER", $ini_array["DBUSER"]);
	define("DBPASS", $ini_array["DBPASS"]);
	define("DBNAME", $ini_array["DBNAME"]);

	if (isset($_GET['session']))
		$session = $_GET['session'];
	
	//Connect to database
	$link = mysql_connect(DBHOST, DBUSER, DBPASS)
				or die('Could not connect: ' . mysql_error());
	mysql_select_db(DBNAME) or die('Could not select database');

	

	//switch/case for function call
	switch ($_GET['func']) {
		// Lets you set a new session name (chosen by the user)
		case "setSession":
			echo $_GET['callback']. '('.json_encode(setSession()).')';
			break;

		//Get new stats from the log (not previously sent)
		case "fetchData":
			echo $_GET['callback'].'('.json_encode(fetchData()).')';
			break;

		//Note to self, the below is an example of outputting JSON, it's
		//just printed on the page! simple!
		//we return the function and data we want to be called back!
		//echo $_GET['callback']. '('.json_encode($data).')';
	}

	//keep connection alive
		keepAlive($session);

	//Close database connection
		mysql_close($link);

	// setSession lets the user set the session
	// returns true on success, false otherwise
	function setSession()
	{
		$session = $_GET['session'];
		if (($session == "null") || (trim($session) == "") || (strpos($session, " ") != false))
			return "invalid";

		// select session where word matches input session
		$query= "SELECT * FROM keepalive WHERE session='".$session."'";
		$result = mysql_query($query);
		$result = mysql_fetch_array($result);

		// if there is no session with that name, we can give that session

		if ($result === false) {
			$query = "INSERT INTO keepalive (session, timestamp, persistent) VALUES ('".$session."', UNIX_TIMESTAMP(), 0) ON DUPLICATE KEY UPDATE timestamp = UNIX_TIMESTAMP()";
			$result = mysql_query($query);
			return "true";
		}


		// else we tell them that it's being used
		return "false";
	}


	function fetchData() {
		//Poke the database every 5 seconds
		$sleepTime = 5;
		$data = array();
		$timeout = 0;
		
		//While there is no result and it has been less than ~7 minutes
		while(count($data) == 0 and $timeout < 80) {
			$query = "SELECT commands FROM sessionData2 WHERE session='".$_GET['session']."' AND UNIX_TIMESTAMP(time) > '".$_GET['lastUpdate']."' ORDER BY 'time' DESC";
			$result = mysql_query($query);
    		//create and assemble array
    		while($request = mysql_fetch_array($result, MYSQL_ASSOC)) {
    			array_push($data, json_decode($request["commands"]));
    		}
    		if(count($data) == 0) {
    			flush();
    			sleep($sleepTime);
    			$timeout +=1;
    		} else {
    			break;
    		}
		}

		return $data;
	}

	//keepAlive pings the database to keep our session alive	
	function keepAlive($session)
	{
		//ping database with last time called
		$query = "INSERT INTO keepalive (session, timestamp, persistent) VALUES ('".$session."', UNIX_TIMESTAMP(), 0) ON DUPLICATE KEY UPDATE timestamp = UNIX_TIMESTAMP()";
		mysql_query($query);
	}
