<?php

namespace Goteo\Model {

	use Goteo\Library\Text,
        Goteo\Model\Image,
        Goteo\Model\Node,
        Goteo\Model\Project,
        Goteo\Library\Template,
        Goteo\Library\Mail,
        Goteo\Library\Check,
        Goteo\Library\Message;

	class User extends \Goteo\Core\Model {

        public
            $id = false,
            $lang,
            $node, // Nodo al que pertenece
            $nodeData, // Datos del nodo
            $userid, // para el login name al registrarse
            $email,
            $password, // para gestion de super admin
            $name,
            $location,
            $avatar = false,
            $about,
            $contribution,
            $keywords,
            $active,  // si no activo, no puede loguear
            $confirmed,  // si no ha confirmado el email
            $hide, // si oculto no aparece su avatar en ninguna parte (pero sus aportes cuentan)
            $facebook,
            $google,
            $twitter,
            $identica,
            $linkedin,
            $amount,
            $worth,
            $created,
            $modified,
            $interests = array(),
            $webs = array(),
            $roles = array();

        /**
         * Sobrecarga de métodos 'setter'.
         *
         * @param type string	$name
         * @param type string	$value
         */
        public function __set ($name, $value) {
	        if($name == "token") {
	            $this->$name = $this->setToken($value);
	        }
	        if($name == "geoloc") {
	            $this->$name = $this->setGeoloc($value);
	        }
            $this->$name = $value;
        }

        /**
         * Sobrecarga de métodos 'getter'.
         *
         * @param type string $name
         * @return type mixed
         */
        public function __get ($name) {
            if($name == "token") {
	            return $this->getToken();
	        }
	        if($name == "get_numInvested") {
                return self::numInvested($this->id);
            }
	        if($name == "support") {
	            return $this->getSupport();
	        }
	        if($name == "get_numOwned") {
                return self::updateOwned($this->id);
	        }
	        if($name == "get_worth") {
                return self::updateWorth($this->id, $this->amount);
	        }
	        if($name == "get_amount") {
                return self::updateAmount($this->id);
	        }
	        if($name == "geoloc") {
	            return User\Location::get($this->id);
	        }
	        if($name == "geologed") {
	            return User\Location::is_geologed($this->id);
	        }
	        if($name == "unlocable") {
	            return User\Location::is_unlocable($this->id);
	        }
	        if($name == "admin_node") {
	            return \Goteo\Model\Node::getAdminNode($this->id);
	        }
            return $this->$name;
        }

        /**
         * Guardar usuario.
         * Guarda los valores de la instancia del usuario en la tabla.
         *
         * @param type array	$errors     	   Errores devueltos pasados por referencia.
         * @param type array	$skip_validations  Crea el usuario aunque estos campos no sean correctos
         *                                         password, active
         * @return type bool	true|false
         */
        public function save (&$errors = array(),$skip_validations = array()) {
            if($this->validate($errors,$skip_validations)) {
                // Nuevo usuario.
                if(empty($this->id)) {
                    $insert = true;
                    $data[':id'] = $this->id = static::idealiza($this->userid);
                    $data[':name'] = $this->name;
                    $data[':location'] = $this->location;
                    $data[':email'] = $this->email;
                    $data[':token'] = $token = md5(uniqid());
                    if(!in_array('password',$skip_validations)) $data[':password'] = sha1($this->password);
                    $data[':created'] = date('Y-m-d H:i:s');
                    $data[':active'] = true;
                    $data[':confirmed'] = false;
                    $data[':lang'] = \LANG;
                    $data[':node'] = \NODE_ID;

					//active = 1 si no se quiere comprovar
					if(in_array('active',$skip_validations) && $this->active) $data[':active'] = 1;
					else {
                        $URL = \SITE_URL;
						// Obtenemos la plantilla para asunto y contenido
						$template = Template::get(5);

						// Sustituimos los datos
						$subject = $template->title;

						// En el contenido:
						$search  = array('%USERNAME%', '%USERID%', '%USERPWD%', '%ACTIVATEURL%');
						$replace = array($this->name, $this->id, $this->password, $URL . '/user/activate/' . $token);
						$content = \str_replace($search, $replace, $template->text);

						// Activación
						$mail = new Mail();
						$mail->to = $this->email;
						$mail->toName = $this->name;
						$mail->subject = $subject;
						$mail->content = $content;
						$mail->html = false;
						$mail->template = $template->id;
						if ($mail->send($errors)) {
							Message::Info(Text::get('register-confirm_mail-success'));
						} else {
							Message::Error(Text::get('register-confirm_mail-fail', GOTEO_MAIL));
							Message::Error(implode('<br />', $errors));
						}
					}
                }
                else {
                    $data[':id'] = $this->id;

                    // E-mail
                    if(!empty($this->email)) {
                        if(count($tmp = explode('¬', $this->email)) > 1) {
                            $data[':email'] = $tmp[1];
                            $data[':token'] = null;
                        }
                        else {
                            $query = self::query('SELECT email FROM user WHERE id = ?', array($this->id));
                            if($this->email !== $query->fetchColumn()) {
                                $this->token = md5(uniqid()).'¬'.$this->email.'¬'.date('Y-m-d');
                            }
                        }
                    }

                    // Contraseña
                    if(!empty($this->password)) {
                        $data[':password'] = sha1($this->password);
                        static::query('DELETE FROM user_login WHERE user= ?', $this->id);
                    }

                    if(!is_null($this->active)) {
                        $data[':active'] = $this->active;
                    }

                    if(!is_null($this->confirmed)) {
                        $data[':confirmed'] = $this->confirmed;
                    }

                    if(!is_null($this->hide)) {
                        $data[':hide'] = $this->hide;
                    }

                    // Avatar
                    if (is_array($this->avatar) && !empty($this->avatar['name'])) {
                        $image = new Image($this->avatar);
                        // eliminando tabla images
                        $image->newstyle = true; // comenzamosa  guardar nombre de archivo en la tabla

                        if ($image->save($errors)) {
                            $data[':avatar'] = $image->id;
                        } else {
                            unset($data[':avatar']);
                        }
                    }
                    if(is_null($this->avatar)) {
                        $data[':avatar'] = '';
                    }


                    // Perfil público
                    if(isset($this->name)) {
                        $data[':name'] = $this->name;
                    }

                    // Dónde está
                    if(isset($this->location)) {
                        $data[':location'] = $this->location;
                    }

                    if(isset($this->about)) {
                        $data[':about'] = $this->about;
                    }

                    if(isset($this->keywords)) {
                        $data[':keywords'] = $this->keywords;
                    }

                    if(isset($this->contribution)) {
                        $data[':contribution'] = $this->contribution;
                    }

                    if(isset($this->facebook)) {
                        $data[':facebook'] = $this->facebook;
                    }

                    if(isset($this->google)) {
                        $data[':google'] = $this->google;
                    }

                    if(isset($this->twitter)) {
                        $data[':twitter'] = $this->twitter;
                    }

                    if(isset($this->identica)) {
                        $data[':identica'] = $this->identica;
                    }

                    if(isset($this->linkedin)) {
                        $data[':linkedin'] = $this->linkedin;
                    }

                    // Intereses
                    $interests = User\Interest::get($this->id);
                    if(!empty($this->interests)) {
                        foreach($this->interests as $interest) {
                            if(!in_array($interest, $interests)) {
                                $_interest = new User\Interest();
                                $_interest->id = $interest;
                                $_interest->user = $this->id;
                                $_interest->save($errors);
                                $interests[] = $_interest;
                            }
                        }
                    }
                    foreach($interests as $key => $interest) {
                        if(!in_array($interest, $this->interests)) {
                            $_interest = new User\Interest();
                            $_interest->id = $interest;
                            $_interest->user = $this->id;
                            $_interest->remove($errors);
                        }
                    }

                    // Webs
                    static::query('DELETE FROM user_web WHERE user= ?', $this->id);
                    if (!empty($this->webs)) {
                        foreach ($this->webs as $web) {
                            if ($web instanceof User\Web) {
                                $web->user = $this->id;
                                $web->save($errors);
                            }
                        }
                    }
                }

                try {
                    // Construye SQL.
                    if(isset($insert) && $insert == true) {
                        $query = "INSERT INTO user (";
                        foreach($data AS $key => $row) {
                            $query .= substr($key, 1) . ", ";
                        }
                        $query = substr($query, 0, -2) . ") VALUES (";
                        foreach($data AS $key => $row) {
                            $query .= $key . ", ";
                        }
                        $query = substr($query, 0, -2) . ")";
                    }
                    else {
                        $query = "UPDATE user SET ";
                        foreach($data AS $key => $row) {
                            if($key != ":id") {
                                $query .= substr($key, 1) . " = " . $key . ", ";
                            }
                        }
                        $query = substr($query, 0, -2) . " WHERE id = :id";
                    }
                    // Ejecuta SQL.
                    return self::query($query, $data);
            	} catch(\PDOException $e) {
                    $errors[] = "Error al actualizar los datos del usuario: " . $e->getMessage();
                    return false;
    			}
            }
            return false;
        }

