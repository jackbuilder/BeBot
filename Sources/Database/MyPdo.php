<?php
namespace Database;

class MyPdo
{
	/**
	 * 
	 * @var PDO
	 */
    private $conn = "";
    private $dbase = "";
    private $user = "";
    private $pass = "";
    private $server = "";
    private $bot;
    private $engine = 'mysql'; 
    
    public static $instance;

    /**
     * Is this like singleton but?? 
     * TODO: Fix this crap with serviceContainer or something
     * 
     * @param Bot $bothandle
     * @param array Configurations for the connection
     * @return MyPdo
     */
    static function get_instance($bothandle, array $configurations)
    {
        $bot = \Bot::get_instance($bothandle);
        if (!isset(self::$instance[$bothandle])) {
            self::$instance[$bothandle] = new MyPdo($bothandle, $configurations);
        }

        return self::$instance[$bothandle];
    }

    /**
     * Create new instance of MyPDO
     * 
     * TODO: Configuration injection.
     * 
     * @param Bot $bothandle
     * @param array $configurations
     * @return boolean
     */
    private function __construct($bothandle, array $configurations = null)
    {
        $this->bot = \Bot::get_instance($bothandle);
        $this->botname = $this->bot->botname;
        $this->error_count = 0;
        $this->last_error = 0;
        $this->last_reconnect = 0;
        $this->underscore = "_";
        
        $nounderscore = FALSE;
        $this->user = $configurations['user'];
        $this->pass = $configurations['pass'];
        $this->server = $configurations['server'];
        $this->dbase = $configurations['dbase'];
        $this->engine = $configurations['engine'];

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
        TODO: Is this supported in other engines then mysql?
        */
        $this->query(
            "CREATE TABLE IF NOT EXISTS " . $this->master_tablename
                . "(internal_name VARCHAR(255) NOT NULL PRIMARY KEY, prefix VARCHAR(100), use_prefix VARCHAR(10) NOT NULL DEFAULT 'false', schemaversion INT(3) NOT NULL DEFAULT 1)"
        );
        $this->query("CREATE TABLE IF NOT EXISTS table_versions (internal_name VARCHAR(255) NOT NULL PRIMARY KEY, schemaversion INT(3) NOT NULL DEFAULT 1)");
        $this->update_master_table();

        return true;
    }

    /**
     * Create some internal tables
     * 
     * @TODO: Check is explain supported on any other db engines.
     * 
     */
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

    /**
     * Create connection 
     * 
     * @param boolean $initial
     * @return boolean
     */
    public function connect($initial = false)
    {
        if ($initial) {
            $this->bot->log("PDO", "START", "Establishing database connection....");
        }
        $dataSource = $this->engine . ':host=' . $this->server . ';dbname=' 
			. $this->dbase . ''; 
        $conn = new \PDO($dataSource, $this->user, $this->pass);
        if (!$conn) {
            $this->error("Cannot connect to the database server!", $initial, false);

            return false;
        }
        if ($initial == true) {
            $this->bot->log("PDO", "START", "database connection test successfull.");
        }
        $this->conn = $conn;
    }

    public function close()
    {
        if ($this->conn != NULL) {
            $this->conn = NULL;
        }
    }

    /**
     * Some error handling. 
     * 
     * @param unknown_type $text
     * @param unknown_type $fatal
     * @param unknown_type $connected
     */
    public function error($text, $fatal = false, $connected = true)
    {
        $msg = $this->conn->errorInfo();
        $this->error_count++;
        $this->bot->log("PDO", "ERROR", "(# " . $this->conn->errorCode(). ") on query: $text", $connected);
        $this->bot->log("PDO", "ERROR", $msg, $connected);
        // If this error is occuring while we are trying to first connect to the database when starting
        // rthe bot its a fatal error.
        if ($fatal == true) {
            $this->bot->log("PDO", "ERROR", "A fatal database error has occurred. Shutting down.", $connected);
            exit();
        }
    }

    public function select($sql, $result_form = MYSQL_NUM)
    {
        //TODO; why we need connect again?
        $this->connect();
        
        $data = "";
        $sql = $this->add_prefix($sql);
        $result = $this->conn->query($sql);
        if (!$result) {
            $this->error($sql);
            return false;
        }
        $return = $result->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($result)) {
            return false;
        }

        return $return; 
    }

    public function query($sql)
    {
    	//TODO: Why we need to do this?
        $this->connect();
        $sql = $this->add_prefix($sql);
        $return = $this->conn->query($sql);
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
        $result = $this->conn->query($sql);
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
        $result = $this->conn->query("DROP TABLE " . $sql);
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
