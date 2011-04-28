<?php

// This is almost verbatim from an example on SimplePie!

// include SimplePie... it's awesome!
require_once('./php/simplepie.inc');

// process codaset blog  with default options...
$feed = new SimplePie();

$feed->set_feed_url('http://codaset.com/thumbdj/thumbdj/blog.atom');

$feed->strip_htmltags(array_merge($feed->strip_htmltags, array('href', 'img')));



// Start SimplePie
$feed->init();

// This makes sure that the content is sent to the browser as text/html and the UTF-8 character set (since we didn't change it).
$feed->handle_content_type();


// Let's begin our XHTML webpage code.  The DOCTYPE is supposed to be the very first thing, so we'll keep it on the same line as the closing-PHP tag.
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
        "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<style type="text/css">
	body {
		font:12px/1.4em Verdana, sans-serif;
		color:#333;
		background-color:#fff;
		width:700px;
		margin:50px auto;
		padding:0;
	}
 
	a {
		color:#326EA1;
		text-decoration:underline;
		padding:0 1px;
	}
 
	a:hover {
		color:#fff;
		text-decoration:none;
	}
 
	div.header {
		border-bottom:1px solid #999;
	}
 
	div.item {
		padding:5px 0;
		border-bottom:1px solid #999;
	}
	</style>
 
</head>
<body>
 
<!-- Maybe put a blog header in later...
	<div class="header">
	</div>
 -->

	<?php
	/*
	Here, we'll loop through all of the items in the feed, and $item represents the current item in the loop.
	*/
	foreach ($feed->get_items() as $item):
	?>
 
		<div class="item">
			<h2><?php echo $item->get_title(); ?></h2>
			<p><?php echo $item->get_description(); ?></p>
			<p><small>Posted on <?php echo $item->get_date('j F Y | g:i a'); ?> by <?php echo $item->get_author()->get_name() ?></small></p>
		</div>
 
	<?php endforeach; ?>
 
</body>
</html>

