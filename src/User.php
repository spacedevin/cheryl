<?php

namespace Cheryl;

class User extends \Tipsy\Resource {

	public static function users() {
		return Cheryl::me()->config['users'];
	}

	public function permission($permission) {
		if ($this->permissions == 'all' || is_array($this->permissions) && $this->permissions[$perimssions] || is_object($this->permissions) && $this->permissions->{$perimssions}) {
			return true;
		} else {
			return false;
		}
	}

	public function exports() {
		return [
			'id' => $this->id_user,
			'username' => $this->username,
			'permissions' => $this->permissions
		];
	}

	public function loadUsername($username) {
		$user = $this->query('select * from `user` where username=? limit 1', [$username])->get(0);
		$this->load($user);
	}

	public function __construct($username = null) {
		$type = strtolower(Cheryl::me()->config['authentication']);

		switch ($type) {
			default:
			case 'simple':
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

			case 'pdo':
				$this->tipsy(\Tipsy\Tipsy::App());
				$this->idVar('id_user')->table('user')->loadUsername($username);

				$perms = $this->db()->query('select * from `permission` WHERE id_user=? limit 1', [$this->id_user])->fetch(\PDO::FETCH_ASSOC);
				if ($perms) {
					$this->permissions = $perms['permission'];
				}
				break;
		}

/*
		if (is_string($u)) {
			$type = strtolower(Cheryl::me()->config['authentication']['type']);

			if ($type == 'simple') {
				foreach (self::users() as $user) {
					if ($user['username'] == $u) {
						$u = $user;
						break;
					}
				}

			} elseif ($type == 'pdo' && class_exists('PDO')) {

				$q = Cheryl::me()->config['authentication']['pdo']->prepare('SELECT * FROM '.Cheryl::me()->config['authentication']['permission_table'].' WHERE user=:username');
				$q->bindValue(':username', $u, \PDO::PARAM_STR);
				$q->execute();
				$rows = $q->fetchAll(\PDO::FETCH_ASSOC);

				$u = array(
					'user' => $u,
					'permissions' => $rows
				);
			} elseif ($type == 'mysql' && function_exists('mysql_connect')) {
				// @todo #18
			}
		}
		*/

	}

	public static function login($username, $password) {
		$username = trim($username);

		if (!$username) {
			return false;
		}

		$type = strtolower(Cheryl::me()->config['authentication']);

		// simple authentication. store users in an array
		if ($type == 'simple') {
			foreach (self::users() as $user) {
				if ($user['username'] == $username) {
					$u = $user;
					break;
				}
			}
var_dump($u);
			if ($u && ((!$u['password_hash'] && !$u['password']) || ($u['password_hash'] && password_verify($password, $u['password_hash']) || ($u['password'] && $password == $u['password'])))) {
				// successfuly send username and password
				return new User($u['username']);
			}

			return false;

		// use php data objects only if we have the libs
		} elseif ($type == 'pdo') {

			//$u = self::query('select * from `user` where username=? limit 1', [$username])->get(0);
			$u = new User($username);

			if ($u->id_user && password_verify($password, $u->password_hash)) {
				return $u;
			}

		// use php data objects only if we have the libs
		} elseif ($type == 'mysql' && function_exists('mysql_connect')) {
			// @todo #18
		} else {
			return false;
		}
	}
}
