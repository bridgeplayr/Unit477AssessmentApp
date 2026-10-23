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


// function make_spreadsheet($pnum, $HCP) {
function make_spreadsheet($pnum, $conn, $gmqualifier) {

  // global $startDate, $stopDate, $conn ;
	// $gmqualifier = GameId_qualifier($startDate, $stopDate) ;

  // Check connection
  if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
  }
# globals from make_spreadsheet(); 
	global $pnum, $played_count, $gm_cnt,
  $Declarer, $nsscore, $sitting, $NSmpt, $EWmpt, $SScol,
  $player_declaring_count, $mpts_by_player_declaring,
  $pard_declaring_count, $mpts_by_pard_declaring,
  $defending_count, $mpts_by_defending, 
  $PO_count, $mpts_from_PO, $PO_judgment,
  $declaring_count, $dataArray; 

# Tally the mtchpts from POs, player-declaring, pard-declaring, & defending for each board played
  global $Tot_mpts ;

	$currentgmid = $gmid = '' ;
	$currentclub = $club = ''; 
  $currentTop  = $top  = 0;
	
	$gm_cnt = 1;
  $pairId = '';
  $SScol = $SS = $SSrow = array();
	$played_count = 0;

  # array of rows 
  $dataArray = array();
  
	# get the results for $pnum, then loop thru all that $player's 
	# results (for pair games only) stored in the "results" query 

	# retrieves one row per GameId - will include some team games which you don't need plus
	# some BAM games, which you definitely need
#	"SELECT GameId,club,player_pair_team,player1,pnum1,player2,pnum2,player3,pnum3,player4,pnum4,Flt,Pct,Score,Award1,Color1,Award2,Color2,Reason,Rnk1S,Rnk2S,Rnk3S,Rnk1O,Rnk2O,Rnk3O,Adjust" .
	$PlayerFinish_sql = 
		"SELECT * FROM players WHERE $gmqualifier AND " .
		"	(pnum1='$pnum' OR pnum2='$pnum' OR pnum3='$pnum' or pnum4='$pnum') ORDER BY GameId;" ;
	$PFresults = mysqli_query($conn, $PlayerFinish_sql );
	if (mysqli_num_rows($PFresults) == 0) {
		echo "$PlayerFinish_sql sql found no records<br>";
		echo "$PlayerFinish_sql <br>";
	}

	# retrieves an average of 25 rows per pairs game (!)

	$BoardScores_sql = 
		"SELECT GameId,nsscore,club,board_id,nsmtchpts as NSmpt,ewmtchpts as EWmpt, nspair as NS,npnum,spnum, ewpair as EW, epnum,wpnum,round,Declarer as dclr,ETF,top" .
		" FROM results  WHERE $gmqualifier AND (npnum='$pnum' OR spnum='$pnum' OR epnum='$pnum' OR wpnum='$pnum') ORDER BY GameId;";
	#		WAS: (added ETF)
