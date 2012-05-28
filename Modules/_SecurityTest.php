<?php
/*
*
* SecurityTest.php - Module template.
*	This module contains commands and functions
*	for testing and debugging Security.php
* Author: Andrew Zbikowski <andyzib@gmail.com> (AKA Glarawyn RK1)
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
$securitytest = new SecurityTest($bot);
/*
The Class itself...
*/
class SecurityTest extends BaseActiveModule
{ // Start Class

    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        $this->registerCommand("all", "securitytest", "OWNER");
    }


    /*
    This function handles all the inputs and returns FALSE if the
    handler should not send output, otherwise returns a string
    sutible for output via sendTell, sendPrivateGroup, and sendGuildChat.
    */
    function commandHandler($name, $msg, $source)
    { // Start function handler()
        $vars = explode(' ', strtolower($msg));
        $command = $vars[0];
        switch ($command) {
        case "securitytest":
            switch ($vars[1]) {
            case "cache":
                if (isset($vars[2])) {
                    return $this->showCache($vars[2]);
                }
                else {
                    return $this->showCache();
                }
                break;
            case "whoAmI":
                return $this->whoAmI($name);
                break;
            case "whoIs":
                return $this->whois($vars[2]);
            default:
                return "Pick a test: cache, whoAmI, whoIs";
            }
            break;
        default:
            $this->bot->send_tell($name, "Broken plugin, received unhandled command: $command");
        }
    } // End function handler()

    /*
    Shows the security cache on the bot console.
    */
    function showCache($what = "all")
    { // Start function showCache()
        $what = strtolower($what);
        if ($what == "member" || $what == "members") {
            print_r("Members Cache:\n");
            print_r($this->bot->core("security")->cache['members']);
            return "Security Members Cache Array dumped to console.";
        }
        elseif ($what == "guest" || $what == "guests") {
            print_r("Guests Cache:\n");
            print_r($this->bot->core("security")->cache['guests']);
            return "Security Guests Cache Array dumped to console.";
        }
        elseif ($what == "banned" || $what == "ban") {
            print_r("Banned Cache:\n");
            print_r($this->bot->core("security")->cache['banned']);
            return "Security Banned Cache Array dumped to console.";
        }
        elseif ($what == "org" || $what == "ranks" || $what == "orgranks") {
            print_r("OrgRanks Cache:\n");
            print_r($this->bot->core("security")->cache['orgranks']);
            return "Security OrgRanks Cache Array dumped to console.";
        }
        elseif ($what == "group" || $what == "groups") {
            print_r("Groups Cache:\n");
            print_r($this->bot->core("security")->cache['groups']);
            return "Security Groups Cache Array dumped to console.";
        }
        else // Entire cache
        {
            print_r("Security Cache:\n");
            print_r($this->bot->core("security")->cache);
            return "Security Cache Array dumped to console.";
        }
    } // End function showCache()

    /*
    Returns highest access level.
    */
    function whoAmI($name)
    { // Start function whoAmI
        $groups = $this->bot->core("security")->get_groups($name);
        $access = $this->bot->core("security")->get_access_level($name);
        $access = $this->getAccessName($access);
        $message = "Your access level is " . $access;
        if ($groups != -1) {
            $groupmsg = " You are a member of the following security groups: ";
            foreach ($groups as $group) {
                $groupmsg .= $group['name'] . " ";
            }
        }
        return $message . $groupmsg;
    } // End function whoAmI

    function whois($name)
    { // Start function whoIs()
        $name = ucfirst(strtolower($name));
        $groups = $this->bot->core("security")->get_groups($name);
        $access = $this->bot->core("security")->get_access_level($name);
        $access = $this->getAccessName($access);
        $message = $name . "'s highest access level is " . $access;
        if ($groups != -1) {
            $groupmsg = $name . " is a member of the following security groups: ";
            foreach ($groups as $group) {
                $groupmsg .= $group['name'] . " ";
            }
        }
        return $message . $groupmsg;
    } // End function whoIs()

    function getAccessName($access)
    { // Start function getAccessName()
        switch ($access) { // Start switch
        case 256:
            $access = "Owner";
            break;
        case 255:
            $access = "SuperAdmin";
            break;
        case 192:
            $access = "Admin";
            break;
        case 128:
            $access = "Leader";
            break;
        case 2:
            $access = "Member";
            break;
        case 1:
            $access = "Guest";
            break;
        case 0:
            $access = "Anonymous";
            break;
        case -1:
            $access = "Banned";
            break;
        default:
            $access = "Unknown (" . $access . ")";
            break;
        } // End switch
        return $access;
    } // End function getAccessName()
} // End of Class
?>
