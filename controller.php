<?php

require_once __DIR__ . '/config.php';

//    Uncomment next 3 lines when white-screen-of-death appears with empty php_error_log file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------------------------------------------
// 0. Includes (real functions will go here later)
// ------------------------------------------------------------
include_once 'helperFunctions.php';

// ------------------------------------------------------------
// 1. Connect to db
// ------------------------------------------------------------
// $servername = "localhost";
// $username = "uydcdcyfwllsh";
// $password = "qddigrzbntsg";
// $dbname   = "db4krxl4bpud17";
// Rely on config.php to supply the login credentials

error_log("SERVERNAME: $servername");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);
// Check connection
if (!$conn) {
   die("Connection failed: " . mysqli_connect_error());
}

// ------------------------------------------------------------
// 2. Initialize variables (all variables the template expects)
// ------------------------------------------------------------
$player_id = trim($_POST['player_id'] ?? '');
$pnum      = trim($_POST['pnum'] ?? '');

$playerNAME        = '';
$matches           = [];
$multiple_matches  = false;
$no_matches        = false;
$playerqueryErr    = '';

$startDate         = '2026-01-01';
$stopDate          = date('Y-m-d');
$gmqualifier       = '';

$query1 = $query2 = $query3 = $query4 = $query5 = $query6 = false;
$queryErr = '';

$email = '';
$emailErr = "";

$send = false;
$sendErr = '';

$comment = '';
$comment_sent = false;

$MAEValues = [
  'M' => "Mor",
  'A' => "Aft",
  'E' => "Eve",
  'L' => "Late",
];

$results = [];   // all output goes here
$timers = [];		// measure execution times
// log file to measure execution times
$logfile = fopen(LOG_FILE, "a") or die("Unable to open file logfile2.txt !");


// ------------------------------------------------------------
// 3a. Only run logic if POST was submitted
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

