<?php

namespace Goteo\Model\User {

    use Goteo\Model;

    class Translate extends \Goteo\Core\Model {

        public
            $user,
            $type,
            $item,
            $ready;

        // tipos de contenidos que se traducen
        public static
            $types = array('project', 'call');

        /*
         *  Para conseguir una instancia de traduccion
         *
        public static function get ($user, $type, $item) {

            if (!in_array($type, self::$types)) {
                return false;
            }

            $query = static::query("
                SELECT *
                FROM    user_translate
                WHERE type = :type
                AND item = :item
                AND user = :user
                ", array(':type' => $type, ':item'=>$item, ':user'=>$user));

            $translate =  $query->fetchObject(__CLASS__);

            if ($translate instanceof \Goteo\Model\User\Translate){
                return $translate;
            } else {
                return false;
            }
        }
         * 
         */
        

        /**
         * Lo usamos para conseguir el tipo de ese item
         * @param varchar(50) $item
         * @return string $type ('project', 'call') or false if not one
         */
	 	public static function get ($id) {
            $array = array ();
            try {
                $query = static::query("SELECT DISTINCT(type) FROM user_translate WHERE item = ?", array($id));
                $types = $query->fetchAll();
                foreach ($types as $type) {
                    $array[] = $type[0];
                }

                if (count($array) !== 1) {
                    return false;
                } else {
                    return $array[0];
                }
                
            } catch(\PDOException $e) {
				throw new \Goteo\Core\Exception($e->getMessage());
            }
        }

        /**
         * Get the translations for a user
         * @param varcahr(50) $id  user identifier
         * @return array of items
         */
	 	public static function getMine ($id, $lang = null, $type = null) {
            $array = array ();
            try {

                $sql = "SELECT type, item FROM user_translate WHERE user = :user";
                $values = array(':user'=>$id);
                
                if (in_array($type, self::$types)) {
                    $sql .= " AND type = :type";
                    $values[':type'] = $type;
                } else {
                    return false;
                }

                $query = static::query($sql, $values);
                $translates = $query->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($translates as $item) {
                    switch ($item['type']) {
                        case 'project':
                            $array[] = Model\Project::get($item['item'], $lang);
                            break;
                        case 'call':
                            $array[] = Model\Call::get($item['item'], $lang);
                            break;
                        default:
                            continue;
                    }
                }

                return $array;
            } catch(\PDOException $e) {
				throw new \Goteo\Core\Exception($e->getMessage());
            }
		}

        // shortcuts para getMine
	 	public static function getMyProjects ($id, $lang = null) {
            return self::getMine($id, $lang, 'project');
        }

	 	public static function getMyCalls ($id, $lang = null) {
            return self::getMine($id, $lang, 'call');
        }


        /**
         * Metodo para sacar los contenidos disponibles para traducir
         * @param varcahr(50) $id  user identifier
         * @return array of items
         */
	 	public static function getAvailables ($type = 'project', $node = null) {

            if (!in_array($type, self::$types)) {
                return false;
            }

            $array = array ();
            try {
                $values = array();
                $sql = "SELECT id, name FROM `{$type}` WHERE status > 1 AND translate = 0";
                if ($type != 'call' && !empty($node)) {
                    $sql .= " AND node = :node";
                    $values[':node'] = $node;
                }
                $query = static::query($sql, $values);
                $avail = $query->fetchAll(\PDO::FETCH_OBJ);
                foreach ($avail as $item) {
                    $array[] = $item;
                }

                return $array;
            } catch(\PDOException $e) {
				throw new \Goteo\Core\Exception($e->getMessage());
            }
		}

		public function validate(&$errors = array()) {
            // Estos son errores que no permiten continuar
            if (empty($this->type) || !in_array($this->type, self::$types))
                $errors[] = 'No hay tipo de contenido o no el tipo no esta habilitado';

            if (empty($this->item))
                $errors[] = 'No hay contenido para asignar';

            if (empty($this->user))
                $errors[] = 'No hay ningun usuario al que asignar';

            //cualquiera de estos errores hace fallar la validacion
            if (!empty($errors))
                return false;
            else
                return true;
        }

