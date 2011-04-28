

//Prototype functions
//
//I am slightly wary of this binary search
Array.prototype.binarySearch = function(elem, retIndex){
    var hi = this.length, lo = -1, med;
    while(hi - lo > 1)
        if(this[med = hi + lo >> 1] > elem) hi = med;
        else lo = med;
    return this[hi] != elem ? retIndex ? hi : -1 : hi;
};



//Once Taylor's Data layer is done, we will actually be doing most of the 
//song ordering processing on the datalayer side.
//Here, we only need to initialize the data layer, register a function
//to be a listener with the data layer, and work on the song
//objects that the data layer sends us. Each song object will have a 
//score
//score is calculated as: Score = min(RC) - SC, where RC is the number of requests of anyone who has requested this song, and SC is the number of times this song has been requested.
//E.G. First request of a song : RC = [0], SC = 1, so score = -1
//One requestor of this song has made 3 requests, the other 2, so RC = [3,2] SC = 2 so score = 0
//Set up data layer
dataLayer.init("tj/tjclient.php");

//Global variables
var queue =[];
var scores = [];

//init gsAPI wrapper here
gsAPI.init();

//Callback function called when a song request is made
var onSongsRequested = function(songs) {
	for(song in songs) {
		var i = scores.binarySearch(song.score, true);
		queue.splice(i,0,song);
		scores.splice(i,0,song.score);
		gsAPI.addSongsAt(song.Id, i);
	}
}

//Register a listener with the data layer
dataLayer.addListener("onSongsRequested", onSongsRequested);

