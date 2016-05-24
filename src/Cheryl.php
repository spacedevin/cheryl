<?php

/**
 * Cheryl 4.0
 *
 * 2003 - 2016 Devin Smith
 * https://github.com/arzynik/cheryl
 *
 * Cheryl is a web based file manager for the modern web using
 * PHP5 and AngularJS.
 *
 */

namespace Cheryl;


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
			$config = [];
		}

		$this->_tipsy = new \Tipsy\Tipsy;

		$this->tipsy()->config('../config/config.yml');
		$this->config = array_merge($this->defaultConfig, $this->tipsy()->config()['cheryl']);
		$config = array_merge($this->config, $config);

		$this->_digestRequest();
		$this->_setup();
		$this->_authenticate();
	}

	// method to grab object from static calls
	public static function me() {
		return self::$_cheryl;
	}

	public static function start() {
		self::me()->_request();
	}

	public function _request() {
		$this->tipsy()->request()->path($this->tipsy()->request()->request()['__p']);
		$self = $this;

		$this->tipsy()
			->get('logout', function() use ($self) {
				$self->_logout();
				echo json_encode([
					'status' => true,
					'message' => 'logged out'
				]);
			})
			->post('login', function() use ($self) {
				$res = $self->_login();
				if ($res) {
					echo json_encode([
						'status' => true,
						'message' => 'logged in'
					]);
				} else {
					echo json_encode([
						'status' => false,
						'message' => 'failed to log in'
					]);
				}
			})
			->get('config', function() use ($self) {
				$self->_getConfig();
			})
			->get('ls', function() use ($self) {
				$self->_requestList();
			})
			->get('dl', function() use ($self) {
				$self->_getFile(true);
			})
			->post('ul', function() use ($self) {
				$self->_takeFile();
			})
			->get('vw', function() use ($self) {
				$self->_getFile(false);
			})
			->get('rm', function() use ($self) {
				$self->_deleteFile();
			})
			->get('rn', function() use ($self) {
				$self->_renameFile();
			})
			->get('mk', function() use ($self) {
				$self->_makeFile();
			})
			->get('sv', function() use ($self) {
				$self->_saveFile();
			})
			->otherwise(function($View) {
				$View->display('cheryl');
			});

		$this->tipsy()->run();
	}

	public function _setup() {

		$this->features = (object)$this->features;

		if (file_exists($this->config['includes'])) {
			// use include root at script level
			$this->config['includes'] = realpath($this->config['includes']).DIRECTORY_SEPARATOR;

		} elseif (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['includes'])) {
			// use include root at lib level
			$this->config['includes'] = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['includes'].DIRECTORY_SEPARATOR;

		} else {
			// use current path
			$this->config['includes'] = realpath(__FILE__).DIRECTORY_SEPARATOR;
		}

		if (!$this->config['root']) {
			$this->config['root'] = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR;
		} else {
			$this->config['root'] = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR.$this->config['root'].DIRECTORY_SEPARATOR;

			if (!file_exists($this->config['root'])) {
				@mkdir($this->config['root']);
				@chmod($this->config['root'], 0777);
			}
		}

		if ((function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) || getenv('HTTP_MOD_REWRITE') == 'On') {
			$this->features->rewrite = true;
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

	public function _authenticate() {
		if (!User::users()) {
			// allow anonymouse access. ur crazy!
			return $this->authed = true;
		}

		session_start();

		if ($_SESSION['cheryl-authed']) {
			$this->user = new User($_SESSION['cheryl-username']);
			return $this->authed = true;
		}
	}

	public function _login() {
		$user = User::login();
		if ($user) {
			$this->user = $user;
			$this->authed = $_SESSION['cheryl-authed'] = true;
			$_SESSION['cheryl-username'] = $this->user->username;
			return true;

		} else {
			return false;
		}
	}

	public function _logout() {
		@session_destroy();
		@session_regenerate_id();
		@session_start();
	}

	public function _digestRequest() {
		/*
		if ($this->request['__p']) {
			// we have a page param result
			$url = explode('/',$this->request['__p']);
			$this->features->userewrite = false;

		} else {
			$url = false;
		}
		*/

		$this->request = $this->tipsy()->request()->request();

		// sanatize file/directory requests
		if ($this->request['_d']) {
			$this->request['_d'] = str_replace('/',DIRECTORY_SEPARATOR, $this->request['_d']);
			if ($this->config['features']['snooping']) {
				// just allow them to enter any old damn thing
				$this->requestDir = $this->config['root'].$this->request['_d'];
			} else {
				$this->requestDir = preg_replace('/\.\.\/|\.\//i','',$this->request['_d']);
				//$this->requestDir = preg_replace('@^'.DIRECTORY_SEPARATOR.basename(__FILE__).'@','',$this->requestDir);
				$this->requestDir = $this->config['root'].$this->requestDir;
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

	public function _getFiles($dir, $filters = array()) {

		if ($filters['recursive']) {
			$iter = new \RecursiveDirectoryIterator($dir);
			$iterator = new \RecursiveIteratorIterator(
				$iter,
				\RecursiveIteratorIterator::SELF_FIRST,
				\RecursiveIteratorIterator::CATCH_GET_CHILD
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
			$iterator = new \IteratorIterator($filter);

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

	public function _cTime($file) {
		return (int)trim(shell_exec('stat -f %B '.escapeshellarg($file->getPathname())));
	}

	// do our own type detection
	public function _type($file, $extended = false) {

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

	public function _getFileInfo($file, $extended = false) {
		$path = str_replace(realpath($this->config['root']),'',realpath($file->getPath()));

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
								$d = new \DateTime($exif['DateTime']);
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

			$info['perms'] = $file->getPerms();

		}
		return $info;
	}

	public function _getFile($download = false) {
		if (!$this->authed && !$this->config['readonly']) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}

		if (!$this->requestDir || !is_file($this->requestDir)) {
			header('Status: 404 Not Found');
			header('HTTP/1.0 404 Not Found');
			exit;
		}

		$file = new \SplFileObject($this->requestDir);

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

	public function _requestList() {
		if (!$this->authed && !$this->config['readonly']) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}

		if (!$this->requestDir) {
			header('Status: 404 Not Found');
			header('HTTP/1.0 404 Not Found');
			exit;
		}

		if (is_file($this->requestDir)) {
			$file = new \SplFileObject($this->requestDir);
			$info = $this->_getFileInfo($file, true);
			echo json_encode(array('type' => 'file', 'file' => $info));

		} else {

			if (realpath($this->requestDir) == realpath($this->config['root'])) {
				$path = '';
				$name = '';
			} else {
				$dir = pathinfo($this->requestDir);
				$path = str_replace(realpath($this->config['root']),'',realpath($dir['dirname']));
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

	public function _takeFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config['readonly'] || !$this->user->permission('upload', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;
		}

		foreach ($_FILES as $file) {
			move_uploaded_file($file['tmp_name'],$this->requestDir.DIRECTORY_SEPARATOR.$file['name']);
		}

		echo json_encode(array('status' => true));
	}

	public function _deleteFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config['readonly'] || !$this->user->permission('delete', $this->requestDir)) {
			echo json_encode(array('status' => false, 'message' => 'no permission'));
			exit;
		}

		$status = false;

		if (is_dir($this->requestDir)) {
			if ($this->config['recursiveDelete']) {
				foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->requestDir), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
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

	public function _renameFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config['readonly'] || !$this->user->permission('rename', $this->requestDir)) {
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

	public function _makeFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config['readonly'] || !$this->user->permission('create', $this->requestDir)) {
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

	public function _saveFile() {
		if (!$this->authed) {
			echo json_encode(array('status' => false, 'message' => 'not authenticated'));
			exit;
		}
		if ($this->config['readonly'] || !$this->user->permission('save', $this->requestDir)) {
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

	public function _getConfig() {
		echo json_encode([
			'status' => true,
			'authed' => $this->authed
		]);
	}

	public static function iteratorFilter($current) {
        return !in_array(
            $current->getFileName(),
            self::me()->config['hiddenFiles'],
            true
        );
	}

	public function tipsy() {
		return $this->_tipsy;
	}
}


class CherylFilterIterator extends \FilterIterator {
    public function accept() {
        return Cheryl::iteratorFilter($this->current());
    }
}

class CherylDirectoryIterator extends \DirectoryIterator {
	public function getExtension() {
		if (method_exists(get_parent_class($this), 'getExtension')) {
			$ext = parent::getExtension();
		} else {
			$ext = pathinfo($this->getPathName(), PATHINFO_EXTENSION);
		}
		return strtolower($ext);
	}
}