		public function save (&$errors = array()) {
            if (!$this->validate($errors)) return false;

            $values = array(
                    ':user'=>$this->user,
                    ':type'=>$this->type,
                    ':item'=>$this->item
                );

			try {
	            $sql = "REPLACE INTO user_translate (user, type, item) VALUES(:user, :type, :item)";
				self::query($sql, $values);
				return true;
			} catch(\PDOException $e) {
				$errors[] = "HA FALLADO!!! " . $e->getMessage();
				return false;
			}

		}

		/**
		 * Quitarle una traduccion al usuario
		 *
		 * @param varchar(50) $user id del usuario
		 * @param INT(12) $id  identificador de la tabla project
		 * @param array $errors 
		 * @return boolean
		 */
		public function remove (&$errors = array()) {
            $values = array(
                    ':user'=>$this->user,
                    ':type'=>$this->type,
                    ':item'=>$this->item
                );

            try {
                self::query("DELETE FROM user_translate WHERE type = :type AND item = :item AND user = :user", $values);
				return true;
			} catch(\PDOException $e) {
                $errors[] = 'HA FALLADO!!! ' . $e->getMessage();
                return false;
			}
		}

        /*
         * Dar por lista una traduccion
         *
        */
		public function ready (&$errors = array()) {
            $values = array(
                    ':user'=>$this->user,
                    ':type'=>$this->type,
                    ':item'=>$this->item
                );

            try {
                if (self::query("UPDATE user_translate SET ready = 1 WHERE type = :type AND item = :item AND user = :user", $values)) {
    				return true;
                }
			} catch(\PDOException $e) {
                $errors[] = 'No se ha podido marcar la traduccion ' . $this->type .':'. $this->item . ' del usuario ' . $this->user . ' como lista. ' . $e->getMessage();
                //Text::get('review-set_ready-fail');
			}
            
            return false;
		}

        /*
         * Reabrir una traduccion
        */
		public function unready (&$errors = array()) {
            $values = array(
                    ':user'=>$this->user,
                    ':type'=>$this->type,
                    ':item'=>$this->item
                );

            try {
                if (self::query("UPDATE user_translate SET ready = 0 WHERE type = :type AND item = :item AND user = :user", $values)) {
    				return true;
                }
			} catch(\PDOException $e) {
                $errors[] = 'No se ha podido reabrir la traduccion ' . $this->type .':'. $this->item . ' del usuario ' . $this->user . '. ' . $e->getMessage();
			}
            
            return false;
		}

        
        /*
         * Lista de usuarios que tienen asignada cierta traduccion
         *
         * //, user_review.ready as ready
         */
        public static function translators ($item, $type = 'project') {

            if (!in_array($type, self::$types)) {
                return false;
            }

             $array = array ();
            try {
               $sql = "SELECT 
                            DISTINCT(user) as id
                        FROM user_translate
                        WHERE type = :type
                        AND item = :item
                        ";
                $query = static::query($sql, array(':type'=>$type, ':item'=>$item));
                foreach ($query->fetchAll(\PDO::FETCH_ASSOC) as $row) {

                    // nombre i avatar
                    $user = \Goteo\Model\User::getMini($row['id']);

                    $array[$row['id']] = $user->name;
                }

                return $array;
            } catch(\PDOException $e) {
				throw new \Goteo\Core\Exception($e->getMessage());
            }
        }

        /*
         * Devuelve true o false si es legal que este usuario haga algo con esta revision
         */
        public static function is_legal ($user, $item, $type = 'project') {

            if (!in_array($type, self::$types)) {
                return false;
            }
            
            $sql = "SELECT user FROM user_translate WHERE user = :user AND type = :type AND item = :item";
            $values = array(
                ':user' => $user,
                ':type' => $type,
                ':item' => $item
            );
            $query = static::query($sql, $values);
            $legal = $query->fetchObject();
            if ($legal->user == $user) {
                return true;
            } else {
                return false;
            }
        }

	}
    
}