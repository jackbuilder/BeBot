<?php
/**
 * @desc    ConfigMagik - Ini-File Reader and Writer (ConfigKeeper)
 * @author  BennyZaminga <bzaminga@web.de>
 * @date    Sat Jul 03 19:52:46 CEST 2004
 *
 * @version 0.01 - Sat Jul 03 19:52:46 2004
 *     - 1st release
 * @version 0.02 - Sun Jul 18 16:04:51 2004
 *     - Added listKeys()
 *     - Added listSections()
 *     - Added toString() [TEXT and HTML -View]
 *     - Added Editor to package
 *     - Changed PROCESS_SECTIONS to enabled by default
 *     - Lacks of SECTIONS_AUTORECOGNITION (comming soon)
 * @version 0.03 - Wed Aug 11 01:31:52 2004
 *     - Fixed a bug in get()
 *     - Added additional code handling PROCESS_SECTIONS more gracefully
 *
 * @version 0.04 - Sunday Dec 02 15:20:53 2007
 *      - Modified for Bebot by Ebag333
 *
 *
 * ConfigMagik is licensed under GNU Lesser General Public License (LGPL)
 * http://www.opensource.org/licenses/lgpl-license.html
 *
 * Original download location: http://www.phpclasses.org/browse/package/1726.html
 */
/*class ConfigMagik extends BasePassiveModule*/
class ConfigMagik
{
    public $PATH = NULL;
    public $VARS = array();
    public $SYNCHRONIZE = FALSE;
    public $PROCESS_SECTIONS = TRUE;
    public $PROTECTED_MODE = TRUE;
    public $ERRORS = array();
    public static $instance = array();

    /**
     * @desc   Constructor of this class.
     *
     * @param string $path Path to ini-file to load at startup.
     *                                  NOTE:   If the ini-file can not be found, it will try to generate a
     *                                  new empty one at the location indicated by path passed to
     *                                  constructor-method of this class.
     * @param bool $synchronize      TRUE for constant synchronisation of memory and file (disabled by default).
     * @param bool $process_sections TRUE or FALSE to enable or disable sections in your ini-file (enabled by default).
     *
     * @return void Returns nothing, like any other constructor-method ��] .
     */
    /*	function __construct(&$bot, $path=null, $synchronize=false, $process_sections=true)*/
    public function __construct(
        $path = NULL, $synchronize = FALSE,
        $process_sections = TRUE
    )
    {
        /*		parent::__construct($bot, get_class($this));

                $this -> register_module("ini");
        */
        $this->PATH = $path;
        $this->create_ini($path, $synchronize, $process_sections);
    }

    public static function get_instance(
        $bothandle, $path = NULL, $synchronize = FALSE,
        $process_sections = TRUE
    )
    {
        if (!isset(self::$instance[$bothandle])) {
            $class = __CLASS__;
            self::$instance[$bothandle] = new $class($path, $synchronize, $process_sections);
        }

        return self::$instance[$bothandle];
    }

    /**
     * @desc                       Attempts to create an INI file and load it. (NOT tested!)
     *
     * @param  path             Path to ini.
     * @param  synchronize      Maintain file sychronization.
     * @param  process_sections Process sections (false if there are no sections in the ini.
     */
    public function create_ini(
        $path = NULL, $synchronize = FALSE,
        $process_sections = TRUE
    )
    {
        // check whether to enable processing-sections or not
        if (isset($process_sections)) {
            $this->PROCESS_SECTIONS = $process_sections;
        }
        // check whether to enable synchronisation or not
        if (isset($synchronize)) {
            $this->SYNCHRONIZE = $synchronize;
        }
        // if a path was passed and file exists, try to load it
        if ($path != NULL) {
            // set passed path as class-var
            $this->PATH = $path;
            if (!is_file($path)) {
                // conf-file seems not to exist, try to create an empty new one
                $fp_new = @fopen($path, 'w', FALSE);
                if (!$fp_new) {
                    $err = "ConfigMagik::ConfigMagik() - Could not create new config-file('$path'), error.";
                    array_push($this->ERRORS, $err);
                    die($err);
                } else {
                    fclose($fp_new);
                }
            } else {
                // try to load and parse ini-file at specified path
                $loaded = $this->load($path);
                if (!$loaded) {
                    exit();
                }
            }
        }
    }

