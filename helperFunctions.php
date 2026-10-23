<?php

// namespace PhpOffice\PhpSpreadsheet\Chart;
// require_once('vendor/autoload.php');
require_once __DIR__ . '/vendor/phpspreadsheetMYversion/autoload.php';

use PhpOffice\PhpSpreadsheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\ChartColor;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Properties;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\TrendLine;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;

###########################    BEGIN function senddata    ######################
#
#    - called optionally from history and display_pards
#
function senddata($msg, $email,$subj) {

  if (empty($email)) {
		echo "<p class='e'><mark>Cannot send your results because you provided no email address.</mark></p>"; echo "<br>";
    return;
  } else {

		// Always set content-type when sending HTML email
		$header = "MIME-Version: 1.0" . "\r\n";
		$header .= "Content-type:text/html;charset=UTF-8" . "\r\n";

		$header .= "From: rick.garvin@acbridge.org" ;
		mail($email, $subj, $msg, $header);
		echo "<p class='e'>Your results were sent.</p>"; echo "<br>";
  }
}
#
###########################    END function senddata    ######################


###########################  BEGIN function playernum    ######################
#
#    - called from index & controller
#
function playernum($pname, $conn) { # provide player's name; return playernumber

# provide a playername, or a fragment of a name.  If there's an exact name 
# match	return the player's number, or a list of possible name matches.

  $name = $fname = $first = $last = $trunlast = "" ; 
  $city = $st = "";
  $pnum = $numnames = 0 ;
  $match = $list = $flpname = array(); # candidate pnum=>name (could be >1)

  // Check connection
  if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
  }

  $flpname = explode (" ", $pname);
  $numnames = sizeof($flpname);
	if ($numnames == 1) { // assume last name if only 1 name provided
  	$first = '';
  	$last  = $flpname[0];
	} elseif ($numnames == 2) { // assume full name if two names provided; but what about Van Fleet?
  	$first = $flpname[0];
  	$last  = $flpname[1];
		# debug
		# echo "first name=$first last name = $last<br>";
	} elseif ($numnames >= 3) { // assume 2 first names, e.g. mary alice
  	$first = $flpname[0] . ' ' . $flpname[1];
  	$flpname = explode (" ", $pname,3); // resplit into 3 array elements; 
  	$last  = $flpname[2];     // last contains elements 3 and beyond !! php doesn't allow negative array indices
	}

  $lastname_sql = 
	  "SELECT LAST_NAME, FIRST_NAME, city, state, PLAYER_NO FROM ACBLPlayerDBASE WHERE " .  
	  "LAST_NAME LIKE '".$last."%' AND PLAYER_NO != '' ORDER BY LAST_NAME, FIRST_NAME;"; // what if Van Fleet, e.g.

  $firstANDlastname_sql = 
		"SELECT LAST_NAME, FIRST_NAME, city, state, PLAYER_NO FROM ACBLPlayerDBASE WHERE FIRST_NAME " .
		"LIKE '".$first."%' AND LAST_NAME LIKE '".$last."%' AND (PLAYER_NO != '') ORDER BY LAST_NAME, FIRST_NAME;";

	$sql = $firstANDlastname_sql;
  if ($first == '') {
		$sql = $lastname_sql;
	}
	# debug
	# echo "Using SQL<br>$sql<br>";

	// make a list of all lastname matches
	// if only one match, confirm first name matches (if you got it), then return [pnum, name] array
	// if no fn match, create list of lns and present to user: list of [pnum, name] arrays

	$result = mysqli_query($conn, $sql);
  $nmatches = mysqli_num_rows($result) ;

	if ($nmatches == 1) { # found 1 match - if fn+ln, yay!
    $row = mysqli_fetch_assoc($result) ; # don't need to loop 
    $pnum = $row["PLAYER_NO"];
    $name = trim($row["FIRST_NAME"]) . " " . trim($row["LAST_NAME"]);
    # debug
    # echo "Found player $name pnum=$pnum <br>";
    $match = array($pnum, $name);
    return $match; # return simple array with 2 elements
  } 

  if ($nmatches > 1) { 
  # found >1 match; stuff array with candidate's name, city, state
  # debug
  # echo "Found $nmatches for $last<br>";
    while ($row = mysqli_fetch_assoc($result)) {
      $fname = trim($row["FIRST_NAME"]) ;
      $lname = trim($row["LAST_NAME"]) ;
      $pnum = $row["PLAYER_NO"];
      $name = $fname . " " . $lname;
      $city = trim($row["city"]) ;
      $st   = trim($row["state"]);
      $list[$pnum] = array($name,$city,$st);# assoc array with key $pnum
    }
    return $list; # list of matches
	}

	// if still here, found no matches for fn + ln search, or ln-only search.  try again.
	if ($numnames >= 2) { // try combining last 2 names as the last name [Van Fleet]
		# start over
  	$flpname = explode (" ", $pname);
		# debug
		# echo "flpname[0]=$flpname[0] flpname[1] = $flpname[1] flpname[2] = $flpname[2]<br>";
		if ($numnames > 2) {
			$first = $flpname[0];
  		$last  = $flpname[1] . ' ' . $flpname[2];
		} elseif  ($numnames == 2) {
			$first = '';
  		$last  = $flpname[0] . ' ' . $flpname[1];
		}
		# echo "trying first name=$first last name = $last<br>";
		$sql = 
		"SELECT LAST_NAME, FIRST_NAME, city, state, PLAYER_NO FROM ACBLPlayerDBASE WHERE FIRST_NAME " .
		"LIKE '".$first."%' AND LAST_NAME LIKE '".$last."%' AND (PLAYER_NO != '') ORDER BY LAST_NAME, FIRST_NAME;";

		$result = mysqli_query($conn, $sql);
  	$nmatches = mysqli_num_rows($result) ;

		if ($nmatches == 1) { # found 1 match - yay!
    	$row = mysqli_fetch_assoc($result) ; # don't need to loop 
    	$pnum = $row["PLAYER_NO"];
    	$name = trim($row["FIRST_NAME"]) . " " . trim($row["LAST_NAME"]);
    	# debug
    	# echo "Found single player named $name pnum=$pnum <br>";
    	$match = array($pnum, $name);
    	return $match; # return simple array with 2 elements
		} elseif ($nmatches > 1) { 
      # found >1 match; stuff array with candidate's name, city, state
      # debug
      # echo "Found $nmatches for $last<br>";
      while ($row = mysqli_fetch_assoc($result)) {
        $fname = trim($row["FIRST_NAME"]) ;
        $lname = trim($row["LAST_NAME"]) ;
        $pnum = $row["PLAYER_NO"];
        $name = $fname . " " . $lname;
        $city = trim($row["city"]) ;
        $st   = trim($row["state"]);
        $list[$pnum] = array($name,$city,$st);# assoc array with key $pnum
      }
      return $list; # list of matches
		} else {
			# debug
			# echo "$sql failed<br>";
			return;
		}

		// now try with ln only search
		if ($numnames > 2) {
			$last  = $flpname[2];
		} elseif  ($numnames == 2) {
			$last  = $flpname[1];
		}
		# debug
		# echo "trying only last name = $last search <br>";
  $lastname_sql = 
	  "SELECT LAST_NAME, FIRST_NAME, city, state, PLAYER_NO FROM ACBLPlayerDBASE WHERE " .  
	  "LAST_NAME LIKE '".$last."%' AND PLAYER_NO != '' ORDER BY LAST_NAME, FIRST_NAME;"; // what if Van Fleet, e.g.

		$sql = $lastname_sql;

		$result = mysqli_query($conn, $sql);
  	$nmatches = mysqli_num_rows($result) ;

  	if ($nmatches >= 1) { 
  	# found >=1 match; stuff array with candidates name, city, state
  	# debug
  	# echo "Found $nmatches for $last<br>";
    	while ($row = mysqli_fetch_assoc($result)) {
      	$fname = trim($row["FIRST_NAME"]) ;
      	$lname = trim($row["LAST_NAME"]) ;
      	$pnum = $row["PLAYER_NO"];
      	$name = $fname . " " . $lname;
      	$city = trim($row["city"]) ;
      	$st   = trim($row["state"]);
      	$list[$pnum] = array($name,$city,$st); # assoc array with key $pnum
    	}
    	return $list; # list of matches
		}

		if ($nmatches == 0) {
			# debug
			echo "$sql failed<br>";
			return; // empty-handed
		}
	}

	// should get here only when no matches and numnames != 2

	# debug
	echo "$sql failed<br>";
	return; // empty-handed
}
#
###########################  END function playernum    ######################


###########################    BEGIN function playername    ######################
#
#    - called from index page 
#
function playername($pnum, $conn) { # provide playernumber; return player's name

	if ($pnum == '') {
		return;
	}

  // Check connection
  if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
  }

  $name_sql = "SELECT FIRST_NAME, LAST_NAME FROM ACBLPlayerDBASE WHERE PLAYER_NO = '".$pnum."';" ;
#    "SELECT FIRST_NAME, LAST_NAME FROM mplistwnames WHERE PLAYER_NO =$pnum ;" ;

  $result = mysqli_query($conn, $name_sql);
  if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result); 
    return $row["FIRST_NAME"] . " " . $row["LAST_NAME"];
#    return $row["first"] . " " . $row["last"];

  } else {
    echo "Name not found for player number $pnum. SQL=$name_sql<br>";
    return NULL;

  }
}
#
###########################    END function playername    ######################


###########################    BEGIN function get_history ########################################
#
#    - called from controller for query1
#
function get_history($conn, $pnum, $gmqualifier) {

	$history_sql = "
				SELECT count(GameId) AS games_played 
				FROM players 
				WHERE $gmqualifier 
				AND ('$pnum' IN (pnum1, pnum2, pnum3, pnum4, pnum5, pnum6)) ;
		  ";

      $result = mysqli_query($conn, $history_sql);
			
      if ($result && mysqli_num_rows($result) > 0) {
				$row = mysqli_fetch_assoc($result) ;

		    return (int)$row['games_played'];
			}

			return 0 ;
} // query1

#
#
###########################    END function get_history ########################################

###########################    BEGIN function get_partners ########################################
#
#    - called from controller for query2
#
#
function get_all_partners($conn, $pnum, $gmqualifier) {
	$partner_sql =
"
WITH
me AS (
    SELECT $pnum AS me
),

my_game_ids AS (
    SELECT DISTINCT p.GameId, p.club
    FROM events e
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
	CROSS JOIN me
    WHERE e.$gmqualifier
      AND me.me IN (
            p.pnum1, p.pnum2, p.pnum3,
            p.pnum4, p.pnum5, p.pnum6
      )
),

games AS (
    SELECT
        e.GameId,
        e.gametype,
        e.movement,
        p.player1, p.player2, p.player3, p.player4, p.player5, p.player6,
        p.pnum1,   p.pnum2,   p.pnum3,   p.pnum4,   p.pnum5,   p.pnum6,
        p.club,
        COALESCE(NULLIF(p.Flt, ''), 'A') AS strat
    FROM events e
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    JOIN my_game_ids mg
      ON mg.GameId = e.GameId
     AND mg.club   = e.club
),

indy_games AS (
    SELECT DISTINCT GameId
    FROM games 
    CROSS JOIN me
    WHERE gametype = 'indy'
      AND me.me IN (pnum1, pnum2, pnum3, pnum4, pnum5, pnum6)
),

team_games AS (
    SELECT DISTINCT GameId
    FROM games g 
    CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
),

my_games AS (
    SELECT g.*
    FROM games g
    CROSS JOIN me
    WHERE me.me IN (
        g.pnum1, g.pnum2, g.pnum3,
        g.pnum4, g.pnum5, g.pnum6
    )
),

my_partners AS (
    SELECT
        CASE WHEN me.me = g.pnum1 THEN g.pnum2 ELSE g.pnum1 END AS pardnum,
        CASE WHEN me.me = g.pnum1 THEN g.player2 ELSE g.player1 END AS pardName,
        1 AS partner
    FROM my_games g
    CROSS JOIN me
    WHERE
        (g.gametype = 'pairs'
         OR (g.gametype = 'teams' AND g.movement = 'HomeStyle P'))
      AND me.me IN (g.pnum1, g.pnum2)
),

my_teammates AS (

    SELECT g.pnum1 AS pardnum, g.player1 AS pardName, 1 AS teammate
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum1 REGEXP '^[0-9]+$'
      AND g.pnum1 <> me.me

    UNION ALL
    SELECT g.pnum2, g.player2, 1
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum2 REGEXP '^[0-9]+$'
      AND g.pnum2 <> me.me

    UNION ALL
    SELECT g.pnum3, g.player3, 1
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum3 REGEXP '^[0-9]+$'
      AND g.pnum3 <> me.me

    UNION ALL
    SELECT g.pnum4, g.player4, 1
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum4 REGEXP '^[0-9]+$'
      AND g.pnum4 <> me.me

    UNION ALL
    SELECT g.pnum5, g.player5, 1
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum5 REGEXP '^[0-9]+$'
      AND g.pnum5 <> me.me

    UNION ALL
    SELECT g.pnum6, g.player6, 1
    FROM my_games g CROSS JOIN me
    WHERE g.gametype = 'teams'
      AND g.movement = 'Swiss teams'
      AND me.me IN (g.pnum1, g.pnum2, g.pnum3, g.pnum4, g.pnum5, g.pnum6)
      AND g.pnum6 REGEXP '^[0-9]+$'
      AND g.pnum6 <> me.me
),

indy_partners AS (
    SELECT
        g.pnum1 AS pardnum,
        g.player1 AS pardName,
        (SELECT COUNT(*) FROM indy_games) AS indy_game_count
    FROM games g  
    CROSS JOIN me
    WHERE g.gametype = 'indy'
      AND g.pnum1 <> me.me
),
all_partners AS (
    SELECT pardnum, pardName, partner, 0 AS teammate, 0 AS indy
    FROM my_partners

    UNION ALL
    
    SELECT pardnum, pardName, 0, teammate, 0
    FROM my_teammates
    
    UNION ALL
    
    SELECT pardnum, pardName, 0, 0, 1
    FROM indy_partners
)
SELECT
    SUM(partner + teammate + indy) AS total,
    SUM(partner) AS partner_freq,
    SUM(teammate) AS teammate_freq,
    SUM(indy) AS indy_freq,
		pardName AS name,
    pardnum
FROM all_partners
GROUP BY pardnum 

UNION ALL

SELECT
    (SELECT COUNT(*) FROM all_partners),
    (SELECT COUNT(*) FROM my_partners),
    (SELECT COUNT(*) FROM my_teammates),
    (SELECT COUNT(*) FROM indy_games),
    (SELECT COUNT(*) FROM team_games),
    'TOTAL'
ORDER BY
    CASE WHEN pardnum = 'TOTAL' THEN 1 ELSE 0 END,
    total DESC,
    name;	
";

	$result = mysqli_query($conn, $partner_sql);

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

	return $rows;
} // query2

#
#
###########################    END function get_partners #######################################



##################################################################
# global var, "$pair_sql1" used in get_partner_details() as well as
# get_partners_summary()
##################################################################
$pair_sql1 =
"
games AS (
    SELECT
        e.GameId,
        e.section,
        e.gametype,
        e.scoring,
        e.movement,
        p.player_pair_team,
        p.player1, p.player2, p.player3, p.player4, p.player5, p.player6,  
        p.pnum1,   p.pnum2,   p.pnum3,   p.pnum4,   p.pnum5,   p.pnum6,  
        p.club,
        COALESCE(NULLIF(p.Flt, ''), 'A') AS strat, 
        p.Score, p.award1, p.color1, p.award2, p.color2, p.reason,
        p.Pct
    FROM events e
    JOIN players p
      ON 	p.GameId = e.GameId
		AND p.club   = e.club
    JOIN my_game_ids mg
      ON 	mg.GameId = e.GameId
		AND mg.club   = e.club
),

