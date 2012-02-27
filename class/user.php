<?php

abstract class abstractUser {
	abstract public function getId();
	abstract public function getUsername();
}

/**
 * This class is a logged in user..
 * It's necessary to be login here! For informations of other user use the alienobject
 * @author skamster
 *
 */
class user extends abstractUser {
	private $username;
	private $name;
	private $lastip;
	private $lastlogin;
	private $currentip;
	private $currentlogin;
	private $id;
	private $welcome = true;
	private $messages = array();
	private $roles = array();
	private $customfields = array();
	private $password;
	public $urow;
	// private $connection;

	public function getUrow() {
		return $this -> urow;
	}

	/**
	 * This do the login
	 * @param String $username is the username
	 * @param String $password is the password
	 * @param unknown_type $connection is a pdo-object with the right informations
	 */
	public function __construct($username, $password, $connection) {
		if ((!empty($username)) && (!empty($password))) {
			$password = hash($GLOBALS["password_hash"], $password);

			$userstatement = $connection -> query('SELECT * FROM user WHERE username="' . $username . '" AND password="' . $password . '"  LIMIT 1;');
			$userrow = $userstatement -> fetch(PDO::FETCH_ASSOC);
			if (sizeof($userrow) == 5) {
				$this -> id = $userrow['uid'];
				$this -> username = $userrow['username'];
				$this -> currentip = getenv('REMOTE_ADDR');
				$this -> lastip = $userrow['lastip'];
				$this -> lastlogin = $userrow['lastlogin'];
				$this -> password = $userrow['password'];
				$datetime = new DateTime($GLOBALS["timezone"]);
				$this -> currentlogin = $datetime -> format("Y-m-d h:s");
				$connection -> exec('UPDATE users SET lastlogin="' . $this -> currentlogin . '", lastip="' . $this -> currentip . '" WHERE username="' . $this -> username . ' AND password="' . $this -> password . '";');
				$_SESSION["user"] = $this;
				$this -> roles = usertools::mkRoleObjects(user::initialiseRoles($this -> id, $connection));

			} else {
				throw new Exception("No user found");
			}
		}
	}

	/**
	 * Temporary method to use it also in alienuser..
	 **/
	public static function initialiseRoles($userId, $connection) {
		//Hole alle Rollen-ID's des Users
		$tmpRids = array();
		foreach ($connection->query('SELECT * FROM user_role WHERE ur_uid="'.$userId.'";') as $tmpRid) {
			$tmpRids[] = $tmpRid['ur_rid'];
		}
		// Create the SQL-Command
		$roleSQL = "SELECT * FROM role WHERE ";
		for ($i = 0; $i < sizeof($tmpRids); $i++) {
			$roleSQL .= "rid=" . $tmpRids[$i];
			if ($i != sizeof($tmpRids) - 1) {
				$roleSQL .= " OR ";
			}
		}
		$roleSQL .= ";";
		$returnArray = array();
		foreach ($connection->query($roleSQL) AS $roleRow) {
			$returnArray[] = $roleRow;
		}
		return $returnArray;
	}

	private function initialiseCustomfields() {
		foreach ($connection->query('SELECT * FROM user_customfields WHERE cf_uid="'.$this->id.'";') as $customfieldrow) {
			$customfield = new customfield();
			$customfield -> setId($customfieldrow[cf_id]);
			$customfield -> setKey($customfieldrow[cf_key]);
			$customfield -> setValue($customfieldrow[cf_value]);
			$this -> customfields[] = $customfield;
		}
	}

	public function getCustomfields() {
		if ($this -> customfields == Null) {
			$this -> initialiseCustomfields();
		}
		return $this -> customfields;
	}

	public function addCustomfield($key, $value) {
		$connection -> exec('INSERT INTO user_customfields (cf_uid, cf_key, cf_value) VALUES (' . $this -> id . ', "' . $key . '", "' . $value . '";');
		$cf = new customfield();
		$cf -> setId($connection -> lastInsertId());
		$cf -> setKey($key);
		$cf -> setValue($value);
		$this -> customfields[] = $cf;
	}

	public function getCustomfieldByKey($key) {
		if ($this -> customfields == Null) {
			$this -> initialiseCustomfields();
		}
		foreach ($this->customfields AS $cf) {
			if ($cf -> getKey() == $key) {
				return $cf;
			}
		}
	}
	public function addRole($roleid){
		$yesorno = true;
		foreach ($this->getRolesIds() as $key => $value) {
			if($value==$roleid){
				$yesorno = false;
			}
		}
		if($yesorno){
			//Inject SQL, 
			
		}
	}

