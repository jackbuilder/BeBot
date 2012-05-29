<?php
/*
* Relay.php - Relaying between guest channel, org chat and other bots via tells or private group
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
$relay = new Relay($bot);
/*
The Class itself...
*/
class Relay extends BaseActiveModule
{

    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        $this->registerModule("relay");
        $this->registerCommand('sendTell', 'gcr', 'SUPERADMIN');
        $this->registerCommand('externalPrivateGroupMessage', 'gcr', 'MEMBER');
        $this->registerCommand('sendTell', 'gcrc', 'SUPERADMIN');
        $this->registerCommand('externalPrivateGroupMessage', 'gcrc', 'MEMBER');
        $this->registerEvent("privateGroup");
        $this->registerEvent("groupMessage", "org");
        $this->registerEvent("privateGroupInvite");
        $this->registerEvent("connect");
        $this->registerEvent("cron", "5min");
        $this->registerEvent("cron", "2sec");
        $this->registerEvent("externalPrivateGroup");
        $this->registerEvent("buddy");
        $this->registerEvent("externalPrivateGroupJoin");
        $this->registerEvent("privateGroupJoin");
        $this->registerEvent("privateGroupLeave");
        $this->bot->core('prefs')
            ->create('AutoInv', 'receive_auto_invite', 'Automatic invites to private group should be?', 'Off', 'Off;On');
        $this->bot->db->query(
            "CREATE TABLE IF NOT EXISTS " . $this->bot->db->defineTableName("relay", "false") . "
               (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						botname VARCHAR(20),
        	 			type VARCHAR(30),
						time INT,
        				msg TEXT)"
        );
        $this->bot->core("settings")
            ->create('Relay', 'Priv', 'Both', 'Where should private group relay to', 'Both;Guildchat;Relaybots;None');
        $this->bot->core("settings")
            ->create('Relay', 'Org', 'Both', 'Where should guild chat group relay to', 'Both;Privgroup;Relaybots;None');
        $this->bot->core("settings")
            ->create('Relay', 'Inc', 'Both', 'Where should incoming messages relay to', 'Both;Guildchat;Privgroup;None');
        $this->bot->core("settings")
            ->create('Relay', 'Relay', '', 'What is the name of the bot that we are using as a relay?');
        $this->bot->core("settings")
            ->create(
            'Relay', 'AutoinviteRelayGroup', '',
            'RELAYBOT: What is the name of the groups that all bots will be in for invites to the relay bot? (Leave empty to invite all bots on the roster)'
        );
        $this->bot->core("settings")
            ->create('Relay', 'Status', FALSE, 'Relay should be');
        $this->bot->core("settings")
            ->create('Relay', 'Autoinvite', FALSE, 'RELAYBOT: Autoinvite bots to the relay group');
        $this->bot->core("settings")
            ->create(
            'Relay', 'Type', 'Tells',
            'How should we relay, via a private group or via tells?  Tells is not the recommended method of handling relays, is slower, and less reliable, and can only be used between two bots.  See the help for <pre>gcr for more information.',
            'Pgroup;Tells;DB'
        );
        $this->bot->core("settings")
            ->create("Relay", "StrictNameCheck", TRUE, "Has the name of the sender of tells with <pre>gcr commands to be an exact match with the name of the relay bot?");
        $this->bot->core("settings")
            ->create('Relay', 'OtherPrefixs', '', 'What other prefixes should be Checked in the in the Relay Group seperated by ; eg @;.;#');
        $this->bot->core("settings")
            ->create("Relay", "Color", FALSE, "color outgoing message to the relay?");
        $this->bot->core("settings")
            ->create("Relay", "Encrypt", FALSE, "Should messages be Encrypted?");
        $this->bot->core("settings")
            ->create("Relay", "Key", "", "Encription Key for Sending and Recieving Messages?");
        $this->bot->core("settings")
            ->create('Relay', 'Orgnameon', 'Both', 'Guild Prefix should be Output? (Requires other Bots to have TypeTag Setting ON)', 'Both;Guildchat;NotGuildChat;None');
        $this->bot->core("settings")
            ->create("Relay", "Ignore", "", "Who should be ignored on incoming Relay? use ; to split eg mike;bob");
        $this->bot->core("settings")
            ->create(
            'Relay', 'TypeTag', FALSE,
            'Should Bot Send a Special Tag to Say What Type of Message its Relaying, Note (Other Bots are Required to Have a Module that Supports This Feature)'
        );
        $this->bot->core("settings")
            ->create("Relay", 'ShowMain', FALSE, "Should we display the name of the characters main when relaying?");
        $this->bot->core("settings")
            ->create("Relay", 'TruncateMain', '6', "How many characters of the main's name to display?", '4;6;8;10;13');
        $this->bot->core("colors")->defineScheme("relay", "channel", "normal");
        $this->bot->core("colors")->defineScheme("relay", "name", "normal");
        $this->bot->core("colors")->defineScheme("relay", "message", "normal");
        $this->bot->core("colors")
            ->defineScheme("relay", "mainname", "normal");
        $this->help['description'] = "Plugin to enable relay between guilds and private groups.";
        $this->help['command']['gcr <message>'] = "Has the bot say a message (useful for testing or other purposes).";
        $this->help['notes']
            = "How to use a private group relay:<BR><BR>Step 1<BR>Create a new bot to use as the relay.  Add the bots that will be using the relay as members.  Configure the relaybot to autoinvite the bots that will be using it.  (It is highly recommended to disable nearly all plugins on the relaybot.  As you are only using it for relaying purposes, there should be no reason why anyone needs access to it other than the bots and yourself.)<BR><BR>Step 2<BR>Install the Relay.php plugin onto the bots that will be using the relay.  Make sure to disable GuildRelay_GUILD.php and Relay_GUILD.php as this will conflict with them.<BR><BR>Step 3<BR>Give the bots that will be relaying the correct access level and permissions to use <pre>gcr. (So if Bot1 is relaying to Bot2 via Relay1, Bot1 needs access to <pre>gcr on Bot2 via sendToGroup, and vice versa.)<BR><BR>Step 4<BR>Restart the bots if you haven't already, and configure your settings to your specifications.<BR><BR>Step 5<BR>Enjoy lightning quick relay messages, and less bot lag (due to no longer queueing the relay messages via /sendTell).";
        $this->update();
        $this->db_relay = FALSE;
        $this->lastsent = 0;
        $this->monbuds = FALSE;
    }


    function update()
    {
        switch ($this->bot->db->getVersion("relay")) {
        case 1:
            $this->bot->core("settings")
                ->update("Relay", "Type", "defaultoptions", "Pgroup;Tells;DB");
        Default:
        }
        $this->bot->db->setVersion("relay", 2);
    }


    function privateGroupInvite($group)
    {
        if (strtolower(
            $this->bot->core("settings")
                ->get('Relay', 'Relay')
        ) == strtolower($group)
        ) {
            $this->bot->core("chat")->pgroup_join($group);
        }
    }


    /*
    This gets called on a msg in the private group.
    This is where we send our message to org chat and to our relay.
    */
    function privateGroup($name, $msg)
    {
        $this->relayToGuildChat($name, $msg);
    }


    /*
    This gets called on a msg in the group.
    This is where we send our message to the private group and to our relay.
    */
    function groupMessage($name, $group, $msg)
    {
        $this->relay_to_pgroup($name, $msg, "chat");
    }


    /*
    This gets called on a sendTell with the command
    */
    function sendTell($name, $msg)
    {
        $input = $this->parseCommand(
            $msg, array(
                'com',
                'args'
            )
        );
        if (strtolower($input['com']) == 'gcrc') {
            $this->incomingCommand($name, $input['args'], "sendTell");
        }
        elseif (strtolower($input['com']) == 'gcr'
            && $this->bot
                ->core("settings")->get('Relay', 'Status')
            && (($this->bot
                ->core("settings")
                ->get("Relay", "Strictnamecheck")
                && strtolower(
                    $this->bot
                        ->core("settings")
                        ->get("Relay", "Relay")
                ) == strtolower($name))
                || !($this->bot
                    ->core("settings")->get("Relay", "Strictnamecheck")))
        ) {
            $txt = $input['args'];
            if ($this->bot->core("settings")
                ->get('Relay', 'Inc') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Inc') == "Guildchat"
            ) {
                $this->bot->send_gc($txt);
            }
            if ($this->bot->core("settings")
                ->get('Relay', 'Inc') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Inc') == "Privgroup"
            ) {
                $this->bot->send_pgroup($txt);
            }
            $this->relayToIrc($txt);
        }
    }


    function externalPrivateGroupMessage($pgroup, $name, $msg, $db = FALSE)
    {
        $input = $this->parseCommand(
            $msg, array(
                'com',
                'args'
            )
        );
        if (strtolower($input['com']) == 'gcrc') {
            $this->incomingCommand($name, $input['args'], "extpg");
        }
        elseif (strtolower($input['com']) == 'gcr'
            && $this->bot
                ->core("settings")
                ->get('Relay', 'Status')
            && ($db
                || strtolower(
                    $this->bot
                        ->core("settings")->get('Relay', 'Relay')
                ) == strtolower($pgroup))
        ) {
            $txt = $input['args'];
            $txt = explode(" ", $txt, 2);
            if ($txt[0] == '&$encrypt$&') {
                $txt = $this->decrypt($txt[1]);
            }
            else {
                $txt = implode(" ", $txt);
            }
            Switch ($this->bot->core("settings")->get('Relay', 'Orgnameon')) {
            case 'Both':
                $type = "FALSE";
                Break;
            case 'Guildchat':
                $type = "notchat";
                Break;
            case 'NotGuildChat':
                $type = "chat";
                Break;
            case 'None':
                $type = "(chat|notchat)";
                Break;
            }
            $txt = preg_replace("/(.+)#&%" . $type . "%&#/U", "", $txt);
            $txt = str_replace("#&%chat%&#", "", $txt);
            $txt = str_replace("#&%notchat%&#", "", $txt);
            $ignore = $this->bot->core("settings")->get('Relay', 'Ignore');
            $ignore = explode(";", $ignore);
            foreach ($ignore as $k => $ig) {
                $ignore[$k] = ucfirst(strtolower(trim($ig)));
            }
            $ignore = implode("|", $ignore);
            $ignore = "(" . $ignore . ")";
            if (preg_match("/(.+)#relay_name##" . $ignore . ":##end## ##relay_message##(.+)##end##/i", $txt)) {
                $this->bot->log("RELAY", "IGNORE", $txt);
                Return;
            }
            if ($this->bot->core("settings")
                ->get('Relay', 'Inc') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Inc') == "Guildchat"
            ) {
                $this->bot->send_gc($txt);
            }
            if ($this->bot->core("settings")
                ->get('Relay', 'Inc') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Inc') == "Privgroup"
            ) {
                $this->bot->send_pgroup($txt);
            }
            $this->relayToIrc($txt);
        }
    }


    function externalPrivateGroup($group, $name, $msg)
    {
        if (strtolower(
            $this->bot->core("settings")
                ->get('Relay', 'Relay')
        ) == strtolower($name)
        ) {
            $msg2 = explode(" ", $msg, 2);
            if (strtolower($msg2[0]) == "relaymsg") {
                $this->externalPrivateGroupMessage($group, $name, "Message from Relay Bot: " . $msg2[1]);
            }
        }
        $prefixs = $this->bot->core("settings")->get('Relay', 'OtherPrefixs');
        if ($prefixs == "") {
            Return;
        }
        $prefixs = str_replace(" ", "", $prefixs);
        $prefixs = explode(";", $prefixs);
        foreach ($prefixs as $pre) {
            if ($msg[0] == $pre) {
                $found = TRUE;
            }
        }
        if (!$found) {
            Return;
        }
        if ($this->bot->core("access_control")->check_for_access($name, "gcr")
        ) {
            $msg = substr($msg, 1);
            $this->externalPrivateGroupMessage($group, $name, $msg);
        }
    }


    function commandHandler($name, $msg, $origin)
    {
    }


    /*
    This gets called on a msg in the group.
    This is where we send our message to the private group and to our relay.
    */
    function relay_to_pgroup($name, $msg, $type = "notchat")
    {
        if ($this->bot->core("settings")->get('Relay', 'Status')) {
            $namestr = "";
            $mainstr = "";
            $main = "";
            if ($name != "0") {
                $namestr = $this->getNameString($name);
            }
            if ($this->bot->core("settings")->get('Relay', 'Gcname') != "") {
                $relaystring = "[##relay_channel##" . $this->bot
                    ->core("settings")->get('Relay', 'Gcname') . "##end##] ";
            }
            $relaystring .= $namestr . "##relay_message##" . $msg . " ##end##";
            if ($this->bot->core("settings")
                ->get('Relay', 'Org') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Org') == "Privgroup"
            ) {
                $this->bot->send_pgroup($relaystring);
            }
            if ($this->bot->core("settings")
                ->get("Relay", "Relay") != ''
                && ($this->bot
                    ->core("settings")
                    ->get('Relay', 'Org') == "Both"
                    || $this->bot
                        ->core("settings")
                        ->get('Relay', 'Org') == "Relaybots")
            ) {
                $this->relayToBot($relaystring, TRUE, FALSE, $type);
            }
        }
    }


    // Relays $msg without any further modifications to other bot(s).
    // If $chat is true $msg will be relayed as chat with added "<pre>gcr " prefix.
    // If $chat is false $msg will be relayed as it is without any addon, this can be used to relay commands to the other bot(s).
    function relayToBot($msg, $chat = TRUE, $alt = FALSE, $type = "notchat")
    {
        $type = strtolower($type);
        if ($alt) {
            $prefix = "<pre>$alt ";
        }
        elseif ($chat) {
            $prefix = "<pre>gcr ";
            if ($this->bot->core("settings")->get('Relay', 'TypeTag')) {
                $msg = str_replace(
                    "[##relay_channel##" . $this->bot
                        ->core("settings")
                        ->get('Relay', 'Gcname') . "##end##] ", "[##relay_channel##" . $this->bot
                    ->core("settings")
                    ->get('Relay', 'Gcname') . "##end##] #&%" . $type . "%&#", $msg
                );
            }
        }
        else {
            $prefix = "";
        }
        if ($this->bot->core("settings")->get('Relay', 'Status')
            && ($this->bot
                ->core("settings")
                ->get("Relay", "Relay") != ''
                || strtoupper(
                    $this->bot
                        ->core("settings")->get("Relay", "Type")
                ) == "DB")
        ) {
            $color = $this->bot->core("settings")->get('Relay', 'Color');
            if (strtolower(
                $this->bot->core("settings")
                    ->get('Relay', 'Type')
            ) == "tells"
            ) {
                $this->bot->send_tell(
                    $this->bot->core("settings")
                        ->get('Relay', 'Relay'), $prefix . $msg, 0, FALSE, TRUE, $color
                );
            }
            elseif (strtolower(
                $this->bot->core("settings")
                    ->get('Relay', 'Type')
            ) == "pgroup"
            ) {
                if (!$alt
                    && $this->bot->core("settings")
                        ->get('Relay', 'Encrypt')
                ) {
                    $msg = $this->encrypt($msg);
                    $msg = '&$encrypt$& ' . $msg;
                }
                $this->bot->send_pgroup(
                    $prefix . $msg, $this->bot
                        ->core("settings")->get('Relay', 'Relay'), TRUE, $color
                );
            }
            else {
                if ($alt) {
                    $prefix = $alt;
                }
                else {
                    $prefix = "gcr";
                }
                $this->bot->db->query(
                    "INSERT INTO #___relay (time, type, botname, msg) VALUES (" . time() . ", '$prefix', '" . $this->bot->botname . "', '" . mysql_real_escape_string($msg) . "')"
                );
            }
        }
    }


    // Relays $msg to IRC module (and from there after formatting to IRC channel)
    function relayToIrc($msg)
    {
        $msg = preg_replace("/##end##/U", "", $msg);
        $msg = preg_replace("/##(.+)##/U", "", $msg);
        $this->bot->send_irc("", "", $msg);
    }


    /*
    This gets called on a msg in the private group.
    This is where we send our message to org chat and to our relay.
    */
    function relayToGuildChat($name, $msg)
    {
        if ($this->bot->core("settings")->get('Relay', 'Status')) {
            $namestr = "";
            if ($name != "0") {
                $namestr = $this->getNameString($name);
            }
            $relaystring = "[##relay_channel##" . $this->bot->core("settings")
                ->get('Relay', 'Pgname') . "##end##] " . $namestr . "##relay_message##" . $msg . " ##end##";
            if ($this->bot->core("settings")
                ->get('Relay', 'Priv') == "Both"
                || $this->bot
                    ->core("settings")
                    ->get('Relay', 'Priv') == "Guildchat"
            ) {
                $this->bot->send_gc($relaystring);
            }
            if ($this->bot->core("settings")
                ->get("Relay", "Relay") != ''
                && ($this->bot
                    ->core("settings")
                    ->get('Relay', 'Priv') == "Both"
                    || $this->bot
                        ->core("settings")
                        ->get('Relay', 'Priv') == "Relaybots")
            ) {
                $this->relayToBot($relaystring);
            }
        }
    }


    // This gets called on cron
    function cron($cron)
    {
        if ($cron == 300) {
            if (!isset($this->guildnameset)) {
                //guildname is not neccessarily set before the bot connects so create those settings here.
                $this->bot->core("settings")
                    ->create('Relay', 'Pgname', $this->bot->guildname . ' Guest', 'What name should we show when we relay from the private group?');
                $this->bot->core("settings")
                    ->create('Relay', 'Gcname', $this->bot->guildname, 'What name should we show when we relay from guild chat?');
                $this->guildnameset = TRUE;
                $this->monbuds = TRUE;
                $this->externalPrivateGroupJoin(FALSE, FALSE, TRUE);
                $this->relayToBot("onlinereq", FALSE, "gcrc");
            }
            if ($this->bot->core("settings")->get('Relay', 'Autoinvite')) {
                $security_group = $this->bot->core("settings")
                    ->get('Relay', 'AutoinviteRelayGroup');
                if (!empty($security_group)) {
                    $security_groups_gid = $this->bot->db->select("SELECT gid,name FROM #___security_groups WHERE name = '$security_group'");
                    if (!empty($security_groups_gid)) {
                        if ($security_groups_gid[0][0]) {
                            $relayedbots_gid = $security_groups_gid[0][0];
                            $thisbotname = $this->bot->botname;
                            $invitelist = $this->bot->db->select(
                                "SELECT ol.nickname,ol.status_pg,ol.botname,sm.gid,sm.name FROM #___online AS ol LEFT JOIN #___security_members AS sm ON ol.nickname = sm.name WHERE sm.gid = $relayedbots_gid AND ol.status_pg = 0 AND ol.botname = \"$thisbotname\""
                            );
                            if ($invitelist[0]) {
                                foreach ($invitelist as $inviteme) {
                                    $this->bot->core("chat")
                                        ->pgroup_invite($inviteme[0]);
                                    echo " NOTICE: Inviting " . $inviteme[0] . " to the bot\n";
                                }
                            }
                        }
                    }
                    else {
                        echo " Error: Relay Module is Unable to find Security group \"$security_group\"\n";
                    }
                }
                else {
                    $members = $this->bot->db->select("SELECT nickname FROM #___users WHERE userLevel >= 1");
                    if (!empty($members)) {
                        foreach ($members as $member) {
                            if ($this->bot->core('prefs')
                                ->get($member[0], 'AutoInv', 'receive_auto_invite') == 'On'
                            ) {
                                $this->bot->core("chat")
                                    ->pgroup_invite($member[0]);
                                echo "Inviting " . $member[0] . " to the bot\n";
                            }
                        }
                    }
                }
                $old = time() - 300;
                $this->bot->db->query("DELETE FROM #___relay WHERE time < " . $old);
            }
        }
        elseif ($cron == 2) {
            if (strtoupper(
                $this->bot->core("settings")
                    ->get("Relay", "Type")
            ) == "DB"
            ) {
                $result = $this->bot->db->select("SELECT id, type, botname, msg FROM #___relay WHERE id > " . $this->lastsent . " ORDER BY id ASC");
                if (!empty($result)) {
                    foreach ($result as $res) {
                        if (strtolower($res[1]) == "gcrc" && $this->db_relay) {
                            $this->incomingCommand($name, $res[3], "db");
                        }
                        elseif (strtolower($res[1]) == "gcr" && $this->db_relay) {
                            if (ucfirst(strtolower($res[2])) != $this->bot->botname) // make sure we are not doing our own command
                            {
                                if ($this->bot->core("access_control")
                                    ->check_for_access($res[2], "gcr")
                                ) {
                                    $this->externalPrivateGroupMessage("", $res[2], "gcr " . $res[3], TRUE);
                                }
                            }
                        }
                        $this->lastsent = $res[0];
                    }
                }
                $this->db_relay = TRUE;
            }
            else {
                $this->db_relay = FALSE;
            }
        }
    }


    function connect()
    {
        if ($this->bot->core("settings")->get('Relay', 'Autoinvite')) {
            $security_group = $this->bot->core("settings")
                ->get('Relay', 'AutoinviteRelayGroup');
            if (!empty($security_group)) {
                $security_groups_gid = $this->bot->db->select("SELECT gid,name FROM #___security_groups WHERE name = '$security_group'");
                if ($security_groups_gid[0][0]) {
                    $relayedbots_gid = $security_groups_gid[0][0];
                    $thisbotname = $this->bot->botname;
                    $invitelist = $this->bot->db->select(
                        "SELECT ol.nickname,ol.status_pg,ol.botname,sm.gid,sm.name FROM online AS ol LEFT JOIN #___security_members AS sm ON ol.nickname = sm.name WHERE sm.gid = $relayedbots_gid AND ol.status_pg = 0 AND ol.botname = \"$thisbotname\""
                    );
                    if ($invitelist[0]) {
                        foreach ($invitelist as $inviteme) {
                            $this->bot->core("chat")
                                ->pgroup_invite($inviteme[0]);
                            echo " NOTICE: Inviting " . $inviteme[0] . " to the bot\n";
                        }
                    }
                }
            }
            else {
                $members = $this->bot->db->select("SELECT nickname FROM #___users WHERE userLevel > 0");
                if (!empty($members)) {
                    foreach ($members as $member) {
                        if ($this->bot->core('prefs')
                            ->get($member[0], 'AutoInv', 'receive_auto_invite') == 'On'
                        ) {
                            $this->bot->core("chat")->pgroup_invite($member[0]);
                            echo "Inviting " . $member[0] . " to the bot\n";
                        }
                    }
                }
            }
        }
    }


    function buddy($name, $msg)
    {
        if ($this->monbuds && ($msg == 1 || $msg == 0)) {
            if ($this->bot->core("notify")->check($name)) {
                if (!isset($this->bot->other_bots[$name])) {
                    $level = $this->bot->db->select("SELECT userLevel FROM #___users WHERE nickname = '$name'");
                    if (!empty($level)) {
                        $level = $level[0][0];
                    }
                    else {
                        $level = 0;
                    }
                    $msg = "buddy $msg $name sendToGuildChat $level";
                    $this->relayToBot($msg, FALSE, "gcrc");
                }
            }
        }
        if ($this->bot->core("settings")->get('Relay', 'Autoinvite')) {
            $security_group = $this->bot->core("settings")
                ->get('Relay', 'AutoinviteRelayGroup');
            if (!empty($security_group)) {
                $security_groups_gid = $this->bot->db->select("SELECT gid,name FROM #___security_groups WHERE name = '$security_group'");
                if (!empty($security_groups_gid)) {
                    if ($security_groups_gid[0][0]) {
                        $relayedbots_gid = $security_groups_gid[0][0];
                        $gids = $this->bot->core("security")
                            ->get_groups($name);
                        if ($gids && is_array($gids)) {
                            $gids = array_flip($gids);
                            if (isset($gids[$relayedbots_gid])) {
                                $this->bot->core("chat")->pgroup_invite($name);
                                echo " NOTICE: Inviting " . $name . " to the bot\n";
                            }
                        }
                    }
                }
                else {
                    echo " Error: Relay Module is Unable to find Security group \"$security_group\"\n";
                }
            }
            else {
                $members = $this->bot->db->select("SELECT nickname FROM #___users WHERE userLevel >= 1 AND nickname = '$name'");
                if (!empty($members)) {
                    if ($this->bot->core('prefs')
                        ->get($name, 'AutoInv', 'receive_auto_invite') == 'On'
                    ) {
                        $this->bot->core("chat")->pgroup_invite($name);
                        echo "Inviting " . $name . " to the bot\n";
                    }
                }
            }
        }
    }


    function externalPrivateGroupJoin($pgname, $name, $cron = FALSE)
    {
        $onmsg = "";
        if (!$cron && $name != $this->bot->botname) {
            Return;
        }
        if ($this->bot->core("settings")
            ->get('Relay', 'Status')
            && ($cron
                || strtolower(
                    $this->bot
                        ->core("settings")->get('Relay', 'Relay')
                ) == strtolower($pgname))
        ) {
            $online = $this->bot->db->select(
                "SELECT nickname, status_gc, status_pg, level FROM #___online WHERE (status_gc = 1 OR status_pg = 1) AND botname = '" . $this->bot->botname . "' ORDER BY nickname"
            );
            if (empty($online)) {
                $online = "";
            }
            else {
                foreach ($online as $on) {
                    $level = $on[3];
                    if ($on[1] == 1) {
                        $onmsg .= $on[0] . ",sendToGuildChat,$level;";
                    }
                    if ($on[2] == 1) {
                        $onmsg .= $on[0] . ",pg;";
                    }
                }
            }
            if (!empty($onmsg)) {
                $onmsg = substr($onmsg, 0, -1);
            }
            $msg = "online $onmsg";
            $this->relayToBot($msg, FALSE, "gcrc");
            if (!$cron) {
                $this->relayToBot("onlinereq", FALSE, "gcrc");
            }
        }
    }


    function privateGroupJoin($name)
    {
        if (!isset($this->bot->other_bots[$name])) {
            $msg = "buddy 1 $name pg";
            $this->relayToBot($msg, FALSE, "gcrc");
        }
    }


    function privateGroupLeave($name)
    {
        if (!isset($this->bot->other_bots[$name])) {
            $msg = "buddy 0 $name pg";
            $this->relayToBot($msg, FALSE, "gcrc");
        }
    }


    function incomingCommand($name, $msg, $source)
    {
        $msg = explode(" ", $msg);
        Switch ($msg[0]) {
        case 'online':
            $sql = "UPDATE #___online SET status_gc = '0', status_pg = '0' WHERE botname = '" . $name . "'";
            $this->bot->db->query($sql);
            if ($msg[1] == "" || !$msg[1]) {
                Return;
            }
            $online = explode(";", $msg[1]);
            if (!empty($online)) {
                foreach ($online as $on) {
                    $on = explode(",", $on);
                    $this->incomingCommand($name, "buddy 1 $on[0] $on[1] $on[2]", $source);
                }
            }
            Break;
        case 'onlinereq':
            $this->externalPrivateGroupJoin(FALSE, FALSE, TRUE);
            Break;
        case 'buddy':
            $name = ucfirst(strtolower($name));
            $nickname = ucfirst(strtolower($msg[2]));
            $where = strtolower($msg[3]);
            $newstatus = $msg[1];
            if ($where != "pg" && !is_numeric($msg[4])) {
                echo "Invalid Level ($msg[4]) for $msg[2]\n";
                $msg[4] = 0;
            }
            switch ($where) {
            case "sendToGuildChat":
                $column = "status_gc";
                $leveln = ", level";
                $level = ", " . $msg[4];
                $levele = ", level = " . $msg[4];
                break;
            case "pg":
                $column = "status_pg";
                $level = "";
                break;
            default:
                $column = FALSE;
                break;
            }
            if ($column) {
                $sql = "INSERT INTO #___online (nickname, botname, " . $column . ", " . $column . "_changetime" . $leveln . ") ";
                $sql .= "VALUES ('" . $nickname . "', '" . $name . "', '" . $newstatus . "', '" . time() . "'" . $level . ") ";
                $sql .= "ON DUPLICATE KEY UPDATE " . $column . " = '" . $newstatus . "', " . $column . "_changetime = '" . time() . "'" . $levele;
                $this->bot->db->query($sql);
            }
            Break;
        }
    }


    function encrypt($string)
    {
        if (!extension_loaded("mcrypt")) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $module = 'mcrypt.dll';
            }
            else {
                $module = 'mcrypt.so';
            }
            if (!dl($module)) {
                return ("Relay Encription Requires the mcrypt PHP extension.");
            }
        }
        /*
        Make sure the mhash module is loaded.
        */
        if (!extension_loaded("mhash")) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $module = 'mhash.dll';
            }
            else {
                $module = 'mhash.so';
            }
            if (!dl($module)) {
                return ("Relay Encription Requires the mhash PHP extension.");
            }
        }
        $key = $this->bot->core("settings")->get("Relay", "Key");
        $return = array(
            'hmac'      => NULL,
            'iv'        => NULL,
            'encrypted' => NULL
        );
        $decrypted_string = trim($string);
        // Encryption Algorithm
        $cipher_alg = MCRYPT_RIJNDAEL_128;
        // Encryption Key
        //$key = $this -> bot -> settings -> get("WebOnline", "Key");
        // Create the initialization vector for added security.
        $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher_alg, MCRYPT_MODE_ECB), MCRYPT_RAND);
        // Encrypt $string
        $encrypted_string = mcrypt_encrypt($cipher_alg, $key, $decrypted_string, MCRYPT_MODE_CBC, $iv);
        // Convert binary data to hex strings.
        $iv = bin2hex($iv);
        $encrypted_string = bin2hex($encrypted_string);
        // Generate HMAC to authenticate/validate data.
        $hmac = bin2hex(mhash(MHASH_MD5, $iv . $encrypted_string, $key));
        //$return['hmac'] = $hmac;
        //$return['iv'] = $iv;
        //$return['encrypted'] = $encrypted_string;
        //$return['decrypted'] = $decrypted_string;
        $return = "$encrypted_string $hmac $iv";
        return $return;
    }


    function decrypt($txt)
    {
        $txt = explode(" ", $txt);
        $key = $this->bot->core("settings")->get("Relay", "Key");
        $data = $this->verifyReceivedData($txt[0], $txt[1], $txt[2], $key);
        if ($data['error']) {
            return ($data['errormsg']);
        }
        else {
            return ($this->decryptData($data['iv'], $data['ciphrtxt'], $key));
        }
    }


    function decryptData($iv, $ciphrtxt, $key)
    {
        // There is no hex2bin, pack() does the job.
        $iv = pack("H*", $iv);
        //$encrypted_string = hex2bin($encrypted_string);
        $ciphrtxt = pack("H*", $ciphrtxt);
        // Encryption Algorithm
        $cipher_alg = MCRYPT_RIJNDAEL_128;
        $decrypted_string = mcrypt_decrypt($cipher_alg, $key, $ciphrtxt, MCRYPT_MODE_CBC, $iv);
        // For some reason there is always some junk on the string until it gets timmed.
        $decrypted_string = trim($decrypted_string);
        return $decrypted_string;
    }


    /*
    This function verifies the data's authenticity and integrity.
    */
    function verifyReceivedData($rec_string, $rec_hmac, $rec_iv, $key)
    {
        /*
        //DEBUG
        $output = "<br>Debug Output:<br>";
        $output .= "<br>Key: ".$key."<br>";
        $output .= "<br>IV: ".$rec_iv."<br>";
        $output .= "<br>HMAC: ".$rec_hmac."<br>";
        $output .= "<br>String: ".$rec_string."<br>";
        print_r($output);
        */
        // String has to be long enough to be valid.
        if (!preg_match("/^[0-9A-Fa-f].+$/", $rec_string)) {
            return ("Invalid String, Expected Hex Characters only.");
        }
        else {
            if (!preg_match("/^[0-9A-Fa-f].+$/", $rec_hmac)) {
                return ("Invalid HMAC, Expected Hex Characters only.");
            }
            else {
                if (!preg_match("/^[0-9A-Fa-f].+$/", $rec_iv)) {
                    return ("Invalid IV, Expected Hex Characters only.");
                }
                else {
                    if (strlen($rec_iv) != 32) {
                        return ("Invalid IV Length.");
                    }
                    else {
                        if (strlen($rec_string) < 32 && strlen($rec_string) <= 1024) {
                            return ("Invalid String Length.");
                        }
                        else {
                            if (strlen($rec_iv) <= 32 && strlen($rec_iv) >= 255) {
                                return ("Invalid HMAC Length.");
                            }
                        }
                    }
                }
            }
        }
        $chk_hmac = bin2hex(mhash(MHASH_MD5, $rec_iv . $rec_string, $key));
        $return = array();
        if ($rec_hmac !== $chk_hmac) {
            $return['error'] = TRUE;
            $return['errormsg'] = "Failed to Decript Message from Relay";
            //print_r("\nReciv: ".$rec_hmac."\n");
            //print_r("\nCheck: ".$chk_hmac."\n");
        }
        else {
            $return['error'] = FALSE;
            $return['errormsg'] = "";
            $return['iv'] = $rec_iv;
            $return['hmac'] = $rec_hmac;
            $return['ciphrtxt'] = $rec_string;
        }
        return $return;
    }


    /*
    Take a supplied name a build a namestring containing colors and additional information for it to be used in relay.
    */
    function getNameString($name)
    {
        $mainstr = "";
        if ($this->bot->core("settings")->get('Relay', 'ShowMain') != "") {
            $main = $this->bot->core("alts")->main($name);
            if ($main && (strcasecmp($main, $name) != 0)) {
                $truncatelen = ($this->bot->core("settings")
                    ->get('Relay', 'TruncateMain'));
                if ((strlen($main) > $truncatelen) && (strlen($main) > ($truncatelen + 1))) {
                    $main = substr($main, 0, $truncatelen);
                    $main = "$main~";
                }
                $mainstr = " ##relay_mainname##(" . $main . ")##end##";
            }
        }
        $namestr = "##relay_name##" . $name . $mainstr . ":##end## ";
        return $namestr;
    }
}

?>