games_with_dir AS (
    SELECT
        g.*,
        CASE
            WHEN g.movement IN ('MITCHELL', 'WEB') THEN
                CASE
                    WHEN RIGHT(g.player_pair_team,1)='N' THEN 'NS'
                    WHEN RIGHT(g.player_pair_team,1)='E' THEN 'EW'
                END
            ELSE NULL
        END AS dir
    FROM games g
),

ranked AS (
    SELECT
        ri.*,
		CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
			RANK() OVER ( PARTITION BY ri.GameId, ri.dir 
						  ORDER BY ri.Score DESC )
			ELSE
			RANK() OVER ( PARTITION BY ri.GameId 
						  ORDER BY ri.Score DESC )
		END AS sec_rank_A,

    CASE WHEN ri.strat IN ('B','C') THEN
      CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
      END
    END AS sec_rank_B,

    CASE WHEN ri.strat = 'C' THEN
      CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
      END
    END AS sec_rank_C,

    RANK() OVER ( PARTITION BY ri.GameId
			ORDER BY ri.Score DESC ) AS ov_rank_A,

    CASE WHEN ri.strat IN ('B','C') THEN
      RANK() OVER (
        PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
        ORDER BY ri.Score DESC
        )
    END AS ov_rank_B,

    CASE WHEN ri.strat = 'C' THEN
      RANK() OVER (
        PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
        ORDER BY ri.Score DESC
        )
    END AS ov_rank_C

    FROM games_with_dir ri 
),

tie_groups AS (
    SELECT
        r.*,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER (PARTITION BY r.GameId, r.dir)
            ELSE
                COUNT(*) OVER (PARTITION BY r.GameId)
        END AS pop_sec_A,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_sec_B,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_sec_C,

        COUNT(*) OVER (PARTITION BY r.GameId) AS pop_ov_A,

        CASE
            WHEN r.strat IN ('B','C') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_ov_B,

        CASE
            WHEN r.strat = 'C' THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_ov_C,

        CASE WHEN r.sec_rank_A IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_A)
        END AS tie_sec_A,

        CASE WHEN r.sec_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_B)
        END AS tie_sec_B,

        CASE WHEN r.sec_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_C)
        END AS tie_sec_C,

        COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_A
        ) AS tie_ov_A,

        CASE WHEN r.ov_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_B
            )
        END AS tie_ov_B,

        CASE WHEN r.ov_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_C
            )
        END AS tie_ov_C

    FROM ranked r
),