// --------------------------------------------------------
// 3b. Player identification (REAL VERSION)
// --------------------------------------------------------
	if ($pnum !== '') {
    $playerNAME = playername($pnum, $conn);
    if (!$playerNAME) {
      $playerqueryErr = "Selected player not found in database.";
		} else {
			$player_id = $playerNAME;
    }
	}
	elseif ($player_id === '') {
    $playerqueryErr = "Player name or number is required.";
	}
	else {
    // Try number
    $normalized = normalize_pnum($player_id);

    if ($normalized !== null) {
  	  // NUMBER PATH
   	  $name = playername($normalized, $conn);

   	  if ($name) {
   	    $pnum = $normalized;
   	    $playerNAME = $name;
				$player_id = $playerNAME ; 
   	  } else {
   	    $playerqueryErr = "No player found with this number.";
   	  }
		}

    // NAME PATH
    else {
      $return_item = playernum($player_id, $conn);

      if (!$return_item) {
        $no_matches = true;
        $playerqueryErr = "Name not found. Try entering only the last name.";
      }
      else {
        $size = count($return_item);

        // Single match: [0 => pnum, 1 => name]
        if ($size === 2 && array_key_exists(0, $return_item)) {
          $pnum = $return_item[0];
          $playerNAME = $return_item[1];
					$player_id = $playerNAME ; 
        }

        // Multiple matches: assoc array pnum => [name, city, state]
        else {
          $matches = $return_item;
          $multiple_matches = true;
          $playerqueryErr = "Please select a player from this list, then press the Submit button.";
        }
      }
    }
	}

  // --------------------------------------------------------
  // 4. Date range
  // --------------------------------------------------------
  // $startDate = $_POST['startDate'] ?? '2010-08-08';
  // $stopDate  = $_POST['stopDate']  ?? date('Y-m-d');
	$startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : '2026-01-01';
	$stopDate  = !empty($_POST['stopDate'])  ? $_POST['stopDate']  : date('Y-m-d');
	

  $gmqualifier = GameId_qualifier($startDate, $stopDate);


  // --------------------------------------------------------
  // 5. Query checkboxes
  // --------------------------------------------------------
  $query1 = isset($_POST['query1']);
  $query2 = isset($_POST['query2']);
  $query3 = isset($_POST['query3']);
  $query4 = isset($_POST['query4']);
  $query5 = isset($_POST['query5']);
  $query6 = isset($_POST['query6']);

  if (!$query1 && !$query2 && !$query3 && !$query4 && !$query5 && !$query6) {
    $queryErr = "Please select one or more searches, then press the Submit button.";
  }


  // --------------------------------------------------------
  // 6. Email + comments
  // --------------------------------------------------------
  $send = isset($_POST['send']);
  $email = test_input($_POST['email'] ?? '');

  // check if e-mail address is well-formed
	if ($email) {
  	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    	$emailErr = "Invalid email format";
  	} else {
    	$emailErr = "";
  	}
	}

  if ($send && $email === '') {
    $sendErr = "Email address required if sending results.";
  }

  $comment = test_input($_POST['comment'] ?? '');
  if ($comment !== '') {
	  $msg = wordwrap($comment,70);
  	$header = "From: rick.garvin@acbridge.org" ;
  	// mail("rick.garvin@acbridge.org", "Bridge WebSQL comment", $msg, $header);
  	mail("director@acbridge.org", "Bridge WebSQL comment", $msg, $header);
  	$results['comment'] = "<p class='e'>Your comment/request has been sent.</p><br>"; 

    $comment_sent = true;   // dummy
		$comment = '' ;
  }


  // --------------------------------------------------------
  // Queries
  // --------------------------------------------------------
  if ($pnum !== '') {

		$pNAME = htmlspecialchars($playerNAME) ;
		$period = "from $startDate to $stopDate";

  	// --------------------------------------------------------
  	// query1 - Get participation history
  	// --------------------------------------------------------
		if ($query1) {
			$results['history'] = '';
			$games_played = get_history($conn, $pnum, $gmqualifier) ;

			if ($games_played > 0) {
				$results['history']	= 
					"<h3>" . htmlspecialchars(playername($pnum, $conn)) . 
					" has played $games_played times including individual, pairs, home-style pairs and teams between $startDate and $stopDate</h3>";
			} else {
				$results['history'] =
          "<p>No games found for ". htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
      }


  		if ($send && $email !== '') {
				$plain = html_to_text_table($results['history']);
        $subj="$pNAME's playing history";
				// alternative
  			// $header = "From: rick.garvin@acbridge.org" ;
				// mail($email, $subj, $mailmsg, $header);
        senddata($plain, $email, $subj);
      }

			$results['history'].= "<p><a href='#top'>Return to top</a></p>";
		}

  	// --------------------------------------------------------
  	// query2 - Get all partners + teammates + indy psuedo-partners
  	// -------------------------------------------------------- 
    if ($query2) {
		$results['partners'] = '';

			$DateTime = date("Y-m-d H:i:s");

			fwrite(
        $logfile,
        "\n$DateTime\n\tStart partner list generation $period for $pNAME\n"
			);

			timer_start($timers, 'partners');

			$query2rows = get_all_partners($conn, $pnum, $gmqualifier) ;

			// $query2rows will always contain a footer row containing the TOTALs.
			// When that's the only row, the table is empty.  Don't show the table.

			if (count($query2rows) == 1 && (int)$query2rows[0]['total'] === 0) {
				$results['partners'] = "<p>No partners or teammates found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
				// return; do not use return.  It exits controller.
			} else { // build the table of partners/teammates/indy

				$html =  "<h1 style='text-align:center'>Partners and teammates of $pNAME $period</h1>";
				$html .= "<div class='table-container'>" ;
				$html .= "<table>";
				$html .= 
					"<thead>
						<tr>
							<th>Total*</th>
							<th>Partner</th>
							<th>Teammate</th>
							<th>Indy</th>
							<th>Name</th>
							<th>Pardnum</th>
						</tr>
					</thead>";

				$html .= "<tbody>";

				$total_row = null;
				$actual_team_games = null;

				foreach ($query2rows as $r) {

					// Detect last row; stuff array $total_row
					if ($r['pardnum'] === 'TOTAL') {
					// Save for later
						$total_row = $r;
						$actual_team_games = $r['name'];
						continue;
					}

					// Normal row
					$html .= 
						"<tr>
							<td>{$r['total']}</td>
							<td>{$r['partner_freq']}</td>
							<td>{$r['teammate_freq']}</td>
							<td>{$r['indy_freq']}</td>
							<td>{$r['name']}</td>
							<td>{$r['pardnum']}</td>
						</tr>";
				}

				// Now output modified TOTAL row
				if ($total_row) {
					$html .= 
						"<tr>
							<td>{$total_row['total']}*</td>
							<td>{$total_row['partner_freq']}</td>
							<td>{$total_row['teammate_freq']}*</td>
							<td>{$total_row['indy_freq']}</td>
							<td><strong>TOTALS</strong></td>
							<td></td>
						 </tr>";

					$html .= "</tbody>";
					$html .= "</table>";
					$html .= "</div>"; // table-container

					$html .= 
						"<p><em>* Sum of teammates overcounts team games; the actual number of team games is " . 
						"$actual_team_games </em></p>";
				}
				$html .= "<hr>"; # horiz rule

				$results['partners'] = $html;

  			if ($send && $email !== '') {
					$plain = html_to_text_report($html);
					$subj="$pNAME's pareto of partners";
					senddata($plain, $email, $subj);
				}

				$results['partners'].= "<p><a href='#top'>Return to top</a></p>";

				timer_stop(
					$timers,
					'partners',
					$logfile,
					'partner list generation'
				);
			}
  	} // query2

  	// --------------------------------------------------------
		// Query3a - Show detailed perf measures for each partner 
		// who has played more than 10% of the total times played 
		// or more than 20 times 
  	// --------------------------------------------------------
		if ($query3) {
			$results['partner_details'] = '';
			$results['partners_summary'] = '' ;

			$DateTime = date("Y-m-d H:i:s");

			fwrite(
        $logfile,
        "\n$DateTime\n\tStart partner detail calculations $period for $pNAME\n"
			);

			timer_start($timers, 'partner_details');

			$query3arows = get_partner_details($conn, $pnum, $gmqualifier) ;

			// if (!$query3arows) {
			if (count($query3arows)== 0) {
				$results['partner_details'] = "<p>No pair games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
				// return; do not use return.  It exits controller.
			} else { // build the table 

				// Section/Query3a Title
				$html = "<h1 style='text-align:center'>Performance of $pNAME with partners who played more than 20 times, or more than 10% of the total.</h1>";
				$html .= "<br>";

				$currentPardnum = null;
				$gamesTogether = 0;

				// accumulators
				$count = 0;
				$total_pct = 0;
				$total_PlyrFrq= 0;	$total_PlyrETF= 0;  $total_PlyrOLE= 0;
				$total_pardFrq= 0;	$total_pardETF= 0;  $total_pardOLE= 0; 
				$total_DefFrq= 	0;	$total_DefETF=  0;  $total_oppOLE=  0; 
				
				$total_ovRtgA= 0;		$total_ovRtgB= 0;	 $total_ovRtgC= 0;
				$total_ovPopA= 0; 	$total_ovPopB= 0;  $total_ovPopC= 0;
				$total_secRtgA= 0; 	$total_secRtgB= 0; $total_secRtgC= 0;
				$total_secPopA= 0; 	$total_secPopB= 0; $total_secPopC= 0;

				$emitFooter = function() use (
						&$html,
						&$total_pct, 
						&$total_PlyrFrq, &$total_PlyrETF, &$total_PlyrOLE,
						&$total_pardFrq, &$total_pardETF, &$total_pardOLE,
						&$total_DefFrq, &$total_DefETF, &$total_oppOLE,
						&$total_ovRtgA, &$total_ovRtgB, &$total_ovRtgC,
						&$total_ovPopA, &$total_ovPopB, &$total_ovPopC,
						&$total_secRtgA, &$total_secRtgB, &$total_secRtgC,
						&$total_secPopA, &$total_secPopB, &$total_secPopC,
						&$count
				) {

						// if ($count === 0) return; // inside controller, return=exit!!

						// compute averages (your existing code)
						$avg_pct       = sprintf("%8.1f", $total_pct / $count) . "%";
						$avg_PlyrFrq   = sprintf("%8.1f", $total_PlyrFrq / $count) . "%";
						$avg_PlyrETF   = sprintf("%8.2f", $total_PlyrETF / $count);
						$avg_PlyrOLE   = sprintf("%8.2f", $total_PlyrOLE / $count);
						$avg_pardFrq   = sprintf("%8.1f", $total_pardFrq / $count) . "%";
						$avg_pardETF   = sprintf("%8.2f", $total_pardETF / $count);
						$avg_pardOLE   = sprintf("%8.2f", $total_pardOLE / $count);
						$avg_DefFrq    = sprintf("%8.1f", $total_DefFrq / $count) . "%";
						$avg_DefETF    = sprintf("%8.2f", $total_DefETF / $count);
						$avg_oppOLE    = sprintf("%8.2f", $total_oppOLE / $count);

						$avg_ovRtgA    = $total_ovPopA > 0 ? sprintf("%8.1f", 100 * $total_ovRtgA / $total_ovPopA) : '-';
						$avg_ovRtgB    = $total_ovPopB > 0 ? sprintf("%8.1f", 100 * $total_ovRtgB / $total_ovPopB) : '-';
						$avg_ovRtgC    = $total_ovPopC > 0 ? sprintf("%8.1f", 100 * $total_ovRtgC / $total_ovPopC) : '-';
						// $avg_ovRtg_string = $avg_ovRtgA . "% " . $avg_ovRtgB . "% " . $avg_ovRtgC . "%";
						// prettified
						$avg_ovRtg_string = implode(' ', [
																	fmt_pct($avg_ovRtgA), 
																	fmt_pct($avg_ovRtgB), 
																	fmt_pct($avg_ovRtgC),
																]);

						$avg_secRtgA   = $total_secPopA > 0 ? sprintf("%8.1f", 100 * $total_secRtgA / $total_secPopA) : '-';
						$avg_secRtgB   = $total_secPopB > 0 ? sprintf("%8.1f", 100 * $total_secRtgB / $total_secPopB) : '-';
						$avg_secRtgC   = $total_secPopC > 0 ? sprintf("%8.1f", 100 * $total_secRtgC / $total_secPopC) : '-';
						// prettified
						$avg_secRtg_string = implode(' ', [
																	fmt_pct($avg_secRtgA), 
																	fmt_pct($avg_secRtgB), 
																	fmt_pct($avg_secRtgC),
																]);

						// emit footer HTML (your existing code)
						$html .= "</tbody>";
						$html .= 
							"<tfoot>
								<tr>
									<td>Ave:</td>
									<td>-</td>
									<td>-</td>
									<td>-</td>
									<td>$avg_pct</td>
									<td>-</td>
									<td>-</td>
									<td>-</td>
									<td></td>
									<td class='grpA'>$avg_PlyrFrq</td>
									<td class='grpA'>$avg_pardFrq</td>
									<td class='grpA'>$avg_DefFrq</td>
									<td></td>
									<td class='grpB'>$avg_PlyrETF</td>
									<td class='grpB'>$avg_pardETF</td>
									<td class='grpB'>$avg_DefETF</td>
									<td></td>
									<td class='grpE'>$avg_PlyrOLE</td>
									<td class='grpE'>$avg_pardOLE</td>
									<td class='grpE'>$avg_oppOLE</td>
									<td>-</td>
									<td></td>
									<td class='grpC'>-</td>
									<td class='grpC'>-</td>
									<td></td>
									<td class='grpD' >$avg_ovRtg_string</td>
									<td class='grpD' >$avg_secRtg_string</td>
								</tr>
							</tfoot>";

						$html .= "</table>";
						$html .= "</div>";      // table-container
						$html .= "</figure>";   // caption
//					$html .= "<p><a href='#top'>Return to top</a></p>";
				};

				foreach ($query3arows as $r) {

					$pardnum= 		 $r['pardnum']; // use pnum to test for next partner since pardName not canonical!
					$gamesTogether=$r['games_together']; 

					// NEW PARTNER DETECTED?
					if ($currentPardnum !== $pardnum) {

						// If this is NOT the first partner, close the previous table
						if ($currentPardnum !== null) {
							$emitFooter();
						}

						// Start a new table for this partner
						$html .= "<hr>";
						$html .= "<figure class='table-wrapper'>";
						$html .= "<figcaption>Performance with " . playername($pardnum, $conn) . 
										 " who played {$r['games_together']} times with $pNAME $period</figcaption>"; 
						$html .= "<div class='table-container'>" ;
						$html .= "<table data-text-schema='partner-detail'>";

						# header 
						$html .= "<thead>";
						# row 1
						$html .= 
							"<tr>
								<th></th>
								<th style='vertical-align:bottom'>Mor</th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th class='toggle' data-target='grpA'></th>
								<th class='grpA' colspan='3'>Play Freq</th>
								<th class='toggle' data-target='grpB'></th>
								<th class='grpB' colspan='3'>Tot ETF when</th>
								<th class='toggle' data-target='grpE'></th>
								<th class='grpE' colspan='3'>Tot OLF when</th>
								<th></th>
								<th class='toggle' data-target='grpC'></th>
								<th class='grpC' style='text-align:left'>OvrlRnk<sup>1</sup> A</th>
								<th class='grpC' style='text-align:left'>ScntnRnk<sup>2</sup> A</th>
								<th class='toggle' data-target='grpD'></th>
								<th class='grpD' style='text-align:left'>OvrlRating<sup>3</sup> A</th>
								<th class='grpD' style='text-align:left'>SctnRating<sup>4</sup> A</th>
							</tr>";
						# row 2
						$html .= 
							"<tr style='vertical-align:bottom'>
								<th></th>
								<th>Aft</th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th>game</th>
								<th></th>
								<th>Decl</th>
								<th class='grpA' colspan='2'>Dclring (typ 25% ea)</th>
								<th class='grpA'>Dfndng (typ 50%)</th>
								<th></th>
								<th class='grpB' colspan='2'>Dclring (>0 good)</th>
								<th class='grpB'>Dfndng (<0 good)</th>
								<th></th>
								<th class='grpE' colspan='2'>Dfndng (0 best)</th>
								<th class='grpE'>Dclring (>0 good)</th>
								<th></th>
								<th></th>
								<th class='grpC'>OvrlRnk B</th>
								<th class='grpC'>ScntnRnk B</th>
								<th></th>
								<th class='grpD'>OvrlRating B</th>
								<th class='grpD'>SctnRating B</th>
							</tr>";
						# row 3
						$html .= 
							"<tr style='vertical-align:bottom'>
								<th>YYMMDD</th>
								<th>Eve</th>
								<th>Sctn</th>
								<th>club</th>
								<th>Score%</th>
								<th>Scoring</th>
								<th>type</th>
								<th>Movement</th>
								<th>vs Dfnd</th>
								<th class='grpA'>Me</th>
								<th class='grpA'>pard</th>
								<th class='grpA'>opp</th>
								<th>ETF</th>
								<th class='grpB'>Me</th>
								<th class='grpB'>pard</th>
								<th class='grpB'>opp</th>
								<th>OLF</th>
								<th class='grpE'>Me</th>
								<th class='grpE'>pard</th>
								<th class='grpE'>opp</th>
								<th>Flt</th>
								<th>Rnk</th>
								<th class='grpC' style='text-align:right'>OvrlRnk C</th>
								<th class='grpC' style='text-align:right'>ScntnRnk C</th>
								<th>Rtg</th>
								<th class='grpD' style='text-align:right'>OvrlRating C</th>
								<th class='grpD' style='text-align:right'>SctnRating C</th>
							</tr>";

						// end head
						$html .= "</thead>";
						// begin body
						$html .= "<tbody>";

						// Reset accumulators
						$count = 0;
						$total_pct=     0;
						$total_PlyrFrq= 0;	$total_PlyrETF= 0;  $total_PlyrOLE= 0;
						$total_pardFrq= 0;	$total_pardETF= 0;  $total_pardOLE= 0; 
						$total_DefFrq= 	0;	$total_DefETF=  0;  $total_oppOLE=  0; 
						
						$total_ovRtgA=  0;	$total_ovRtgB=  0;	$total_ovRtgC=  0;
						$total_ovPopA=  0; 	$total_ovPopB=  0;	$total_ovPopC=  0;
						$total_secRtgA= 0;  $total_secRtgB= 0;	$total_secRtgC= 0;
						$total_secPopA= 0;  $total_secPopB= 0;	$total_secPopC= 0;

						$currentPardnum = $pardnum;
					}

					// Process this row
					$count++ ;
					$gt= $r['gametype']; 
					$club= $r['club']; 
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
										preg_match ("/477/", $club)) 
					{ $club = 'HOV'; }

					// treat HomeStyle pairs games different from pair games
					if ($gt == 'teams') {
						$count-- ; // prevent HomeStyle pairs games from "contaminating" these values' averages
						$r['my_dclr_frq'] = null ;
						$r['pard_dclr_frq'] = null ;
						$r['def_frq'] = null ;
						$r['ETF_me'] = null ;
						$r['ETF_pard'] = null ;
						$r['ETF_defense'] = null ;
						$r['OLE_me'] = null ;
						$r['OLE_pard'] = null ;
						$r['OLE_opp'] = null ;
						$r['ovRtgA'] = null ;
						$r['ovRtgB'] = null ;
						$r['ovRtgC'] = null ;
						$r['pop_ov_A'] = null ;
						$r['pop_ov_B'] = null ;
						$r['pop_ov_C'] = null ;
						$r['secRtgA'] = null ;
						$r['secRtgB'] = null ;
						$r['secRtgC'] = null ;
						$r['pop_sec_A'] = null ;
						$r['pop_sec_B'] = null ;
						$r['pop_sec_C'] = null ;
					}
					$total_pct +=   	$r['Pct'];
					$total_PlyrFrq += $r['my_dclr_frq'];		$total_PlyrETF += $r['ETF_me'];				$total_PlyrOLE += $r['OLE_me'];
					$total_pardFrq += $r['pard_dclr_frq'];	$total_pardETF += $r['ETF_pard'];			$total_pardOLE += $r['OLE_pard'];
					$total_DefFrq  += $r['def_frq'];				$total_DefETF  += $r['ETF_defense'];	$total_oppOLE  += $r['OLE_opp'];

					$total_ovRtgA +=  $r['ovRtgA'] 	  ;	$total_ovRtgB += $r['ovRtgB'] 		;	$total_ovRtgC += $r['ovRtgC'] ;
					$total_ovPopA +=  $r['pop_ov_A']  ; $total_ovPopB += $r['pop_ov_B'] 	;	$total_ovPopC += $r['pop_ov_C'] ;
					$total_secRtgA += $r['secRtgA']   ;	$total_secRtgB += $r['secRtgB'] 	;	$total_secRtgC += $r['secRtgC'] ;
					$total_secPopA += $r['pop_sec_A'] ; $total_secPopB += $r['pop_sec_B'] ;	$total_secPopC += $r['pop_sec_C'] ;

					$gamesTogether= $r['games_together'];
					
					$GameId=        $r['GameId'];
					$yymmdd= substr($r['GameId'],0,6); 
					$MAE= 	 strtoupper(trim(substr($r['GameId'],6,1)));
					$section=strtoupper(substr($r['GameId'],7,1));

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

					if ($gt != 'teams') {
						// Clarify fractional ratings; add   " of "   between rating and pop
						$ovRtg_string = $r['ovRtgA'] . " of " . $r['pop_ov_A'] . " " ; // strat A ov rating always present
						$ovRtg_string .= (dash($r['ovRtgB']) !== "-") ?  $r['ovRtgB'] . " of " . $r['pop_ov_B'] . " " : " -";
						$ovRtg_string .= (dash($r['ovRtgC']) !== "-") ?  $r['ovRtgC'] . " of " . $r['pop_ov_C'] : " -";

						$secRtg_string = $r['secRtgA'] . " of " . $r['pop_sec_A'] . " " ; // strat A sec rating always present 
						$secRtg_string .= (dash($r['secRtgB']) !== "-") ?  $r['secRtgB'] . " of " . $r['pop_sec_B'] . " " : " -";
						$secRtg_string .= (dash($r['secRtgC']) !== "-") ?  $r['secRtgC'] . " of " . $r['pop_sec_C'] : " -";
					} else {
						$ovRtg_string = null;
						$secRtg_string = null;
					}

					$myfrq   = $r['my_dclr_frq']   == null ? '-' : $r['my_dclr_frq'] . '%'  ;
					$pardfrq = $r['pard_dclr_frq'] == null ? '-' : $r['pard_dclr_frq'] . '%'  ;
					$deffrq  = $r['def_frq']       == null ? '-' : $r['def_frq'] . '%'  ;

					$MAEfull = $MAEValues[$MAE] ?? $MAE;

					$html .= 
						"<tr>
							<td>$yymmdd</td>
							<td>$MAEfull</td>
							<td>$section</td>
							<td>$club</td>";
					if ($r['Pct'] >= 75.00) {
						$html .= "<td class='biggame'><strong>{$r['Pct']}%</strong></td>";
					} elseif ($r['Pct'] >= 60.00) {
						$html .= "<td class='goodgame'><strong>{$r['Pct']}%</strong></td>";
					} else {
						$html .= "<td>{$r['Pct']}%</td>";
					}
					$html  .= 
						 "<td>{$r['ScrMethod']}</td>
							<td>{$r['gametype']}</td>
							<td>{$r['movement']}</td>
							<td></td>
							<td class='grpA'>$myfrq</td>
							<td class='grpA'>$pardfrq</td>
							<td class='grpA'>$deffrq</td>
							<td></td>
							<td class='grpB'>{$r['ETF_me']}</td>
							<td class='grpB'>{$r['ETF_pard']}</td>
							<td class='grpB'>{$r['ETF_defense']}</td>
							<td></td>
							<td class='grpE'>{$r['OLE_me']}</td>
							<td class='grpE'>{$r['OLE_pard']}</td>
							<td class='grpE'>{$r['OLE_opp']}</td>
							<td>{$r['strat']}</td>
							<td></td>
							<td class='grpC'>$ovRnk_string</td>
							<td class='grpC'>$secRnk_string</td>
							<td></td>
							<td class='grpD'>$ovRtg_string</td>
							<td class='grpD'>$secRtg_string</td>
					 </tr>";
				} // foreach 

				// After loop ends, close the last partner’s table DUPLICATE!!
				$emitFooter();

				$results['partner_details'] = $html;

  			if ($send && $email !== '') {
					$plain = html_to_text_report($html);
					$subj="$pNAME's partner details";
					senddata($plain, $email, $subj);
				}

				$results['partner_details'].= "<p><a href='#top'>Return to top</a></p>";

				timer_stop(
					$timers,
					'partner_details',
					$logfile,
					'partner detail calculation'
				);


				// --------------------------------------------------------
				// Query3b Summary of partners' performance
				// --------------------------------------------------------

				$DateTime = date("Y-m-d H:i:s");

				fwrite(
					$logfile,
					"\n$DateTime\n\tStart partners summary aggregation $period for $pNAME\n"
				);

				timer_start($timers, 'partners_summary');

				$query3brows = get_partners_summary($conn, $pnum, $gmqualifier) ;

				// should never get here if there were no pair games found in query3a !!
				// ... but just in case ...
				if (count($query3brows)== 0) {
					$results['partners_summary'] = "<p>No pair games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
					// return; do not use return.  It exits controller.
				} else { // build the summary tables 

				$html = "<hr><h1 style='text-align:center'>Sorted Performance of partners of $pNAME</h1>";
				$html .= "<div class='block500pxHigh'>"; 

				$html .= "<br>";

				$html .= "<hr>";

				# frozen header
				$html .= "<div class='table-container'>" ;
				$html .= "<table data-text-schema='partners-summary'>";

				# header 
				$html .= "<thead>";
				$html .= 
					"<tr style='vertical-align:bottom'>
						<th data-sort='text'>Partner</th>
						<th data-sort='num'>Games Together</th>
						<th data-sort='num'>Ave_Scr%</th>
						<th data-sort='num'>My Av Declr Frq%</th>
						<th data-sort='num'>My Av Declr Scr%</th>
						<th data-sort='num'>Pard Av Declr Frq%</th>
						<th data-sort='num'>Pard Av Declr Scr%</th>
						<th data-sort='num'>Our Av Def Frq%</th>
						<th data-sort='num'>Our Av Def Scr%</th>
						<th data-sort='num'>TotalBds</th>
						<th data-sort='num'>MyETF/Bd</th>
						<th data-sort='num'>PardETF/Bd</th>
						<th data-sort='num'>DefETF/Bd</th>
						<th data-sort='num'>MyOLF/Bd</th>
						<th data-sort='num'>PardOLF/Bd</th>
						<th data-sort='num'>oppOLF/Bd</th>
						<th data-sort='num'>Av_Ov A rtng%</th>
						<th data-sort='num'>Av_Ov B rtng%</th>
						<th data-sort='num'>Av_Ov C rtng%</th>
						<th data-sort='num'>Av_Sctn A rtng%</th>
						<th data-sort='num'>Av_Sctn B rtng%</th>
						<th data-sort='num'>Av_Sctn C rtng%</th>
					</tr>";

					// end header
					$html .= "</thead>";
					// begin body
					$html .= "<tbody>";

				foreach ($query3brows as $r) {
					$OavA= (dash($r['ov_avrtg_A'])  !== '-')  ? $r['ov_avrtg_A']."%" : '-' ; 
					$OavB= (dash($r['ov_avrtg_B'])  !== '-')  ? $r['ov_avrtg_B']."%" : '-' ; 
					$OavC= (dash($r['ov_avrtg_C'])  !== '-')  ? $r['ov_avrtg_C']."%" : '-' ; 
					$SavA= (dash($r['sec_avrtg_A']) !== '-') ? $r['sec_avrtg_A']."%" : '-' ; 
					$SavB= (dash($r['sec_avrtg_B']) !== '-') ? $r['sec_avrtg_B']."%" : '-' ; 
					$SavC= (dash($r['sec_avrtg_C']) !== '-') ? $r['sec_avrtg_C']."%" : '-' ; 

					$html .= 
						"<tr>
							<td>{$r['pardName']}</td>
							<td>{$r['games_together']}</td>
							<td>{$r['avg_pct']}%</td>
							<td>{$r['my_av_decl_frq']}%</td>
							<td>{$r['my_av_decl_scr']}%</td>
							<td>{$r['pard_av_decl_frq']}%</td>
							<td>{$r['pard_av_decl_scr']}%</td>
							<td>{$r['our_av_def_frq']}%</td>
							<td>{$r['our_av_def_scr']}%</td>
							<td>{$r['total_boards']}</td>
							<td>{$r['MyETFperBd']}</td>
							<td>{$r['PardETFperBd']}</td>
							<td>{$r['DefETFperBd']}</td>
							<td>{$r['MyOLEperBd']}</td>
							<td>{$r['PardOLEperBd']}</td>
							<td>{$r['OppOLEperBd']}</td>
							<td>$OavA</td>
							<td>$OavB</td>
							<td>$OavC</td>
							<td>$SavA</td>
							<td>$SavB</td>
							<td>$SavC</td>
						</tr>";
				}

				$html .= "</tbody>";
				$html .= "</table>";
				$html .= "</div>";      // table-container

				$html .= "<hr>";
//			$html .= "<p><a href='#top'>Return to top</a></p>";

				$results['partners_summary'] = $html ;

  			if ($send && $email !== '') {
					$plain = html_to_text_report($html);
					$subj="$pNAME's partners summary";

					senddata($plain, $email, $subj);
				}

				$results['partners_summary'].= "<p><a href='#top'>Return to top</a></p>";

				timer_stop(
					$timers,
					'partners_summary',
					$logfile,
					'partner summary aggregation'
				);

				} // query3b
			} // query3a
		} // query3a & b combined

  	// --------------------------------------------------------
		// Query4a - Show perf measures for each team game with 
		// teammates who have played more than 10% of the total times 
		// played or more than 20 times 
  	// --------------------------------------------------------
		if ($query4) {
			$results['teamGame_details'] = '';
			$results['teammates_summary'] = '' ;

			$DateTime = date("Y-m-d H:i:s");

			fwrite(
				$logfile,
				"\n$DateTime\n\tStart teammate detail calculations $period for $pNAME\n"
			);

			timer_start($timers, 'teammate_details');

			$query4arows = get_teamgames($conn, $pnum, $gmqualifier) ;

			if (count($query4arows) === 0) {
				$results['teamGame_details'] = "<p>No team games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
			} else { // build the tables 

				// accumulators
				$count = 0;
				$total_pct = 0;
				$total_ovRtgA= 0;		$total_ovRtgB= 0;	 $total_ovRtgC= 0;
				$total_ovPopA= 0; 	$total_ovPopB= 0;  $total_ovPopC= 0;

				// Section/Query4a Title
				$html = "<h1 style='text-align:center'>Performance of $pNAME with teammates who played more than 20 times, or more than 10% of the total.</h1>";
				$html .= "<br>";

				// Start a new table for this game
				$html .= "<hr>";
				$html .= "<div class='table-container'>" ;
				$html .= "<table data-text-schema='teammate-detail'>";

				# header 
				$html .= "<thead>";
				# row 1
				$html .= 
					"<tr style='vertical-align:bottom'>
						<th></th>
						<th>Mor</th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
						<th style='text-align:left'>OvrlRnk<sup>1</sup> A</th>
						<th style='text-align:left'>OvrlRating<sup>3</sup> A</th>
					</tr>";
				# row 2
				$html .= 
					"<tr style='vertical-align:bottom'>
						<th></th>
						<th>Aft</th>
						<th></th>
						<th></th>
						<th></th>
						<th>Scoring</th>
						<th></th>
						<th></th>
						<th>Team</th>
						<th>Team</th>
						<th>Team</th>
						<th>Team</th>
						<th></th>
						<th>OvrlRnk B</th>
						<th>OvrlRating B</th>
					</tr>";
				# row 3
				$html .= 
					"<tr style='vertical-align:bottom'>
						<th>YYMMDD</th>
						<th>Eve</th>
						<th>Sctn</th>
						<th>club</th>
						<th>Score%</th>
						<th>method</th>
						<th>movement</th>
						<th>rounds</th>
						<th>mate1</th>
						<th>mate2</th>
						<th>mate3</th>
						<th>mate4</th>
						<th>Flt</th>
						<th style='text-align:right'>OvrlRnk C</th>
						<th style='text-align:right'>OvrlRating C</th>
					</tr>";
				// end head
				$html .= "</thead>";
				// begin body
				$html .= "<tbody>";

				foreach ($query4arows as $r) {

					// Process this row
					$count++ ;
					$total_pct +=   	$r['Pct'];

					$total_ovRtgA +=  $r['Ov_Rtg_A'] ;		
					$total_ovPopA +=  $r['pop_A'] ; 	

					$total_ovRtgB +=  $r['Ov_Rtg_B'] ;	
					$total_ovPopB +=  $r['pop_B'] ;	

					$total_ovRtgC +=  $r['Ov_Rtg_C'] ;
					$total_ovPopC +=  $r['pop_C'] ;

					$GameId=        $r['GameId'];
					$yymmdd= substr($r['GameId'],0,6); 
					$MAE= 	 strtoupper(trim(substr($r['GameId'],6,1)));
					$section=strtoupper(substr($r['GameId'],7,1));
					$club=   				$r['club'];
					if (preg_match ("/Corvallis Dupl/i", $club)) {
						$club = 'CDBC';
					} elseif (preg_match ("/Albany/i", $club)) {
						$club = 'Albany';
					} elseif (preg_match ("/New Deal/i", $club)) {
						$club = 'NewDeal';
					} elseif (preg_match ("/Corvallis DBC/i", $club)) {
						$club = 'CorvOnLine';
					} elseif (preg_match ("/valley/i", $club) or
										preg_match ("/HOV/i", $club) or 
										preg_match ("/477/", $club)) {
						$club = 'HOV'; }

					// convert frac Ranking 2.5 => "2/3"
					// convert null to "-" 
					$ovRnkA=  fractionalRank($r['Ov_Rnk_A']) ;
					$ovRnkB=  fractionalRank($r['Ov_Rnk_B']) ;
					$ovRnkC=  fractionalRank($r['Ov_Rnk_C']) ;
					$ovRnk_string  = $ovRnkA  . " " . dash($ovRnkB)  . " " .dash($ovRnkC)  ;

					// Clarify fractional ratings; add   " of "   between rating and pop
					$ovRtg_string = $r['Ov_Rtg_A'] . " of " . $r['pop_A'] . " " ; // strat A ov rating always present
					$ovRtg_string .= (dash($r['Ov_Rtg_B']) !== "-") ?  $r['Ov_Rtg_B'] . " of " . $r['pop_B'] . " " : " -";
					$ovRtg_string .= (dash($r['Ov_Rtg_C']) !== "-") ?  $r['Ov_Rtg_C'] . " of " . $r['pop_C'] : " -";

					$MAEfull = $MAEValues[$MAE] ?? $MAE;

					$html .= 
						"<tr>
							<td>$yymmdd</td>
							<td>$MAEfull</td>
							<td>$section</td>
							<td>$club</td>";
					if ($r['Pct'] >= 75.00) {
						$html .= "<td class='biggame'><strong>{$r['Pct']}%</strong></td>";
					} elseif ($r['Pct'] >= 60.00) {
						$html .= "<td class='goodgame'><strong>{$r['Pct']}%</strong></td>";
					} else {
						$html .= "<td>{$r['Pct']}%</td>";
					}
					$html .= 
						 "<td>{$r['ScrMethod']}</td>
							<td>{$r['movement']}</td>
							<td>{$r['rounds']}</td>
							<td>{$r['teammate1']}</td>
							<td>{$r['teammate2']}</td>
							<td>{$r['teammate3']}</td>
							<td>{$r['teammate4']}</td>
							<td>{$r['strat']}</td>
							<td>$ovRnk_string</td>
							<td>$ovRtg_string</td>
						</tr>";
				}; // foreach rows

				// compute averages (your existing code)
				$avg_pct       = sprintf("%8.1f", $total_pct / $count) . "%";
				$avg_ovRtgA    = $total_ovPopA > 0 ? sprintf("%8.1f", 100 * $total_ovRtgA / $total_ovPopA) : '-';
				$avg_ovRtgB    = $total_ovPopB > 0 ? sprintf("%8.1f", 100 * $total_ovRtgB / $total_ovPopB) : '-';
				$avg_ovRtgC    = $total_ovPopC > 0 ? sprintf("%8.1f", 100 * $total_ovRtgC / $total_ovPopC) : '-';
				// $avg_ovRtg_string = $avg_ovRtgA . "% " . $avg_ovRtgB . "% " . $avg_ovRtgC . "%";
				// prettified
				$avg_ovRtg_string = implode(' ', [
															fmt_pct($avg_ovRtgA), 
															fmt_pct($avg_ovRtgB), 
															fmt_pct($avg_ovRtgC),
														]);

				// emit footer HTML (your existing code)
				$html .= "</tbody>";
				$html .= 
					"<tfoot>
						<tr>
							<td>Ave:</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>$avg_pct</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>-</td>
							<td>$avg_ovRtg_string</td>
						</tr>
					</tfoot>";

				$html .= "</table>";
				$html .= "</div>";      // table-container


				$results['teamGame_details'] = $html ;

				if ($send && $email !== '') {
					$plain = html_to_text_report($html);
					$subj = "$pNAME's teammate details";
					senddata($plain, $email, $subj);
				}

				$results['teamGame_details'] .= "<p><a href='#top'>Return to top</a></p>";

				timer_stop(
					$timers,
					'teammate_details',
					$logfile,
					'teammate detail calculations'
				);


				// --------------------------------------------------------
				// Query4b Summary of teammates' performance
				// --------------------------------------------------------

				$DateTime = date("Y-m-d H:i:s");

				fwrite(
					$logfile,
					"\n$DateTime\n\tStart teammate summary aggregation $period for $pNAME\n"
				);

				timer_start($timers, 'teammates_summary');

				$query4brows = get_teammates_summary($conn, $pnum, $gmqualifier) ;

				// should never get here if there were no pair games found in query4a !!
				// ... but just in case ...
				if (count($query4brows)== 0) {
					$results['teammates_summary'] = "<p>No team games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
					// return; do not use return.  It exits controller.
				} else { // build the summary tables 

					$html = "<hr><h1 style='text-align:center'>Sorted Performance of teammates of $pNAME</h1>";
					$html .= "<div class='block500pxHigh'>"; 

					$html .= "<br>";

					$html .= "<hr>";

					# frozen header
					$html .= "<div class='table-container'>" ;
					$html .= "<table data-text-schema='teammates-summary'>";

					# header 
					$html .= "<thead>";
					$html .= 
						"<tr style='vertical-align:bottom'>
							<th data-sort='text'>Partner</th>
							<th data-sort='num'>Games Together</th>
							<th data-sort='num'>Ave_Scr%</th>
							<th data-sort='num'>Av_Ov A rtng%</th>
							<th data-sort='num'>Av_Ov B rtng%</th>
							<th data-sort='num'>Av_Ov C rtng%</th>
						</tr>";

						// end header
						$html .= "</thead>";
						// begin body
						$html .= "<tbody>";

					foreach ($query4brows as $r) {
						$OavA= (dash($r['avgRtngA']) !== '-') ? $r['avgRtngA']."%" : '-' ; 
						$OavB= (dash($r['avgRtngB']) !== '-') ? $r['avgRtngB']."%" : '-' ; 
						$OavC= (dash($r['avgRtngC']) !== '-') ? $r['avgRtngC']."%" : '-' ; 

						$html .= 
							"<tr>
								<td>{$r['teammate_name']}</td>
								<td>{$r['games_together']}</td>
								<td>{$r['avg_Score']}%</td>
								<td>$OavA</td>
								<td>$OavB</td>
								<td>$OavC</td>
							</tr>";
					}

					$html .= "</tbody>";
					$html .= "</table>";
					$html .= "</div>";      // table-container

					$html .= "<hr>";
					$html .= "<p><a href='#top'>Return to top</a></p>";

					$results['teammates_summary'] = $html ;

					if ($send && $email !== '') {
							$plain = html_to_text_report($html);
							$subj = "$pNAME's teammates summary";
							senddata($plain, $email, $subj);
					}

					timer_stop(
						$timers,
						'teammates_summary',
						$logfile,
						'teammate summary aggregation'
					);

				} // query4b
			} // query4a
		} // query4a & b

  	// --------------------------------------------------------
		// Query5 Create Excel Spreadsheet of my data + charts
		//
		// Acquire info about all deals played by $pnum over a time period;
		// Create an Excel ss with tabs containing Trend Charts; Correlation charts; 
		// each db table, plus a top-level Executive Summary
		//
  	// --------------------------------------------------------
		if ($query5) { 
			$results['spreadsheet'] = '' ;

			$DateTime = date("Y-m-d H:i:s");

			fwrite(
				$logfile,
				"\n$DateTime\n\tStart spreadsheet generation $period for $pNAME\n"
			);

			timer_start($timers, 'spreadsheet');

			$filename = make_spreadsheet($conn, $pnum, $gmqualifier, $logfile);	
			if (!$filename) {
					$results['spreadsheet'] =
							"<p>No games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
			} else {
					$results['spreadsheet'] =
							'<a href="' . $filename . '">Excel Wrkbk with Trend charts</a><br>';
			}
			
			timer_stop(
				$timers,
				'spreadsheet',
				$logfile,
				'spreadsheet generation'
			);

		}

  	// --------------------------------------------------------
		// Query6 HCP calculation
  	// --------------------------------------------------------
		if ($query6) { 
			$results['HCP'] = '';

			$DateTime = date("Y-m-d H:i:s");

			fwrite(
				$logfile,
				"\n$DateTime\n\tStart HCP tabulation $period for $pNAME\n"
			);

			timer_start($timers, 'HCP');

			$html = "<hr><h1 style='text-align:center'>History of average session HCP for $pNAME $period</h1>";
			$html .= "<div class='block500pxHigh'>"; 

  		$html .= "<br>";

  		$html .= "<hr>";

			# frozen header
			$html .= "<div class='table-container'>" ;
		  $html .= "<table data-text-schema='hcp-detail'>";

			# header 
			$html .= "<thead>";
			$html .= 
				"<tr style='vertical-align:bottom'>
					<th>GameDate (YYMMDD+MAE+Section)</th>
					<th>Club</th>
					<th>My ave HCP / bd</th>
					<th>Pard ave HCP / bd</th>
					<th>#Bds Played</th>
					<th style='text-align:left;'>My Seat & Bd list</th>
				</tr>";

			// end header
			$html .= "</thead>";

			$t0 = hrtime(true);

			$games = get_HCP_by_board($conn, $pnum, $gmqualifier, $logfile);

			$t1 = hrtime(true);

			fwrite(
					$logfile,
					sprintf(
							"\t%.6f s inside get_HCP_by_board()\n",
							($t1 - $t0) / 1e9
					)
			);

			if (count($games)== 0) {
				$results['HCP'] = "<p>No games found for " . htmlspecialchars(playername($pnum, $conn)) . " in this time frame.</p>";
			} else { // build the tables 

				// begin body
				$html .= "<tbody>";

				$mysumHCP = $pardsumHCP = $sumHCP = $sumBdsPlayed = 0; // declare & initialize

				foreach ($games as $gmid => $game) {
					$boards = $game['boards'];
				
					$mytotalHCP 	= array_sum(array_column($games[$gmid]['boards'], 'myhcp'));
					$pardtotalHCP = array_sum(array_column($games[$gmid]['boards'], 'pardhcp'));
					if ($mytotalHCP == 0) continue; # skip games without hands (missing $deal's)
					$bdsPlayed 		= count($boards);
					$myavgHCP 		= $mytotalHCP 	/ $bdsPlayed;
					$pardavgHCP 	= $pardtotalHCP / $bdsPlayed;
					$myavgHCP 		= sprintf("%8.2f",$mytotalHCP / $bdsPlayed); 
					$pardavgHCP 	= sprintf("%8.2f",$pardtotalHCP / $bdsPlayed); 

					// group by seat
					$boardsBySeat = [];
					foreach ($games[$gmid]['boards'] as $bd => $info) {
						$boardsBySeat[$info['myseat']][] = $bd;
					}

					// format seat summary
					$seatSummary = [];
					foreach ($boardsBySeat as $myseat => $bds) {
							$seatSummary[] = "sitting $myseat: " . compressRanges($bds);
					}

					$seatBdList = implode(' ', $seatSummary);
					$club = $games[$gmid]['club'] ;

					$html .= 
						"<tr>
							<td>$gmid</td>
							<td>$club</td>";
					if ($myavgHCP >= 12.0) {
						$html .= "<td class='greathand'><strong>$myavgHCP</strong></td>";
					} elseif ($myavgHCP >= 11.0) {
						$html .= "<td class='goodhand'><strong>$myavgHCP</strong></td>";
					} elseif ($myavgHCP <= 8.0) {
						$html .= "<td class='badhand'><strong>$myavgHCP</strong></td>";
					} elseif ($myavgHCP <= 9.0) {
						$html .= "<td class='weakhand'><strong>$myavgHCP</strong></td>";
					} else {
						$html .= "<td>$myavgHCP</td>";
					}
					if ($pardavgHCP >= 12.0) {
						$html .= "<td class='greathand'><strong>$pardavgHCP</strong></td>";
					} elseif ($pardavgHCP >= 11.0) {
						$html .= "<td class='goodhand'><strong>$pardavgHCP</strong></td>";
					} elseif ($pardavgHCP <= 8.0) {
						$html .= "<td class='badhand'><strong>$pardavgHCP</strong></td>";
					} elseif ($pardavgHCP <= 9.0) {
						$html .= "<td class='weakhand'><strong>$pardavgHCP</strong></td>";
					} else {
						$html .= "<td>$pardavgHCP</td>";
					}
					$html .= 
							"<td>$bdsPlayed</td>
							<td style='text-align:left;'>$seatBdList</td>
						</tr>";

					// accumulate total HCP for each game, displayed in footer summary
					$mysumHCP       	+= $mytotalHCP;
					$pardsumHCP       += $pardtotalHCP;
					$sumBdsPlayed 		+= $bdsPlayed;
				} // foreach games

				$html .= "</tbody>";

				$gmsPlayed 		 = count($games[$gmid]);
				$myavgavgHCP 	 = sprintf("%8.2f", $mysumHCP 	/ $sumBdsPlayed) ;
				$pardavgavgHCP = sprintf("%8.2f", $pardsumHCP / $sumBdsPlayed) ;

				$html .= "<tfoot>
					<tr>
						<th></th>
						<th></th>";
					if ($myavgavgHCP >= 10.10) {
						$html .= "<th class='AboveAvghand'><strong>Period average = $myavgavgHCP</strong></th>";
					} elseif ($myavgavgHCP <= 9.90) {
						$html .= "<th class='BelowAvghand'><strong>Period average = $myavgavgHCP</strong></th>";
					} else {
						$html .= "<th>Period average = $myavgavgHCP</th>";
					}
					if ($pardavgavgHCP >= 10.10) {
						$html .= "<th class='AboveAvghand'><strong>Period average = $pardavgavgHCP</strong></th>";
					} elseif ($pardavgavgHCP<= 9.90) {
						$html .= "<th class='BelowAvghand'><strong>Period average = $pardavgavgHCP</strong></th>";
					} else {
						$html .= "<th>Period average = $pardavgavgHCP</th>";
					}
				$html .= 
					 "<th></th>
						<th></th>
					</tr>";
				$html .= "</tfoot>";
				$html .= "</table>";
				$html .= "</div>";      // table-container
				
				$html .= "<p>N.B. Some Howell movements require players to change direction from NS to EW.  These calculations assume players from each pair occupy specifically N & E seats, and S & W seats.  If you do not adhere to this guideline, then the ave HCP values are incorrect.<br></p>";


				$results['HCP'] = $html ;

				if ($send && $email !== '') {
					$plain = html_to_text_report($html);
					$subj = "$pNAME? & pard HCP tabulation";
					senddata($plain, $email, $subj);
				}

				$results['HCP'] .= "<p><a href='#top'>Return to top</a></p>";

				timer_stop(
					$timers,
					'HCP',
					$logfile,
					'HCP tabulation',
				);
			}
		} # query6
	} # pnum != ''
} #POST

