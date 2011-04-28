<?php
//cleanup.php runs every 5 minutes to timeout sessions

//TODO make sure that we actually get this array...
	$ini_array = parse_ini_file("../thumbdj.ini");

	//definitions for database
	define("DBHOST", $ini_array["DBHOST"]);
	define("DBUSER", $ini_array["DBUSER"]);
	define("DBPASS", $ini_array["DBPASS"]);
	define("DBNAME", $ini_array["DBNAME"]);

	//Connect to database
	$link = mysql_connect(DBHOST, DBUSER, DBPASS)
				or die('Could not connect: ' . mysql_error());
	mysql_select_db(DBNAME) or die('Could not select database');

    //select all sessions that are older than 10 minutes
        $query = "SELECT * FROM keepalive WHERE timestamp < (UNIX_TIMESTAMP() - 600)";
        $result = mysql_query($query);


    //foreach session selected
    while($row = mysql_fetch_row($result)){

	//delete associations
            $query = "DELETE FROM association WHERE session='".$row[0]."'";
            mysql_query($query);

	//delete keepalive pings
            $query = "DELETE FROM keepalive WHERE session='".$row[0]."'";
            mysql_query($query);

	//clear any remaining queue
            $query = "DELETE FROM sessionData2 WHERE session='".$row[0]."'";
            mysql_query($query);

	//set word inuse to 0
		$query = "UPDATE  `words` SET  `inuse` =  '0' WHERE  `words`.`word` =  '".$row[0]."'" ;

	    //$query = "REPLACE INTO words (word, inuse) VALUES ('".$row[0]."', '0')";
	    mysql_query($query);
    }
