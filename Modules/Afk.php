<?php
/*
* AFK Module for BE Bot <http://bebot.fieses.net>
* Module coded by Craized <http://www.craized.net>
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
/*
Add a "_" at the beginning of the file (_AFK.php) if you do not want it to be loaded.
*/
$afk = new AFK($bot);
class AFK extends BaseActiveModule
{
    var $afk;


    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        $this->afk = array();
        $this->afkalias = array();
        $this->registerCommand("all", "afk", 'MEMBER');
        $this->registerEvent("privateGroup");
        $this->registerEvent("groupMessage", "org");
        $this->registerEvent("buddy");
        //Create default_level access levels
        $this->help['description'] = "Shows other players that you are afk.";
        $this->help['command']['afk <message>'] = "Sets you afk with <message>";
        $this->help['notes'] = "This command does not affect nor is it affected by the in-game command /afk.";
        $this->bot->core("settings")
            ->create("Afk", "Alias", TRUE, "Should Alias's be used with AFK?");
        $this->bot->core("settings")
            ->create("Afk", "noprefix", FALSE, "Can no prefix with AFK be used to go AFK?");
        $this->bot->core("settings")
            ->create("Afk", "brb_noprefix", FALSE, "Can no prefix with BRB be used to go AFK?");
        $this->bot->core("command_alias")->register("afk", "brb");
    }


    function commandHandler($name, $msg, $origin)
    {
        $this->error->reset();
        $com = $this->parseCommand(
            $msg, array(
                'com',
                'args'
            )
        );
        $this->gone($name, $com['args']);
        return ("##highlight##$name##end## is now AFK.");
    }


    /*
    Check if the line in privateGroup was meant for someone afk or if someone afk is back
    */
    function privateGroup($name, $msg)
    {
        if ($this->awayCheck($name)) {
            $timegone = $this->afkTime($name);
            $this->back($name);
            $msgs = $this->messages($name);
            $this->bot->send_output($name, $name . " is back. AFK for (" . $timegone . ")  " . $msgs, "both");
        }
        if ($this->bot->core("settings")->get("Afk", "noprefix")) {
            if (preg_match("/^afk (.*)/i", $msg, $afkmsg)) {
                $this->gone($name, $afkmsg[1]);
                $this->bot->send_output($name, $name . " is now AFK.", "both");
            }
            elseif (preg_match("/^afk/i", $msg)) {
                $this->gone($name);
                $this->bot->send_output($name, $name . " is now AFK.", "both");
            }
        }
        elseif ($this->bot->core("settings")->get("Afk", "brb_noprefix")) {
            if (preg_match("/^brb (.*)/i", $msg, $afkmsg)) {
                $this->gone($name, $afkmsg[1]);
                $this->bot->send_output($name, $name . " is now AFK.", "both");
            }
            elseif (preg_match("/^brb/i", $msg)) {
                $this->gone($name, "");
                $this->bot->send_output($name, $name . " is now AFK.", "both");
            }
        }
        if (!empty($this->afk)) {
            $msgcheck = $this->checkMessage($name, "", $msg);
        }
        if (!empty($msgcheck)) {
            $this->bot->send_pgroup($msgcheck);
        }
    }


    function groupMessage($name, $group, $msg)
    {
        if ($this->awayCheck($name)) {
            $timegone = $this->afkTime($name);
            $this->back($name);
            $msgs = $this->messages($name);
            $this->bot->send_output($name, $name . " is back. AFK for (" . $timegone . ") " . $msgs, "both");
        }
        if ($this->bot->core("settings")->get("Afk", "noprefix")) {
            if (preg_match("/^afk (.*)/i", $msg, $afkmsg)) {
                $this->gone($name, $afkmsg[1]);
                $this->bot->send_output($name, "##highlight##$name##end## is now AFK.", "both");
                Return;
            }
            elseif (preg_match("/^afk/i", $msg)) {
                $this->gone($name);
                $this->bot->send_output($name, "##highlight##$name##end## is now AFK.", "both");
                Return;
            }
        }
        elseif ($this->bot->core("settings")->get("Afk", "brb_noprefix")) {
            if (preg_match("/^brb (.*)/i", $msg, $afkmsg)) {
                $this->gone($name, $afkmsg[1]);
                $this->bot->send_output($name, "##highlight##$name##end## is now AFK.", "both");
            }
            elseif (preg_match("/^brb/i", $msg)) {
                $this->gone($name, "");
                $this->bot->send_output($name, "##highlight##$name##end## is now AFK.", "both");
            }
        }
        if (!empty($this->afk)) {
            $msgcheck = $this->checkMessage($name, $group, $msg);
        }
        if (!empty($msgcheck)) {
            $this->bot->send_gc($msgcheck);
        }
    }


    function checkMessage($name, $group, $msg)
    {
        $found = FALSE;
        foreach ($this->afk as $key => $value) {
            if (preg_match("/$key\b/i", $msg)) {
                $this->afkmsgs[$key][] = array(
                    time(),
                    $name,
                    $msg
                );
                return ($key . " has been AFK for " . $this->afkTime($key) . " (" . $value[$msg] . ").");
            }
        }
        if ($this->bot->core("settings")->get("Afk", "Alias")) {
            if (!$found) {
                if (!empty($this->afkalias)) {
                    foreach ($this->afkalias as $key2 => $value) {
                        if (preg_match("/$key2\b/i", $msg)) {
                            $this->afkmsgs[$value][$this->afkmsgid] = array(
                                time(),
                                $name,
                                $msg
                            );
                            $this->afkmsgid++;
                            return ($value . " has been AFK for " . $this->afkTime($value) . " (" . $this->afk[$value][msg] . ").");
                        }
                    }
                }
            }
        }
        Return FALSE;
    }


    function afkTime($name)
    {
        $timenow = "" . time() . "";
        $timeafk = $this->afk[$name]['time'];
        $dif = $timenow - $timeafk;
        if ($dif < 60) {
            Return $dif . " Seconds";
        }
        elseif ($dif < 3600) {
            $mins = floor($dif / 60);
            Return $mins . " Minutes";
        }
        else {
            $mins = floor($dif / 60);
            $hours = floor($mins / 60);
            $minstorem = $hours * 60;
            $minsrem = $mins - $minstorem;
            Return $hours . " Hours and " . $minsrem . " Minutes";
        }
    }


    function gone($name, $msg = FALSE)
    {
        if (empty($msg)) {
            $msg = "Away from keyboard";
        }
        $this->afk[$name] = array(
            'time' => time(),
            'msg'  => $msg
        );
        // Add Aliases to AFK list
        $main = $this->bot->core("alts")->main($name);
        $alts = $this->bot->core("alts")->get_alts($main);
        if (!empty($alts)) {
            foreach ($alts as $alt) {
                $this->afkalias[$alt] = $name;
            }
        }
        if ($this->bot->core("settings")->get("Afk", "Alias")) {
            $aliases = $this->bot->core("alias")->alias;
            if (!empty($aliases)) {
                foreach ($aliases as $alias => $nickname) {
                    if ($main == $nickname) {
                        $this->afkalias[$alias] = $name;
                    }
                }
            }
        }
    }


    function back($name)
    {
        if ($this->afk[$name]) {
            unset($this->afk[$name]);
            foreach ($this->afkalias as $key => $value) {
                if ($name == $value) {
                    unset($this->afkalias[$key]);
                }
            }
        }
    }


    function awayCheck($name)
    {
        if (isset($this->afk[$name])) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }


    function buddy($name, $msg)
    {
        $access = $this->bot->core("security")->get_access_level($name);
        if (($msg == 5) && ($access > 1)) {
            if ($this->awayCheck($name)) {
                $this->back($name);
                $msgs = $this->messages($name);
                if ($this->bot->game == "ao") {
                    $this->bot->send_tell($name, "you have been set as back. " . $msgs . "");
                }
            }
        }
        else {
            if (($msg == 3) && ($access > 1)) {
                if (!$this->awayCheck($name)) {
                    $this->gone($name);
                    $msgs = $this->messages($name);
                    if ($this->bot->game == "ao") {
                        $this->bot->send_tell($name, "you have been set as AFK. " . $msgs . "");
                    }
                }
            }
            elseif ($msg == 0) {
                if ($this->awayCheck($name)) {
                    $this->back($name);
                    $msgs = $this->messages($name);
                    $this->bot->send_tell($name, "you have been set as back. (Logoff) " . $msgs . "");
                }
            }
        }
    }


    function messages($name)
    {
        if (!empty($this->afkmsgs[$name])) {
            $inside = "##blob_title##..:: AFK Messages ::..##end##\n\n";
            foreach ($this->afkmsgs[$name] as $key => $value) {
                $inside .= "##green##" . gmdate(
                    $this->bot->core("settings")
                        ->get("Time", "FormatString"), $value[0]
                ) . "##end##  ##orange##" . $value[1] . "##end##\n        ##blob_text##" . $value[2] . "##end##\n\n";
                $count++;
            }
            $msgs = "##highlight##" . $count . "##end## Messages :: " . $this->bot
                ->core("tools")->make_blob("click to view", $inside);
            unset($this->afkmsgs[$name]);
            Return ($msgs);
        }
        unset($this->afkmsgs[$name]);
        Return FALSE;
    }
}

?>
