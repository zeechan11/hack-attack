<?php

$up = "../";
// Connect to database and start session
require_once("../secure.php");

$code = '';

$answer = '';


$match_flag   = isset($_POST['language']); // Match up users instead of display challenge
$chall_flag   = isset($_SESSION['user']['hash_id']); // Display the ongoing challenge
$waiting_flag = isset($_SESSION['user']['waiting']);

if (isset($_POST['difficulty'])) {
	$difficulty = $_POST['difficulty'];
}

if ($match_flag) {

	$language = mysqli_real_escape_string($connect, $_POST['language']);
	$select = "
		SELECT *
		FROM waiting
		WHERE language = '{$language}'
		LIMIT 1
	";
	$query = mysqli_query($connect, $select);
	$rows  = mysqli_num_rows($query);
	// If someone is waiting with your language, both get added to ongoing challenges
	// (that person is deleted from waiting)
	if ($rows > 0) {
		$row = mysqli_fetch_array($query, MYSQLI_ASSOC);

		$stmt = $connect->prepare("
			INSERT INTO ongoing
				(
					id,
					challenge_id,
					user1,
					user2,
					first_name1,
					last_name1,
					first_name2,
					last_name2,
					start_time,
					points1,
					points2
				)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		");
		if ($stmt) {
			$user1 = $row['username'];
			$user2 = $_SESSION['user']['username'];

			if ($user1 == $user2) {
				header('Location: index.php');
				die('Redirecting');
			}

			$start_time = time();

			$challenge_id = $difficulty;
			$default_correct = 0;

			$hash_id = hash('sha512', $user1 . $user2 . $start_time);

			$stmt->bind_param(
				"sissssssiii",
				$hash_id,
				$challenge_id,
				$user1,
				$user2,
				$row['first_name'],
				$row['last_name'],
				$_SESSION['user']['first_name'],
				$_SESSION['user']['last_name'],
				$start_time,
				$row['points'],
				$_SESSION['user']['points']
			);
			$stmt->execute();
			$rows =  $stmt->affected_rows;
			$stmt->close();

			// Delete user1 from waiting list
			$delete = "
				DELETE FROM waiting
				WHERE username = '{$user1}'
			";
			$query = mysqli_query($connect, $delete);

			$_SESSION['user']['hash_id'] = $hash_id;
			header('Location: index.php');
			die('Redirecting');
		}
		else {
			echo "Prepare failed: (" . $connect->errno . ") " . $connect->error;
		}
	}
	else { // If no one is waiting with your language, get added to waiting list
		$stmt = $connect->prepare("
			INSERT INTO waiting
				(username, first_name, last_name, language, points)
			VALUES (?, ?, ?, ?, ?)
		");
		if ($stmt) {
			$stmt->bind_param(
				"ssssi",
				$_SESSION['user']['username'],
				$_SESSION['user']['first_name'],
				$_SESSION['user']['last_name'],
				$language,
				$_SESSION['user']['points']
			);
			$stmt->execute();
			$rows =  $stmt->affected_rows;
			$stmt->close();
		}

		$_SESSION['user']['waiting'] = 1;
		$waiting_flag = 1;
	}
}
else if ($chall_flag) {
	$id = $_SESSION['user']['hash_id'];
	$select = "
		SELECT *
		FROM ongoing
		WHERE id = '{$id}'
		LIMIT 1
	";
	$query = mysqli_query($connect, $select);
	$row   = mysqli_fetch_array($query, MYSQLI_ASSOC);

	$start_time = $row['start_time'];
	$winner = $row['winner'];

	$opponent = NULL;
	$user   = $_SESSION['user']['username'];
	// Opponent is user2
	if ($row['user1'] == $user) {
		$opponent = $row['user2'];
	}
	// Opponent is user1
	else {
		$opponent = $row['user1'];
	}


	$challenge_id = $row['challenge_id'];
	$chall_select = "
		SELECT *
		FROM challenges
		WHERE id = '{$challenge_id}'
		LIMIT 1
	";
	$chall_query = mysqli_query($connect, $chall_select);
	$chall_row   = mysqli_fetch_array($chall_query, MYSQLI_ASSOC);

	$minutes     = $chall_row['minutes'];
	$chall_info  = $chall_row['challenge_info'];
	$out_correct = $chall_row['correct_out'];

	if (!empty($_POST['code'])) {
		$code = $_POST['code'];
		// echo $code;

		// Filter out any non-ascii characters (security measure)
		preg_replace('/[^a-zA-Z0-9]/', '', $code);

		$file = hash('md5', $_SESSION['user']['username'] . time()) . 'test.py';
		file_put_contents($file, $code);

		$out = `python $file`;
		// echo $out;

		// Delete $file
		unlink($file);


		$correct_str   = '<span class="correct">Correct! Get 1 point(s)</span>';
		$incorrect_str = 'Sorry, try again';
		// Compare user output with expected output
		$out = trim($out);
		$out_correct = trim($out_correct);
		$answer = '';

		if ($out === $out_correct) {
			if ($winner == NULL) {
				$answer = $correct_str;

				// Give 1 point to the user
				$points = $_SESSION['user']['points'];

				// Update session
				$_SESSION['user']['points'] = $_SESSION['user']['points'] + 1;

				// Update database
				$user   = $_SESSION['user']['username'];
				$update = "
					UPDATE users
					SET points = points + 1
					WHERE username = '{$user}'
				";

				$query = mysqli_query($connect, $update);


				// Update database
				$user   = $_SESSION['user']['username'];
				$update = "
					UPDATE ongoing
					SET winner = '{$user}'
					WHERE id = '{$id}'
				";

				$query = mysqli_query($connect, $update);
			}
			else if ($winner == $opponent) {
				$answer = '<span class="correct">Sorry, you\'ve been beaten by your opponent.</span>';
			}
		}
		else {
			$answer = '<span class="correct">'. $incorrect_str .'</span>';
		}
	}

}



require_once("../head_top.php");

/////////////////////////
if ($chall_flag) {
?>
<link rel="stylesheet" type="text/css" href="../css/challenge.css">

<!-- Code Mirror files -->
<script src="../codemirror/lib/codemirror.js"></script>
<link rel="stylesheet" href="../codemirror/lib/codemirror.css">
<link rel="stylesheet" type="text/css" href="../codemirror/theme/tomorrow-night-bright.css">
<script src="../codemirror/mode/python/python.js"></script>

<script type="text/javascript">
function submit() {

}
</script>

<?php
}
/////////////////////////

require_once("../head_bottom.php");
require_once("../header.php");

//////////////////////////////////////////////////
if ($waiting_flag) {
?>

<style>
img.loading {
	margin: 10% auto 0;
	display: block;
}
div.desc {
	margin: 0 auto;
	width: 50%;
	display: block;
	text-align: center;
	font-size: 1.2em;
}
</style>

<img src='../images/loading.gif' class='loading'/>
<div class='desc'>Please wait while we match you with another coder.</div>

<script type="text/javascript">
function redirect() {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', '../link.php', true);
	xhr.onreadystatechange = function() {
		if(xhr.readyState == 4 && xhr.status == 200 && xhr.responseText !== '') {
			window.location = xhr.responseText;
		}
	}
	xhr.send();
}

// Check every second
window.setInterval(redirect, 500);
</script>

<?php
}
//////////////////////////////////////////////////
else if ($chall_flag) {
?>

<form id="challenge" action="../challenge/index.php" method="post">
	<div id="challenge-info">
		<?php echo $chall_info; ?>
	</div>
	<textarea id="code" name="code" autocomplete="off"></textarea>
	<input type="submit" id="submit" value="Submit">
	<div id="answer">
		<?php echo $answer; ?>
	</div>
</form>
<div id="opponent"></div>
<div id="timer"></div>

<script>
var mins = <?php echo $minutes; ?>;
var ms = mins * 60 * 1000;
var countDownDate = (<?php echo $start_time; ?> * 1000) + ms;

var x = setInterval(function() {

	var now = new Date().getTime();

	var distance = countDownDate - now;

	var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
	var seconds = Math.floor((distance % (1000 * 60)) / 1000);

	var str = "" + seconds;
	var pad = "00";
	var ans = pad.substring(0, pad.length - str.length) + str;
	document.getElementById("timer").innerHTML = minutes + ":" + ans;
	document.getElementById("timer").style.opacity = 1;

	if (distance < 0) {
		clearInterval(x);
		document.getElementById("timer").innerHTML = "0:00";
		alert('Returning to home page');
		window.location = '../index.php';
	}
}, 1000);
</script>

<script type="text/javascript">
var editor = CodeMirror.fromTextArea(document.getElementById('code'), {
	mode: 'python',
	lineNumbers: true,
    styleActiveLine: true,
    theme: 'tomorrow-night-bright'
});

// Set the code inside the editor
editor.getDoc().setValue(<?php

$code = addslashes($code);
$code = str_replace(array("\r","\n"),array("\\r","\\n"), $code);
echo '"'.$code.'"';

?>);

function opponent_refresh() {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', '../opponent.php', true);
	xhr.onreadystatechange = function() {
		if(xhr.readyState == 4 && xhr.status == 200 && xhr.responseText !== '') {
			document.getElementById('opponent').innerHTML = xhr.responseText;
		}
	}
	xhr.send();
}

// Check every second
window.setInterval(opponent_refresh, 1000);
</script>

<?php
}
//////////////////////////////////////////////////

require_once("../footer.php");

?>
