/* EXAMPLE UI FILE
 * 	Called in onload function, so no need to wrap it in that
 *  BASE is already set, use it with relative linking to call other files.
 * 	Each UI is responsible for creating its own Data Layer, which is used to communicate with the server.
 *	See this example for how to do that, and what functions you need to alter.
*/

var myDL;

// add a div so we can make dialogs
$("body").append("<div id='dialog' title='Dialog Title' style='visible:none'>I'm a ThumbDJ dialog!</div>")

// get the script that makes inheritance easy
// need to put in here instead of in DL.js due to call back issues
$.getScript(BASE+"js/inheritance.js", function() {
	// get the DL	
	$.getScript(BASE + 'js/DL.js', function() {
		// make grooveshark specific Data Layer Class by extending the original
		var groovesharkDL = DL.extend({
			autoAdd: true,
	
			// @override
			init: function(session, autoAdd) {
				this._super(session);
				
				this.autoAdd = autoAdd;
			},
	
			// @override
			addData: function(toAdd) {
				this._super(toAdd);
				
				if (this.autoAdd) {
					for(var i = 0; i < toAdd.length; i++) {
						this.addSongs(toAdd[i].songids);
					}
				}
			},
			
			// expects an array like this: ["song1,song2,etc"]
			addSongs: function(songs) {
				$("#gsliteswf")[0].addSongsByID(songs, false);
			}
		});
	
		//Poll server php file for session name
		$.getJSON(BASE + "tj/tjclient.php?func=getSession&callback=?", 
		    function(sessionName) {
		    	// create the data layer object that the UI will use
				// remember- the above code is used to make a new class, not an object
				myDL = new groovesharkDL(sessionName, true); // session name, autoadd
		    
		    	alert("Write this down! Text \"#" + myDL.session + "\" to 217-645-6542 to get started! (you only need to do this once/phone)");
		    	//message("Getting Started with ThumbDJ", "Write this down! Text \"#" + myDL.session + "\" to 217-645-6542 to get started! (you only need to do this once/phone)", true);
		    	myDL.getData();
		    	
		    	// do other stuff here
		});
	});
});

// setup the jQuery Dialog (default values)
function message(title, text, modal) {
	$("#dialog").attr("title", title); // redundent since I set the title when I make the dialog
	$("#dialog").text(text);
	$("#dialog").dialog({
		title: title,
		modal: modal,
		buttons: {
			"Ok": function() {
				$(this).dialog("close");
			}
		},
		minWidth: 250, // default is 150
		minHeight: 150 // default is 150
	});
}
