<?php

require_once('vendor/autoload.php');
# use PHPExcel\Spreadsheet;
# use PHPExcel\Writer\Xls;
# use PhpOffice\PHPExcel\IOFactory;
# use PHPExcel\Reader;
# use PHPExcel\Reader\IReader;
# use PHPExcel\Settings;
# use PHPExcel\Worksheet\Worksheet;
# use PHPExcel\Calculation\Calculation;
# use PHPExcel\Calculation\Functions;
# use PHPExcel\Cell\Coordinate;
# use PHPExcel\Chart;


# globals from get_partners(); pairgames used in get_personal_results()
$pairgames = $teamcount = $paircount = $pardname = array();
$total_played = $total_teamcount = $total_paircount = 0;

# globals from make_spreadsheet(); 
$NSmpt = $EWmpt = array();
# Tally the mtchpts from POs, player-declaring, pard-declaring, & defending for each board played
$mpts_from_PO = $mpts_by_player_declaring = $mpts_by_pard_declaring = $mpts_by_defending = $Tot_mpts = 0;
$player_declaring_count   = $pard_declaring_count   = $declaring_count = $defending_count = $GoodDeclrCount = $GoodDefCount = 0;
$PO_count     = $PO_judgment = 0;

###########################    function spitlist (debug only)   ########################################
#                              	- called from display_pard_details
#
function spitlist ($scorelist) {
	# for debug use
	# scorelist must be an assoc array of scores

	foreach ($scorelist as $strt => $ary) {
		echo "strat $strt score list: ";
		$sz=count($ary);
			for ($x=0;$x<$sz;$x++) {
				echo "$ary[$x], ";
			}
		echo "<br>";
	}
}


###########################    function spitlist (debug only)   ########################################
#                              	- called from display_pard_details
#
function spitlist2 ($scorelist) {
	# for debug use
	# scorelist must be a simple array of scores

	echo "score list: ";
	foreach ($scorelist as $scr ) {
		echo "$scr ";
	}
	echo "<br>";
}

###########################    function rate    ########################################
#                              	- called from display_pard_details
#
function rate ($score, $stratstring, $scores) {
	# The array $scores might be an associative array of scores like this:
	# $Flt_scores('A', scr1, scr2, ... scrn) or $Flt_strat_scores('B', ....)
	#
	# Sometimes we want to merge 2 or 3 flights, then search.  The second argument, $stratstring, 
	# contains 1-3 characters: A, B and C.  Split the string into characters, then assemble the 
	# desired lists, if multiple lists are desired, and sort ascending.
	#
	# If the simple array 'allscores' is passed in, the stratstring is set to ''.  Detect that 
	# situation, then bypass the inner array extraction process.
	# Return the rating as a fraction, and the rank (finish) as a string
		
	$scorelist = array();

	if ($stratstring == "") {
		$scorelist = $scores; # simple array
	} else {
		$stratlist = str_split( trim($stratstring) );
		# merge possibly multiple flights of scores
		foreach ($stratlist as $st) {
			foreach ($scores as $strat => $array_of_scores) {
				if ( $strat == $st ) {
					$scorelist = array_merge($scorelist, $array_of_scores);
				}
			}
		}
	}
	
	$nscores=count($scorelist);
	sort($scorelist); # ascending

	$wins = 0; 
	$ties = 0;
	$rating= 0;

	foreach ($scorelist as $s) {
		if ($score > $s) {
			$wins++;
		} 
		elseif ($s == $score) {
			$ties++;
		} 
		elseif ($s > $score) {
			break; # we're done because the rest of the scores are greater than my score
		}
	}
	# If you tie with 1 or more others, your rating increases fractionally; if you tie only once, the 
	# tie is with your own score.  That's one win. If you tie with more than 1 other score, construct 
	# a tie for ith/jth positions.  If you didn't tie or beat any of the scores, your rating is 
	# (necessarily) 0.  Should never happen.  The score-to-be-rated should be in the list, so you should always 
	# find at least a tie, for a rating of 1.  A zero rating indicates a mistake.

	if ($ties>1) {
		$rating=sprintf("%8.1f",($wins+(1/$ties))) ;
		$lower_rank  = $nscores-$wins-$ties+1;
		$upper_rank  = $nscores-$wins;
		$rank        = "$lower_rank" . "/" . "$upper_rank" ; # 2/3 or 3/5
	} 
	elseif ($ties==1) { # one tie
		$rating = $wins+1;
		$rank   = $nscores-$wins;
	} 
	else { # no ties, no wins - should never happen!
		$rating = 0;
		$rank   =".";
	}

	$return_values=array($rating, $rank, $nscores);
	# debug
	#echo "   rated $score against $nscores strat $stratstring scores: rank=$rank, rating=$rating <br>";
	return $return_values;
}


###########################    function senddatat    ######################
#                              	- called from history and display_pards
#
function senddata($msg, $email,$subj) {
	if (!$email) {
		return;
	} else {
	$header = "From: rick.garvin@acbridge.org" ;
	mail($email, $subj, $msg, $header);
	echo "<p class='e'>Your results were sent.</p>"; echo "<br>";
	}
}

###########################    function playernum    ######################
#                              	- called from home page and several others
#
function playernum($pname) { # provide a playername, get back the player's number, or a list of possible name matches
# Nightmare return scenarios - 
# If one match is found return an array with 4 elements: pnum, name, city, st
# If > 1 match, return an assoc array with an array of possible matches, each array like:
# tmp[pnum1] = name1, city1, st1  N.B. only THREE elements
# tmp[pnum2] = name2, city2, st2
# tmp[pnum3] = name3, city3, st3
#  ...
# Then leave it to the returning code to figure out which kind of array it got.  Yuck.


	$name = $fname = $first = $last = $trunlast = "" ; 
	$city = $st = "";
	$lastname_sql = $pnum_sql1 = $pnum_sql2 = $trun_sql = "";
	$pnum = $numnames = 0 ;
	$tmp = $list = $flname = array(); # candidate pnum=>name (could be >1)

	// Create connection
#	$conn = mysqli_connect($servername, $username, $password, $dbname);
	global $conn;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}
	# lc, and split the name-to-search-for, then search for last name first.  
	# If find 1 match, yer done, if more than 1, look for a first name match.
	# if still > 1, make a list, and return the list of possible names.  
	# If 0, try again.
	$pname = strtolower($pname);
	$pattern = "/\s/";
	$flname = preg_split($pattern, $pname); # explode is useless
	$numnames = sizeof($flname);
	$first = $flname[0];
	$last  = $flname[$numnames-1];

	# last name only search
	# debug
	# echo "Searching for pname: $pname, $numnames names: first:$first last:$last <br>";
	$lastname_sql = 
		"SELECT pnum, first, last, city, state FROM pnamepnumCityStCntry WHERE last LIKE '".$last."%' ;";
	$result = mysqli_query($conn, $lastname_sql);
	$nmatches = mysqli_num_rows($result) ;
	#debug
	# echo "Found $nmatches for $last<br>";

	if (mysqli_num_rows($result) == 1) { # found 1 match yay
  	$row = mysqli_fetch_assoc($result) ; # don't need to loop 
		$pnum = $row["pnum"];
		$name = trim($row["first"]) . " " . trim($row["last"]);
		$city = trim($row["city"]);
		$st   = trim($row["state"]);
		# debug
		# echo "Found single player named $name $pnum <br>";
		array_push($tmp, $pnum,$name,$city,$st);
		return $tmp; # return 1 simple array with a list of 4 elements

	} 
	elseif (mysqli_num_rows($result) > 1) { 
	$nmatches = mysqli_num_rows($result) ;
	# found >1 match; look for first name match, but use RE
	#debug
	# echo "Found $nmatches for $last<br>";
		$firstname_matches = 0 ;
  	while ($row = mysqli_fetch_assoc($result)) {
			$fname = strtolower($row["first"]) ;
			$pattern = "/$first/i" ;
			$tmp = array(); # clear it each time thru, otherwise 'push' accumulates players
			#debug
			# echo "Matching $first and $fname?<br>";
			if (preg_match($pattern, $fname)) { # match!  return it/them
				$firstname_matches++ ;
				$pnum = $row["pnum"];
				$name = trim($row["first"]) . " " . trim($row["last"]);
				$city = trim($row["city"]);
				$st   = trim($row["state"]);
				if (!$city) { $city = '?';}
				if (!$st) { $st = '?';}
				# debug
				# echo "Found player $name of $city, $st - #$pnum <br>";
				# return a list of arrays just in case > 1 first name match.
				# check below for just one match (yay!)
				array_push($tmp, $name,$city,$st);
				$list[$pnum] = $tmp; # assoc array of lists, whose keys are $pnum
			}
		}
		# debug
		# echo "Found $firstname_matches matches <br>";
		if ($firstname_matches == 1 ) {
			$tmp = array(); # clear it, then add the FOUR entries, since you added only 3 in the above 
											#	while loop just in case you needed to send a list of arrays
			array_push($tmp, $pnum,$name,$city,$st);
			return $tmp; # return 1 simple array with a list of 4 elements

		} elseif ($firstname_matches > 1) {
			return $list; # list of possible first name matches

		} 
		# else no matches !!
		# Are you still here?  Hmm, matched last name, but no first name matched.
		# Send the list of matches back and ask user to select one, or start over
			
  	while ($row = mysqli_fetch_assoc($result)) {
			$tmp = array(); # clear it each time thru, otherwise 'push' accumulates players
			$pnum = $row["pnum"];
			$name = trim($row["first"]) . " " . trim($row["last"]);
			$city = trim($row["city"]);
			$st   = trim($row["state"]);
			# debug
			# echo "Found several players last name: $name $pnum but no first name $first match <br>";
			array_push($tmp, $name,$city,$state);
			$list[$pnum] = $tmp; # assoc array of lists, whose keys are $pnum
		}
		return $list; # list of possible matches of last name, but none matched first name!

	} else { # no last name match - truncate the last name to 1st 4 chars and query for that
		$trunlast = substr($last, 0, 4);
		$trun_sql = 
			"SELECT pnum, first, last, city, state FROM pnamepnumCityStCntry WHERE last LIKE '%".$trunlast."%' ;";
		$result = mysqli_query($conn, $trun_sql);

		if (mysqli_num_rows($result) >= 1) { # found 1 or more matches yay (I guess)
  		while ($row = mysqli_fetch_assoc($result)) {
				$tmp = array(); # clear it each time thru, otherwise 'push' accumulates players
				$pnum = $row["pnum"];
				$name = trim($row["first"]) . " " . trim($row["last"]);
				$city = trim($row["city"]);
				$st   = trim($row["state"]);
				# debug
				# echo "Found truncated player: $name $pnum <br>";
				array_push($tmp, $name,$city,$state);
				$list[$pnum] = $tmp; # assoc array of lists, whose keys are $pnum
			}
			return $list; # list of possible matches

		}
		else { # no truncated matches either. We don't know this person, or anyone like them!  Try again.
			echo "Found no one with last name of $last, or even $trunlast.  $pname not found <br>";
			return NULL;

		}
	}
	echo "$pname REALLY not found <br>";
	return NULL;

}

###########################    function playername    ######################
#                              	- called from home page 
#
function playername($pnum) { # provide a playernumber, get back the player's name

	global $conn;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}

	$name_sql = 
		"SELECT FIRST_NAME, LAST_NAME FROM mplistwnames WHERE PLAYER_NO =$pnum ;" ;
#		"SELECT first, last FROM pnamepnumCityStCntry WHERE pnum='$pnum' ;" ;

	$result = mysqli_query($conn, $name_sql);
	if (mysqli_num_rows($result) > 0) {
		$row = mysqli_fetch_assoc($result); 
		return $row["FIRST_NAME"] . " " . $row["LAST_NAME"];
#		return $row["first"] . " " . $row["last"];

	} else {
		echo "Name not found for player $pnum.";echo "<br>";
		return NULL;

	}
}

###########################    function history    ######################
#                              	- called from home page 
#
function history($pnum,$email,$send) {

	global $conn;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}

	$history_sql = 
		"SELECT count(GameId) FROM players WHERE pnum2=$pnum OR pnum1=$pnum OR pnum3=$pnum or pnum4=$pnum;";

	$result = mysqli_query($conn, $history_sql);

	echo "<h3>Number of times ".playername($pnum)." has played since 2010</h3>";
	if (mysqli_num_rows($result) > 0) {
  	// output data of each row
  	$row = mysqli_fetch_assoc($result) ;
		$msg = 
			"player ".playername($pnum)." ($pnum) : " . "game count=" . $row["count(GameId)"]."\r\n" ;
    echo "<p class='e'>$msg</p>"; echo "<br>";

		if ($send=="yes") {
			$subj="Bridge WebSQL playing history";
			senddata($msg, $email,$subj);
  	}
	} else {
  	echo "<p class='e'>player $pnum playername($pnum) not found</p>";
	}
}