min_max AS (
    SELECT
        tg.GameId,
        tg.club,
        tg.player_pair_team,
        tg.strat,
        tg.dir,
        
        tg.pnum1,   tg.pnum2,   tg.pnum3,   tg.pnum4,   tg.pnum5,   tg.pnum6, 
        tg.player1, tg.player2, tg.player3, tg.player4, tg.player5, tg.player6,
        
        tg.gametype,
        tg.scoring,
        tg.movement,
        tg.section,
        tg.Pct, tg.award1, tg.color1, tg.award2, tg.color2, tg.reason,

        tg.pop_sec_A AS pop_sec_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_sec_B END AS pop_sec_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_sec_C END AS pop_sec_C,

        tg.pop_ov_A AS pop_ov_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_ov_B END AS pop_ov_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_ov_C END AS pop_ov_C,

        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0
        END AS frac_sec_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0
        END AS frac_sec_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0
        END AS frac_sec_C,

        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0
        END AS frac_ov_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0
        END AS frac_ov_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0
        END AS frac_ov_C,

        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            tg.pop_sec_A
              - ( (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0 )
              + 1
        END AS sec_rating_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            tg.pop_sec_B
              - ( (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0 )
              + 1
        END AS sec_rating_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            tg.pop_sec_C
              - ( (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0 )
              + 1
        END AS sec_rating_C,

        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            tg.pop_ov_A
              - ( (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0 )
              + 1
        END AS ov_rating_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            tg.pop_ov_B
              - ( (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0 )
              + 1
        END AS ov_rating_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            tg.pop_ov_C
              - ( (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0 )
              + 1
        END AS ov_rating_C

    FROM tie_groups tg
    WHERE tg.ov_rank_A IS NOT NULL   
),

pair_games AS (
    SELECT
        mm.*,
        CASE 
            WHEN me.me = mm.pnum1 THEN mm.pnum2
            ELSE mm.pnum1
        END AS pardnum,

        
        CASE 
            WHEN me.me = mm.pnum1 THEN mm.player2
            ELSE mm.player1
        END AS pardName

    FROM min_max mm
    CROSS JOIN me
    WHERE me.me IN (mm.pnum1, mm.pnum2)
      AND NULLIF(mm.pnum3, '') IS NULL
			AND mm.gametype = 'pairs'
      -- AND mm.movement NOT LIKE 'Swiss%'
),

total_games AS (
    SELECT COUNT(DISTINCT GameId) AS total_games
    FROM pair_games
),

min_games AS (
    SELECT CEIL(0.1 * total_games) AS min_games
    FROM total_games
),

partner_counts AS (
    SELECT
        pardnum,
        COUNT(DISTINCT GameId) AS games_together
    FROM pair_games
    GROUP BY pardnum
),

top_partners AS (
    SELECT pc.pardnum
    FROM partner_counts pc
    CROSS JOIN min_games mg
    WHERE pc.games_together >= mg.min_games
       OR pc.games_together > 20
),

results_enriched AS (
    SELECT
			pg.GameId,
			pg.club,
			pg.gametype,
			pg.Pct, pg.award1, pg.color1, pg.award2, pg.color2, pg.reason,
			pg.strat,
			pg.movement,
			pg.scoring,

			r.board_id,
			r.ETF,
			r.OLE,
			r.contractLevel,
			r.Declarer,
      r.top,
			r.nsmtchpts,
			r.ewmtchpts,

    CASE
			WHEN r.npnum = me.me THEN 'N'
			WHEN r.spnum = me.me THEN 'S'
			WHEN r.epnum = me.me THEN 'E'
			WHEN r.wpnum = me.me THEN 'W'
    END AS my_seat,

    CASE
			WHEN r.npnum = me.me THEN 'S'
			WHEN r.spnum = me.me THEN 'N'
			WHEN r.epnum = me.me THEN 'W'
			WHEN r.wpnum = me.me THEN 'E'
    END AS pard_seat,

    CASE
      WHEN r.contractLevel = 'PASS' THEN 'passout'

				-- me declaring
      	WHEN r.Declarer =
          CASE
            WHEN r.npnum = me.me THEN 'N'
            WHEN r.spnum = me.me THEN 'S'
            WHEN r.epnum = me.me THEN 'E'
            WHEN r.wpnum = me.me THEN 'W'
          END
        	THEN 'me_declr'

				-- pard declaring
        WHEN r.Declarer =
          CASE
            WHEN r.npnum = me.me THEN 'S'
            WHEN r.spnum = me.me THEN 'N'
            WHEN r.epnum = me.me THEN 'W'
            WHEN r.wpnum = me.me THEN 'E'
          END
        	THEN 'pard_declr'

		-- OLE additions
			-- me leading
        WHEN r.Declarer =
          CASE
            WHEN r.wpnum = me.me THEN 'S'
            WHEN r.epnum = me.me THEN 'N'
            WHEN r.npnum = me.me THEN 'W'
            WHEN r.spnum = me.me THEN 'E'
          END
        	THEN 'me_leading'

			-- pard leading
        WHEN r.Declarer =
          CASE
            WHEN r.epnum = me.me THEN 'S'
            WHEN r.wpnum = me.me THEN 'N'
            WHEN r.spnum = me.me THEN 'W'
            WHEN r.npnum = me.me THEN 'E'
          END
        	THEN 'pard_leading'
-- No ELSE needed: all non-PASS cases are covered by the 4 WHEN blocks
		END AS role

    FROM results r

    CROSS JOIN me
    JOIN pair_games pg
      ON  pg.GameId = r.GameId
      AND pg.club   = r.club
      AND me.me IN (r.npnum, r.spnum, r.epnum, r.wpnum)

    LEFT JOIN events e
      ON e.GameId = pg.GameId
      AND e.club   = pg.club
),

games_with_pair_results AS (
    SELECT
        pg.GameId,
        pg.club,
        pg.pardnum,
        pg.pardName,
        pg.gametype,
        pg.Pct, pg.award1, pg.color1, pg.award2, pg.color2, pg.reason,
        pg.strat,
        pg.movement,
        pg.scoring,
        re.board_id,
        re.my_seat,
				re.pard_seat,
        re.role,
        re.ETF,
        re.OLE,
        re.top,
        
	SUM(
		CASE 
			WHEN re.role = 'me_declr' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS my_declr_mtchpts,
	
  SUM(
		CASE 
			WHEN re.role = 'pard_declr' THEN
				CASE WHEN re.pard_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS pard_declr_mtchpts,

	SUM(
		CASE 
      -- role 'dfndng' replaced by two cases
			WHEN re.role = 'pard_leading' OR re.role = 'me_leading' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS def_mtchpts,

	SUM(
		CASE 
			WHEN re.role = 'passout' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS PO_mtchpts

  FROM pair_games pg
  LEFT JOIN results_enriched re
       ON re.GameId = pg.GameId 
			 AND re.club  = pg.club

  GROUP BY
        pg.GameId, pg.club, pg.pardnum, 
        pg.gametype, pg.Pct, pg.strat, pg.movement,
        re.board_id, re.my_seat, re.pard_seat, re.role, re.ETF, re.top

),

final AS (

    SELECT
        g.GameId,
        g.club,
        g.pardnum,
        g.pardName,
        g.gametype,
        g.movement,
        g.scoring,
        g.strat,
        g.Pct, g.award1, g.color1, g.award2, g.color2, g.reason,
        g.top,
        SUM(g.my_declr_mtchpts)   AS my_mtchpts,
				SUM(g.pard_declr_mtchpts) AS pard_mtchpts,
				SUM(g.def_mtchpts)        AS dfndng_mtchpts,
				SUM(g.PO_mtchpts)         AS pass_mtchpts,

        SUM(g.role = 'me_declr')   AS hands_I_declared,
        SUM(g.role = 'pard_declr') AS hands_pard_declared,
        SUM(g.role = 'me_leading' or g.role = 'pard_leading') AS hands_defended,
        SUM(g.role = 'passout')    AS passout_count,

        SUM(g.role = 'me_leading')   AS hands_me_leading,
        SUM(g.role = 'pard_leading') AS hands_pard_leading,
        SUM(g.role = 'me_declr' or g.role = 'pard_declr') AS hands_declaring,

        SUM(CASE WHEN g.role = 'me_declr'   THEN g.ETF END) AS ETF_me,
        SUM(CASE WHEN g.role = 'pard_declr' THEN g.ETF END) AS ETF_pard,
				-- role 'defndng' no longer exists
				SUM(CASE WHEN (g.role = 'pard_leading' OR g.role = 'me_leading') THEN 0 - g.ETF END) 
					AS ETF_defense, 

				-- NEW: OLE buckets
				SUM(CASE WHEN g.role = 'me_leading'   THEN g.OLE END) AS OLE_me,
				SUM(CASE WHEN g.role = 'pard_leading' THEN g.OLE END) AS OLE_pard,
				SUM(CASE WHEN g.role = 'me_declr' OR g.role = 'pard_declr' THEN 0 - g.OLE END) 
					AS OLE_opp,

        mm.frac_ov_A,  
        mm.frac_ov_B, 
        mm.frac_ov_C,

        mm.frac_sec_A, 
        mm.frac_sec_B, 
        mm.frac_sec_C, 

        mm.ov_rating_A,  
        mm.pop_ov_A,     

        mm.ov_rating_B,  
        mm.pop_ov_B,     

        mm.ov_rating_C,  
        mm.pop_ov_C,     

        mm.sec_rating_A, 
        mm.pop_sec_A,    

        mm.sec_rating_B, 
        mm.pop_sec_B,    

        mm.sec_rating_C, 
        mm.pop_sec_C,    

        COUNT(*) AS boards_played
    
    FROM games_with_pair_results g
    CROSS JOIN me
    LEFT JOIN min_max mm
      ON mm.GameId = g.GameId
     AND mm.club   = g.club
     AND me.me  IN (mm.pnum1, mm.pnum2)
     AND g.pardnum IN (mm.pnum1, mm.pnum2)


	GROUP BY
        g.GameId,
        g.club,
        g.pardnum,
        -- g.pardName,
        g.gametype,
        g.movement,
        g.strat,
        g.Pct,
        g.top,
        mm.frac_ov_A,    mm.frac_ov_B,    mm.frac_ov_C,
        mm.frac_sec_A,   mm.frac_sec_B,   mm.frac_sec_C,
        mm.ov_rating_A,  mm.ov_rating_B,  mm.ov_rating_C,
        mm.pop_ov_A,     mm.pop_ov_B,     mm.pop_ov_C,
        mm.sec_rating_A, mm.sec_rating_B, mm.sec_rating_C,
        mm.pop_sec_A,    mm.pop_sec_B,    mm.pop_sec_C

),

pair_summary AS (
SELECT
    f.GameId,
    f.club,
    f.pardnum,
    f.pardName,
    pc.games_together,
    f.gametype,
    f.movement,
    f.scoring,

    COALESCE(NULLIF(f.strat, ''), 'A') AS strat,

    f.Pct, f.award1, f.color1, f.award2, f.color2, f.reason,
    f.boards_played,

    f.hands_I_declared,
    f.hands_pard_declared,
    f.hands_defended,
    f.passout_count,
    f.hands_me_leading,
    f.hands_pard_leading,
    f.hands_declaring,
	
    f.my_mtchpts,
    f.pard_mtchpts,
    f.dfndng_mtchpts,
    f.pass_mtchpts,
   
    ROUND(100 * f.hands_I_declared    / NULLIF(f.boards_played, 0), 1) AS my_dclr_frq,

    ROUND(100 * f.hands_pard_declared / NULLIF(f.boards_played, 0), 1) AS pard_dclr_frq,

    ROUND(100 * f.hands_defended      / NULLIF(f.boards_played, 0), 1) AS def_frq,

    ROUND(100 * f.passout_count       / NULLIF(f.boards_played, 0), 1) AS PO_frq,
    
		ROUND(100 * f.my_mtchpts     / NULLIF(f.hands_I_declared * f.top, 0), 1) AS my_dclr_scr,

		ROUND(100 * f.pard_mtchpts   / NULLIF(f.hands_pard_declared * f.top, 0), 1) AS pard_dclr_scr,

		ROUND(100 * f.dfndng_mtchpts / NULLIF(f.hands_defended * f.top, 0), 1) AS def_scr,

		ROUND(100 * f.pass_mtchpts   / NULLIF(f.passout_count * f.top, 0), 1) AS PO_scr,

    f.ETF_me,
    f.ETF_pard,
    f.ETF_defense,
		f.OLE_me,
		f.OLE_pard,
		f.OLE_opp,

		f.frac_ov_A,    f.frac_ov_B,    f.frac_ov_C,        

		f.frac_sec_A,   f.frac_sec_B,   f.frac_sec_C,
			
		f.ov_rating_A,  f.pop_ov_A,
    f.ov_rating_B,  f.pop_ov_B,
    f.ov_rating_C,  f.pop_ov_C,

		f.sec_rating_A, f.pop_sec_A, 
    f.sec_rating_B, f.pop_sec_B,
    f.sec_rating_C, f.pop_sec_C

FROM final f
JOIN partner_counts pc ON pc.pardnum = f.pardnum
JOIN top_partners tp   ON tp.pardnum = f.pardnum

ORDER BY
    pc.games_together DESC,
    f.pardnum,
    f.GameId
)
";

###########################    BEGIN function get_partner_details ##############################
#
#    - called from controller for query3a
#
#
function get_partner_details($conn, $pnum, $gmqualifier) {
	global $pair_sql1 ;

	$pair_sql_hdr =
"WITH
me AS (
    SELECT $pnum AS me
),
my_game_ids AS (
    SELECT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),"
;

	$pair_sql2 =
"
SELECT 
    p.GameId,
    p.club,
    ps.pardnum,
    ps.pardName,
    ps.games_together,
    ps.gametype,
    ps.movement,
    CASE ps.scoring
		WHEN 'VICTORY POINT' THEN 'VP'
        WHEN 'WIN-LOSS' 	 THEN 'W-L'
        WHEN 'MATCHPOINTS'   THEN 'Mtchpts'
    END AS ScrMethod,
		ps.strat,
    ps.Pct, ps.award1, ps.color1, ps.award2, ps.color2, ps.reason,
    ps.boards_played,

    -- Board-level counts
    ps.hands_I_declared,
    ps.hands_pard_declared,
    ps.hands_defended,
    ps.passout_count,
	
    ps.my_dclr_frq,
    ps.pard_dclr_frq,
    ps.def_frq,
    ps.PO_frq,
    
    -- NEW: Declarer/defense/passout scoring metrics
    ps.my_dclr_scr,
    ps.pard_dclr_scr,
    ps.def_scr,
    ps.PO_scr,

    -- ETFs
    ps.ETF_me,
    ps.ETF_pard,
    ps.ETF_defense,
    ps.OLE_me,
		ps.OLE_pard,
		ps.OLE_opp,

    -- Cleaned Overall rankings
		clean_num(ps.frac_ov_A) AS ovRnkA,    
    clean_num(ps.frac_ov_B) AS ovRnkB,    
    clean_num(ps.frac_ov_C) AS ovRnkC,        

    -- Cleaned Section rankings
		clean_num(ps.frac_sec_A) AS secRnkA,   
    clean_num(ps.frac_sec_B) AS secRnkB,   
    clean_num(ps.frac_sec_C) AS secRnkC,
    
    -- Cleaned Overall ratings
		clean_num(ps.ov_rating_A) AS ovRtgA,  pop_ov_A,
    clean_num(ps.ov_rating_B) AS ovRtgB,  pop_ov_B,
    clean_num(ps.ov_rating_C) AS ovRtgC,  pop_ov_C,

    -- Cleaned Section ratings
		clean_num(ps.sec_rating_A) AS secRtgA, pop_sec_A, 
    clean_num(ps.sec_rating_B) AS secRtgB, pop_sec_B,
    clean_num(ps.sec_rating_C) AS secRtgC, pop_sec_C
    
FROM pair_summary ps
join players p
		ON ps.GameId = p.GameId
    AND ps.club   = p.club
GROUP BY ps.GameId, ps.club
ORDER BY
	games_together DESC,
		pardnum, GameId ;"
;

	$pair_details_sql = $pair_sql_hdr . $pair_sql1 . $pair_sql2;
	$result = mysqli_query($conn, $pair_details_sql);

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

	return $rows;
} // query3a

###########################    BEGIN function get_partner_summary ##############################
#
#    - called from controller for query3b
#
#
function get_partners_summary($conn, $pnum, $gmqualifier) {
	global $pair_sql1 ;

	$pair_sql_hdr =
"WITH
me AS (
    SELECT $pnum AS me
),
my_game_ids AS (
    SELECT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),"
;

	$partner_summary_sql =
"
, partner_summary AS (
    SELECT
      ps.pardnum,
      ps.pardName,
      pc.games_together,

    ROUND(AVG(ps.Pct), 1) AS avPct,

		ROUND(AVG(ps.my_dclr_frq), 1)   AS my_av_dcl_frq,
		ROUND(AVG(ps.my_dclr_scr), 1)   AS my_av_dcl_scr,

		ROUND(AVG(ps.pard_dclr_frq), 1) AS pard_av_dcl_frq,
    ROUND(AVG(ps.pard_dclr_scr), 1) AS pard_av_dcl_scr,
		
		ROUND(AVG(ps.def_frq), 1)       AS our_av_df_frq,
    ROUND(AVG(ps.def_scr), 1)       AS our_av_df_scr,
        
		ROUND(AVG(ps.PO_frq), 1) 	    	AS avg_PO_frq,
		ROUND(AVG(ps.PO_scr), 1) 	    	AS avg_PO_scr,

    SUM(ps.boards_played) AS total_boards,
        
    ROUND(SUM(ps.ETF_me) / SUM(ps.hands_I_declared), 3) AS MyETFperBd,
		ROUND(SUM(ps.ETF_pard) / SUM(ps.hands_pard_declared), 3) AS PardETFperBd,
		ROUND(SUM(ps.ETF_defense) / SUM(ps.hands_defended), 3) AS DefETFperBd,

-- OLE addition
		ROUND(SUM(ps.OLE_me)      / SUM(ps.hands_me_leading), 3)   AS MyOLEperBd,
		ROUND(SUM(ps.OLE_pard)    / SUM(ps.hands_pard_leading), 3) AS PardOLEperBd,
		ROUND(SUM(ps.OLE_opp)     / SUM(ps.hands_declaring), 3) 	 AS OppOLEperBd,

    ROUND(100 * SUM(ps.ov_rating_A) / NULLIF(SUM(ps.pop_ov_A), 0), 1)  AS ov_avr_A,
    ROUND(100 * SUM(ps.ov_rating_B) / NULLIF(SUM(ps.pop_ov_B), 0), 1)  AS ov_avr_B,
    ROUND(100 * SUM(ps.ov_rating_C) / NULLIF(SUM(ps.pop_ov_C), 0), 1)  AS ov_avr_C,

    ROUND(100 * SUM(ps.sec_rating_A) / NULLIF(SUM(ps.pop_sec_A), 0), 1) AS sec_avr_A,
    ROUND(100 * SUM(ps.sec_rating_B) / NULLIF(SUM(ps.pop_sec_B), 0), 1) AS sec_avr_B,
    ROUND(100 * SUM(ps.sec_rating_C) / NULLIF(SUM(ps.pop_sec_C), 0), 1) AS sec_avr_C

    FROM pair_summary ps
    JOIN partner_counts pc
      ON pc.pardnum = ps.pardnum

	WHERE movement NOT LIKE 'Indy%'

    GROUP BY
        ps.pardnum,
        pc.games_together

    ORDER BY
        pc.games_together DESC,
        ps.pardnum

) -- final SELECT

    SELECT
        pardnum,
        pardName,
        games_together,

    clean_num(avPct) AS avg_pct,

		clean_num(my_av_dcl_frq) AS my_av_decl_frq,
		clean_num(my_av_dcl_scr) AS my_av_decl_scr,

		clean_num(pard_av_dcl_frq) AS pard_av_decl_frq,
    clean_num(pard_av_dcl_scr) AS pard_av_decl_scr,
		
		clean_num(our_av_df_frq) AS our_av_def_frq,
    clean_num(our_av_df_scr) AS our_av_def_scr,
        
		clean_num(avg_PO_frq) AS av_PO_frq,
		clean_num(avg_PO_scr) AS av_PO_scr,

    total_boards,

    MyETFperBd,
    PardETFperBd,
    DefETFperBd,

		MyOLEperBd,
		PardOLEperBd,
		OppOLEperBd,

    clean_num(ov_avr_A) AS ov_avrtg_A,
    clean_num(ov_avr_B) AS ov_avrtg_B,
    clean_num(ov_avr_C) AS ov_avrtg_C,

    clean_num(sec_avr_A) AS sec_avrtg_A,
    clean_num(sec_avr_B) AS sec_avrtg_B,
    clean_num(sec_avr_C) AS sec_avrtg_C

    FROM partner_summary 

    GROUP BY
        pardnum,
        games_together

    ORDER BY
    avg_pct DESC, 
		games_together DESC,
		pardnum ;
";

	$partnerS_sql = $pair_sql_hdr . $pair_sql1 . $partner_summary_sql ;
	$result = mysqli_query($conn, $partnerS_sql); 

	if (!$result) {
    error_log("SQL ERROR: " . mysqli_error($conn));
    error_log("SQL QUERY: " . $partnerS_sql);
    return [];  // prevent further errors
	}

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

	return $rows;
} // query3b

##################################################################
# global "$teamgame_sql_body" - used in get_teamgames() and in 
# get_teammates_summary
##################################################################
	$teamgame_sql_body =
"
games AS (
    SELECT
        e.GameId,
        e.section,
        e.gametype,
        e.scoring,
        e.movement,
        e.rounds,
        p.player_pair_team,
        p.player1, p.player2, p.player3, p.player4, p.player5, p.player6,  -- needed for team games!
        p.pnum1,   p.pnum2,   p.pnum3,   p.pnum4,   p.pnum5,   p.pnum6,  -- needed for team games!
        p.club,
        COALESCE(NULLIF(p.Flt, ''), 'A') AS strat, -- deal with Flt=NULL
        p.Score,
        p.Pct
    FROM events e
    JOIN players p
      ON 	p.GameId = e.GameId
		AND p.club   = e.club
    JOIN my_game_ids mg
      ON 	mg.GameId = e.GameId
		AND mg.club   = e.club
),

games_with_dir AS (
    SELECT
        g.*,
        CASE
            WHEN g.movement IN ('MITCHELL', 'WEB') THEN
                CASE
                    WHEN RIGHT(g.player_pair_team,1)='N' THEN 'NS'
                    WHEN RIGHT(g.player_pair_team,1)='E' THEN 'EW'
                END
            ELSE NULL
        END AS dir
    FROM games g
),

ranked AS (
    SELECT
        ri.*,


		CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
			RANK() OVER ( PARTITION BY ri.GameId, ri.dir 
						  ORDER BY ri.Score DESC )
			ELSE
			RANK() OVER ( PARTITION BY ri.GameId 
						  ORDER BY ri.Score DESC )
		END AS sec_rank_A,

        CASE WHEN ri.strat IN ('B','C') THEN
            CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
            END
        END AS sec_rank_B,

        CASE WHEN ri.strat = 'C' THEN
            CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
            END
        END AS sec_rank_C,

        RANK() OVER ( PARTITION BY ri.GameId
					  ORDER BY ri.Score DESC ) AS ov_rank_A,

        CASE WHEN ri.strat IN ('B','C') THEN
            RANK() OVER (
                PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
                ORDER BY ri.Score DESC
            )
        END AS ov_rank_B,

        CASE WHEN ri.strat = 'C' THEN
            RANK() OVER (
                PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
                ORDER BY ri.Score DESC
            )
        END AS ov_rank_C

    FROM games_with_dir ri -- bypassing 'ranked_input'
),
tie_groups AS (
    SELECT
        r.*,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER (PARTITION BY r.GameId, r.dir)
            ELSE
                COUNT(*) OVER (PARTITION BY r.GameId)
        END AS pop_sec_A,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_sec_B,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_sec_C,

        COUNT(*) OVER (PARTITION BY r.GameId) AS pop_ov_A,

        CASE
            WHEN r.strat IN ('B','C') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_ov_B,

        CASE
            WHEN r.strat = 'C' THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_ov_C,

        CASE WHEN r.sec_rank_A IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_A)
        END AS tie_sec_A,

        CASE WHEN r.sec_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_B)
        END AS tie_sec_B,

        CASE WHEN r.sec_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_C)
        END AS tie_sec_C,

        COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_A
        ) AS tie_ov_A,

        CASE WHEN r.ov_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_B
            )
        END AS tie_ov_B,

        CASE WHEN r.ov_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_C
            )
        END AS tie_ov_C

    FROM ranked r
),

min_max AS (
    SELECT
        tg.GameId,
        tg.club,
        tg.player_pair_team,
        tg.strat,
        tg.dir,

        -- pair identity
        tg.pnum1,   tg.pnum2,   tg.pnum3,   tg.pnum4,   tg.pnum5,   tg.pnum6, 
        tg.player1, tg.player2, tg.player3, tg.player4, tg.player5, tg.player6,

        -- game metadata
        tg.gametype,
        tg.scoring,
        tg.score,
        tg.movement,
        tg.rounds,
        tg.section,
        tg.Pct,

        tg.pop_sec_A AS pop_sec_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_sec_B END AS pop_sec_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_sec_C END AS pop_sec_C,

        tg.pop_ov_A AS pop_ov_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_ov_B END AS pop_ov_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_ov_C END AS pop_ov_C,

        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0
        END AS frac_sec_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0
        END AS frac_sec_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0
        END AS frac_sec_C,

        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0
        END AS frac_ov_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0
        END AS frac_ov_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0
        END AS frac_ov_C,

        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            tg.pop_sec_A
              - ( (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0 )
              + 1
        END AS sec_rating_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            tg.pop_sec_B
              - ( (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0 )
              + 1
        END AS sec_rating_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            tg.pop_sec_C
              - ( (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0 )
              + 1
        END AS sec_rating_C,

        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            tg.pop_ov_A
              - ( (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0 )
              + 1
        END AS ov_rating_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            tg.pop_ov_B
              - ( (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0 )
              + 1
        END AS ov_rating_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            tg.pop_ov_C
              - ( (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0 )
              + 1
        END AS ov_rating_C

    FROM tie_groups tg
    WHERE tg.ov_rank_A IS NOT NULL   -- canonical row: one per pair per game
),
team_games AS (
    SELECT mm.* 
    
    FROM min_max mm
    CROSS JOIN me
    WHERE NULLIF(mm.pnum3, '') IS NOT NULL      -- team games only
      AND me.me IN (                            -- my team only
            mm.pnum1, mm.pnum2, mm.pnum3,
            mm.pnum4, mm.pnum5, mm.pnum6
      )
),

team_unnested AS (
    SELECT
        tg.GameId,
        tg.player_pair_team,
        tg.club,
        tg.movement,
        tg.rounds,
        tg.strat,
        tg.scoring,
        tg.score,
        tg.Pct,
        tg.frac_ov_A, tg.frac_ov_B, tg.frac_ov_C,
        tg.ov_rating_A, tg.ov_rating_B, tg.ov_rating_C,
        tg.pop_ov_A, tg.pop_ov_B, tg.pop_ov_C,
        slots.slot,
        CASE slots.slot
            WHEN 1 THEN NULLIF(tg.pnum1, '')
            WHEN 2 THEN NULLIF(tg.pnum2, '')
            WHEN 3 THEN NULLIF(tg.pnum3, '')
            WHEN 4 THEN NULLIF(tg.pnum4, '')
            WHEN 5 THEN NULLIF(tg.pnum5, '')
            WHEN 6 THEN NULLIF(tg.pnum6, '')
        END AS teammate_pnum,
        CASE slots.slot
            WHEN 1 THEN NULLIF(tg.player1, '')
            WHEN 2 THEN NULLIF(tg.player2, '')
            WHEN 3 THEN NULLIF(tg.player3, '')
            WHEN 4 THEN NULLIF(tg.player4, '')
            WHEN 5 THEN NULLIF(tg.player5, '')
            WHEN 6 THEN NULLIF(tg.player6, '')
        END AS teammate_name
    FROM team_games tg
    JOIN (
        SELECT 1 AS slot UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
    ) AS slots
),

team_teammates_only AS (
    SELECT *
    FROM team_unnested tu
    CROSS JOIN me
    WHERE tu.teammate_pnum <> me.me
),

team_pivoted AS (
    SELECT
        GameId,
        player_pair_team,
        club,
        movement,
        rounds,
        strat,
        scoring,
        score,
        -- max_score,
        -- Calc_Pct,
        Pct,
        frac_ov_A, frac_ov_B, frac_ov_C,
		ov_rating_A, ov_rating_B, ov_rating_C,
		pop_ov_A, pop_ov_B, pop_ov_C,
        
        MAX(CASE WHEN rn = 1 THEN teammate_name END) AS teammate1,
        MAX(CASE WHEN rn = 2 THEN teammate_name END) AS teammate2,
        MAX(CASE WHEN rn = 3 THEN teammate_name END) AS teammate3,
        MAX(CASE WHEN rn = 4 THEN teammate_name END) AS teammate4,
        MAX(CASE WHEN rn = 5 THEN teammate_name END) AS teammate5

    FROM (
        SELECT
            tu.*,
            ROW_NUMBER() OVER (
                PARTITION BY GameId, player_pair_team
                ORDER BY slot
            ) AS rn
        FROM team_teammates_only tu
    ) AS numbered

    GROUP BY
        GameId, club, player_pair_team, 
        movement, strat, Pct,
        frac_ov_A, frac_ov_B, frac_ov_C,
        ov_rating_A, ov_rating_B, ov_rating_C,
        pop_ov_A, pop_ov_B, pop_ov_C
)  -- THIS is the last CTE for teamgames
";


###########################    BEGIN function get_teamgames ##############################
#
#    - called from controller for query4a
#
#
function get_teamgames($conn, $pnum, $gmqualifier) {
	global $teamgame_sql_body;  

	// error_log("get_teamgames() START");

	$teamgame_sql_hdr =
"
WITH

me AS (
    SELECT $pnum AS me
),
my_game_ids AS (
    SELECT DISTINCT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),
";

	$teamgame_sql_select =
"
SELECT 
	GameId,
    player_pair_team AS Team_id,
	club,
	movement,
    strat,
    CASE scoring
		WHEN 'VICTORY POINT' THEN 'VP'
        WHEN 'WIN-LOSS' 	 THEN 'W-L'
        WHEN 'MATCHPOINTS'   THEN 'Mtchpts'
    END AS ScrMethod,
    rounds,
	score,
    -- max_score,
    -- Calc_Pct,
    Pct,
    teammate1,
    teammate2,
    teammate3,
    teammate4,
    teammate5,
    clean_num(frac_ov_A) AS Ov_Rnk_A, 
	clean_num(frac_ov_B) AS Ov_Rnk_B,
    clean_num(frac_ov_C) AS Ov_Rnk_C,
	clean_num(ov_rating_A) AS Ov_Rtg_A,
		pop_ov_A AS pop_A, 
	clean_num(ov_rating_B) AS Ov_Rtg_B,
		pop_ov_B AS pop_B, 
	clean_num(ov_rating_C) AS Ov_Rtg_C,
		pop_ov_C AS pop_C
    
FROM team_pivoted
ORDER BY GameId, club;
";

	
	$teamgames_sql = $teamgame_sql_hdr . $teamgame_sql_body . $teamgame_sql_select ;
	$result = mysqli_query($conn, $teamgames_sql); 

	if (!$result) {
    error_log("SQL ERROR: " . mysqli_error($conn));
    error_log("SQL QUERY: " . $teamgames_sql);
    return [];  // prevent further errors
	}

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

	if (empty($rows) || (count($rows) === 1 && empty(array_filter($rows[0])))) {
		return [];
	}

	return $rows;

} // query4a

###########################    BEGIN function get_teammates_summary ##############################
#
#    - called from controller for query4b
#
#
function get_teammates_summary($conn, $pnum, $gmqualifier) {
	global $teamgame_sql_body;  

	$teamgame_sql_hdr =
"
WITH

me AS (
    SELECT $pnum AS me
),
my_game_ids AS (
    SELECT DISTINCT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),
";

	$teammate_sql_hdr  = $teamgame_sql_hdr; // redundant

	$teammate_sql_body = $teamgame_sql_body; // redundant 

	$teammate_sql_final_select = 
"
, -- must add comma following previous CTE (team_pivoted)

teammate_summary AS (
    SELECT
        teammate_pnum,
        teammate_name,
        COUNT(*) AS games_together,

        ROUND(AVG(Pct), 1) AS avg_pct,

        -- Weighted ratings
        ROUND(100 * SUM(ov_rating_A) / NULLIF(SUM(pop_ov_A), 0), 1) AS raw_ovRtgA,
        ROUND(100 * SUM(ov_rating_B) / NULLIF(SUM(pop_ov_B), 0), 1) AS raw_ovRtgB,
        ROUND(100 * SUM(ov_rating_C) / NULLIF(SUM(pop_ov_C), 0), 1) AS raw_ovRtgC

    FROM team_teammates_only
    GROUP BY teammate_pnum, teammate_name
) -- THIS is the last CTE for teammate_summary

SELECT
    teammate_pnum,
    teammate_name,
    games_together,
    avg_pct AS avg_Score,

    clean_num(raw_ovRtgA) AS avgRtngA,
    clean_num(raw_ovRtgB) AS avgRtngB,
    clean_num(raw_ovRtgC) AS avgRtngC

FROM teammate_summary
WHERE games_together > 2
ORDER BY avg_Score DESC, games_together DESC, avgRtngA DESC, teammate_name;
";

	$teammates_sql = $teammate_sql_hdr . $teammate_sql_body . $teammate_sql_final_select ;
	$result = mysqli_query($conn, $teammates_sql); 

	if (!$result) {
    error_log("SQL ERROR: " . mysqli_error($conn));
    error_log("SQL QUERY: " . $teammates_sql);
    return [];  // prevent further errors
	}

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

	return $rows;

} // query4b


###########################    function make_spreadsheet    ######################
#
#    - called from controller for query5
#
# acquire info about all deals played by $pnum over a time period;
# Create an Excel ss with tabs containing: Trend Charts; Correlation charts; 
# (and a top-level Executive Summary?).
#
##################################################################################

function make_spreadsheet($conn, $pnum, $gmqualifier) {
  # array of rows in SS
  $dataArray = $SScol = $SSrow = array();

//	struct for $games HCP calcs
//		$games[$gmid] = [
//   									'club'   => $club,
//   									'boards' => [
//       													$bd => [
//           														 'myseat' 	=> $myseat,
//           														 'pardseat' => $pardseat,
//           														 'myhcp'  	=> $myhcpForThisHand,
//           														 'pardhcp'  => $pardhcpForThisHand,
//       																 ],
//   															],
//										];

	$details = [];
	// get pard's pair game details
	$details = get_spreadsheet_data ($conn, $pnum, $gmqualifier) ; 

	if (count($details)== 0) {
		return ''; // controller knows no data for ss.
	} else { // build the spreadsheet

		$games   = [];
		// get HCP for player + pard
		$games = get_HCP_by_board($conn, $pnum, $gmqualifier);

		$gm_cnt = 1; // header row

		// Fill SS sheet 'data'
		foreach ($details as $r) {
			$gm_cnt++; // also row count; row 1 is the header row which is embedded in the xl template

			$SSrow=array(); # start with an empty row
			
			# col A is the XL function DATEVALUE(cell)
			$datevaluestr = '=DATEVALUE(B' . ($gm_cnt) . ')';
			array_push ($SSrow, $datevaluestr); # first col is Excel DATEVALUE, followed by the SScols

			$gmid = $r['GameId'] ;
			$yy =   substr($gmid, 0, 2) ;
			$mm =   substr($gmid, 2, 2) ;
			$dd =   substr($gmid, 4, 2) ;
			$MAE=   substr($gmid, 6, 1);
			$SSDate = $mm . "/" . $dd . "/" . "20" . $yy; # SS Date
			array_push ($SSrow, $SSDate );
			array_push ($SSrow, $gmid );
			$club = trim($r['club']) ;
			array_push ($SSrow, $club );
			array_push ($SSrow, $r['pardName']) ;
			array_push ($SSrow, $r['pardnum']) ;
			array_push ($SSrow, $r['games_together']) ;
			array_push ($SSrow, $r['gametype']) ;
			array_push ($SSrow, $r['rating']) ;
			array_push ($SSrow, $r['movement']) ;
			array_push ($SSrow, $r['ScrMethod']) ;
			array_push ($SSrow, $r['strat']) ;
			array_push ($SSrow, $r['Pct']) ;
			# SS Award; suppress clr2 award if absent
			$AwardStr = $r['award1'] . " " .$r['color1'] . " " .
								 ($r['award2'] > 0 ? " + ".$r['award2'] : "")." " .($r['color2'] ?? "") ;
			array_push ($SSrow, $AwardStr) ;
			array_push ($SSrow, $r['reason']) ;
			array_push ($SSrow, $r['boards_played']) ;
			array_push ($SSrow, $r['hands_I_declared']) ;
			array_push ($SSrow, $r['hands_pard_declared']) ;
			array_push ($SSrow, $r['hands_defended']) ;
			array_push ($SSrow, $r['passout_count']) ;

			# "%Declaring" header in SS
			// $SScol[14] = $r['my_dclr_frq'] + $r['pard_dclr_frq']; # "How often we declare"
			$DeclFrq = 100*($r['hands_I_declared'] + $r['hands_pard_declared']) / $r['boards_played']; # "How often we declare"
			array_push ($SSrow, $DeclFrq);  
			array_push ($SSrow, $r['my_dclr_frq']) ;
			array_push ($SSrow, $r['my_dclr_scr']) ;
			array_push ($SSrow, $r['pard_dclr_frq']) ;
			array_push ($SSrow, $r['pard_dclr_scr']) ;
			array_push ($SSrow, $r['def_frq']) ;
			array_push ($SSrow, $r['def_scr']) ;
			array_push ($SSrow, $r['PO_frq']) ;
			array_push ($SSrow, $r['PO_scr']) ;
			array_push ($SSrow, $r['ETF_me']) ;
			array_push ($SSrow, $r['ETF_pard']) ;
			array_push ($SSrow, $r['ETF_defense']) ;
			array_push ($SSrow, $r['OLE_me']) ;
			array_push ($SSrow, $r['OLE_pard']) ;
			array_push ($SSrow, $r['OLE_opp']) ;

			// convert frac Ranking 2.5 => "2/3"
			// convert null to "-" 
			$ovRnkA=  fractionalRank($r['ovRnkA']) ;
			$ovRnkB=  fractionalRank($r['ovRnkB']) ;
			$ovRnkC=  fractionalRank($r['ovRnkC']) ;
			$ovRnk_string  = $ovRnkA  . " " . dash($ovRnkB)  . " " .dash($ovRnkC)  ;

			$secRnkA= fractionalRank($r['secRnkA']) ;
			$secRnkB= fractionalRank($r['secRnkB']) ;
			$secRnkC= fractionalRank($r['secRnkC']) ;
			$secRnk_string = $secRnkA . " " . dash($secRnkB) . " " .dash($secRnkC)  ;

			array_push ($SSrow, $ovRnk_string) ;  
			array_push ($SSrow, $secRnk_string) ;  
								
			$ovRtg_string = $r['ovRtgA'] . " of " . $r['pop_ov_A'] . " " ; // strat A ov rating always present
			$ovRtg_string .= (dash($r['ovRtgB']) !== "-") ?  $r['ovRtgB'] . " of " . $r['pop_ov_B'] . " " : " -";
			$ovRtg_string .= (dash($r['ovRtgC']) !== "-") ?  $r['ovRtgC'] . " of " . $r['pop_ov_C'] : " -";

			$secRtg_string = $r['secRtgA'] . " of " . $r['pop_sec_A'] . " " ; // strat A sec rating always present 
			$secRtg_string .= (dash($r['secRtgB']) !== "-") ?  $r['secRtgB'] . " of " . $r['pop_sec_B'] . " " : " -";
			$secRtg_string .= (dash($r['secRtgC']) !== "-") ?  $r['secRtgC'] . " of " . $r['pop_sec_C'] : " -";

			array_push ($SSrow, $ovRtg_string) ; 
			array_push ($SSrow, $secRtg_string) ; 

			// aveHCP from $games struct
			// skip if this game has no deals for HCP calc
			if (isset($games[$gmid])) {
				$boards    = $games[$gmid]['boards'];

				$mytotalHCP  	 = array_sum(array_column($games[$gmid]['boards'], 'myhcp'));
				$pardtotalHCP  = array_sum(array_column($games[$gmid]['boards'], 'pardhcp'));
				$bdsPlayed 		 = count($boards);
				$myavgHCP 	 	 = $mytotalHCP / $bdsPlayed;
				$pardavgHCP 	 = $pardtotalHCP / $bdsPlayed;
				// DO NOT USE sprintf to insert values into Excel - Excel considers it text
				// $avgHCP 	 = sprintf("%8.2f",$totalHCP / $bdsPlayed);
				array_push ($SSrow, $myavgHCP) ; 
				array_push ($SSrow, $pardavgHCP) ; 
			} else {
				array_push ($SSrow, null) ; 
				array_push ($SSrow, null) ; 
			}
				#	3 - write	$SSrow to dataArray
			array_push($dataArray, $SSrow); # add this row to the 2D array of rows
		}

		# open the XLSS template
		$headerOnly   = "headerOnly.xlsx" ;
		$reader       = IOFactory::createReader('Xlsx');
		$reader->setIncludeCharts(TRUE);
		$Wrkbk  = $reader->load($headerOnly);

		# insert the data into sheet 'Data'
		$Wrkbk->getSheetByName('Data')
				->fromArray(  
				$dataArray,  // The data to set
				"9999",      // Array values with this value will not be set
				'A2'         // Top left coordinate of the worksheet range where
										 //    we want to set these values (default is A1)
		);  

		# autoformat the columns in 'Data' sheet
		$Datasheet = $Wrkbk->getSheetByName('Data');
		foreach (range('A', 'AI') as $col) {
			$Datasheet->getColumnDimension($col)->setAutoSize(true);  
		}

		$dateMinMax = playDateRange ($gm_cnt, $Wrkbk, $details) ; // return array
		// measure the time span of games played.  change chart tick marks to match
		$nQtrs = sprintf("%3.2f",  (($dateMinMax['max'] - $dateMinMax['min'])/30.5)/4 ) ;
		// echo "<br>play spans $nQtrs quarters<br>";

	//---------------------------- begin Chart creation

	# create the 'Charts' worksheet
		$Wrkbk->createSheet();
		$Chart_sheet = $Wrkbk->getSheet(1);
		$Chart_sheet->setTitle("Charts");

		$Chart_sheet = $Wrkbk->getSheetByName('Charts');

		// column definitions - because I keep moving and adding columns
		$DeclScrCol = 'W';
		$PardScrCol = 'Y';
		$DefScrCol  = 'AA';
		$PerCentDeclrngCol = 'U';
		$GameScrCol = 'M';
		$AveHCPCol  = 'AN';

	// --------------- Add the 5 Trend Charts -----------------------------------
	// --------------- Add the 6 Trend Charts -----------------------------------
	//
		#  create_Trend_chart(
		#    $column, // column containing data
		#    $nrows, 
		#    $metric, // name of data
		#    $marker, 
		#    $marker_size, 
		#    $fill_color array
		#    $border_color array
		#    $yLabel, // y-axis label, like metric
		#    $topleftcell, 
		#    $bottomrightcell, 
		#    $trendLine['width','color','type','order','period','dispRSqr','dispEq'], 
		#    date range info
		#    workbook
		#    ) 

			// use this one for each single dataseries values
			$trendLines = [ 
				new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false),
			] ;
			// $fill = setClrProps ('tx1', null, 'schemeClr'); // black

			# DeclScr 
			$fill   = setClrProps ('0070C0', null, 'srgbClr'); // blue
			$border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($GameScrCol, $gm_cnt, 'Declarer Score', 'diamond', 7, $fill, $border, 'Your Declarer Score', 'A1', 'S24', $trendLine, $dateMinMax, $Wrkbk); 
		
			$Chart_sheet->addChart($chrt);

			# PardScr
			$fill   = setClrProps ('FF0000', null, 'srgbClr'); // red
			$border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($PardScrCol, $gm_cnt, 'Pard Score', 'square', 5, $fill, $border, 'Partners Declarer Score', 'A25', 'S48', $trendLine, $dateMinMax, $Wrkbk);
			$Chart_sheet->addChart($chrt);
		
			# DefScr 
			$fill   = setClrProps ('00B050', null, 'srgbClr'); // green
			$border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($DefScrCol, $gm_cnt, 'Defense Score', 'triangle', 7, $fill, $border, 'Score when Defending', 'A49', 'S72', $trendLine, $dateMinMax, $Wrkbk); 
			$Chart_sheet->addChart($chrt);
		
			# GameScr 
			$fill   = setClrProps ('000000', null, 'srgbClr'); // black
			$border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($GameScrCol, $gm_cnt, 'Game Score', 'circle', 5, $fill, $border, 'Game Score', 'A73', 'S96', $trendLine, $dateMinMax, $Wrkbk);
			$Chart_sheet->addChart($chrt);

			# %Declring
			$fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$border = setClrProps ('7030A0', null, 'srgbClr'); // purple
			$trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($PerCentDeclrngCol, $gm_cnt, 'How often we Declare', 'x', 5, $fill, $border, '%Declaring', 'A97', 'S120', $trendLine, $dateMinMax, $Wrkbk);
			$Chart_sheet->addChart($chrt);


		// --------------- Correlation Chart for Game Score% vs. each of the previous 4 score paramaters -----------------------------------

		// Set the X-Axis Labels  - DeclScr, PardScr, DefScr, %Declaring
			$dataSeriesLabels = 
			[
				 new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$'. $DeclScrCol . '$1', null, 1), // DeclScr
				 new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$'. $PardScrCol . '$1', null, 1), // PardScr
				 new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$'. $DefScrCol . '$1', null, 1), // DefScr
				 new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$'. $PerCentDeclrngCol . '$1', null, 1), // %Declring
			];

			$xAxisTickValues = 
			[
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $DeclScrCol . '$2:$'. $DeclScrCol . '$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $PardScrCol . '$2:$'. $PardScrCol . '$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $DefScrCol . '$2:$'. $DefScrCol . '$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $PerCentDeclrngCol . '$2:$'. $PerCentDeclrngCol . '$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt),
			];

		// Set the same Y-Axis for each of the above
			$dataSeriesValues = 
			[
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $GameScrCol . '$2:$'. $GameScrCol .'$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'diamond', null, 7),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $GameScrCol . '$2:$'. $GameScrCol .'$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'square', null, 5),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $GameScrCol . '$2:$'. $GameScrCol .'$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'triangle', null, 7),
				new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $GameScrCol . '$2:$'. $GameScrCol .'$'.($gm_cnt+1), 
				 Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'x', null, 5),
			];
			$DeclrtrendLines = [ 
				new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, false, true),
			] ;
			$PardtrendLines = [ 
				new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, false, true),
			] ;
			$DeftrendLines = [ 
				new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, false, true),
			] ;
			$PerCentDeclngtrendLines = [ 
				new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, false, true),
			] ;
		
			$cell = $DeclScrCol .'1'; // cell containing 'DeclScr'
			$DeclrTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
			$dataSeriesValues[0]->setTrendLines($DeclrtrendLines);
		
			$cell = $PardScrCol .'1'; // cell containing 'PardScr'
			$PardTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
			$dataSeriesValues[1]->setTrendLines($PardtrendLines);
		
			$cell = $DefScrCol  .'1'; // cell containing 'DefScr'
			$DefTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
			$dataSeriesValues[2]->setTrendLines($DeftrendLines);
		
			$cell = $PerCentDeclrngCol .'1'; // cell containing '% Declaring'
			$PerCentTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
			$dataSeriesValues[3]->setTrendLines($PerCentDeclngtrendLines);

			// Declr Score - blue 0070C0
			$dataSeriesValues[0]->getMarkerFillColor()->setColorProperties('0070C0', null, 'srgbClr');
			$dataSeriesValues[0]->getMarkerBorderColor()->setColorProperties('FFFFFF', null, 'srgbClr');
			$dataSeriesValues[0]
					->setScatterlines(false) // disable connecting lines
					->setLineWidth(0.5)
					->getTrendLines(0)
					->setTrendLineProperties('linear', null, null, true, false, $DeclrTrendLineLabel) 
					->setLineStyleProperties (1.0, null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
			$dataSeriesValues[0]->getTrendLines(0)->getLineColor()->setColorProperties ('0070C0', null, 'srgbClr');

			// Pard Score - red FF0000
			$dataSeriesValues[1]->getMarkerFillColor()->setColorProperties('FF0000', null, 'srgbClr');
			$dataSeriesValues[1]->getMarkerBorderColor()->setColorProperties('FFFFFF', null, 'srgbClr');
			$dataSeriesValues[1]
					->setScatterlines(false)
					->setLineWidth(0.5)
					->getTrendLines(0)
					->setTrendLineProperties('linear', null, null, true, false, $PardTrendLineLabel) 
					->setLineStyleProperties (1.0, null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
			$dataSeriesValues[1]->getTrendLines(0)->getLineColor()->setColorProperties ('FF0000', null, 'srgbClr');

		// Def Score - green 00B050
			$dataSeriesValues[2]->getMarkerFillColor()->setColorProperties('00B050', null, 'srgbClr');
			$dataSeriesValues[2]->getMarkerBorderColor()->setColorProperties('FFFFFF', null, 'srgbClr');
			$dataSeriesValues[2]
					->setScatterlines(false)
					->setLineWidth(0.5)
					->getTrendLines(0)
					->setTrendLineProperties('linear', null, null, true, false, $DefTrendLineLabel) 
					->setLineStyleProperties (1.50, null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
			$dataSeriesValues[2]->getTrendLines(0)->getLineColor()->setColorProperties ('00B050', null, 'srgbClr');

		// %Declaring - purple 7030A0
			$dataSeriesValues[3]->getMarkerFillColor()->setColorProperties('FFFFFF', null, 'srgbClr');
			$dataSeriesValues[3]->getMarkerBorderColor()->setColorProperties('7030A0', null, 'srgbClr');
			$dataSeriesValues[3]
					->setScatterlines(false)
					->setLineWidth(0.5)
					->getTrendLines(0)
					->setTrendLineProperties('linear', null, null, true, false, $PerCentTrendLineLabel) 
					->setLineStyleProperties (1.0, null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
			$dataSeriesValues[3]->getTrendLines(0)->getLineColor()->setColorProperties ('7030A0', null, 'srgbClr');

		// Build the dataseries
			$series = new DataSeries (
				DataSeries::TYPE_SCATTERCHART, // plotType
				null, // plotGrouping (Scatter charts don't have any grouping)
				range(0, count($dataSeriesValues) - 1), // plotOrder
				$dataSeriesLabels, // plotLabel
				$xAxisTickValues, // plotCategory
				$dataSeriesValues, // plotValues
				null, // plotDirection
				false, // smooth line
				DataSeries::STYLE_SMOOTHMARKER  // plotStyle
			);

		// Set the series in the plot area
			$plotArea = new PlotArea(null, [$series]);

			$pos1 = 0; // pos = 0% (extreme low side or lower left corner)
			$brightness1 = 0; // 0%
			$gsColor1  = new ChartColor;
			$gsColor1->setColorProperties('FF0000', 75, 'srgbClr', $brightness1); // red
			$gradientStop1 = array('pos' => $pos1, 'gsColor' => $gsColor1);

			$pos2 = 0.5; // pos = 50% (middle)
			$brightness2 = 0.5; // 50%
			$gsColor2  = new ChartColor;
			$gsColor2->setColorProperties('FFFF00', 50, 'srgbClr', $brightness2); // yellow
			$gradientStop2 = array('pos' => $pos2, 'gsColor' => $gsColor2);

			$pos3 = 1.0; // pos = 100% (extreme high side or upper right corner)
			$brightness3 = 0.5; // 50%
			$gsColor3  = new ChartColor;
			$gsColor3->setColorProperties('00B050', 50, 'srgbClr', $brightness3); // green
			$gradientStop3 = array('pos' => $pos3, 'gsColor' => $gsColor3);

			$gsLst = 
			[    $gradientStop1, 
					 $gradientStop2,
					 $gradientStop3
			];
			$gradientAngle = "315"; // appears to be 45deg ccw above horiz

			$plotArea->setGradientFillProperties($gsLst, $gradientAngle);

		// Set the chart legend
			$legend = new Legend(Legend::POSITION_RIGHT, null, 0);

			$title = new Title (
		'Correlation between Game score% and each of the other factors 
	(or, which metric tracks Game score?)'
			);

			$xAxisLabel = new Title('Declaring / Pard Decl / Def / %Declaring Score');
			$xAxis = new Axis();
			$xAxis->setAxisType ('valAx'); // valAx = same as yAxis ? I hope
			$xAxis->setAxisNumberProperties('0');
			$xAxis->setAxisOptionsProperties (
					Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
					"0", // horizontalCrossesValue
					null, // horizontalCrosses
					null, // axisOrientation
					null, // major_tick_mark
					null, // minor_tick_mark
					"0",   // minimum
					"100",  // maximum
					"20", // majorUnit
					null  // minorUnit
			);
			$xAxis->setCrossBetween('midCat');

			$yAxisLabel = new Title('Game Score %');
			$yAxis = new Axis();
			$yAxis->setAxisNumberProperties ('0');
			$yAxis->setAxisOptionsProperties (
					Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
					"0", // horizontalCrossesValue
					null, // horizontalCrosses
					null, // axisOrientation
					null, // major_tick_mark
					null, // minor_tick_mark
					"0",    // minimum
					"100",  // maximum
					"20", // majorUnit
					null  // minorUnit
			);
			$yAxis->setCrossBetween('midCat');
			// $majorGridlines = true;
			// $minorGridlines = true;

			$majorGridlines = new GridLines() ;
			// $majorGridlines->activateObject() ; // Nope. I can't activate it.  I must set some 
			// property of the Gridline, which will activate it for me.
			$majorGridlines->setLineStyleProperties(0.5) ; // linewidth, fer instance, should do the trick

			// $minorGridlines = new Gridlines() ; 

		// Create the chart
			$chart = new Chart (
				"Correlation Chart", // name
				$title, // title
				$legend, // legend
				$plotArea, // plotArea
				true, // plotVisibleOnly
				DataSeries::EMPTY_AS_GAP, // displayBlanksAs
				$xAxisLabel, // xAxisLabel
				$yAxisLabel, // yAxisLabel
				$xAxis, // xAxis
				$yAxis, // yAxis
				$majorGridlines, // majorGridlines
				// $minorGridlines, // minorGridlines
			);

		// Set the position where the chart should appear in the worksheet
			$chart->setTopLeftPosition('A121');
			$chart->setBottomRightPosition('S144');
			$chart->setRoundedCorners('true');

			$Chart_sheet->addChart($chart);

			# My aveHCP trend chart - different y-axis from above 4 charts!!!
			$fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$border = setClrProps ('7030A0', null, 'srgbClr'); // purple
			$trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
			$chrt =  create_Trend_chart
				($AveHCPCol, $gm_cnt, 'Session aveHCP', 'plus', 5, $fill, $border, 'Average HCP in a session', 'A145', 'S168', $trendLine, $dateMinMax, $Wrkbk);
			$Chart_sheet->addChart($chrt);

			# My aveHCP correlation chart - different y-axis from percentile charts!!!
			$fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
			$border = setClrProps ('7030A0', null, 'srgbClr'); // purple
			$trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
			$chrt =  create_HCP_correlation_chart 
				($AveHCPCol, $GameScrCol, $gm_cnt, 'Game Score %', 'plus', 5, $fill, $border, 'Game Score %', 'A169', 'S192', $trendLine, null, $Wrkbk);
			$Chart_sheet->addChart($chrt);


	 
		// Save Excel 2007 file
			$filename = $pnum . '.xlsx';
			$writer = IOFactory::createWriter($Wrkbk, 'Xlsx');
			$writer->setIncludeCharts(true);
			// $callStartTime = microtime(true);
			$writer->save($filename);

			unset($writer);
			unset($reader);
			unset($Wrkbk);

		//---------------------------- end Chart creation

		}
	return $filename;
}

##################################################################################
// 
//
// Acquire all details from pair games to be inserted into the Excel spreadsheet.
//
//
##################################################################################
function get_spreadsheet_data ($conn, $pnum, $gmqualifier) { 

	$SS_sql =
"
WITH

me AS (
    SELECT $pnum AS me
),

my_game_ids AS (
    SELECT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),

/* ============================================================
   STEP 1 — Pull ALL pairs from those games
   ============================================================ */
games AS (
    SELECT
        e.GameId,
        e.gametype,
        e.scoring,
        e.movement,
        e.rating,
        p.player_pair_team,
        p.player1, p.player2, p.player3, p.player4, p.player5, p.player6,  -- needed for team games!
        p.pnum1,   p.pnum2,   p.pnum3,   p.pnum4,   p.pnum5,   p.pnum6,  -- needed for team games!
        p.club,
        COALESCE(NULLIF(p.Flt, ''), 'A') AS strat, -- deal with Flt=NULL
        p.Score, p.award1, p.color1, p.award2, p.color2, p.reason,
        p.Pct
    FROM events e
    JOIN players p
      ON 	p.GameId = e.GameId
		AND p.club   = e.club
    JOIN my_game_ids mg
      ON 	mg.GameId = e.GameId
		AND mg.club   = e.club
),


/* ============================================================
   STEP 2 — Add direction (NS/EW) for Mitchell/WEB movements
   ============================================================ */
games_with_dir AS (
    SELECT
        g.*,
        CASE
            WHEN g.movement IN ('MITCHELL', 'WEB') THEN
                CASE
                    WHEN RIGHT(g.player_pair_team,1)='N' THEN 'NS'
                    WHEN RIGHT(g.player_pair_team,1)='E' THEN 'EW'
                END
            ELSE NULL
        END AS dir
    FROM games g
),

/* ============================================================
   STEP 3 — Raw ranks (no tie logic yet)
   ============================================================ */
ranked AS (
    SELECT
        ri.*,

        /* ============================================================
           SECTION RANKS
           ============================================================ */

        /* ---------- Section A (all strats) ---------- */
		CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
			RANK() OVER ( PARTITION BY ri.GameId, ri.dir 
						  ORDER BY ri.Score DESC )
			ELSE
			RANK() OVER ( PARTITION BY ri.GameId 
						  ORDER BY ri.Score DESC )
		END AS sec_rank_A,

        /* ---------- Section B (B+C only) ---------- */
        CASE WHEN ri.strat IN ('B','C') THEN
            CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
					ORDER BY ri.Score DESC
				)
            END
        END AS sec_rank_B,

        /* ---------- Section C (C only) ---------- */
        CASE WHEN ri.strat = 'C' THEN
            CASE WHEN ri.movement IN ('MITCHELL', 'WEB') THEN
				RANK() OVER (
					PARTITION BY ri.GameId, ri.dir, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
			ELSE
				RANK() OVER (
					PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
					ORDER BY ri.Score DESC
				)
            END
        END AS sec_rank_C,

        /* ============================================================
           OVERALL RANKS
           ============================================================ */

        /* ---------- Overall A (all pairs) ---------- */
        RANK() OVER ( PARTITION BY ri.GameId
					  ORDER BY ri.Score DESC ) AS ov_rank_A,

        /* ---------- Overall B (B+C only) ---------- */
        CASE WHEN ri.strat IN ('B','C') THEN
            RANK() OVER (
                PARTITION BY ri.GameId, CASE WHEN ri.strat IN ('B','C') THEN 'B' END
                ORDER BY ri.Score DESC
            )
        END AS ov_rank_B,

        /* ---------- Overall C (C only) ---------- */
        CASE WHEN ri.strat = 'C' THEN
            RANK() OVER (
                PARTITION BY ri.GameId, CASE WHEN ri.strat = 'C' THEN 'C' END
                ORDER BY ri.Score DESC
            )
        END AS ov_rank_C

    FROM games_with_dir ri -- bypassing 'ranked_input'
),

/* ============================================================
   STEP 4 — Populations + tie counts (ONE pass)
   ============================================================ */
tie_groups AS (
    SELECT
        r.*,

        /* ---------- Section populations ---------- */
        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER (PARTITION BY r.GameId, r.dir)
            ELSE
                COUNT(*) OVER (PARTITION BY r.GameId)
        END AS pop_sec_A,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_sec_B,

        CASE
            WHEN r.movement IN ('MITCHELL', 'WEB') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, r.dir, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
            ELSE
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_sec_C,

        /* ---------- Overall populations (all pairs, no dir) ---------- */
        COUNT(*) OVER (PARTITION BY r.GameId) AS pop_ov_A,

        CASE
            WHEN r.strat IN ('B','C') THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat IN ('B','C') THEN 'BC' ELSE 'X' END))
        END AS pop_ov_B,

        CASE
            WHEN r.strat = 'C' THEN
                COUNT(*) OVER ( PARTITION BY r.GameId, (CASE WHEN r.strat = 'C' THEN 'C' ELSE 'X' END))
        END AS pop_ov_C,

        /* ---------- Tie counts for section ranks ---------- */
        CASE WHEN r.sec_rank_A IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_A)
        END AS tie_sec_A,

        CASE WHEN r.sec_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_B)
        END AS tie_sec_B,

        CASE WHEN r.sec_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, CASE WHEN r.movement IN ('MITCHELL', 'WEB') THEN r.dir END,
							r.sec_rank_C)
        END AS tie_sec_C,

        /* ---------- Tie counts for overall ranks ---------- */
        COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_A
        ) AS tie_ov_A,

        CASE WHEN r.ov_rank_B IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_B
            )
        END AS tie_ov_B,

        CASE WHEN r.ov_rank_C IS NOT NULL THEN
            COUNT(*) OVER ( PARTITION BY r.GameId, r.ov_rank_C
            )
        END AS tie_ov_C

    FROM ranked r
),

