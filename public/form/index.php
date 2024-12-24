<html>
<head>
<title><?php echo 'form'; ?></title>
<link rel="stylesheet" href="../assets/style.css" type="text/css">
<script src="../assets/script.js"></script>
</head>
<body>
<h1>form</h1>
<h2>GET</h2>
<form method="GET">
    <input type="text" name="test">
    <input type="submit">
    <pre><?php print_r($_GET); ?></pre>
</form>
<h2>POST</h2>
<form method="POST">
    <input type="text" name="test">
    <input type="submit">
    <pre><?php print_r($_POST); ?></pre>
</form>
<h2>REQUEST</h2>
<pre><?php print_r($_REQUEST); ?></pre>
<a href="/">top</a>
</body>
</html>
