<?php

namespace Goteo\Model\User {

    use Goteo\Library\Check,
        Goteo\Library\Text;


    class Donor extends \Goteo\Core\Model {

        public
        $user,
        $amount,
        $name,
        $surname,
        $nif,
        $address,
        $zipcode,
        $location,
        $country,
        $year,
        $edited = 0,
        $confirmed = 0,
        $pdf = null,
        $dates = array();

        /**
         * Get invest data if a user is a donor
         * @param varcahr(50) $id  user identifier
         */
        public static function get($id, $year = null) {

            if (empty($year)) return null;

            try {

                // si ya ha introducido los datos, sacamos de user_donation
                $sql = "SELECT * FROM user_donation WHERE user = :id AND year = :year";
                $query = static::query($sql, array(':id' => $id, ':year' => $year));
                if ($donation = $query->fetchObject(__CLASS__)) {
                    return $donation;
                } else {
                    // sino sacamos de invest_address
                    $sql = "SELECT
                                    invest.user as user,
                                    SUM(invest.amount) as amount
                                FROM  invest
                                INNER JOIN project
                                    ON project.id = invest.project
                                    AND (project.passed IS NOT NULL AND project.passed != '0000-00-00')
                                INNER JOIN user ON user.id = invest.user
                                LEFT JOIN invest_address ON invest_address.invest = invest.id
                                WHERE   invest.user = :id
                                AND invest.status IN ('1', '3')
                                AND (invest.invested >= '{$year}-01-01' AND invest.invested <= '{$year}-12-31')
                                GROUP BY invest.user
                            ";
                    $query = static::query($sql, array(':id' => $id));
                    if ($donation = $query->fetchObject(__CLASS__)) {
                        $donation->year = $year;
                    } else {
                        $donation = null;
                    }
                    return $donation;
                }


            } catch (\PDOException $e) {
                throw new \Goteo\Core\Exception($e->getMessage());
            }
        }

        /* 
        * Listado de datos de donativos que tenemos
        * @param csv boolean si procesamos los datos para el excel
        */
        public function getList($filters = array(), $csv = false) {


            // naturaleza según tipo de persona (F, J)
            $nt = array(
                    'nif' => 'F',
                    'nie' => 'F',
                    'cif' => 'J'
                );
            // porcentaje segun tipo de persona (25, 35)
            $pt = array(
                    'nif' => '25',
                    'nie' => '25',
                    'cif' => '35'
                );

            $year = empty($filter['year']) ? date('Y') : $filter['year'];

            $values = array();

            $list = array();

            $sqlFilter = '';
            if (!empty($filters['user'])) {
                $user = $filters['user'];
                $sqlFilter .= " AND (user.id LIKE :user OR user.name LIKE :user OR user.email LIKE :user)";
                $values[':user'] = "%{$user}%";
            }

            if (!empty($filters['status'])) {
                switch ($filters['status']) {
                    case 'pending': // Pendientes de revisar
                        $sqlFilter .= " AND (user_donation.edited IS NULL OR user_donation.edited = 0)";
                        break;
                    case 'edited': // Revisados no confirmados
                        $sqlFilter .= " AND user_donation.edited = 1 AND (user_donation.confirmed IS NULL OR user_donation.confirmed = 0)";
                        break;
                    case 'confirmed': // Confirmados
                        $sqlFilter .= " AND user_donation.confirmed = 1";
                        break;
                    case 'emited': // Certificado emitido
                        $sqlFilter .= " AND (user_donation.pdf IS NOT NULL OR user_donation.pdf != '')";
                        break;
                    case 'notemited': //Confirmado pero no emitido
                        $sqlFilter .= " AND user_donation.confirmed = 1 AND (user_donation.pdf IS NULL OR user_donation.pdf = '')";
                        break;
                }
            }

            $sql = "SELECT
                        user.id as id,
                        user.email,
                        user_donation.name as name,
                        user_donation.nif as nif,
                        user_donation.address as address,
                        user_donation.zipcode as zipcode,
                        user_donation.location as location,
                        user_donation.country as country,
                        user_donation.amount as amount,
                        user_donation.numproj as numproj,
                        CONCAT('{$year}') as year,
                        user_donation.edited as edited,
                        user_donation.confirmed as confirmed,
                        user_donation.pdf as pdf
                FROM  user_donation
                INNER JOIN user ON user.id = user_donation.user
                WHERE user_donation.year = '{$year}'
                $sqlFilter
                ORDER BY user.email ASC";

            $query = self::query($sql, $values);
            $items = $query->fetchAll(\PDO::FETCH_OBJ);
            foreach ($items as $item) {
                // tipo de persona segun nif/nie/cif
                $type = '';
                Check::nif($item->nif, $type);
                $per = $pt[$type];
                $nat = $nt[$type];

// NIF;NIF_REPRLEGAL;Nombre;Provincia;CLAVE;PORCENTAJE;VALOR;EN_ESPECIE;COMUNIDAD;PORCENTAJE_CA;NATURALEZA;REVOCACION;EJERCICIO;TIPOBIEN;BIEN
                $list[] = array($item->nif, '', 
                    $item->surname.', '.$item->name,
                    $item->location, 'A', $per, $item->amount, '', '', '', $nat, '', $year, '', '', '');
            }
            return $list;
        }