	public function removeCustomfield($id) {
		$connection -> exec('DELETE FROM `meta`.`user_customfields` WHERE `user_customfields`.`cf_id` = ' . $id . ';');
	}

	/**
	 * Change the password of a user
	 * @param $newPassword
	 * @param $oldPassword
	 * @param $connection
	 */
	public function setPassword($newPassword, $oldPassword, $connection) {
		if (hash($GLOBALS["password_hash"], $oldPassword) == $this -> password) {
			usertools::setPassword($this -> username, $newPassword, $connection);
		}
	}
	public function getRolesIds(){
		$roleIds = array();
		foreach($this->roles AS $role){
			$roleIds[] = $role->getId();
		}
		return $roleIds;
	}

	/**
	 * Get the hashed password of a user
	 */
	public function getPassword() {
		return $this -> password;
	}

	/**
	 * Disable the welcome-message
	 */
	public function disableWelcome() {
		$this -> welcome = false;
	}

	/**
	 * Was the user logged in for one time?
	 */
	public function getWelcome() {
		return $this -> welcome;
	}

	/**
	 * Get the id
	 */
	public function getId() {
		return $this -> id;
	}

	/**
	 * Get some messages for the channel (not im-messages, error-messages!)
	 */
	public function getMessages() {
		return $this -> messages;
	}

	/**
	 * Let's get all the roles a user have
	 */
	public function getRoles() {
		return $this -> roles;
	}

	/**
	 * get the username
	 */
	public function getUsername() {
		return $this -> username;
	}

	/**
	 * get the lastLogin as a date
	 */
	public function getLastLogin() {
		return $this -> lastlogin;
	}

	/**
	 * get the last used ip
	 */
	public function getLastIp() {
		return $this -> lastip;
	}

	/**
	 * Check, if the user is realy a user-object
	 * @return string|string
	 */
	public function isValid() {
		if ($_SESSION["user"] == $this) {
			return true;
		}
		return false;
	}

	/**
	 * Do logout the user
	 */
	public function logout() {
		unset($_SESSION["user"]);
	}

}
/**
 * Customfield represent a customfield (short: cf) which use a key/value-princip to make every profile individual
 */
class customfield {
	private $id;
	private $key;
	private $value;

	public function setId($id) {
		$this -> id = $id;
	}
	/**
	 * Get the ID of a cf
	 * @return int id
	 */
	public function getId() {
		return $this -> id;
	}

	public function setKey($key) {
		$this -> key = $key;
	}
	/**
	 * Get the key of a cf
	 * @return String key
	 */
	public function getKey() {
		return $this -> key;
	}

	public function setValue($value) {
		$this -> value = $value;
	}
	/**
	 * Return the value of a cf
	 * @return String value
	 */
	public function getValue() {
		return $this -> value;
	}

}

/**
 * A alienuser is a just-information-user, so just for profiles and so on..
 * @author skamster
 *
 */
class alienuser extends abstractUser {
	private $username;
	private $id;
	private $password;
	private $lastlogin;
	private $roles = array();

	public function getUsername() {
		return $this -> username;
	}

	public function setUsername($username) {
		$this -> username = $username;
	}

	public function getPassword() {
		return $this -> password;
	}

	public function getRoles() {
		return $this -> roles;
	}

	public function setRoles($roles) {
		$this -> roles = $roles;
	}

	public function addRole($role) {
		$roles[] = $role;
	}

	public function setPassword($password) {
		$this -> password = $password;
	}

	public function getId() {
		return $this -> id;
	}

	public function setId($id) {
		$this -> id = $id;
	}

	public function getLastlogin() {
		return $this -> lastlogin;
	}

	public function setLastlogin($lastlogin) {
		$this -> lastlogin = $lastlogin;
	}

	public function getRolesIds() {
		$roleIds = array();
		foreach ($this->roles AS $role) {
			$roleIds[] = $role -> getId();
		}
		return $roleIds;
	}

}
/**
 * usertools is a little collection of static tools to make a faster developement possible..
 * @author skamster
 *
 */
