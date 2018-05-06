<?php
/**
 * AWS S3
 */

/**
 * AWS S3
 */
class AwsS3
{
	/** @var S3Client クラス */
	public $client = null;

	/**
	 * @var array 設定
	 */
	protected $settings = array(
        'key'    => '',
        'secret' => '',
	    'bucket' => '',
	    'region' => 'ap-northeast-1',
	    'version' => '2006-03-01',
	    'signature_version' => 'v4',
	    'expires' => '+1 minutes',
	);

	/**
	 * コンストラクタ
	 * @param array $settings 設定
	 */
	function __construct($settings = array())
	{
		if (!isset($settings['key']) ||
			strlen($settings['key']) == 0 ||
			!isset($settings['secret']) ||
			strlen($settings['secret']) == 0) {
			throw new Exception("settings error .");
		}
		$this->settings = array_merge($this->settings, $settings);
		$this->client = new Aws\S3\S3Client([
		    'credentials' => [
		        'key' => $this->settings['key'],
		        'secret' => $this->settings['secret'],
		    ],
		    'region' => $this->settings['region'],
		    'version' => $this->settings['version'],
		    'signature_version' => $this->settings['signature_version'],
		]);
	}

	/**
	 * S3から直接ダウンロードするURLを取得
	 * @param string $key S3ファイルのパス
	 * @return string
	 */
	public function getUrlDirectDownload($key)
	{
		$key = $this->removeHeadSlash($key);
		$file_name = basename($key);
		$command = $this->client->getCommand('getObject', [
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		    'ResponseContentDisposition' => "attachment; filename={$file_name}",
//		    'ResponseContentType' => '',
		]);

		$request = $this->client->createPresignedRequest($command, $this->settings['expires']);
		$url = $request->getUri()->__toString();
		if (!isset($url) ||
			!is_string($url) ||
			!preg_match("/\Ahttps/", $url)) {
			throw new Exception("aws download url error!");
		}
		return $url;
	}

	/**
	 * S3に直接アップロードするためのURLを含んだオブジェクトを出力する
	 * ajax による呼び出しへの応答処理
	 *
	 * @param string $key S3ファイルのパス
	 * @return void
	 */
	public function echoUrlObjectDirectUploadAndExit($key)
	{
		$key = $this->removeHeadSlash($key);
		header('Content-Type: application/json');

		echo json_encode(array(
			'url' => $this->getUrlDirectUpload($key),
			'key' => $key,
			'file_name' => $key
		));
		exit;
	}

	/**
	 * S3に直接アップロードするためのURLを取得する
	 * @param string $key S3ファイルのパス
	 * @return string
	 */
	protected function getUrlDirectUpload($key)
	{
		$command = $this->client->getCommand('putObject', [
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		    'ACL'    => 'private',
		]);
		$request = $this->client->createPresignedRequest($command, $this->settings['expires']);
		$url = $request->getUri()->__toString();
		if (!isset($url) ||
			!is_string($url) ||
			!preg_match("/\Ahttps/", $url)) {
			throw new Exception("aws upload url error!");
		}
		return $url;
	}

	/**
	 * S3にファイルをアップロードする
	 * @param string $source_path WEBサーバ側のファイルのパス
	 * @param string $key S3ファイルのパス
	 * @return Aws\Result
	 */
	public function putObject($source_path, $key)
	{
		$key = $this->removeHeadSlash($key);
		$result = $this->client->putObject([
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		    'SourceFile' => $source_path,
		    'ContentType'=> mime_content_type($source_path),
		]);
		$this->client->waitUntil('ObjectExists', array(
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		));
		if (!$this->doesObjectExist($key)) {
			throw new Exception("putObject Error!");
		}
		return $result;
	}

	/**
	 * S3からファイルを取得する
	 * @param string $dest_path WEBサーバ側のファイルのパス
	 * @param string $key S3ファイルのパス
	 * @return Aws\Result
	 */
	public function getObject($dest_path, $key)
	{
		$key = $this->removeHeadSlash($key);
		if (!$this->doesObjectExist($key)) {
			throw new Exception("file not found!");
		}
		$result = $this->client->getObject([
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		    'SaveAs' => $dest_path,
		]);
		return $result;
	}

