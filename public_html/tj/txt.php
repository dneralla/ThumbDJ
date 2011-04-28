<?php
/**
*	txt.php
*	txt.php encapsulates all the functionality to
*	process the body of a text message
*
*	It expects the functions these functions to be defined:
*		* tieSession($session) // returns boolean if session was tied
*		* handleSongQueries($queries) // returns array of song name/artist pairs
*												//		index 0 is boolean if everything was found
*
*	Notably it has the function processMessage($msgBody) 
*		and function respondSessionNotFound();
*
**/

/**
* processMessage takes the message body and
*	parses out the session name, songs, and anything else
*	
*	$commands is a 2d array, the second array hold the command and callback
*		(i.e. array( 0=> array("word" => ":)", "callback" => "voteUp")))
**/

// TODO take an array of commands and callbacks... if no commands are found do command and song search 
// TODO Callbacks handle calling text message responses
//TODO make command words "inuse"
// if a callback is not implemented nothing will happen
//TODO log if a callback is not implemented or report it

function processMessage($msgBody, $commands) {

	// iterate through commands and do it
//TODO debug this
	foreach ($commands as $command) {
		if (strtoupper(trim($msgBody)) == strtoupper(trim($command["word"]))) {
			// command was found now it's do or die
			if (is_callable($command["callback"]))
				call_user_func($command["callback"]);
			return NULL;
		}
	}



	// parse out session name
	$pattern = "/(#)\K\w*/";
	$tiedSession = false;
	if (preg_match($pattern, $msgBody, $session) != 0) {
		$tiedSession = tieSession($session[0]);
		$msgBody = trim(preg_replace($pattern, "", $msgBody), "#");
		if (!$tiedSession) { // if session was invalid
			respond("No active session found, visit ThumbDJ.com!");
			return NULL;
		}
	}

   // if we tied to a session, and no songs were added,
   //    let them know they were tied
   if ($tiedSession && ($msgBody=="")) {
      respondSessionTied();
      return NULL;
   } 

   if ($msgBody != "") {
      $result = handleSongQuery($msgBody);

		// all songs were found
		if ($result["found"]) {
			respondSongFound($result);
			return NULL;
		}

		// nothing found
		if (!$result["found"]) {
			respondNotFound();
			return NULL;
		}
	}

	// catch all
	respondCatchAll();
}


/**
* Below are text message responses
*	encapsulated as functions
*
**/

function respond($message) {
	header("content-type: text/xml");
	echo "<Response>
			<Sms>".$message."</Sms>
			</Response>";
}

function respondSessionTied() {
	header("content-type: text/xml");
	echo "<Response>
			<Sms>Welcome to ThumbDJ! Reply with your requests to add a new song. Examples:\"Kids by MGMT\", \"lady gaga\", \"black and yellow\"</Sms>
			</Response>";
}
/**
function respondSessionTied() {
	header("content-type: text/xml");
	echo "<Response>
			<Sms>Your phone number is tied to Grooveshark! Reply with your requests. Examples:\"The high road by broken bells\", \"lady gaga\", \"thriller\"</Sms>
			</Response>";
}
**/
function respondSessionNotFound() {
   header("content-type: text/xml");
   echo "<Response>
         <Sms>You're tied to no sessions, get a session from your host or thumbdj.com, and sending a text with #session</Sms>
         </Response>";
}

function respondCatchAll() {
	header("content-type: text/xml");
	echo "<Response>
			<Sms>Did you try requesting songs? Visit thumbdj.com</Sms>
			</Response>";
}

function respondSongFound($result) {
	header("content-type: text/xml");
	echo "<Response>
	<Sms>Added " . $result["name"] . " by " . $result["artist"]  . "!</Sms>
	</Response>";
}

function respondNotFound() {
	header("content-type: text/xml");
	echo "<Response>
			<Sms>Your request was not found... try again?</Sms>
			</Response>";
}