#		"SELECT GameId,nsscore,club,board_id,nsmtchpts as NSmpt,ewmtchpts as EWmpt, nspair as NS,npnum,spnum, ewpair as EW, epnum,wpnum,round,Declarer as dclr,top" .
#		" FROM results  WHERE $gmqualifier AND (npnum='$pnum' OR spnum='$pnum' OR epnum='$pnum' OR wpnum='$pnum') ORDER BY GameId;";
	$BdScoreresults = mysqli_query($conn, $BoardScores_sql);
	if (mysqli_num_rows($BdScoreresults) == 0) {
		echo 'BdScoreresults sql found no records<br>';
		echo "$BoardScores_sql<br>";
	}
			
  # initialize and establish scope
  $bdlist = $Declarer = $nsscore = $sitting = $NSmpt = $EWmpt = array();
  $mpts_from_PO = $mpts_by_player_declaring = $mpts_by_pard_declaring = $mpts_by_defending = $Tot_mpts = 0;
  $player_declaring_count   = $pard_declaring_count   = $declaring_count = $defending_count = 0;
  $PO_count     = $PO_judgment = $adjust = 0;
	global $isBAM ;

	# get a row of Board results from the BoardScores query; 
	# 	If this is not a new GameId+new club
	# 		accumulate the BdScores for this GameId 
	# 	else 
	# 		get the GameFinish info for the now-finished GameId
	# 		write the last game's record to the SS; 
	# 		reinitialize the BdScore variables;
	# 	endif
	# next 
	
	if (mysqli_num_rows($BdScoreresults) > 0) {
		# set the first row of results as the "current" GameId/club
		$firstBdrow  = mysqli_fetch_assoc($BdScoreresults) ;
		$currentgmid = $firstBdrow['GameId']; 
		$currentclub = $firstBdrow['club'];
    $currentTop  = $firstBdrow['top'];

		# debug
		# echo "<br>starting BD GameId $currentgmid, $currentclub<br>";

		mysqli_data_seek($BdScoreresults,0); # reset the result pointer

// ------------------ Loop through the Board scores for each GameId for player ------------------ 
		
    while ($Bdrow = mysqli_fetch_assoc($BdScoreresults)) {
		  $gmid = $Bdrow['GameId'];
		  $club = $Bdrow['club'];
			
      # Flag for BAM messiness
			$isBAM = ($currentgmid == '150526EA') ? 1 : 0;

		  # if this is just another Bdrow of board data from currentgmid/club, 
		  # continue to acculate the Bddata in arrays indexed by $i instead of by 
		  # $board_id, because player likely didn't play boards in order
		
		  if ($gmid == $currentgmid and $club == $currentclub ) {
			  stuff_Bdrow_data ($Bdrow) ;
			} 
		  else { # got the first row of next gmid 

			  # $Bdrow now contains data from the first board of the next $gmid & $club
				# Allocate mtchpts from currentgmid/club, and store in dataArray[]
		    # debug
				# echo "allocating mtchpts for $currentgmid, $currentclub<br>";
				allocate_mtchpts($PFresults, $currentgmid, $currentclub, $currentTop); 

		    # debug
        # limit to 10 datapoints
        // if ($gm_cnt>10) {
        //   continue;
        // }
        $gm_cnt++;

		    # Step 4 - reinitialize vars for the next GameId

		    $currentgmid = $Bdrow['GameId']; 
		    $currentclub = $Bdrow['club'];
        $currentTop  = $Bdrow['top'];
				# debug
				# echo "<br>starting next BD GameId $currentgmid, $currentclub<br>";

		    # debug
        # echo "step 4 - Process next GameId: $currentgmid club=$currentclub<br>";

  	    $bdlist = $sitting = $Declarer = $nsscore = $NSmpt = $EWmpt = array();
        $mpts_from_PO = $mpts_by_player_declaring = $mpts_by_pard_declaring = $mpts_by_defending = $Tot_mpts = 0;
        $player_declaring_count = $pard_declaring_count = $declaring_count = $defending_count = 0;
        $PO_count = $PO_judgment = $adjust = $played_count = 0;
				
        # Flag for BAM messiness
			  $isBAM = ($currentgmid == '150526EA') ? 1 : 0;

				# Having written the last summarized GameId results to $dataArray, we 
				# can now stuff the first board data from this new GameId into BdInfo arrays
				
        stuff_Bdrow_data ($Bdrow) ;

      } # else, (end of current GameId, beginning of next GameId)
		} # while $Bdrow

		# $Bdrow now empty; allocate mtchpts from this final gmid/club, and store in dataArray[]
		allocate_mtchpts($PFresults, $currentgmid, $currentclub, $currentTop); 

  	# new abbreviated XLSS header 9/22/2022 - removed Good... measures
  	# XL date, Game Date, GameId, Club, Pard, DeclScr, PardScr, DefScr, GameScr, Finish, Award, %Declaring, ", "#PO, %POScr

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
		# debug
    # echo "<br>Wrote SS: last GameId: $currentgmid club=$currentclub<br>";

    # autoformat the columns in 'Data' sheet
    $Datasheet = $Wrkbk->getSheetByName('Data');
    foreach (range('A', 'S') as $col) {
      $Datasheet->getColumnDimension($col)->setAutoSize(true);  
    }

    $dateMinMax = playDateRange ($gm_cnt, $Wrkbk) ; // array
    // measure the time span of games played.  change chart tick marks to match
    $nQtrs = sprintf("%3.2f",  (($dateMinMax['max'] - $dateMinMax['min'])/30.5)/4 ) ;
    // echo "<br>play spans $nQtrs quarters<br>";

//---------------------------- begin Chart creation

  # create the 'Charts' worksheet
    $Wrkbk->createSheet();
    $Chart_sheet = $Wrkbk->getSheet(1);
    $Chart_sheet->setTitle("Charts");

    $Chart_sheet = $Wrkbk->getSheetByName('Charts');


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
      ('F', $gm_cnt, 'Declarer Score', 'diamond', 7, $fill, $border, 'Your Declarer Score', 'A1', 'S24', $trendLine, $dateMinMax, $Wrkbk); 
  
    $Chart_sheet->addChart($chrt);

    # PardScr
    $fill   = setClrProps ('FF0000', null, 'srgbClr'); // red
    $border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
    $chrt =  create_Trend_chart
      ('G', $gm_cnt, 'Pard Score', 'square', 5, $fill, $border, 'Partners Declarer Score', 'A25', 'S48', $trendLine, $dateMinMax, $Wrkbk);
    $Chart_sheet->addChart($chrt);
  
    # DefScr 
    $fill   = setClrProps ('00B050', null, 'srgbClr'); // green
    $border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
    $chrt =  create_Trend_chart
      ('H', $gm_cnt, 'Defense Score', 'triangle', 7, $fill, $border, 'Score when Defending', 'A49', 'S72', $trendLine, $dateMinMax, $Wrkbk); 
    $Chart_sheet->addChart($chrt);
  
    # GameScr 
    $fill   = setClrProps ('000000', null, 'srgbClr'); // black
    $border = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $trendLine  = array (1.0, $fill, 'linear', null, null, true, true); // disp slope
    $chrt =  create_Trend_chart
      ('I', $gm_cnt, 'Game Score', 'circle', 5, $fill, $border, 'Game Score', 'A73', 'S96', $trendLine, $dateMinMax, $Wrkbk);
    $Chart_sheet->addChart($chrt);

    # %Declring
    $fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $border = setClrProps ('7030A0', null, 'srgbClr'); // purple
    $trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
    $chrt =  create_Trend_chart
      ('L', $gm_cnt, 'How often we Declare', 'x', 5, $fill, $border, '%Declaring', 'A97', 'S120', $trendLine, $dateMinMax, $Wrkbk);
    $Chart_sheet->addChart($chrt);


// --------------- Correlation Chart for Game Score% vs. each of the previous 4 score paramaters -----------------------------------

// Set the X-Axis Labels  - GameScr, PardScr, DefScr, %Declaring
    $dataSeriesLabels = 
    [
       new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$F$1', null, 1), // DeclScr
       new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$G$1', null, 1), // PardScr
       new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$H$1', null, 1), // DefScr
       new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Data!$L$1', null, 1), // %Declring
    ];

    $xAxisTickValues = 
    [
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$F$2:$F$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$G$2:$G$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$H$2:$H$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$L$2:$L$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt),
    ];

