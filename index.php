<?php
require_once __DIR__ . '/config.php';
?>

<!DOCTYPE HTML>  

<p style="text-align:right; font-size:0.8em;">
	Version <?= APP_VERSION ?>
</p>


<html>
<head>
  <title>Bridge player's self-assessment app</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="stylesheet" href="css/styles.css?v=<?= APP_VERSION ?>">
	
</head>

<body background="images/backgrou.gif">
<div id="top" class="column">
<h1 style='text-align:center'>Unit 477 self-assessment app</h1>

<p><span class="e">* required field</span></p>

<form id="queryForm" method="post" action="controller.php" autocomplete="on">

<!-- PLAYER INFORMATION -->
<div id="playerSection"
	class="form-section"
	data-selected="<?= empty($multiple_matches) && !empty($pnum) ? 'yes' : 'no' ?>">

  <button type="button" class="section-toggle open">Player information</button>

  <div class="section-content" style="display:block;">
	<fieldset>

	  <?php if (!empty($playerqueryErr)): ?>
		<p class="error"><?= htmlspecialchars($playerqueryErr) ?></p>
	  <?php endif; ?>

	  <!-- Always show the input box -->
	  <label for="player_id">Player number or name:</label>
	  <input type="text"
			 id="player_id"
			 name="player_id"
			 size="30"
			 value="<?= htmlspecialchars($player_id) ?>">

	  <?php if (!empty($multiple_matches)): ?>
		<!-- Multiple matches: show dropdown -->
		<label for="pnum">Select a player:</label><br>

		<select id="pnum" name="pnum" size="10" autofocus>
		  <option value="" selected>none</option>

		  <?php foreach ($matches as $pnum => $info): ?>
			<?php
			  $nm   = $info[0];
			  $city = $info[1];
			  $st   = $info[2];
			  $msg  = "$nm of $city, $st";
			?>
			<option value="<?= htmlspecialchars($pnum) ?>">
			  <?= htmlspecialchars($msg) ?>
			</option>
		  <?php endforeach; ?>
		</select>
	  <?php endif; ?>

	  <?php if (!empty($pnum)): ?>
		<!-- Single match: show final selection -->
		<label>Player selected:</label>
		<div class="selected-player">
		  <?= htmlspecialchars($playerNAME) ?> (<?= htmlspecialchars($pnum) ?>)
		</div>
	  <?php endif; ?>

	</fieldset>
  </div>
</div>


  <!-- DATE RANGE (always visible) -->
  <fieldset>
	<div class="section-title required">Date range:</div>

	<label for="startDate">start date</label><br>
	<input type="date" id="startDate" name="startDate" value="<?= $startDate ?>"><br>

	<label for="stopDate">stop date</label><br>
	<input type="date" id="stopDate" name="stopDate" value="<?= $stopDate ?>">
  </fieldset>


  <!-- INQUIRIES (always visible) -->
  <fieldset>
	<div class="section-title required">Inquiries – choose one or more:
	  <span class="error"><?= $queryErr ?></span>
	</div>

	<input type="checkbox" id="query1" name="query1" value="history">
	<label for="query1">My history</label><br>

	<input type="checkbox" id="query2" name="query2" value="partners">
	<label for="query2">My partners</label><br>

	<input type="checkbox" id="query3" name="query3" value="perfwt">
	<label for="query3">Detailed partner metrics w/comparison</label><br>

	<input type="checkbox" id="query4" name="query4" value="perfwot">
	<label for="query4">Detailed teammate metrics w/comparison</label><br>

	<input type="checkbox" id="query5" name="query5" value="spreadsheet">
	<label for="query5">My spreadsheet, showing historic trends</label><br>

	<input type="checkbox" id="query6" name="query6" value="HCP">
	<label for="query6">My average HCP per session</label><br>
  </fieldset>


  <!-- SEND-TO / EMAIL (collapsible) -->
  <div class="form-section">
	<button type="button" class="section-toggle">Send-to / Email</button>
	<div class="section-content">
	  <fieldset>
		<!-- <legend>Send-to / Email</legend> -->

		<input type="checkbox" id="send" name="send"
					 <?= !empty($send) ? "checked" : "" ?>>
		<label for="send">email these results to me</label><br>

		<span class="error"><mark><?= $sendErr ?></mark></span><br>

		my email addr:
		<input type="email" id="email" name="email" value="<?= $email ?>">
		<span class="error"><?= $emailErr ?></span>
	  </fieldset>
	</div>
  </div>


  <!-- FEEDBACK (collapsible) -->
  <div class="form-section">
	<button type="button" class="section-toggle">Feedback</button>
	<div class="section-content">
	  <fieldset>
		<!-- <legend>Feedback</legend> -->
		Comments/Requests<br>
		Remember to identify yourself for followup response<br>
		<textarea name="comment" rows="5" cols="40" maxlength="400"></textarea>
	  </fieldset>
	</div>
  </div>

  <input type="submit" name="submit" value="Submit">
  <br>

</form>

<div id="overlay" style="display:none;">
	<div class="loader"></div>
	<p style='text-align:center'>Working… please be patient</p>
</div>

<script>
document.getElementById('queryForm').addEventListener('submit', function () {
	document.getElementById('overlay').style.display = 'flex';
});
</script>

<!-- Display any results -->
<?php if (!empty($results)): ?>
  <div id="results">
	<?php foreach ($results as $section => $html): ?>
	  <?= $html ?>
	<?php endforeach; ?>
  </div>
<?php endif; ?>

</div>

<script src="js/app.js?v=<?= APP_VERSION ?>"></script>


</body>
</html>

