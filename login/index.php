<?php

$up = "../";
require_once("../db.php");

if (!empty($_SESSION['user'])) {
	// already logged in, redirect the user
	header("Location: ../");
	die("Redirecting");
}

$notice = '';

if (isset($_GET['registered'])) {
	$notice = 'Successfully registered! Now you can login below';
}

if (isset($_POST)) {
	$user   = '';
	$pass   = '';

	if (!empty($_POST['username'])) {
		$user = mysqli_real_escape_string($connect, $_POST['username']);
	}

	if (!empty($_POST['password'])) {
		$pass = mysqli_real_escape_string($connect, $_POST['password']);
	}
	

	$select = "
		SELECT
			id,
			first_name,
			last_name,
			username,
			password,
			points
		FROM users
		WHERE
			username = '{$user}' AND
			password = '{$pass}'
	";

	$query = mysqli_query($connect, $select);
	$rows  = mysqli_num_rows($query);

	if ($rows == 1) {
		$row = mysqli_fetch_array($query, MYSQLI_ASSOC);

		// Remove password
		unset($row['password']);

		$_SESSION['user'] = $row;

		header('Location: ../');
		die("Redirecting");
	}
}

require_once("../head_top.php");
require_once("../head_bottom.php");
?>
<div id="wrap">
<p id="logo"> Hack Attack </p>
<?php

if (!empty($notice)) {
?>
<div class="notice">
<?php echo $notice; ?>
</div>
<?php
}

?>
<form class="login" method="post" action="../login/index.php">
	<input class="login" type="username" name="username" placeholder="Username" autocomplete="off"> <br> <br>
	<input class="login" type="password" name="password" placeholder="Password" autocomplete="off"> <br><br>

	<button type="submit" class="login" name="submit">Login</button>
</form>
<div id="login-after">
	Not signed up? <a href="../register" id="register">Register</a>
</div>

<?php
require_once("../footer.php");
?>
