<?php
/*
* BotHelp.php - Bot Help Systems
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

$bothelp_core = new BotHelp_Core($bot);

/*
The Class itself...
*/
class BotHelp_Core extends BaseActiveModule
{
    private $help_cache;


    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));

        $this->registerModule("help");
        $this->registerCommand("all", "help", "GUEST");

        $this->help['description'] = "The bot help system.";
        $this->help['command']['help [command]'] = "Shows help on [command]. If no argument is given shows the help menu";
        $this->help['notes'] = "No notes";
    }


    function commandHandler($name, $msg, $origin)
    {
        $vars = explode(' ', $msg);
        unset($vars[0]);

        if (empty($this->help_cache)) {
            $this->updateCache();
        }

        if (!isset($vars[1])) {
            return ($this->showHelpMenu($name, 'source', $origin));
        }
        else {
            switch ($vars[1]) {
            case 'sendTell':
            case 'sendToGuildChat':
            case 'sendToGroup':
                return ($this->showHelpMenu($name, $vars[1]));
                break;
            default:
                return ($this->showHelp($name, $vars[1]));
                break;
            }
        }
    }


    function showHelpMenu($name, $section = 'source', $origin = FALSE)
    {
        switch ($section) {
        case 'source':
            switch ($origin) {
            case 'sendTell':
                $window = $this->getCommands($name, 'sendTell');
                break;
            case 'sendToGuildChat':
                $window = $this->getCommands($name, 'sendToGuildChat');
                break;
            case 'sendToGroup':
                $window = $this->getCommands($name, 'sendToGroup');
                break;
            }
            return ($this->bot->core("tools")->make_blob('Help', $window));
            break;
        default:
            $window = $this->getCommands($name, $section);
            return ($this->bot->core("tools")->make_blob('Help', $window));
            break;
        }
    }


    /*
    Gets commands for a given channel
    */
    function getCommands($name, $channel)
    {
        $channel = strtolower($channel);
        $lvl = $this->bot->core("security")->get_access_name(
            $this->bot
                ->core("security")->get_access_level($name)
        );
        $window = ":: BeBot Help ::\n\n" . $this->help_cache[$channel][$lvl];
        return $window;
    }


    function updateCache()
    {
        $this->makeHelpBlobs("sendTell");
        $this->makeHelpBlobs("sendToGroup");
        $this->makeHelpBlobs("sendToGuildChat");
    }


    function makeHelpBlobs($channel)
    {
        $channel = strtolower($channel);
        $this->help_cache[$channel] = array();
        foreach (
            $this->bot->core("access_control")->getAccessLevels() as
            $lvl
        ) {
            $this->help_cache[$channel][$lvl] = "";
        }
        unset($this->help_cache[$channel]["DISABLED"]);
        unset($this->help_cache[$channel]["DELETED"]);

        ksort($this->bot->commands[$channel]);
        foreach ($this->bot->commands[$channel] as $command => $module) {
            if (is_array($module->help)) {
                $cmdstr = $this->bot->core("tools")
                    ->chatcmd("help " . $command, $command) . " ";
            }
            else {
                $cmdstr = $command . " ";
            }
            switch ($this->bot->core("access_control")
                ->getMinAccessLevel($command, $channel)) {
            case ANONYMOUS:
                $this->help_cache[$channel]['ANONYMOUS'] .= $cmdstr;
            case GUEST:
                $this->help_cache[$channel]['GUEST'] .= $cmdstr;
            case MEMBER:
                $this->help_cache[$channel]['MEMBER'] .= $cmdstr;
            case LEADER:
                $this->help_cache[$channel]['LEADER'] .= $cmdstr;
            case ADMIN:
                $this->help_cache[$channel]['ADMIN'] .= $cmdstr;
            case SUPERADMIN:
                $this->help_cache[$channel]['SUPERADMIN'] .= $cmdstr;
            case OWNER:
                $this->help_cache[$channel]['OWNER'] .= $cmdstr;
                break;
            default:
                break;
            }
            unset($cmdstr);
        }
    }


    function showHelp($name, $command)
    {
        if (!$this->bot->core("access_control")
            ->check_for_access($name, $command)
        ) {
            return ("##highlight##$command##end## does not exist or you do not have access to it.");
        }
        elseif (!empty($this->bot->commands['sendTell'][$command])) {
            $com = $this->bot->commands['sendTell'][$command];
        }
        elseif (!empty($this->bot->commands['sendToGuildChat'][$command])) {
            $com = $this->bot->commands['sendToGuildChat'][$command];
        }
        elseif (!empty($this->bot->commands['sendToGroup'][$command])) {
            $com = $this->bot->commands['sendToGroup'][$command];
        }
        else {
            return ("##highlight##$command##end## does not exist or you do not have access to it.");
        }
        $window = "##blob_title## ::::: HELP ON " . strtoupper($command) . " :::::##end##<br><br>";
        if (isset($com->help)) {
            $help = $com->help;
            $window .= '##highlight##' . $help['description'] . '##end##<br><br>';
            $module_commands = array();
            foreach ($help['command'] as $key => $value) {
                // Only show help for the specific command, not all help for module!
                $parts = explode(' ', $key, 2);
                if (strcasecmp($command, $parts[0]) == 0) {
                    $key = str_replace('<', '&lt;', $key);
                    $value = str_replace('<', '&lt;', $value);
                    $window .= " ##highlight##<pre>$key##end## - ##blob_text##$value##end##<br>";
                }
                else {
                    if ($this->bot->core("access_control")
                        ->check_for_access($name, $parts[0])
                    ) {
                        $module_commands[$parts[0]] = $this->bot->core("tools")
                            ->chatcmd("help " . $parts[0], $parts[0]);
                    }
                }
            }
            $window .= '<br>##blob_title##NOTES:##end##<br>##blob_text##' . $help['notes'] . '##end##';
            if (!empty($module_commands)) {
                ksort($module_commands);
                $window .= "<br><br>##blob_title##OTHER COMMANDS OF THIS MODULE:##end##<br>";
                $window .= implode(" ", $module_commands);
            }
        }
        else {
            $window .= '##error##No Help Found##end##';
        }
        return ('help on ' . $this->bot->core("tools")
            ->make_blob($command, $window));
    }
}

?>
