/* Data Layer Class
 * Copyright 2010 to Present - ThumbDJ - All Rights Reserved
 */

/* The Datalayer object interfaces between the client and the server,
 * and provides ways for other JS objects to access data sent by the
 * server.
 *
 * @parameter session - this is the session that the Datalayer is
 *     tied to, which it will use when communicating with the
 *     server.
 */
function DL(session) { // Data Layer initialization
	// settings vars
	var session = session; // private var (set on DL creation, read only)
	this.getSession = function() {
		return session;
	}

	// data storage vars
	var sessionData = new Array(); // private var (read only, can be altered by DL functions)
	this.getSessionData = function() {
		return sessionData;
	}
	
	var songs = new Array(); // private var (read only, can be altered by DL functions)
	this.getSongs = function() {
		return songs;
	}
	this.getSong = function(songId) {
		return songs[songId];
	}
	
	var users = new Array(); // private var (read only, can be altered by DL functions)
	this.getUsers = function() {
		return users;
	}
	this.getUser = function(userId) {
		return users[userId];
	}
	
	/* a method to add song data to the DL object
	 *
	 * @parameter request - this should be an instance of the 'addSong' data object in the json format as follows:
	 *     "addSong":{"user":{"number":"phoneNum"}, "song":{"name":"songName", "artist":"songArtist", "id":songID},  "timeAdded":timestamp}
	 */
	this.addSong = function(request) {
		sessionData.push(request);
		
		var sid = request.song.id; // song id (grooveshark song id for now)
    	var uid = request.user.phone; // user id (phone number)
    	
    	// setup new songs / users in their respective arrays
    	if (songs[sid] === undefined) {
//    		this.addSong(request.song);
	 		songs[sid] = request.song;
	 		songs[sid].userRef = new Array();
	 		songs[sid].sessionDataRef = new Array();
	 		songs[sid].count = 0;
	 		songs[sid].minUser = Infinity;
	 		songs[sid].score = 0;
     	}
       	if(users[uid] === undefined) {
//    		this.addUser(request.user);
			users[uid] = request.user;
   			users[uid].songsRef = new Array();
    		users[uid].sessionDataRef = new Array();
    		users[uid].count = 0;
    	}
    	
    	// for all (new and existing) songs / users
    	// for queuing
    	users[uid].count++;
		songs[sid].count++;
		
		// for queuing
		if(songs[sid].minUser > users[uid].count) {
			songs[sid].minUser = users[uid].count;
		}
		
		this.updateScore(sid);
		
		// make song / user data easy to access
		songs[sid].userRef[uid] = users[uid];
		songs[sid].sessionDataRef.push(sessionData[sessionData.length-1]);

		users[uid].songsRef[sid] = songs[sid];
		users[uid].sessionDataRef.push(songs[sessionData.length-1]);
		
		 // add the song to the UI / players (grooveshark)
	    //em.broadcast(new Event("Song", "Requested"), sid);

		 //GS.
		 GS.player.addSongsToQueueAt([sid], GS.player.INDEX_LAST);
	}
	
	this.removeSong = function(songId) {
		var users = this.songs[songId].userRef;
		for (var i = 0; i < users.length; i++) {
			users[i].count--;
			delete users[i].songsRef[songId];
		}
		delete this.songs[songId];
	}
	this.updateScore = function(songId) {
		var song = songs[songId];
		song.score = song.minUser - song.count*1.01;
	}
}

DL.prototype.lastUpdate = 1296297578; // set for epoch of 1/29/11; public var, but should only be set by getData()

/* a method to get data from the server
 * it is currently configured to handle 'addSong' data objects and ignore the rest
 * Example Data Object:
 *     ["addSong":{"user":{"number":"phoneNum"}, "song":{"name":"songName", "artist":"songArtist", "id":songID},  "timeAdded":timestamp}]
 *     note: could have multiple data objects in the main array: [{addSong}, {addSong}, {skipSong}, etc.]
 */
DL.prototype.getData = function() {
    var temp = this; // set a variable so you can reference the DL in the getJSON's callback
    $.getJSON(BASE + "tj/tjclient.php?func=fetchData&session="+this.getSession()+"&lastUpdate="+this.lastUpdate+"&callback=?", function(data) {
    	if (data != "" && data != null && data.length != 0) { // == null also checks for undefined, empty arrays
    		for (var i = 0; i < data.length; i++) {
    			if (data[i].addSong != "" && data[i].addSong != null) { // makes sure that the data is type of 'addSong'
    				temp.addSong(data[i].addSong);
    			}
    		}
    		temp.lastUpdate = data[data.length-1].addSong.time;
    	}
    	em.broadcast(new Event("Get Data", "Completed"));
    });
}
