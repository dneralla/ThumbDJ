<?php
	require ("./search.php");
	$ini_array = parse_ini_file("../thumbdj.ini");
	$search;
	$search = new search();

	function dumpSearch ($query) {
		global $search;
		echo "Searching for:<br>\n ".$query."<br><br>\n\n";
		echo "Results:<br>\n";
		var_dump($search->gsSearch($query));
		echo "<br><br>\n\nPath:<br>\n".$search->getLastPath();
		echo "<br><br><br>\n\n\n";
	}
	
	dumpSearch("Thriller");
	dumpSearch("shakira");
	dumpSearch("modest mouse");
	dumpSearch("float on by modest mouse");
	dumpSearch("crystalized");
	dumpSearch("crystallized");
	dumpSearch("Thug Cello");