// Set the same Y-Axis for each of the above
    $dataSeriesValues = 
    [
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$I$2:$I$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'diamond', null, 7),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$I$2:$I$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'square', null, 5),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$I$2:$I$'.($gm_cnt+1), 
       Properties::FORMAT_CODE_NUMBER, $gm_cnt, null, 'triangle', null, 7),
      new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$I$2:$I$'.($gm_cnt+1), 
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
  
    $cell = 'F1'; // cell containing 'DeclScr'
    $DeclrTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
    $dataSeriesValues[0]->setTrendLines($DeclrtrendLines);
  
    $cell = 'G1'; // cell containing 'PardScr'
    $PardTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
    $dataSeriesValues[1]->setTrendLines($PardtrendLines);
  
    $cell = 'H1'; // cell containing 'DefScr'
    $DefTrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";
    $dataSeriesValues[2]->setTrendLines($DeftrendLines);
  
    // WAS $cell = 'P1'; // cell containing '% Declaring'
    $cell = 'L1'; // cell containing '% Declaring'
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

		// 
		// 
		// HCP trend and correlation charts.  HCP differs from the
		// previous set in that it is not a % value - rather
		// it is a float with an average of 10 and a range +- 4.
		// 
		// 
    # aveHCP trend chart - different y-axis from above 4 charts!!!
    $fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $border = setClrProps ('7030A0', null, 'srgbClr'); // purple
    $trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
    $chrt =  create_Trend_chart
      ('O', $gm_cnt, 'Session aveHCP', 'plus', 5, $fill, $border, 'Average HCP in a session', 'A145', 'S168', $trendLine, $dateMinMax, $Wrkbk);
    $Chart_sheet->addChart($chrt);

    # aveHCP correlation chart - different y-axis from above 4 charts!!!
    $fill   = setClrProps ('FFFFFF', null, 'srgbClr'); // white
    $border = setClrProps ('7030A0', null, 'srgbClr'); // purple
    $trendLine  = array (1.0, $border, 'linear', null, null, true, true); // disp slope
    $chrt =  create_HCP_correlation_chart 
		 	('O', $gm_cnt, 'Game Score %', 'plus', 5, $fill, $border, 'Game Score %', 'A169', 'S192', $trendLine, null, $Wrkbk);
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

    echo '<a href="' . $filename . '">Excel Wrkbk with Trend charts</a><br>';
	}  # if num_results > 0
	else {
		echo 'BdScoreresults sql found no records<br>';
	}
}

