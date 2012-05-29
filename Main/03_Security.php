<?php
/*
* Security.php - Provide security to all of BeBot.
*
* See http://bebot.shadow-realm.org/wiki/doku.php?id=security for full on
* usage of BeBots Security System.
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

// Define Access Levels as Constants, these are available globally...
// There is no way to change these during runtime.
define("OWNER", 256); // Outside range of MySQL TINYINT UNSIGNED
define("SUPERADMIN", 255);
define("ADMIN", 192);
define("LEADER", 128);
define("MEMBER", 2);
define("GUEST", 1);
define("ANONYMOUS", 0);
define("BANNED", -1); // Outside range of MySQL TINYINT UNSIGNED

$security = new Security_Core($bot);

/*
The Class itself...
*/
class Security_Core extends BaseActiveModule
{ // Start Class
    var $enabled; // Set to true when the security subsystem is ready.
    /*
    The $firstcon and $gocron variables make an end run around cron...
    The idea is to do cron on bot startup, but then only do cron actions
    when the roster update is not happening.
    */
    var $firstcron; // Cron Control Hack.
    var $gocron; // Cron Crontrol Hack part 2. :)
    var $_super_admin; // SuperAdmins from Bot.conf
    var $_owner; // Owner from Bot.conf
    var $_cache; // Security Cache.
    var $last_alts_status; // Check status of setting UseAlts, if it changes clear main cache.
    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));

        $this->registerModule("security");
        $this->registerEvent("cron", "12hour");
        $this->registerEvent("connect");

        // Create security_groups table.
        $this->bot->db->query(
            "CREATE TABLE IF NOT EXISTS " . $this->bot->db->defineTableName("security_groups", "true") . "
					(gid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					name VARCHAR(35) UNIQUE,
					description VARCHAR(80),
					access_level TINYINT UNSIGNED NOT NULL DEFAULT 0)"
        );

        // Create default Security Groups (superadmin, admin, leader)
        $sql = "INSERT IGNORE INTO #___security_groups (name, description, access_level) VALUES ";
        $sql .= "('superadmin', 'Super Administrators', 255),";
        $sql .= "('admin', 'Administrators', 192),";
        $sql .= "('leader', 'Raid Leaders', 128)";
        $this->bot->db->query($sql);
        unset($sql);

        // Create security_members table.
        $this->bot->db->query(
            "CREATE TABLE IF NOT EXISTS " . $this->bot->db->defineTableName("security_members", "true") . "
						(id INT UNIQUE NOT NULL AUTO_INCREMENT,
						name VARCHAR(50),
						gid INT,
						PRIMARY KEY (name, gid),
						KEY (id)
						)"
        );

        // All org members will be bot members so give org ranks a default access_level of 2.
        $this->bot->db->query(
            "CREATE TABLE IF NOT EXISTS " . $this->bot->db->defineTableName("security_org", "true") . "
					(org_gov VARCHAR(25) NOT NULL,
					org_rank VARCHAR(25) NOT NULL,
					org_rank_id TINYINT UNSIGNED NOT NULL,
					access_level TINYINT UNSIGNED NOT NULL DEFAULT 2,
					PRIMARY KEY (org_gov, org_rank, org_rank_id))
					"
        );

        // Insert Ranks into table.
        $sql = "INSERT IGNORE INTO #___security_org (org_gov, org_rank, org_rank_id) VALUES ";
        $sql .= "('Department', 'President', 0), ";
        $sql .= "('Department', 'General', 1), ";
        $sql .= "('Department', 'Squad Commander', 2), ";
        $sql .= "('Department', 'Unit Commander', 3), ";
        $sql .= "('Department', 'Unit Leader', 4), ";
        $sql .= "('Department', 'Unit Member', 5), ";
        $sql .= "('Department', 'Applicant', 6), ";
        $sql .= "('Faction', 'Director', 0), ";
        $sql .= "('Faction', 'Board Member', 1), ";
        $sql .= "('Faction', 'Executive', 2), ";
        $sql .= "('Faction', 'Member', 3), ";
        $sql .= "('Faction', 'Applicant', 4), ";
        $sql .= "('Republic', 'President', 0), ";
        $sql .= "('Republic', 'Advisor', 1), ";
        $sql .= "('Republic', 'Veteran', 2), ";
        $sql .= "('Republic', 'Member', 3), ";
        $sql .= "('Republic', 'Applicant', 4), ";
        $sql .= "('Monarchy', 'Monarch', 0), ";
        $sql .= "('Monarchy', 'Consil', 1), ";
        $sql .= "('Monarchy', 'Follower', 2), ";
        $sql .= "('Feudalism', 'Lord', 0), ";
        $sql .= "('Feudalism', 'Knight', 1), ";
        $sql .= "('Feudalism', 'Vassal', 2), ";
        $sql .= "('Feudalism', 'Peasant', 3), ";
        $sql .= "('Anarchism', 'Anarchist', 1)";
        $this->bot->db->query($sql);
        unset($sql);

        $this->enabled = FALSE;

        $this->owner = ucfirst(strtolower($bot->owner));
        $this->super_admin = array();
        if (!empty($bot->super_admin)) {
            foreach ($bot->super_admin as $user => $value) {
                $this->super_admin[ucfirst(strtolower($user))] = $value;
            }
        }
        $this->firstcron = TRUE;
        $this->gocron = TRUE;

        $this->help['description'] = "Handles the security groups, their rights and their members.";
        $this->help['command']['admin groups'] = "Shows all security groups and their members.";
        $this->help['command']['admin group add <groupname>'] = "Adds the new group <groupname> with ANONYMOUS rights.";
        $this->help['command']['admin group del <groupname>'] = "Removes the group <groupname>.";
        $this->help['command']['admin add <group> <name>'] = "Adds <name> as member to the group <group>.";
        $this->help['command']['admin del <group> <name>'] = "Removes <name> as member from the group <group>.";
        $this->help['command']['admin del <name>'] = "Removes <name> from the bot and all security groups.";
        $this->help['command']['addgroup <group> <desc>'] = "Adds a new group named <group> with description <desc>.";
        $this->help['command']['adduser <name>'] = "Adds <name> as GUEST to guild bots or as MEMBER to raid bots.";
        $this->help['command']['adduser <name> <group>'] = "Adds <name> as member to the security group <group>.";
        $this->help['command']['delgroup <group>'] = "Deletes the security group <group>";
        $this->help['command']['deluser <name>'] = "Removes <name> from the bot and all security groups.";
        $this->help['command']['deluser <name> <group>'] = "Removes <name> from the security group <group>.";
        $this->help['command']['security'] = "Display security system main menu.";
        $this->help['command']['security groups'] = "Display security groups.";
        $this->help['command']['security levels'] = "Display security access levels.";
        $this->help['notes'] = "The owner and superadmins defined in the config file cannot be modified in any way.";
    }


    /*
    This gets called when bot connects
    */
    function connect()
    { // Start function connect()
        // Bind the command to the bot
        // Can't be done earlier as otherwise we'd end in a requirement loop with access control
        $this->registerCommand("all", "security", "SUPERADMIN");
        $this->registerCommand("all", "adduser", "SUPERADMIN");
        $this->registerCommand("all", "deluser", "SUPERADMIN");
        $this->registerCommand("all", "addgroup", "SUPERADMIN");
        $this->registerCommand("all", "delgroup", "SUPERADMIN");
        $this->registerCommand("all", "admin", "SUPERADMIN");

        $this->enable();

        $this->bot->core("settings")
            ->create("Security", "orggov", "Unknown", "Orginization Government Form", "Anarchism;Department;Faction;Feudalism;Monarchy;Republic;Unknown", TRUE, 99);
    } // End function connect()

    /*
    This gets called on cron
    */
    function cron()
    { // Start function cron()
        if (!$this->enabled) {
            $this->enable();
        }
        if ($this->gocron) {
            // Do cron stuff
            $this->cacheSecurity();
            $this->setGovernment();
            // End cron stuff with this.
            if ($this->firstcron) {
                $this->firstcron = FALSE;
            }
            else {
                $this->gocron = FALSE;
            }
        }
        else {
            $this->gocron = TRUE; // Don't do cron stuff, but do it next time.
        }
    } // End function cron()

    /*
    As this module depends on settings module, and Security.php will be loaded before
    Settings.php, this function will be called by the first cron job. This function
    initilized the security cache then enables security.
    */
    function enable()
    { // Start function enable()
        $this->cache = array();
        $this->cacheSecurity(); // Populate the security cache.
        // Customizable Security Settings.
        $longdesc = "Should be run over all alts to get the highest access level for the queried characters?";
        $this->bot->core("settings")
            ->create("Security", "UseAlts", FALSE, $longdesc);
        $longdesc = "Should all characters in the chat group of the bot be considered GUESTs for security reasons?";
        $this->bot->core("settings")
            ->create("Security", "GuestInChannel", TRUE, $longdesc);
        $longdesc = "Security Access Level required to add members and guests.";
        $this->bot->core("settings")
            ->create("Security", "adduser", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 1);
        $longdesc = "Security Access Level required to remove members and guests.";
        $this->bot->core("settings")
            ->create("Security", "deluser", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 2);
        $longdesc = "Security Access Level required to add security groups.";
        $this->bot->core("settings")
            ->create("Security", "addgroup", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 3);
        $longdesc = "Security Access Level required to remove security groups.";
        $this->bot->core("settings")
            ->create("Security", "delgroup", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 4);
        $longdesc = "Security Access Level required to add users to security groups.";
        $this->bot->core("settings")
            ->create("Security", "addgroupmember", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 5);
        $longdesc = "Security Access Level required to remove users from security groups.";
        $this->bot->core("settings")
            ->create("Security", "remgroupmember", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 6);
        $longdesc = "Security Access Level required to use <pre>security whoIs name.";
        $this->bot->core("settings")
            ->create("Security", "whoIs", "leader", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 10);
        $longdesc = "Security Access Level required to change settings for all modules.";
        $this->bot->core("settings")
            ->create("Security", "settings", "SUPERADMIN", $longdesc, "OWNER;SUPERADMIN;ADMIN;LEADER", FALSE, 99);

        $this->enabled = TRUE;
        $this->last_alts_status = $this->bot->core("settings")
            ->get("Security", "UseAlts");
        $this->last_leader = "";
    } // End function enable()

    /*
    Unified message handler
    */
    function commandHandler($source, $msg, $msgtype)
    { // Start funciton handler
        $vars = explode(' ', strtolower($msg));

        $command = $vars[0];

        switch ($command) {
        case 'security':
            switch ($vars[1]) {
            case "changelevel":
                if ($this->checkAccess($source, "SUPERADMIN")) {
                    //The following is in URGENT NEED of comments!
                    if (preg_match("/^security changelevel (Department) (Squad|Unit|Board) (Commander|Leader|Member) (.+)$/i", $msg, $info)) {
                        return $this->changeLevel($info[2] . " " . $info[3], $info[4], $info[1]); // SA
                    }
                    elseif (preg_match("/^security changelevel (Faction) (Board Member) (.+)$/i", $msg, $info)) {
                        return $this->changeLevel($info[2], $info[3], $info[1]); // SA
                    }
                    elseif (preg_match("/^security changelevel (.+?) (.+?) (.+)$/i", $msg, $info)) {
                        return $this->changeLevel($info[2], $info[3], $info[1]); // SA
                    }
                    elseif (preg_match("/^security changelevel (.+?) (.+)$/i", $msg, $info)) {
                        return $this->changeLevel($info[1], $info[2]); // SA
                    }
                }
                else {
                    $this->error->set("Only SUPERADMINs can access the changelevel command.");
                    return $this->error;
                }
                break;
            case "levels":
                if ($this->checkAccess($source, "SUPERADMIN")) {
                    return $this->showSecurityLevels($msgtype); // A
                }
                else {
                    $this->error->set("Only SUPERADMINs can modify the access levels.");
                    return $this->error;
                }
            case "whoIs":
                if ($this->checkAccess(
                    $source, $this->bot
                        ->core("settings")->get('Security', 'Whois')
                )
                ) {
                    return $this->whoIs($vars[2]);
                }
                else {
                    $this->error->set(
                        "You must have " . strtoupper(
                            $this->bot
                                ->core("settings")
                                ->get('Security', 'Whois')
                        ) . " access or higher to use <pre>security whoIs"
                    );
                    return $this->error;
                }
                break;
            case "whoAmI":
                if ($this->checkAccess($source, "GUEST")) {
                    return $this->whoAmI($source);
                }
                else {
                    $this->bot->log("SECURITY", "DENIED", "Player " . $source . " was denied access to <pre>security whoAmI.");
                    return FALSE;
                }
                break;
            case "groups":
                if ($this->checkAccess($source, "GUEST")) {
                    return $this->showGroups();
                }
                else {
                    $this->error->set("You need to be GUEST or higher to access 'groups'");
                    return $this->error;
                }
                break;
            default:
                if ($this->checkAccess($source, "GUEST")) {
                    return $this->showSecurityMenu($source);
                }
                else {
                    $this->error->set("You need to be GUEST or higher to access 'checkAccess'");
                    return $this->error;
                }
                break;
            }
            break;
        case 'adduser': // adduser username group
            if (isset($vars[2])) {
                if ($this->checkAccess(
                    $source, $this->bot
                        ->core("settings")->get('Security', 'Addgroupmember')
                )
                ) {
                    return $this->addGroupMember($vars[1], $vars[2], $source);
                }
                else {
                    $this->error->set(
                        "Only " . strtoupper(
                            $this->bot
                                ->core("settings")
                                ->get('Security', 'Addgroupmember')
                        ) . "s and above can add group members."
                    );
                    return $this->error;
                }
            }
            else {
                if ($this->checkAccess(
                    $source, $this->bot
                        ->core("settings")->get('Security', 'Adduser')
                )
                ) {
                    return $this->addUser($source, $vars[1]);
                }
                else {
                    $this->error->set(
                        "Only " . strtoupper(
                            $this->bot
                                ->core("settings")
                                ->get('Security', 'Adduser')
                        ) . "s and above can add users."
                    );
                    return $this->error;
                }
            }
            break;
        case 'deluser':
            if (isset($vars[2])) {
                if ($this->checkAccess(
                    $source, $this->bot
                        ->core("settings")->get('Security', 'Remgroupmember')
                )
                ) {
                    return $this->remGroupMember($vars[1], $vars[2], $source);
                }
                else {
                    $this->error->set(
                        "Only " . strtoupper(
                            $this->bot
                                ->core("settings")
                                ->get('Security', 'Remgroupmember')
                        ) . "s and above can remove group members."
                    );
                    return $this->error;
                }
            }
            else {
                if ($this->checkAccess(
                    $source, $this->bot
                        ->core("settings")->get('Security', 'Deluser')
                )
                ) {
                    return $this->delUser($source, $vars[1]);
                }
                else {
                    $this->error->set(
                        "Only " . strtoupper(
                            $this->bot
                                ->core("settings")
                                ->get('Security', 'Deluser')
                        ) . "s and above can remove users."
                    );
                    return $this->error;
                }
            }
            break;
        case 'addgroup':
            if ($this->checkAccess(
                $source, $this->bot->core("settings")
                    ->get('Security', 'Addgroup')
            )
            ) {
                if (preg_match("/^addgroup (.+?) (.+)$/i", $msg, $info)) {
                    return $this->addGroup($info[1], $info[2], $source);
                }
                else {
                    $this->error->set("Not enough paramaters given. Try /sendTell <botname> <pre>addgroup groupname description.");
                }
            }
            else {
                $this->error->set(
                    "Only " . strtoupper(
                        $this->bot
                            ->core("settings")
                            ->get('Security', 'Addgroup')
                    ) . "s and above can add groups."
                );
                return $this->error;
            }
            break;
        case 'delgroup':
            if ($this->checkAccess(
                $source, $this->bot->core("settings")
                    ->get('Security', 'Delgroup')
            )
            ) {
                if (isset($vars[1])) {
                    return $this->delGroup($vars[1], $source);
                }
                else {
                    $this->error->set("Not enough paramaters given. Try /sendTell <botname> <pre>delgroup groupname.");
                    return $this->error;
                }
            }
            else {
                $this->error->set(
                    "Only " . strtoupper(
                        $this->bot
                            ->core("settings")
                            ->get('Security', 'Delgroup')
                    ) . "s and above can delete groups."
                );
                return $this->error;
            }
            break;
        case 'admin':
            if (preg_match("/^admin group(s){0,1}$/i", $msg)) {
                if ($this->checkAccess($source, "guest")) {
                    return $this->showGroups();
                }
                else {
                    return FALSE; // FIXME: Nothing returned?
                }
            }
            else {
                if (preg_match("/^admin group add (.+?) (.+)$/i", $msg, $info)) {
                    if ($this->checkAccess(
                        $source, $this->bot
                            ->core("settings")->get('Security', 'Addgroupmember')
                    )
                    ) {
                        return $this->addGroup($info[1], $info[2], $source);
                    }
                    else {
                        $this->error->set(
                            "Only " . strtoupper(
                                $this->bot
                                    ->core("settings")
                                    ->get('Security', 'Addgroup')
                            ) . "s and above can add groups."
                        );
                        return $this->error;
                    }
                }
                else {
                    if (preg_match("/^admin group add ([a-zA-Z0-9]+)$/i", $msg, $info)) {
                        if ($this->checkAccess(
                            $source, $this->bot
                                ->core("settings")->get('Security', 'Addgroupmember')
                        )
                        ) {
                            return $this->addGroup($info[1], " ", $source); // No group description
                        }
                        else {
                            $this->error->set(
                                "Only " . strtoupper(
                                    $this->bot
                                        ->core("settings")
                                        ->get('Security', 'Addgroup')
                                ) . "s and above can add groups."
                            );
                            return $this->error;
                        }
                    }
                    else {
                        if (preg_match("/^admin group (remove|rem|del) ([a-zA-Z0-9]+)$/i", $msg, $info)) {
                            if ($this->checkAccess(
                                $source, $this->bot
                                    ->core("settings")->get('Security', 'Delgroup')
                            )
                            ) {
                                return $this->delGroup($info[2], $source);
                            }
                            else {
                                $this->error->set(
                                    "Only " . strtoupper(
                                        $this->bot
                                            ->core("settings")
                                            ->get('Security', 'Delgroup')
                                    ) . "s and above can delete groups."
                                );
                                return $this->error;
                            }
                        }
                        else {
                            if (preg_match("/^admin add ([a-zA-Z0-9]+) ([a-zA-Z0-9]+)$/i", $msg, $info)) {
                                if ($this->checkAccess(
                                    $source, $this->bot
                                        ->core("settings")->get('Security', 'Addgroupmember')
                                )
                                ) {
                                    return $this->addGroupMember($info[2], $info[1], $source);
                                }
                                else {
                                    $this->error->set(
                                        "Only " . strtoupper(
                                            $this->bot
                                                ->core("settings")
                                                ->get('Security', 'Addgroupmember')
                                        ) . "s and above and add group members."
                                    );
                                    return ($this->error);
                                }
                            }
                            else {
                                if (preg_match("/^admin (remove|rem|del) ([a-zA-Z0-9]+) ([a-zA-Z0-9]+)$/i", $msg, $info)) {
                                    if ($this->checkAccess(
                                        $source, $this->bot
                                            ->core("settings")->get('Security', 'Remgroupmember')
                                    )
                                    ) {
                                        return $this->remGroupMember($info[3], $info[2], $source);
                                    }
                                    else {
                                        $this->error->set(
                                            "Only " . strtoupper(
                                                $this->bot
                                                    ->core("settings")
                                                    ->get('Security', 'Remgroupmember')
                                            ) . "s and above can remove group members."
                                        );
                                        return $this->error;
                                    }
                                }
                                else {
                                    if (preg_match("/^admin (remove|rem|del) ([a-zA-Z0-9]+)$/i", $msg, $info)) {
                                        if ($this->checkAccess(
                                            $source, $this->bot
                                                ->core("settings")->get('Security', 'Deluser')
                                        )
                                        ) {
                                            return $this->delUser($source, $info[2]);
                                        }
                                        else {
                                            $this->error->set(
                                                "Only " . strtoupper(
                                                    $this->bot
                                                        ->core("settings")
                                                        ->get('Security', 'Deluser')
                                                ) . "s and above can delete users."
                                            );
                                            return $this->error;
                                        }
                                    }
                                    else {
                                        return $this->bot->send_help($source);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            break;
        default:
            $this->bot->send_tell($source, "Broken plugin, received unhandled command: $command");
        }
    } // End funciton handler

    /*
    Adds a group.
    */
    function addGroup($groupname, $description, $caller = "Internal Process")
    { // Start function addGroup()
        $groupname = strtolower($groupname);
        if ($groupname == "leader" || $groupname == "admin" || $groupname == "superadmin" || $groupname == "member"
            || $groupname == "guest"
            || $groupname == "anonymous"
            || $groupname == "owner"
        ) {
            $this->error->set(ucfirst($groupname) . " is a default group and cannot be created as a custom group.");
            return $this->error;
        }
        // Check for bad input.
        if (is_numeric($groupname)) {
            $this->error->set("Group Names should not be all numbers.");
            return $this->error;
        }
        if (strlen($groupname) < 5) {
            if (is_numeric($groupname)) {
                $this->error->set("Group Names should be five or more characters.");
                return $this->error;
            }
        }
        $groupname = str_replace(" ", "_", $groupname); // Replace Spaces with underscores.
        $groupname = mysql_real_escape_string($groupname); // If any slashes are added, it's an invalid group.
        if (strpos($groupname, "\\")) {
            $this->error->set("Single quotes, double quotes, backslash and other special characters are not allowed in group names.");
            return $this->error;
        }
        // Input should be good now...
        if (isset($this->cache['groups'][$groupname]['gid'])) {
            $this->error->set("Group " . $groupname . " already exisits");
            return $this->error;
        }
        $sql = "INSERT INTO #___security_groups (name, description) ";
        $sql .= "VALUES ('" . $groupname . "', '" . mysql_real_escape_string($description) . "')";
        $this->bot->db->query($sql);
        $sql = "SELECT gid FROM #___security_groups WHERE name = '" . $groupname . "'";
        $result = $this->bot->db->select($sql);
        $gid = $result[0][0];
        unset($result);
        $tmp = array(
            "gid"          => $gid,
            "name"         => $groupname,
            "description"  => $description,
            "access_level" => 0
        );
        $tmp['members'] = array();
        $this->cacheManager("add", "groups", $tmp);
        $this->bot->log("SECURITY", "ADDGROUP", $caller . " Created group " . $groupname . " with anonymous level privileges.");
        return "Created group " . $groupname . " with anonymous level privileges.";
    } // End function addGroup()

    /*
    Deletes a group.
    */
    function delGroup($target, $caller = "Internal Process")
    { // Start function delGroup()
        $target = strtolower($target);
        if ($target == "leader" || $target == "admin" || $target == "superadmin") {
            $this->error->set($target . " cannot be deleted.");
            return $this->error;
        }
        $target = mysql_real_escape_string($target);
        if (is_numeric($target)) {
            $sql = "SELECT name FROM #___security_groups WHERE gid = '" . $target . "'"; // FIXME: Could use the cache here.
            $result = $this->bot->db->select($sql);
            if (!isset($this->cache['groups'][$target])) {
                $this->error->set("Group ID " . $target . " not found.");
                return $this->error;
            }
            $target = $this->cache['groups'][$target]['name'];
        }
        if (!isset($this->cache['groups'][$target]['gid'])) {
            $this->error->set("Group " . $target . " does not exisit");
            return $this->error;
        }
        $sql = "DELETE FROM #___security_members WHERE gid = '" . $this->cache['groups'][$target]['gid'] . "'";
        $this->bot->db->query($sql);
        $sql = "DELETE FROM #___security_groups WHERE gid = '" . $this->cache['groups'][$target]['gid'] . "'";
        $this->bot->db->query($sql);
        $this->bot->log("SECURITY", "DELGROUP", $caller . " Deleted group ID " . $this->cache['groups'][$target]['gid'] . " Name: " . $target . ".");
        $this->cacheManager("rem", "groups", $target);

        // Clear the flexible security cache if it exists:
        if ($this->bot->core("flexible_security") != NULL) {
            $this->bot->core("flexible_security")->clear_cache();
        }
        return "Deleted group ID " . $this->cache['groups'][$target]['gid'] . " Name: " . $target . ".";
    } // End function delGroup()

    /*
    Adds $target to $group
    */
    function addGroupMember($target, $group, $caller = "Internal Process")
    { // Start function addGroupMember()
        $target = ucfirst(strtolower($target));
        $group = strtolower($group);
        $uid = $this->bot->core('player')->id($target);
        if (!$uid) {
            $this->error->set($target . " is not a valid character.");
            return $this->error;
        }
        $gid = $this->getGroupId($group);
        if ($gid == -1) {
            $this->error->set("Unable to find group ID for " . $group . " " . $group . " may not exisit. Check your spelling and try again.");
            return $this->error;
        }

        if (strtolower($caller) != "internal process" && $this->getAccessLevel($caller) < $this->cache['groups'][$gid]['access_level']) {
            $this->error->set("Your Access Level is less than the Access Level of " . $group . ". You cannot add members to " . $group . ".");
            return $this->error;
        }

        if (!isset($this->cache['groups'][$gid]['members'][$target])) {
            $sql = "INSERT INTO #___security_members (name,gid) VALUES ('" . $target . "', " . $gid . ")";
            $this->bot->db->query($sql);
            $this->cacheManager("add", "groupmem", $group, $target);
            $this->bot->log("SECURITY", "GRPMBR", $caller . " Added " . $target . " to group " . $group . ".");
            return ("Added " . $target . " to group " . $group . ".");
        }
        else {
            $this->error->set($target . " is already a member of " . $group);
            return $this->error;
        }
    } // End function addGroupMember()

    /*
    Removes $target from $group if $name is an admin.
    */
    function remGroupMember($target, $group, $caller)
    { // Start function remGroupMember()
        $target = ucfirst(strtolower($target));
        $group = strtolower($group);
        $uid = $this->bot->core('player')->id($target);
        if (!$uid) {
            $this->error->set($target . " is not a valid character.");
            return $this->error;
        }

        $gid = $this->getGroupId($group);
        if ($gid == -1) {
            $this->error->set("Unable to find group ID for " . $group . " " . $group . " may not exisit. Check your spelling and try again.");
            return $this->error;
        }


        if (!preg_match("/^Internal Process: (.*)$/i", $caller)) {
            if ($this->getAccessLevel($caller) < $this->cache['groups'][$group]['access_level']) {
                $this->error->set("Your Access Level is lower than " . $group . "'s Access Level. You cannot remove members from " . $group . ".");
                return $this->error;
            }
        }

        if (isset($this->cache['groups'][$group]['members'][$target])) {
            $sql = "DELETE FROM #___security_members WHERE name = '" . $target . "' AND gid = " . $this->cache['groups'][$group]['gid'];
            $this->bot->db->query($sql);
            $this->cacheManager("rem", "groupmem", $group, $target);
            $this->bot->log("SECURITY", "GRPMBR", $caller . " Removed " . $target . " from " . $group);
            return "Removed " . $target . " from " . $group;
        }
        else {
            $this->error->set($target . " is not a member of " . $group);
            return $this->error;
        }
    } // End function remGroupMember()

    /*
    Adds a user as a guest or member.
    $admin = person setting the ban.
    $target = person being banned.
    */
    function addUser($admin, $target)
    { // Start function addUser()
        $admin = ucfirst(strtolower($admin));
        $target = ucfirst(strtolower($target));
        $level = strtoupper($level);
        $uid = $this->bot->core('player')->id($target);
        // Check to see if user is banned.
        if ($this->isBanned($target)) {
            $this->error->set($target . " is banned.");
            return $this->error;
        }

        // Get whoIs data & check for errors.
        if ($this->bot->game == "ao") {
            $who = $this->bot->core("whoIs")->lookup($target);
            if ($who instanceof BotError) {
                return $who;
            }
        }

        if ($this->bot->guildbot) // If this is a guildBot, we can only add guests.
        {
            $level = "GUEST";
            $lvlnum = GUEST;
            $cache = "guests";
        }
        else // If it's a raid bot, we should only add members...
        {
            $level = "MEMBER";
            $lvlnum = MEMBER;
            $cache = "members";
        }

        // Check to see if they are already a member
        if (isset($this->cache[$cache][$target])) {
            $this->error->set(ucfirst($target) . " is already a " . $level . ".");
            return $this->error;
        }
        else {
            $this->cacheManager("add", $cache, $target);
            $sql = "INSERT INTO #___users (char_id, nickname, added_by, added_at, userLevel, updated_at) ";
            $sql .= "VALUES (" . $uid . ", '" . $target . "', '" . mysql_real_escape_string($admin) . "', " . time() . ", " . $lvlnum . ", " . time() . ") ";
            $sql .= "ON DUPLICATE KEY UPDATE added_by = VALUES(added_by), added_at = VALUES(added_at), userLevel=VALUES(userLevel), updated_at = VALUES(updated_at)";
            $this->bot->db->query($sql);
            $this->bot->log("SECURITY", "ADDUSER", $admin . " Added " . $target . " as a " . $level);
            return "Added " . $target . " as a " . $level;
        }
    } // End function addUser()

    /*
    Removes a user
    */
    function delUser($admin, $target)
    { // Start function delUser()
        $admin = ucfirst(strtolower($admin));
        $target = ucfirst(strtolower($target));
        if (!isset($this->cache["members"][$target]) && !isset($this->cache["guests"][$target])) {
            $this->error->set($target . " is not a member of <botname>.");
            return $this->error;
        }
        else {
            $this->cacheManager("rem", "members", $target);
            $this->cacheManager("rem", "guests", $target);
            $groups = $this->getGroups($target);
            if ($groups <> -1) {
                foreach ($groups as $gid) {
                    $this->remGroupMember($target, $this->cache['groups'][$gid]['name']);
                }
            }
            $this->bot->core("notify")->del($target);
            $sql
                =
                "UPDATE #___users SET userLevel = 0, deleted_by = '" . mysql_real_escape_string($admin) . "', deleted_at = " . time() . ", notify = 0 WHERE nickname = '" . $target
                    . "'";
            $this->bot->db->query($sql);
            $this->bot->log("SECURITY", "DELUSER", $admin . " " . $target . " has been removed from <botname>.");
            return $target . " has been removed from <botname>.";
        }
    } // End function delUser()

    /*
    $admin = person setting the ban.
    $target = person being banned.
    */
    function setBan(
        $admin, $target, $caller = "Internal Process",
        $reason = "None given.", $endtime = 0
    )
    { // Start function setBan()
        $admin = ucfirst(strtolower($admin));
        $target = ucfirst(strtolower($target));

        if (!$this->bot->core('player')->id($target)) {
            $this->error->set($target . " is not a valid character!");
            return $this->error;
        }

        if ($this->checkAccess($target, "OWNER")) {
            $this->error->set($target . " is the bot owner and cannot be banned.");
            return $this->error;
        }

        if (isset($this->cache['banned'][$target])) {
            $this->error->set($target . " is already banned.");
            return $this->error;
        }
        elseif (isset($this->cache['guests'][$target])) {
            $this->cacheManager("rem", "guests", $target);
            $this->cacheManager("add", "banned", $target);
            $sql = "UPDATE #___users SET userLevel = -1, banned_by = '" . mysql_real_escape_string($admin) . "', banned_at = " . time() . ", banned_for = '"
                . mysql_real_escape_string($reason) . "', banned_until = " . $endtime . " WHERE nickname = '" . $target . "'";
        }
        elseif (isset($this->cache['members'][$target])) {
            $this->cacheManager("rem", "members", $target);
            $this->cacheManager("add", "banned", $target);
            $sql = "UPDATE #___users SET userLevel = -1, banned_by = '" . mysql_real_escape_string($admin) . "', banned_at = " . time() . ", updated_at = " . time()
                . ", banned_for = '" . mysql_real_escape_string($reason) . "', banned_until = " . $endtime . " WHERE nickname = '" . $target . "'";
        }
        else // They are not in the member table at all.
        {
            $who = $this->bot->core("whoIs")->lookup($target);
            if ($who instanceof BotError) {
                return $who;
            }
            $this->cacheManager("add", "banned", $target);
            $sql = "INSERT INTO #___users (char_id,nickname,added_by,added_at,banned_by,banned_at,banned_for,banned_until,notify,userLevel,updated_at) ";
            $sql
                .=
                "VALUES ('" . $who['id'] . "', '" . $who['nickname'] . "', '" . mysql_real_escape_string($admin) . "', " . time() . ", '" . mysql_real_escape_string($admin) . "', "
                    . time() . ", '" . mysql_real_escape_string($reason) . "', " . $endtime . ", 0, -1, " . time() . ") ";
            $sql .= " ON DUPLICATE KEY UPDATE banned_by = VALUES(banned_by), banned_at = VALUES(banned_at), userLevel = VALUES(userLevel), updated_at = VALUES(updated_at), banned_for = VALUES(banned_for), banned_until = VALUES(banned_until)";
        }
        $this->bot->db->query($sql);
        $this->bot->core("player_notes")->add($target, $admin, $reason, 1);
        $this->bot->core("notify")->del($target);
        $this->bot->log("SECURITY", "BAN", $caller . " Banned " . $target . " from " . $this->bot->botname . ".");
        return "Banned " . $target . " from " . $this->bot->botname . ".";
    } // End function setBan()

    /*
    Removes a ban.
    */
    function remBan($admin, $target, $caller = "Internal Process")
    { // Start function remBan()
        $admin = ucfirst(strtolower($admin));
        $target = ucfirst(strtolower($target));
        if (!isset($this->cache['banned'][$target])) {
            $this->error->set($target . " is not banned.");
            return $this->error;
        }
        else {
            $this->cacheManager("rem", "banned", $target);
            $sql = "UPDATE #___users SET userLevel = 0 WHERE nickname = '" . $target . "'";
            $this->bot->db->query($sql);
            $return = "Unbanned " . $target . " from " . $this->bot->botname . ". " . $target . " is now anonymous.";
            $this->bot->log("SECURITY", "BAN", $caller . " " . $return);
            return $return;
        }
    } // End function remBan()

    /*
    Returns the group id for $groupname.
    Returns -1 if the group doesn't exisit.
    */
    function getGroupId($groupname)
    { // Start function getGroupId()
        if (isset($this->cache['groups'][$groupname]['gid'])) {
            return $this->cache['groups'][$groupname]['gid'];
        }
        else {
            return -1;
        }
    } // End function getGroupId()

    // Shows the security commands.
    function showSecurityMenu()
    { // Start function showSecurityMenu
        $inside = "Security System Main Menu\n\n";
        $inside .= "[" . $this->bot->core("tools")
            ->chatcmd("security groups", "Security Groups") . "]\n";
        $inside .= "[" . $this->bot->core("tools")
            ->chatcmd("security levels", "Security Levels") . "]\n";
        $inside .= "[" . $this->bot->core("tools")
            ->chatcmd("settings security", "Security Settings") . "]\n";
        $inside .= "[" . $this->bot->core("tools")
            ->chatcmd("security whoAmI", "Your Security Level and Group Membership") . "]\n";
        $inside .= "\n";
        $inside .= "To see someone elses Security Level and Group Membership type\n /sendTell <botname> <pre>security whoIs &lt;playername&gt;\n";
        return $this->bot->core("tools")->make_blob("Security System", $inside);
    } // End function showSecurityMenu

    /*
    Shows the groups, their ID numbers, and access levels.
    If called by a superadmin, allows changing access levels.
    */
    function showSecurityLevels($msgtype)
    { // Start function showSecurityLevels()
        $sql = "SELECT gid,name,description,access_level FROM #___security_groups ";
        $sql .= "WHERE name != 'superadmin' AND name != 'admin' AND name != 'leader' ";
        $sql .= "ORDER BY access_level DESC, name ASC";
        $result = $this->bot->db->select($sql, MYSQL_ASSOC);
        if (!empty($result)) {
            foreach ($result as $group) {
                if ($group['access_level'] == SUPERADMIN && $group['name'] <> "superadmin") {
                    $superadmin .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(SUPERADMIN, $group['gid'], $msgtype) . "\n";
                }
                if ($group['access_level'] == ADMIN && $group['name'] <> "admin") {
                    $admin .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(ADMIN, $group['gid'], $msgtype) . "\n";
                }
                if ($group['access_level'] == LEADER && $group['name'] <> "leader") {
                    $leader .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(LEADER, $group['gid'], $msgtype) . "\n";
                }
                if ($group['access_level'] == MEMBER && $group['name'] <> "member") {
                    $member .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(MEMBER, $group['gid'], $msgtype) . "\n";
                }
                if ($group['access_level'] == GUEST && $group['name'] <> "guest") {
                    $guest .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(GUEST, $group['gid'], $msgtype) . "\n";
                }
                if ($group['access_level'] == ANONYMOUS && $group['name'] <> "anonymous") {
                    $anonymous .= "+ Security Group " . $group['name'] . " (" . stripslashes($group['description']) . "):\n    Change " . $group['name'] . " Access Level To: "
                        . $this->changeLinks(ANONYMOUS, $group['gid'], $msgtype) . "\n";
                }
            }
        }
        unset($result);
        if ($this->bot->guildbot) {
            $sql = "SELECT org_rank, org_rank_id, access_level FROM #___security_org ";
            $sql .= "WHERE org_gov = '" . $this->bot->core("settings")
                ->get('Security', 'Orggov') . "' ";
            $sql .= "ORDER BY org_rank_id ASC, access_level DESC";
            $result = $this->bot->db->select($sql, MYSQL_ASSOC);
            if (!empty($result)) // This really should never be empty as this module automaticaly inserts data here.
            {
                foreach ($result as $rank) {
                    if ($rank['access_level'] == SUPERADMIN) {
                        $superadmin
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(SUPERADMIN, $rank, $msgtype)
                            . "\n";
                    }
                    if ($rank['access_level'] == ADMIN) {
                        $admin
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(ADMIN, $rank, $msgtype) . "\n";
                    }
                    if ($rank['access_level'] == LEADER) {
                        $leader
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(LEADER, $rank, $msgtype) . "\n";
                    }
                    if ($rank['access_level'] == MEMBER) {
                        $member
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(MEMBER, $rank, $msgtype) . "\n";
                    }
                    if ($rank['access_level'] == GUEST) {
                        $guest
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(GUEST, $rank, $msgtype) . "\n";
                    }
                    if ($rank['access_level'] == ANONYMOUS) {
                        $anonymous
                            .= "+ Org Rank " . $rank['org_rank'] . ":\n    Change " . $rank['org_rank'] . " Access Level To: " . $this->changeLinks(ANONYMOUS, $rank, $msgtype)
                            . "\n";
                    }
                }
            }
        }
        if ($this->bot->guildbot) {
            $blurb = "Org Ranks and Security Groups with ";
        }
        else {
            $blurb = "Security Groups with ";
        }
        $superadmin = $blurb . "Access Level SUPERADMIN\n" . $superadmin;
        $admin = $blurb . "Access Level ADMIN\n" . $admin;
        $leader = $blurb . "Access Level LEADER\n" . $leader;
        $member = $blurb . "Access Level MEMBER\n" . $member;
        $guest = $blurb . "Access Level GUEST\n" . $guest;
        $anonymous = $blurb . "Access Level ANONYMOUS\n" . $anonymous;
        $return = "Security: Access Levels\n\n";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security", "Security: Main Menu") . "] ";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security groups", "Security: Security Groups") . "] ";
        $return .= "\n\n";
        $return .= $superadmin . "\n";
        $return .= $admin . "\n";
        $return .= $leader . "\n";
        $return .= $member . "\n";
        $return .= $guest . "\n";
        $return .= $anonymous . "\n\n";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security", "Security: Main Menu") . "] ";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security groups", "Security: Security Groups") . "] ";
        return $this->bot->core("tools")
            ->make_blob("Security Access Levels", $return);
    } // End function showSecurityLevels()

    // Displays security groups, the group's access level, and the members of the group.
    function showGroups()
    { // Start function showGroups()
        $sql = "SELECT gid, name, description, access_level FROM #___security_groups ";
        $sql .= "ORDER BY access_level DESC, gid ASC, name";
        $result = $this->bot->db->select($sql, MYSQL_ASSOC);
        $superadmins = "Access Level SUPERADMIN:\n";
        $admins = "Access Level ADMIN:\n";
        $leaders = "Access Level LEADER:\n";
        $members = "Access Level MEMBER:\n";
        $guests = "Access Level GUEST:\n";
        $anon = "Access Level ANONYMOUS:\n";
        foreach ($result as $group) {
            if ($group['access_level'] == SUPERADMIN) {
                $superadmins .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $superadmins .= "\n";
                $superadmins .= $this->makeGroupMemberList($group['gid']);
                $superadmins .= "\n";
            }
            elseif ($group['access_level'] == ADMIN) {
                $admins .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $admins .= "\n";
                $admins .= $this->makeGroupMemberList($group['gid']);
                $admins .= "\n";
            }
            elseif ($group['access_level'] == LEADER) {
                $leaders .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $leaders .= "\n";
                $leaders .= $this->makeGroupMemberList($group['gid']);
                $leaders .= "\n";
            }
            elseif ($group['access_level'] == MEMBER) {
                $members .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $members .= "\n";
                $members .= $this->makeGroupMemberList($group['gid']);
                $members .= "\n";
            }
            elseif ($group['access_level'] == GUEST) {
                $guests .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $guests .= "\n";
                $guests .= $this->makeGroupMemberList($group['gid']);
                $guests .= "\n";
            }
            elseif ($group['access_level'] == ANONYMOUS) {
                $anon .= " + " . $group['name'] . " (" . stripslashes($group['description']) . ") ";
                $anon .= "\n";
                $anon .= $this->makeGroupMemberList($group['gid']);
                $anon .= "\n";
            }
        }
        $return = "Security: Groups\n\n";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security", "Security: Main Menu") . "] ";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security levels", "Security: Access Levels") . "] ";
        $return .= "\n\n";
        $return .= $superadmins . "\n";
        $return .= $admins . "\n";
        $return .= $leaders . "\n";
        $return .= $members . "\n";
        $return .= $guests . "\n";
        $return .= $anon . "\n";
        $return .= "\n";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security", "Security: Main Menu") . "] ";
        $return .= "[" . $this->bot->core("tools")
            ->chatcmd("security levels", "Security: Access Levels") . "] ";
        return $this->bot->core("tools")->make_blob("Security Groups", $return);
    } // End function showGroups()

    // Makes the list of group members for showGroups()
    function makeGroupMemberList($gid)
    { // Start function makeGroupMemberList()
        $tmp = "";
        if (empty($this->cache['groups'][$gid]['members'])) {
            return "        - No members.";
        }
        $users = $this->cache['groups'][$gid]['members'];
        sort($users);
        foreach ($users as $member) {
            $tmp .= "        - " . $member . "\n";
        }
        return rtrim($tmp);
    } // End function makeGroupMemberList()

    // Displays all admins.
    function show_admins($source)
    { // Start function show_admins()
    } // End function show_admins()

    /*
    Creates the proper change links for a group, called by showGroups.
    $levelid = numeric access level
    $groupid = group id number or name/shortname
    $source = source of command (sendToGroup, sendToGuildChat, sendTell)
    */
    function changeLinks($levelid, $groupid, $msgtype)
    { // Start function changeLinks
        if (!is_numeric($levelid)) {
            return NULL;
        }
        if (is_array($groupid)) {
            $vars = $this->bot->core("settings")
                ->get('Security', 'Orggov') . " " . $groupid['org_rank'];
        }
        else {
            $vars = $groupid;
        }
        $chatcmd = "security changelevel " . $vars . " ";
        if ($levelid <> SUPERADMIN) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . SUPERADMIN, "SUPERADMIN", $msgtype) . "] ";
        }

        if ($levelid <> ADMIN) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . ADMIN, "ADMIN", $msgtype) . "] ";
        }

        if ($levelid <> LEADER) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . LEADER, "LEADER", $msgtype) . "] ";
        }

        if ($levelid <> MEMBER) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . MEMBER, "MEMBER", $msgtype) . "] ";
        }

        if ($levelid <> GUEST) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . GUEST, "GUEST", $msgtype) . "] ";
        }

        if ($levelid <> ANONYMOUS) {
            $return .= "[" . $this->bot->core("tools")
                ->chatcmd($chatcmd . ANONYMOUS, "ANONYMOUS", $msgtype) . "] ";
        }
        return $return;
    } // End function changeLinks

    /*
    Changes the access level of a security group or org rank.
    */
    function changeLevel($groupid, $newacl, $government = NULL)
    { // Start function changeLevel()
        if (!is_numeric($newacl)) {
            return "Access Levels should be an integer.";
        }
        if ($newacl > SUPERADMIN || $newacl < ANONYMOUS) {
            return "Error: Access level should be between " . ANONYMOUS . " and " . SUPERADMIN . ".";
        }
        if (is_numeric($groupid) && is_null($government)) {
            $org_rank = FALSE;
            $sql = "UPDATE #___security_groups SET access_level = " . $newacl . " WHERE gid = " . $groupid;
            $return = "Group ID " . $groupid . " changed to access level " . $this->getAccessName($newacl);
        }
        elseif (strtolower($government) == strtolower(
            $this->bot
                ->core("settings")->get('Security', 'Orggov')
        )
        ) {
            $org_rank = TRUE;
            $sql = "UPDATE #___security_org SET access_level = " . $newacl . " WHERE org_gov = '" . mysql_real_escape_string($government) . "' AND org_rank = '"
                . mysql_real_escape_string($groupid) . "'";
            $return = "Org Rank " . $groupid . " changed to access level " . $this->getAccessName($newacl);
        }
        else {
            $sql = FALSE;
            return "Invalid input for changelevel command.";
        }

        if ($this->bot->db->returnQuery($sql)) // Success
        {
            if ($org_rank) {
                $this->cacheManager("add", "org_ranks", $groupid, $newacl);
            }
            else {
                $tmp = array(
                    'gid'  => $groupid,
                    'name' => $this->cache['groups'][$groupid]['name']
                );
                $this->cacheManager("add", "groups", $tmp, $newacl);
            }
            $this->bot->log("SECURITY", "UPDATE", "Access level for $groupid changed to " . $this->getAccessName($newacl) . ".");

            // Clear the flexible security cache if it exists:
            if ($this->bot->core("flexible_security") != NULL) {
                $this->bot->core("flexible_security")->clear_cache();
            }

            return $return;
        }
        else {
            $this->bot->log("SECURITY", "ERROR", "MySQL Error: " . $sql);
            return "Error updating database. Check logfile for details.";
        }
    } // End function changeLevel()


    /*
    Return an array contining the group ids $name is a member of.
    */
    function getGroups($name)
    { // Start function getGroups()
        $name = ucfirst(strtolower($name));
        $tmp = array();
        if (!isset($this->cache['membership'][$name]) || empty($this->cache['membership'][$name])) {
            return -1;
        }
        foreach ($this->cache['membership'][$name] AS $gid) {
            $tmp[] = $gid;
        }
        sort($tmp);
        return $tmp;
    } // End function getGroups()

    /*
    Get group name from a gid
    */
    function getGroupInfo($gid)
    { // Start function get_group_name()
        $sql = "SELECT * FROM #___security_groups WHERE gid = " . $gid;
        $result = $this->bot->db->select($sql, MYSQL_ASSOC);
        if (empty($result)) {
            return FALSE;
        }
        else {
            return $result[0];
        } // Should return an array with elements named id, name, description, and access_level
    } // End function get_group_name()

    /*
    Returns the access level of the passed player
    If the player is in mutiple groups, the highest
    access level will be returned.
    This function only checks the specified character,
    no alts.
    */
    function getPlayerAccessLevel($player)
    { // Start function getAccessLevel()

        $uid = $this->bot->core("player")->id($player);
        // If user does not exist return ANONYMOUS access right away
        if (!$uid) {
            return 0;
        }

        $dbuid = $this->bot->core("user")->get_db_uid($player);
        if ($uid && $dbuid && ($uid != $dbuid)) {
            // Danger rodger wilco. We have a rerolled player which have not yet been deleted from users table.
            //$this -> bot -> core("user") -> erase("Security", $player, FALSE, $userId);
            //echo "Debug1: $userId does not match $dbuid \n";
            return 0;
        }

        $player = ucfirst(strtolower($player));
        // Check #1: Check Owner and SuperAdmin from Bot.conf.
        if ($player == $this->owner) {
            return 256;
        }
        if (isset($this->super_admin[$player])) {
            return 255;
        }
        // Check to see if the user is banned.
        if (isset($this->cache['banned'][$player])) {
            return -1;
        }
        // Check user's table status. users_table: anonymous (0), guest (1), member (2)
        $highestlevel = 0;
        if (isset($this->cache['guests'][$player])) {
            $highestlevel = 1;
        }
        if ($this->bot->core("settings")
            ->get("Security", "GuestInChannel")
            && $this->bot
                ->core("online")->in_chat($player)
        ) {
            $highestlevel = 1;
            $this->cache['online'][$player] = TRUE;
        }
        if (isset($this->cache['members'][$player])) {
            $highestlevel = 2;
        }

        // Check Org Rank Access.
        if ($this->bot->guildbot && isset($this->cache['members'][$player])) {
            $highestlevel = $this->org_rankAccess($player, $highestlevel);
        }
        // Check default and custom groups.
        $highestlevel = $this->groupAccess($player, $highestlevel);

        // Check if the flexible security module is enabled, if yes check there:
        if ($this->bot->core("flexible_security") != NULL) {
            $highestlevel = $this->bot->core("flexible_security")
                ->flexible_group_access($player, $highestlevel);
        }

        // !leader handling
        if ($this->bot->core("settings")
            ->exists("Leader", "Name")
            && $highestlevel < LEADER
        ) {
            if ($this->bot->core("settings")->get("Leader", "Leaderaccess")
                && strtolower($player) == strtolower(
                    $this->bot
                        ->core("settings")->get("Leader", "Name")
                )
            ) {
                $highestlevel = LEADER;
            }
        }

        // All checks done, return the result.
        return $highestlevel;
    } // End function getAccessLevel()

    /*
    Returns the access level of the passed player
    If the player is in mutiple groups, the highest
    access level will be returned.
    This function checks all alts for the highest
    access level of any registered alt.
    If one alt is banned the user as a total will
    be considered banned!
    */
    function getAccessLevel($player)
    {
        // If setting UseAlts got changed since last round whipe mains cache:
        if ($this->last_alts_status != $this->bot->core("settings")
            ->get("Security", "UseAlts")
        ) {
            unset($this->cache['mains']);
            $this->cache['mains'] = array();
            $this->last_alts_status = $this->bot->core("settings")
                ->get("Security", "UseAlts");
        }

        $player = ucfirst(strtolower($player));

        // Check if leader exists and is set, make sure to unset outdated cache entries on leader changes:
        if ($this->bot->core("settings")->exists("Leader", "Name")) {
            if ($this->bot->core("settings")->get("Leader", "Leaderaccess")
                && strtolower($this->last_leader) != strtolower(
                    $this->bot
                        ->core("settings")->get("Leader", "Name")
                )
            ) {
                if (!$this->bot->core("settings")->get("Security", "Usealts")) {
                    $leadername = $this->last_leader;
                }
                else {
                    $leadername = $this->bot->core("alts")
                        ->main($this->last_leader);
                }
                unset($this->cache['mains'][$leadername]);
                $this->last_leader = $this->bot->core("settings")
                    ->get("Leader", "Name");
                if (!$this->bot->core("settings")->get("Security", "Usealts")) {
                    $leadername = $this->last_leader;
                }
                else {
                    $leadername = $this->bot->core("alts")
                        ->main($this->last_leader);
                }
                unset($this->cache['mains'][$leadername]);
            }
        }

        // If characters in private chatgroup are counted as guests make sure that status is deleted
        // when they leave chat again:
        if ($this->bot->core("settings")->get("Security", "GuestInChannel")
            && !$this->bot->core("online")->in_chat($player)
            && (isset($this->cache['online'][$player]))
        ) {
            // Unset online cache:
            unset($this->cache['online'][$player]);
            // Get mains cache entry to check (depends on UseAlts):
            if (!$this->bot->core("settings")->get("Security", "Usealts")) {
                $onlinename = $player;
            }
            else {
                $onlinename = $this->bot->core("alts")->main($player);
            }
            // Check if there is a cache entry for $onlinename, and highest level is GUEST.
            // If both are true unset that cached entry:
            if (isset($this->cache['mains'][$onlinename])
                && $this->cache['mains'][$onlinename] == GUEST
            ) {
                unset($this->cache['mains'][$onlinename]);
            }
        }

        $uid = $this->bot->core("player")->id($player);
        // If user does not exist return ANONYMOUS access right away
        if (!$uid) {
            return 0;
        }

        $dbuid = $this->bot->core("user")->get_db_uid($player);
        if ($uid && $dbuid && ($uid != $dbuid)) {
            // Danger rodger wilco. We have a rerolled player which have not yet been deleted from users table.
            //$this -> bot -> core("user") -> erase("Security", $player, FALSE, $userId);
            //echo "Debug: $userId does not match $dbuid \n";
            return 0;
        }

        // If alts should not be queried just return the access level for $player
        if (!$this->bot->core("settings")->get("Security", "Usealts")) {
            // If we got a cached entry, return that:
            if (isset($this->cache['mains'][$player])) {
                return $this->cache['mains'][$player];
            }
            // Otherwise get highest access level, cache it, and then return it.
            $highest = $this->getPlayerAccessLevel($player);
            $this->cache['mains'][$player] = $highest;
            return $highest;
        }

        // Check mains cache
        if (isset($this->cache['mains'][$this->bot->core("alts")
            ->main($player)])
        ) {
            return $this->cache['mains'][$this->bot->core("alts")
                ->main($player)];
        }

        // Get main and alts
        $main = $this->bot->core("alts")->main($player);
        $alts = $this->bot->core("alts")->get_alts($main);

        // Check main and alts for owner or config file defined superadmins
        $foundSA = FALSE;
        if ($main == $this->owner) {
            $this->cache['mains'][$main] = 256;
            return 256;
        }
        if (isset($this->super_admin[$main])) {
            $this->cache['mains'][$main] = 255;
            $foundSA = TRUE;
        }
        if (!empty($alts)) {
            foreach ($alts as $alt) {
                if ($alt == $this->owner) {
                    $this->cache['mains'][$main] = 256;
                    return 256;
                }
                if (isset($this->super_admin[$main])) {
                    $this->cache['mains'][$main] = 255;
                    $foundSA = TRUE;
                }
            }
        }
        if ($foundSA) {
            return 255;
        }

        // Get access rights of main
        $access = $this->getPlayerAccessLevel($main);

        // if main is banned user is considered banned
        if ($access == -1) {
            $this->cache['mains'][$main] = -1;
            return -1;
        }

        // if user got alts check all their access levels
        // if nobody is banned return highest access level
        // over all alts.
        // if banned return banned.
        if (!empty($alts)) {
            foreach ($alts as $alt) {
                $newaccess = $this->getPlayerAccessLevel($alt);
                if ($newaccess == -1) {
                    $this->cache['mains'][$main] = -1;
                    return -1;
                }
                if ($newaccess > $access) {
                    $access = $newaccess;
                }
            }
        }

        $this->cache['mains'][$main] = $access;
        return $access;
    }


    /*
    Figures out the access level based on org rank.
    */
    function org_rankAccess($player, $highest)
    { // Start function org_rankAccess()
        $who = $this->bot->core("whoIs")
            ->lookup($player, TRUE); // Do whoIs with no XML lookup, guild members should be cached...
        if ($who instanceof BotError) {
            return $highest;
        }
        if ($who['org'] <> $this->bot->guildname) {
            return $highest;
        }
        if ($this->cache['org_ranks'][$who["rank"]] > $highest) {
            return $this->cache['org_ranks'][$who["rank"]];
        }
        else {
            return $highest;
        }
    } // End function org_rankAccess()

    function org_rank_id($player, $highest)
    { // Start function org_rankAccess()
        $who = $this->bot->core("whoIs")
            ->lookup($player, TRUE); // Do whoIs with no XML lookup, guild members should be cached...
        if ($who instanceof BotError) {
            return $highest;
        }
        if ($who['org'] <> $this->bot->guildname) {
            return $highest;
        }
        if ($this->cache['org_rank_ids'][$who["rank"]] < $highest) {
            return $this->cache['org_rank_ids'][$who["rank"]];
        }
        else {
            return $highest;
        }
    } // End function org_rankAccess()

    /*
    Figure out the access level based on group membership
    Should only be called by getAccessLevel()
    */
    function groupAccess($player, $highest)
    { // Start function groupAccess()
        $groups = $this->getGroups($player);
        if ($groups == -1) {
            return $highest; // $player is not a member of any groups.
        }
        foreach ($groups as $gid) {
            if ($this->cache['groups'][$gid]['access_level'] > $highest) {
                $highest = $this->cache['groups'][$gid]['access_level'];
            }
        }
        return $highest;
    } // End function groupAccess()

    /*
    Checks $name's access agnist $level.
    Returns TRUE if they meet (or exceede) the specified level, otherwise false.
    Replacment for is_admin(), check_security().

    Reminder: If you do checkAccess($name, "banned"), this function will return TRUE if they are NOT banned!
    IF the user is banned, this function will return FALSE.
    */
    function checkAccess($name, $level)
    { // Start function checkAccess()
        if (!$this->enabled) {
            return FALSE;
        } // No access is granted until the secuirty subsystems are ready.
        $name = ucfirst(strtolower($name));
        $level = strtoupper($level);
        if ($level == "RAIDLEADER") {
            $this->bot->log("SECURITY", "WARNING", "Deprecated level raidleader passed to checkAccess().");
            $level = "LEADER";
        }
        $access = $this->getAccessLevel($name);
        if (is_numeric($level)) // Just check numbers.
        {
            if ($access >= $level) {
                return TRUE;
            }
            else {
                return FALSE;
            }
        }
        else {
            switch ($level) { // Start switch
            case "ANONYMOUS":
                if ($access >= ANONYMOUS) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "GUEST":
                if ($access >= GUEST) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "MEMBER":
                if ($access >= MEMBER) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "LEADER":
                if ($access >= LEADER) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "ADMIN":
                if ($access >= ADMIN) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "SUPERADMIN":
                if ($access >= SUPERADMIN) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "OWNER":
                if ($access >= OWNER) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            case "BANNED":
                $this->bot->log("SECURITY", "WARNING", "Consider using the isBanned(\$name) function instead of checkAccess(\$name, \$level) for ban checking.");
                if ($access > BANNED) {
                    return TRUE;
                }
                else {
                    return FALSE;
                }
                break;
            default:
                return FALSE; // Unknown Access Level.
                break;
            } // End switch
        }
    } // End function checkAccess()

    // Returns true if the user is banned, otherwise false
    function isBanned($name)
    { // Start function isBanned()
        if ($this->enabled) {
            return (isset($this->cache['banned'][ucfirst(strtolower($name))]));
        }
        else {
            return FALSE;
        }
    } // End function isBanned()

    /*
    Sets the org governing form, resets org rank access if the governing form changes.
    */
    function setGovernment()
    { // Start setGovernment()
        if (!$this->bot->guildbot) {
            return FALSE;
        } // Raidbot.
        //$guild = $this -> bot -> db -> select("SELECT org_name FROM #___whois WHERE nickname = '".$this -> bot -> botname."'");
        $whois = $this->bot->core("whoIs")->lookup($this->bot->botname);
        if ($whois && !($whois instanceof BotError)) {
            $guild = $whois['org'];
        }
        //if(!empty($guild) && $guild[0][0] != "")
        if (empty($guild) || $guild != "") {
            $guild = $this->bot->guildname;
        }
        $sql = "SELECT DISTINCT org_rank_id,org_rank FROM #___whois WHERE org_name = '" . mysql_real_escape_string($guild) . "' ORDER BY org_rank_id ASC"; // Gets the org ranks.
        $result = $this->bot->db->select($sql);
        if (empty($result)) {
            //$this -> bot -> core("settings") -> save("Security", "orggov", "Unknown");
            return FALSE; // Org roster hans't updated yet. FIXME: Need to try again later.
        }

        if ($result[0][1] == "Director") // Faction
        {
            $orggov = "Faction";
        }
        elseif ($result[0][1] == "Monarch") // Monarchy
        {
            $orggov = "Monarchy";
        }
        elseif ($result[0][1] == "Lord") // Feudalism
        {
            $orggov = "Feudalism";
        }
        elseif ($result[0][1] == "Anarchist") // Anarchism
        {
            $orggov = "Anarchism";
        }
        elseif ($result[0][1] == "President") // Republic or Department
        {
            if ($result[1][1] == "General") // Department
            {
                $orggov = "Department";
            }
            elseif ($result[1][1] == "Advisor") // Republic
            {
                $orggov = "Republic";
            }
            else // Unknown?!?
            {
                $orggov = "Unknown";
            }
        }
        else // Unknown?!?
        {
            $orggov = "Unknown";
        }
        if ($this->bot->core("settings")->get('Security', 'Orggov') <> $orggov
        ) // Change detected, reset access levels.
        {
            if ($orggov == "Unknown") {
                return $this->bot->core("settings")->get('Security', 'Orggov');
            }
            else {
                $this->bot->core("settings")
                    ->save("Security", "orggov", $orggov);
                $sql = "UPDATE #___security_org SET access_level = 2";
                $this->bot->db->query($sql);
                $this->cacheorg_ranks();
            }
        }
        return $this->bot->core("settings")->get('Security', 'Orggov');
    } // End setGovernment()

    /*
    Admin.php Functions:

    To Do:
    function add_admin($source, $group, $name, $type) // 0.3
    function list_admin($source, $type) // 0.3
    function member_del($name, $group, $member) // 0.2
    function member_add($name, $group, $member) // 0.2
    function group_add($name, $group) // 0.2
    function group_del($name, $group) // 0.2
    function group_show() // 0.2

    Done:
    function in_group($name, $group) // 0.2
    - in_group() is a wrapper for checkAccess() in 0.4
    */

    // --------------------------------------------------
    // Functions to setup and manage the security cache.
    // --------------------------------------------------

    /*
    Adds and removes information from the cache.
    $action: add or rem
    $cache: Which cache to modify (groups, guests, members, banned, groupmem, org_ranks, main, maincache)
    $info: The information to add (or remove)
    $more: Extra informaion needed for some actions.
    */
    function cacheManager($action, $cache, $info, $more = NULL)
    { // Start function cacheManager()
        $action = strtolower($action);
        if ($action == "add") {
            $action = TRUE;
        }
        else {
            $action = FALSE;
        }
        $cache = strtolower($cache);
        switch ($cache) {
        case "guests":
            if ($action) {
                $this->cache['guests'][$info] = $info;
            }
            else {
                unset($this->cache['guests'][$info]);
            }
            unset($this->cache['mains'][$this->bot->core("alts")
                ->main($info)]);
            break;
        case "members":
            if ($action) {
                $this->cache['members'][$info] = $info;
            }
            else {
                unset($this->cache['members'][$info]);
            }
            unset($this->cache['mains'][$this->bot->core("alts")
                ->main($info)]);
            break;
        case "banned":
            if ($action) {
                $this->cache['banned'][$info] = $info;
            }
            else {
                unset($this->cache['banned'][$info]);
            }
            unset($this->cache['mains'][$this->bot->core("alts")
                ->main($info)]);
            break;
        case "groups":
            if ($action) {
                if (is_null($more)) // Adding a new group.
                {
                    $this->cache['groups'][$info['gid']] = $info;
                    $this->cache['groups'][$info['name']] = $info;
                }
                else // Updating a groups access level.
                {
                    $this->cache['groups'][$info['gid']]['access_level'] = $more;
                    $this->cache['groups'][$info['name']]['access_level'] = $more;
                    foreach (
                        $this->cache['groups'][$info['gid']]['members']
                        as $member
                    ) {
                        unset($this->cache['mains'][$this->bot->core("alts")
                            ->main($member)]);
                    }
                }
            }
            else {
                $gid = $this->cache['groups'][$info]['gid'];
                $gname = $info;
                foreach ($this->cache['groups'][$gid]['members'] as $member) {
                    unset($this->cache['membership'][$member][$gid]);
                    unset($this->cache['mains'][$this->bot->core("alts")
                        ->main($member)]);
                }
                unset ($this->cache['groups'][$gid]);
                unset ($this->cache['groups'][$gname]);
            }
            break;
        case "groupmem":
            $group = strtolower($info);
            $member = ucfirst(strtolower($more));
            $gid = $this->getGroupId($group);
            if ($action) {
                $this->cache['groups'][$group]['members'][$member] = $member;
                $this->cache['groups'][$gid]['members'][$member] = $member;
                $this->cache['membership'][$member][$gid] = $gid;
            }
            else {
                unset($this->cache['membership'][$member][$gid]);
                unset($this->cache['groups'][$group]['members'][$member]);
                unset($this->cache['groups'][$gid]['members'][$member]);
            }
            unset($this->cache['mains'][$this->bot->core("alts")
                ->main($member)]);
            break;
        case "org_ranks":
            if ($action) {
                $this->cache['org_ranks'][$info] = $more;
            }
            else {
                unset($this->cache['org_ranks'][$info]);
            }
            unset($this->cache['mains']);
            $this->cache['mains'] = array();
            break;
        case "main":
            unset($this->cache['mains'][$this->bot->core("alts")
                ->main($info)]);
            break;
        case "maincache":
            unset($this->cache['mains']);
            $this->cache['mains'] = array();
            break;
        }
    } // End function cacheManager()

    /*
    Loads security information into the cache.
    */
    function cacheSecurity()
    { // Start function cacheSecurity()
        $this->cacheUsers(); // Cache users security.
        $this->cacheGroups(); // Admin groups and members.
        if ($this->bot->guildbot) {
            $this->cacheorg_ranks(); // Cache org rank security if this is a guildBot.
        }
    } // End function cacheSecurity()

    // Adds information from the users table to the security cache.
    function cacheUsers()
    { // Start function cacheUsers()
        $this->cache['members'] = array();
        $this->cache['guests'] = array();
        $this->cache['banned'] = array();
        $this->cache['mains'] = array();
        $this->cache['online'] = array();
        $sql = "SELECT nickname,userLevel FROM #___users WHERE userLevel != 0";
        $result = $this->bot->db->select($sql);
        if (empty($result)) {
            return FALSE;
        } // No users...huh. ;-)
        foreach ($result as $user) {
            if ($user[1] == 2) {
                $this->cache['members'][$user[0]] = $user[0];
            }
            elseif ($user[1] == 1) {
                $this->cache['guests'][$user[0]] = $user[0];
            }
            /*** FIXME ***/
            // This is to keep 0.3.x BeBot admins from being treated as banned outright.
            elseif ($user[1] == 3) {
                $this->cache['members'][$user[0]] = $user[0];
            }
            else {
                $this->cache['banned'][$user[0]] = $user[0];
            }
        }
        unset($result);
    } // End function cacheUsers()

    // Adds the org_rank access levels to the cache.
    function cacheorg_ranks()
    { // Start function cacheorg_ranks()
        $this->cache['org_ranks'] = array();
        if (!($this->bot->core("settings")->exists('Security', 'Orggov'))) {
            $this->setGovernment(); // Won't work until the org governing form is identified.
        }
        if ($this->bot->core("settings")
            ->get('Security', 'Orggov') instanceof BotError
        ) {
            return FALSE; // If the setting is still missing, we can't do anything about it.
        }
        if ($this->bot->core("settings")
            ->get('Security', 'Orggov') == "Unknown"
        ) {
            $this->setGovernment(); // Won't work until the org governing form is identified.
        }
        if ($this->bot->core("settings")
            ->get('Security', 'Orggov') == "Unknown"
        ) {
            return FALSE; // Tried to ID the org government and failed. Try again in 12 hours.
        }
        $sql = "SELECT org_rank, access_level, org_rank_id FROM #___security_org ";
        $sql .= "WHERE org_gov = '" . $this->bot->core("settings")
            ->get('Security', 'Orggov') . "' ";
        $sql .= "ORDER BY org_rank_id ASC";
        $result = $this->bot->db->select($sql, MYSQL_ASSOC);
        if (empty($result)) {
            return FALSE;
        } // Nothing to cache.
        // Now cache them...
        foreach ($result as $org_rank) {
            $this->cache['org_ranks'][$org_rank['org_rank']] = $org_rank['access_level'];
            $this->cache['org_rank_ids'][$org_rank['org_rank']] = $org_rank['org_rank_id'];
        }
        return TRUE;
    } // End function cacheorg_ranks()

    // Adds the groups and their members to the cache.
    function cacheGroups()
    { // Start function cacheGroups()
        $this->cache['groups'] = array();
        $this->cache['membership'] = array();
        $sql = "SELECT * FROM #___security_groups";
        $result = $this->bot->db->select($sql, MYSQL_ASSOC);
        if (empty($result)) {
            $this->bot->log("SECURITY", "ERROR", "No groups exisit, not even the groups created by default. Something is very wrong.");
            exit();
        }
        foreach ($result as $group) { //gid, name, description, access_level
            $this->cache['groups'][$group['gid']] = $group;
            $this->cache['groups'][$group['name']] = $group;
            $gid = $this->getGroupId($group['name']);
            $sql = "SELECT name FROM #___security_members WHERE gid = " . $gid;
            $members = $this->bot->db->select($sql, MYSQL_ASSOC);
            $this->cache['groups'][$group['gid']]['members'] = array();
            $this->cache['groups'][$group['name']]['members'] = array();
            // Cache members of the group, no big deal if there are no members.
            if (!empty($members)) {
                foreach ($members as $member) {
                    $this->cache['groups'][$group['gid']]['members'][$member['name']] = $member['name'];
                    $this->cache['groups'][$group['name']]['members'][$member['name']] = $member['name'];

                    if (!isset($this->cache['membership'][$member['name']])) {
                        $this->cache['membership'][$member['name']] = array();
                    }
                    $this->cache['membership'][$member['name']][$group['gid']] = $group['gid'];
                }
            }

        }
    } // End function cacheGroups()

    /*
    Returns highest access level.
    */
    function whoAmI($name)
    { // Start function whoAmI
        $groups = $this->bot->core("security")->get_groups($name);
        $access = $this->bot->core("security")->get_access_level($name);
        $access = $this->getAccessName($access);
        $message = "Your access level is " . strtoupper($access) . ".";
        if ($groups <> -1) {
            $groupmsg = " You are a member of the following security groups: ";
            foreach ($groups as $gid) {
                $groupmsg .= strtolower($this->cache['groups'][$gid]['name']) . ", ";
            }
            $groupmsg = rtrim($groupmsg);
            $groupmsg = rtrim($groupmsg, ",");
        }
        return $message . $groupmsg;
    } // End function whoAmI

    function whoIs($name)
    { // Start function whoIs()
        $player = ucfirst(strtolower($name));
        $groups = $this->bot->core("security")->get_groups($player);
        $access = $this->bot->core("security")->get_access_level($player);
        $access = $this->getAccessName($access);
        $message = $player . "'s highest access level is " . strtoupper($access) . ".";
        if ($groups <> -1) {
            $groupmsg = " " . $player . " is a member of the following security groups: ";
            foreach ($groups as $gid) {
                $groupmsg .= strtolower($this->cache['groups'][$gid]['name']) . ", ";
            }
            $groupmsg = rtrim($groupmsg);
            $groupmsg = rtrim($groupmsg, ",");
        }
        return $message . $groupmsg;
    } // End function whoIs()

    function getAccessName($access)
    { // Start function getAccessName()
        switch ($access) { // Start switch
        case OWNER:
            $access = "OWNER";
            break;
        case SUPERADMIN:
            $access = "SUPERADMIN";
            break;
        case ADMIN:
            $access = "ADMIN";
            break;
        case LEADER:
            $access = "LEADER";
            break;
        case MEMBER:
            $access = "MEMBER";
            break;
        case GUEST:
            $access = "GUEST";
            break;
        case ANONYMOUS:
            $access = "ANONYMOUS";
            break;
        case BANNED:
            $access = "BANNED";
            break;
        default:
            $access = "UNKNOWN (" . STRTOUPPER($access) . ")";
            break;
        } // End switch
        return $access;
    } // End function getAccessName()

} // End of Class
?>
