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
<h1 style='text-align:center'>Unit 477 Bridge player self-assessment app</h1>

<p><span class="e">* required field</span></p>

<form id="queryForm" method="post" action="controller.php" autocomplete="on">
  <fieldset>
    <legend>
      <span class="error">*</span> Player information
<!--  <span class="error"><?= htmlspecialchars($playerqueryErr) ?></span> -->
    </legend>

    <!-- Single entry field: number OR name -->
		<label for="player_id">Player number or name:</label>
		<input type="text"
			id="player_id"
			name="player_id"
			size="30"
			value="<?= htmlspecialchars($player_id) ?>">

		<?php if (!empty($playerqueryErr)): ?>
			<p class="error"><?= htmlspecialchars($playerqueryErr) ?></p>
		<?php endif; ?>

		<?php if (!empty($multiple_matches)): ?>
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

    <!-- If exactly one match, show confirmation -->
		<?php if (!empty($pnum) && empty($multiple_matches)): ?>
   		<p>Player selected: <strong><?= htmlspecialchars($playerNAME) ?></strong> (<?= htmlspecialchars($pnum) ?>)</p>
		<?php endif; ?>

	</fieldset>

	<fieldset>
    <legend><span class="error">*</span>Date range:</legend>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<label for="startDate">start date</label>
		  <input type="date" id="startDate" name="startDate" value="<?php echo $startDate;?>"> &nbsp;&nbsp;
		  <label for="stopDate">stop date</label>
      <input type="date" id="stopDate" name="stopDate" value="<?php echo $stopDate;?>">
			<br>
  </fieldset>

  <fieldset>
    <legend><span class="error">*</span>Inquiries - choose one or more :
    <span class="error"><?php echo $queryErr;?></span></legend>
    <input type="checkbox" id="query1" name="query1" value="history">
		  <label for="query1"> Player's history</label><br>
    <input type="checkbox" id="query2" name="query2" value="partners">
      <label for="query2"> Player's partners</label><br>
    <input type="checkbox" id="query3" name="query3" value="perfwt">
      <label for="query3"> Detailed measures for all pair game partners w/comparison </label><br>
    <input type="checkbox" id="query4" name="query4" value="perfwot">
      <label for="query4"> Detailed measures for all team game teammates w/comparison </label><br>
    <input type="checkbox" id="query5" name="query5" value="spreadsheet">
      <label for="query5"> Player's spreadsheet, showing historic trends</label><br>
    <input type="checkbox" id="query6" name="query6" value="HCP">
      <label for="query6"> Player's average HCP per session</label><br>
  </fieldset>

  <fieldset>
    <legend>send-to/Email</legend>
    <input type="checkbox" id="send" name="send" <?php if (isset($send) && $send=="yes") echo "checked";?> value="yes">
    <label for="send">email these results to me</label><br>
      <span class="error"> <mark><?php echo $sendErr;?></mark></span>
    my email addr: <input type="email" id="email" name="email" value="<?php echo $email;?>">
      <span class="error"> <?php echo $emailErr;?></span>
  </fieldset>

  <fieldset>
    <legend>Feedback</legend>
		Comments/Requests <br>Please identify yourself for followup response
		<textarea name="comment" rows="5" cols="40" maxlength=400 ></textarea>
  </fieldset>
  <br>
  
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

