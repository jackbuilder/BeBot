<?php
/*
* Mysql.php - MySQL interaction
*
* BeBot - An Anarchy Online & Age of Conan Chat Automaton
* Copyright (C) 2004 Jonas Jax
* Copyright (C) 2005-2012 J-Soft and the BeBot development team.
*
* Developed by:
* - Alreadythere (RK2)
* - Blondengy (RK1)
* - Blueeagl3 (RK1)
* - Glarawyn (RK1)
* - Khalem (RK1)
* - Naturalistic (RK1)
* - Temar (RK1)
*
* See Credits file for all acknowledgements.
*
*  This program is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; version 2 of the License only.
*
*  This program is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  You should have received a copy of the GNU General Public License
*  along with this program; if not, write to the Free Software
*  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307
*  USA
*/
class Mysql
{
    public $CONN = "";
    public $DBASE = "";
    public $USER = "";
    public $PASS = "";
    public $SERVER = "";
    public $bot;
    public static $instance;

    public function get_instance($bothandle)
    {
        $bot = Bot::get_instance($bothandle);
        if (!isset(self::$instance[$bothandle])) {
            $class = __CLASS__;
            self::$instance[$bothandle] = new $class($bothandle);
        }

        return self::$instance[$bothandle];
    }

    private function __construct($bothandle)
    {
        $this->bot = Bot::get_instance($bothandle);
        $this->botname = $this->bot->botname;
        $this->error_count = 0;
        $this->last_error = 0;
        $this->last_reconnect = 0;
        $this->underscore = "_";
        $nounderscore = FALSE;
        /*
        Load up config
        */
        $botname_mysqli_conf = "Conf/" . $this->botname . ".MySQL.conf";
        if (file_exists($botname_mysqli_conf)) {
            include $botname_mysqli_conf;
            $this->bot->log("MYSQL", "START", "Loaded MySQL configuration from " . $botname_mysqli_conf, FALSE);
        } else {
            include 'Conf/MySQL.conf';
            $this->bot->log("MYSQL", "START", "Loaded MySQL configuration from Conf/MySQL.conf", FALSE);
        }
        $this->USER = $user;
        $this->PASS = $pass;
        $this->SERVER = $server;
        $this->DBASE = $dbase;
        if (empty($master_tablename)) {
            $this->master_tablename = strtolower($this->botname) . "_tablenames";
        } else {
            $master_tablename = str_ireplace("<botname>", strtolower($this->botname), $master_tablename);
            $this->master_tablename = $master_tablename;
        }
        if (!isset($table_prefix)) {
            $this->table_prefix = strtolower($this->botname);
        } else {
            $table_prefix = str_ireplace("<botname>", strtolower($this->botname), $table_prefix);
            $this->table_prefix = $table_prefix;
        }
        if ($nounderscore) {
            $this->underscore = "";
        }
        $this->connect(true);
        /*
        Make sure we have the master table for tablenames that the bot cannot function without.
        */
        $this->query(
            "CREATE TABLE IF NOT EXISTS " . $this->master_tablename
                . "(internal_name VARCHAR(255) NOT NULL PRIMARY KEY, prefix VARCHAR(100), use_prefix VARCHAR(10) NOT NULL DEFAULT 'false', schemaversion INT(3) NOT NULL DEFAULT 1)"
        );
        $this->query("CREATE TABLE IF NOT EXISTS table_versions (internal_name VARCHAR(255) NOT NULL PRIMARY KEY, schemaversion INT(3) NOT NULL DEFAULT 1)");
        $this->update_master_table();

        return true;
    }