    /**
     * @desc                      Retrieves the value for a given key.
     *
     * @param string $key     Key or name of directive to set in current config.
     * @param string $section Name of section to set key/value-pair therein.
     *                         NOTE:                   Section must only be specified when sections are used in your ini-file.
     *
     * @return mixed Returns the value or NULL on failure.
     *                            NOTE:                   An empty directive will always return an empty string.
     *                            Only when directive can not be found, NULL is returned.
     */
    public function get($key = NULL, $section = NULL)
    {
        // if section was passed, change the PROCESS_SECTION-switch (FIX: 11/08/2004 BennyZaminga)
        if ($section) {
            $this->PROCESS_SECTIONS = TRUE;
        } else {
            $this->PROCESS_SECTIONS = FALSE;
        }
        // get requested value
        if ($this->PROCESS_SECTIONS) {
            if (isset($this->VARS[$section][$key])) {
                $value = $this->VARS[$section][$key];
            } else {
                if (isset($this->VARS[$key])) {
                    $value = $this->VARS[$key];
                } else {
                    return NULL;
                }
            }
        } else {
            $value = $this->VARS[$key];
        }
        // if value was not found (false), return NULL (FIX: 11/08/2004 BennyZaminga)
        if ($value === FALSE) {
            return NULL;
        }
        // return found value
        return $value;
    }

    /**
     * @desc   Sets the value for a given key (in given section, if any specified).
     *
     * @param string $key     Key or name of directive to set in current config.
     * @param mixed  $value   Value of directive to set in current config.
     * @param string $section Name of section to set key/value-pair therein.
     *                         NOTE:   Section must only be specified when sections are enabled in your ini-file.
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function set($key, $value, $section = NULL)
    {
        // when sections are enabled and user tries to genarate non-sectioned vars,
        // throw an error, this is definitely not allowed.
        if ($this->PROCESS_SECTIONS and !$section) {
            $err = "ConfigMagik::set() - Passed no section when in section-mode, nothing was set.";
            array_push($this->ERRORS, $err);

            return FALSE;
        }
        // check if section was passed
        if ($section === TRUE) {
            $this->PROCESS_SECTIONS = TRUE;
        }
        // set key with given value in given section (if enabled)
        if ($this->PROCESS_SECTIONS) {
            $this->VARS[$section][$key] = $value;
        } else {
            $this->VARS[$key] = $value;
        }
        // synchronize memory with file when enabled
        if ($this->SYNCHRONIZE) {
            $this->save();
        }

        return TRUE;
    }

    /**
     * @desc   Remove a directive (key and it's value) from current config.
     *
     * @param string $key     Name of key to remove form current config.
     * @param string $section Optional name of section (if used).
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function removeKey($key, $section = NULL)
    {
        // check if section was passed and it's valid
        if ($section != NULL) {
            if (in_array($section, array_keys($this->VARS)) == FALSE) {
                $err = "ConfigMagik::removeKey() - Could not find section('$section'), nothing was removed.";
                array_push($this->ERRORS, $err);

                return FALSE;
            }
            // look if given key exists in given section
            if (in_array($key, array_keys($this->VARS[$section])) === FALSE) {
                $err = "ConfigMagik::removeKey() - Could not find key('$key'), nothing was removed.";
                array_push($this->ERRORS, $err);

                return FALSE;
            }
            // remove key from section
            $pos = array_search($key, array_keys($this->VARS[$section]), TRUE);
            array_splice($this->VARS[$section], $pos, 1);

            return TRUE;
        } else {
            // look if given key exists
            if (in_array($key, array_keys($this->VARS)) === FALSE) {
                $err = "ConfigMagik::removeKey() - Could not find key('$key'), nothing was removed.";
                array_push($this->ERRORS, $err);

                return FALSE;
            }
            // remove key (sections disabled)
            $pos = array_search($key, array_keys($this->VARS), TRUE);
            array_splice($this->VARS, $pos, 1);
            // synchronisation-stuff
            if ($this->SYNCHRONIZE) {
                $this->save();
            }
            // return
            return TRUE;
        }
    }

    /**
     * @desc   Remove entire section from current config.
     *
     * @param string $section Name of section to remove.
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function removeSection($section)
    {
        // check if section exists
        if (in_array($section, array_keys($this->VARS), TRUE) === FALSE) {
            $err = "ConfigMagik::removeSection() - Section('$section') could not be found, nothing removed.";
            array_push($this->ERRORS, $err);

            return FALSE;
        }
        // find position of $section in current config
        $pos = array_search($section, array_keys($this->VARS), TRUE);
        // remove section from current config
        array_splice($this->VARS, $pos, 1);
        // synchronisation-stuff
        if ($this->SYNCHRONIZE) {
            $this->save();
        }
        // return
        return TRUE;
    }

    /**
     * @desc   Loads and parses ini-file from filesystem.
     *
     * @param string $path Optional path to ini-file to load.
     *                      NOTE:   When not provided, path passed to constructor will be used.
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function load($path = NULL)
    {
        // if path was specified, check if valid else abort
        if ($path != NULL and !is_file($path)) {
            $err = "ConfigMagik::load() - Path('$path') is invalid, nothing loaded.";
            array_push($this->ERRORS, $err);
            echo $err;

            return FALSE;
        } elseif ($path == NULL) {
            // no path was specified, fall back to class-var
            $path = $this->PATH;
        }
        /*
        * PHP's own method is used for parsing the ini-file instead of own code.
        * It's robust enough ;-)
        */
        $this->VARS = @parse_ini_file($path, $this->PROCESS_SECTIONS);

