<?php
/*
* Tools.php - Module Containing Useful Functions to be used by other Modules
*
* Made by Temar (most code is Simply Taken from elsewhere)
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

class Tools extends \Commodities\BasePassiveModule
{

    public function __construct(&$bot)
    {

        parent::__construct($bot, get_class($this));
        $this->register_module("tools");
        $this->bot->core("settings")
            ->create("tools", "force_sockets", FALSE, "Should we force the usage of Sockets in get_site() even if Curl is available?");
        $this->bot->core("settings")
            ->create("tools", "connect_timeout", 25, "How long in seconds should we wait for data to be returned from the webserver when making get_data calls?");
        // Please do not change this string.
        $this->useragent = BOT_VERSION_NAME . "/" . BOT_VERSION . " (Originating bot: " . $this->bot->botname . "; Dimension: " . $this->bot->dimension . ";)";
        $this->randomsource = "";
    }

    public function chatcmd($link, $title, $origin = FALSE, $strip = FALSE)
    {
        $origin = strtolower($origin);
        $msgstrip = "";
        switch ($origin) {
        case 'gc':
        case 'o':
        case 'gu':
        case '3':
            if ($this->bot->game == "aoc") {
                $chatcmd = "gu <pre>";
            } else {
                $chatcmd = "o <pre>";
            }
            Break;
        case 'pgmsg':
        case 'pg':
        case '2':
            $chatcmd = "group " . $this->bot->botname . " <pre>";
            Break;
        case 'start':
            $chatcmd = "start ";
            Break;
        case 'tell':
        case '0':
        case '1':
        case FALSE:
            $chatcmd = "tell " . $this->bot->botname . " <pre>";
            Break;
        case '/':
            $chatcmd = "";
            Break;
        Default:
            $chatcmd = $origin . " ";
        }
        if ($strip) {
            $msgstrip = "style=text-decoration:none ";
        }
        Return ('<a ' . $msgstrip . 'href=\'chatcmd:///' . $chatcmd . $link . '\'>' . $title . '</a>');
    }

    public function get_site($url, $strip_headers = FALSE, $read_timeout = FALSE)
    {
        if (!function_exists('curl_init')
            || ($this->bot->core("settings")
                ->get("tools", "force_sockets") == TRUE)
        ) {
            Return $this->get_site_sock($url, $strip_headers, $read_timeout);
        } else {
            Return $this->get_site_curl($url, $strip_headers, $read_timeout);
        }
    }

    public function get_site_sock($url, $strip_headers = FALSE, $read_timeout = FALSE)
    {
        $return = $this->get_site_data($url, $strip_headers, $read_timeout);
        if (($return instanceof BotError) && $this->use_proxy_server && !empty($this->proxy_server_address)) {
            echo "We're using a proxy\n";
            foreach ($this->proxy_server_address as $proxy) {
                echo "Trying proxy: " . $proxy . "\n";
                $return = $this->get_site_data($url, $strip_headers, $read_timeout, $proxy);
                if (!($return instanceof BotError)) {
                    break;
                }
            }
        }

        if ($return['error']) {
            $this->bot->log("ERROR", "tools", $return['errordesc'] . " Reason (" . $return['content'] . ")");
        }

        return $return;
    }

    /*
    Gets the data from a URL
    */
    public function get_site_data(
        $url, $strip_headers = FALSE, $read_timeout = FALSE,
        $proxy = ''
    )
    {
        $get_url = parse_url($url);
        // Check to see if we're using a proxy, and get the IP address for the target host.
        if (!empty($proxy)) {
            $proxy_address = explode(":", $proxy);
            $address = gethostbyname($proxy_address[0]);
            $service_port = $proxy_address[1];
        } else {
            $address = gethostbyname($get_url['host']);
            /* Get the port for the WWW service. */
            $service_port = getservbyname('www', 'tcp');
        }
        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // Check to see if the socket failed to create.
        if ($socket === FALSE) {
            $this->error->set("Failed to create socket. Error was: " . socket_strerror(socket_last_error()));

            return $this->error;
        }

        // Set some sane read timeouts to prevent the bot from hanging forever.
        if (!$read_timeout) {
            $read_timeout = $this->bot->core("settings")
                ->get("tools", "connect_timeout");
        }

        socket_set_option(
            $socket, SOL_SOCKET, SO_RCVTIMEO, array(
                "sec" => $read_timeout,
                "usec" => 0
            )
        );

        $connect_result = @socket_connect($socket, $address, $service_port);

        // Make sure we have a connection
        if ($connect_result === FALSE) {
            $this->error->set("Failed to connect to server " . $address . ":" . $service_port . " (" . $url . ") Error was: " . socket_strerror(socket_last_error()));

            return $this->error;
        }
        // Rebuild the full query after parse_url
        $url = $get_url["path"];
        if (!empty($get_url["query"])) {
            $url .= '?' . $get_url["query"];
        }
        $in = "GET $url HTTP/1.0\r\n";
        $in .= "Host: " . $get_url['host'] . "\r\n";
        $in .= "Connection: Close\r\n";
        $in .= "User-Agent:" . $this->useragent . "\r\n\r\n";
        $write_result = @socket_write($socket, $in, strlen($in));
        // Make sure we wrote to the server okay.
        if ($write_result === FALSE) {
            $this->error->set("Failed to write to server: " . socket_strerror(socket_last_error()));

            return $this->error;
        }
        $return["content"] = "";
        $read_result = @socket_read($socket, 2048);
        while ($read_result != "" && $read_result !== FALSE) {
            $return .= $read_result;
            $read_result = @socket_read($socket, 2048);
        }
        // Make sure we got a response back from the server.
        if ($read_result === FALSE) {
            $this->error->set("Failed to read response: " . socket_strerror(socket_last_error()));

            return $this->error;
        }
        $close_result = @socket_close($socket);
        // Make sure we closed our socket properly.  Open sockets are bad!
        if ($close_result === FALSE) {
            $this->error->set("Failed to close socket: " . socket_strerror(socket_last_error()));

            return $this->error;
        }
        // Did the calling function want http headers stripped?
        if ($strip_headers) {
            $split = split("\r\n\r\n", $return);
            $return = $split[1];
        }

        return $return;
    }


    public function get_site_curl(
        $url, $strip_headers = FALSE, $timeout = FALSE,
        $post = NULL,
        $login = NULL
    ) // login should be username:password
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
        // Set your login and password for authentication
        //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        //curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$pw);
        // You can use CURLAUTH_BASIC, CURLAUTH_DIGEST, CURLAUTH_GSSNEGOTIATE,
        // CURLAUTH_NTLM, CURLAUTH_ANY, and CURLAUTH_ANYSAFE
        //
        // You can use the bitwise | (or) operator to combine more than one method.
        // If you do this, CURL will poll the server to see what methods it supports and pick the best one.
        //
        // CURLAUTH_ANY is an alias for CURLAUTH_BASIC | CURLAUTH_DIGEST |
        // CURLAUTH_GSSNEGOTIATE | CURLAUTH_NTLM
        //
        // CURLAUTH_ANYSAFE is an alias for CURLAUTH_DIGEST | CURLAUTH_GSSNEGOTIATE |
        // CURLAUTH_NTLM
        //
        // Personally I prefer CURLAUTH_ANY as it covers all bases
        // This is occassionally required to stop CURL from verifying the peer's certificate.
        // CURLOPT_SSL_VERIFYHOST may also need to be TRUE or FALSE if
        // CURLOPT_SSL_VERIFYPEER is disabled (it defaults to 2 - check the existence of a
        // common name and also verify that it matches the hostname provided)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        // Optional: Return the result instead of printing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // Specify a timeout
        if (!$timeout) {
            $timeout = $this->bot->core("settings")
                ->get("tools", "connect_timeout");
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // The usual - get the data and close the session
        $return = curl_exec($ch);
        curl_close($ch);
        // Did the calling function want http headers stripped?
        //if ($strip_headers)// already stripped?
        //{
        //	$split = split("\r\n\r\n",$return);
        //	$return = $split[1];
        //}
        Return $return;
    }

    /*
    Parse XML crap
    */
    public function xmlparse($xml, $tag)
    {
        $tmp = explode("<" . $tag . ">", $xml);
        if (!isset($tmp[1])) {
            $tmp[1] = "";
        }
        $tmp = explode("</" . $tag . ">", $tmp[1]);

        return $tmp[0];
    }

    public function make_blob($title, $content, $header = TRUE)
    {
        $inside = "";
        if ($header) {
            // Generic header for all info windows, shamelessly borrowed from VhaBot
            $inside .= "##blob_title##:::::::::::##end## ##blob_text##BeBot Client Terminal##end## ##blob_title##::::::::::::##end##\n";
            $inside .= $this->chatcmd('about', '##blob_title##�##end## ##blob_text##About##end## ##blob_title##�##end##', FALSE, TRUE) . "     ";
            $inside .= $this->chatcmd('help', '##blob_title##�##end## ##blob_text##Help##end## ##blob_title##�##end##', FALSE, TRUE) . "     ";
            $inside .= $this->chatcmd('close InfoView', '##blob_title##�##end## ##blob_text##Close Terminal##end## ##blob_title##�##end##', '/', TRUE);
            $inside .= "\n##blob_title##������������������������������������##end##\n";
        }
        // Using " inside a blob will end the blob.
        // Convert opening and closing tags with " to '
        // Convert any other " to HTML entities.
        $content = str_replace("=\"", "='", $content);
        $content = str_replace("\">", "'>", $content);
        $content = str_replace("\"", "&quot;", $content);
        $inside .= $content;

        return "<a href=\"text://" . $inside . "\">" . $title . "</a>";
    }

    /*
    Creates a text blob.
    */
    public function make_item(
        $lowid, $highid, $ql, $name, $alt = FALSE,
        $strip = FALSE
    )
    {
        $msgstrip = "";
        if ($strip) {
            $msgstrip = "style=text-decoration:none ";
        }
        $quote = '"';
        if ($alt) {
            $quote = '\'';
        }
        $name = str_replace("'", "&#039;", $name);

        return "<a {$msgstrip}href=" . $quote . "itemref://$lowid/$highid/$ql" . $quote . ">$name</a>";
    }

    /*
    Takes an item string and returns an array with lowid, highid, ql and name.
    If $item is unparsable it returns a BotError
    */
    public function parse_item($item)
    {
        $pattern = '|<a href="itemref://([0-9]+)/([0-9]+)/([0-9]{1,3})">([^<]+)</a>|';
        preg_match($pattern, $item, $parts);
        if (empty($parts)) {
            $this->error->set("Unable to parse item: '$item'");

            return ($this->error);
        }
        $parsed['lowid'] = $parts[1];
        $parsed['highid'] = $parts[2];
        $parsed['ql'] = $parts[3];
        $parsed['name'] = $parts[4];

        return ($parsed);
    }

    //Returns true if $item is an itemref, false otherwise.
    public function is_item($item)
    {
        $pattern = '|<a href="itemref://([0-9]+)/([0-9]+)/([0-9]{1,3})">([^<]+)</a>|';
        preg_match($pattern, $item, $parts);
        if (empty($parts)) {
            return FALSE;
        }

        return TRUE;
    }

    /*
    Used to convert an overflowed (unsigned) integer to a string with the correct positive unsigned integer value
    If the passed integer is not negative, the integer is merely passed back in string form with no modifications.
    */
    public function int_to_string($int)
    {
        if ($int <= -1) {
            $int += (float) "4294967296";
        }

        return (string) $int;
    }

    /*
    Used to convert an unsigned interger in string form to an overflowed (negative) integere
    If the passed string is not an integer large enough to overflow, the string is merely passed back in integer form with no modifications.
    */
    public function string_to_int($string)
    {
        $int = (float) $string;
        if ($int > (float) 2147483647) {
            $int -= (float) "4294967296";
        }

        return (int) $int;
    }

    /*
    Checks if a player name is valid and if the player exists.
    Returns BotError on failure
    Returns ucfirst(strtolower($name)) if the player exists.
    */
    public function validate_player($name, $check_exists = TRUE)
    {
        $name = trim(ucfirst(strtolower($name)));
        if (strlen($name) < 3 || strlen($name) > 14) {
            $this->error->set("Player name has to be between 4 and 13 characters long (inclusive)");

            return ($this->error);
        }
        if (preg_match("|([a-z]+[0-9]*[^a-z]*)|", $name) == 0) {
            $this->error->set("Player name has to be alphabetical followed by 0 or more digits not followed by alphabetical characters.");

            return ($this->error);
        }
        if ($check_exists) {
            $uid = $this->bot->core('player')->id($name);
            if (!$uid || ($uid instanceof BotError)) {
                $this->error->set("Player '$name' does not exist.");

                return ($this->error);
            }
        }

        return ($name);
    }

    public function my_rand($min = FALSE, $max = FALSE)
    {
        // For now we only support Mersienne Twister, but this can be changed.
        $this->randomsource = "Mersenne Twister";
        if (isset($min)) {
            return mt_rand($min, $max);
        } else {
            return mt_rand();
        }
    }

    public function best_match($find, $in, $perc = 0)
    {
        $use = array(0);
        $percentage = 0;

        if (!empty($in)) {
            foreach ($in as $compare) {
                similar_text($find, $compare, $percentage);
                if ($percentage >= $perc
                    && $percentage > $use[0]
                ) {
                    $use = array(
                        $percentage,
                        $compare
                    );
                }
            }
        }

        return $use;
    }

    //return TRUE if the same, and FALSE if not
    public function compare($a, $b)
    {
        if (is_array($a) && is_array($b)) {
            $dif = array_diff_assoc($a, $b);
            if (!empty($dif)) {
                Return (FALSE);
            } else {
                $check = TRUE;
                foreach ($a as $k => $v) {
                    if (is_array($v) && $check) {
                        $check = $this->compare($v, $b[$k]);
                    }
                }
                Return ($check);
            }
        }
        if (is_array($a) || is_array($b)) {
            Return (FALSE);
        } else {
            Return ($a == $b);
        }
    }
}
$tools = new tools($bot);