class usertools {
	/**
	 * Register a new user
	 * Was once registerUser
	 * @deprecated
	 * @param unknown_type $name fullname
	 * @param unknown_type $username username
	 * @param unknown_type $password a password
	 * @param unknown_type $role the role.. must be static on public-sites
	 * @param unknown_type $connection pdo-object
	 */
	static public function registerUser2($name, $username, $password, $role, $connection) {
		if (usertools::passwordRequirements($password, $GLOBALS["min_password_length"], $GLOBALS["password_need_specialchars"])) {
			if (!usertools::userExists($username, $connection)) {
				try {
					$password = hash($GLOBALS["password_hash"], $password);
					// TODO check for specialchars!
					$datetime = new DateTime($GLOBALS["timezone"]);
					$connection -> exec("INSERT INTO users (`username`, `password`, `lastlogin`, `lastip`) VALUES ('" . $username . "', '" . $password . "', '" . $datetime -> format('Y-m-d') . "', '" . getenv('REMOTE_ADDR') . "');");
					$userid = $connection -> lastInsertId();
					$connection -> exec("INSERT INTO users_profile (`user_profile_id`, `name`, `schule`, `klasse`, `mail`, `hobbys`) VALUES ('" . $userid . "', '" . $name . "', '', '', '', '');");
					$connection -> exec("INSERT INTO userrole (`buserid`, `broleid`) VALUES ('" . $userid . "', '" . $role . "');");
					return "User " . $username . " was created successfull!";
				} catch (Exception $e) {
					return "Error is happend: " . $e;
				}
			} else {
				return "User does already exist";
			}
		} else {
			return "Your password is to short. It needs at least " . $GLOBALS["min_password_length"] . " signs";
		}
	}

	/**
	 * create a user
	 * @param array $post your post-variable <br />
	 * it must contain<br />
	 * password<br />
	 * password2<br />
	 * username<br />
	 * role<br />
	 * name<br />
	 * @param unknown_type $connection
	 */
	public static function registerUser($post, $connection) {
		if (!empty($post)) {
			if (($post['password'] == $post['password2']) && (!empty($post['email'])) && (usertools::passwordRequirements($post['password'], $GLOBALS["min_password_length"], $GLOBALS["password_need_specialchars"]))) {
				if (!usertools::userExists($post['username'], $connection)) {
					try {

						$password = hash($GLOBALS["password_hash"], $post['password']);
						// TODO check for specialchars!
						$datetime = new DateTime($GLOBALS["timezone"]);
						$connection -> exec("INSERT INTO user (`username`, `password`, `lastlogin`, `lastip`) VALUES ('" . $post['username'] . "', '" . $password . "', '" . $datetime -> format('Y-m-d h:s') . "', '" . getenv('REMOTE_ADDR') . "');");
						$userid = $connection -> lastInsertId();
						$connection -> exec("INSERT INTO user_customfields (`cf_uid`, `cf_key`, `cf_value`) VALUES ('" . $userid . "', 'E-Mail', '" . $post[email] . "');");
						if (!empty($GLOBALS["defaultRole"])) {
							$roleid = usertools::getIdFromRole($GLOBALS["defaultRole"], $connection);
							$connection -> exec("INSERT INTO user_role (`ur_uid`, `ur_rid`) VALUES ('" . $userid . "', '" . $roleid . "');");
						}
						return "0";
					} catch (Exception $e) {
						return "Error is happend: " . $e;
					}
				} else {
					return "User does already exist";
				}
			} else {
				return "Something is strange with your password. Remember: <br /> It needs at least " . $GLOBALS["min_password_length"] . " signs<br />You should type two passwords which are the same (to confirm)";
			}
		}
	}

	/**
	 * get a alienuser
	 * @param $id
	 * @param $connection
	 */
	static public function getAlienUserbyId($id, $connection) {
		try {
			$alien = new alienuser();
			foreach ($connection->query('SELECT * FROM user WHERE uid='.$id.' LIMIT 1;') as $userrow) {
				$alien -> setId($userrow['uid']);
				$alien -> setLastlogin($userrow['lastlogin']);
				$alien -> setUsername($userrow['username']);
				$alien -> setPassword($userrow['password']);
				$alien -> setRoles(usertools::mkRoleObjects(user::initialiseRoles($userrow['uid'], $connection)));

			}
			return $alien;
		} catch (Exception $e) {
			return 'Exception abgefangen: ' . $e -> getMessage();
		}

	}

	static public function getAlienUserbyUsername($username, $connection) {
		$alien = new alienuser();
		foreach ($connection->query('SELECT * FROM user WHERE username="'.$username.'" LIMIT 1;') as $userrow) {
			$alien -> setId($userrow['uid']);
			$alien -> setLastlogin($userrow['lastlogin']);
			$alien -> setUsername($userrow['username']);
			$alien -> setPassword($userrow['password']);
			$alien -> setRoles(usertools::mkRoleObjects(user::initialiseRoles($userrow['uid'], $connection)));
		}

		return $alien;
	}

