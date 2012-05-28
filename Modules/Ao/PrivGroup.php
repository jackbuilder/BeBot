<?php
/*
* PrivGroup.php - Basic featureset for private group handeling (join/kick etc)
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
$privgroup = new PrivGroup($bot);
/*
The Class itself...
*/
class PrivGroup extends BaseActiveModule
{

    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        // Create sane default levels for the commands
        $this->registerCommand("all", "join", "GUEST");
        $this->registerCommand("all", "invite", "LEADER", array("$" => "GUEST"));
        $this->registerCommand("all", "chat", "GUEST");
        $this->registerCommand("all", "leave", "ANONYMOUS");
        $this->registerCommand("all", "kick", "LEADER", array("$" => "ANONYMOUS"));
        $this->registerCommand("all", "kickall", "ADMIN");
        $this->registerAlias("invite", "inviteuser");
        $this->registerAlias("kick", "kickuser");
        $this->registerAlias("kickall", "kick all");
        $this->registerEvent("privateGroupJoin");
        $this->registerEvent("privateGroupLeave");
        if ($this->bot->guildbot) {
            $joindef = "sendToGuildChat";
            $leavedef = "sendToGuildChat";
        }
        else {
            $joindef = "sendToGroup";
            $leavedef = "none";
        }
        $longjoin = "Which chat channels should be notified about joins to the private chat channel?";
        $longleave = "Which chat channels should be notified about leave to the private chat channel?";
        $this->bot->core("settings")
            ->create("PrivGroup", "EchoJoin", $joindef, $longjoin, "none;sendToGroup;sendToGuildChat;both");
        $this->bot->core("settings")
            ->create("PrivGroup", "EchoLeave", $leavedef, $longleave, "none;sendToGroup;sendToGuildChat;both");
        $this->bot->core("settings")
            ->create(
            "PrivGroup", "JoinString", "#!NICKNAME!# (#!LEVEL!#/#!AT_ID!# ##~!FACTION!~##~!FACTION!~##end## #!PROFESSION!#, #!ORG!#) has joined #!BOTNAME!#",
            "Formating string for join notifications.", "", TRUE
        );
        $this->bot->core("settings")
            ->create("PrivGroup", "LeaveString", "#!NICKNAME!# has left #!BOTNAME!#", "Formating string for leave notifications.", "", TRUE);
        $this->bot->core("settings")
            ->create("PrivGroup", "Deactivated", FALSE, "Is joining the private chat deactived?");
        $this->bot->core("settings")
            ->create("PrivGroup", "DeactivatedText", "Private chatgroup is disabled!", "The text shown if the private chat cannot be joined.", "");
        unset($longjoin);
        unset($longleave);
        unset($joindef);
        unset($leavedef);
        $this->help['description'] = 'Module to handle the privat group.';
        $this->help['command']['join'] = "Join the private group of the bot.";
        $this->help['command']['chat'] = "Join the private group of the bot.";
        $this->help['command']['invite'] = "Join the private group of the bot.";
        $this->help['command']['invite <name>'] = "Invite <name> to join the private group of the bot.";
        $this->help['command']['leave'] = "Leave the private group of the bot.";
        $this->help['command']['kick'] = "Leave the private group of the bot.";
        $this->help['command']['kick <name>'] = "Kick <name> from the private group of the bot.";
        $this->help['command']['inviteuser <name>'] = "Invites <name> to the private group of the bot.";
        $this->help['command']['kickuser <name>'] = "Kicks <name> from the private group of the bot.";
        $this->help['command']['kickall'] = "Completely empties the private group of the bot.";
    }


    function commandHandler($name, $msg, $chan)
    {
        if (preg_match("/^join/i", $msg) || preg_match("/^invite$/i", $msg) || preg_match("/^chat/i", $msg)) {
            if ($this->bot->core("settings")->get("Privgroup", "Deactivated")) {
                $this->bot->send_output(
                    $name, $this->bot->core("settings")
                        ->get("Privgroup", "Deactivatedtext"), $chan
                );
            }
            else {
                $this->bot->core("chat")->pgroup_invite($name);
            }
        }
        else {
            if (preg_match("/^leave/i", $msg) || preg_match("/^kick$/i", $msg)) {
                $this->bot->core("chat")->pgroup_kick($name);
            }
            else {
                if (preg_match("/^invite (.+)$/i", $msg, $info)) {
                    if ($this->bot->core("settings")->get("Privgroup", "Deactivated")) {
                        $this->bot->send_output(
                            $name, $this->bot->core("settings")
                                ->get("Privgroup", "Deactivatedtext"), $chan
                        );
                    }
                    else {
                        $this->invite($name, $chan, $info[1]);
                    }
                }
                else {
                    if (preg_match("/^kick (.+)$/i", $msg, $info)) {
                        $this->kickUser($name, $chan, $info[1]);
                    }
                    else {
                        if (preg_match("/^kickall$/i", $msg, $info)) {
                            $this->kickAll($name, $chan);
                        }
                        else {
                            $this->bot->send_help($name);
                        }
                    }
                }
            }
        }
        return FALSE;
    }


    /*
    Invite a user to private group.
    */
    function invite($from, $type, $who)
    {
        $who = explode(" ", $who, 2);
        if (!empty($who[1])) {
            $pmsg = " (" . $who[1] . ")";
        }
        $who = ucfirst(strtolower($who[0]));
        $id = $this->bot->core('player')->id($who);
        if ($this->bot->botname == $who) {
            $msg = "You cannot invite the bot to its own chat group";
        }
        else {
            if ($this->bot->core("online")->in_chat($who)) {
                $msg = "##highlight##" . $who . "##end## is already in the bot!";
            }
            else {
                if (!($id instanceof BotError) && ($id > 1)) {
                    // We can simply invite, the access control has handled the rights
                    $this->bot->core("chat")->pgroup_invite($who);
                    $this->bot->send_tell($who, "You have been invited to the privategroup by " . $from . "." . $pmsg);
                    $msg = "##highlight##" . $who . "##end## has been invited.";
                }
                else {
                    $msg = "Player ##highlight##" . $who . "##end## does not exist.";
                }
            }
        }
        $this->bot->send_output($from, $msg, $type);
    }


    /*
    Allows a user to kick someone.
    */
    function kickUser($name, $type, $target = NULL)
    {
        $target = explode(" ", $target, 2);
        if (!empty($target[1])) {
            $pmsg = " (" . $target[1] . ")";
        }
        $target = ucfirst(strtolower($target[0]));
        if ($name == $target) {
            $this->bot->core("chat")->pgroup_kick($name);
        }
        elseif (!is_null($target)) {
            $name_access = $this->bot->core("security")
                ->get_access_level($name);
            $target_access = $this->bot->core("security")
                ->get_access_level($target);
            if ($name_access >= $target_access) {
                $this->bot->core("chat")->pgroup_kick($target);
                $this->bot->send_output($name, $name . " kicked $target from privategroup.", "sendToGroup");
                $this->bot->send_tell($target, $name . " has Kicked you from privategroup." . $pmsg);
            }
            else {
                $this->bot->send_output($name, "You cannot kick someone with a higher access level than yourself.", "sendTell");
            }
        }
        else {
            $this->bot->core("chat")->pgroup_kick($name);
        }
    }


    /*
    Kicks everyone from pgroup.
    */
    function kickAll($name, $chan = "sendToGroup")
    {
        $text = "##highlight##" . $name . " ##end##kicked all users from the private group!";
        if ($chan == "sendToGuildChat") {
            $this->bot->send_gc($text);
        }
        $this->bot->send_pgroup($text);
        $this->bot->core("chat")->pgroup_kick_all();
    }


    function privateGroupJoin($name)
    {
        if ($this->bot->core("settings")
            ->get("Privgroup", "Echojoin") == "none"
        ) {
            return;
        }
        // Parse the string first:
        $text = $this->parseText(
            $name, $this->bot->core("settings")
                ->get("Privgroup", "Joinstring"), "has joined"
        );
        switch (strtolower(
            $this->bot->core("settings")
                ->get("Privgroup", "Echojoin")
        )) {
        case "sendToGuildChat":
            $this->bot->send_gc($text);
            break;
        case "sendToGroup":
            $this->bot->send_pgroup($text);
            break;
        case "both":
            $this->bot->send_pgroup($text);
            $this->bot->send_gc($text);
            break;
        default:
            break;
        }
    }


    function privateGroupLeave($name)
    {
        if ($this->bot->core("settings")
            ->get("Privgroup", "Echoleave") == "none"
        ) {
            return;
        }
        // Parse the string first:
        $text = $this->parseText(
            $name, $this->bot->core("settings")
                ->get("Privgroup", "Leavestring"), "has left"
        );
        switch (strtolower(
            $this->bot->core("settings")
                ->get("Privgroup", "Echoleave")
        )) {
        case "sendToGuildChat":
            $this->bot->send_gc($text);
            break;
        case "sendToGroup":
            $this->bot->send_pgroup($text);
            break;
        case "both":
            $this->bot->send_pgroup($text);
            $this->bot->send_gc($text);
            break;
        default:
            break;
        }
    }


    function parseText($name, $string, $actionstring)
    {
        $info = $this->bot->core("whoIs")->lookup($name);
        // Only "Name joined bot" if no whoIs entry was found
        if ($info instanceof BotError) {
            return "##highlight##" . $name . " ##end##" . $actionstring . " " . $this->bot->botname;
        }
        foreach ($info as $key => $value) {
            $string = preg_replace("/#!$key!#/i", "##highlight##$value##end##", $string);
            $string = preg_replace("/~!$key!~/i", $value, $string);
        }
        $string = preg_replace("/#!BOTNAME!#/i", $this->bot->botname, $string);
        return $string;
    }
}

?>