    public function update_master_table()
    {
        $columns = array_flip(
            array(
                "internal_name",
                "prefix",
                "use_prefix",
                "schemaversion"
            )
        );
        $fields = $this->select("EXPLAIN " . $this->master_tablename, MYSQL_ASSOC);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                unset($columns[$field['Field']]);
            }
        }
        if (!empty($columns)) {
            foreach ($columns as $column => $temp) {
                switch ($column) {
                case 'schemaversion':
                    $this->query("ALTER TABLE " . $this->master_tablename . " ADD COLUMN schemaversion INT(3) NOT NULL DEFAULT 1");
                    break;
                }
            }
        }
    }

    public function connect($initial = false)
    {
        if ($initial) {
            $this->bot->log("MYSQL", "START", "Establishing MySQL database connection....");
        }
        $conn = @mysqli_connect($this->SERVER, $this->USER, $this->PASS);
        if (!$conn) {
            $this->error("Cannot connect to the database server!", $initial, false);

            return false;
        }
        if (!mysqli_select_db($this->DBASE, $conn)) {
            $this->error("Database not found or insufficient priviledges!", $initial, false);

            return false;
        }
        if ($initial == true) {
            $this->bot->log("MYSQL", "START", "MySQL database connection test successfull.");
        }
        $this->CONN = $conn;
    }

    public function close()
    {
        if ($this->CONN != NULL) {
            mysqli_close($this->CONN);
            $this->CONN = NULL;
        }
    }

    public function error($text, $fatal = false, $connected = true)
    {
        $msg = mysqli_error();
        $this->error_count++;
        $this->bot->log("MySQL", "ERROR", "(# " . $this->error_count . ") on query: $text", $connected);
        $this->bot->log("MySQL", "ERROR", $msg, $connected);
        // If this error is occuring while we are trying to first connect to the database when starting
        // rthe bot its a fatal error.
        if ($fatal == true) {
            $this->bot->log("MySQL", "ERROR", "A fatal database error has occurred. Shutting down.", $connected);
            exit();
        }
    }

    public function select($sql, $result_form = MYSQL_NUM)
    {
        $this->connect();
        $data = "";
        $sql = $this->add_prefix($sql);
        $result = mysqli_query($sql, $this->CONN);
        if (!$result) {
            $this->error($sql);

            return false;
        }
        if (empty($result)) {
            return false;
        }
        while ($row = mysqli_fetch_array($result, $result_form)) {
            $data[] = $row;
        }
        mysqli_free_result($result);

        return $data;
    }

    public function query($sql)
    {
        $this->connect();
        $sql = $this->add_prefix($sql);
        $return = mysqli_query($sql, $this->CONN);
        if (!$return) {
            $this->error($sql);

            return false;
        } else {
            return true;
        }
    }

    public function returnQuery($sql)
    {
        $this->connect();
        $sql = $this->add_prefix($sql);
        $result = mysqli_query($sql, $this->CONN);
        if (!$result) {
            return false;
        } else {
            return $result;
        }
    }

    public function dropTable($sql)
    {
        $this->connect();
        $sql = $this->add_prefix($sql);
        $result = mysqli_query("DROP TABLE " . $sql, $this->CONN);
        if (!$return) {
            $this->error($sql);

            return false;
        } else {
            return true;
        }
    }

    public function add_prefix($sql)
    {
        $pattern = '/\w?(#___.+?)\b/';

        return preg_replace_callback(
            $pattern, array(
                &$this,
                'strip_prefix_control'
            ), $sql
        );
    }

    public function strip_prefix_control($matches)
    {
        $tablename = $this->get_tablename(substr($matches[1], 4));

        return $tablename;
    }

    /*
    Returns a table name, adding prefix.
    Creates a default name of $prefix_$table and adds this to the database if the tablename doesn't exist yet.
    For speed purposes names are cached after the first query - tablenames don't change during runtime.
    */
    public function get_tablename($table)
    {
        // get name out of cached entries if possible:
        if (isset($this->tablenames[$table])) {
            return $this->tablenames[$table];
        }
        // check the database for the name, default prefix and default suffix:
        $name = $this->select("SELECT * FROM " . $this->master_tablename . " WHERE internal_name = '" . $table . "'");
        if (empty($name)) {
            // no entry existing, create one:
            if (empty($this->table_prefix)) {
                $tablename = $table;
            } else {
                $tablename = $this->table_prefix . $this->underscore . $table;
            }
            $this->query("INSERT INTO " . $this->master_tablename . " (internal_name, prefix, use_prefix) VALUES ('" . $table . "', '" . $this->table_prefix . "', 'true')");
        } else {
            // entry exists, create the correct tablename:
            if ($name[0][2] == 'true' && !empty($this->table_prefix)) {
                $tablename = $name[0][1] . $this->underscore . $table;
            } else {
                $tablename = $table;
            }
        }
        // cache the entry and return it:
        $this->tablenames[$table] = $tablename;

        return $tablename;
    }

    /*
    Used for first defines of tablenames, allows to set if prefix should be used.
    If the tablename already exists, the existing name is returned - NO NAMES ARE REDEFINED!

    Otherwise same as get_tablename()
    */
    public function define_tablename($table, $use_prefix)
    {
        // get name out of cached entries if possible:
        if (isset($this->tablenames[$table])) {
            return $this->tablenames[$table];
        }
        // check the database for the name, default prefix and default suffix:
        $name = $this->select("SELECT * FROM " . $this->master_tablename . " WHERE internal_name = '" . $table . "'");
        if (empty($name)) {
            // no entry existing, create one:
            $tablename = '';
            $prefix = '';
            if (((strtolower($use_prefix) == 'true') || ($use_prefix === true)) && !empty($this->table_prefix)) {
                $prefix = $this->table_prefix;
                $tablename = $prefix . $this->underscore . $table;
                $use_prefix = 'true';
            } else {
                $tablename = $table;
                $use_prefix = 'false';
            }
            $this->query("INSERT INTO " . $this->master_tablename . " (internal_name, prefix, use_prefix) VALUES ('" . $table . "', '" . $prefix . "', '" . $use_prefix . "')");
        } else {
            // entry exists, create the correct tablename:
            if ($name[0][2] == 'true' && !empty($this->table_prefix)) {
                $tablename = $name[0][1] . $this->underscore . $table;
            } else {
                $tablename = $table;
            }
        }
        // cache the entry and return it:
        $this->tablenames[$table] = $tablename;

        return $tablename;
    }

    public function get_version($table)
    {
        $version = $this->select("SELECT schemaversion, use_prefix FROM " . $this->master_tablename . " WHERE internal_name = '" . $table . "'");
        if (!empty($version)) {
            if ($version[0][1] == "false") {
                $version2 = $this->select("SELECT schemaversion FROM table_versions WHERE internal_name = '" . $table . "'");
                if (!empty($version2)) {
                    Return ($version2[0][0]);
                }
            }
            Return ($version[0][0]);
        } else {
            Return (1);
        }
    }

    public function set_version($table, $version)
    {
        if (!is_numeric($version)) {
            echo "DB Error Trying to set version: " . $version . " for table " . $table . "!\n";
            //$this->bot->log("DB", "ERROR", "Trying to set version: " . $version . " for table " . $table . "!");
        } else {
            $this->query("UPDATE " . $this->master_tablename . " SET schemaversion = " . $version . " WHERE internal_name = '" . $table . "'");
            $usep = $this->select("SELECT use_prefix FROM " . $this->master_tablename . " WHERE internal_name = '" . $table . "'");
            if ($usep[0][0] == "false") {
                $this->query(
                    "INSERT INTO table_versions (internal_name, schemaversion) VALUES ('" . $table . "', " . $version
                        . ") ON DUPLICATE KEY UPDATE schemaversion = VALUES(schemaversion)"
                );
            }
        }
    }

    public function update_table($table, $column, $action, $query)
    {
        $fields = $this->select("EXPLAIN #___" . $table, MYSQL_ASSOC);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $columns[$field['Field']] = TRUE;
            }
        }
        Switch (strtolower($action)) {
        case 'add': // make sure it doesnt exist
            $do = TRUE;
            if (is_array($column)) {
                foreach ($column as $c) {
                    if (isset($columns[$c])) {
                        $do = FALSE;
                    }
                }
            } else {
                if (isset($columns[$column])) {
                    $do = FALSE;
                }
            }
            if ($do) {
                $this->query($query);
            }
            Break;
        case 'drop': // Make sure it does exist
        case 'alter':
        case 'modify':
            $do = TRUE;
            if (is_array($column)) {
                foreach ($column as $c) {
                    if (!isset($columns[$c])) {
                        $do = FALSE;
                    }
                }
            } else {
                if (!isset($columns[$column])) {
                    $do = FALSE;
                }
            }
            if ($do) {
                $this->query($query);
            }
            Break;
        case 'change':
            if (isset($columns[$column[0]]) && !isset($columns[$column[1]])) {
                $this->query($query);
            }
            Break;
        Default:
            echo "Unknown MYSQL UPDATE Action '" . $action . "'";
            $this->query($query);
        }
    }
}