min_max AS (
    SELECT
        tg.GameId,
        tg.club,
        tg.player_pair_team,
        tg.strat,
        tg.dir,

        -- pair identity
        tg.pnum1,   tg.pnum2,   tg.pnum3,   tg.pnum4,   tg.pnum5,   tg.pnum6, 
        tg.player1, tg.player2, tg.player3, tg.player4, tg.player5, tg.player6,

        -- game metadata
        tg.gametype,
        tg.scoring,
        tg.movement,
        tg.rating,
        tg.Pct, tg.award1, tg.color1, tg.award2, tg.color2, tg.reason,

        -- populations (NULL where ineligible)
        -- CASE WHEN tg.strat = 'A' THEN tg.pop_sec_A END AS pop_sec_A,
        tg.pop_sec_A AS pop_sec_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_sec_B END AS pop_sec_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_sec_C END AS pop_sec_C,

        -- CASE WHEN tg.strat = 'A' THEN tg.pop_ov_A END AS pop_ov_A,
        tg.pop_ov_A AS pop_ov_A,
        CASE WHEN tg.strat IN ('B','C') THEN tg.pop_ov_B END AS pop_ov_B,
        CASE WHEN tg.strat IN ('C') THEN tg.pop_ov_C END AS pop_ov_C,

        -- fractional section ranks
        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0
        END AS frac_sec_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0
        END AS frac_sec_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0
        END AS frac_sec_C,

        -- fractional overall ranks
        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0
        END AS frac_ov_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0
        END AS frac_ov_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0
        END AS frac_ov_C,

        -- reverse-place section ratings
        CASE WHEN tg.sec_rank_A IS NOT NULL THEN
            tg.pop_sec_A
              - ( (tg.sec_rank_A + (tg.sec_rank_A + tg.tie_sec_A - 1)) / 2.0 )
              + 1
        END AS sec_rating_A,

        CASE WHEN tg.sec_rank_B IS NOT NULL THEN
            tg.pop_sec_B
              - ( (tg.sec_rank_B + (tg.sec_rank_B + tg.tie_sec_B - 1)) / 2.0 )
              + 1
        END AS sec_rating_B,

        CASE WHEN tg.sec_rank_C IS NOT NULL THEN
            tg.pop_sec_C
              - ( (tg.sec_rank_C + (tg.sec_rank_C + tg.tie_sec_C - 1)) / 2.0 )
              + 1
        END AS sec_rating_C,

        -- reverse-place overall ratings
        CASE WHEN tg.ov_rank_A IS NOT NULL THEN
            tg.pop_ov_A
              - ( (tg.ov_rank_A + (tg.ov_rank_A + tg.tie_ov_A - 1)) / 2.0 )
              + 1
        END AS ov_rating_A,

        CASE WHEN tg.ov_rank_B IS NOT NULL THEN
            tg.pop_ov_B
              - ( (tg.ov_rank_B + (tg.ov_rank_B + tg.tie_ov_B - 1)) / 2.0 )
              + 1
        END AS ov_rating_B,

        CASE WHEN tg.ov_rank_C IS NOT NULL THEN
            tg.pop_ov_C
              - ( (tg.ov_rank_C + (tg.ov_rank_C + tg.tie_ov_C - 1)) / 2.0 )
              + 1
        END AS ov_rating_C

    FROM tie_groups tg
    WHERE tg.ov_rank_A IS NOT NULL   -- canonical row: one per pair per game
),