	/**
	 * ファイルをコピーする（同じバケット内）
	 * @param string $source_key コピー元 S3ファイルのパス
	 * @param string $dest_key コピー先 S3ファイルのパス
	 * @return Aws\Result
	 */
	public function copyObject($source_key, $dest_key)
	{
		$source_key = $this->removeHeadSlash($source_key);
		$dest_key = $this->removeHeadSlash($dest_key);
		$result = $this->client->copyObject([
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $dest_key,
		    'CopySource' => $this->settings['bucket'].'/'.
		    	$source_key,
		]);
		$this->client->waitUntil('ObjectExists', array(
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $dest_key,
		));
		if (!$this->doesObjectExist($dest_key)) {
			throw new Exception("copyObject Error!");
		}
		return $result;
	}

	/**
	 * S3からファイルを削除する
	 * @param string $key S3ファイルのパス
	 * @return Aws\Result
	 */
	public function deleteObject($key)
	{
		$key = $this->removeHeadSlash($key);
		$result = $this->client->deleteObject([
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		]);
		$this->client->waitUntil('ObjectNotExists', array(
		    'Bucket' => $this->settings['bucket'],
		    'Key'    => $key,
		));
		if ($this->doesObjectExist($key)) {
			throw new Exception("deleteObject Error!");
		}
		return $result;
	}

	/**
	 * ファイルを移動する（同じバケット内）
	 * @param string $source_key コピー元 S3ファイルのパス
	 * @param string $dest_key コピー先 S3ファイルのパス
	 * @return Aws\Result
	 */
	public function moveObject($source_key, $dest_key)
	{
		$result = $this->copyObject($source_key, $dest_key);
		$this->deleteObject($source_key);
		return $result;
	}

	/**
	 * 指定したフォルダのファイル一覧を取得する
	 * ディレクトリは除外する
	 * @param string $dir_path S3ファイルのパス
	 * @return array
	 */
	public function getListKeys($dir_path = '/')
	{
		$names = $this->getListObjects($dir_path);
		if (count($names) > 0) {
			foreach ($names as $key => $name) {
				if (strpos($name, '/') !== false) {
					unset($names[$key]);
				}
			}
		}
		return $names;
	}

	/**
	 * 指定した階層のフォルダのオブジェクト一覧を取得する
	 * @param string $dir_path S3ファイルのパス
	 * @return array
	 */
	public function getListObjects($dir_path = '/')
	{
		$key = $this->removeHeadSlash($dir_path);
		if (strlen($key) > 0 &&
			substr($key, -1) != '/') {
			$key .= '/';
		}
		$result = $this->client->listObjects([
		    'Bucket' => $this->settings['bucket'],
		    'Prefix' => $key,
		])->toArray();
		if (!array_key_exists('Contents', $result) ||
			count($result['Contents']) == 0) {
			return array();
		}
		$prefix = $result['Prefix'];
		$contents = $result['Contents'];
		$size = 0;
		$names = array();
		foreach ($contents as $key => $content) {
			$name = substr($content['Key'], strlen($prefix));
			if (strlen($name) == 0) {
				continue;
			}
			$names[] = $name;
			$size += $content['Size'];
		}

		return $names;
	}

	/**
	 * key が存在するか判定する
	 * @param string $key S3ファイルのパス
	 * @return bool
	 */
	public function doesObjectExist($key)
	{
		$key = $this->removeHeadSlash($key);
		$result = $this->client->doesObjectExist(
		    $this->settings['bucket'],
		    $key
		);
		return $result;
	}

	/**
	 * key の先頭のスラッシュ'/'を削除する
	 * @param string $key S3ファイルのパス
	 * @return string
	 */
	protected function removeHeadSlash($key)
	{
		if (substr($key, 0, 1) == '/') {
			return substr($key, 1);
		}
		return $key;
	}
}
