<?php
/**
 * サーバ側の処理
 */
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__."/AwsS3.php";
require_once __DIR__."/config.php";

$s3 = new AwsS3($config);
$message = '';

if (isset($_GET['type']) &&
	$_GET['type'] == 'download') {
	$keys = $s3->getListKeys();
	if (count($keys) == 0) {
		echo "file not found!";
		exit;
	}
	$download_path = './download/';
	if (!file_exists($download_path)) {
		echo "please 'mkdir download'";
		exit;
	}
	if (!is_writable($download_path)) {
		echo "please 'chmod 777 download'";
		exit;
	}
	$file_name = basename($keys[0]);
	$file_path = $download_path.$file_name;
	$s3->getObject($file_path, $keys[0]);
	header('Content-Disposition: inline; filename="'.$file_name.'"');
	header('Content-Length: '.filesize($file_path));
	header('Content-Type: application/octet-stream');
	readfile($file_path);
	exit;
}

if (isset($_POST) &&
	count($_POST) > 0) {
	if (!isset($_FILES['file']['tmp_name']) ||
		strlen($_FILES['file']['tmp_name']) == 0 ||
		$_FILES['file']['error'] != 0 ||
		$_FILES['file']['size'] == 0) {
		$message = 'ファイルを選択してください';
	} else {
		$file_name = $_FILES['file']['name'];
		$tmp_path = $_FILES['file']['tmp_name'];
		$s3->putObject($tmp_path, $file_name);
		$message = 'ファイルをアップロードしました';
	}
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>S3 Upload</title>
<script type="text/javascript">
function download1(){
  var childWindow = window.open('about:blank');
  childWindow.location.href = './server2.php?type=download';
  childWindow = null;
}
</script>
</head>
<body>
<div class="container">
    <h2>サーバ側でS3と通信するサンプル</h2>
    <span style="color:red;">
    <? if (strlen($message) > 0): ?>
      <?= $message; ?>
    <? endif; ?>
    </span>
    <form action="./server2.php" method="POST" enctype="multipart/form-data">
        <input type="file" id="file1" name="file"><br>
        <br>
        <input type="submit" name="file_upload1" value="送信する">
        <br>
        <br>
    </form>
    <input type="button" name="file_download1" value="ダウンロード"
        onclick="javascript:download1();">
    <br>
    <br>
    <div class="result"></div>
</div>
</body>
</html>