/*  
			BEGIN the PAIRS branch of the bridge pipeline.
*/
pair_games AS (
    SELECT
        mm.*,
        CASE 
            WHEN me.me = mm.pnum1 THEN mm.pnum2
            ELSE mm.pnum1
        END AS pardnum,

        -- Partner name
        CASE 
            WHEN me.me = mm.pnum1 THEN mm.player2
            ELSE mm.player1
        END AS pardName

    FROM min_max mm
    CROSS JOIN me
    WHERE me.me IN (mm.pnum1, mm.pnum2, mm.pnum3, mm.pnum4, mm.pnum5, mm.pnum6)
      -- AND NULLIF(mm.pnum3, '') IS NULL
      -- AND mm.movement NOT LIKE 'Swiss%'
),

partner_counts AS (
    SELECT
        pardnum,
        COUNT(DISTINCT GameId) AS games_together
    FROM pair_games
    GROUP BY pardnum
),

results_enriched AS (
    SELECT
        pg.GameId,
        pg.club,
        pg.gametype,
        pg.rating,
        pg.Pct, pg.award1, pg.color1, pg.award2, pg.color2, pg.reason,
        pg.strat,
        pg.movement,
        pg.scoring,

        r.board_id,
        r.ETF,
        r.OLE,
        r.contractLevel,
        r.Declarer,
        r.top,
		r.nsmtchpts,
		r.ewmtchpts,

        -- My seat
        CASE
            WHEN r.npnum = me.me THEN 'N'
            WHEN r.spnum = me.me THEN 'S'
            WHEN r.epnum = me.me THEN 'E'
            WHEN r.wpnum = me.me THEN 'W'
        END AS my_seat,

        -- Partner seat
        CASE
            WHEN r.npnum = me.me THEN 'S'
            WHEN r.spnum = me.me THEN 'N'
            WHEN r.epnum = me.me THEN 'W'
            WHEN r.wpnum = me.me THEN 'E'
        END AS pard_seat,

        -- Role
        CASE
            WHEN r.contractLevel = 'PASS' THEN 'passout'
            WHEN r.Declarer =
                CASE
                    WHEN r.npnum = me.me THEN 'N'
                    WHEN r.spnum = me.me THEN 'S'
                    WHEN r.epnum = me.me THEN 'E'
                    WHEN r.wpnum = me.me THEN 'W'
                END
                THEN 'me_declr'
            WHEN r.Declarer =
                CASE
                    WHEN r.npnum = me.me THEN 'S'
                    WHEN r.spnum = me.me THEN 'N'
                    WHEN r.epnum = me.me THEN 'W'
                    WHEN r.wpnum = me.me THEN 'E'
                END
                THEN 'pard_declr'
		-- OLE additions
			-- me leading
			WHEN r.Declarer =
				CASE
					WHEN r.wpnum = me.me THEN 'S'
					WHEN r.epnum = me.me THEN 'N'
					WHEN r.npnum = me.me THEN 'W'
					WHEN r.spnum = me.me THEN 'E'
				END
				THEN 'me_leading'

			-- pard leading
			WHEN r.Declarer =
				CASE
					WHEN r.epnum = me.me THEN 'S'
					WHEN r.wpnum = me.me THEN 'N'
					WHEN r.spnum = me.me THEN 'W'
					WHEN r.npnum = me.me THEN 'E'
				END
				THEN 'pard_leading'
-- No ELSE needed: all non-PASS cases are covered by the 4 WHEN blocks
        END AS role

    FROM results r
    CROSS JOIN me
    JOIN pair_games pg
        ON  pg.GameId = r.GameId
        AND pg.club   = r.club
        AND me.me IN (r.npnum, r.spnum, r.epnum, r.wpnum)

    LEFT JOIN events e
        ON e.GameId = pg.GameId
       AND e.club   = pg.club
),

