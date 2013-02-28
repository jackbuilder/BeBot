<?php
/*
* BotError.php - Error handling class
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
class BotError
{
    private $bot;
    protected $status;
    private $is_fatal;
    private $description;
    private $source;

    public function __construct(&$bot, $module)
    {
        $this->status = FALSE;
        $this->is_fatal = FALSE;
        $this->description = '';
        $this->source = $module;
        $this->bot = $bot;
    }

    public function status()
    {
        return $this->status;
    }

    public function reset()
    {
        $this->status = FALSE;
        $this->is_fatal = FALSE;
        $this->description = '';
    }

    public function set($description, $log = TRUE, $fatal = FALSE)
    {
        $this->description = $description;
        $this->is_error = TRUE;
        $this->is_fatal = $fatal;
        if ($log) {
            $this->bot->log('ERROR', $this->source, $description);
        }
        if ($fatal) {
            $this->bot->log('FATAL', $this->source, $description);
            exit(1);
        }
    }

    public function set_description($description)
    {
        $this->description = $description;
    }

    public function get()
    {
        return $this->description;
    }

    public function message()
    {
        return "##error##Error: ##end##The module ##highlight##{$this->source}##end## returned the error ##error##{$this->description}##end##";
    }
}