###########################    function get_partners (called from home page)   ######################
#															 returns array $pardcount but also assigns many globals, including 
#															 $pardcount (yes, I'm embarrassed).  Sue me. 
#                              Also creates assoc array $pairgames containing all pair games 
#                              played by player: pairgames[$GameId] = ($pnum1, $pnum2, $player_pair_team,$club)
#                              Player may be pnum1 or pnum2, but it's important to know which later.
#
function get_partners($pnum) {

	# global $total_played, $total_teamcount, $total_paircount, $teamcount, $paircount, $pardname, $have_pard_list;
	global $total_played, $total_teamcount, $total_paircount, $teamcount, $paircount, $have_pard_list;
	global $pairgames;

	global $conn;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}

	# cases where: player1 played with player2, or player3, or player4
	$partner_sql1  = "SELECT COUNT(pnum2), pnum2, player2, club FROM players WHERE pnum1=$pnum and player3<>'' GROUP BY pnum2 ORDER BY COUNT(pnum2) DESC ;";
	$partner_sql2  = "SELECT COUNT(pnum3), pnum3, player3, club FROM players WHERE pnum1=$pnum and pnum3>0 GROUP BY pnum3 ORDER BY COUNT(pnum3) DESC ;";
	$partner_sql3  = "SELECT COUNT(pnum4), pnum4, player4, club FROM players WHERE pnum1=$pnum and pnum4>0 GROUP BY pnum4 ORDER BY COUNT(pnum4) DESC ;";

	# cases where: player2 played with player1, or player3, or player4
	$partner_sql4  = "SELECT COUNT(pnum1), pnum1, player1, club FROM players WHERE pnum2=$pnum and player3<>'' GROUP BY pnum1 ORDER BY COUNT(pnum1) DESC ;";
	$partner_sql5  = "SELECT COUNT(pnum3), pnum3, player3, club FROM players WHERE pnum2=$pnum and pnum3>0 GROUP BY pnum3 ORDER BY COUNT(pnum3) DESC ;";
	$partner_sql6  = "SELECT COUNT(pnum4), pnum4, player4, club FROM players WHERE pnum2=$pnum and pnum4>0 GROUP BY pnum4 ORDER BY COUNT(pnum4) DESC ;";

	# cases where: player3 played with player1, or player2, or player4
	$partner_sql7  = "SELECT COUNT(pnum1), pnum1, player1, club FROM players WHERE pnum3=$pnum GROUP BY pnum1 ORDER BY COUNT(pnum1) DESC ;";
	$partner_sql8  = "SELECT COUNT(pnum2), pnum2, player2, club FROM players WHERE pnum3=$pnum GROUP BY pnum2 ORDER BY COUNT(pnum2) DESC ;";
	$partner_sql9  = "SELECT COUNT(pnum4), pnum4, player4, club FROM players WHERE pnum3=$pnum GROUP BY pnum4 ORDER BY COUNT(pnum4) DESC ;";

	# cases where: player4 played with player1, or player2, or player3
	$partner_sql10 = "SELECT COUNT(pnum1), pnum1, player1, club FROM players WHERE pnum4=$pnum GROUP BY pnum1 ORDER BY COUNT(pnum1) DESC ;";
	$partner_sql11 = "SELECT COUNT(pnum2), pnum2, player2, club FROM players WHERE pnum4=$pnum GROUP BY pnum2 ORDER BY COUNT(pnum2) DESC ;";
	$partner_sql12 = "SELECT COUNT(pnum3), pnum3, player3, club FROM players WHERE pnum4=$pnum GROUP BY pnum3 ORDER BY COUNT(pnum3) DESC ;";

	# pairs only - 
	# weed out Indy games from sql41 - because no partner!
	$partner_sql41 = "SELECT COUNT(pnum2), pnum2, player2, club FROM players WHERE pnum1=$pnum and pnum2>0 and player3='' GROUP BY pnum2 ORDER BY COUNT(pnum2) DESC ;";
	$partner_sql42 = "SELECT COUNT(pnum1), pnum1, player1, club FROM players WHERE pnum2=$pnum and player3='' GROUP BY pnum1 ORDER BY COUNT(pnum1) DESC ;";

	# N.B. These next 2 queries return a unique set of values, different from the ones above!!
	# pairs only - save GameId & player_pair_team ID for future 'results' queries; add 'movement' to weed out HomeStyle pairs
	$partner_sql81 = "SELECT players.GameId, pnum1, pnum2, players.player_pair_team, events.movement, players.club FROM players JOIN events ON events.GameId=players.GameId WHERE pnum1=$pnum and pnum2>0 and player3='' ;";
	$partner_sql82 = "SELECT players.GameId, pnum1, pnum2, players.player_pair_team, events.movement, players.club FROM players JOIN events ON events.GameId=players.GameId WHERE pnum2=$pnum and player3='' ;";

	# pairs-only events
	$result[41] = mysqli_query($conn, $partner_sql41); # player is player1, sits N or E, pard sits S or W
	$result[81] = mysqli_query($conn, $partner_sql81); #  returns : GameId, NEpair#, movement !!
	$result[42] = mysqli_query($conn, $partner_sql42); # player is player2, sits S or W, pard sits N or E
	$result[82] = mysqli_query($conn, $partner_sql82); #  returns : GameId, SWpair#, movement !!
	# team games
	$result[1]  = mysqli_query($conn, $partner_sql1);
	$result[2]  = mysqli_query($conn, $partner_sql2);
	$result[3]  = mysqli_query($conn, $partner_sql3);
	$result[4]  = mysqli_query($conn, $partner_sql4);
	$result[5]  = mysqli_query($conn, $partner_sql5);
	$result[6]  = mysqli_query($conn, $partner_sql6);
	$result[7]  = mysqli_query($conn, $partner_sql7);
	$result[8]  = mysqli_query($conn, $partner_sql8);
	$result[9]  = mysqli_query($conn, $partner_sql9);
	$result[10] = mysqli_query($conn, $partner_sql10);
	$result[11] = mysqli_query($conn, $partner_sql11);
	$result[12] = mysqli_query($conn, $partner_sql12);

	$withpnum1= array(4,7,10,42,82);
	$withpnum2= array(1,8,11,41,81);
	$withpnum3= array(2,5,12);
	$withpnum4= array(3,6,9);

	# build some linked associative arrays from the returned values
	# $pardcount[pardpnum] - useful later in functions display_pards & display_pard_details
	# $pardname [pardpnum]
	# $paircount[pardpnum]
	# $teamcount[pardpnum]
	
	# save the GameId and pairId from pairs games 
	# $pairgames[GameId] = pnum1, pnum2, pairId, club
	
	# initialization
	# $pardcount = $pardname = $paircount = $teamcount = array(); 
	$pardcount = $paircount = $teamcount = array(); 
	$total_played=0;

	foreach ($withpnum1 as $result_select) {
		if (mysqli_num_rows($result[$result_select]) > 0) {
  		while($row = mysqli_fetch_assoc($result[$result_select])) {
				$pardcount[$row["pnum1"]] += $row["COUNT(pnum1)"];
				# $pardname [$row["pnum1"]]  = $row["player1"];
				$total_played 						+= $row["COUNT(pnum1)"];
				if ($result_select == 42) {
					$paircount[$row["pnum1"]] += $row["COUNT(pnum1)"]; 
					$total_paircount 					+= $row["COUNT(pnum1)"]; 
					#echo "paircount[" . $row["pnum2"] . "] += " . $row["COUNT(pnum2)"]. "<br>";
				}
				elseif ($result_select == 82) {
					if (! preg_match('/HomeStyle/i', $row["movement"])) {
						# echo "GameId=". $row["GameId"]. "pair id=". $row["player_pair_team"]. "movement=". $row["movement"]. "<br>";
						$tmp = array ($row["pnum1"], $row["pnum2"], $row["player_pair_team"], $row["club"]);
						$pairgames[$row["GameId"]] = $tmp; # save for later pairs queries; player is pnum2
					}
				}
				else { # 4, 7 or 10
					$teamcount[$row["pnum1"]] += $row["COUNT(pnum1)"]; 
					$total_teamcount          += $row["COUNT(pnum1)"]; 
				}
				# debug
				#echo "player1, as pard " . $row['player1'] . " played, " . $row["COUNT(pnum1)"] . " times<br>" ;
			}
		}
	}

	foreach ($withpnum2 as $result_select) {
		if (mysqli_num_rows($result[$result_select]) > 0) {
  		while($row = mysqli_fetch_assoc($result[$result_select])) {
				$pardcount[$row["pnum2"]] += $row["COUNT(pnum2)"];
				# $pardname [$row["pnum2"]]  = $row["player2"];
				$total_played += $row["COUNT(pnum2)"];
				if ($result_select == 41) {
					$paircount[$row["pnum2"]] += $row["COUNT(pnum2)"]; 
					$total_paircount 					+= $row["COUNT(pnum2)"]; 
					#echo "paircount[" . $row["pnum2"] . "] += " . $row["COUNT(pnum2)"]. "<br>";
				}
				elseif ($result_select == 81) {
					if (! preg_match('/HomeStyle/i', $row["movement"])) {
						# echo "GameId=". $row["GameId"]. "pair id=". $row["player_pair_team"]. "movement=". $row["movement"]. "<br>";
						$tmp = array ($row["pnum1"], $row["pnum2"], $row["player_pair_team"], $row["club"]);
						$pairgames[$row["GameId"]] = $tmp; # save for later pairs queries; player is pnum1
					}
				}
				else { # 1, 8 or 11 
					$teamcount[$row["pnum2"]] += $row["COUNT(pnum2)"]; 
					$total_teamcount          += $row["COUNT(pnum2)"]; 
				}
				# debug
				# echo "player2, pard " . $row['player2'] . " found <br>" ;
			}
		}
	}

	# only team games
	foreach ($withpnum3 as $result_select) {
		if (mysqli_num_rows($result[$result_select]) > 0) {
  		while($row = mysqli_fetch_assoc($result[$result_select])) {
				$pardcount[$row["pnum3"]] += $row["COUNT(pnum3)"];
				$teamcount[$row["pnum3"]] += $row["COUNT(pnum3)"]; 
				$total_teamcount          += $row["COUNT(pnum3)"]; 
				# debug
				# echo "teamcount[" . $row["pnum3"] . "] += " . $row["COUNT(pnum3)"]. "<br>";
				# $pardname [$row["pnum3"]]  = $row["player3"];
				# echo "player3, pard " . $row['player3'] . " found <br>" ;
				$total_played += $row["COUNT(pnum3)"];
			}
		}
	}

	# only team games
	foreach ($withpnum4 as $result_select) {
		if (mysqli_num_rows($result[$result_select]) > 0) {
  		while($row = mysqli_fetch_assoc($result[$result_select])) {
				$pardcount[$row["pnum4"]] += $row["COUNT(pnum4)"];
				$teamcount[$row["pnum4"]] += $row["COUNT(pnum4)"]; 
				$total_teamcount          += $row["COUNT(pnum4)"]; 
				# debug
				# echo "teamcount[" . $row["pnum4"] . "] += " . $row["COUNT(pnum4)"]. "<br>";
				# $pardname [$row["pnum4"]]  = $row["player4"];
				# echo "player4, pard " . $row['player4'] . " found <br>" ;
				$total_played += $row["COUNT(pnum4)"];
			}
		}
	}

	arsort($pardcount); # sort in descending count
	$have_pard_list++; # flag for main page to avoid calling it unnecessarily
	# debug
	# echo "get_partners: have_pard_list=$have_pard_list ";
	
	# sort player's games in ascending order by key GameId
	ksort($pairgames); 

	# debug
	# $pairgamecount = sizeof($pairgames, 1) ;
	# echo "# of pairs games = $pairgamecount<br>";
	# foreach ($pairgames as $gm => $array ) {
	# 	echo "player $array[0] sat N or E with S or W player $array[1] as pairId $array[2] for GameId $gm <br>";
	# }

	return $pardcount; # make up your mind - is it a global or a returned value?!  PHP seems to get it right ...
}

###########################    function display_pards    ######################
#                              	- called from home page 
#
function display_pards($pnum, $pardcount, $email, $send) {

	# global $total_played, $teamcount, $total_teamcount, $paircount, $total_paircount, $pardname;
	global $total_played, $teamcount, $total_teamcount, $paircount, $total_paircount;
	$pard = '';

	$msg=
		"List of players who were either a partner or teammate with ".playername($pnum)." since 2010\r\n";

	echo "<hr>"; # horiz rule
	echo "<div class='scroll_partners'>"; 
	echo "<table>";
	echo "<caption>List of players who were either a partner or teammate with ".playername($pnum)." since 2010</caption>";
	echo "<thead>";
	echo "<tr><th> </th><th>partner/teammate</th></tr>";
	echo "<tr><th>Total</th><th>count</th><th style='text-align:left;'>Partner/Teammate</th><th  style='text-align:left;'>player#</th></tr>";
	echo "</thead>";
	echo "<tbody>";

	# assemble email text
	$msg.="\r\nTotal \tpartner/teammate                  \r\n";
	$msg.="count \tcount  \t\tPartner/Teammate \tplayer#\r\n";

 	// output data of each row
 	foreach ($pardcount as $pardpnum  =>  $times_played_together) {
		$tmcnt = 0+ $teamcount["$pardpnum"]; # if zero get blank without "0+"
		$prcnt = 0+ $paircount["$pardpnum"]; # if zero get blank without "0+"
		# $pard  =  $pardname["$pardpnum"];
		$pard  = playername($pardpnum);
		
   	echo "<tr><td>$times_played_together</td><td>$prcnt / $tmcnt</td><td style='text-align:left;'>$pard</td><td style='text-align:left;'>$pardpnum</td></tr>";

		# assemble email body text
		# $msg.="$times_played_together\t$prcnt / $tmcnt    \t" . $pardname["$pardpnum"] . "\t\t$pardpnum\r\n";
		$msg.="$times_played_together\t$prcnt / $tmcnt    \t" . $pard . "\t\t$pardpnum\r\n";
 	}

	echo "<tr><td><sup>*</sup>$total_played</td><td>$total_paircount / $total_teamcount</td></tr>";
	echo "</tbody>";
	echo "</table>";
	echo "</div>";

	echo "<p><sup>*</sup>The teammate count may include games where this player was a partner with another listed player. There is no way of knowing who played with who in team game results.</p>";

	# add final email text
	$msg.="$total_played\r\n";
	$msg.="The teammate count may include games where this player was a partner with another listed player. There is no way of knowing who played with who in team game results.\n";

	if ($send=="yes") {
		$subj="Bridge WebSQL partner history";
		senddata($msg, $email, $subj);
 	}
}