games_with_pair_results AS (
    SELECT
        pg.GameId,
        pg.club,
        pg.pardnum,
        pg.pardName,
        pg.gametype,
        pg.rating,
        pg.Pct, pg.award1, pg.color1, pg.award2, pg.color2, pg.reason,
        pg.strat,
        pg.movement,
        pg.scoring,
        re.board_id,
        re.my_seat,
		re.pard_seat,
        re.role,
        re.ETF,
        re.OLE,
        re.top,
        
	SUM(
		CASE 
			WHEN re.role = 'me_declr' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS my_declr_mtchpts,
	
    SUM(
		CASE 
			WHEN re.role = 'pard_declr' THEN
				CASE WHEN re.pard_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS pard_declr_mtchpts,

	SUM(
		CASE 
			-- role 'dfndng' replaced by: 'pard_leading' & 'me_leading'
			WHEN re.role = 'pard_leading' OR re.role = 'me_leading' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS def_mtchpts,

	SUM(
		CASE 
			WHEN re.role = 'passout' THEN
				CASE WHEN re.my_seat IN ('N','S') THEN re.nsmtchpts ELSE re.ewmtchpts END
			ELSE 0
		END
	) AS PO_mtchpts 
    
    FROM pair_games pg
    LEFT JOIN results_enriched re
        ON 	re.GameId = pg.GameId 
		AND re.club   = pg.club


    
    GROUP BY
        pg.GameId, pg.club, pg.pardnum, -- pg.pardName,
        pg.gametype, pg.Pct, pg.strat, pg.movement,
        re.board_id, re.my_seat, re.pard_seat, re.role, re.ETF, re.top

),

final AS (

    -- A. Board-level-data pair games
    SELECT
        g.GameId,
        g.club,
        g.pardnum,
        g.pardName,
        g.gametype,
        g.rating,
        g.movement,
        g.scoring,
        g.strat,
        g.Pct, g.award1, g.color1, g.award2, g.color2, g.reason,
        g.top,
        SUM(g.my_declr_mtchpts)   AS my_mtchpts,
		SUM(g.pard_declr_mtchpts) AS pard_mtchpts,
		SUM(g.def_mtchpts)        AS dfndng_mtchpts,
		SUM(g.PO_mtchpts)         AS pass_mtchpts,

        SUM(g.role = 'me_declr')   AS hands_I_declared,
        SUM(g.role = 'pard_declr') AS hands_pard_declared,
	 -- SUM(g.role = 'dfndng')     AS hands_defended,
		SUM(g.role = 'me_leading' or g.role = 'pard_leading') AS hands_defended,
        SUM(g.role = 'passout')    AS passout_count,

		SUM(g.role = 'me_leading')   AS hands_me_leading,
        SUM(g.role = 'pard_leading') AS hands_pard_leading,
        SUM(g.role = 'me_declr' or g.role = 'pard_declr') AS hands_declaring,
        
        SUM(CASE WHEN g.role = 'me_declr'   THEN g.ETF END) AS ETF_me,
        SUM(CASE WHEN g.role = 'pard_declr' THEN g.ETF END) AS ETF_pard,
				-- ETF<0 on defense means you gave away undeserved overtricks!
        -- SUM(CASE WHEN g.role = 'dfndng'     THEN 0 - g.ETF END) AS ETF_defense,
        SUM(CASE WHEN (g.role = 'pard_leading' OR g.role = 'me_leading') THEN 0 - g.ETF END) 
			AS ETF_defense,

		-- NEW: OLE buckets
		SUM(CASE WHEN g.role = 'me_leading'   THEN g.OLE END) AS OLE_me,
		SUM(CASE WHEN g.role = 'pard_leading' THEN g.OLE END) AS OLE_pard,
        -- SUM(CASE WHEN g.role = 'opp_leading'  THEN g.OLE END) AS OLE_opp,
        SUM(CASE WHEN g.role = 'me_declr' or g.role = 'pard_declr' THEN 0 - g.OLE END) 
			AS OLE_opp,

        mm.frac_ov_A,  -- AS ovkA,
        mm.frac_ov_B,  -- AS ovkB,
        mm.frac_ov_C,  -- AS ovkC,

        mm.frac_sec_A, -- AS sckA,
        mm.frac_sec_B, -- AS sckB,
        mm.frac_sec_C, -- AS sckC,

        mm.ov_rating_A,  -- AS ovrtA,
        mm.pop_ov_A,     -- AS ovrtA_pop,

        mm.ov_rating_B,  -- AS ovrtB,
        mm.pop_ov_B,     -- AS ovrtB_pop,

        mm.ov_rating_C,  -- AS ovrtC,
        mm.pop_ov_C,     -- AS ovrtC_pop,

        mm.sec_rating_A, -- AS scrtA,
        mm.pop_sec_A,    -- AS scrtA_pop,

        mm.sec_rating_B, -- AS scrtB,
        mm.pop_sec_B,    -- AS scrtB_pop,

        mm.sec_rating_C, -- AS scrtC,
        mm.pop_sec_C,    -- AS scrtC_pop,

        COUNT(*) AS boards_played
    
    FROM games_with_pair_results g
    CROSS JOIN me
    LEFT JOIN min_max mm
      ON mm.GameId = g.GameId
     AND mm.club   = g.club
     AND me.me  IN (mm.pnum1, mm.pnum2)
     AND g.pardnum IN (mm.pnum1, mm.pnum2)


	GROUP BY
        g.GameId,
        g.club,
        g.pardnum,
        -- g.pardName,
        g.gametype,
        g.movement,
        g.strat,
        g.Pct,
        g.top,
        mm.frac_ov_A,    mm.frac_ov_B,    mm.frac_ov_C,
        mm.frac_sec_A,   mm.frac_sec_B,   mm.frac_sec_C,
        mm.ov_rating_A,  mm.ov_rating_B,  mm.ov_rating_C,
        mm.pop_ov_A,     mm.pop_ov_B,     mm.pop_ov_C,
        mm.sec_rating_A, mm.sec_rating_B, mm.sec_rating_C,
        mm.pop_sec_A,    mm.pop_sec_B,    mm.pop_sec_C

),

pair_summary AS (
SELECT
    f.GameId,
	-- COUNT(DISTINCT GameId) AS games_together,
    f.club,
    f.pardnum,
    f.pardName,
		pc.games_together,
    f.gametype,
    f.rating,
    f.movement,
    f.scoring,

    -- Fix blank or NULL strat.
    COALESCE(NULLIF(f.strat, ''), 'A') AS strat,

    f.Pct, f.award1, f.color1, f.award2, f.color2, f.reason,
    f.boards_played,

    -- Board-level counts
    f.hands_I_declared,
    f.hands_pard_declared,
    f.hands_defended,
    f.passout_count,
    f.hands_me_leading,
    f.hands_pard_leading,
    f.hands_declaring,
	
    f.my_mtchpts,
    f.pard_mtchpts,
    f.dfndng_mtchpts,
    f.pass_mtchpts,
   
    -- Percentages (pairs only)
    ROUND(100 * f.hands_I_declared    / NULLIF(f.boards_played, 0), 1) AS my_dclr_frq,

    ROUND(100 * f.hands_pard_declared / NULLIF(f.boards_played, 0), 1) AS pard_dclr_frq,

    ROUND(100 * f.hands_defended      / NULLIF(f.boards_played, 0), 1) AS def_frq,

    ROUND(100 * f.passout_count       / NULLIF(f.boards_played, 0), 1) AS PO_frq,
    
	ROUND(100 * f.my_mtchpts     / NULLIF(f.hands_I_declared * f.top, 0), 1) AS my_dclr_scr,

	ROUND(100 * f.pard_mtchpts   / NULLIF(f.hands_pard_declared * f.top, 0), 1) AS pard_dclr_scr,

	ROUND(100 * f.dfndng_mtchpts / NULLIF(f.hands_defended * f.top, 0), 1) AS def_scr,

	ROUND(100 * f.pass_mtchpts   / NULLIF(f.passout_count * f.top, 0), 1) AS PO_scr,

    -- ETFs
    f.ETF_me,
    f.ETF_pard,
    f.ETF_defense,
    f.OLE_me,
	f.OLE_pard,
	f.OLE_opp,

    -- Overall rankings
	f.frac_ov_A,    f.frac_ov_B,    f.frac_ov_C,        

    -- Section rankings
	f.frac_sec_A,   f.frac_sec_B,   f.frac_sec_C,
    
    -- Overall ratings
	f.ov_rating_A,  f.pop_ov_A,
    f.ov_rating_B,  f.pop_ov_B,
    f.ov_rating_C,  f.pop_ov_C,

    -- Section ratings
	f.sec_rating_A, f.pop_sec_A, 
    f.sec_rating_B, f.pop_sec_B,
    f.sec_rating_C, f.pop_sec_C

FROM final f
JOIN partner_counts pc ON pc.pardnum = f.pardnum

ORDER BY f.GameId,
    f.pardnum
)

/*        Pair Games Report final SQL      */
SELECT 
    GameId,
    club,
    pardnum,
    pardName,
    games_together,
    gametype,
    rating,
    movement,
    CASE scoring
		WHEN 'VICTORY POINT' THEN 'VP'
        WHEN 'WIN-LOSS' 	 THEN 'W-L'
        WHEN 'MATCHPOINTS'   THEN 'Mtchpts'
    END AS ScrMethod,
	strat,
    Pct, award1, color1, award2, color2, reason,
    boards_played,

    -- Board-level counts
    hands_I_declared,
    hands_pard_declared,
    hands_defended,
    passout_count,
	
    my_dclr_frq,
    pard_dclr_frq,
    def_frq,
    PO_frq,
    
    -- NEW: Declarer/defense/passout scoring metrics
    my_dclr_scr,
    pard_dclr_scr,
    def_scr,
    PO_scr,

    -- ETFs
    ETF_me,
    ETF_pard,
    ETF_defense,
    OLE_me,
	OLE_pard,
	OLE_opp,

    -- Cleaned Overall rankings
	clean_num(frac_ov_A) AS ovRnkA,    
    clean_num(frac_ov_B) AS ovRnkB,    
    clean_num(frac_ov_C) AS ovRnkC,        

    -- Cleaned Section rankings
	clean_num(frac_sec_A) AS secRnkA,   
    clean_num(frac_sec_B) AS secRnkB,   
    clean_num(frac_sec_C) AS secRnkC,
    
    -- Cleaned Overall ratings
	clean_num(ov_rating_A) AS ovRtgA,  pop_ov_A,
    clean_num(ov_rating_B) AS ovRtgB,  pop_ov_B,
    clean_num(ov_rating_C) AS ovRtgC,  pop_ov_C,

    -- Cleaned Section ratings
	clean_num(sec_rating_A) AS secRtgA, pop_sec_A, 
    clean_num(sec_rating_B) AS secRtgB, pop_sec_B,
    clean_num(sec_rating_C) AS secRtgC, pop_sec_C
    
FROM pair_summary
ORDER BY games_together DESC, pardnum, GameId ;
";

  // Check connection
  if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
  }

	// each row is event info + player info + results from a $game at a $club.
	$SS_results = mysqli_query($conn, $SS_sql); 
	if (!$SS_results) {
    error_log("SQL ERROR: " . mysqli_error($conn));
    error_log("SQL QUERY: " . $SS_sql);
    return [];  // prevent further errors
	}

  $rows = [];
  while ($row = mysqli_fetch_assoc($SS_results)) {
    $rows[] = $row;
  }

	return $rows;

}

##################################################################################
// 
//
// create the HCP Correlation chart
//
//
##################################################################################
function create_HCP_correlation_chart 
	( $column, $GameScrCol, $nrows, $metric, $marker, $marker_size, $fill, $border, 
		$yLabel, $topleftcell, $bottomrightcell, $trendLine, $dateMinMax, $Wrkbk) 
{

 	$TrendLineLabel = 'Correlation between My avg HCP and Game Score %' ;
  $Datasheet = $Wrkbk->getSheetByName('Data');
  // use this one for all dataseries values
  $trendLines = [ 
    new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false),
  ] ;

// Set the Labels (Column header) for each data series we want to plot
  $cell = $column.'1'; // cell containing data label - aveHCP
  $TrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";

// Set the X-Axis Label  -  "My avg HCP"
  $dataSeriesLabels = 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $column .'$1', null, 1), // MyaveHCP
  ];

