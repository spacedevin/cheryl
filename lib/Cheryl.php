<?php

/**
 * Cheryl 3.0
 *
 * 2003 - 2013 Devin Smith
 * https://github.com/arzynik/cheryl
 *
 * Cheryl is a web based file manager for the modern web. Built
 * on top of PHP5 and AngularJS with lots of love from HTML5 and
 * CSS3 animations.
 *
 * In intall, just copy this script to where you want to share files.
 * See the config below for options.
 *
 */
 

ignore_user_abort(false);
set_time_limit(10);
date_default_timezone_set('America/Los_Angeles');

ini_set('zlib.output_compression','On');
ini_set('zlib.output_compression_level', 9);


if (!defined('CHERYL_SALT')) {
	// password salt. make something random
	define('CHERYL_SALT', 'SOMETHING/NOT/COOL/AND/RANDOM');
}

class Cheryl {
	private static $_cheryl;

	private $defaultConfig = array(
		// the admin username nad password to access all features. if set to blank, all users will have access to all enabled features
		// array of users. this can be overwridden by custom user clases
		'users' => array(
			array(
				'username' => 'admin',
				'password' => '',
				'permissions' => 'all' // if set to all, all permissions are enabled. even new features addedd in the future
			)
		),
		'authentication' => array(
			'type' => 'simple'  // simple: users are stored in the users array. mysql: uses a mysqli interface. pdo: uses pdo interface. see examples
		),
		'useSha1' => true, // if true, passwords will be hashed client side for security.
		'root' => 'files', // the folder you want users to browse
		'includes' => 'Cheryl', // path to look for additional libraries. leave blank if you dont know
		'templateName' => 'cheryl', // name of the template to look for. leave alone if you dont know
		'readonly' => false, // if true, disables all write features, and doesnt require authentication
		'features' => array(
			'snooping' => false, // if true, a user can browse filters behind the root directory, posibly exposing secure files. not reccomended
			'recursiveBrowsing' => true, // if true, allows a simplified view that shows all files recursivly in a directory. with lots of files this can slow it down
		),
		// files to hide from view
		'hiddenFiles' => array(
			'.DS_Store',
			'desktop.ini',
			'.git',
			'.svn',
			'.hg',
			'.trash',
			'.thumb',
			'.cherylconfig'
		),
		'trash' => true, // if true, deleting files will send to trash first
		'libraries' => array(
			'type' => 'remote'
		),
		'recursiveDelete' => true // if true, will allow deleting of unempty folders
	);

	public $features = array(
		'rewrite' => false,
		'userewrite' => null,
		'json' => false,
		'gd' => false,
		'exif' => false,
		'imlib' => false,
		'imcli' => false
	);

	public $authed = false;
	

	public static function init($config = null) {
		if (!self::$_cheryl) {
			new Cheryl($config);
		}
		return self::$_cheryl;
	}
	
	public function __construct($config = null) {
		if (!self::$_cheryl) {
			self::$_cheryl = $this;
		}

		if (is_object($config)) {
			$config = (array)$config;
		} elseif(is_array($config)) {
			$config = $config;
		} else {
			$config = array();
		}
		
		$config = array_merge($this->defaultConfig, $config);
		$config = Cheryl_Model::toModel($config);
		
		$this->config = $config;

		$this->_setup();
		$this->_digestRequest();
		$this->_authenticate();
	}
	
	public static function script() {
		return preg_replace('@'.DIRECTORY_SEPARATOR.'((index|default)\.(php|htm|html))$@','',$_SERVER['SCRIPT_NAME']);
	}

	public static function password($password) {
		// just a pinch
		return sha1($password.CHERYL_SALT);
	}
	
	public static function me() {
		return self::$_cheryl;
	}
	
	public static function go() {
		self::me()->_request();
		echo self::template();
	}
	
	public static function template() {
		return Cheryl_Template::show();
	}