		public function saveLang (&$errors = array()) {

			$fields = array(
				'id'=>'id',
				'lang'=>'lang',
				'about'=>'about_lang',
				'keywords'=>'keywords_lang',
				'contribution'=>'contribution_lang'
				);

			$set = '';
			$values = array();

			foreach ($fields as $field=>$ffield) {
				if ($set != '') $set .= ", ";
				$set .= "`$field` = :$field ";
				$values[":$field"] = $this->$ffield;
			}

			try {
				$sql = "REPLACE INTO user_lang SET " . $set;
				self::query($sql, $values);
            	
				return true;
			} catch(\PDOException $e) {
                $errors[] = "El usuario {$this->id} no se ha grabado correctamente. Por favor, revise los datos." . $e->getMessage();
                return false;
			}
		}

        /**
         * Validación de datos de usuario.
         *
         * @param type array $errors               Errores devueltos pasados por referencia.
         * @param type array	$skip_validations  Crea el usuario aunque estos campos no sean correctos
         *                                         password, active
         * @return bool true|false
         */
        public function validate (&$errors = array(), $skip_validations = array()) {
            // Nuevo usuario.
            if(empty($this->id)) {
                // Nombre de usuario (id)
                if(empty($this->userid)) {
                    $errors['userid'] = Text::get('error-register-userid');
                }
                else {
                    $id = self::idealiza($this->userid);
                    $query = self::query('SELECT id FROM user WHERE id = ?', array($id));
                    if($query->fetchColumn()) {
                        $errors['userid'] = Text::get('error-register-user-exists');
                    }
                }

                if(empty($this->name)) {
                    $errors['username'] = Text::get('error-register-username');
                }

                // E-mail
                if (empty($this->email)) {
                    $errors['email'] = Text::get('mandatory-register-field-email');
                } elseif (!Check::mail($this->email)) {
                    $errors['email'] = Text::get('validate-register-value-email');
                } else {
                    $query = self::query('SELECT email FROM user WHERE email = ?', array($this->email));
                    if($query->fetchObject()) {
                        $errors['email'] = Text::get('error-register-email-exists');
                    }
                }

                // Contraseña
                if(!in_array('password',$skip_validations))  {
					if(!empty($this->password)) {
						if(!Check::password($this->password)) {
							$errors['password'] = Text::get('error-register-invalid-password');
						}
					}
					else {
						$errors['password'] = Text::get('error-register-pasword-empty');
					}
				}
                return empty($errors);
            }
            // Modificar usuario.
            else {
                if(!empty($this->email)) {
                    if(count($tmp = explode('¬', $this->email)) > 1) {
                        if($this->email !== $this->token) {
                            $errors['email'] = Text::get('error-user-email-token-invalid');
                        }
                    }
                    elseif(!Check::mail($this->email)) {
                        $errors['email'] = Text::get('error-user-email-invalid');
                    }
                    else {
                        $query = self::query('SELECT id FROM user WHERE email = ?', array($this->email));
                        if($found = $query->fetchColumn()) {
                            if($this->id !== $found) {
                                $errors['email'] = Text::get('error-user-email-exists');
                            }
                        }
                    }
                }
                if(!empty($this->password)) {
                    if(!Check::password($this->password)) {
                        $errors['password'] = Text::get('error-user-password-invalid');
                    }
                }

            }

            if (\str_replace(Text::get('regular-facebook-url'), '', $this->facebook) == '') $this->facebook = '';
            if (\str_replace(Text::get('regular-google-url'), '', $this->google) == '') $this->google = '';
            if (\str_replace(Text::get('regular-twitter-url'), '', $this->twitter) == '') $this->twitter = '';
            if (\str_replace(Text::get('regular-identica-url'), '', $this->identica) == '') $this->identica = '';
            if (\str_replace(Text::get('regular-linkedin-url'), '', $this->linkedin) == '') $this->linkedin = '';



            return (empty($errors['email']) && empty($errors['password']));
        }