// Set the X-coordinate = each session's aveHCP (col AN)
  $xAxisTickValues = 
	[ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $column .'$2:$'. $column .'$'.($nrows), 
		 Properties::FORMAT_CODE_NUMBER, $nrows), 
  ];

// Set the Y-coordinate = each session's GameScr (col M)
  $dataSeriesValues = 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'. $GameScrCol . '$2:$'. $GameScrCol .'$'.($nrows), 
    Properties::FORMAT_CODE_NUMBER, $nrows, null, $marker, null, $marker_size),
  ];

	$dataSeriesValues[0]->setTrendLines($trendLines);
	
  $dataSeriesValues[0]->getMarkerFillColor()->setColorPropertiesArray($fill);
  $dataSeriesValues[0]->getMarkerBorderColor()->setColorPropertiesArray($border);
  
  // confusing passed-in var: $trendLine = array ($width, $color, $type, $order, $period, $dispRSqr, $dispEq), 
  $dataSeriesValues[0]
      ->setScatterlines(false); // disable connecting lines
      // ->setLineWidth(0.5)
      // ->getLineColor()->setColorPropertiesArray($trendLine[1]); // connecting line same color as trendline

  $dataSeriesValues[0]
      ->getTrendLines(0)
      ->setTrendLineProperties($trendLine[2], $trendLine[3], $trendLine[4], $trendLine[5], $trendLine[6], $TrendLineLabel) // describe trendLine 
      ->setLineStyleProperties($trendLine[0], null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
  $dataSeriesValues[0]->getTrendLines(0)->getLineColor()->setColorPropertiesArray($trendLine[1]); 

// Build the dataseries
  $series = new DataSeries (
      DataSeries::TYPE_SCATTERCHART, // plotType
      null, 
      range(0, count($dataSeriesValues) - 1), // plotOrder
      $dataSeriesLabels, // plotLabel
      $xAxisTickValues, // plotCategory
      $dataSeriesValues, // plotValues
      null, // plotDirection
      false, // smooth line Boolean
      DataSeries::STYLE_SMOOTHMARKER  // plotStyle
  );

// Set the series in the plot area
  $plotArea = new PlotArea(null, [$series]);
//  $plotArea-> fill and gradient properties now supported
  $pos1 = 0; // pos = 0% (extreme low side or lower left corner)
  $brightness1 = 0.0; // 0%
  $gsColor1  = new ChartColor;
  $gsColor1->setColorProperties('FF0000', 75, 'srgbClr', $brightness1); // red
  $gradientStop1 = array('pos' => $pos1, 'gsColor' => $gsColor1);

  $pos2 = 0.5; // pos = 50% (middle)
  $brightness2 = 0.5; // 0%
  $gsColor2  = new ChartColor;
  $gsColor2->setColorProperties('FFFF00', 50, 'srgbClr', $brightness2); // yellow
  $gradientStop2 = array('pos' => $pos2, 'gsColor' => $gsColor2);

  $pos3 = 1.0; // pos = 100% (extreme high side or upper right corner)
  $brightness3 = 0.5; // 50%
  $gsColor3  = new ChartColor;
  $gsColor3->setColorProperties('00B050', 50, 'srgbClr', $brightness3); // green
  $gradientStop3 = array('pos' => $pos3, 'gsColor' => $gsColor3);

  $gsLst = 
  [    $gradientStop1, 
       $gradientStop2,
       $gradientStop3
  ];
  $gradientAngle = "315"; // appears to be 45deg ccw above horiz

  $plotArea->setGradientFillProperties($gsLst, $gradientAngle);

// Set the chart legend
  $legend = new Legend(Legend::POSITION_RIGHT, null, 0);

	$title = new Title("Correlation between My avg HCP and Game Score %") ;

  $xAxisLabel = new Title('avg HCP');
  $xAxis = new Axis();
	$xAxis->setAxisType ('valAx'); // valAx = same as yAxis ? I hope
	$xAxis->setAxisNumberProperties('0');
  
  $xAxis->setAxisOptionsProperties (
      Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
			"0", // horizontalCrossesValue
			null, // horizontalCrosses
			null, // axisOrientation
			null, // major_tick_mark
			null, // minor_tick_mark
      "6",  // minimum
      "14", // maximum
      "2", 	// majorUnit
			null  // minorUnit
  );
  $xAxis->setCrossBetween('midCat');

  $yAxisLabel = new Title($yLabel);
  $yAxis = new Axis();
  $yAxis->setAxisNumberProperties('0'); // decimal places (for percent in this case)
  $yAxis->setAxisOptionsProperties (
      Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
      null, // horizontalCrossesValue
      null, // horizontalCrosses
      null, // axisOrientation
      null, // major_tick_mark
      null, // minor_tick_mark
			"0",  // minimum
			"100",// maximum
			"20", // majorUnit
      null, // minorUnit
  );
  $yAxis->setCrossBetween('midCat');

  $majorGridlines = new GridLines() ;
  // $majorGridlines->activateObject() ; // Nope. I can't activate it.  I must set some 
  // property of the Gridline, which will activate it for me.
  $majorGridlines->setLineStyleProperties(0.5) ; // linewidth, fer instance, should do the trick

// Create the chart
  $chart = new Chart (
    "aveHCP Correlation Chart", // name
    $title, // title
    $legend, // legend
    $plotArea, // plotArea
    true, // plotVisibleOnly
    DataSeries::EMPTY_AS_GAP, // displayBlanksAs
    $xAxisLabel,  // xAxisLabel
    $yAxisLabel,  // yAxisLabel
    $xAxis, // xAxis
    $yAxis, // yAxis
    $majorGridlines, // majorGridlines - how to get into x-axis? modify writer/Xlsx/Chart.php - writeCategoryAxis() by adding Gridlines 
    // $minorGridlines, // minorGridlines
  );

// position the chart in the worksheet
  $chart->setTopLeftPosition($topleftcell);
  $chart->setBottomRightPosition($bottomrightcell);
  $chart->setRoundedCorners('true');

	return ($chart) ;

}

##################################################################################
function playDateRange ($nrows, $Wrkbk, $sql_return_array) {
  $Datasheet = $Wrkbk->getSheetByName('Data');

	// For trend charts,
  // start the xaxis (time) at the beginning of the quarter of the first play date
	//     Can't use the method below which assumes the SS rows are ordered by GameId
	//		 because I changed my mind, and ordered by 'games_together'.
  // $startDateStr = $Datasheet->getCell('B2')->getValue(); // mm/dd/yyyy date string
  // $startDate    = date_create_from_format('m/d/Y', $startDateStr); // php date obj
	//
	// New method for obtaining $startDateStr & $endDateStr is to sort all the GameIds.
	// Build an array of $GameIds, sort it, top is starting GameId, last is ending GameId.
	$gmidArray = [];
	foreach ($sql_return_array as $r) {
		array_push ($gmidArray, $r['GameId']) ;
	}
	sort($gmidArray, SORT_STRING);
	$startingGameId = $gmidArray[0];
	$endingGameId   = end($gmidArray); // NOT array[-1]

	$yy =   substr($startingGameId, 0, 2) ;
	$mm =   substr($startingGameId, 2, 2) ;
	$dd =   substr($startingGameId, 4, 2) ;
	$startDateStr = $mm . "/" . $dd . "/" . "20" . $yy;
	$startDate    = date_create_from_format('m/d/Y', $startDateStr); // php date obj
		
  // get date of first day of the quarter of the start date
  $startMonth   = date_format($startDate ,'n'); // suppress leading zero
  $startYr      = date_format($startDate ,'Y'); 
  $qtr          = intdiv($startMonth, 3) + (($startMonth % 3 > 0) ? 1:0);
  $qtrStartMonth= 1+ (($qtr-1) * 3);
  // start x-axis 1 quarter before the first data point
	// nah.  forget it.
  // if ($qtrStartMonth != 1) {
  //   $qtrStartMonth = $qtrStartMonth-3 ; 
  // } else {
  //   $qtrStartMonth = 10; // if qtrStartMonth was Jan, Oct is next earlier qtr
  //   $startYr--;          // decrement the year, too
  // }
  $qtrStartStr  = "$qtrStartMonth/1/$startYr";
  $ExcelQtrStartDateVal = excelDateValue($qtrStartStr); // derived from pure php code

  // end the xaxis at the end of the quarter of the last play date
  // $lastDateStr   = $Datasheet->getCellByColumnAndRow(2, $nrows+1)->getValue();
  // No longer works: $lastDateStr   = $Datasheet->getCellByColumnAndRow(2, $nrows)->getValue();
	$yy =   substr($endingGameId, 0, 2) ;
	$mm =   substr($endingGameId, 2, 2) ;
	$dd =   substr($endingGameId, 4, 2) ;
	$lastDateStr = $mm . "/" . $dd . "/" . "20" . $yy;
  $lastDate      = date_create_from_format('m/d/Y', $lastDateStr); 

  $lastMonth     = date_format($lastDate, 'n'); 
  $lastYr        = date_format($lastDate, 'Y'); 
  $qtr           = intdiv($lastMonth, 3) + (($lastMonth % 3 > 0) ? 1:0);
  $qtrEndMonth   = 3 + (($qtr-1) * 3);
  // push end of x-axis 1 quarter later than the last data point
	// nah.  forget it.
  // if ($qtrEndMonth < 10) { // Jan, Apr, Jul
  //   $qtrEndMonth = $qtrEndMonth+3;
  // } else {
  //   $qtrEndMonth = 1; // if qtrEndMonth was Oct, Jan starts next qtr
  //   $lastYr++;        // increment the year, too
  // }  
  $lastDOM      = cal_days_in_month(CAL_GREGORIAN, $qtrEndMonth,$lastYr);
  $qtrEndStr    = "$qtrEndMonth/$lastDOM/$lastYr";
  $ExcelQtrEndDateVal = excelDateValue($qtrEndStr); // derived from pure php code

  $minMaxDates = ['min' => $ExcelQtrStartDateVal, 'max' => $ExcelQtrEndDateVal ];
  return $minMaxDates;
}

