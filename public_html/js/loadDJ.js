if (!window.thumbDJ) {
	var BASE = document.BASE;
	
	$(document).ready(function(){
		//load jquery
		$("head").append($("<link rel='stylesheet' type='text/css' href='"+BASE+"js/jquery-ui/css/ui-lightness/jquery-ui-1.8.6.custom.css' />"));
		
		$.getJSON(BASE+'tj/getPlatform.php?platform='+window.location.hostname+'&callback=?', function(data) {
			if (data["platform"] == "fail") {
				alert("You are not on a supported ThumbDJ platform.\nMay I suggest grooveshark.com.")
			} else {
				$.getScript(BASE+'js/jquery-ui.js', function() {
					$.getScript(BASE+'js/eventManager.js', function() {
						$.getScript(BASE + 'js/DL.js', function() {
							$.getScript(BASE+'js/queue.js', function() {
								$.getScript(BASE+"js/"+data["platform"]);
							});
						});
					})
				});
			}
		});
	});
	
	window.thumbDJ = true;
} else {
	alert("You have already loaded ThumbDJ.  To reload ThumbDJ refresh the page and click on the bookmarklet.");
}