	private function _request() {
		// process authentication requests
		switch ($this->requestPath[0]) {
			case 'logout':
				$this->_logout();
				echo json_encode(array('status' => true, 'message' => 'logged out'));
				exit;

			case 'login':
				$res = $this->_login();
				if ($res) {
					echo json_encode(array('status' => true, 'message' => 'logged in'));
				} else {
					echo json_encode(array('status' => false, 'message' => 'failed to log in'));
				}
				exit;
				
			// get the config and authentication status
			case 'config':
				$this->_getConfig();
				exit;
				break;

			// list the contents of a directory
			case 'ls':
				$this->_requestList();
				exit;
				break;
			
			// download a file
			case 'dl':
				$this->_getFile(true);
				exit;
				break;
				
			// upload a file
			case 'ul':
				$this->_takeFile();
				exit;
				break;
				
			// view a file
			case 'vw':
				$this->_getFile(false);
				exit;
				break;
				
			// delete a file
			case 'rm':
				$this->_deleteFile();
				exit;
				break;
				
			// rename a file
			case 'rn':
				$this->_renameFile();
				exit;
				break;
				
			// make a directory
			case 'mk':
				$this->_makeFile();
				exit;
				break;

			// save a file
			case 'sv':
				$this->_saveFile();
				exit;
				break;
				
			// display icon
			case 'icon':
				header('Content-Type: image/png');
				echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAJgAAACYCAYAAAAYwiAhAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3NpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNS1jMDE0IDc5LjE1MTQ4MSwgMjAxMy8wMy8xMy0xMjowOToxNSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDphYWFjYWRhNi1lYjI0LTQ2NTMtYWNmYi1jYzY2NDZhNDk0MzMiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NDYyNUIwMDQ1NkY4MTFFM0FFODVCQ0FBODlEMUY3OTkiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NDYyNUIwMDM1NkY4MTFFM0FFODVCQ0FBODlEMUY3OTkiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIChNYWNpbnRvc2gpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6YWFhY2FkYTYtZWIyNC00NjUzLWFjZmItY2M2NjQ2YTQ5NDMzIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOmFhYWNhZGE2LWViMjQtNDY1My1hY2ZiLWNjNjY0NmE0OTQzMyIvPiA8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');
				exit;
				break;
				

			default:
				// display the main html document by letting it pass through php
				break;
		}
	}

	private function _setup() {

		$this->features = (object)$this->features;

		if (file_exists($this->config->includes)) {
			// use include root at script level
			$this->config->includes = realpath($this->config->includes).DIRECTORY_SEPARATOR;

		} elseif (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config->includes)) {
			// use include root at lib level
			$this->config->includes = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config->includes.DIRECTORY_SEPARATOR;

		} else {
			// use current path
			$this->config->includes = realpath(__FILE__).DIRECTORY_SEPARATOR;
		}

		if (!$this->config->root) {
			$this->config->root = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR;
		} else {
			$this->config->root = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR.$this->config->root.DIRECTORY_SEPARATOR;
			
			if (!file_exists($this->config->root)) {
				@mkdir($this->config->root);
				@chmod($this->config->root, 0777);
			}
		}

		if ((function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) || getenv('HTTP_MOD_REWRITE') == 'On') {
			$this->features->rewrite = true;
		}
		
		if (!function_exists('json_decode')) {
			if (file_exists($this->config->includes.'Cheryl/Library/JSON.php')) {
				require_once($this->config->includes.'Cheryl/Library/JSON.php');
			}
		}

		if (function_exists('json_decode')) {
			$this->features->json = true;
		}
		
		if (function_exists('exif_read_data')) {
			$this->features->exif = true;
		}
		if (function_exists('getimagesize')) {
			$this->features->gd = true;
		}
		if (function_exists('Imagick::identifyImage')) {
			$this->features->imlib = true;
		}
		if (!$this->features->imlib) {
			$o = shell_exec('identify -version 2>&1');
			if (!strpos($o, 'not found')) {
				$this->features->imcli = 'identify';
			} elseif (file_exists('/usr/local/bin/identify')) {
				$this->features->imcli = '/usr/local/bin/identify';
			} elseif(file_exists('/usr/bin/identify')) {
				$this->features->imcli = '/usr/bin/identify';
			} elseif(file_exists('/opt/local/bin/identify')) {
				$this->features->imcli = '/opt/local/bin/identify';
			} elseif(file_exists('/bin/identify')) {
				$this->features->imcli = '/bin/identify';
			} elseif(file_exists('/usr/bin/identify')) {
				$this->features->imcli = '/usr/bin/identify';
			}
		}

