var myDL;
var queue;

var em = new EventManager();

// add a div so we can make dialogs
$("body").append("<div id='dialog' title='Dialog Title' style='visible:none'>I'm a ThumbDJ dialog!</div>");

// Ask user for a session and see if it's in use
function askSession() {
    var session = prompt("Enter a ThumbDJ session name:", "");
	if (session != undefined) {
	    $.getJSON(BASE + "tj/tjclient.php?func=setSession&session=" + session + "&callback=?", function(worked) {
	    	if (worked == "true") {
	    		initThumbDJ(session);
	    	    message("Your ThumbDJ Session", "Write this down! Text \"#" + myDL.getSession() + "\" to 217-645-6542 to get started! (you only need to do this once per phone)", true);
	    	    myDL.getData();
	    	} else if (worked == "false"){
	    	    alert("The ThumbDJ session name '"+session+"' has already been taken.\nPlease pick a different ThumbDJ session name.");
	    	    askSession();
	    	} else if (worked == "invalid") {
				alert("Session name is invalid, try another one.");
	    	    askSession();
			}
	    });
    } else {
    	window.thumbDJ = false;
    }
}
askSession();

function initThumbDJ(session) {
	myDL = new DL(session); // session name
	myQueue = new Queue(myDL);
	em.subscribe(new Subscription([new Event("Get Data", "Completed")], function(events, data) {myDL.getData(events, data);}, true));
	em.subscribe(new Subscription([new Event("Song", "Requested")], function(events, data) {myQueue.onSongRequest(events, data);}, true));
	$.subscribe("gs.player.completed", function(){queue.onSongCompletion();});
}

// setup the jQuery Dialog (default values)
function message(title, text, modal) {
/* 	$("#dialog").attr("title", title); // redundent since I set the title when I make the dialog */
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

function groovesharkUI() {
	$("#dialog").text('');
	$("#dialog").html('');
	$("#dialog").attr("title", "ThumbDJ Admin Panel");
	$("#dialog").dialog({
		title: "ThumbDJ Admin Panel",
		modal: false,
		buttons: {},
		minWidth: 600,
		minHeight: 400
	});
}

// UI
// 1. make session getter into 1 tab
// 2. make control panel to block users / review usage
// 3. pretty shit
