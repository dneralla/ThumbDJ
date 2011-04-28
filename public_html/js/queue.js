Array.prototype.binarySearch = function(elem, retIndex, value){
    var hi = this.length, lo = -1, med;
    while(hi - lo > 1) {
        if(value(this[med = hi + lo >> 1]) > value(elem)) {hi = med;}
        else {lo = med;}
	 }
     return value(this[hi]) != value(elem) ? retIndex ? hi : -1 : hi;
};

function Queue(DL) {
	var queue = new Array();
	var songIndex = new Object(); // [sid] = queueIndex, maps songId to the queue position
	
	var myDL = DL;
	this.getDL = function() {
		return myDL;
	}
	
	var playCount = 0;
	this.getPlayCount = function() {
		return playCount;
	}
	this.incrementPlayCount = function() {
		return playCount++;
	}

	Queue.prototype.onSongCompletion = function() {
		this.incrementPlayCount();
		queue.shift();

		var song = this.gs.getSong(0);
		myDL.removeSong(song.SongID);
		this.gs.removeSong(0);
	}
	Queue.prototype.onSongRequest = function(events, songId) {
		var newIndex;
		if (queue.length > 0) {
/*
			newIndex = queue.binarySearch(this.getDL().getSong(songId), true, function(song) {
				if (song == undefined) {
					return undefined;
				} else {
					return song.score;
				}
			});
*/
			newIndex = -1;
			for (var i = 0; i < queue.length; i++) {
				if (this.getDL().getSong(songId).score < queue[i].score) {
					newIndex = i;
					break;
				}
			}
			if (newIndex == -1) {
				newIndex = queue.length;
			}
			if (newIndex == 0 && queue.length != 0) { // edge case- prevents song from replacing currently playing song
				newIndex = 1;
			}
		} else {
			newIndex = 0;
		}
		
		if (songIndex[songId] != undefined) {
			queue.splice(songIndex[songId], 1); // removes the song from it's old position
			queue.splice(newIndex, 0, this.getDL().getSong(songId)); // adds the song into the new position, 0 means don't delete any positions
			this.gs.moveSong(songIndex[songId], newIndex);
			songIndex[songId] = newIndex;
		} else {
			queue.splice(newIndex, 0, this.getDL().getSong(songId)); // adds the song into the new position, 0 means don't delete any positions
			this.gs.insertSong(songId, newIndex);
			songIndex[songId] = newIndex;
		}
	}
}
Queue.prototype.gs = new gsWrapper();

function gsWrapper() {

}
/* a GS wrapper method to add songs to the GS queue
 * 
 * @parameter songId - the GS song id
 * @parameter index - (a number) the position in the grooveshark queue to insert the song at
 * 		0 is the front of the queue, see grooveshark documentation for other "special numbers"
*/
gsWrapper.prototype.insertSong = function(songId, index) {
	GS.player.addSongsToQueueAt([songId], index);
}
/* a GS wrapper method to move a song's position the GS queue
 * 
 * @parameter from - (a number) the position in the grooveshark queue that the song is at
 *		0 is the front of the queue
 * @parameter to - (a number) the position in the grooveshark queue to move the song to
 * 		0 is the front of the queue, see grooveshark documentation for other "special numbers"
*/
gsWrapper.prototype.moveSong = function(from, to) {
	alert('from = '+from)
	GS.player.moveSongsTo([this.getSongByPos(from).queueSongID], to);
}
/* a GS wrapper method to remove songs from the GS queue
 * 
 * @parameter index - (a number) the position in the grooveshark queue to insert the song at
 * 		0 is the front of the queue, see grooveshark documentation for other "special numbers"
*/
gsWrapper.prototype.removeSong = function(index) {
	GS.player.removeSongs(this.getSongByPos(index).queueSongID); // might need to wrap param in brackets ???
}
/* a GS wrapper method to play the song at the front of the queue
*/
gsWrapper.prototype.playSong = function() {
	GS.player.playSong();
}
/* a GS wrapper method to get the gs song objects
 *
 * @parameter pos - the position in the gs queue to get the song at
*/
gsWrapper.prototype.getSongByPos = function(pos) {
	return GS.player.queue.songs[pos];
}