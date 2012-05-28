<?php
/*
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
class BasePassiveModule
{
    protected $bot; // A reference to the bot
    public $moduleName; //Name of the module extending this class.
    protected $error; //This holds an error class.
    protected $linkName;


    function __construct(&$bot, $moduleName)
    {
        //Save reference to bot
        $this->bot = &$bot;
        $this->moduleName = $moduleName;
        $this->linkName = NULL;
        $this->error = new BotError($bot, $moduleName);
    }


    protected function registerEvent($event, $target = FALSE)
    {
        $ret = $this->bot->register_event($event, $target, $this);
        if ($ret) {
            $this->error->set($ret);
        }
    }


    protected function unregisterEvent($event, $target = FALSE)
    {
        $ret = $this->bot->unregister_event($event, $target, $this);
        if ($ret) {
            $this->error->set($ret);
        }
    }


    protected function registerModule($name)
    {
        if ($this->linkName == NULL) {
            $this->linkName = strtolower($name);
            $this->bot->register_module($this, strtolower($name));
        }
    }


    protected function unregisterModule()
    {
        if ($this->linkName != NULL) {
            $this->bot->unregister_module($this->linkName);
        }
    }


    protected function outputDestination($name, $msg, $channel = FALSE)
    {
        if ($channel !== FALSE) {
            if ($channel & SAME) {
                if ($channel & $this->source) {
                    $channel -= SAME;
                }
                else {
                    $channel += $this->source;
                }
            }
        }
        else {
            $channel += $this->source;
        }
        if ($channel & TELL) {
            $this->bot->send_tell($name, $msg);
        }
        if ($channel & GC) {
            $this->bot->send_gc($msg);
        }
        if ($channel & PG) {
            $this->bot->send_pgroup($msg);
        }
        if ($channel & RELAY) {
            $this->bot->core("relay")->relay_to_pgroup($name, $msg);
        }
        if ($channel & IRC) {
            $this->bot->send_irc($this->moduleName, $name, $msg);
        }
    }


    public function __call($name, $args)
    {
        foreach ($args as $i => $arg) {
            if (is_object($arg)) {
                $args[$i] = "::object::";
            }
        }
        $args = implode(', ', $args);
        $msg = "Undefined function $name($args)!";
        $this->error->set($msg);
        return $this->error->message();
    }


    public function debugOutput($title)
    {
        if ($this->bot->debug) {
            if ($title != "") {
                echo $title . "\n";
            }
            $this->bot->log("DEBUG", "BasePassive", $this->bot->debug_bt());
        }
    }
}

?>
