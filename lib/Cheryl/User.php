<?php

if (!class_exists('Cheryl_User')) {
	class Cheryl_User extends Cheryl_User_Base {
		public static function users() {
			return Cheryl::me()->config->users;
		}

		public static function login() {
			if (Cheryl::me()->request['__username']) {
				// log in attempt
				if (Cheryl::me()->request['__hash']) {
					$pass = Cheryl::me()->request['__hash'];
				} else {
					$pass = self::password(Cheryl::me()->request['__password']);
				}
			}

			foreach (self::users() as $user) {
				if ($user->username == Cheryl::me()->request['__username']) {
					$u = $user;
					break;
				}
			}

			if ($u && (!$u->password || $pass == $u->password)) {
				// successfuly send username and password
				return new Cheryl_User($u);
			}

			return false;			
		}
	}
}


class Cheryl_User_Base {
	public static function users() {
		return array();
	}
	
	public static function login() {
		return false;
	}

	public function permission($permission) {
		if ($this->permissions == 'all' || is_array($this->permissions) && $this->permissions[$perimssions] || is_object($this->permissions) && $this->permissions->{$perimssions}) {
			return true;
		} else {
			return false;
		}
	}
}
