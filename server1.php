<?php
/**
 * サーバ側の処理
 */
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__."/AwsS3.php";
require_once __DIR__."/config.php";

if (!isset($_GET['type']) ||
	!in_array($_GET['type'], ['upload', 'download'])) {
	exit;
}

switch ($_GET['type']) {
	case 'upload':
		$s3 = new AwsS3($config);
		$s3->echoUrlObjectDirectUploadAndExit($_POST['name']);
		break;

	case 'download':
		$s3 = new AwsS3($config);
		$keys = $s3->getListKeys();
		if (count($keys) == 0) {
			echo "file not found!";
			exit;
		}
		header('Location: '.$s3->getUrlDirectDownload($keys[0]));
		exit;
		break;
}