		$stat = intval(trim(shell_exec('stat -f %B '.escapeshellarg(__FILE__))));
		if ($stat && intval(filemtime(__FILE__)) != $stat) {
			$this->features->ctime = true;
		}
	}

	private function _authenticate() {
		if (!Cheryl_User::users()) {
			// allow anonymouse access. ur crazy!
			return $this->authed = true;
		}

		session_start();

		if ($_SESSION['cheryl-authed']) {
			$this->user = new Cheryl_User($_SESSION['cheryl-username']);
			return $this->authed = true;
		}
	}
	
	private function _login() {
		$user = Cheryl_User::login();
		if ($user) {
			$this->user = $user;
			$this->authed = $_SESSION['cheryl-authed'] = true;
			$_SESSION['cheryl-username'] = $this->user->username;
			return true;

		} else {
			return false;
		}
	}
	
	private function _logout() {
		@session_destroy();
		@session_regenerate_id();
		@session_start();
	}

	private function _digestRequest() {
		if (strtolower($_SERVER['REQUEST_METHOD']) == 'post' && !$_REQUEST['__p']) {
			if (!$this->features->json) {
				header('Status: 400 Bad Request');
				header('HTTP/1.0 400 Bad Request');
				echo json_encode(array('status' => false, 'JSON is not installed on this server. requests must use query strings'));
			} else {
				$this->request = json_decode(file_get_contents('php://input'),true);
			}
		} else {
			$this->request = $_REQUEST;
		}

		if ($this->request['__p']) {
			// we have a page param result
			$url = explode('/',$this->request['__p']);
			$this->features->userewrite = false;

		} else {
			$url = false;
		}

		$this->requestPath = $url;
		
		// sanatize file/directory requests
		if ($this->request['_d']) {
			$this->request['_d'] = str_replace('/',DIRECTORY_SEPARATOR, $this->request['_d']);
			if ($this->config->features->snooping) {
				// just allow them to enter any old damn thing
				$this->requestDir = $this->config->root.$this->request['_d'];
			} else {
				$this->requestDir = preg_replace('/\.\.\/|\.\//i','',$this->request['_d']);
				//$this->requestDir = preg_replace('@^'.DIRECTORY_SEPARATOR.basename(__FILE__).'@','',$this->requestDir);
				$this->requestDir = $this->config->root.$this->requestDir;
			}
			
			if (file_exists($this->requestDir)) {
				$this->requestDir = dirname($this->requestDir).DIRECTORY_SEPARATOR.basename($this->requestDir);
			} else {
				$this->requestDir = null;
			}
		}
		
		// sanatize filename
		if ($this->request['_n']) {
			$this->requestName = preg_replace('@'.DIRECTORY_SEPARATOR.'@','',$this->request['_n']);	
		}
	}
	
	private function _getFiles($dir, $filters = array()) {
		
		if ($filters['recursive']) {
			$iter = new RecursiveDirectoryIterator($dir);
			$iterator = new RecursiveIteratorIterator(
				$iter,
				RecursiveIteratorIterator::SELF_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			
			$filtered = new CherylFilterIterator($iterator);
			
			$paths = array($dir);
			foreach ($filtered as $path => $file) {
				if ($file->getFilename() == '.' || $file->getFilename() == '..') {
					continue;
				}
				if ($file->isDir()) {
					$dirs[] = $this->_getFileInfo($file);
				} elseif (!$file->isDir()) {
					$files[] = $this->_getFileInfo($file);
				}
			}

		} else {
			$iter = new CherylDirectoryIterator($dir);
			$filter = new CherylFilterIterator($iter);
			$iterator = new IteratorIterator($filter);

			$paths = array($dir);
			foreach ($iterator as $path => $file) {
				if ($file->isDot()) {
					continue;
				}
				if ($file->isDir()) {
					$dirs[] = $this->_getFileInfo($file);
				} elseif (!$file->isDir()) {
					$files[] = $this->_getFileInfo($file);
				}
			}
		}
		
		return array('dirs' => $dirs, 'files' => $files);
	}

	private function _cTime($file) {
		return trim(shell_exec('stat -f %B '.escapeshellarg($file->getPathname())));
	}

	// do our own type detection
	private function _type($file, $extended = false) {

		$mime = mime_content_type($file->getPathname());

		$mimes = explode('/',$mime);
		$type = strtolower($mimes[0]);
		$ext = $file->getExtension();

		if ($ext == 'pdf') {
			$type = 'image';
		}
		
		$ret = array(
			'type' => $type,
			'mime' => $mime
		);
		
		if (!$extended) {
			return $ret['type'];
		} else {
			return $ret;
		}
	}
	
	private function _getFileInfo($file, $extended = false) {
		$path = str_replace(realpath($this->config->root),'',realpath($file->getPath()));

		if ($file->isDir()) {
			$info = array(
				'path' => $path,
				'name' => $file->getFilename(),
				'writeable' => $file->isWritable(),
				'type' => $this->_type($file, false)
			);

		} elseif (!$file->isDir()) {
			$info = array(
				'path' => $path,
				'name' => $file->getFilename(),
				'size' => $file->getSize(),
				'mtime' => $file->getMTime(),
				'ext' => $file->getExtension(),
				'writeable' => $file->isWritable()
			);

			if ($this->features->ctime) {
				$info['ctime'] = $this->_cTime($file);
			}

			if ($extended) {
				$type = $this->_type($file, true);
				
				$info['type'] = $type['type'];

				$info['meta'] = array(
					'mime' => $type['mime']
				);

				if ($type['type'] == 'text') {
					$info['contents'] = file_get_contents($file->getPathname());
				}

				if (strpos($mime, 'image') > -1) {
					if ($this->features->exif) {
						$exif = @exif_read_data($file->getPathname());

						if ($exif) {
							$keys = array('Make','Model','ExposureTime','FNumber','ISOSpeedRatings','FocalLength','Flash');
							foreach ($keys as $key) {
								if (!$exif[$key]) {
									continue;
								}
								$exifInfo[$key] = $exif[$key];
							}
							if ($exif['DateTime']) {
								$d = new DateTime($exif['DateTime']);
								$exifInfo['Created'] = $d->getTimestamp();
							} elseif ($exif['FileDateTime']) {
								$exifInfo['Created'] = $exif['FileDateTime'];
							}
							if ($exif['COMPUTED']['CCDWidth']) {
								$exifInfo['CCDWidth'] = $exif['COMPUTED']['CCDWidth'];
							}
							if ($exif['COMPUTED']['ApertureFNumber']) {
								$exifInfo['ApertureFNumber'] = $exif['COMPUTED']['ApertureFNumber'];
							}
							if ($exif['COMPUTED']['Height']) {
								$height = $exif['COMPUTED']['Height'];
							}
							if ($exif['COMPUTED']['Width']) {
								$width = $exif['COMPUTED']['Width'];
							}
						}
					}
					$info['meta']['exif'] = $exifInfo;
				}
				if (!$height || !$width) {
					if ($this->features->gd) {
						$is = @getimagesize($file->getPathname());
						if ($is[0]) {
							$width = $is[0];
							$height = $is[1];
						}
					}
				}
				if ($height && $width) {
					$info['meta']['height'] = $height;
					$info['meta']['width'] = $width;
				}
			}
			
			$info['meta']['mime'] = $mime;
			$info['perms'] = $file->getPerms();

		}
		return $info;
	}
	
	private function _getFile($download = false) {
		if (!$this->authed && !$this->config->readonly) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		
		if (!$this->requestDir || !is_file($this->requestDir)) {
			header('Status: 404 Not Found');
			header('HTTP/1.0 404 Not Found');
			exit;
		}
		
		$file = new SplFileObject($this->requestDir);
		
		// not really sure if this range shit works. stole it from an old script i wrote
		if (isset($_SERVER['HTTP_RANGE'])) {
			list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if ($size_unit == 'bytes') {
				list($range, $extra_ranges) = explode(',', $range_orig, 2);
			} else {
				$range = '';
			}
			
			if ($range) {
				list ($seek_start, $seek_end) = explode('-', $range, 2);
			}
			
			$seek_end = (empty($seek_end)) ? ($size - 1) : min(abs(intval($seek_end)),($size - 1));
			$seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);
			
			if ($seek_start > 0 || $seek_end < ($size - 1)) {
				header('HTTP/1.1 206 Partial Content');
			} else {
				header('HTTP/1.1 200 OK');
			}
			header('Accept-Ranges: bytes');
			header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$size);
			$contentLength = ($seek_end - $seek_start + 1);

		} else {
			header('HTTP/1.1 200 OK');
			header('Accept-Ranges: bytes');
			$contentLength = $file->getSize();
		}

		header('Pragma: public');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Date: '.date('r'));
		header('Last-Modified: '.date('r',$file->getMTime()));
		header('Content-Length: '.$contentLength);
		header('Content-Transfer-Encoding: binary');
		
		if ($download) {
			header('Content-Disposition: attachment; filename="'.$file->getFilename().'"');
			header('Content-Type: application/force-download');
		} else {
			header('Content-Type: '. mime_content_type($file->getPathname()));
		}

		// i wrote this freading a really long time ago but it seems to be more robust than SPL. someone correct me if im wrong
		$fp = fopen($file->getPathname(), 'rb');
		fseek($fp, $seek_start);
		while(!feof($fp)) {
			set_time_limit(0);
			print(fread($fp, 1024*8));
			flush();
			ob_flush();
		}
		fclose($fp);
		exit;
	}

	private function _requestList() {
		if (!$this->authed && !$this->config->readonly) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}

		if (!$this->requestDir) {
			header('Status: 404 Not Found');
			header('HTTP/1.0 404 Not Found');
			exit;
		}

		if (is_file($this->requestDir)) {
			$file = new SplFileObject($this->requestDir);
			$info = $this->_getFileInfo($file, true);
			echo json_encode(array('type' => 'file', 'file' => $info));

		} else {

			if (realpath($this->requestDir) == realpath($this->config->root)) {
				$path = '';
				$name = '';
			} else {
				$dir = pathinfo($this->requestDir);
				$path = str_replace(realpath($this->config->root),'',realpath($dir['dirname']));
				$name = basename($this->requestDir);
				$path = $path{0} == '/' ? $path : '/'.$path;
			}
			$info = array(
				'path' => $path,
				'writeable' => is_writable($this->requestDir),
				'name' => $name
			);

			$files = $this->_getFiles($this->requestDir, array(
				'recursive' => $this->request['filters']['recursive']
			));
			echo json_encode(array('type' => 'dir', 'list' => $files, 'file' => $info));
		}
	}
	
	private function _takeFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config->readonly || !$this->user->permission('upload', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;			
		}

		foreach ($_FILES as $file) {
			move_uploaded_file($file['tmp_name'],$this->requestDir.DIRECTORY_SEPARATOR.$file['name']);
		}

		echo json_encode(array('status' => true));
	}
	
	private function _deleteFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config->readonly || !$this->user->permission('delete', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;			
		}
		
		$status = false;

		if (is_dir($this->requestDir)) {
			if ($this->config->recursiveDelete) {
				foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->requestDir), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
					if ($path->getFilename() == '.' || $path->getFilename() == '..') {
						continue;
					}
					$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
				}
			}

			if (rmdir($this->requestDir)) {
				$status = true;
			}

		} else {
			if (unlink($this->requestDir)) {
				$status = true;
			}
		}
		
		echo json_encode(array('status' => $status));
	}
	
	private function _renameFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config->readonly || !$this->user->permission('rename', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;			
		}

		if (@rename($this->requestDir, dirname($this->requestDir).DIRECTORY_SEPARATOR.$this->requestName)) {
			$status = true;
		} else {
			$status = false;
		}
		
		echo json_encode(array('status' => $status, 'name' => $this->requestName));
	}
	
	private function _makeFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config->readonly || !$this->user->permission('create', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;			
		}

		if (@mkdir($this->requestDir.DIRECTORY_SEPARATOR.$this->requestName,0777)) {
			$status = true;
		} else {
			$status = false;
		}
		
		echo json_encode(array('status' => $status, 'name' => $this->requestName));
	}
	
	private function _saveFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config->readonly || !$this->user->permission('save', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;			
		}

		if (@file_put_contents($this->requestDir,$this->request['c'])) {
			$status = true;
		} else {
			$status = false;
		}

		echo json_encode(array('status' => $status));
	}
	
	private function _getConfig() {
		echo json_encode(array('status' => true, 'authed' => $this->authed));
	}

	public static function iteratorFilter($current) {
        return !in_array(
            $current->getFileName(),
            self::me()->config->hiddenFiles,
            true
        );
	}
}


