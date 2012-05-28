<?php
/*
* AOChatWrapper.php - A class of wrappers around AOChat functions.
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
$aochat_wrapper_core = new AOChatWrapper_Core($bot);
/*
The Class itself...
*/
class AOChatWrapper_Core extends BasePassiveModule
{

    /*
    Constructor:
    Hands over a reference to the "Bot" class.
    */
    function __construct(&$bot)
    {
        parent::__construct($bot, get_class($this));
        $this->registerModule("chat");
    }


    /*
    This is a wrapper function for aoc->getUserId() that checks the whoIs cache if aoc->getUserId() fails
    */
    function getUserId($user)
    {
        $user = ucfirst(strtolower($user));
        echo "Depreciated AOChatWrapper::getUserId() called for $user. Backtrace:\n";
        $this->bot->log("DEBUG", "BACKTRACE", $this->bot->debug_bt());
        $this->debugOutput("Deprecated call to core('chat')->getUserId(). Use bot->core('player')->id($user)\n");
        return $this->bot->core('player')->id($user);
    }


    /*
    This is a wrapper function for aoc->getUserName() that checks the whoIs cache if aoc->getUserName() failes
    */
    function getUserName($user)
    {
        $user = ucfirst(strtolower($user));
        $this->debugOutput("Deprecated call to core('chat')->getUserName(). Use bot->core('player')->name($user)\n");
        echo "Depreciated AOChatWarapper::getUserName() called for $user. Backtrace:\n";
        $this->bot->log("DEBUG", "BACKTRACE", $this->bot->debug_bt());
        return $this->bot->core('player')->name($user);
    }


    /* Buddies */
    function buddyAdd($user, $que = TRUE)
    {
        $add = TRUE;
        if (is_numeric($user)) {
            $uid = $user;
        }
        else {
            $uid = $this->bot->core('player')->id($user);
        }

        if ($uid instanceof BotError) {
            return $uid;
        }
        else {
            // FIXME
            // Currently checking specifically for 4294967295 as userid to ensure we never ever send an add buddy
            // packet to AoC as it will disconnect the player.
            if ($uid > 4294967294 && $uid < 4294967296) {
                $this->bot->log(
                    "BUDDY", "BUDDY-ADD",
                    "Received add request for " . $user . "(" . $uid . ") This user is likely in the userlist and might need to be manually removed if this error persists."
                );
                return FALSE;
            }

            if ($uid < 1) {
                $this->bot->log("BUDDY", "BUDDY-ADD", "Received add request for " . $user . " but user appears to not exist!!");
                return FALSE;
            }


            if (!($this->bot->aoc->buddy_exists($uid))
                && $uid != $this->bot
                    ->core('player')->id($this->bot->botname)
                && !($this->bot
                    ->core('player')
                    ->name($uid) instanceof BotError)
            ) {
                if (!$que || $this->bot->core("buddy_queue")->check_queue()) {
                    $this->bot->aoc->buddy_add($uid);
                    $this->bot->log(
                        "BUDDY", "BUDDY-ADD", $this->bot
                            ->core('player')->name($uid)
                    );
                    return TRUE;
                }
                else {
                    $return = $this->bot->core("buddy_queue")
                        ->into_queue($uid, $add);
                    return $return;
                }
            }
            else {
                return FALSE;
            }
        }
    }


    function buddyRemove($user)
    {
        $add = FALSE;
        if (empty($user)
            || ($uid = $this->bot->core('player')
                ->id($user)) === FALSE
        ) {
            return FALSE;
        }
        else {
            if (($this->bot->aoc->buddy_exists($uid))) {
                if ($this->bot->core("buddy_queue")->check_queue()) {
                    $this->bot->aoc->buddy_remove($uid);
                    $this->bot->log("BUDDY", "BUDDY-DEL", $this->getUserName($uid));
                    return TRUE;
                }
                else {
                    $return = $this->bot->core("buddy_queue")
                        ->into_queue($uid, $add);
                    return $return;
                }
            }
            else {
                return FALSE;
            }
        }
    }


    function buddyExists($who)
    {
        return $this->bot->aoc->buddy_exists($who);
    }


    function buddyOnline($who)
    {
        return $this->bot->aoc->buddy_online($who);
    }


    /*
    accept invite to private group
    */
    function privateGroupJoin($group)
    {
        if ($group == NULL) {
            return FALSE;
        }
        $this->bot->log("PGRP", "ACCEPT", "Accepting Invite for Private Group [" . $group . "]");
        return $this->bot->aoc->privategroup_join($group);
    }


    /*
    leave private group
    */
    function privateGroupLeave($group)
    {
        if ($group == NULL) {
            return FALSE;
        }
        $this->bot->log("PGRP", "LEAVE", "Leaving Private Group [" . $group . "]");
        return $this->bot->aoc->privategroup_leave($group);
    }


    /*
    decline private group
    */
    function privateGroupDecline($group)
    {
        return $this->send_pgroup_leave($group);
    }


    /*
    private group status
    added - 2007/Sep/1 - anarchyonline@mafoo.org
    */
    function privateGroupStatus($group)
    {
        if ($group == NULL) {
            $group = $this->bot->botname;
        }
        return $this->bot->aoc->group_status($group);
    }


    function privateGroupInvite($user)
    {
        $this->bot->log("PGRP", "INVITE", "Invited " . $user . " to private group");
        return $this->bot->aoc->privategroup_invite($user);
    }


    function privateGroupKick($user)
    {
        $this->bot->log("PGRP", "KICK", "Kicking " . $user . " from private group");
        return $this->bot->aoc->privategroup_kick($user);
    }


    function privateGroupKickAll()
    {
        $this->bot->log("PGRP", "KICKALL", "Kicking all user from private group");
        return $this->bot->aoc->privategroup_kick_all();
    }


    function lookupGroup($arg, $type = 0)
    {
        return $this->bot->aoc->lookup_group($arg, $type);
    }


    function getGroupName($g)
    {
        return $this->bot->aoc->get_gname($g);
    }
}

?>