	/**
	 * Check, if a user exists (with name)
	 * @param unknown_type $username
	 * @param unknown_type $connection
	 */
	static public function userExists($username, $connection) {
		foreach ($connection->query('SELECT * FROM user WHERE username="'.$username.'";') as $userrow) {
			return true;
		}
		return false;
	}

	/**
	 * check, if a user exists (with id)
	 * @param unknown_type $id
	 * @param unknown_type $connection
	 */
	static public function userIdExists($id, $connection) {
		foreach ($connection->query('SELECT * FROM user WHERE id='.$id.';') as $userrow) {
			return true;
		}
		return false;
	}

	/**
	 * What's required for a password? is the password strong enough?
	 * @param unknown_type $password
	 * @param unknown_type $lenght
	 * @param unknown_type $specialchars
	 */
	static public function passwordRequirements($password, $lenght, $specialchars) {
		if (strlen($password) >= $lenght) {
			return true;
		}
		return false;
	}

	/**
	 * contain the user one of the necessary roles? use getRoles of the user-object!
	 * @param $roles
	 * @param $userRoles
	 * @TODO use $roles the same way as userRoles (change in default.php)
	 */
	static public function containRoles($roles, $userRoles) {
		foreach ($roles as $role) {
			foreach ($userRoles as $userRole) {
				if ($role == $userRole -> getRole()) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Change a user
	 * was once called editUser
	 * @deprecated
	 * @param unknown_type $oldUser
	 * @param unknown_type $editUser
	 * @param unknown_type $connection
	 */
	//TODO find a good art for this method! as example, give direct the POST-array!
	static public function editUser2($oldUser, $editUser, $connection) {
		$changes = false;
		$password = hash($GLOBALS["password_hash"], $editUser['password']);
		$changeSQL = array();
		if ($oldUser['name'] != $editUser['name']) {
			array_push($changeSQL, ' name="' . $editUser['name'] . '"');
			$changes = true;
		}
		if ($oldUser['password'] != $password) {
			usertools::setPassword($oldUser['username'], $editUser['password'], $connection);
		}
		if ($oldUser['broleid'] != $editUser['broleid']) {
			usertools::setRole($oldUser['id'], $oldUser['broleid'], $editUser['broleid'], $connection);
		}
		if ($changes) {
			$SQLUpdate = "UPDATE users_profile SET";
			foreach ($changeSQL as $singlechange) {
				$SQLUpdate .= $singlechange;
			}
			$SQLUpdate .= ' WHERE user_profile_id="' . $oldUser["id"] . '";';
			$connection -> exec($SQLUpdate);
		}
	}

	/**
	 *
	 * @param int $oldUserId
	 * @param array $editUser a post-variable which contains a user..
	 * working var-names:
	 * password = cleartext-password<br />
	 * name = the name of the user..<br />
	 * username = the username<br />
	 * role = the new role
	 * @param PDO $connection
	 */
	static public function editUser($oldUserId, $editUser, $connection) {
		if (!empty($editUser)) {
			$fakeOldUser = usertools::getAlienUserbyId($oldUserId, $connection);
			$changes = false;
			$changeSQL = array();
			$remeberExisting = array();
			$userRoleIds = array();
			$getUsedRoles = array();
			foreach (array_keys($_POST) as $key) {
				if (substr($key, 0, 5) == "role_") {
					echo "debug keys: ".$_POST[$key];
					$getUsedRoles[] = $_POST[$key];
				}
			}

			foreach ($fakeOldUser->getRoles() AS $uRole) {
				$userRoleIds[] = $uRole -> getId();
			}
			if ((!empty($editUser['role'])) && ($fakeOldUser -> getUsername() != $editUser['username'])) {
				array_push($changeSQL, ' name="' . $editUser['name'] . '"');
				if ($_SESSION['user'] -> getId() == $oldUserId) {
					$_SESSION['user'] -> setName($editUser['name']);
				}
				$changes = true;
			}
			if (!empty($editUser['password'])) {
				$password = hash($GLOBALS["password_hash"], $editUser['password']);
				if ($fakeOldUser -> getPassword() != $password) {
					usertools::setPassword($fakeOldUser -> getUsername(), $editUser['password'], $connection);
					$changes = true;
				}
			}
			if (sizeof(array_diff($getUsedRoles, $userRoleIds))!=0) {
				usertools::setRole2($fakeOldUser, $getUsedRoles, $connection);
			}
			// usertools::setRole($fakeOldUser -> getId(), $fakeOldUser -> getRoles(), $editUser['roles'], $connection);
			$changes = true;
		}

		if ($changes) {
			$SQLUpdate = "UPDATE users_profile SET";
			foreach ($changeSQL as $singlechange) {
				$SQLUpdate .= $singlechange;
			}
			$SQLUpdate .= ' WHERE user_profile_id="' . $fakeOldUser -> getId() . '";';
			$connection -> exec($SQLUpdate);
		}
	}

	/**
	 * Set new roles
	 * @param int $userid
	 * @param String $oldRole
	 * @param String $newRole
	 * @param PDO $connection
	 **/
	static public function setRole($userid, $oldRole, $newRole, $connection) {
		$oldId = usertools::getIdFromRole($oldRole, $connection);
		$newId = usertools::getIdFromRole($newRole, $connection);
		$connection -> exec('UPDATE user_role SET ur_rid="' . $newId . '" WHERE ur_uid="' . $userid . '" AND ur_rid="' . $oldId . '";');
	}
//new roles = getusedroles
	static public function setRole2($user, $newRoles, $connection) {
		echo "diff: ";
		print_r(array_diff($user->getRolesIds(), $newRoles));
		echo "<br />u-roles: ";
		print_r($user->getRolesIds());
		if(sizeof($newRoles)!=0){
		$removeRoles = array_diff($user->getRolesIds(), $newRoles);
		}
				echo "<br />new used roles: ";
		print_r($newRoles);
						echo "<br />remove roles: ";
		print_r($removeRoles);
		foreach($removeRoles AS $rRole){
			echo "<br />remove? ".$rRole."<br />";
		}
		foreach ($newRoles AS $role) {
			echo "add? ".$role."<br />";
			//$connection -> exec('INSERT INTO user_role (ur_uid , ur_rid) VALUES ('.$user->getId().', '.$role.');');
		}
		
	}

	public static function mkRoleObjects($dbRoles) {
		$roleObjects = array();
		foreach ($dbRoles AS $dbRole) {
			$roleObject = new role();
			$roleObject -> setId($dbRole['rid']);
			$roleObject -> setRole($dbRole['role']);
			$roleObject -> setAdmin($dbRole['r_admin']);
			$roleObjects[] = $roleObject;
		}
		return $roleObjects;
	}

	public static function getIdFromRole($role, $connection) {
		foreach ($connection->query('SELECT * FROM role WHERE role="'.$role.'" LIMIT 1;') as $rolerow) {
			return $rolerow['rid'];
		}
	}

	/**
	 * Set a password
	 * @param unknown_type $username
	 * @param unknown_type $password
	 * @param unknown_type $connection
	 */
	static public function setPassword($username, $password, $connection) {
		if (usertools::passwordRequirements($password, $GLOBALS["min_password_length"], $GLOBALS["password_need_specialchars"])) {
			$password = hash($GLOBALS["password_hash"], $password);
			$connection -> exec('UPDATE users SET password="' . $password . '" WHERE username="' . $username . '";');
		}
	}

	/**
	 * Resolve a username with a id..
	 * @param unknown_type $userid
	 * @param unknown_type $connection
	 */
	static public function getUsernameById($userid, $connection) {
		foreach ($connection->query('SELECT * FROM user WHERE uid="'.$userid.'";') as $userrow) {
			return $userrow['username'];
		}
	}

}

/**
 * Role-Object to make access more easy..
 * it's the same as it is in the db-table
 */
class role {
	private $id;
	private $role;
	private $admin;
	/**
	 * Returns the id of the role
	 * @return int the role-id
	 */
	public function getId() {
		return $this -> id;
	}

	/**
	 * Get the rolename
	 * @return String the rolename
	 */
	public function getRole() {
		return $this -> role;
	}

	/**
	 * Return if it's a adminrole or not
	 * @return boolean if admin or not
	 */
	public function getAdmin() {
		return $this -> admin;
	}

	/**
	 * Set the id (no db!)
	 */
	public function setId($id) {
		$this -> id = $id;
	}

	public function setRole($role) {
		$this -> role = $role;
	}

	public function setAdmin($admin) {
		if ($admin == 0) {
			$this -> admin = false;
		} else if ($admin == 1) {
			$this -> admin = true;
		} else {
			$this -> admin = $admin;
		}
	}

}
?>