        /**
         * Este método actualiza directamente los campos de email y contraseña de un usuario (para gestión de superadmin)
         */
        public function update (&$errors = array()) {
            if(!empty($this->password)) {
                if(!Check::password($this->password)) {
                    $errors['password'] = Text::get('error-user-password-invalid');
                }
            }
            if(!empty($this->email)) {
                if(!Check::mail($this->email)) {
                    $errors['email'] = Text::get('error-user-email-invalid');
                }
                else {
                    $query = self::query('SELECT id FROM user WHERE email = ?', array($this->email));
                    if($found = $query->fetchColumn()) {
                        if($this->id !== $found) {
                            $errors['email'] = Text::get('error-user-email-exists');
                        }
                    }
                }
            }

            if (!empty($errors['email']) || !empty($errors['password'])) {
                return false;
            }

            $set = '';
            $values = array(':id'=>$this->id);

            if (!empty($this->email)) {
                if ($set != '') $set .= ", ";
                $set .= "`email` = :email ";
                $values[":email"] = $this->email;
            }

            if (!empty($this->password)) {
                if ($set != '') $set .= ", ";
                $set .= "`password` = :password ";
                $values[":password"] = sha1($this->password);
            }

            if ($set == '') return false;

            try {
                $sql = "UPDATE user SET " . $set . " WHERE id = :id";
                self::query($sql, $values);

                return true;
            } catch(\PDOException $e) {
                $errors[] = "HA FALLADO!!! " . $e->getMessage();
                return false;
            }

        }

        /**
         * Este método actualiza directamente el campo de idioma preferido
         */
        public function updateLang ($id, $lang) {

            $values = array(':id'=>$id, ':lang'=>$lang);

            try {
                $sql = "UPDATE user SET `lang` = :lang WHERE id = :id";
                self::query($sql, $values);

                return true;
            } catch(\PDOException $e) {
                $errors[] = "HA FALLADO!!! " . $e->getMessage();
                return false;
            }

        }

        /**
         * Este método actualiza directamente el campo de nodo
         */
        public function updateNode (&$errors = array()) {

            $values = array(':id'=>$this->id, ':node'=>$this->node);

            try {
                $sql = "UPDATE user SET `node` = :node WHERE id = :id";
                self::query($sql, $values);

                return true;
            } catch(\PDOException $e) {
                $errors[] = "HA FALLADO!!! " . $e->getMessage();
                return false;
            }

        }


        /**
         * Usuario.
         *
         * @param string $id    Nombre de usuario
         * @return obj|false    Objeto de usuario, en caso contrario devolverÃ¡ 'false'.
         */
        public static function get ($id, $lang = null) {
            try {

                //Obtenemos el idioma de soporte
                $lang=self::default_lang_by_id($id, 'user_lang', $lang);

                $sql = "
                    SELECT
                        user.id as id,
                        user.name as name,
                        user.email as email,
                        user.active as active,
                        IFNULL(user.lang, 'es') as lang,
                        user.location as location,
                        user.avatar as avatar,
                        IFNULL(user_lang.about, user.about) as about,
                        IFNULL(user_lang.contribution, user.contribution) as contribution,
                        IFNULL(user_lang.keywords, user.keywords) as keywords,
                        user.facebook as facebook,
                        user.google as google,
                        user.twitter as twitter,
                        user.identica as identica,
                        user.linkedin as linkedin,
                        user.amount as amount,
                        user.num_patron as num_patron,
                        user.num_patron_active as num_patron_active,
                        user.worth as worth,
                        user.confirmed as confirmed,
                        user.hide as hide,
                        user.created as created,
                        user.modified as modified,
                        user.node as node,
                        user.num_invested as num_invested,
                        user.num_owned as num_owned
                    FROM user
                    LEFT JOIN user_lang
                        ON  user_lang.id = user.id
                        AND user_lang.lang = :lang
                    WHERE user.id = :id
                    ";

                $query = static::query($sql, array(':id' => $id, ':lang' => $lang));
                $user = $query->fetchObject(__CLASS__);

                if (!$user instanceof  \Goteo\Model\User) {
                    return false;
                }

                $user->roles = $user->getRoles();
                $user->avatar = Image::get($user->avatar);

                $user->interests = User\Interest::get($id);

                // campo calculado tipo lista para las webs del usuario
                $user->webs = User\Web::get($id);

                // Nodo
                if (!empty($user->node) && $user->node != \GOTEO_NODE) {
                    $user->nodeData = Node::getMini($user->node);
                }

                // si es traductor cargamos sus idiomas
                if (isset($user->roles['translator'])) {
                    $user->translangs = User\Translate::getLangs($user->id);
                }


                return $user;
            } catch(\PDOException $e) {
                return false;
            }
        }

