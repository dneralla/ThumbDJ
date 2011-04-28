<!doctype HTML>
<html>
	<head>
		<title>Text Emulator Page</title>
		<script type="text/javascript" src="js/jquery-1.4.3.min.js"></script>
		<script type="text/javascript">
		var PATH = window.location.pathname;
		PATH = PATH.split("/");
		PATH[PATH.length-1] = "";
		PATH = PATH.join("/");
		document.write('<base href="http://' + window.location.hostname+PATH+'/" />');

			$(function(){
				$("#connectSession").click(function(){
					if ($("#phone").val().length == 11) {
						$("#convo").prepend("<li>"+Date()+" | "+$("#phone").val()+" | "+"#"+$("#session").val()+"</li>");
						$.get("tj/tjserver.php?From="+$("#phone").val()+"&Body="+"%23"+$("#session").val(), function(response) {
							$("#convo").prepend("<li>"+Date()+" | tjserver | "+$(response).children().text().trim()+"</li>");
						});
					} else {
						alert("Input Error for 'Connecting to a Session'.\nYour phone number wasn't correctly formatted.\nPlease try again.");
					}
				});
				$("#sendMessage").click(function(){
					if ($("#phone").val().length == 11) {
						$("#convo").prepend("<li>"+Date()+" | "+$("#phone").val()+" | "+$("#message").val()+"</li>");
						$.get("tj/tjserver.php?From="+$("#phone").val()+"&Body="+$("#message").val(), function(response){
							$("#convo").prepend("<li>"+Date()+" | tjserver | "+$(response).children().text().trim()+"</li>")
						});
					} else {
						alert("Input Error for 'Connecting to a Session'.\nYour phone number wasn't correctly formatted.\nPlease try again.");
					}
				});
			});
		</script>
	</head>
	<body>
		<h1>About the Text Emulator Page</h1>
		<p>This page is used to emulate how a user connects to ThumbDJ via text message for testing purposes.  It is designed to reduce costs by lessening the number of texts that we actually send.  Before pushing the development repo to live, someone should actually send real text messages as a final test.</p>
		<h1>Connecting to a Session</h1>
		<label for="phone">Phone Number (11 digit): <input type="text" name="phone" id="phone"></label><br>
		<label for="session">Session (no #): <input type="text" name="session" id="session"></label><br>
		<button id="connectSession">Connect to Session</button>
		<h1>Sending a Text</h1>
		<label for="message">Message: <input type="text" name="message" id="message"></label>
		<button id="sendMessage">Send Message</button>
		<h1>The Conversation</h1>
		<ul id="convo">
			<li><strong>Time | From | Message</strong></li>
		</ul>
	</body>
</html>