###########################    function display_pard_details    ######################
#                              	- called from home page 
#
function display_pard_details($pnum, $pardcount, $email, $send, $perfwt) {

	$logfile = fopen("logfile.txt", "a") or die("Unable to open file logfile.txt !");

	$tstamp = microtime(true); 
	$start  = sprintf("%f",$tstamp);
	fwrite($logfile, "$start Start Pard Details perfwt=$perfwt timestamp\n");

	global $conn;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}

	global $total_played, $total_paircount, $paircount ;

	# gather performance measures for each partner who has played with pnum more than 10% of the 
	# total times played or more than ? times (changes as the mood hits)
	
	$total = 0 ; # stuff with either total_played or total_paircount, depending on perfwt
	$ArrayToUse = $Pct = $Ovrl = $SA = $SB = $SC = array();

	if ($perfwt) {
		$ArrayToUse = $pardcount;
		$total      = $total_played;
		$and 				= " & teammates" ;
	}
	else {
		$ArrayToUse = $paircount;
		$total      = $total_paircount;
		$and 				= ", excluding team games," ;
	}
	arsort($ArrayToUse); # sort descending

	echo "<h2>Performance of ".playername($pnum)." with partners" . $and . " who have played together more than 20 times, or more than 10% of the time.</h2>";echo "<br>";

 	foreach ($ArrayToUse as $pard  =>  $times_played_together) {
		$pard = trim($pard);
		if ( ($times_played_together >= 20) or ($times_played_together/$total >= 0.1 ) ) {
			# debug
			# echo "pnum:$pnum:--pard:$pard:<br>";
		# debug
		#if ( $pard == "4934180") { # Kevin K

			$Ovrl_rating_total =	$total_players = 0;
			$total_pct = $pct_cnt = 0;
			$Strat_rating_total = $Strat_total_players = array(); # for the 3 strats
				

			# performance including team games
			$perf_wt_sql=	
"SELECT GameId,player_pair_team 'id',pct,Flt,score FROM players WHERE (pnum1=$pnum and pnum2=$pard) or (pnum1=$pnum and pnum3=$pard) or (pnum1=$pnum and pnum4=$pard) or (pnum2=$pnum and pnum3=$pard) or (pnum2=$pnum and pnum4=$pard) or (pnum2=$pnum and pnum1=$pard) or (pnum3=$pnum and pnum1=$pard) or (pnum3=$pnum and pnum2=$pard) or (pnum3=$pnum and pnum4=$pard) or (pnum4=$pnum and pnum1=$pard) or (pnum4=$pnum and pnum2=$pard) or (pnum4=$pnum and pnum3=$pard) ;";

			# performance excluding team games - (don't need to exclude more than player3)
			$perf_wot_sql=	
"SELECT GameId,player_pair_team 'id',pct,Flt,score FROM players WHERE ((pnum1=$pnum and pnum2=$pard) and player3='') or ((pnum2=$pnum and pnum1=$pard) and player3='');" ;

			if ($perfwt) {
				$perf_result = mysqli_query($conn, $perf_wt_sql);# list of all games, including team games, played with $pard
			}
			else {
				$perf_result = mysqli_query($conn, $perf_wot_sql);# list of all games, excluding team games, played with $pard
			}

			if (mysqli_num_rows($perf_result) > 0) {
  		// output data from each game

				echo "<div class='scroll_details'>";
				echo "<table>";
				echo "<hr><caption>Performance with ".playername($pard)." who has played ".$times_played_together." times with ".playername($pnum)." since 2010.</caption>"; 
					echo "<thead>";
						echo "<tr><th> </th><th> </th><th> </th><th> </th><th> </th><th> </th><th style='text-align:left;'>OvrlRnk A<sup>1</sup></th><th style='text-align:left;'>StratRnk A<sup>2</sup></th><th> </th><th style='text-align:left;'>StratRating A<sup>3</sup></th></tr>";
						echo "<tr><th>YYMMDD</th><th>M/A/E</th><th>sctn</th><th>Flt</th><th>pct</th><th>score</th><th>OvrlRnk B</th><th>StratRnk B</th><th>OvrlRating<sup>4</sup></th><th>StratRating B</th></tr>";
						echo "<tr><th> </th><th> </th><th> </th><th> </th><th> </th><th> </th><th style='text-align:right;'>OvrlRnk C</th><th style='text-align:right;'>StratRnk C</th><th></th><th style='text-align:right;'>StratRating C</th></tr>";
					echo "</thead>";
					echo "<tbody>";
  			while($row = mysqli_fetch_assoc($perf_result)) {
					$GameId=       $row['GameId'];
					$yymmdd=substr($row['GameId'],0,6); 
					$MAE=   substr($row['GameId'],6,1);
					$sctn=  substr($row['GameId'],7,1); 
					$pct=          $row['pct'];
					$score=        $row['score'];
					$Flt=          $row['Flt'];

					# repairs and tweaks
					if ($Flt) { # some games do not assign a strat
						$Flt=trim($Flt);
					}

					if ($pct>0) { # home-style games don't calc Pct
						$total_pct += $pct; $pct_cnt++;
					}

					# Get the direction of the player, if Mitchell - e.g. "B7N" is NS pair 7 in section B; 
					# Last char is direction, unlike "A4", which is 4th pair in a one-winner game, or "5", which is team 5
					# if the last char is a letter, it's the direction; otherwise if it's numeric, it's a team or Howell pairs
					$id = $row['id'];
					if (preg_match('/[a-z]/i', substr($id,-1,1))) { 
						$dir = substr($id,-1,1) ;
					} else {
						$dir = "";
					}

					$event_sql = "SELECT nstrats,strats FROM events WHERE GameId like '".$GameId."%' ;";
					$temp1 = mysqli_query($conn, $event_sql);
					$nstrats=0; $strats=""; $stratlist=array();
					if (mysqli_num_rows($temp1) > 0 ) { 
						while ($row2=mysqli_fetch_assoc($temp1) ) {
							$nstrats   =  $row2['nstrats'];
							$strats    =  $row2['strats'];
						}
					} else {
						echo "Error: event_sql returned 0 results<br>";
					}
					$stratlist = str_split( trim($strats) ) ;

					# Rating algorithm: see function 'rate' above.
					# Construct a "Rating" measure to represent success which can be averaged over many events;
					# higher rating is better, (as opposed to a rank of 1st being better than 2nd or 3rd ...)
					# Finishing 1st of 10 is "rated" as 10/10 instead of 1/10.  2nd of 50 is 49/50.  Handle 
					# ties fractionally.						
					#
					# For pair games using a Mitchell movement, 'stratified' rating (by 'Flt') is separated for 
					# EW & NS pairs. (As opposed to Overalls, which use all scores, irrespective of direction.)  
					# So segregate stratified scores depending on dir: EW separate from NS.  Put these scores 
					# in assoc array $Flt_strat_scores by $Flt.
					# $Flt_strat_scores('A'= [scr1, scr2, ... scrn], 'B'= [scr1, scr2, ... scrn], 'C'= [scr1, scr2, ... scrn] ) 
					#
					# If the game was NOT a Mitchell movement, then the scores are in assoc array 
					# $Flt_scores.
					# $Flt_scores('A', scr1, scr2, ... scrn) 
					#
					# If no Flt was assigned, all scores are stored in simple array $allscores
					# $allscores(scr1, scr2, ... scrn) 
					
					$allscores = $Flt_scores = $Flt_strat_scores = array(); # critical that you predeclare these arrays 
					$tmplistA = $tmplistB = $tmplistC = array(); # simple arrays
					$tmplistANSEW = $tmplistBNSEW = $tmplistCNSEW = array(); # simple arrays

					$ScorePairIdFlt_sql = "SELECT player_pair_team as pairId, flt, score FROM players WHERE GameId like '".$GameId."%' ORDER BY score ASC;";
					$temp = mysqli_query($conn, $ScorePairIdFlt_sql);
					if (mysqli_num_rows($temp) > 0) {
						while ($row = mysqli_fetch_assoc($temp)) {
							$id  	 = $row['pairId'];
							$strat = $row['flt'];
							$scr 	 = $row['score'];
							# Build the 3 arrays from each row
							array_push ($allscores, $scr); # array of all scores in ascending order
							if ($strat) {
								if (preg_match('/[a-z]/i', substr($id,-1,1))) { 
									$NSEW = substr($id,-1,1) ;
								} else {
									$NSEW = "";
								}
								if ($strat==" A") {
									array_push ($tmplistA, $scr); # all strat A scores
									if ($dir == $NSEW) { # if score in player's dir 
										array_push ($tmplistANSEW, $scr); # strat A scores, this dir
									}
								}
								if ($strat==" B") {
									array_push ($tmplistB, $scr); # all strat B scores
									if ($dir == $NSEW) { # if score in player's dir 
										array_push ($tmplistBNSEW, $scr); # strat B scores, this dir
									}
								}
								if ($strat==" C") {
									array_push ($tmplistC, $scr); # all strat C scores
									if ($dir == $NSEW) { # if score in player's dir 
										array_push ($tmplistCNSEW, $scr); # strat C scores, this dir
									}
								}
							}
						}
					} else {
						echo "Note:$GameId scores sql returned 0 results<br>";
					}
					$tmpA = array('A' => $tmplistA); # make an assoc array from a simple list
					$tmpB = array('B' => $tmplistB);
					$tmpC = array('C' => $tmplistC);
					$Flt_scores				= array_merge($Flt_scores,$tmpA,$tmpB,$tmpC); # join them
					$tmpA = array('A' => $tmplistANSEW);
					$tmpB = array('B' => $tmplistBNSEW);
					$tmpC = array('C' => $tmplistCNSEW);
					$Flt_strat_scores = array_merge($Flt_strat_scores,$tmpA,$tmpB,$tmpC);


					# Determining the Stratified rank and rating by EW/NS if Mitchell)
					# For a Flt C player, C rank from strat_scores['C']
					# For a Flt C player, B rank from combined strat_scores['B'] & strat_scores['C']
					# For a Flt C player, A rank from combined strat_scores['A'] & strat_scores['B'] & strat_scores['C']
					# For a Flt B player, B rank from strat_scores['B'] & 'C'
					# For a Flt B player, A rank from combined strat_scores['A'] & strat_scores['B'] & 'C' allscores
					# For a Flt A player, rank from all strat_scores
						
					# Determining the Overall rank 
					# For a Flt C player, ovrl C rank from scores['C']
					# For a Flt C player, ovrl B rank from combined scores['B'] & scores['C']
					# For a Flt C player, ovrl A rank from combined scores['A'] & scores['B'] & scores['C'] == allscores
					# For a Flt B player, ovrl B rank from scores['B'] & 'C'
					# For a Flt B player, ovrl A rank from combined scores['A'] & scores['B'] & 'C' == allscores
					# For a Flt A player, ovrl rank from all combined scores
						
					# Overall rating is determined from allscores - not stratified (redundant)

					$ovrl = $strat_rank_rating = array(); # since function 'rate' returns an array
					$Strat_rank = $Strat_string = $Strat_rating = array(); # for the 3 strats
					$Ovrl_rank  = $Ovrl_string  = $Ovrl_rating = array(); # for strats A/B/C

					if ($Flt == 'C') {
						# ------ Ovrl C ---------------
						$ovrl = rate( $score, 'C', $Flt_scores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['C']   = $rank;   
						$Ovrl_rating['C'] = $rating;  # not displayed as stratified
						$Ovrl_string['C'] = $rating . "/" . $count; 

						# ------ Ovrl B for C Flt player ---------------
						# get B Ovrl rank from combined B & C Ovrl scores 
						$ovrl = rate( $score, 'BC', $Flt_scores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['B']   = $rank;   
						$Ovrl_rating['B'] = $rating;  # not displayed as stratified
						$Ovrl_string['B'] = $rating . "/" . $count; 

						# ------ Ovrl A for C Flt player ---------------
						# get A Ovrl rank from combined A & B & C Ovrl scores 
						#$ovrl = rate( $score, 'ABC', $Flt_scores); equivalent to below
						$ovrl = rate( $score, '', $allscores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['A']   = $rank;   
						$Ovrl_rating['A'] = $rating;  # only 1 displayed
						$Ovrl_string['A'] = $rating . "/" . $count; 

						# ------ Strat C ---------------
						if ($dir) { # Mitchell pairs
							# get flt C rank & 'rating' from C strat scores only
							$strat_rank_rating =  rate( $score, 'C', $Flt_strat_scores);
							$rank 	= $strat_rank_rating[1];
							$rating = $strat_rank_rating[0];
							$count  = $strat_rank_rating[2];
							$Strat_rank['C']   =  $rank;
							$Strat_rating['C'] =  $rating;
							$Strat_string['C'] =  $rating . "/" . $count; # like, "13/15"
							# running total of C rating scores for weighted averaging
							$Strat_rating_total['C']  = $Strat_rating_total['C'] + $rating;
							$Strat_total_players['C'] = $Strat_total_players['C'] + $count;
	
							# ------ Strat B for Flt C player ---------------
							# get flt B rank for Flt C player from combined B & C strat scores
							$strat_rank_rating = rate( $score, 'BC', $Flt_strat_scores);
							$rank 	= $strat_rank_rating[1];
							$rating = $strat_rank_rating[0];
							$count  = $strat_rank_rating[2];
							$Strat_rank['B']   =  $rank;
							$Strat_rating['B'] =  $rating;
							$Strat_string['B'] =  $rating . "/" . $count; # like, "13/15"
							# running total of B rating scores for weighted averaging
							$Strat_rating_total['B']  = $Strat_rating_total['B'] + $rating;
							$Strat_total_players['B'] = $Strat_total_players['B'] + $count;

							# ------ Strat A for Flt C player ---------------
							# get flt A rank for Flt C player from combined A & B & C strat scores
							$strat_rank_rating = rate( $score, 'ABC', $Flt_strat_scores);
							$rank 	= $strat_rank_rating[1];
							$rating = $strat_rank_rating[0];
							$count  = $strat_rank_rating[2];
							$Strat_rank['A']   =  $rank;
							$Strat_rating['A'] =  $rating;
							$Strat_string['A'] =  $rating . "/" . $count; # like, "13/15"
							# running total of A rating scores for weighted averaging
							$Strat_rating_total['A']  = $Strat_rating_total['A'] + $rating;
							$Strat_total_players['A'] = $Strat_total_players['A'] + $count;
						} 
						else { # if no dir, strat==ovrl
							$Strat_rank['C']   =  $Ovrl_rank['C'];
							$Strat_rating['C'] =  $Ovrl_rating['C'];
							$Strat_string['C'] =  $Ovrl_string['C'];
							$Strat_rank['B']   =  $Ovrl_rank['B'];
							$Strat_rating['B'] =  $Ovrl_rating['B'];
							$Strat_string['B'] =  $Ovrl_string['B'];
							$Strat_rank['A']   =  $Ovrl_rank['A'];
							$Strat_rating['A'] =  $Ovrl_rating['A'];
							$Strat_string['A'] =  $Ovrl_string['A'];
						}	
					} 
					elseif ($Flt=='B') {
						# ------ Ovrl B ---------------
						if ($stratlist[2]) { # C strat exist?
							$Ovrl_rank['C'] = "-"; #NE
						}
						$ovrl = rate( $score, 'BC', $Flt_scores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['B']   = $rank;   
						$Ovrl_rating['B'] = $rating;  # not displayed as stratified
						$Ovrl_string['B'] = $rating . "/" . $count; 

						# ------ Ovrl A for B Flt player ---------------
						# get A Ovrl rank from combined A & B Ovrl scores 
						#$ovrl = rate( $score, 'ABC', $Flt_scores); equivalent to below
						$ovrl = rate( $score, '', $allscores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['A']   = $rank;   
						$Ovrl_rating['A'] = $rating;  # only 1 displayed
						$Ovrl_string['A'] = $rating . "/" . $count; 

						# ------ Strat B ---------------
						if ($dir) { # Mitchell pairs
							# get flt B rank & 'rating' from BC strat scores only
							$strat_rank_rating =  rate( $score, 'BC', $Flt_strat_scores);
							$rank 	= $strat_rank_rating[1];
							$rating = $strat_rank_rating[0];
							$count  = $strat_rank_rating[2];
							$Strat_rank['B']   =  $rank;
							$Strat_rating['B'] =  $rating;
							$Strat_string['B'] =  $rating . "/" . $count; # like, "13/15"
							# running total of B rating scores for weighted averaging
							$Strat_rating_total['B']  = $Strat_rating_total['B'] + $rating;
							$Strat_total_players['B'] = $Strat_total_players['B'] + $count;

							# ------ Strat A for Flt B player ---------------
							# get flt A rank for Flt B player from combined ABC strat scores
							$strat_rank_rating =  rate( $score, 'ABC', $Flt_strat_scores);
							$rank 	= $strat_rank_rating[1];
							$rating = $strat_rank_rating[0];
							$count  = $strat_rank_rating[2];
							$Strat_rank['A']   =  $rank;
							$Strat_rating['A'] =  $rating;
							$Strat_string['A'] =  $rating . "/" . $count; # like, "13/15"
							# running total of A rating scores for weighted averaging
							$Strat_rating_total['A']  = $Strat_rating_total['A'] + $rating;
							$Strat_total_players['A'] =  $Strat_total_players['A'] + $count;
						} 
						else { # if no dir, strat==ovrl
							if ($stratlist[2]) { # C strat exist?
								$Strat_rank['C'] = "-"; #NE
								$Strat_string['C'] = "-"; #NE
							}
							$Strat_rank['B']   =  $Ovrl_rank['B'];
							$Strat_rating['B'] =  $Ovrl_rating['B'];
							$Strat_string['B'] =  $Ovrl_string['B'];
							$Strat_rank['A']   =  $Ovrl_rank['A'];
							$Strat_rating['A'] =  $Ovrl_rating['A'];
							$Strat_string['A'] =  $Ovrl_string['A'];
						}

						#debug
						#echo "Ovrl_rating['A,B,C']=".$Ovrl_rating['A']." ".$Ovrl_rating['B'] ." ".$Ovrl_rating['C']."<br>";
						#echo "Strat_rating['A,B,C']=".$Strat_rating['A']." ".$Strat_rating['B'] ." ".$Strat_rating['C']."<br>";
						#echo "Ovrl_rank['A,B,C']=".$Ovrl_rank['A']." ".$Ovrl_rank['B'] ." ".$Ovrl_rank['C']."<br>";
						#echo "Strat_rank['A,B,C']=".$Strat_rank['A']." ".$Strat_rank['B'] ." ".$Strat_rank['C']."<br>";
					} 
					else { # Flt must be 'A' or no Flt assigned
						# ------ Strat A ---------------
						# ------ Ovrl A ---------------
						if ($stratlist[2]) { # C strat exist?
							$Ovrl_rank['C'] = "-"; #NE
						}
						if ($stratlist[1]) { # B strat exist?
							$Ovrl_rank['B'] = "-"; #NE
						}
						#$ovrl = rate( $score, 'ABC', $Flt_scores); equivalent to below
						$ovrl = rate( $score, '', $allscores);
						$rank 	= $ovrl[1];
						$rating = $ovrl[0];
						$count  = $ovrl[2];
						$Ovrl_rank['A']   = $rank;   
						$Ovrl_rating['A'] = $rating;  # only 1 displayed
						$Ovrl_string['A'] = $rating . "/" . $count; 

						if ($stratlist[2]) { # C strat exist?
							$Strat_rank['C'] = "-"; #NE
							$Strat_string['C'] = "-"; #NE
						}
						if ($stratlist[1]) { # B strat exist?
							$Strat_rank['B'] = "-"; #NE
							$Strat_string['B'] = "-"; #NE
						}
						$Strat_rank['A']   =  $Ovrl_rank['A'];
						$Strat_rating['A'] =  $Ovrl_rating['A'];
						$Strat_string['A'] =  $Ovrl_string['A']; # like, "13/15"

						# for Mitchell pairs game with assigned strats, write over the above $Strat_ variables
						if ($Flt=='A') { # Flt 'A' assigned
							if ($dir) { # Mitchell pairs
								# get flt A rank & 'rating' from A strat scores only
								$strat_rank_rating =  rate( $score, 'ABC', $Flt_strat_scores);
								$rank 	= $strat_rank_rating[1];
								$rating = $strat_rank_rating[0];
								$count  = $strat_rank_rating[2];
								$Strat_rank['A']   =  $rank;
								$Strat_rating['A'] =  $rating;
								$Strat_string['A'] =  $rating . "/" . $count; # like, "13/15"
							}
						}
						# running total of A rating scores for weighted averaging
						$Strat_rating_total['A']  = $Strat_rating_total['A']  + $Strat_rating['A'];
						$Strat_total_players['A'] = $Strat_total_players['A'] + $count;

						#debug
						#echo "Ovrl_rating['A,B,C']=".$Ovrl_rating['A']." ".$Ovrl_rating['B'] ." ".$Ovrl_rating['C']."<br>";
						#echo "Strat_rating['A,B,C']=".$Strat_rating['A']." ".$Strat_rating['B'] ." ".$Strat_rating['C']."<br>";
						#echo "Ovrl_rank['A,B,C']=".$Ovrl_rank['A']." ".$Ovrl_rank['B'] ." ".$Ovrl_rank['C']."<br>";
						#echo "Strat_rank['A,B,C']=".$Strat_rank['A']." ".$Strat_rank['B'] ." ".$Strat_rank['C']."<br>";
					}

					# ovrl rank strings - like 3/5 or 0/6 (for a C Flt player in strat A) or "-" or "."
					$Ovrl_rank_string    = $Ovrl_rank['A'] . " " ;
					if ( $stratlist[1] ) {
						$Ovrl_rank_string .= $Ovrl_rank['B'] ;
						$Ovrl_rank_string .= " ";
					}
					if ( $stratlist[2] ) {
						$Ovrl_rank_string .= $Ovrl_rank['C'] ; 
					}

					# strat rank strings - like 3/5 or 1/3 or 0/6 (for a C Flt player in strat A)
					$Strat_rank_string      = $Strat_rank['A'] . " " ;
					if ( $stratlist[1] ) {
						$Strat_rank_string   .= $Strat_rank['B'] ;
						$Strat_rank_string   .= " " ;
					}
					if ( $stratlist[2] ) {
						$Strat_rank_string   .= $Strat_rank['C'] ; 
					}
																		
					# Overall rating like, "15/16"
					$ovrl        = rate($score, '', $allscores);
					$rating 		 = $ovrl[0];
					$count  		 = $ovrl[2];
					$Ovrl_string = $rating . "/" . $count;
					# Accumulate some values from all the games played with this partner.
					# make a weighted average of each rating as: (sum of ratings) / (sum of player counts)
					$Ovrl_rating_total+= $rating;
					$total_players    += $count ;

					# strat rating strings - like '3 of 5' or '1 of 3' or '0 of 6' (for a C Flt player in strat A)
					$Strat_rating_string      = $Strat_string['A'] . " ";
					if ( $stratlist[1] ) {
						$Strat_rating_string   .= $Strat_string['B'] ;
						$Strat_rating_string   .= " ";
					}
					if ( $stratlist[2] ) {
						$Strat_rating_string   .= $Strat_string['C'] ; 
					}
					
 					echo "<tr><td>".$yymmdd."</td><td>".$MAE."</td><td>".$sctn."</td><td>".$Flt."</td><td>".$pct."</td><td>".$score."</td><td>".$Ovrl_rank_string."</td><td>".$Strat_rank_string."</td><td>".$Ovrl_string."</td><td>".$Strat_rating_string."</td></tr>";
				} # while ( $row of perf_sql for $GameId with partners $pnum and $pard
				# add a summary line with averages for the last 2 metrics
				$Ave_pct = sprintf ("%8.1f",$total_pct/$pct_cnt );

				$Ave_Ovrl_rating  = sprintf ("%8.1f",100*$Ovrl_rating_total/$total_players);

				$Ave_Strat_rating['A'] = sprintf ("%8.1f",100*$Strat_rating_total['A']/$Strat_total_players['A']) ;
				if ($Strat_total_players['B'] > 0) {
					$Ave_Strat_rating['B'] = sprintf ("%8.1f",100*$Strat_rating_total['B']/$Strat_total_players['B']);
				} else {
					$Ave_Strat_rating['B'] = '-';
				}
				if ($Strat_total_players['C'] > 0) {
					$Ave_Strat_rating['C'] = sprintf ("%8.1f",100*$Strat_rating_total['C']/$Strat_total_players['C']);
				} else {
					$Ave_Strat_rating['C'] = '-';
				}
				$Ave_Strat_rating_string = $Ave_Strat_rating['A'] ."% " . $Ave_Strat_rating['B'] ."% " . $Ave_Strat_rating['C'] ; 

				$partner = playername($pard);

				$Pct[$partner]  = $Ave_pct;
				$Ovrl[$partner] = $Ave_Ovrl_rating;
				$SA[$partner]   = $Ave_Strat_rating['A'];
				if ($Ave_Strat_rating['B'] != '-') {
					$SB[$partner]   = $Ave_Strat_rating['B'];
				}
				if ($Ave_Strat_rating['C'] != '-') {
					$SC[$partner]   = $Ave_Strat_rating['C'];
				}

   					echo "<tr><td>Ave:</td><td>-</td><td>-</td><td>-</td><td>".$Ave_pct."</td><td>-</td><td>-</td><td>-</td><td>".$Ave_Ovrl_rating."%</td><td>".$Ave_Strat_rating_string."%</td></tr>";
					echo "</tbody>";
				echo "</table>";
				echo "</div>";
			} else { # if num_rows>0 for this pard
  			echo "Error:$GameId perf_sql returned 0 results<br>";
			}
		#} # debug - if this pard has played >= 20 times
		echo '<a href="MySQL_Bridge_queries_new.php#top">Return to top</a>';
		} # if played_count > 20 or 10%
		# output some summary of the accumulated perf averages

		$tstamp = microtime(true); 
		$start  = sprintf("%f",$tstamp);
		fwrite($logfile, "$start End of Pard query timestamp\n");

	} # foreach pard
echo '<p><sup>1 </sup>
"Overall rank" is the same as displayed on all ACBL game summaries where players are ranked by Flight.  All players are ranked together for the A rank; B and C players are ranked together for the B rank; only C players are ranked for the C rank.</p>';
echo '<p><sup>2 </sup>
"Strat rank" compares subgroups of the players.  For pairs'."'".' games with a Mitchell movement, NS players in each strat are ranked separately from EW players.  (Note, for ONE-WINNER games (like Howell movements), there is no difference between the Overall rank and the stratified rank, since all of the players move from NS to EW throughout the game [apart from one or two stationary pairs]).</p>';
echo '<p><sup>3 </sup>
"Strat rating" uses the same concept of <u>rating</u> vs <i>rank</i> as the Ovrl Rating, except that players are compared by stratification - A, B and C.  All players are included in the strat A <u>rating</u>; B & C players are rated together in the B <u>rating</u>; only C players are rated in the C <u>rating</u>.  For Mitchell pairs games, the strat population is limited to players in the same direction.</p>';
echo '<p><sup>4 </sup>
"Ovrl rating" uses a <u>rating</u> metric instead of a ranking metric.  The lower the number of your <i>rank</i>, the better.  But <i>rank</i> doesn'."'".'t capture the significance of a player'."'".'s performance like a <u>rating</u> of say, 18/18 - which means you finished on top of a field of 18 pairs.  <u>Rating</u> also lends itself to averaging.  A <i>rank</i> of 1 leaves doubt about the significance of the win - first of 4 or first of 40?  A <u>rating</u> metric weights high finishes in large fields over high finishes in small fields.  All players are compared for the Ovrl <u>rating</u>.</p>';

	echo '<a href="MySQL_Bridge_queries_new.php#top">Return to top</a>';

	arsort ($Pct);
	$Pct_leaders = array_keys($Pct);
	echo '<div class="roomy">';
#	echo "<div class='scroll_details'>";
		echo "<table>";
		echo "<hr><caption>Performance with partners" . $and . " sorted by Ave_Pct</caption>";
			echo "<thead>";
				echo '<tr><th>Partner</th><th style="background-color: #D6EEEE"><span class="roomy">Ave_Pct</span></th><th>Ave_Ovrl_rating</th><th>Ave_Strat_rating[A]</th><th>Ave_Strat_rating[B]</th><th>Ave_Strat_rating[C]</th></tr>';
			echo "</thead>";
			echo "<tbody>";
	foreach ($Pct_leaders as $ldr) {
				echo "<tr><td>".$ldr."</td><td style='background-color: #D6EEEE'>".$Pct[$ldr]."</td><td>".$Ovrl[$ldr]."</td><td>".$SA[$ldr]."</td><td>".$SB[$ldr]."</td><td>".$SC[$ldr]."</td></tr>";
	}
			echo "</tbody>";
		echo "</table>";

	arsort ($Ovrl);
	$Ovrl_leaders = array_keys($Ovrl);
		echo "<table>";
		echo "<hr><caption>Performance with partners" . $and . " sorted by Ave_Ovrl_rating</caption>"; 
			echo "<thead>";
				echo "<tr><th>Partner</th><th>Ave_Pct</th><th style='background-color: #D6EEEE'>Ave_Ovrl_rating</th><th>Ave_Strat_rating[A]</th><th>Ave_Strat_rating[B]</th><th>Ave_Strat_rating[C]</th></tr>";
			echo "</thead>";
			echo "<tbody>";
	foreach ($Ovrl_leaders as $ldr) {
				echo "<tr><td>".$ldr."</td><td>".$Pct[$ldr]."</td><td style='background-color: #D6EEEE'>".$Ovrl[$ldr]."</td><td>".$SA[$ldr]."</td><td>".$SB[$ldr]."</td><td>".$SC[$ldr]."</td></tr>";
	}
			echo "</tbody>";
		echo "</table>";

	arsort ($SA);
	$SA_leaders = array_keys($SA);
		echo "<table>";
		echo "<hr><caption>Performance with partners" . $and . " sorted by Ave_Strat_rating[A]</caption>"; 
			echo "<thead>";
				echo "<tr><th>Partner</th><th>Ave_Pct</th><th>Ave_Ovrl_rating</th><th style='background-color: #D6EEEE'>Ave_Strat_rating[A]</th><th>Ave_Strat_rating[B]</th><th>Ave_Strat_rating[C]</th></tr>";
			echo "</thead>";
			echo "<tbody>";
	foreach ($SA_leaders as $ldr) {
				echo "<tr><td>".$ldr."</td><td>".$Pct[$ldr]."</td><td>".$Ovrl[$ldr]."</td><td style='background-color: #D6EEEE'>".$SA[$ldr]."</td><td>".$SB[$ldr]."</td><td>".$SC[$ldr]."</td></tr>";
	}
			echo "</tbody>";
		echo "</table>";

	if ($SB) {
		arsort ($SB);
		$SB_leaders = array_keys($SB);
		echo "<table>";
		echo "<hr><caption>Performance with partners" . $and . " sorted by Ave_Strat_rating[B]</caption>"; 
			echo "<thead>";
				echo "<tr><th>Partner</th><th>Ave_Pct</th><th>Ave_Ovrl_rating</th><th>Ave_Strat_rating[A]</th><th style='background-color: #D6EEEE'>Ave_Strat_rating[B]</th><th>Ave_Strat_rating[C]</th></tr>";
			echo "</thead>";
			echo "<tbody>";
		foreach ($SB_leaders as $ldr) {
			if ($SB[$ldr] == "-") { continue; }
				echo "<tr><td>".$ldr."</td><td>".$Pct[$ldr]."</td><td>".$Ovrl[$ldr]."</td><td>".$SA[$ldr]."</td><td style='background-color: #D6EEEE'>".$SB[$ldr]."</td><td>".$SC[$ldr]."</td></tr>";
		}
	 		echo "</tbody>";
		echo "</table>";
	}

	if ($SC) {
		arsort ($SC);
		$SC_leaders = array_keys($SC);
		echo "<table>";
		echo "<hr><caption>Performance with partners" . $and . " sorted by Ave_Strat_rating[C]</caption>"; 
			echo "<thead>";
				echo "<tr><th>Partner</th><th>Ave_Pct</th><th>Ave_Ovrl_rating</th><th>Ave_Strat_rating[A]</th><th>Ave_Strat_rating[B]</th><th style='background-color: #D6EEEE'>Ave_Strat_rating[C]</th></tr>";
			echo "</thead>";
			echo "<tbody>";
		foreach ($SC_leaders as $ldr) {
			if ($SC[$ldr] == "-") { continue;}
				echo "<tr><td>".$ldr."</td><td>".$Pct[$ldr]."</td><td>".$Ovrl[$ldr]."</td><td>".$SA[$ldr]."</td><td>".$SB[$ldr]."</td><td style='background-color: #D6EEEE'>".$SC[$ldr]."</td></tr>";
		}
			echo "</tbody>";
		echo "</table>";
	}
	echo "</div>";

	$tstamp = microtime(true); 
	$start  = sprintf("%f",$tstamp);
	fwrite($logfile, "$start End of Pard Details timestamp\n");
}

function make_spreadsheet ($pnum) {

	global $pairgames, $conn ;
	// Check connection
	if (!$conn) {
  	die("Connection failed: " . mysqli_connect_error());
	}
# globals from make_spreadsheet(); 
	global $NSmpt, $EWmpt;
# Tally the mtchpts from POs, player-declaring, pard-declaring, & defending for each board played
	global $mpts_from_PO, $mpts_by_player_declaring, $mpts_by_pard_declaring, $mpts_by_defending, $Tot_mpts ;
	global $player_declaring_count, $pard_declaring_count, $declaring_count, $defending_count, $GoodDeclrCount, $GoodDefCount ;
	global $PO_count, $PO_judgment ;

	$gmid = 0; $gm_cnt = 0;
	$pairId =0;
	$tmp = $SScol = $SS = $SSrow = $SS = $NbdsPerBunch = $NPs = array();
	$player_sits = $club = $sql = $player_sits_first_round = '';
	$top = $isHowell = $BdsPerRound	= 0;
	$start = $tstamp = $interval = $looptop= $loopend=$gamequery =$starttally= $filewrite=$loopend=$done= 0.0001; # time measurements


# Get game results for player.  Each player's results for each board are saved in the results table
# as either nsmtchpts or ewmtchpts, so you must be careful to get the correct mtchpts. For Mitchell 
# movements, there is no doubt about which way player sat - if she started W, she played the whole 
# game as W.  Difficulties arise only for Howell movements, where player may start in, say, the N 
# seat, but next may sit E; or she may start as W, then move to either S or N. The progression 
# specified on the Howell movement guide cards are specific for 6 & 8 pair movements; N->E, E->S, 
# S->W, W->N.  But for 10, 12 & 14 pair Howell movements, the guide simply says E->NS, or S->EW, and 
# we have no way of telling which seat either player moved to.  
	
# We can tell by comparing declarer with nspair/ewpair if player was declarer/dummy or defender, but 
# for declarer/dummy situations, we can't tell which was who for Howell games, aside from the 
# usual tendancy for a player to continue playing N when sitting NS if they started in N seat, 
# and similarly, if they start E, they always sit E when instructed to "sit EW". Best you can do.

# Howell (one winner) movements
# $pairgames[GameId] = (pnum1, pnum2, pairId, club).  If player == pnum1 (N or E) then, if 
# pairId ~ A9, movement=Howell; and at least with a Howell game, we know player1 
# started out sitting N or E.  (Determine which by checking where player1 sat on 
# round==1.) If player = player2, they started S or W, depending where they sat on round 
# 1.  
# N.B. x.5 table 'rover' games are the exception.  Watch the rover move to each table, 
# sitting first EW, then NS.  The rover playerid is the highest pairnum and odd.

	# loop thru all of player's pair games, storing "results" query responses from one 
	# of the 3 game sql queries into various arrays indexed by $i
	
	$logfile = fopen("logfile.txt", "a") or die("Unable to open file logfile.txt !");
	# $csvfile = fopen("newfile.csv", "a") or die("Unable to open file newfile.csv !");
	
	# one worksheet version
	$templateSS 			= "smdata6charts.xls" ;
	# two worksheet version
	# $templateSS 			= "smdata6charts.xlsx" ;
	$templateSSxlsclone  = "smdata6chartsclone.xls" ;
	$templateSSxlsxclone  = "smdata6chartsclone.xlsx" ;
	include('vendor/phpoffice/phpexcel/Classes/PHPExcel/IOFactory.php');
	$reader = PHPExcel_IOFactory::createReader('Excel2007');
	$reader->setReadDataOnly(0);
	$reader->setIncludeCharts(1);
	#$DataOnly = $reader->getReadDataOnly() ;
	#$InclCharts = $reader->getIncludeCharts(); 
	#echo "DataOnly $DataOnly InclCharts $InclCharts<br>"; 
	$ExcelObj= PHPExcel_IOFactory::load($templateSS ); # maybe redundancy will help

	# ------------
	# code from 36chartreadwriteHTML.php example in PHPExcel
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');
/** Include path **/
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../Classes/');

//	Change these values to select the Rendering library that you wish to use
//		and its directory location on your server
$rendererName = PHPExcel_Settings::CHART_RENDERER_JPGRAPH;
$rendererLibrary = 'jpgraph3.5.0b1/src';
$rendererLibraryPath = '/php/libraries/Charts/' . $rendererLibrary;

	echo date('H:i:s') , " Iterate worksheets looking at the charts" , EOL;
	#foreach ($ExcelObj->getWorksheetIterator() as $worksheet) {
		$worksheet = $ExcelObj->getWorksheet();
		$sheetName = $worksheet->getTitle();
		echo 'Worksheet: ' , $sheetName , EOL;

		$chartNames = $worksheet->getChartNames();
		if(empty($chartNames)) {
			echo '    There are no charts in this worksheet' , EOL;
		} else {
			natsort($chartNames);
			foreach($chartNames as $i => $chartName) {
				echo "examining chart $chartName<br>";
				$chart = $worksheet->getChartByName($chartName);
				if (!is_null($chart->getTitle())) {
					$caption = '"' . implode(' ',$chart->getTitle()->getCaption()) . '"';
				} else {
					$caption = 'Untitled';
				}
				echo '    ' , $chartName , ' - ' , $caption , EOL;
				echo str_repeat(' ',strlen($chartName)+3);
				$groupCount = $chart->getPlotArea()->getPlotGroupCount();
				if ($groupCount == 1) {
					$chartType = $chart->getPlotArea()->getPlotGroupByIndex(0)->getPlotType();
					echo '    ' , $chartType , EOL;
				} else {
					$chartTypes = array();
					for($i = 0; $i < $groupCount; ++$i) {
						$chartTypes[] = $chart->getPlotArea()->getPlotGroupByIndex($i)->getPlotType();
					}
					$chartTypes = array_unique($chartTypes);
					if (count($chartTypes) == 1) {
						$chartType = 'Multiple Plot ' . array_pop($chartTypes);
						echo '    ' , $chartType , EOL;
					} elseif (count($chartTypes) == 0) {
						echo '    *** Type not yet implemented' , EOL;
					} else {
						echo '    Combination Chart' , EOL;
					}
				}
			}
		}
	#}
	return;

	#$spreadsheet = \PhpOffice\PHPExcel_IOFactory::load($templateSS, 
	#				\PhpOffice\PHPExcel\Reader\IReader::LOAD_WITH_CHARTS,
	#				\PhpOffice\PHPExcel\Reader\IReader::setReadDataOnly(0));
	#$reader = \PhpOffice\PHPExcel_IOFactory::createReaderForFile($templateSS);

	#$Chartworksheet = $spreadsheet->getSheetByName('Charts');
	#$chartcount = $Chartworksheet->getChartCount();

	# save a xls clone to see if the load function munges the charts
	$writer = PHPExcel_IOFactory::createWriter($spreadsheet, 'Excel5');
	$writer->setIncludeCharts(true);
	$writer->save($templateSSxlsclone);

	# save a xlsx clone to see if the load function munges the charts
	$writer = PHPExcel_IOFactory::createWriter($spreadsheet, 'Excel2007');
	$writer->setIncludeCharts(true);
	$writer->save($templateSSxlsxclone);

	return;

	$tstamp = microtime(true); 
	$start  = sprintf("%f",$tstamp);
	fwrite($logfile, "$start Start timestamp\n");

	$hdr = "Date, Game, Club, Player#, Pard, DeclScr, PardScr, DefScr, TotScr, Finish, Award, #GoodDeclr, %GoodDeclr, #GoodDef, %GoodDef, %Declaring, %Defending, ";
	$hdr.= "PO#, PO%";
	# $hdr.= "Tab'd pts:fctr:fctrdPts:RptdPts, Calc%:Pct, PO#:PO%";
	# fwrite($csvfile, "$hdr\n");

	foreach ($pairgames as $gmid => $tmp) {
		     # $pairgames[GameId] = (pnum1, pnum2, pairId, club) 
		$gm_cnt++;

		$pnum1  = trim($tmp[0]) ; 
		$pnum2  = trim($tmp[1]) ; 
		$pairId = trim($tmp[2]) ; # ('player_pair_team' in "players" table)
		$club   = trim($tmp[3]) ; # important after 2018!
		$yy = 	substr($gmid, 0, 2) ;
		$mm = 	substr($gmid, 2, 2) ;
		$dd = 	substr($gmid, 4, 2) ;
		$MAE=   substr($gmid, 6, 1);
		$sctn=  substr($gmid, 7); # all after MAE ('A' or 'B' or 'AB' or 'EE' or 'DD')

		if ($pnum == $pnum1) {
			$SScol[4] =  playername($pnum2) ; # SS Pard
		}
		else {
			$SScol[4] =  playername($pnum1) ; # SS Pard
		}
		# limit sample debug
		# if ($gmid <> '180321AA' and $gmid <> '180307AA' and $gmid <> '180508AA' and $gmid <> '190514AA' and $gmid <> '200108AA') { continue;}
		# if ($yy <> '11') { continue;}

		# debug
		echo "<br>GameId: $gmid playerId=$pairId club=$club<br>";

		# debug - time stamp
		# echo "Begin: utime $utime ntime=$ntime<br>";
		$tstamp  = microtime(true); 
		$looptop = sprintf("%f",$tstamp);
		if ($gm_cnt>1) { # don't do this cal first loop since loopend not defined
			$interval= sprintf("%f",($looptop-$loopend));
			fwrite($logfile, "\t$interval s\tLoop end-> Loop top\n");
		}

		$SScol[0] =  $mm . "/" . $dd . "/" . "20" . $yy; # SS Date
		$SScol[1] =  $gmid ; # SS Game
		$SScol[3] =  $pnum ; # SS Player#

		# pairId in table 'players' is ~ A9 or A11N == "section" + "pairnum" + ["direction" if Mitchell]
		# However, 'results' use numeric pairnums (except for rare multi-section games with 
		# combined ranking, meaning each pair must keep the section letter preceding their pairnum like 
		# "A7E" or "B5N".  Don't lop off the section letter(s) for those rare games.)  But for most
		# cases, lop off the section.

		# lop off 'direction' from pairId, leaving 'section' + pairnum
		# assign "player_sits"
		# identify Pard
		# identify Howell
		$isHowell = 0;
		if 		 ( (substr($pairId,-1,1) == "N" ) and ($pnum == $pnum1) ) {
			$sctnpairnum = substr($pairId,0,strlen($pairId)-1); # lop off 'N'
			$player_sits = 'N' ;
		}
		elseif ( (substr($pairId,-1,1) == "N" ) and ($pnum == $pnum2) ) {
			$sctnpairnum = substr($pairId,0,strlen($pairId)-1); # lop off 'N'
			$player_sits = 'S' ;
		}
		elseif ( (substr($pairId,-1,1) == "E" ) and ($pnum == $pnum1) ) {
			$sctnpairnum = substr($pairId,0,strlen($pairId)-1); # lop off 'E'
			$player_sits = 'E' ;
		}
		elseif ( (substr($pairId,-1,1) == "E" ) and ($pnum == $pnum2) ) {
			$sctnpairnum = substr($pairId,0,strlen($pairId)-1); # lop off 'E'
			$player_sits = 'W' ;
		}
		else { # must be a Howell (one-winner) game!
			$sctnpairnum = $pairId ;
			$player_sits = '?' ; 
			$player_sits_first_round = '?' ; # wait to find round 1 seat from query
			$isHowell = 1;
		}
		
		# Split $sctnpairnum into "section" + "pairnum" unless 'combined section' game
		# Two sections are combined if Top for each section equals one less than the 
		# combined number of players in the two sections.
		$components = preg_split("/(\d+)/", $sctnpairnum,-1,PREG_SPLIT_DELIM_CAPTURE);
		if ($sctn == 'AB') {
			$pairnum = $sctnpairnum ; # leave the scnt prefix
		} else {
			$pairnum = $components[1] ; # numeric, no letters
		}

		# prepare queries to get results from this gmid for player
		# Mitchell game queries
		$NS_sql = "SELECT DISTINCT board_id,results.club,results.GameId,nsscore,nsmtchpts as NSmpt,ewmtchpts as EWmpt, nspair as NS, ewpair as EW, round,top,Declarer as dclr," . 
							"players.adjust FROM results JOIN players ON players.GameId=results.GameId WHERE results.club=' $club' and results.GameId = '$gmid' and nspair = '$pairnum';" ;
					
		$EW_sql = "SELECT DISTINCT board_id,results.club,results.GameId,nsscore,nsmtchpts as NSmpt,ewmtchpts as EWmpt, nspair as NS, ewpair as EW, round,top,Declarer as dclr," . 
							"players.adjust FROM results JOIN players ON players.GameId=results.GameId WHERE results.club=' $club' and results.GameId = '$gmid' and ewpair = '$pairnum';" ;
	
		# Howell game query
		$Howell_sql =
							"SELECT DISTINCT board_id,results.club,results.GameId,nsscore,nsmtchpts as NSmpt,ewmtchpts as EWmpt, nspair as NS, ewpair as EW, round,top,Declarer as dclr," .
					 		"players.adjust FROM results JOIN players ON players.GameId=results.GameId WHERE results.club=' $club' and results.GameId = '$gmid' and (nspair = '$pairnum' or ewpair = '$pairnum');" ;

		$tstamp 	 = microtime(true); 
		$gamequery = sprintf("%f",$tstamp);
		$interval	 = sprintf("%f",($gamequery-$looptop));
		fwrite($logfile, "\t$interval s\tLoop top -> Start game query\n");

		if 		(($player_sits == "N") or ($player_sits == "S")) {
			$sql = $NS_sql;
			$results = mysqli_query($conn, $NS_sql);
		} 
		elseif (($player_sits == "E") or ($player_sits == "W")) {
			$sql = $EW_sql;
			$results = mysqli_query($conn, $EW_sql);
		}
		else { # Howell (One-Winner) game
			$sql = $Howell_sql;
			$results = mysqli_query($conn, $Howell_sql);
		}

		$done_once 		= 0 ;
		$played_count = 0 ;

		# initialize and establish scope
		$Rnd = $bdlist = $Declarer = $nsscore = $sitting = array();
		$NSmpt = $EWmpt = $pairing = array();
		$mpts_from_PO = $mpts_by_player_declaring = $mpts_by_pard_declaring = $mpts_by_defending = $Tot_mpts = 0;
		$player_declaring_count   = $pard_declaring_count   = $declaring_count = $defending_count = $GoodDeclrCount = $GoodDefCount = 0;
		$PO_count     = $PO_judgment = $adjust = $ZeroRoundFound = 0;

		$ith_board  = 0;
		$fake_round 		 = 100; # to start with
		$prev_fake_round = 0; # useful when 'round' is missing
		if (mysqli_num_rows($results) > 0) {
			while ($row = mysqli_fetch_assoc($results)) {
				$played_count++; # count the boards played, starting with 1, not zero
				$ith_board = $played_count - 1; # index of the arrays below ('array_push' starts with 0)

				if (! $done_once ) { # get overhead data for this gmid
					$SScol[2] = $row['club'];
					$top 			= $row['top'];
					$adjust 	= $row['adjust'];
					$done_once++ ;
				}

				# Index of these arrays is 'ith board', NOT the board_id, which are seldom consecutive!
				$board_id 					 = $row['board_id'] ; # highest board played after final loop
				array_push ($bdlist, 	 $board_id ); 			# contains the list of boards played
				# Create a "pairing" string of NS pair vs EW pair so that when this string changes, we know 
				# we have a new round (for when 'round'==0)
				array_push ($pairing, $row['NS'].",".$row['EW']) ;
				array_push ($Rnd,		 	 $row['round']) ; 
				array_push ($NSmpt, 	 $row['NSmpt']) ; 
				array_push ($EWmpt, 	 $row['EWmpt']) ; 
				array_push ($Declarer, trim($row['dclr'])) ;  # if blank for all boards, Bridgemate wasn't used; lots of guesswork to do
				array_push ($nsscore,  $row['nsscore']) ; 		# check nsscore==0 for PO, or the guesswork if != 0 & dclr=''

				# fix 'round=0' cases
				if ($row['round'] == 0) {
					$ZeroRoundFound++ ; # count 'em 
					# if 'round' is zero, assign a round starting with 100, incrementing when the pairing 
					# changes
					if ($ith_board == 0) { # 1st board
						$fake_round 		 = 101;
						$Rnd[0] = 101; # replace round=0 with 101 for the 1st board 
					}
					else { # not 1st played bd 
						# If the pairing has not changed from prev bd, this board is in the same round as prev board
						$last_bd   = $ith_board-1;
						# compare this pair against last board's pairings as well as for pairs swapping seats (100907EA!)
						$swapped_pairing = $row['EW'].",".$row['NS'] ;
						if ( ($pairing[$last_bd] == $pairing[$ith_board]) or
								 ($pairing[$last_bd] == $swapped_pairing) ) {
							$Rnd[$ith_board] = $Rnd[$last_bd] ; 
						}
						# If the pairing HAS changed, incrment the fake_round & assign to this bd 
						else {
							$fake_round++;  # don't combine with next assignment statement
							$Rnd[$ith_board] = $fake_round ; 
						}
					}
					# debug
					# echo "changed round for bd $board_id, pairing $pairing[$ith_board] to $Rnd[$ith_board]<br>";
				}

				# for Mitchell movements, "$sitting" is constant == $player_sits->["N", "E", "S", "W"]
				# but for Howell movements, "$sitting" changes, and is ["NS" or "EW"]

				# Howell messiness - use round 1 to determine where $player_sits_first_round
				if ( $isHowell ) { # this test fails for a rover movement where rover sits out first round
					if ($pairnum == $row['NS']) { # req'd for Howell movement
						array_push ($sitting,  'NS') ;
					}
					elseif ($pairnum == $row['EW']) { #          "
						array_push ($sitting,  'EW') ;
					}
					if ( $row['round'] == 1 ) {
						if 		 ( ($pairnum == $row["NS"]) and ($pnum == $pnum1) ) {
							$player_sits_first_round = 'N' ;
						}
						elseif ( ($pairnum == $row["NS"]) and ($pnum == $pnum2) ) {
							$player_sits_first_round = 'S' ;
						}
						elseif ( ($pairnum == $row["EW"]) and ($pnum == $pnum1) ) {
							$player_sits_first_round = 'E' ;
						}
						elseif ( ($pairnum == $row["EW"]) and ($pnum == $pnum2) ) {
							$player_sits_first_round = 'W' ;
						}
						else {
							$player_sits_first_round = 'rover' ; # or sat out round 1, or all rounds missing
							if ($pnum == $pnum1) {
							} 
						}
					}
				} 
				else { # Mitchell
					array_push ($sitting,  $player_sits) ;
				}
			}
		}
		else {
			echo "Found no results for $pnum at game $gmid with $sql<br>";
		}
		# debug
		# echo "Player sits $player_sits<br>";
		
		# Populate NbdsPerBunch array with #bds played each time pairing changes, since fake rounds messes 
		# things up, so group them in 'bunches' instead of 'rounds'
		
		$NbdsPerBunch = array();
		$bunch = 0; # array index
		$NbdsPerBunch[0] = 1;# put first pairing in first bunch
		for ($i=1; $i<=$ith_board; $i++) { # start with second board played
			$thisboard = $i ;
			$lastboard = $i-1; # php seems unable to do perform arithmetic inside []'s

			$pairs = array(); # what a pain; test for pairs swapping places
			$pairs = preg_split('/,/', $pairing[$thisboard]);
			$swapped_pairing = $pairs[1] . "," . $pairs[0] ; # "NS,EW" -> "EW,NS"
			# debug
			# echo "this bd=$thisboard, last bd=$lastboard, this pr=$pairing[$thisboard], swp pr=$swapped_pairing, last pr=$pairing[$lastboard]<br>";
			if ( ($pairing[$thisboard] == $pairing[$lastboard]) or
					 ($swapped_pairing     == $pairing[$lastboard]) ) {
				$NbdsPerBunch[$bunch]++; # don't care which bds are played on any round.  care only about how many boards per round
			}
			else { # pairing changed; increment bunch and increment next bunch bd count
				$bunch++ ; # increment array index when pairings change
				$NbdsPerBunch[$bunch]++; # increment count in next bunch
			}
		}

		$BunchList = array_keys($NbdsPerBunch); 
		$numRnds = sizeof($BunchList);

		# calculate bdsPerRnd & look for short rounds
		$BdsPerRound	= $NbdsPerBunch[0]; # first bunch count
		sort($BunchList); # ascending
		foreach ($BunchList as $bunch) {
			$numBds= $NbdsPerBunch[$bunch];
			# debug
			# echo "NbdsPerBunch[$bunch] = $numBds <br>";
			if ($numBds > $BdsPerRound) { # assign biggest value
				$BdsPerRound = $numBds;
			}
		}
		# debug
		echo "$played_count Played Bds; $numRnds Rounds ; $BdsPerRound BdsPerRound <br>";

		# more reliable Potential sitout situations: odd # of Howell tables, or uneven NSpairs and EWpairs in Mitchell
		$OddHowellPairs = 0;
		$Mitchell_factor = 1 ;
		$HowellPairCount_sql 		 = "SELECT count(player1) as prs FROM players WHERE (GameId = '$gmid') ;";
		$MitchellNSPairCount_sql = "SELECT count(player1) as NS  FROM players WHERE (GameId = '$gmid') and player_pair_team like '%N';";
		$MitchellEWPairCount_sql = "SELECT count(player1) as EW  FROM players WHERE (GameId = '$gmid') and player_pair_team like '%E';";

		if ($isHowell) {
			$results = mysqli_query($conn, $HowellPairCount_sql);
			$row = mysqli_fetch_assoc($results) ;
			$HowellPairCount = $row['prs'];
			if ($HowellPairCount % 2) {
				echo "Likely Howell sitout due to odd # tables<br>";
				$OddHowellPairs = 1;
			}
		}
		else { # Mitchell - larger section sits out
			$results = mysqli_query($conn, $MitchellNSPairCount_sql);
			$row = mysqli_fetch_assoc($results) ;
			$NS = $row['NS'];
			$results = mysqli_query($conn, $MitchellEWPairCount_sql);
			$row = mysqli_fetch_assoc($results) ;
			$EW = $row['EW'];
			if ( ( ($NS > $EW) and ( ($player_sits=='N') or ($player_sits=='S') ) )
				or ( ($NS < $EW) and ( ($player_sits=='E') or ($player_sits=='W') ) ) )  {
				echo "Likely Mitchell sitout due to unbalanced NS($NS)/EW($EW) table count<br>";
				# calculate the likely factor - find the bigger section
				if ($NS>$EW) {$bigger=$NS;}
				if ($NS<$EW) {$bigger=$EW;}

				# 2 cases: 3-bd-round & 2-bd-round games
				# 3-bd-round cases: <=9 tables, >9 tables; if >9 tables, may or may not have sitout
				# if numRnds < 9, player sat out
				if ($BdsPerRound == 4) { # 7/6 almost always if unbalanced Mitchell 7 1/2 tables
					# NOPE - $Mitchell_factor=$bigger/($bigger-1); # even table movement has a skip round, so one less
					$Mitchell_factor=7/6; 
				}
				if ($BdsPerRound == 3) { # 9/8 unless fewer than 9 tables
					if ($bigger<=9) {
						$Mitchell_factor=$bigger/($bigger-1);
					}
					elseif ($numRnds<9) {
									$Mitchell_factor=9/8;
					} # never play more than 9 rounds
				}
				elseif ($BdsPerRound == 2) {
					# difficult to predict ; if numRnds is even, and numRnds < fieldsize, you probably had a sitout
					#if ( ($numRnds % 2 == 0) and ($numRnds < $bigger) ) {
						$Mitchell_factor= ($numRnds+1)/$numRnds ;
					#}
				}
			}
		}

		# detect and count NPs
		# Most accurate method is to count missing pairings.  Pairings change after each 
		# BdsPerRound, so when there are fewer than BdsPerRound in any 'bunch', there was a NP.
		# But since round data can be unreliable, just count number of pairings
		#
		# NP detected when #bds in any 'bunch' < BdsPerRnd
		#
		# stuff #NPs into an array NPs['bunch']
		$NPs = array(); # initialize!
		# ??? for ($bunch=1; $bunch < sizeof($NbdsPerBunch); $bunch++) 
		$totNPs=0;
		foreach ($BunchList as $bunch) { # necessary when 'fake' rounds employed
			if ($NbdsPerBunch[$bunch] == $BdsPerRound )  {
				continue;
			}
			else { # bunch has fewer than $BdsPerRound in it
				$NPs[$bunch] = ($BdsPerRound - $NbdsPerBunch[$bunch]);
				# debug
				$totNPs += $NPs[$bunch] ;
				echo "- GameId: $gmid: $NPs[$bunch] possible NP found in bunch $bunch<br>";
			}
		}
		
	#------- allocate mpts into categoeies: player-Declaring, pard-declaring, defending or PO ------------------

		$score = 0;

		$tstamp 	  = microtime(true); 
		$starttally = sprintf("%f",$tstamp);
		$interval	  = sprintf("%f",($starttally-$gamequery));
		fwrite($logfile, "\t$interval s\tGame query -> Start tally\n");

		for ($i=0; $i<$played_count; $i++)  {
			if ( 			$Declarer[$i] == '' ) {
					# debug
					# echo "No declarer found in game $gmid on board $bdlist[$i]";
					# echo "- nsscore=$nsscore[$i] means guesswork looks like a ".offense_defense($nsscore[$i])." score<br>";
				# PO ?
				if ( $nsscore[$i] ==0 ) { # PO
				# debug
					# echo "No declarer found in game $gmid on board $bdlist[$i]";
					# echo "- nsscore=0 means PO<br>";
					if (($player_sits == 'N') or ($player_sits == 'S') or ($sitting[$i] == 'NS')) {
						NorSPO ($i); continue ;
					}
					else { # must be sitting EW
						EorWPO ($i); continue ;
					}
				} # DRAT - no declarer specified, but nsscore <> 0.  Bridgemate foul up.  Must guess who played the hand.
					# must combine Mitchell & Howell "sitting" cases below, which makes for some very long 'if' stmnts

				#---------------------- OFFENSIVE (Declaring) SCORES --------------------------------------------
				
				# NS declared (successfully) : nsscore > 0 and score ~ offensive, & Player sat NS
				elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] > 0 ) and 
								(( $player_sits == 'N') or ($player_sits == 'S') or ($sitting[$i] == 'NS')) ) { # but can't tell who declared, so both get credit/blame
					NSbothDeclaring ($i, $top); continue ;
				}
				# NS declared (successfully) : nsscore > 0 and score ~ offensive, but Player sat EW
				elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] > 0 ) and 
							  (( $player_sits == 'E') or ($player_sits == 'W') or ($sitting[$i] == 'EW')) ) { # defense
 					EWdefending ($i, $top); continue ;
				}
				# EW declared (successfully) : nsscore < 0 and score ~ offensive, & Player sat EW
				elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] < 0 ) and 
							  (( $player_sits == 'E') or ($player_sits == 'W') or ($sitting[$i] == 'EW')) ) { # but can't tell who declared, so both get credit/blame
					EWbothDeclaring ($i, $top); continue ;
				}
				# EW declared (successfully) : nsscore < 0 and score ~ offensive, but Player sat NS
				elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] < 0 ) and 
							  (( $player_sits == 'N') or ($player_sits == 'S') or ($sitting[$i] == 'NS')) ) { # defense
 					NSdefending ($i, $top); continue ;
				}

				#---------------------- DEFENSIVE SCORES --------------------------------------------
				
				# NS defending successfully : nsscore > 0 and score ~ defensive, & Player sat NS
				elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] > 0 ) and 
							  (( $player_sits == 'N') or ($player_sits == 'S') or ($sitting[$i] == 'NS')) ) { # defense
 					NSdefending ($i, $top); continue ;
				}
				# NS defending successfully : nsscore > 0 and score ~ defensive, but Player sat EW
				elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] > 0 ) and 
							  (( $player_sits == 'E') or ($player_sits == 'W') or ($sitting[$i] == 'EW')) ) { # but can't tell who declared, so both get credit/blame
					EWbothDeclaring ($i, $top); continue ;
				}
				# EW defending successfully : nsscore < 0 and score ~ defensive, but Player sat NS
				elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] < 0 ) and 
							  (( $player_sits == 'N') or ($player_sits == 'S') or ($sitting[$i] == 'NS')) ) { # but can't tell who declared, so both get credit/blame
					NSbothDeclaring ($i, $top); continue ;
				}
				# EW defending successfully : nsscore < 0 and score ~ defensive, & Player sat EW
				elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] < 0 ) and 
							  (( $player_sits == 'E') or ($player_sits == 'W') or ($sitting[$i] == 'EW')) ) { # defense
 					EWdefending ($i, $top); continue ;
				}
				else {
					# debug
					# echo "Response from offense_defense:", offense_defense($nsscore[$i]),"<br>";
					if ($nsscore[$i] <> 0) {
						# debug
						echo "game $gmid, board $i player sitting $player_sits or $sitting[$i]- nsscore=$nsscore[$i], $NSmpt[$i] NSmpts or $EWmpt[$i] EWmpts were not allocated<br>";
					}
				}
			} # end of unknown-declarer-but-not-passout mess
			# $Declarer is specified
			elseif (! $isHowell) { # Mitchell movement
				# player sits N or S
				if ( 			 $player_sits == 'N' ) {
					if (		$Declarer[$i] == 'N') {
						NorSPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'S') {
						NorSPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
 						NSdefending ($i, $top); continue ;
					}
				}
				if ( 			 $player_sits == 'S' ) {
					if ( 		$Declarer[$i] == 'S') {
						NorSPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'N') {
						NorSPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
 						NSdefending ($i, $top); continue ;
					}
				}
				# player sits E or W
				if ( 			 $player_sits == 'E' ) {
					if (		$Declarer[$i] == 'E') {
						EorWPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'W') {
						EorWPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
 						EWdefending ($i, $top); continue ;
					}
				}
				if ( 			 $player_sits == 'W' ) {
					if (		$Declarer[$i] == 'W') {
						EorWPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'E') {
						EorWPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
 						EWdefending ($i, $top); continue ;
					}
				}
			} # end of Mitchell movement
			elseif ($isHowell) { # Howell movement
				# player & pard currently sitting N or S
				if ( ($sitting[$i] == 'NS') and ($player_sits_first_round == 'N') ) { # nailed it
					if (		$Declarer[$i] == 'N') {
						NorSPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'S') {
						NorSPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
 						NSdefending ($i, $top); continue ;
					}
				}
				if ( ($sitting[$i] == 'NS') and ($player_sits_first_round == 'S') ) { # nailed it
					if (		$Declarer[$i] == 'N') {
						NorSPardDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'S') {
						NorSPlayerDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
 						NSdefending ($i, $top); continue ;
					}
				}
				if ($sitting[$i] == 'NS') { # don't know where these people sat on 1st round; so maybe rover or sat out 1st round
					if (	  $Declarer[$i] == 'N' or $Declarer[$i] == 'S') { # can't tell who so both get credit/blame
						NSbothDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
 						NSdefending ($i, $top); continue ;
					}
				}
				# player & pard currently sitting E or W
				if ( ($sitting[$i] == 'EW') and ($player_sits_first_round == 'E') ) { # nailed it
					if (		$Declarer[$i] == 'E') {
						EorWPlayerDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'W') {
						EorWPardDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
 						EWdefending ($i, $top); continue ;
					}
				}
				if ( ($sitting[$i] == 'EW') and ($player_sits_first_round == 'W') ) { # nailed it
					if (		$Declarer[$i] == 'E') {
						EorWPardDeclaring ($i, $top); continue ;
					} 
					elseif ($Declarer[$i] == 'W') {
						EorWPlayerDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
 						EWdefending ($i, $top); continue ;
					}
				}
				if ($sitting[$i] == 'EW') { # don't know where these people sat on 1st round; so maybe rover or sat out 1st round
					if (		$Declarer[$i] == 'E' or $Declarer[$i] == 'W') { # can't tell who so both get credit/blame
						EWbothDeclaring ($i, $top); continue ;
					}
					elseif ($Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
 						EWdefending ($i, $top); continue ;
					}
				}
			} # end of Howell movement
			else { # should never get here - a previous continue should branch to top of loop
				echo "unexpectedly fell thru board loop for game $gmid, board $i, pairid, $pairId<br>";
			}
		} # end of tally-each-played-board loop

		$tstamp 	 = microtime(true); 
		$filewrite = sprintf("%f",$tstamp);
		$interval	 = sprintf("%f",($filewrite-$starttally));
		fwrite($logfile, "\t$interval s\tStart tally -> Write to file\n");


		# summarize, ready to stuff into SS
		if ($declaring_count > 0 ) { 
			$SScol[12] = sprintf("%3.2f",100*$GoodDeclrCount / $declaring_count ); # %GoodDeclr 
		}
		else { $SScol[12] = '' ; }

		if ($player_declaring_count > 0 ) { 
			$SScol[5]  = sprintf("%3.2f",100*$mpts_by_player_declaring / ($top*$player_declaring_count)); # DeclrScr
		}
		else { $SScol[5]  = '' ; }

		if ($pard_declaring_count > 0 ) { 
			$SScol[6]  = sprintf("%3.2f",100*$mpts_by_pard_declaring / ($top*$pard_declaring_count)); # PardScr
		}
		else { $SScol[6]  = '' ; }

		if ($defending_count > 0 ) { 
			$SScol[7]  = sprintf("%3.2f",100*$mpts_by_defending / ($top*$defending_count)); # DefScr
			$SScol[14] = sprintf("%3.2f",100*$GoodDefCount / $defending_count ); # %GoodDef 
		}
		else {
			$SScol[7]  = '' ;
			$SScol[14] = '' ;
		}

		if ($PO_count > 0 ) { 
			$PO_judgment = sprintf("%3.2f",100*$mpts_from_PO/($top*$PO_count) );
		} 
		else { $PO_judgment = ''; }
		$SScol[17] = $PO_count ;
		$SScol[18] = $PO_judgment ;

		$SScol[11] = $GoodDeclrCount ;
		$SScol[13] = $GoodDefCount ;

		$SScol[15] = sprintf("%3.2f",100*$declaring_count/$played_count); # not necessarily same as player_declaring_count + pard_declaring_count for Howell
		$SScol[16] = sprintf("%3.2f",100*$defending_count/$played_count);
		
		# player results query
		$summary_sql = "SELECT Flt, Pct, Score, award1, color1, award2, color2, Rnk1S, Rnk2S, Rnk3S, Rnk1O, Rnk2O, Rnk3O FROM players WHERE GameId LIKE '" . 
									$gmid . "%' AND player_pair_team LIKE '%" . $tmp[2] . "';" ;

		$summary = mysqli_query($conn, $summary_sql);
		if (mysqli_num_rows($summary) > 0) {
			while ($row  = mysqli_fetch_assoc($summary)) { # should be only 1 row
#				$SScol[6]  = $row['Flt'] ; # SS Flt
				$SScol[8] = $row['Pct'] ; # SS %Scr - (compare with calc'd final % from matchpoints)
				$SScol[9] = $row['Rnk1O'] . $row['Rnk2O'] . $row['Rnk3O'] . "|" . $row['Rnk1S'] . $row['Rnk2S'] . $row['Rnk3S'] ; # SS Finish
				$SScol[10] = $row['award1'] ." ". $row['color1'] ." ".$row['award2'] ." ".$row['color2'] ; # SS Award
				$score     = $row['Score'] ; # for comparison purposes
				$Pct			 = $row['Pct']   ; #        "
			}
		}
		else {
			echo "Player summary sql $summary_sql failed<br>";
		}

		# Calculate the mtchpts for this game, then compare with the reported Score & "Pct" from 
		# 'players' table.  Acct for NPs & sitouts.  $top+1 is the number of times each board 
		# should have been played.  
	
		$theo_max_score = $top * (1+$top) * $BdsPerRound 			; # theoretical max score, if no sit-outs or NPs
		$max_score = $top * $played_count ; # player's actual max score, not comprehending sit-outs and NPs

		$Scaling_factor = 1 ;
		if ($isHowell) {
			if ($OddHowellPairs) {
				$Scaling_factor = ( (($numRnds+1)/$numRnds)*($totNPs+$played_count)/$played_count);
			}
			elseif ($totNPs) {
				$Scaling_factor = ( ($totNPs+$played_count)/$played_count);
			}
		} else {
			$Scaling_factor = $Mitchell_factor*(($totNPs+$played_count)/$played_count) ;
		}

		# add 'adjust' mpts
		$Tot_mpts += $adjust ;

		# lop off (now) unneeded extra precision
		$factored_mpts 		= sprintf("%3.2f", $Tot_mpts*$Scaling_factor);
		$calculated_score = sprintf("%3.2f", 100*$Tot_mpts / $max_score) ; # compare to 'Pct'
		$Scaling_factor 	= sprintf("%3.2f", $Scaling_factor) ; # compare to 'Pct' -- irrelevant

		# $SScol[2] = $row['rating']; # SS entry
		# $SScol[2] = $BdsPerRound ; # SS entry

		if ($factored_mpts <> $score) {
			$score = "!=" . $score;
		} else {
			$score = ": " . $score;
		}
	
		if ($calculated_score <> $Pct) {
			$Pct = "!=" . $Pct;
		} else {
			$Pct = ": " . $Pct;
		}

		$tstamp 	 = microtime(true); 
		$loopend 	 = sprintf("%f",$tstamp);
		$interval	 = sprintf("%f",($loopend-$filewrite));
		fwrite($logfile, "\t$interval s\twrite File -> Loop end\n");

		$ftime = microtime(true); 
		$utime += microtime(true); 
		$utimeinterval = sprintf('%f', $utime);