        public function validate(&$errors = array()) {

            $this->location = ($this->country == 'spain') ? substr($this->zipcode, 0, 2) : '99';

            // limpio nombre y apellidos
            $this->name = self::idealiza($this->name);
            $this->name = str_replace('-', ' ', $this->name);
            $this->name = strtoupper(trim($this->name));

            $this->surname = self::idealiza($this->surname);
            $this->surname = str_replace('-', ' ', $this->surname);
            $this->surname = strtoupper(trim($this->surname));

			// quitamos puntos y guiones
			$this->nif = str_replace(array('_', '.', ' ', '-', ','), '', $this->nif);

        }

        /*
         *  Guarda los datos de donativo de un usuario
         */

        public function save(&$errors = array()) {
            $this->validate();


            $fields = array(
                'user',
                'amount',
                'name',
                'surname',
                'nif',
                'address',
                'zipcode',
                'location',
                'country',
                'year',
                'edited'
            );

            $set = '';
            $values = array();

            foreach ($fields as $field) {
                if ($set != '')
                    $set .= ', ';
                $set .= "$field = :$field";
                $values[":$field"] = $this->$field;
            }

            try {
                $sql = "REPLACE INTO user_donation (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_keys($values)) . ")";
                self::query($sql, $values);
                return true;
            } catch (\PDOException $e) {
                $errors[] = "Los datos no se han guardado correctamente. Por favor, revise los datos." . $e->getMessage();
                return false;
            }

        }

        public static function setConfirmed($user, $year) {
            try {
                $sql = "UPDATE user_donation SET confirmed = 1 WHERE user = :user AND year = :year";
                if (self::query($sql, array(':user' => $user, 'year' => $year))) {
                    return true;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                $errors[] = "Los datos no se han guardado correctamente. Por favor, revise los datos." . $e->getMessage();
                return false;
            }
        }
        
        /*
         * Nombre del archivo de certificado generado
         */
        public function setPdf($filename) {
            try {
                $sql = "UPDATE user_donation SET pdf = :pdf WHERE user = :user AND year = :year";
                if (self::query($sql, array(':pdf'=>$filename,':user' => $this->user, 'year' => $this->year))) {
                    $this->pdf = $filename;
                    return true;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                $errors[] = "Los datos no se han guardado correctamente. Por favor, revise los datos." . $e->getMessage();
                return false;
            }
        }

        /*
         * Nombre del archivo de certificado guardado
         */
        public function getPdf($user, $year) {
            try {
                $sql = "SELECT pdf FROM user_donation WHERE user = :user AND year = :year";
                if ($filename = self::query($sql, array(':user' => $user, 'year' => $year))) {
                    return $filename->fetchColumn();
                } else {
                    return null;
                }
            } catch (\PDOException $e) {
                $errors[] = "No se puede recuperar pdf." . $e->getMessage();
                return false;
            }
        }


        /*
         * Resetear pdf
         */
        static public function resetPdf($xfilename) {
            try {
                $sql = "UPDATE user_donation SET pdf = NULL WHERE MD5(pdf) = :pdf";
                if (self::query($sql, array(':pdf'=>$xfilename))) {

                    // @TODO: debe usar library file para eliminar el archivo (bucket documents)
                    $path = 'certs/'.$xfilename;
                    unlink($path);


                    return true;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                $errors[] = "Los datos no se han guardado correctamente. Por favor, revise los datos." . $e->getMessage();
                return false;
            }
        }


        static public function getDates ($user, $year) {

            $fechas = array();

            // Fechas de donativos
            $sql = "SELECT 
                        DATE_FORMAT(invest.invested, '%d-%m-%Y') as date,
                        invest.amount as amount,
                        project.name as project
                    FROM invest
                    INNER JOIN project
                        ON project.id = invest.project
                        AND (project.passed IS NOT NULL AND project.passed != '0000-00-00')
                    WHERE   invest.status IN ('1', '3')
                    AND invest.user = :id
                    AND (invest.invested >= '{$year}-01-01' AND invest.invested <= '{$year}-12-31')
                    ORDER BY invest.invested ASC
                    ";
//                    echo($sql . '<br />' . $user);
            $query = static::query($sql, array(':id' => $user));
            foreach ($query->fetchAll(\PDO::FETCH_OBJ) as $row) {
                $fechas[] = $row;
            }

            return $fechas;
        }

        /*
         * Año fiscal actual
         */
        static public function currYear() {

            $year = date('Y');
            $month = date('m');
            // hasta junio es el año anterior
            if ($month <= 6) {
                $year--;
            }

            return $year;
        }



    }

}