<?php

namespace Goteo\Model\Blog\Post {

    class tag extends \Goteo\Core\Model {

        public
            $id,
            $name,
            $blog;

        /*
         *  Devuelve datos de una comentario
         */
        public static function get ($id) {
                $query = static::query("
                    SELECT
                        id,
                        name,
                        blog
                    FROM    tag
                    WHERE id = :id
                    ", array(':id' => $id));

                return $query->fetchObject(__CLASS__);
        }

        /*
         * Lista de tags
         * de un post si recibe el parametro
         */
        public static function getAll ($post = null) {

            $list = array();

            $sql = "
                SELECT
                    tag.id,
                    tag.name
                FROM    tag
                ";
            
            if (!empty($post)) {
                $sql .= "INNER JOIN post_tag
                    ON tag.id = post_tag.tag
                    AND post_tag.post = ?
                    ";
            }

            $sql .= "ORDER BY tag.name ASC";
            
            $query = static::query($sql, array($post));
                
            foreach ($query->fetchAll(\PDO::FETCH_CLASS, __CLASS__) as $tag) {
                $list[$tag->id] = $tag->name;
            }

            return $list;
        }

        public function validate (&$errors = array()) { 
            if (empty($this->name))
                $errors[] = 'Falta nombre';
                //Text::get('validate-tag-name');

            if (empty($errors))
                return true;
            else
                return false;
        }

        // para añadir un nuevo tag al blog
        public function save (&$errors = array()) {
            if (!$this->validate($errors)) return false;

            $fields = array(
                'id',
                'name',
                'blog'
                );

            $set = '';
            $values = array();

            foreach ($fields as $field) {
                if ($set != '') $set .= ", ";
                $set .= "`$field` = :$field ";
                $values[":$field"] = $this->$field;
            }

            try {
                $sql = "REPLACE INTO tag SET " . $set;
                self::query($sql, $values);
                if (empty($this->id)) $this->id = self::insertId();

                return true;
            } catch(\PDOException $e) {
                $errors[] = "No se ha guardado correctamente. " . $e->getMessage();
                return false;
            }
        }

        // para añadir un nuevo tag al post
        public function assign (&$errors = array()) {
            if (!$this->validate($errors)) return false;

            $fields = array(
                'tag',
                'post'
                );

            $set = '';
            $values = array();

            foreach ($fields as $field) {
                if ($set != '') $set .= ", ";
                $set .= "`$field` = :$field ";
                $values[":$field"] = $this->$field;
            }

            try {
                $sql = "REPLACE INTO post_tag SET " . $set;
                self::query($sql, $values);
                if (empty($this->id)) $this->id = self::insertId();

                return true;
            } catch(\PDOException $e) {
                $errors[] = "No se ha guardado correctamente. " . $e->getMessage();
                return false;
            }
        }

        /*
         * Para eliminar un tag
         */
        public static function delete ($id) {
            
            $sql = "DELETE FROM tag WHERE id = :id";
            if (self::query($sql, array(':id'=>$id))) {
                return true;
            } else {
                return false;
            }

        }

        /*
         * Para quitar un tag de un post
         */
        public static function remove ($tag) {

            $sql = "DELETE FROM post_tag WHERE tag = :tag";
            if (self::query($sql, array(':tag'=>$tag))) {
                return true;
            } else {
                return false;
            }

        }

    }
    
}