# Existing Spreadsheet columns:
#   0    			1				2			3				  4			5					6				7			8				9			10			11				12					13				14				15  				16				17	 			18	19
# XLdate Game Date, GameId, Club, Player#, Pard, DeclScr, PardScr,DefScr,TotScr, Finish, Award, #GoodDeclr, %GoodDeclr, #GoodDef, %GoodDef, %Declaring, %Defending,#PO,POscr
# First column is the 'date' in xl integer-date format, produced by xl function DATEVALUE(Bx); player data goes into cols 1-19

		# write one row of data, then add the row to the array of rows
		$SSrow=array(); # start with an empty row
		# $csvrow = '';
		for ($i=0; $i<=18; $i++) {
			array_push ($SSrow, $SScol[$i] );
			# $csvrow .= ($SScol[$i] . ', ');

		}
		$topleftcell = 'B' . ($gm_cnt+1); # start with B2 since we already have a header row in line 1, and col A is XLdate 

		$spreadsheet->getSheetByName('Data')
			->fromArray(
			$SSrow,      // The data to set
			"9999",      // Array values with this value will not be set
			$topleftcell // Top left coordinate of the worksheet range where
	             		 //    we want to set these values (default is A1)
		);


		# fwrite($csvfile, "$csvrow\n");

		$tstamp 	 = microtime(true); 
		$done 		 = sprintf("%f",$tstamp);
		$interval	 = sprintf("%f",($done-$start));
		fwrite($logfile, "\t$interval s\t Start-> Finish\n");

		$secs_pergame = sprintf("%f",$interval/$gm_cnt) ;

		fwrite($logfile, "\n$gm_cnt games in $interval s\nPer Game Calc in s:$secs_pergame");
	} # end of foreach $gmid

	fclose($logfile);

	# sample SS has 64 rows (64 games).  If player filled in fewer than 64, overwrite from end of player's 
	# games to 65 with nulls.
	if ($gm_cnt<65) {
		$SSemptyrow=array(); # an empty row
		for ($i=0; $i<=18; $i++) {
			array_push ($SSrow, '' ); # fill with 19 nulls
		}

		$next_row = $gm_cnt + 2; # row to be erased
		for ($row=$next_row; $row<=65; $row++) {
			$topleftcell = 'B' . $row; # start with next row, enter blanks upto 1280

			$spreadsheet->getSheetByName('Data')
				->fromArray(
				$SSemptyrow, // fill with nulls
				"9999",      // Array values with this value will not be set
				$topleftcell // Top left coordinate of the worksheet range where
	           		  	 //    we want to set these values (default is A1)
			);
		}
	}

	$SSfilename = $pnum . "_Charts.xls";
	$writer = PHPExcel_IOFactory::createWriter($spreadsheet, 'Xls');
	$writer->setIncludeCharts(true);
	$writer->save($SSfilename);
	#die ;

	#for ($i=0; $i<$chartcount;$i++) {
	#	$chartname=$Chartworksheet->getChartNames($i);
	#	$Chart = $Chartworksheet->getChartByIndex($i);
	#	echo "<br>display info here about chart after data entry $i $chartname[$i]<br>";
	#	var_dump($Chart->getXAxisLabel());echo" <br>";
	#	var_dump($Chart->getYAxisLabel());echo" <br>";
	#	var_dump($Chart->getPlotType());echo" <br>";
	#	var_dump($Chart->getPlotSeriesCount());echo" <br>";
	#	var_dump($Chart->getDataSource());echo" <br>";
	#	var_dump($Chart->getFillColor());echo" <br>";
	#	var_dump($Chart->getDataValues());echo" <br>";
	#	var_dump($Chart->isMultiLevelSeries() );echo"<br>";
	#	var_dump($Chart->MultiLevelCount());echo" <br>";
	#	var_dump($Chart->getCaption());echo" <br>";
	#}
	$spreadsheet->disconnectWorksheets();
	unset($spreadsheet);


	# fclose($csvfile);
}