class CherylFilterIterator extends FilterIterator {
    public function accept() {
        return Cheryl::iteratorFilter($this->current());
    }
}

class CherylDirectoryIterator extends DirectoryIterator {
	public function getExtension() {
		if (method_exists(get_parent_class($this), 'getExtension')) {
			$ext = parent::getExtension();
		} else {
			$ext = pathinfo($this->getPathName(), PATHINFO_EXTENSION);
		}
		return strtolower($ext);
	}
}


class Cheryl_Model {
	public static function toModel($array) {

		$object = new Cheryl_Model();
		if (is_array($array) && count($array) > 0) {
			foreach ($array as $name => $value) {
				if ($name === 0) {
					$isArray = true;
				}

				if (!empty($name) || $name === 0) {

					if (is_array($value)) {
						if (!count($value)) {
                    		$value = null;
						} else {
							$value = self::toModel($value);
						}
                    }
					if ($isArray) {
						switch ($value) {
							case 'false':
								$array[$name] = false;
								break;
							case 'true':
								$array[$name] = true;
								break;
							case 'null':
								$array[$name] = null;
								break;
							default:
								$array[$name] = $value;
								break;
						}
					} else {
						$name = trim($name);
						switch ($value) {
							case 'false':
								$object->$name = false;
								break;
							case 'true':
								$object->$name = true;
								break;
							case 'null':
								$object->$name = null;
								break;
							default:
								$object->$name = $value;
								break;
						}					
					}
				}
			}
		}

		return $isArray ? $array : $object;
	}
}

// we need to at least be able to encode data
if (!function_exists('json_encode')) {
	function json_encode($data) {
		switch ($type = gettype($data)) {
			case 'NULL':
				return 'null';
			case 'boolean':
				return ($data ? 'true' : 'false');
			case 'integer':
			case 'double':
			case 'float':
				return $data;
			case 'string':
				return '"' . addslashes($data) . '"';
			case 'object':
				$data = get_object_vars($data);
			case 'array':
				$output_index_count = 0;
				$output_indexed = array();
				$output_associative = array();
				foreach ($data as $key => $value) {
					$output_indexed[] = json_encode($value);
					$output_associative[] = json_encode($key) . ':' . json_encode($value);
					if ($output_index_count !== NULL && $output_index_count++ !== $key) {
						$output_index_count = NULL;
					}
				}
				if ($output_index_count !== NULL) {
					return '[' . implode(',', $output_indexed) . ']';
				} else {
					return '{' . implode(',', $output_associative) . '}';
				}
			default:
				return '';
		}
	}
}

require_once('Cheryl/Template.php');
require_once('Cheryl/User.php');
require_once('Cheryl/Extras.php');