        // version mini de get para sacar nombre, avatar, email, idioma y nodo
        public static function getMini ($id) {
            try {
                $query = static::query("
                    SELECT
                        id,
                        name,
                        avatar,
                        email,
                        IFNULL(lang, 'es') as lang,
                        node
                    FROM user
                    WHERE id = :id
                    ", array(':id' => $id));
                $user = $query->fetchObject(); // stdClass para qno grabar accidentalmente y machacar todo

                $user->avatar = Image::get($user->avatar);

                return $user;
            } catch(\PDOException $e) {
                return false;
            }
        }

        /**
         * Lista de usuarios.
         *
         * @param  array $filters  Filtros
         * @param  boolean $subnode Filtra además por...
         * @return mixed            Array de objetos de usuario activos|todos.
         */
        public static function getAll ($filters = array(), $subnode = false) {

            $values = array();

            $users = array();

            $sqlFilter = "";
            $sqlOrder = "";
            if (!empty($filters['id'])) {
                $sqlFilter .= " AND id = :id";
                $values[':id'] = $filters['id'];
            }
            if (!empty($filters['name'])) {
                $sqlFilter .= " AND (name LIKE :name OR email LIKE :name)";
                $values[':name'] = "%{$filters['name']}%";
            }
            if (!empty($filters['status'])) {
                $sqlFilter .= " AND active = :active";
                $values[':active'] = $filters['status'] == 'active' ? '1' : '0';
            }
            if (!empty($filters['interest'])) {
                $sqlFilter .= " AND id IN (
                    SELECT user
                    FROM user_interest
                    WHERE interest = :interest
                    ) ";
                $values[':interest'] = $filters['interest'];
            }
            if (!empty($filters['role']) && $filters['role'] != 'user') {
                $sqlFilter .= " AND id IN (
                    SELECT user_id
                    FROM user_role
                    WHERE role_id = :role
                    ) ";
                $values[':role'] = $filters['role'];
            }

            // un admin de central puede filtrar usuarios de nodo
            if($subnode) {
                $sqlFilter .= " AND (node = :node
                    OR id IN (
                        SELECT user_id
                        FROM invest_node
                        WHERE project_node = :node
                    )
                )";
                $values[':node'] = $filters['node'];
            } elseif (!empty($filters['node'])) {
                $sqlFilter .= " AND node = :node";
                $values[':node'] = $filters['node'];
            }