##################################################################################
function excelDateValue($datestr) {
  $dayOne       = date_create_from_format('m/d/Y', "1/1/1900");
  $dateObj      = date_create_from_format('m/d/Y', $datestr); 
  $daysAfterOne = date_diff($dayOne, $dateObj);
  $excelDateVal = $daysAfterOne->format("%a");
  return 2+$excelDateVal; // at least according to php instead of phpss
}

##################################################################################
function setClrProps ($value, ?int $alpha, ?string $type): array
{
   $cl = array('value' => $value, 
               'alpha' => $alpha,
               'type'  => $type,
                );

   return $cl;
}

##################################################################################
function create_Trend_chart (
    $column, $nrows, $metric, $marker, $marker_size, $fill, $border, $yLabel, $topleftcell, $bottomrightcell, $trendLine, $dateMinMax, $Wrkbk) 
{

  $Datasheet = $Wrkbk->getSheetByName('Data');
  // use this one for all dataseries values
  $trendLines = [ 
    new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false),
  ] ;

// Set the Labels (Column header) for each data series we want to plot
  $cell = $column.'1'; // cell containing data label - DeclScr, e.g.
  $TrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Trend";
  $dataSeriesLabels = 
   [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'.$column.'$1', null, 1),
  ];
// Set the X-Axis Labels - dates, e.g. 07/08/2010 === July 8, 2010 NOT Aug 7, 2010!!
  $xAxisTickValues = 
  // [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$A$2:$A$'.($nrows+1), Properties::FORMAT_CODE_DATE, $nrows), 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$A$2:$A$'.($nrows), Properties::FORMAT_CODE_DATE, $nrows), 
  ];
  // since now using LineChart instead of ScatterChart, 
  // x-axis values are an Excel numeric representation of the date - so 
  // formatCode=General for the xAxis VALUES, but we want the labels to be 
  // displayed as 'm/d/yyyy' 

// Set the Data values for each data series we want to plot
  $dataSeriesValues = 
  // [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'.$column.'$2:$'.$column.'$'.($nrows+1), 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'.$column.'$2:$'.$column.'$'.($nrows), 
    Properties::FORMAT_CODE_NUMBER, $nrows, null, $marker, null, $marker_size),
  ];
	$dataSeriesValues[0]->setTrendLines($trendLines);
	
  $dataSeriesValues[0]->getMarkerFillColor()->setColorPropertiesArray($fill);
  $dataSeriesValues[0]->getMarkerBorderColor()->setColorPropertiesArray($border);
  
  // confusing passed-in var: $trendLine = array ($width, $color, $type, $order, $period, $dispRSqr, $dispEq), 
  $dataSeriesValues[0]
      ->setScatterlines(false); // disable connecting lines
      // ->setLineWidth(0.5)
      // ->getLineColor()->setColorPropertiesArray($trendLine[1]); // connecting line same color as trendline

  $dataSeriesValues[0]
      ->getTrendLines(0)
      ->setTrendLineProperties($trendLine[2], $trendLine[3], $trendLine[4], $trendLine[5], $trendLine[6], $TrendLineLabel) // describe trendLine 
      ->setLineStyleProperties($trendLine[0], null, null, null, null, null, null, Properties::LINE_STYLE_ARROW_TYPE_OPEN);
  $dataSeriesValues[0]->getTrendLines(0)->getLineColor()->setColorPropertiesArray($trendLine[1]); 

// Build the dataseries
//$xAxis->setAxisType ('dateAx'); // dateAx available ONLY for LINECHART, not SCATTERCHART
  $series = new DataSeries (
      DataSeries::TYPE_LINECHART, // plotType
      'standard', // plotGrouping
      range(0, count($dataSeriesValues) - 1), // plotOrder
      $dataSeriesLabels, // plotLabel
      $xAxisTickValues, // plotCategory (Dates)
      $dataSeriesValues, // plotValues
      null, // plotDirection
      false, // smooth line Boolean
      DataSeries::STYLE_LINEMARKER  // lineMarker
  );

// Set the series in the plot area
  $plotArea = new PlotArea(null, [$series]);
//  $plotArea-> fill and gradient properties now supported
  $pos1 = 0; // pos = 0% (extreme low side or lower left corner)
  $brightness1 = 0.5; // 0%
  $gsColor1  = new ChartColor;
  $gsColor1->setColorProperties('FF0000', 50, 'srgbClr', $brightness1); // red
  $gradientStop1 = array('pos' => $pos1, 'gsColor' => $gsColor1);

  $pos2 = 0.5; // pos = 50% (middle)
  $brightness2 = 0.5; // 0%
  $gsColor2  = new ChartColor;
  $gsColor2->setColorProperties('FFFF00', 50, 'srgbClr', $brightness2); // yellow
  $gradientStop2 = array('pos' => $pos2, 'gsColor' => $gsColor2);

  $pos3 = 1.0; // pos = 100% (extreme high side or upper right corner)
  $brightness3 = 0.5; // 50%
  $gsColor3  = new ChartColor;
  $gsColor3->setColorProperties('00B050', 50, 'srgbClr', $brightness3); // green
  $gradientStop3 = array('pos' => $pos3, 'gsColor' => $gsColor3);

  $gsLst = 
  [    $gradientStop1, 
       $gradientStop2,
       $gradientStop3
  ];
  $gradientAngle = "270"; // vertical (pointing down to red, first grad stop)

  $plotArea->setGradientFillProperties($gsLst, $gradientAngle);

// Set the chart legend
  $legend = new Legend(Legend::POSITION_RIGHT, null, 0);

  $title = new Title("$metric".' Trend');

// X axis (date) settings
  $xAxisLabel = new Title('Game Date');
  $xAxis = new Axis();
  // date axis values are Excel numbers, not mm/dd/yyyy Date strings
//  $xAxis->setAxisNumberProperties (Properties::FORMAT_CODE_GENERAL);?
  $xAxis->setAxisNumberProperties (Properties::FORMAT_CODE_DATE);

  $xAxis->setAxisType ('dateAx'); // dateAx available ONLY for LINECHART, not SCATTERCHART

  // measure the time span of games played.  change tick marks to match
  $nQtrs = sprintf("%3.2f",  (($dateMinMax['max'] - $dateMinMax['min'])/30.5)/4 ) ;
  
  $tickMarkInterval = ($nQtrs > 20) ? 6 : 3; // tick marks every ? months
  
  $xAxis->setAxisOptionsProperties (
      Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
      null, // horizontalCrossesValue
      null, // horizontalCrosses
      null, // axisOrientation
      "in", // major_tick_mark
      null, // minor_tick_mark
      $dateMinMax['min'], // minimum calculate this from the earliest data: 'Data!$A$2'
      $dateMinMax['max'], // maximum calculate this from the last data:     'Data!$A$'.($nrows)
      $tickMarkInterval,  // majorUnit determines tickmarks & Gridlines ?
      null, // minorUnit
      'months', // majorTimeUnit,
      "1",   // minorTimeUnit; value determines minorGridlines
      'days',// baseTimeUnit
      '0'    // auto - enable automatic axis selection ?
  );

  // $auto = (string) $xAxis->getAxisOptionsProperty('auto');
  // echo "<br>Xaxis : auto = $auto<br>";  

  $yAxisLabel = new Title($yLabel);
  $yAxis = new Axis();
  $yAxis->setAxisNumberProperties('0'); // decimal places (for percent in this case)

	// all charts have a y-axis of % (0-100%) except aveHCP whose range is 4-16
	// check metric for HCP to choose the correct y-axis
	if (preg_match("/HCP/i",$metric)) {
		$yAxis->setAxisOptionsProperties (
				Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
				null, // horizontalCrossesValue
				null, // horizontalCrosses
				null, // axisOrientation
				null, // major_tick_mark
				null, // minor_tick_mark
				"6",    // minimum
				"14",   // maximum
				"2",    // majorUnit
				"0.5",  // minorUnit
				null, // majorTimeUnit
				null, // minorTimeUnit
				null  // baseTimeUnit
		);
	} else {
		$yAxis->setAxisOptionsProperties (
				Properties::AXIS_LABELS_NEXT_TO, // axis_label pos
				null, // horizontalCrossesValue
				null, // horizontalCrosses
				null, // axisOrientation
				null, // major_tick_mark
				null, // minor_tick_mark
				"0",    // minimum
				"100",  // maximum
				"20", // majorUnit
				null, // minorUnit
				null, // majorTimeUnit
				null, // minorTimeUnit
				null  // baseTimeUnit
		);
	}
  $yAxis->setCrossBetween('between'); // req'd for LineChart

  $majorGridlines = new GridLines() ;
  // $majorGridlines->activateObject() ; // Nope. I can't activate it.  I must set some 
  // property of the Gridline, which will activate it for me.
  $majorGridlines->setLineStyleProperties(0.5) ; // linewidth, fer instance, should do the trick

// Create the chart
  $chart = new Chart (
    "$metric", // name
    $title, // title
    $legend, // legend
    $plotArea, // plotArea
    true, // plotVisibleOnly
    DataSeries::EMPTY_AS_GAP, // displayBlanksAs
    $xAxisLabel,  // xAxisLabel
    $yAxisLabel,  // yAxisLabel
    $xAxis, // xAxis
    $yAxis, // yAxis
    $majorGridlines, // majorGridlines - how to get into x-axis? modify writer/Xlsx/Chart.php - writeCategoryAxis() by adding Gridlines 
    // $minorGridlines, // minorGridlines
  );

// position the chart in the worksheet
  $chart->setTopLeftPosition($topleftcell);
  $chart->setBottomRightPosition($bottomrightcell);
  $chart->setRoundedCorners('true');

  return $chart;
}


###########################    function get_HCP_by_board    ######################
#
#    - called from controller for query6
#
# acquire the deals played by $pnum over a time period, then calc HCP per 
# hand;
#
##################################################################################

function get_HCP_by_board($conn, $pnum, $gmqualifier) {
	$games = [] ;

	$HCP_Hand_sql =
"
WITH

me AS (
    SELECT $pnum AS me
),
my_game_ids AS (
    SELECT DISTINCT p.GameId, p.club
    FROM events e
    CROSS JOIN me
    JOIN players p
      ON p.GameId = e.GameId
     AND p.club   = e.club
    WHERE e.$gmqualifier
      AND me.me IN (
		p.pnum1, p.pnum2, p.pnum3, p.pnum4, p.pnum5, p.pnum6
	  )
),
seats AS (
	SELECT r.GameId, r.club, r.board_id, r.npnum, r.spnum, r.epnum, r.wpnum 
    FROM my_game_ids mg
    JOIN results r
	  ON mg.GameId = r.GameId
     AND mg.club   = r.club
    WHERE (SELECT me FROM me) IN (r.npnum, r.spnum, r.epnum, r.wpnum)
),
my_seat AS (
    SELECT 
        s.GameId,
        s.club,
        s.board_id,
        CASE 
            WHEN s.npnum = me.me THEN 'N'
            WHEN s.spnum = me.me THEN 'S'
            WHEN s.epnum = me.me THEN 'E'
            WHEN s.wpnum = me.me THEN 'W'
				END AS myseat,
        CASE 
            WHEN s.npnum = me.me THEN 'S'
            WHEN s.spnum = me.me THEN 'N'
            WHEN s.epnum = me.me THEN 'W'
            WHEN s.wpnum = me.me THEN 'E'
				END AS pardseat

    FROM seats s
    CROSS JOIN me
),
hands AS (
    SELECT 
        ms.GameId,
        ms.club,
        ms.board_id,
        ms.myseat, ms.pardseat,
        h.deal
    FROM my_seat ms
    JOIN handsdd h
      ON h.GameId   = ms.GameId
     AND h.club     = ms.club
     AND h.board_id = ms.board_id
)
SELECT  *
FROM hands
ORDER BY GameId, board_id;
";

  // Check connection
  if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
  }

	// each row is a $board_id from a $game at a $club.
	// 'me' sits in $seat, holding $deal
	$deal_result = mysqli_query($conn, $HCP_Hand_sql); 

	if (!$deal_result) {
    error_log("SQL ERROR: " . mysqli_error($conn));
    error_log("SQL QUERY: " . $HCP_Hand_sql);
    return [];  // prevent further errors
	}

	$seatIndex = [
		'W' => 0,
		'N' => 1,
		'E' => 2,
		'S' => 3
	];

	$hcpValues = [
		'A' => 4,
		'K' => 3,
		'Q' => 2,
		'J' => 1
	];

#   GameId,                        club, board_id, myseat, pardseat, 		 deal
#'260105AA', 'Albany Duplicate Bridge C',     '5',    'E',      'W', 'W:K...3'
  while ($r = mysqli_fetch_assoc($deal_result)) {
		if ($r['deal'] == '') continue;# skip boards with blank $deal
		$gmid 		= $r['GameId'];
		$club 		= $r['club'];
		$bd   		= $r['board_id'];
		$myseat 	= $r['myseat'];
		$pardseat = $r['pardseat'];
		$deal 		= $r['deal'];
		
//	create new struct for HCP calcs
//		$games[$gmid] = [
//   		'club'      => $club,
//   		'boards'    => [
//       	$bd => [
//           'myseat' 	=> $myseat,
//           'pardseat' => $pardseat,
//           'myhcp'  	=> $myhcpForThisHand,
//           'pardhcp'	=> $pardhcpForThisHand
//       	],
//   		]
//		];

		if (preg_match ("/Corvallis Dupl/i", $club)) {
			$club = 'CDBC';
		} elseif (preg_match ("/Albany/i", $club)) {
			$club = 'Albany';
		} elseif (preg_match ("/New Deal/i", $club)) {
			$club = 'NewDeal Online';
		} elseif (preg_match ("/Corvallis DBC/i", $club)) {
			$club = 'Corv OnLine';
		} elseif (preg_match ("/valley/i", $club) or
							preg_match ("/HOV/i", $club) or 
							preg_match ("/477/", $club)) {
			$club = 'HOV'; }

		$hands = explode (' ', $deal) ;
		// Assume always $west = $hands[0] ; 
		// remove extra "W:" prefix from west hand 
		$hands[0] = ltrim($hands[0],"W:") ;

		$myHand 	= $hands[$seatIndex[$myseat]];
		$pardHand = $hands[$seatIndex[$pardseat]];

		# guard against Yarborough
		$games[$gmid]['boards'][$bd] = [
			'myseat' 	 => $myseat,
			'pardseat' => $pardseat,
			'myhcp' 	 => 0,
			'pardhcp'  => 0
		] ;
		$myhcpForThisHand = 0; // avoid accumulating
		$pardhcpForThisHand = 0; // avoid accumulating

		foreach (str_split($myHand) as $card) {
			$myhcpForThisHand += $hcpValues[$card] ?? 0;
		}
		foreach (str_split($pardHand) as $card) {
			$pardhcpForThisHand += $hcpValues[$card] ?? 0;
		}
		$games[$gmid]['club'] = $club;
		$games[$gmid]['boards'][$bd] = [
			'myseat' 	 => $myseat,
			'pardseat' => $pardseat,
			'myhcp'  	 => $myhcpForThisHand, 
			'pardhcp'  => $pardhcpForThisHand
		];
	}

	return $games;

} // get_HCP_by_board() - query6

?>