// ------------------------------------------------------------
// 8. Include template
// ------------------------------------------------------------
include 'index.php';


// ------------------------------------------------------------
// Helper: test_input()
// ------------------------------------------------------------
function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

// ------------------------------------------------------------
// Helper: normalize_pnum()
// ------------------------------------------------------------
function normalize_pnum($query) {
    $query = trim($query);

    if (preg_match('/^#[0-9]+$/', $query)) return $query;

    if (preg_match('/^[I-R][0-9]{6}$/i', $query)) {
			$query = ucfirst($query); # p889224 -> P889224
			$first = substr($query, 0, 1);
			$digit = chr(ord($first) - 25);
			return $digit . substr($query, 1);
    }

    if (preg_match('/^[0-9]{7}$/', $query)) return $query;

    return null;
}

// ------------------------------------------------------------
// Helper: GameId_qualifier()
// ------------------------------------------------------------
function GameId_qualifier($startDate, $stopDate) {
    $today = date('Y-m-d');

    $gmStart = ($startDate !== '2010-08-08')
        ? substr($startDate, 2, 2) . substr($startDate, 5, 2) . substr($startDate, 8, 2) . '00'
        : '100808' . '00' ;

    $gmStop = ($stopDate !== $today)
        ? substr($stopDate, 2, 2) . substr($stopDate, 5, 2) . substr($stopDate, 8, 2) . 'ZZ'
        : substr($today, 2, 2) . substr($today, 5, 2) . substr($today, 8, 2) . 'ZZ';

    return "GameId BETWEEN '$gmStart' AND '$gmStop'";
}

// ------------------------------------------------------------
// Helper: convert null to dash for null rank and rating values
// ------------------------------------------------------------
function dash($v) {
    return ($v === null || $v === '') ? '-' : $v;
}

// ------------------------------------------------------------
// Helper: convert fractional rank value to fractional rank strings
// e.g. 2.5 => "2/3"
// ------------------------------------------------------------
function fractionalRank($value) {
    if (fmod($value, 1) == 0.5) {
        $whole = floor($value);
        return $whole . "/" . ($whole + 1);
    }
    return (string)$value;
}

// ------------------------------------------------------------
// Helper: compress ranges like “4–9,13–15,19–24”
// ------------------------------------------------------------
function compressRanges(array $nums) {
    sort($nums);
    $ranges = [];
    $start = $prev = array_shift($nums);

    foreach ($nums as $n) {
        if ($n == $prev + 1) {
            $prev = $n;
            continue;
        }
        $ranges[] = ($start == $prev) ? "$start" : "$start-$prev";
        $start = $prev = $n;
    }

    $ranges[] = ($start == $prev) ? "$start" : "$start-$prev";
    return implode(',', $ranges);
}

?>
