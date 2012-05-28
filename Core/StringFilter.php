<?php
/*
* StringFilter.php - Performs some common text filtering.
* Can be used as a word censor. This functionality is not enabled by default.
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
$stringfilter_core = new stringfilter_core($bot);
/*
The Class itself...
*/
class stringfilter_core extends BasePassiveModule
{ // Start Class
    var $stringlist;


    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        // Create Table
        $this->bot->db->query(
            "CREATE TABLE IF NOT EXISTS " . $this->bot->db->define_tablename("string_filter", "true") . "
			(search varchar(255) NOT NULL,
				new VARCHAR(255) NOT NULL DEFAULT '**bleep**',
				PRIMARY KEY (search))"
        );
        $this->registerModule("stringfilter");
        $this->registerEvent("connect");
        $this->stringlist = array();
        $this->bot->core("settings")
            ->create("Filter", "Enabled", FALSE, "Enable bot output text filter.", "On;Off", FALSE, 1);
        $this->bot->core("settings")
            ->create("Filter", "Funmode", "off", "Select a fun bot output filter. (See documentation)", "off;chef;eleet;fudd;pirate;noFont;", FALSE, 10);
    }


    /*
    This gets called when bot connects
    */
    function connect()
    { // Start function connect()
        $this->getStrings(TRUE);
    } // End function connect()

    function outputFilter($text)
    { // Start function outputFilter()
        foreach ($this->stringlist as $search => $new) {
            $text = preg_replace("/" . $search . "/i", $new, $text);
            // $text = str_ireplace($search, $new, $text); // str_ireplace is php5+
        }
        if ($this->bot->core("settings")->get("Filter", "Funmode") != "off") {
            echo "\nCaling funMode()!\n";
            $text = $this->funMode(
                $text, $this->bot->core("settings")
                    ->get("Filter", "Funmode")
            );
        }
        return $text;
    } // End function outputFilter()

    /*
    This function can be used to filter input against the string list.
    What else could we do for input filtering?
    */
    function inputFilter($text)
    { // Start function inputFilter()
        foreach ($this->stringlist as $search => $new) {
            $text = preg_replace("/" . stripslashes($search) . "/i", stripslashes($new), $text);
            // $text = str_ireplace($search, $new, $text); // str_ireplace is php5+
        }
        return $text;
    } // End function inputFilter()

    /*
    Gets the filterd string list from the database.
    If update is true, the array is refreshed from the database.
    */
    function getStrings($update = FALSE)
    { // Start function getStrings()
        if ($update) {
            $sql = "SELECT * FROM #___string_filter";
            $result = $this->bot->db->select($sql, MYSQL_ASSOC);
            if (empty($result)) {
                return FALSE;
            }
            else {
                foreach ($result as $info) {
                    $this->stringlist[$info["search"]] = $info["new"];
                }
                unset($result);
            }
        }
        return $this->stringlist;
    } // End function getStrings()

    /*
    Adds a string to the filtered string list.
    */
    function addString($search, $new = NULL)
    { // Start function addString()
        $search = mysql_real_escape_string(strtolower($search));
        if (isset($this->stringlist[$search])) {
            $this->error->set("The string '" . $search . "' is already on the filtered word list.");
            return $this->error;
        }
        if (!is_null($new)) {
            $new = mysql_real_escape_string(strtolower($new));
            $sql = "INSERT INTO #___string_filter (search, new) VALUES ('" . $search . "', '" . $new . "')";
        }
        else {
            $sql = "INSERT INTO #___string_filter (search) VALUES ('" . $search . "')";
            $new = "**bleep**";
        }
        $this->bot->db->query($sql);
        $this->stringlist[$search] = $new;
        return "Added '" . $search . "' to the filterd string list. It will be replaced with '" . $new . "'";
    } // End function addString()

    function remString($search)
    { // Start function remString()
        $search = mysql_real_escape_string(strtolower($search));
        if (isset($this->stringlist[$search])) {
            unset($this->stringlist[$search]);
            $sql = "DELETE FROM #___string_filter WHERE search = '" . $search . "'";
            $this->bot->db->query($sql);
            return "Removed " . $search . " from the filtered string list.";
        }
        else {
            $this->error->set($search . " is not on the filtered string list.");
            return $this->error;
        }
    } // End function remString()

    /*
    Returns garbled text. ;-)
    */
    function funMode($text, $filter)
    { // Start function funMode()
        $filter = strtolower($filter);
        switch ($filter) {
        case "rot13":
            return $this->bot->core("funfilters")->rot13($text);
            break;
        case "chef":
            return $this->bot->core("funfilters")->chef($text);
            break;
        case "eleet":
            return $this->bot->core("funfilters")->eleet($text);
            break;
        case "fudd":
            return $this->bot->core("funfilters")->fudd($text);
            break;
        case "pirate":
            return $this->bot->core("funfilters")->pirate($text);
            break;
        case "noFont":
            return $this->bot->core("funfilters")->nofont($text);
            break;
        default:
            $this->bot->log("FILTER", "ERROR", $filter . " is not a valid fun mode.");
            return $text;
            break;
        }
    } // End function funMode()
} // End of Class
?>