            if (!empty($filters['project'])) {
                $subFilter = $filters['project'] == 'any' ? '' : 'invest.project = :project AND';
                $sqlFilter .= " AND id IN (
                    SELECT user
                    FROM invest
                    WHERE {$subFilter} invest.status IN ('0', '1', '3', '4')
                    ) ";
                if ($filters['project'] != 'any') {
                    $values[':project'] = $filters['project'];
                }
            }

            // por tipo de usuario (un usuario puede ser de más de un tipo)
            if (!empty($filters['type'])) {
                switch ($filters['type']) {
                    case 'creators': // crean proyectos que se publican
                        $sqlFilter .= " AND id IN (
                            SELECT DISTINCT(owner)
                            FROM project
                            WHERE status > 2
                            ) ";
                        break;
                    case 'investors': // aportan correctamente a proyectos
                        $sqlFilter .= " AND id IN (
                            SELECT DISTINCT(user)
                            FROM invest
                            WHERE status IN ('0', '1', '3', '4')
                            ) ";
                        break;
                    case 'supporters': // colaboran con el proyecto
                        $sqlFilter .= " AND id IN (
                            SELECT DISTINCT(user)
                            FROM message
                            WHERE thread IN (
                                SELECT id 
                                FROM message
                                WHERE thread IS NULL
                                AND blocked = 1
                                )
                            ) ";
                        break;
                    case 'consultants': // asesores de proyectos (admins)
                        $sqlFilter .= " AND id IN (
                            SELECT DISTINCT(user)
                            FROM user_project
                            ) ";
                        break;
                    case 'lurkers': // colaboran con el proyecto
                        $sqlFilter .= " AND id NOT IN (
                                SELECT DISTINCT(user)
                                FROM invest
                                WHERE status IN ('0', '1', '3', '4')
                            )
                             AND id NOT IN (
                                SELECT DISTINCT(user)
                                FROM invest
                                WHERE status IN ('0', '1', '3', '4')
                            )
                             AND id NOT IN (
                                SELECT DISTINCT(user)
                                FROM message
                            )
                            ";
                        break;
                }
            }

            // si es solo los usuarios normales, añadimos HAVING
            if ($filters['role'] == 'user') {
                $sqlCR = ", (SELECT COUNT(role_id) FROM user_role WHERE user_id = user.id) as roles";
                $sqlOrder .= " HAVING roles = 0";
            } else {
                $sqlCR = "";
            }

            //el Order
            switch ($filters['order']) {
                case 'name':
                    $sqlOrder .= " ORDER BY name ASC";
                break;
                case 'id':
                    $sqlOrder .= " ORDER BY id ASC";
                break;
                default:
                    $sqlOrder .= " ORDER BY created DESC";
                break;
            }

            $sql = "SELECT
                        id,
                        name,
                        email,
                        active,
                        hide,
                        DATE_FORMAT(created, '%d/%m/%Y %H:%i:%s') as register_date,
                        amount,
                        num_invested,
                        num_owned,
                        node
                        $sqlCR
                    FROM user
                    WHERE id != 'root'
                        $sqlFilter
                    $sqlOrder
                    LIMIT 999
                    ";

            // echo str_replace(array_keys($values), array_values($values),$sql).'<br />';
            $query = self::query($sql, $values);

            foreach ($query->fetchAll(\PDO::FETCH_CLASS, __CLASS__) as $user) {

                $query = static::query("
                    SELECT
                        role_id
                    FROM user_role
                    WHERE user_id = :id
                    ", array(':id' => $user->id));
                foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $role) {
                    $rolevar = $role->role_id;
                    $user->$rolevar = true;
                }

                $users[] = $user;
            }
            return $users;
        }

        /*
         * Listado simple de todos los usuarios
         */
        public static function getAllMini() {

            $list = array();

            $query = static::query("
                SELECT
                    user.id as id,
                    CONCAT(user.name, ' (', user.email, ')') as name
                FROM    user
                ");

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[$item->id] = $item->name;
            }

            return $list;
        }

        /*
         * Listado simple de los usuarios que han creado proyectos
         */
        public static function getOwners() {

            $list = array();

            $query = static::query("
                SELECT
                    user.id as id,
                    user.name as name
                FROM    user
                INNER JOIN project
                    ON project.owner = user.id
                ORDER BY user.name ASC
                ");

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[$item->id] = $item->name;
            }

            return $list;
        }

        /*
         * Consulta simple de si el usuario es impulsor (de proyecto publicado)
         */
        public static function isOwner($user, $published = false, $dbg = false) {

            $sql = "SELECT COUNT(*) FROM project WHERE owner = ?";
            if ($published) {
                $sql .= " AND status > 2";
            }
            $sql .= " ORDER BY created DESC";
            if ($dbg) echo $sql.\trace($user).'<br />';
            $query = self::query($sql, array($user));
            $is = $query->fetchColumn();
            if ($dbg) var_dump($is);
            return !empty($is);
        }

        /*
         * Listado simple de los usuarios Convocadores
         */
        public static function getCallers() {

            $list = array();

            $query = static::query("
                SELECT
                    user.id as id,
                    user.name as name
                FROM    user
                INNER JOIN user_role
                    ON  user_role.user_id = user.id
                    AND user_role.role_id = 'caller'
                ORDER BY user.name ASC
                ");

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[$item->id] = $item->name;
            }

            return $list;
        }

        /*
         * Listado simple de los usuarios Administradores
         * @param boolean $availableonly si es true, solo devuelve los administradores que no tienen asignado ningún nodo
         */
        public static function getAdmins($availableonly = false) {

            $list = array();

            $sql = "
                SELECT
                    user.id as id,
                    user.name as name
                FROM    user
                INNER JOIN user_role
                    ON  user_role.user_id = user.id
                    AND user_role.role_id = 'admin'
                ";
            
            if ($availableonly) {
                $sql .= " WHERE id NOT IN (SELECT distinct(user) FROM user_node)";
            }

            $sql .= " ORDER BY user.name ASC
                ";

            $query = static::query($sql);

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[$item->id] = $item->name;
            }

            return $list;
        }

        /*
         * Listado simple de los usuarios Colaboradores
         */
        public static function getVips() {

            $list = array();

            $query = static::query("
                SELECT
                    user.id as id,
                    user.name as name
                FROM    user
                INNER JOIN user_role
                    ON  user_role.user_id = user.id
                    AND user_role.role_id = 'vip'
                ORDER BY user.name ASC
                ");

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[$item->id] = $item->name;
            }

            return $list;
        }

        /*
         * Listado id-nombre-email de los usuarios que siguen teniendo su email como contraseña
        public static function getWorkshoppers() {

            $list = array();

            $query = static::query("
                SELECT
                    user.id as id,
                    user.name as name,
                    user.email as email
                FROM    user
                WHERE BINARY password = SHA1(user.email)
                ORDER BY user.name ASC
                ");

            foreach ($query->fetchAll(\PDO::FETCH_CLASS) as $item) {
                $list[] = $item;
            }

            return $list;
        }
         */

		/**
		 * Validación de usuario.
		 *
		 * @param string $username Nombre de usuario
		 * @param string $password Contraseña
		 * @return obj|false Objeto del usuario, en caso contrario devolverá 'false'.
		 */
		public static function login ($username, $password) {
            
            $query = self::query("
                    SELECT
                        id
                    FROM user
                    WHERE BINARY id = :username
                    AND BINARY password = :password",
				array(
					':username' => trim($username),
                    // si la contraseña ya viene en formato sha1 no la encriptamos
					':password' => (\is_sha1($password)) ? $password : sha1($password)
				)
			);

			if($row = $query->fetch()) {
			    $user = static::get($row['id']);
			    if($user->active) {
			        return $user;
			    } else {
			        Message::Error(Text::get('user-account-inactive'));
			    }
			}
			return false;
		}

		/**
		 * Comprueba si el usuario está identificado.
		 *
		 * @return boolean
		 */
		public static function isLogged () {
			return !empty($_SESSION['user']);
		}

        /**
         * Comprueba si el usuario es administrador
         * @param   type varchar(50)  $id   Usuario admin
         * @return  type bool true/false
         */
        public function isAdmin ($id) {

            $sql = "
                SELECT
                    user.id as id
                FROM    user
                INNER JOIN user_role
                    ON  user_role.user_id = user.id
                    AND user_role.role_id IN ('admin', 'superadmin')
                WHERE user.id = :id
                LIMIT 1
                ";
            $query = static::query($sql, array(':id' => $id));
            $res = $query->fetchColumn();
            return ($res == $id);
        }

		/**
		 * Refresca la sesión.
		 * (Utilizar después de un save)
		 *
		 * @return type object	User
		 */
		public static function flush () {
    		if(static::isLogged()) {
    			return $_SESSION['user'] = self::get($_SESSION['user']->id);
    		}
    	}

		/**
		 * Verificacion de recuperacion de contraseña
		 *
		 * @param string $username Nombre de usuario
		 * @param string $email    Email de la cuenta
		 * @return boolean true|false  Correctos y mail enviado
		 */
		public static function recover ($email = null) {
            $URL = \SITE_URL;
            $query = self::query("
                    SELECT
                        id,
                        name,
                        email
                    FROM user
                    WHERE BINARY email = :email
                    ",
				array(
					':email'    => trim($email)
				)
			);
			if($row = $query->fetchObject()) {
                // tenemos id, nombre, email
                // genero el token
                $token = md5(uniqid()).'¬'.$row->email.'¬'.date('Y-m-d');
                self::query('UPDATE user SET token = :token WHERE id = :id', array(':id' => $row->id, ':token' => $token));

                // Obtenemos la plantilla para asunto y contenido
                $template = Template::get(6);

                // Sustituimos los datos
                $subject = $template->title;

                // En el contenido:
                $search  = array('%USERNAME%', '%USERID%', '%RECOVERURL%');
                $replace = array($row->name, $row->id, $URL . '/user/recover/' . \mybase64_encode($token));
                $content = \str_replace($search, $replace, $template->text);
                // Email de recuperacion
                $mail = new Mail();
                $mail->to = $row->email;
                $mail->toName = $row->name;
                $mail->subject = $subject;
                $mail->content = $content;
                $mail->html = true;
                $mail->template = $template->id;
                if ($mail->send($errors)) {
                    return true;
                }
			}
			return false;
		}

		/**
		 * Verificacion de darse de baja
		 *
		 * @param string $email    Email de la cuenta
		 * @return boolean true|false  Correctos y mail enviado
		 */
		public static function leaving ($email, $message = null) {
            $URL = \SITE_URL;
            $query = self::query("
                    SELECT
                        id,
                        name,
                        email
                    FROM user
                    WHERE BINARY email = :email
                    ",
				array(
					':email'    => trim($email)
				)
			);
			if($row = $query->fetchObject()) {
                // tenemos id, nombre, email
                // genero el token
                $token = md5(uniqid()).'¬'.$row->email.'¬'.date('Y-m-d');
                self::query('UPDATE user SET token = :token WHERE id = :id', array(':id' => $row->id, ':token' => $token));

                // Obtenemos la plantilla para asunto y contenido
                $template = Template::get(9);

                // Sustituimos los datos
                $subject = $template->title;

                // En el contenido:
                $search  = array('%USERNAME%', '%URL%');
                $replace = array($row->name, SEC_URL . '/user/leave/' . \mybase64_encode($token));
                $content = \str_replace($search, $replace, $template->text);
                // Email de recuperacion
                $mail = new Mail();
                $mail->to = $row->email;
                $mail->toName = $row->name;
                $mail->subject = $subject;
                $mail->content = $content;
                $mail->html = true;
                $mail->template = $template->id;
                $mail->send($errors);
                unset($mail);

                // email a los de goteo
                $mail = new Mail();
                $mail->to = \GOTEO_MAIL;
                $mail->toName = 'Admin Goteo';
                $mail->subject = 'El usuario ' . $row->id . ' se da de baja';
                $mail->content = '<p>Han solicitado la baja para el mail <strong>'.$email.'</strong> que corresponde al usuario <strong>'.$row->name.'</strong>';
                if (!empty($message)) $mail->content .= 'y ha dejado el siguiente mensaje:</p><p> ' . $message;
                $mail->content .= '</p>';
                $mail->fromName = "{$row->name}";
                $mail->from = $row->email;
                $mail->html = true;
                $mail->template = 0;
                $mail->send($errors);
                unset($mail);

                return true;
			}
			return false;
		}

    	/**
    	 * Guarda el Token y envía un correo de confirmación.
    	 *
    	 * Usa el separador: ¬
    	 *
    	 * @param type string	$token	Formato: '<md5>¬<email>'
    	 * @return type bool
    	 */
    	private function setToken ($token) {
            $URL = \SITE_URL;
            if(count($tmp = explode('¬', $token)) > 1) {
                $email = $tmp[1];
                if(Check::mail($email)) {

                    // Obtenemos la plantilla para asunto y contenido
                    $template = Template::get(7);

                    // Sustituimos los datos
                    $subject = $template->title;

                    // En el contenido:
                    $search  = array('%USERNAME%', '%CHANGEURL%');
                    $replace = array($this->name, $URL . '/user/changeemail/' . \mybase64_encode($token));
                    $content = \str_replace($search, $replace, $template->text);



                    $mail = new Mail();
                    $mail->to = $email;
                    $mail->toName = $this->name;
                    $mail->subject = $subject;
                    $mail->content = $content;
                    $mail->html = true;
                    $mail->template = $template->id;
                    $mail->send();

                    return self::query('UPDATE user SET token = :token WHERE id = :id', array(':id' => $this->id, ':token' => $token));
                }
            }
    	}

    	/**
    	 * Token de confirmación.
    	 *
    	 * @return type string
    	 */
    	private function getToken () {
            $query = self::query('SELECT token FROM user WHERE id = ?', array($this->id));
            return $query->fetchColumn(0);
    	}

    	/**
    	 * Asigna el usuario a una Geolocalización
    	 *
    	 * @param type int (id geolocation)
    	 * @return type int (id geolocation)
    	 */
    	private function setGeoloc ($loc) {
            
            $errors = array();
            
            $geoloc = new User\Location(array(
                'user' => $this->id,
                'location' => $loc
            ));
            
            if ($geoloc->save($errors)) {
                return $loc;
            } else {
                @mail(\GOTEO_FAIL_MAIL, 'Geoloc fail en ' . SITE_URL, 'Error al asignar location a usuario en ' . __FUNCTION__ . '. '. implode (', ', $errors));
                return false;
            }
    	}

        /**
         * Cofinanciación.
         *
         * @return type array
         */
    	private function getSupport () {
            $query = self::query("SELECT DISTINCT(project) FROM invest WHERE user = ? AND status IN ('0', '1', '3')", array($this->id));
            $projects = $query->fetchAll(\PDO::FETCH_ASSOC);
            $query = self::query("SELECT SUM(amount), COUNT(id) FROM invest WHERE user = ? AND status IN ('0', '1', '3')", array($this->id));
            $invest = $query->fetch();
            return array('projects' => $projects, 'amount' => $invest[0], 'invests' => $invest[1]);
        }

        /*
         * Método para calcular el número de proyectos cofinanciados
         * Actualiza el campo
         */
    	public static function numInvested ($id) {
            $query = self::query("SELECT num_invested as old_num_invested, (SELECT COUNT(DISTINCT(project)) FROM invest WHERE user = :user AND status IN ('0', '1', '3', '4')) as num_invested FROM user WHERE id = :user", array(':user' => $id));
            $inv = $query->fetchObject();
            if($inv->old_num_invested != $inv->num_invested) {
                self::query("UPDATE
                        user SET
                        num_invested = :nproj
                     WHERE id = :id", array(':id' => $id, ':nproj' => $inv->num_invested));
            }
            return $inv->num_invested;
        }

	    /**
    	 * Recalcula y actualiza el nivel de meritocracia
    	 * Segun el actual importe cofinanciado por el usuario
         *
         * @param $amount int
    	 * @return result boolean
    	 */
    	public static function updateWorth ($user, $amount) {
            $query = self::query('SELECT worth as old_worth, (SELECT id FROM worthcracy WHERE amount <= :amount ORDER BY amount DESC LIMIT 1) as new_worth FROM user WHERE id = :user', array(':amount'=>$amount, ':user'=>$user));
            $usr = $query->fetchObject();
            if ($usr->old_worth != $usr->new_worth) {
                self::query('UPDATE user SET worth = :worth WHERE id = :id', array(':id' => $user, ':worth' => $usr->new_worth));
            }
            return $usr->new_worth;
        }

        /**
    	 * Número de proyectos publicados
    	 *
    	 * @return type int	Count(id)
    	 */
        public static function updateOwned ($user) {
            $query = self::query('SELECT num_owned as old_num, (SELECT COUNT(id) FROM project WHERE owner = :user AND status > 2) as new_num FROM user WHERE id = :user', array(':user'=>$user));
            $num = $query->fetchObject();
            if ($num->old_num != $num->new_num) {
                self::query('UPDATE user SET num_owned = :num WHERE id = :id', array(':id' => $user, ':num' => $num->new_num));
            }
            return $num->new_num;
        }

        /**
    	 * Actualiza Cantidad aportada
    	 *
         * @param user string Id del usuario
    	 * @return type int	Count(id)
    	 */
    	public static function updateAmount ($user) {
            $query = self::query("SELECT amount as old_amount, (SELECT SUM(invest.amount) FROM invest WHERE user = :user AND status IN ('0', '1', '3')) as new_amount FROM user WHERE id = :user", array(':user'=>$user));
            $amount = $query->fetchObject();
            if ($amount->old_amount != $amount->new_amount) {
                self::query('UPDATE user SET amount = :amount WHERE id = :id', array(':id' => $user, ':amount' => $amount->new_amount));
            }
            return $amount->new_amount;
        }

        /**
         * Valores por defecto actuales para datos personales
         *
         * @return type array
         */
        public static function getPersonal ($id) {
            $query = self::query('SELECT
                                      contract_name,
                                      contract_nif,
                                      phone,
                                      address,
                                      zipcode,
                                      location,
                                      country
                                  FROM user_personal
                                  WHERE user = ?'
                , array($id));

            $data = $query->fetchObject();
            return $data;
        }

        /**
         * Actualizar los valores personales
         *
         * @params force boolean  (REPLACE data when true, only if empty when false)
         * @return type booblean
         */
        public static function setPersonal ($user, $data = array(), $force = false, &$errors = array()) {

            if ($force) {
                // actualizamos los datos
                $ins = 'REPLACE';
            } else {
                // solo si no existe el registro
                $ins = 'INSERT';
                $query = self::query('SELECT user FROM user_personal WHERE user = ?', array($user));
                if ($query->fetchColumn(0) == $user) {
                    return false;
                }
            }


            $fields = array(
                  'contract_name',
                  'contract_nif',
                  'phone',
                  'address',
                  'zipcode',
                  'location',
                  'country'
            );

            $values = array();
            $set = '';

            foreach ($data as $key=>$value) {
                if (in_array($key, $fields)) {
                    $values[":$key"] = $value;
                    if ($set != '') $set .= ', ';
                    $set .= "$key = :$key";
                }
            }

            if (!empty($values) && $set != '') {
                    $values[':user'] = $user;
                    $sql = "$ins INTO user_personal SET user = :user, " . $set;

                try {
                    self::query($sql, $values);
                    return true;

                } catch (\PDOException $e) {
                    $errors[] = "FALLO al gestionar el registro de datos personales " . $e->getMessage();
                    return false;
                }
            }


        }

        /**
         * Preferencias de notificacion
         *
         * @return type array
         */
        public static function getPreferences ($id) {
            $query = self::query('SELECT
                                      updates,
                                      threads,
                                      rounds,
                                      mailing,
                                      email,
                                      tips,
                                      comlang
                                  FROM user_prefer
                                  WHERE user = ?'
                , array($id));

            $data = $query->fetchObject();
            return $data;
        }

        /**
         * Actualizar las preferencias de notificación
         *
         * @return type booblean
         */
        public static function setPreferences ($user, $data = array(), &$errors = array()) {

            $values = array();
            $set = '';

            foreach ($data as $key=>$value) {
                $values[":$key"] = $value;
                if ($set != '') $set .= ', ';
                $set .= "$key = :$key";
            }

            if (!empty($values) && $set != '') {
                    $values[':user'] = $user;
                    $sql = "REPLACE INTO user_prefer SET user = :user, " . $set;

                try {
                    self::query($sql, $values);
                    return true;

                } catch (\PDOException $e) {
                    $errors[] = "FALLO al gestionar las preferencias de notificación " . $e->getMessage();
                    return false;
                }
            }


        }

		private function getRoles () {

            $roles = array();
            
		    $query = self::query('
		    	SELECT
		    		role.id as id,
		    		role.name as name
		    	FROM role
		    	JOIN user_role ON role.id = user_role.role_id
		    	WHERE user_id = ?
		    ', array($this->id));
            foreach ($query->fetchAll(\PDO::FETCH_OBJ) as $rol) {
                $roles[$rol->id] = $rol;
            }
            // añadimos el de usuario normal
            $roles['user'] = (object) array('id'=>'user', 'name'=>'Usuario registrado');
            
            return $roles;

		}

        /* listado de roles */
		public static function getRolesList () {

            $roles = array();

		    $query = self::query('SELECT role.id as id, role.name as name FROM role ORDER BY role.name');
            foreach ($query->fetchAll(\PDO::FETCH_OBJ) as $rol) {
                $roles[$rol->id] = $rol->name;
            }
            return $roles;

		}


        /*
         * Lista de proyectos cofinanciados
         */
        public static function invested($user, $publicOnly = true)
        {
            $debug = false;

            $projects = array();
            $values = array();
            $values[':lang'] = \LANG;
            $values[':user'] = $user;

            if(self::default_lang(\LANG)=='es') {
                $different_select=" IFNULL(project_lang.description, project.description) as description";
            }
            else {
                $different_select=" IFNULL(project_lang.description, IFNULL(eng.description, project.description)) as description";
                $eng_join=" LEFT JOIN project_lang as eng
                                ON  eng.id = project.id
                                AND eng.lang = 'en'";
            }

            if ($publicOnly) {
                $sqlFilter = " AND project.status > 2";
            }

            $sql ="
                SELECT
                    project.id as project,
                    $different_select,
                    project.status as status,
                    project.published as published,
                    project.created as created,
                    project.updated as updated,
                    project.success as success,
                    project.closed as closed,
                    project.mincost as mincost,
                    project.maxcost as maxcost,
                    project.amount as amount,
                    project.image as image,
                    project.gallery as gallery,
                    project.num_investors as num_investors,
                    project.num_messengers as num_messengers,
                    project.num_posts as num_posts,
                    project.days as days,
                    project.name as name,
                    user.id as user_id,
                    user.name as user_name,
                    project_conf.noinvest as noinvest,
                    project_conf.one_round as one_round,
                    project_conf.days_round1 as days_round1,
                    project_conf.days_round2 as days_round2
                FROM  project
                INNER JOIN invest
                    ON project.id = invest.project
                    AND invest.user = :user
                    AND invest.status IN ('0', '1', '3', '4')
                INNER JOIN user
                    ON user.id = project.owner
                LEFT JOIN project_conf
                    ON project_conf.project = project.id
                LEFT JOIN project_lang
                    ON  project_lang.id = project.id
                    AND project_lang.lang = :lang
                $eng_join
                WHERE project.status < 7
                $sqlFilter
                ORDER BY  project.status ASC, project.created DESC
                ";

            $sql .= "LIMIT 12";

            if ($debug) {
                echo \trace($values);
                echo $sql;
                die;
            }

            $query = self::query($sql, $values);
            foreach ($query->fetchAll(\PDO::FETCH_OBJ) as $proj) {
                $projects[] = Project::getWidget($proj);
            }
            return $projects;
        }

        /**
         * Metodo para cancelar la cuenta de usuario
         * Nos e borra nada, se desactiva y se oculta.
         *
         * @param string $userId
         * @return bool
         */
        public static function cancel($userId) {

            if (self::query('UPDATE user SET active = 0, hide = 1 WHERE id = :id', array(':id' => $userId))) {
                return true;
            } else {
                return false;
            }

        }

        /**
         * Metodo para saber si el usuario ha bloqueado este envio de mailing
         *
         * @param string $userId
         * @param string $mailingCode Tipo de envio de mailing. Default: newsletter
         * @return bool
         */
        public static function mailBlock($userId, $mailingCode = 'mailing') {

            $values = array(':user' => $userId);

            $sql = "SELECT user_prefer.{$mailingCode} as blocked FROM user_prefer WHERE user_prefer.user = :user";

            $query = self::query($sql, $values);
            $block = $query->fetchColumn();
            if ($block == 1) {
                return true;
            } else {
                return false;
            }

        }

        /*
         * Para saber si un usuario tiene traducción en cierto idioma
         * @return: boolean
         */
        public static function isTranslated($id, $lang) {
            $sql = "SELECT id FROM user_lang WHERE id = :id AND lang = :lang";
            $values = array(
                ':id' => $id,
                ':lang' => $lang
            );
            $query = static::query($sql, $values);
            $its = $query->fetchObject();
            if ($its->id == $id) {
                return true;
            } else {
                return false;
            }
        }


        /*
         * Consulta simple para saber si un usuario ha cofinanciado en algun proyecto de un impulsor
         * @return: boolean
         */
        public static function isInvestor($user, $owner, $dbg = false) {
            $sql = "SELECT COUNT(*)
            FROM project
            INNER JOIN invest
                ON invest.project = project.id
                AND invest.status IN ('0', '1', '3', '4')
                AND invest.user = :user
            WHERE project.owner = :owner
            AND project.status > 2
            ";
            $values = array(
                ':user' => $user,
                ':owner' => $owner
            );
            if ($dbg) echo str_replace(array_keys($values), array_values($values),$sql).'<br />';
            $query = static::query($sql, $values);
            $is = $query->fetchColumn();
            if ($dbg) var_dump($is);
            return !empty($is);
        }

        /*
         * Consulta simple para saber si un usuario ha participado en los mensajes de algun proyecto de un impulsor
         * @return: boolean
         */
        public static function isParticipant($user, $owner, $dbg = false) {
            $sql = "SELECT COUNT(*)
            FROM project
            INNER JOIN message
                ON message.project = project.id
                AND message.user = :user
            WHERE project.owner = :owner
            AND project.status > 2
            ";
            $values = array(
                ':user' => $user,
                ':owner' => $owner
            );
            if ($dbg) echo str_replace(array_keys($values), array_values($values),$sql).'<br />';
            $query = static::query($sql, $values);
            $is = $query->fetchColumn();
            if ($dbg) var_dump($is);
            return !empty($is);
        }


    }
}