function offense_defense($score) {
	# pass in the score, return "offense" or "defense"
	# if the passed-in score was a NS score, then
	# if pos & OFFENSE, NS were declaring successfully
	# if neg & OFFENSE, EW were declaring successfully
	# if pos & DEFENSE, EW were declaring unsuccessfuly
	# if neg & DEFENSE, NS were declaring unsuccessfuly
	$offdef = "" ; # fill with "offense" or "defense"
	$offensive_scores = array (
		70,80,90,
		110,120,130,140,150,160,170,180,190,200,
		210,230,240,260,270,280,
		340,360,380,
		400,420,430,440,450,460,470,480,490,
		510,520,530,540,560,570,580,590,
		600,610,620,630,640,650,660,670,680,690,
		710,720,730,740,750,760,770,780,790,
		810,830,830,840,850,870,880,890,
		910,920,930,940,950,960,980,990,
		1010,1020,1030,1040,1050,1070,1080,1090,
		1110,1120,1130,1140,1150,1160,1180,1190,
		1210,1230,1240,1250,1270,1280,1290,
		1310,1320,1330,1340,1360,1370,1380,1390,
		1410,1430,1440,1460,1470,1480,1490,
		1510,1520,1530,1540,1550,1560,1580,
		1600,1620,1630,1640,1660,1680,1690,
		1720,1740,1760,1770,1790,
		1800,1820,1830,1860,1880,
		1920,1960,
		2040,2070,2080,
		2110,2120,2140,2160,
		2200,2210,2220,2230,2240,2280,
		2320,2330,2360,
		2440,2470,2480,2490,
		2510,2560,
		2600,2630,2660,
		2720,2760,
		2840,2880,
		2940,2980,
		3120,3160); 

	$defensive_scores = array (0,
		50,100, 250,300,350, 500,550, 700,800,900,
		1000,1100,1200,1300,1400,1700,
		2000,2300,2600,2900,
		3200,3500,3800);

	# zero is a PASS OUT; should not use this function!
	if ($score == 0) {
		return ("PASS");
	}

# the two score lists are (hopefully) non-overlapping, so search for the passed-in score and find
# it one or the other list.  Depending on which list, NS was on offense or defense, depending also 
# on the sign of the score - (positive scores being from the NS pov.)

	# Perl: for $scr (@offensive_scores) 
	for ($i=0; $i<= sizeof($offensive_scores); $i++) {
		$scr = $offensive_scores[$i] ;
		if ( $scr == abs ($score) ) {
			return ("OFFENSE");
		}
	}

	# Perl: for $scr (@defensive_scores) 
	for ($i=0; $i<= sizeof($defensive_scores); $i++) {
		$scr = $defensive_scores[$i] ;
		if ( $scr == abs ($score) ) {
			return ("DEFENSE");
		}
	}

	return ("Go Fish"); # oops
}