// 
//
// create the HCP Correlation chart
//
//
function create_HCP_correlation_chart (
    $column, $nrows, $metric, $marker, $marker_size, $fill, $border, $yLabel, $topleftcell, $bottomrightcell, $trendLine, $dateMinMax, $Wrkbk) 
{

  	$TrendLineLabel = 'Correlation between ave HCP and Game Score %' ;
  $Datasheet = $Wrkbk->getSheetByName('Data');
  // use this one for all dataseries values
  $trendLines = [ 
    new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false),
  ] ;

// Set the Labels (Column header) for each data series we want to plot
  $cell = $column.'1'; // cell containing data label - aveHCP
  $TrendLineLabel = $Datasheet->getCell($cell)->getValue() . " Correlation";

// Set the X-Axis Label  -  "aveHCP"
  $dataSeriesLabels = 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$O$1', null, 1), // aveHCP
  ];

// Set the X-coordinate = each session's aveHCP
  $xAxisTickValues = 
	[ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$O$2:$O$'.($nrows+1), 
		 Properties::FORMAT_CODE_NUMBER, $nrows), 
  ];

// Set the Y-coordinate = each session's GameScr
  $dataSeriesValues = 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$I$2:$I$'.($nrows+1), 
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

	$title = new Title("Correlation between ave HCP and Game Score %") ;

  $xAxisLabel = new Title('ave HCP');
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

function playDateRange ($nrows, $Wrkbk) {
  $Datasheet = $Wrkbk->getSheetByName('Data');

  // start the xaxis at the beginning of the quarter of the first play date
  $startDateStr = $Datasheet->getCell('B2')->getValue(); // mm/dd/yyyy date string
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
  $lastDateStr   = $Datasheet->getCellByColumnAndRow(2, $nrows+1)->getValue();
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

function excelDateValue($datestr) {
  $dayOne       = date_create_from_format('m/d/Y', "1/1/1900");
  $dateObj      = date_create_from_format('m/d/Y', $datestr); 
  $daysAfterOne = date_diff($dayOne, $dateObj);
  $excelDateVal = $daysAfterOne->format("%a");
  return 2+$excelDateVal; // at least according to php instead of phpss
}

function setClrProps ($value, ?int $alpha, ?string $type): array
{
   $cl = array('value' => $value, 
               'alpha' => $alpha,
               'type'  => $type,
                );

   return $cl;
}

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
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$A$2:$A$'.($nrows+1), Properties::FORMAT_CODE_DATE, $nrows), 
  ];
  // since now using LineChart instead of ScatterChart, 
  // x-axis values are an Excel numeric representation of the date - so 
  // formatCode=General for the xAxis VALUES, but we want the labels to be 
  // displayed as 'm/d/yyyy' 

// Set the Data values for each data series we want to plot
  $dataSeriesValues = 
  [ new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Data!$'.$column.'$2:$'.$column.'$'.($nrows+1), 
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
      $dateMinMax['max'], // maximum calculate this from the last data:     'Data!$A$'.($nrows+1)
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
global $defending_count, $Tot_mpts, $mpts_by_defending;

  $defending_count++;            $Tot_mpts += $NSmpt[$i];
  $mpts_by_defending         += $NSmpt[$i];
  return ;
}

function EWdefending ($i, $top) {
global $EWmpt ;
global $defending_count, $Tot_mpts, $mpts_by_defending ;

  $defending_count++;            $Tot_mpts += $EWmpt[$i];
  $mpts_by_defending         += $EWmpt[$i];
  return ;
}

function NSbothDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $player_declaring_count, $pard_declaring_count;
global $Tot_mpts, $mpts_by_player_declaring, $mpts_by_pard_declaring; 

  $declaring_count++;            $Tot_mpts += $NSmpt[$i];
  $mpts_by_player_declaring += $NSmpt[$i]; $player_declaring_count++;
  $mpts_by_pard_declaring   += $NSmpt[$i]; $pard_declaring_count++;
  return ;
}

function EWbothDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $player_declaring_count, $pard_declaring_count;
global $Tot_mpts, $mpts_by_player_declaring, $mpts_by_pard_declaring; 

  $declaring_count++;            $Tot_mpts += $EWmpt[$i];
  $mpts_by_player_declaring += $EWmpt[$i]; $player_declaring_count++;
  $mpts_by_pard_declaring   += $EWmpt[$i]; $pard_declaring_count++;
  return ;
}

function EorWPardDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $pard_declaring_count;
global $Tot_mpts, $mpts_by_pard_declaring; 

  $declaring_count++;            $Tot_mpts += $EWmpt[$i];
  $mpts_by_pard_declaring += $EWmpt[$i]; $pard_declaring_count++;
  return ;
}

function EorWPlayerDeclaring ($i, $top) {
global $EWmpt ;
global $declaring_count, $player_declaring_count;
global $Tot_mpts, $mpts_by_player_declaring; 

  $declaring_count++;            $Tot_mpts += $EWmpt[$i];
  $mpts_by_player_declaring += $EWmpt[$i]; $player_declaring_count++;
  return ;
}

function NorSPardDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $pard_declaring_count;
global $Tot_mpts, $mpts_by_pard_declaring; 

  $declaring_count++;            $Tot_mpts += $NSmpt[$i];
  $mpts_by_pard_declaring += $NSmpt[$i]; $pard_declaring_count++;
  return ;
}

function NorSPlayerDeclaring ($i, $top) {
global $NSmpt;
global $declaring_count, $player_declaring_count;
global $Tot_mpts, $mpts_by_player_declaring; 

  $declaring_count++;            $Tot_mpts += $NSmpt[$i];
  $mpts_by_player_declaring += $NSmpt[$i]; $player_declaring_count++;
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

function stuff_Bdrow_data ($Bdrow) {

global $pnum, $played_count, $gm_cnt, $bdlist,
  $Declarer, $nsscore, $sitting, $NSmpt, $EWmpt, $SScol,
  $player_declaring_count, $mpts_by_player_declaring,
  $pard_declaring_count, $mpts_by_pard_declaring,
  $defending_count, $mpts_by_defending, 
  $PO_count, $mpts_from_PO, $PO_judgment,
  $declaring_count, $dataArray; 

global $isBAM ;			

  $played_count++; # count the boards played, starting with 1, not zero

	# Index of these arrays is 'ith board', NOT the board_id; (players seldom 
	# get to play boards sequentially - except with Barometer movements or 
	# Mitchell movement NS pair 1); 
	# Use $board_id later - it is the last board in play
  $board_id            = $Bdrow['board_id'] ; # highest board played after final loop
			
  array_push ($bdlist,   $board_id );      # contains the list of boards played
  array_push ($NSmpt,    $Bdrow['NSmpt']) ; 

	# accomodate BAMscoring: EWmpt==0  NSmpt=1=win; NSmpt=0.5=tie; NSmpt=0=loss; 
	# So assign EWmpt = 1-NSmpt, then proceed as normal, sort of.
  if ($isBAM) {
    array_push ($EWmpt, 1.0 - $Bdrow['NSmpt']) ; 
	} else {
    array_push ($EWmpt, $Bdrow['EWmpt']) ; 
	}

  array_push ($Declarer, trim($Bdrow['dclr'])) ;  # if blank for all boards, no Bridgemate; lots of guesswork to do
  array_push ($nsscore,  $Bdrow['nsscore']) ; # check nsscore==0 for PO, or the guesswork if != 0 & dclr=''

	$npnum               = $Bdrow['npnum'] ;
	$spnum               = $Bdrow['spnum'] ;
	$epnum               = $Bdrow['epnum'] ;
	$wpnum               = $Bdrow['wpnum'] ;
	if       ($pnum == $npnum) {
    array_push ($sitting, 'N') ;
	} elseif ($pnum == $spnum) {
    array_push ($sitting, 'S') ;
	} elseif ($pnum == $epnum) {
    array_push ($sitting, 'E') ;
	} elseif ($pnum == $wpnum) {
    array_push ($sitting, 'W') ;
	} else {
    array_push ($sitting, '?') ; # should never happen
	}

	return;
}

function allocate_mtchpts ($PFresults, $currentgmid, $currentclub, $currentTop) {

global $pnum, $played_count, $gm_cnt, $bdlist,
  $Declarer, $nsscore, $sitting, $NSmpt, $EWmpt, $SScol,
  $player_declaring_count, $mpts_by_player_declaring,
  $pard_declaring_count, $mpts_by_pard_declaring,
  $defending_count, $mpts_by_defending, 
  $PO_count, $mpts_from_PO, $PO_judgment,
  $declaring_count, $dataArray; 
global $isBAM, $HCP ;

$PFgmid = $PFclub = $DATE = $pnum1 = $pnum2 = $pairid = $adjust = $score = '';
$Pct = 0;
  

// -------- Find the Player Finish data for this GameId then save this GameId info in dataArray 
							
		#	1 - get PFinfo for this current completed GameId
		#	2 - generate $SSrow from summarized Bdinfo in each $SScol[]
		#	3 - write	$SSrow to dataArray
		# 4 - reinitialize vars for this GameId

		while ($PFrow = mysqli_fetch_assoc($PFresults) ) {
			$PFgmid = $PFrow['GameId'] ; 
			$PFclub = $PFrow['club'] ;

			# debug
			# echo "<br>looking for match with PFgmid $PFgmid PFclub $PFclub<br>";

			// is this a row from the currentgmid/club?
			if ($PFgmid == $currentgmid and $PFclub == $currentclub) {

			  # debug
			  # echo "match found with PFgmid $PFgmid PFclub $PFclub<br>";

				#	1 - get PFinfo for the current now-completed GameId
			  $yy =   substr($currentgmid, 0, 2) ;
        $mm =   substr($currentgmid, 2, 2) ;
        $dd =   substr($currentgmid, 4, 2) ;
        $MAE=   substr($currentgmid, 6, 1);
			  $SScol[0] =  $mm . "/" . $dd . "/" . "20" . $yy; # SS Date

    	  $SScol[1] =  $currentgmid ; # SS GameId
				$pnum1    = trim($PFrow['pnum1']) ; # pnum1 - could be $player or pard
        $pnum2    = trim($PFrow['pnum2']) ; # pnum2 - could be $player or pard
        $pairId   = trim($PFrow['player_pair_team']) ; # ('player_pair_team' in "players" table)
        $SScol[2] = trim($PFrow['club']);
        $adjust   = $PFrow['adjust'];
        $SScol[7] = $PFrow['Pct'] ; # SS %Scr - (compare with calc'd final % from matchpoints)
        $SScol[8] = $PFrow['Rnk1O'] ." ". $PFrow['Rnk2O'] ." ". $PFrow['Rnk3O'] . "|" . $PFrow['Rnk1S'] ." ". $PFrow['Rnk2S'] ." ". $PFrow['Rnk3S'] ;
        $SScol[9] = $PFrow['Award1'] ." ". $PFrow['Color1'] ." ".($PFrow['Award2'] > 0 ? " +  ".$PFrow['Award2'] : "")." ".($PFrow['Color2'] ?? "") ; # SS Award; suppress clr2 award if none
        $score    = $PFrow['Score'] ; # for comparison purposes

				# Find Pard
				if ($PFrow['pnum1'] == $pnum ) {
					$pard = $PFrow['player2'] == '' ? 'indy' : $PFrow['player2']  ;
				} elseif ($PFrow['pnum2'] == $pnum) {
					$pard = $PFrow['player1'];
				} elseif ($PFrow['pnum3'] == $pnum) { # BAM
					$pard = $PFrow['player4'];
				} elseif ($PFrow['pnum4'] == $pnum) { # BAM
					$pard = $PFrow['player3'];
				} else {
					$pard = '?';
					echo "unable to identify pard of $pnum for $currentclub $currentgmid;\n";
				}

				# Find teammates too, if BAM
				if ($isBAM) {
					if ($PFrow['pnum1'] == $pnum ) {
						$pard = $PFrow['player2'].'+'.$PFrow['player3'].','. $PFrow['player4'];
					} elseif ($PFrow['pnum2'] == $pnum) {
						$pard = $PFrow['player1'].'+'.$PFrow['player3'].','. $PFrow['player4'];
				  } elseif ($PFrow['pnum3'] == $pnum) { # BAM
						$pard = $PFrow['player4'].'+'.$PFrow['player1'].','. $PFrow['player2'];
				  } elseif ($PFrow['pnum4'] == $pnum) { # BAM
						$pard = $PFrow['player3'].'+'.$PFrow['player1'].','. $PFrow['player2'];
					}
				}

        $SScol[3] = $pard ;

		    # debug
				# echo "Step 1 - Acquiring player finish for gmid $currentgmid pard=$pard club=$currentclub<br>";

				break; # break out of the while PFrow loop if you found a match
  
      } else { # PFrow info didn't match currentgmid
				# echo "<br>PFgmid $PFgmid didnt match currentgmid $currentgmid, try next PFrecord <br>";
				# hope one of the next rows match!
				# if you get of sync here, you could be toast
			}
		}

		#	2 - generate $SSrow from summarized Bdinfo in each $SScol[]
		# debug
		# echo "Step 2 - Allocating mpts into categories<br>";

  #------- allocate mpts into categoeies: player-Declaring, pard-declaring, defending or PO ------------------

		# debug
		# echo "allocating $played_count scores from $currentgmid/$currentclub<br>";

    for ($i=0; $i<$played_count; $i++)  {
      if (       $Declarer[$i] == '' ) {
        # debug
        # echo "No declarer found in game $currentgmid on board $bdlist[$i]";
        # echo "- nsscore=$nsscore[$i] means guesswork looks like a ".offense_defense($nsscore[$i])." score<br>";
        # PO ?
        if ( $nsscore[$i] ==0 ) { # PO
          # debug
          # echo "No declarer found in game $currentgmid on board $bdlist[$i]";
          # echo "- nsscore=0 means PO<br>";
          if (($sitting[$i] == 'N') or ($sitting[$i] == 'S') ) {
            NorSPO ($i); continue ; }
          else { # must be sitting EW
            EorWPO ($i); continue ; }
        } # DRAT - no declarer specified, but nsscore <> 0.  Bridgemate foul up.  Must guess who played the hand.
          # must combine Mitchell & Howell "sitting" cases below, which makes for some very long 'if' stmnts
    
        #---------------------- OFFENSIVE (Declaring) SCORES --------------------------------------------
        
          # NS declared (successfully) : nsscore > 0 and score ~ offensive, & Player sat NS
        elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] > 0 ) and 
                (( $sitting[$i] == 'N') or ($sitting[$i] == 'S') ) ) { # but can't tell who declared, so both get credit/blame
          NSbothDeclaring ($i, $currentTop); continue ; }
          # NS declared (successfully) : nsscore > 0 and score ~ offensive, but Player sat EW
        elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] > 0 ) and 
                (( $sitting[$i] == 'E') or ($sitting[$i] == 'W') ) ) { # defense
          EWdefending ($i, $currentTop); continue ; }
          # EW declared (successfully) : nsscore < 0 and score ~ offensive, & Player sat EW
        elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] < 0 ) and 
                (( $sitting[$i] == 'E') or ($sitting[$i] == 'W') ) ) { # but can't tell who declared, so both get credit/blame
          EWbothDeclaring ($i, $currentTop); continue ; }
          # EW declared (successfully) : nsscore < 0 and score ~ offensive, but Player sat NS
        elseif (( offense_defense($nsscore[$i]) == "OFFENSE") and ($nsscore[$i] < 0 ) and 
                (( $sitting[$i] == 'N') or ($sitting[$i] == 'S') ) ) { # defense
          NSdefending ($i, $currentTop); continue ; }

        #---------------------- DEFENSIVE SCORES --------------------------------------------
        
          # NS defending successfully : nsscore > 0 and score ~ defensive, & Player sat NS
        elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] > 0 ) and 
                (( $sitting[$i] == 'N') or ($sitting[$i] == 'S') ) ) { # defense
          NSdefending ($i, $currentTop); continue ; }
          # NS defending successfully : nsscore > 0 and score ~ defensive, but Player sat EW
        elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] > 0 ) and 
                (( $sitting[$i] == 'E') or ($sitting[$i] == 'W') ) ) { # but can't tell who declared, so both get credit/blame
          EWbothDeclaring ($i, $currentTop); continue ; }
          # EW defending successfully : nsscore < 0 and score ~ defensive, but Player sat NS
        elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] < 0 ) and 
                (( $sitting[$i] == 'N') or ($sitting[$i] == 'S') ) ) { # but can't tell who declared, so both get credit/blame
          NSbothDeclaring ($i, $currentTop); continue ; }
          # EW defending successfully : nsscore < 0 and score ~ defensive, & Player sat EW
        elseif (( offense_defense($nsscore[$i]) == "DEFENSE") and ($nsscore[$i] < 0 ) and 
                (( $sitting[$i] == 'E') or ($sitting[$i] == 'W') ) ) { # defense
          EWdefending ($i, $currentTop); continue ; }
        else {
          # debug
          # echo "Response from offense_defense:", offense_defense($nsscore[$i]),"<br>";
          if ($nsscore[$i] <> 0) {
            # debug
            echo "game $currentgmid, board $i player sitting $sitting[$i] - nsscore=$nsscore[$i], $NSmpt[$i] NSmpts or $EWmpt[$i] EWmpts were not allocated<br>";
          }
        }
      } # end of unknown-declarer-but-not-passout mess
        # $Declarer is specified
        elseif (    $Declarer[$i] != '' ) {
          # player sits N or S
          if (       $sitting[$i] == 'N' ) {
            if (    $Declarer[$i] == 'N') {
              NorSPlayerDeclaring ($i, $currentTop); continue ; } 
            elseif ($Declarer[$i] == 'S') {
              NorSPardDeclaring ($i, $currentTop); continue ; }
            elseif ($Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
              NSdefending ($i, $currentTop); continue ; }
          }
          if (        $sitting[$i] == 'S' ) {
            if (     $Declarer[$i] == 'S') {
              NorSPlayerDeclaring ($i, $currentTop); continue ; } 
            elseif ( $Declarer[$i] == 'N') {
              NorSPardDeclaring ($i, $currentTop); continue ; }
            elseif ( $Declarer[$i] == 'E' or $Declarer[$i] == 'W') {
              NSdefending ($i, $currentTop); continue ; }
          }
          # player sits E or W
          if (        $sitting[$i] == 'E' ) {
            if (     $Declarer[$i] == 'E') {
              EorWPlayerDeclaring ($i, $currentTop); continue ; } 
            elseif ( $Declarer[$i] == 'W') {
              EorWPardDeclaring ($i, $currentTop); continue ; }
            elseif ( $Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
              EWdefending ($i, $currentTop); continue ; }
          }
          if (        $sitting[$i] == 'W' ) {
            if (     $Declarer[$i] == 'W') {
              EorWPlayerDeclaring ($i, $currentTop); continue ; } 
            elseif ( $Declarer[$i] == 'E') {
              EorWPardDeclaring ($i, $currentTop); continue ; }
            elseif ( $Declarer[$i] == 'N' or $Declarer[$i] == 'S') {
              EWdefending ($i, $currentTop); continue ; }
          }
        } # end of known declarer
        else { # should never get here - a previous continue should branch to top of loop
          echo "unexpectedly fell thru board loop for game $currentgmid, board $i, pairid, $pairId<br>";
        }
    } # end of tally-each-played-board loop


    # summarize, ready to stuff into SS
    if ($player_declaring_count > 0 ) { 
      $SScol[4]  = sprintf("%3.2f", 100*$mpts_by_player_declaring / ($currentTop*$player_declaring_count)); # DeclrScr
    }
      else { $SScol[4]  = '' ; } # Excel treats this as a zero
      # else { $SScol[4]  = 'declared none' ; } # Excel treats this as a zero too, but complains
    
    if ($pard_declaring_count > 0 ) { 
      $SScol[5]  = sprintf("%3.2f", 100*$mpts_by_pard_declaring / ($currentTop*$pard_declaring_count)); # PardScr
    }
      else { $SScol[5]  = '' ; } # Excel treats this as a zero
      # else { $SScol[5]  = 'declared none' ; } # Excel treats this as a zero too, but complains

    if ($defending_count > 0 ) { 
      $SScol[6]  = sprintf("%3.2f", 100*$mpts_by_defending / ($currentTop*$defending_count)); # DefScr
    }
      else { $SScol[6]  = 'defended none!' ; }

    if ($PO_count > 0 ) { 
          $PO_judgment = sprintf("%3.2f", 100*$mpts_from_PO/($currentTop*$PO_count) );
    } 
      else { $PO_judgment = ''; }

    # not necessarily same as player_declaring_count + pard_declaring_count for Howell
    $SScol[10] = sprintf("%3.2f", 100*$declaring_count/$played_count);

    $SScol[11] = $PO_count ;
    $SScol[12] = $PO_judgment ;
		# end summarizing mtchpt allocation and SScol stuffing

		$Nbds = $HCP[$currentgmid]['bdsPlayed'] ;
		$aveHCP = sprintf ("%4.2f",($HCP[$currentgmid]['total'] / $Nbds )) ;
		$SScol[13] = $aveHCP ;

    
# Existing Spreadsheet columns:
# XLdate, Game Date, GameId, Club, Pard, DeclScr, PardScr,DefScr,GameScr, Finish, 
#   -1      0          1        2   3      4        5        6       7      8
# Award, %Declaring, #PO, POscr, HCP
#    9      10        11   12    13
# First column is the 'date' in xl integer-date format, produced by xl function 
# DATEVALUE(Bx); player data goes into cols 5-15

		#	3 - write	$SSrow to dataArray
    $SSrow=array(); # start with an empty row
    # col A is the XL function DATEVALUE(cell)
    $datevaluestr = '=DATEVALUE(B' . ($gm_cnt+1) . ')';
    array_push ($SSrow, $datevaluestr); # first col is Excel DATEVALUE, followed by the SScols
    for ($i=0; $i<=13; $i++) {
      # echo "SSrow[$i]: $SSrow[$i]<br>";
      array_push ($SSrow, $SScol[$i] );
    }
    array_push($dataArray, $SSrow); # add this row to the 2D array of rows
		# debug
    # echo "Step 3 - Write dataArray gm_cnt=$gm_cnt: GameId: $currentgmid club=$currentclub<br><br>";

return;

}
?>
