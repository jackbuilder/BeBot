<?php
namespace Core;
/*
* ModuleCatcher.php - Collects information about modules available to the bot.
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
The Class itself...
*/
class ModuleCatcher extends \Commodities\BasePassiveModule
{
    public $core_directories;
    public $module_directories;

    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    public function __construct(&$bot, $core, $mod)
    {
        parent::__construct($bot, get_class($this));
        $this->register_module("module_core");
        $this->register_event("connect");
        $this->core_directories = $core;
        $this->module_directories = $mod;
    }

    /*
    This gets called when bot connects
    */
    public function connect()
    { // Start function connect()
        // Load up core-modules
        $folder = dir("./Core/");
        while ($mod = $folder->read()) {
            if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                $value = $this->bot->core("ini")->get($mod, "Core");
                if (empty($value)) {
                    $this->bot->core("ini")->set($mod, "TRUE", "Core");
                }
            }
        }
        // Load up game core-modules
        if (is_dir("./Core/" . $this->bot->game . "/")) {
            $folder = dir("./Core/" . $this->bot->game . "/");
            while ($mod = $folder->read()) {
                if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                    $value = $this->bot->core("ini")->get($mod, "Core");
                    if (empty($value)) {
                        $this->bot->core("ini")->set($mod, "TRUE", "Core");
                    }
                }
            }
        }
        // Load up all custom core-modules if the directory exists
        if (is_dir("./Custom/Core")) {
            $folder = dir("./Custom/Core/");
            while ($mod = $folder->read()) {
                if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                    $value = $this->bot->core("ini")->get($mod, "Custom_Core");
                    if (empty($value)) {
                        $this->bot->core("ini")
                            ->set($mod, "TRUE", "Custom_Core");
                    }
                }
            }
        }
        // Load up the core modules in the $core_directories config entry
        $core_dirs = explode(",", $this->core_directories);
        foreach ($core_dirs as $core_dir) {
            $core_dir = trim($core_dir);
            $sec_name = str_replace("/", "_", $core_dir);
            // Only load anything if it really is a directory
            if (is_dir($core_dir)) {
                $folder = dir($core_dir . "/");
                while ($mod = $folder->read()) {
                    if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                        $value = $this->bot->core("ini")->get($mod, $sec_name);
                        if (empty($value)) {
                            $this->bot->core("ini")
                                ->set($mod, "TRUE", $sec_name);
                        }
                    }
                }
            }
        }
        // Load up all modules
        $folder = dir("./Modules/");
        while ($mod = $folder->read()) {
            if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                $value = $this->bot->core("ini")->get($mod, "Modules");
                if (empty($value)) {
                    $this->bot->core("ini")->set($mod, "TRUE", "Modules");
                }
            }
        }
        // Load up all game modules
        if (is_dir("./Modules/" . $this->bot->game . "/")) {
            $folder = dir("./Modules/" . $this->bot->game . "/");
            while ($mod = $folder->read()) {
                if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                    $value = $this->bot->core("ini")->get($mod, "Modules");
                    if (empty($value)) {
                        $this->bot->core("ini")->set($mod, "TRUE", "Modules");
                    }
                }
            }
        }
        // Load up all custom modules if the directoy exists
        if (is_dir("./Custom/Modules")) {
            $folder = dir("./Custom/Modules/");
            while ($mod = $folder->read()) {
                if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                    $value = $this->bot->core("ini")
                        ->get($mod, "Custom_Modules");
                    if (empty($value)) {
                        $this->bot->core("ini")
                            ->set($mod, "TRUE", "Custom_Modules");
                    }
                }
            }
        }
        // Load up the modules in the $module_directories config entry
        $mod_dirs = explode(",", $this->module_directories);
        foreach ($mod_dirs as $mod_dir) {
            $mod_dir = trim($mod_dir);
            $sec_name = str_replace("/", "_", $mod_dir);
            // Only load anything if it really is a directory
            if (is_dir($mod_dir)) {
                $folder = dir($mod_dir . "/");
                while ($mod = $folder->read()) {
                    if (!is_dir($mod) && !preg_match("/^_/", $mod) && preg_match("/\.php$/i", $mod)) {
                        $value = $this->bot->core("ini")->get($mod, $sec_name);
                        if (empty($value)) {
                            $this->bot->core("ini")
                                ->set($mod, "TRUE", $sec_name);
                        }
                    }
                }
            }
        }
    } // End function connect()
}
$modulecatcher_core = new ModuleCatcher($bot, $bot->core_directories, $bot->module_directories);