function NSdefending ($i, $top) {
global $NSmpt;
global $defending_count, $Tot_mpts, $mpts_by_defending, $GoodDefCount;

	$defending_count++;				 	 $Tot_mpts += $NSmpt[$i];
	$mpts_by_defending 				+= $NSmpt[$i];
	if ($NSmpt[$i]/$top > 0.6) { $GoodDefCount++ ;}
	return ;
}

function EWdefending ($i, $top) {
global $EWmpt ;
global $defending_count, $Tot_mpts, $mpts_by_defending, $GoodDefCount;

	$defending_count++;				 	 $Tot_mpts += $EWmpt[$i];
	$mpts_by_defending 				+= $EWmpt[$i];
	if ($EWmpt[$i]/$top > 0.6) { $GoodDefCount++ ;}
	return ;
}

function NSbothDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $player_declaring_count, $pard_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_player_declaring, $mpts_by_pard_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $NSmpt[$i];
	$mpts_by_player_declaring += $NSmpt[$i]; $player_declaring_count++;
	$mpts_by_pard_declaring 	+= $NSmpt[$i]; $pard_declaring_count++;
	if ($NSmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function EWbothDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $player_declaring_count, $pard_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_player_declaring, $mpts_by_pard_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $EWmpt[$i];
	$mpts_by_player_declaring += $EWmpt[$i]; $player_declaring_count++;
	$mpts_by_pard_declaring 	+= $EWmpt[$i]; $pard_declaring_count++;
	if ($EWmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function EorWPardDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $pard_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_pard_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $EWmpt[$i];
	$mpts_by_pard_declaring += $EWmpt[$i]; $pard_declaring_count++;
	if ($EWmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function EorWPlayerDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $player_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_player_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $EWmpt[$i];
	$mpts_by_player_declaring += $EWmpt[$i]; $player_declaring_count++;
	if ($EWmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function NorSPardDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $pard_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_pard_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $NSmpt[$i];
	$mpts_by_pard_declaring += $NSmpt[$i]; $pard_declaring_count++;
	if ($NSmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function NorSPlayerDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $player_declaring_count, $GoodDeclrCount;
global $Tot_mpts, $mpts_by_player_declaring; 

	$declaring_count++;				 	 $Tot_mpts += $NSmpt[$i];
	$mpts_by_player_declaring += $NSmpt[$i]; $player_declaring_count++;
	if ($NSmpt[$i]/$top > 0.6) { $GoodDeclrCount++ ;}
	return ;
}

function NorSPO ($i) {
global $NSmpt;
global $PO_count, $Tot_mpts, $mpts_from_PO; 

	$PO_count++;	
	$mpts_from_PO += $NSmpt[$i];
	$Tot_mpts += $NSmpt[$i];
	return ;
}

function EorWPO ($i) {
global $EWmpt;
global $PO_count, $Tot_mpts, $mpts_from_PO; 

	$PO_count++;	
	$mpts_from_PO += $EWmpt[$i];
	$Tot_mpts += $EWmpt[$i];
	return ;
}

?> 