        return TRUE;
    }

    /**
     * @desc   Writes ini-file to filesystem as file.
     *
     * @param string $path Optional path to write ini-file to.
     *                      NOTE:   When not provided, path passed to constructor will be used.
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     */
    public function save($path = NULL)
    {
        // if no path was specified, fall back to class-var
        if ($path == NULL) {
            $path = $this->PATH;
        }
        $content = "";
        // PROTECTED_MODE-prefix
        if ($this->PROTECTED_MODE) {
            $content .= "<?PHP\n; /*\n; -- BEGIN PROTECTED_MODE\n";
        }
        // config-header
        $content .= "; This file was automagically generated by ConfigMagik.\n";
        $content .= "; Do not edit this file by hand, use the bot GUI interface instead.\n";
        $content .= "; If you cannot load your bot due to a module being disabled, and\n";
        $content .= "; absolutely MUST edit this file, do not have the bot running while editing.\n";
        $content .= "; Last modified: " . date('d M Y H:i s') . "\n";
        // check if there are sections to process
        if ($this->PROCESS_SECTIONS) {
            foreach ($this->VARS as $key => $elem) {
                $content .= "[" . $key . "]\n";
                foreach ($elem as $key2 => $elem2) {
                    $content .= $key2 . " = \"" . $elem2 . "\"\n";
                }
            }
        } else {
            foreach ($this->VARS as $key => $elem) {
                $content .= $key . " = \"" . $elem . "\"\n";
            }
        }
        // add PROTECTED_MODE-ending
        if ($this->PROTECTED_MODE) {
            $content .= "\n; -- END PROTECTED_MODE\n; */\n?>\n";
        }
        // write to file
        if (!$handle = @fopen($path, 'w')) {
            $err = "ConfigMagik::save() - Could not open file('$path') for writing, error.";
            array_push($this->ERRORS, $err);

            return FALSE;
        }
        if (!fwrite($handle, $content)) {
            $err = "ConfigMagik::save() - Could not write to open file('$path'), error.";
            array_push($this->ERRORS, $err);

            return FALSE;
        } else {
            // push a message onto error-stack
            $err = "ConfigMagik::save() - Sucessfully saved to file('$path').";
            array_push($this->ERRORS, $err);
        }
        fclose($handle);

        return TRUE;
    }

    /**
     * @desc   Renders this Object as formatted string (TEXT or HTML).
     *
     * @param string $output_type Type of desired output. Can be 'TEXT' or 'HTML'.
     *
     * @return string Returns a formatted string according to chosen output-type.
     */
    public function toString($output_type = 'TEXT')
    {
        // check requested output-type
        if (strtoupper($output_type) !== 'TEXT' and strtoupper($output_type) !== 'HTML') {
            $err = "ConfigMagik::toString() - Unknown OutputType('$output_type') was requested, falling back to TEXT.";
            array_push($this->ERRORS, $err);
            $output_type = 'TEXT';
        }
        if (strtoupper($output_type) === 'TEXT') {
            // render object as TEXT
            $out = "";
            ob_start();
            print_r($this->VARS);
            $out .= ob_get_clean();

            return $out;
        } elseif (strtoupper($output_type) === 'HTML') {
            // render object as HTML
            $out = "<table style='background:#FFEECC;border:1px solid black;' width=60%>\n";
            if ($this->PROCESS_SECTIONS) {
                // render with sections
                $out .= "\t<tr><th style='border:1px solid white;'>Section</th><th style='border:1px solid white;'>Key</th><th style='border:1px solid white;'>Value</th></tr>\n";
                $sections = $this->listSections();
                $num_sections = 0;
                $num_keys = 0;
                foreach ($sections as $section) {
                    $out .= "\t<tr><td style='border:1px solid white;' colspan=3>$section</td></tr>\n";
                    $keys = $this->listKeys($section);
                    foreach ($keys as $key) {
                        $val = $this->get($key, $section);
                        $out .= "\t<tr><td>&nbsp;</td><td style='border:1px solid maroon;'>$key</td><td style='border:1px solid brown;'>$val</td></tr>\n";
                        $num_keys++;
                    }
                    $num_sections++;
                }
                // summary of table (with sections)
                $out .= "\t<tr><td style='border:1px solid white;' colspan=3 align=right><code>There are <b>$num_keys keys</b> in <b>$num_sections sections</b>.</code></td></tr>\n";
            } else {
                // render without sections
                $keys = $this->listKeys();
                $num_keys = 0;
                $out .= "\t<tr><th style='border:1px solid white;'>Key</th><th style='border:1px solid white;'>Value</th></tr>\n";
                foreach ($keys as $key) {
                    $val = $this->get($key);
                    $out .= "\t<tr><td style='border:1px solid maroon;'>$key</td><td style='border:1px solid brown;'>$val</td></tr>\n";
                    $num_keys++;
                }
                // summary of table (without sections)
                $out .= "\t<tr><td style='border:1px solid white;' colspan=2 align=right><code>There are <b>$num_keys keys</b>.</code></td></tr>\n";
            }
            // close table
            $out .= "</table>";

            return $out;
        }
    }

    /**
     * @desc                   Lists all keys.
     *
     * @param string $section Optional section (needed only when using sections).
     *
     * @return array Returns a numeric array containing the keys as string.
     */
    public function listKeys($section = NULL)
    {
        // check if section was passed
        if ($section !== NULL) {
            // check if passed section exists
            $sections = $this->listSections();
            if (in_array($section, $sections) === FALSE) {
                $err = "ConfigMagik::listKeys() - Section('$section') could not be found.";
                array_push($this->ERRORS, $err);

                return FALSE;
            }
            // list all keys in given section
            $list = array();
            $all = array_keys($this->VARS[$section]);
            foreach ($all as $possible_key) {
                if (!is_array($this->VARS[$possible_key])) {
                    array_push($list, $possible_key);
                }
            }

            return $list;
        } else {
            // list all keys (section-less)
            return array_keys($this->VARS);
        }
    }

    /**
     * @desc   List all sections (if any).
     *
     * @param  void
     *
     * @return array Returns a numeric array with all section-names as stings therein.
     */
    public function listSections()
    {
        $list = array();
        // separate sections from normal keys
        $all = array_keys($this->VARS);
        foreach ($all as $possible_section) {
            if (is_array($this->VARS[$possible_section])) {
                array_push($list, $possible_section);
            }
        }

        return $list;
    }
}
