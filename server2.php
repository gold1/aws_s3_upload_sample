<?php
/**
 * サーバ側の処理
 */
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__."/AwsS3.php";
require_once __DIR__."/config.php";

if (!isset($_GET['command'])) {
	exit;
}

$s3 = new AwsS3($config);

switch ($_GET['command']) {
	case 'CreateMultipartUpload':
		$file_name = basename($_GET['fileInfo']['name']);
		$s3->echoInfoCreateMultipartUploadAndExit($file_name, $_GET['lengthes']);
		break;

	case 'CompleteMultipartUpload':
		$sendBackData = $_GET['sendBackData'];
		$s3->completeMultipartUpload($sendBackData['key'], $sendBackData['uploadId']);
		header('Content-Type: application/json');
		echo json_encode(array(
            'status' => 'success',
		));
		exit;
		break;

	case 'download':
		$keys = $s3->getListKeys();
		if (count($keys) == 0) {
			echo "file not found!";
			exit;
		}
		header('Location: '.$s3->getUrlDirectDownload($keys[0]));
		exit;
		break;
}
