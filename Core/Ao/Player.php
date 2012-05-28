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
class Player
{
    //Game spesific variables
    private $userId = FALSE;
    private $userName = FALSE; //aka nickname
    private $preferences = array();


    //When constructing a new player we need to have the bot handle so that the
    //class can look up certain variables automagically.
    public function __construct(&$botHandle, $data)
    {
        $this->bot = $botHandle;
        $this->error = new BotError($this->bot, get_class($this));
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }


    /*
        This function allows coders to use $player->userId instead of player->getUserId() when wanting to
        access a variable while still allowing the class to look up any values it has not already cached.
    */
    public function _get($variable)
    {
        switch ($variable) {
        case 'userId':
        case 'id':
            return ($this->getUserId());
            break;
        case 'userName':
        case 'nick':
        case 'nickname':
            return ($this->getUserName());
            break;
        case 'firstname':
        case 'lastname':
        case 'breed':
        case 'gender':
        case 'level':
        case 'profession':
        case 'aiLevel':
        case 'ai_rank':
        case 'organization':
        case 'orgRank':
            return ($this->getWhois($variable));
            break;
        case 'pref':
        case 'preferences':
            return ($this->getPreferences($variable));
            break;
        default:
            $this->error->set("Unknown attribute '$variable'.");
            return $this->error;
            break;
        }
    }


    public function getUserId($uname)
    {
        //Make sure we have the userId at hand.
        if (!$this->userId) {
            $this->userId = $this->bot->core('player')->get_uid($uname);
            if ($this->userId instanceof BotError) {
                //The userId could not be resolved.
                $this->error = $this->userId;
                $this->userId = FALSE;
                return $this->error;
            }
        }
        return $this->userId;
    }


    public function getUserName($uid)
    {
        //Make sure we have the userName at hand.
        if (!$this->userName) {
            $this->userName = $this->bot->core('player')->get_uname($uid);
            if ($this->userName instanceof BotError) {
                //The userId could not be resolved.
                $this->error = $this->userName;
                $this->userName = 'Unknown';
                return $this->error;
            }
        }
        return $this->userId;
    }


    public function getWhois($attribute)
    {
        //Make sure we have the attribute at hand.
        if (!$this->$attribute) {
            //Make sure we have a userName
            if (!$this->userName) {
                //If we don't have a userName already we should have an userId.
                $this->getUserName($this->userId);
            }
            $data = $this->bot->core('whoIs')->lookup($this->userName);
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
        return ($this->$attribute);
    }


    //Lookup the preferences in the table if we haven't already done that.
    public function getPreferences($variable)
    {
    }
}

?>
