<?php

namespace Cheryl\File;

use Cheryl\Cheryl;

class Db extends \Tipsy\Resource {
	public function __construct($username = null) {
		$type = strtolower(Cheryl::me()->config['storage']);

		switch ($type) {
			default:
			case 'local':
				foreach (self::users() as $user) {
					if ($user['username'] == $username) {
						$u = $user;
						break;
					}
				}
				if ($u) {
					foreach($u as $key => $value) {
						$this->{$key} = $value;
					}
				}
				break;

			case 'db':
				$this->tipsy(\Tipsy\Tipsy::App());
				$this->idVar('id_file')->table('file')->loadUsername($username);

				$perms = $this->db()->query('select * from `permission` WHERE id_user=? limit 1', [$this->id_user])->fetch(\PDO::FETCH_ASSOC);
				if ($perms) {
					$this->permissions = $perms['permission'];
				}
				break;
		}
	}

	public static function ls() {
		switch (Cheryl::me()->config['storage']()) {
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
}
