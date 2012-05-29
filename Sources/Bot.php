<?php
/*
* Bot.php - The actual core functions for the bot
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
This is where the basic magic happens
Some functions you might need:

connect():
Connects the bot with AO Chatserver

disconnect():
Disconnects the bot from AO Chatserver

reconnect():
Disconnects and then connects the bot from AO Chatserver

log($first, $second, $msg):
Writes to console/log file.

makeBlob($title, $content):
Makes a text blob.
- Returns blob.

make_chatcommand($link, $title):
Creates a clickable chatcommand link
- Returns string

makeItem($lowid, $highid, $ql, $name)
Makes an item reference.
- Returns reference blob.

sendTell($to, $msg):
Sends a sendTell to character.

sendPrivateGroup($msg):
Sends a msg to the privategroup.

sendGuildChat($msg):
Sends a msg to the guildchat.

sendHelp($to):
Sends /sendTell <botname> <pre>help.

sendPermissionDenied($to, $command, $type)
If $type is missing or 0 error is returned to the calling function, else it
sends a permission denied error to the apropriate location based on $type for $command.

getSite($url, $strip_headers, $server_timeout, $read_timeout):
Retrives the content of a site

intToString($int)
Used to convert an overflowed (unsigned) integer to a string with the correct positive unsigned integer value
If the passed integer is not negative, the integer is merely passed back in string form with no modifications.
- Returns a string.

stringToInt($string)
Used to convert an unsigned interger in string form to an overflowed (negative) integere
If the passed string is not an integer large enough to overflow, the string is merely passed back in integer form with no modifications.
- Returns an integer.
*/
define('CHAT_AO_TELL', bindec("00 00 00 001"));
define('CHAT_AO_PGROUP', bindec("00 00 00 010"));
define('CHAT_AO_GC', bindec("00 00 00 100"));
define('CHAT_AO', bindec("00 00 00 111"));
define('CHAT_IRC_PRIV', bindec("00 00 01 000"));
define('CHAT_IRC_CHAN', bindec("00 00 10 000"));
define('CHAT_IRC', bindec("00 00 11 000"));
define('CHAT_MSN_PRIV', bindec("00 01 00 000"));
define('CHAT_MSN_PUB', bindec("00 10 00 000"));
define('CHAT_MSN', bindec("00 11 00 000"));
define('CHAT_PRIVATE', bindec("00 01 01 001"));
define('CHAT_ALL', bindec("11 1111111"));
define("SAME", 1);
define("TELL", 2);
define("GC", 4);
define("PG", 8);
define("RELAY", 16);
define("IRC", 32);
define("ALL", 255);
class Bot
{
    var $lastTell;
    var $banMessageOut;
    var $dimension;
    var $botVersion;
    var $botVersionName;
    var $otherBots;
    var $aoc;
    var $irc;
    var $db;
    var $commPre;
    var $cronDelay;
    var $tellDelay;
    var $maxSize;
    var $reconnectTime;
    var $guildBot;
    var $guildId;
    var $guild;
    var $log;
    var $logPath;
    var $logTimestamp;
    var $useProxyServer;
    var $proxyServerAddress;
    var $startTime;
    var $commands;
    public $owner;
    public $superAdmin;
    private $moduleLinks = array();
    private $cronTimes = array();
    private $cronJobTimer = array();
    private $cronJobActive = array();
    private $cron = array();
    private $startupTime;
    public $glob = array();
    public $botname;
    public $botHandle; // == botname@dimension
    public $debug = FALSE;
    public static $instance;
    public $dispatcher;


    public static function factory($configFile = NULL)
    {
        require_once ("./Conf/ServerList.php");
        if (!empty($configFile)) {
            $configFile = ucfirst(strtolower($configFile)) . ".Bot.conf";
        }
        else {
            $configFile = "Bot.conf";
        }
        //Read configFile
        if (file_exists("./Conf/" . $configFile)) {
            require_once "./Conf/" . $configFile;
            echo "Loaded bot configuration from Conf/" . $configFile . "\n";
        }
        else {
            die("Could not read config file Conf/" . $configFile);
        }

        if (empty($ao_password) || $ao_password == "") {
            $fp = fopen('./conf/pw', 'r');
            if ($fp) {
                $ao_password = fread($fp, filesize('./Conf/pw'));
                fclose($fp);
                $fp = fopen('./Conf/pw', 'w');
                fwrite($fp, "");
                fclose($fp);
            }
            else {
                if (empty($ao_password) || $ao_password == "") {
                    die("No password set in either ./Conf/" . $configFile . " or in Conf/pw");
                }
            }
        }
        //Determine which game we are playing
        if (!empty($_BEBOT_server_list['Ao'][$dimension])) {
            define('AOCHAT_GAME', 'Ao');
        }
        elseif (!empty($_BEBOT_server_list['Aoc'][$dimension])) {
            define('AOCHAT_GAME', 'Aoc');
        }
        else {
            die("Unable to find dimension '$dimension' in any game.");
        }
        //Make sure that the log path exists.
        $logpath = $log_path . "/" . strtolower($bot_name) . "@RK" . $dimension;
        if (!file_exists($logpath)) {
            mkdir($logpath);
        }
        //Determine botHandle
        $botHandle = $bot_name . "@" . $dimension;
        //Check if bot has already been created.
        if (isset(self::$instance[$botHandle])) {
            return self::$instance[$botHandle];
        }
        //instantiate bot
        $class = __CLASS__;
        self::$instance[$botHandle] = new $class($botHandle);
        self::$instance[$botHandle]->server = $_BEBOT_server_list[AOCHAT_GAME][$dimension]['server'];
        self::$instance[$botHandle]->port = $_BEBOT_server_list[AOCHAT_GAME][$dimension]['port'];
        //initialize bot.
        self::$instance[$botHandle]->username = $ao_username;
        self::$instance[$botHandle]->password = $ao_password;
        self::$instance[$botHandle]->botname = ucfirst(strtolower($bot_name));
        self::$instance[$botHandle]->dimension = ucfirst(strtolower($dimension));
        self::$instance[$botHandle]->botversion = BOT_VERSION;
        self::$instance[$botHandle]->botversionname = BOT_VERSION_NAME;
        self::$instance[$botHandle]->other_bots = $other_bots;
        self::$instance[$botHandle]->commands = array();
        self::$instance[$botHandle]->commpre = $command_prefix;
        self::$instance[$botHandle]->crondelay = $cron_delay;
        self::$instance[$botHandle]->telldelay = $tell_delay;
        self::$instance[$botHandle]->maxsize = $max_blobsize;
        self::$instance[$botHandle]->reconnecttime = $reconnect_time;
        self::$instance[$botHandle]->guildbot = $guildbot;
        self::$instance[$botHandle]->guildid = $guild_id;
        self::$instance[$botHandle]->guildname = $guild;
        self::$instance[$botHandle]->log = $log;
        self::$instance[$botHandle]->log_path = $logpath;
        self::$instance[$botHandle]->log_timestamp = $log_timestamp;
        self::$instance[$botHandle]->banmsgout = array();
        self::$instance[$botHandle]->use_proxy_server = $use_proxy_server;
        self::$instance[$botHandle]->proxy_server_address = explode(",", $proxy_server_address);
        self::$instance[$botHandle]->starttime = time();
        self::$instance[$botHandle]->game = AOCHAT_GAME;
        self::$instance[$botHandle]->accessallbots = $accessallbots;
        self::$instance[$botHandle]->core_directories = $core_directories;
        self::$instance[$botHandle]->module_directories = $module_directories;
        //We need to keep these too.
        if (isset($owner)) {
            self::$instance[$botHandle]->owner = $owner;
        }
        else {
            self::$instance[$botHandle]->owner = NULL;
        }
        if (isset($super_admin)) {
            self::$instance[$botHandle]->super_admin = $super_admin;
        }
        else {
            self::$instance[$botHandle]->super_admin = NULL;
        }
        // create new ConfigMagik-Object (HACXX ALERT! This should most likely be a singleton!)
        self::$instance[$botHandle]->ini = ConfigMagik::get_instance($botHandle, "Conf/" . ucfirst(strtolower($bot_name)) . ".Modules.ini", TRUE, TRUE);
        self::$instance[$botHandle]->registerModule(self::$instance[$botHandle]->ini, 'ini');
        //Instantiate singletons
        self::$instance[$botHandle]->irc = &$irc; //To do: This should probably be a singleton aswell.
        self::$instance[$botHandle]->aoc = AOChat::get_instance($botHandle);
        self::$instance[$botHandle]->db = MySQL::get_instance($botHandle);
        //Pass back the handle of the bot for future reference.
        return ($botHandle);
    }


    public static function getInstance($bothandle)
    {
        if (!isset(self::$instance[$bothandle])) {
            return FALSE;
        }
        return self::$instance[$bothandle];
    }


    private function __construct()
    {
        //Empty
    }


    function loadFiles($section, $directory)
    {
        if (!is_dir($directory)) {
            $this->log("LOAD", "ERROR", "The specified directory '$directory' is inaccessible!");
            return;
        }
        $bot = $this;
        $section = ucfirst(strtolower($section));
        $this->log(strtoupper($section), "LOAD", "Loading $section-modules from '$directory'");
        $folder = dir("./$directory");
        $filelist = array();
        //Create an array of files loadable.
        while ($module = $folder->read()) {
            $is_disabled = $this->ini->get($module, $section);
            if (!is_dir($module) && !preg_match("/^_/", $module) && preg_match("/\.php$/i", $module) && $is_disabled != "FALSE") {
                $filelist[] = $module;
            }
        }
        if (!empty($filelist)) {
            sort($filelist);
            foreach ($filelist as $file) {
                require_once ("$directory/$file");
                $this->log(strtoupper($section), "LOAD", $file);
            }
        }
        echo "\n";
    }


    /*
    Connects the bot to AO's chat server
    */
    function connect()
    {
        // Make sure all cronjobs are locked, we don't want to run any cronJob before we are logged in!
        $this->cron_activated = FALSE;

        if (!$this->aoc->connect($this->server, $this->port)) {
            $this->cron_activated = FALSE;
            $this->disconnect();
            $this->log("CONN", "ERROR", "Can't connect to server. Retrying in " . $this->reconnectTime . " seconds.");
            sleep($this->reconnectTime);
            die("The bot is restarting.\n");
        }
        // AoC authentication is a bit different
        if ($this->game == "aoc") {
            // Open connection
            $this->log("LOGIN", "STATUS", "Connecting to $this->game server $server:$port");
            if (!$this->aoc->connect($server, $port, $this->sixtyfourbit)) {
                $this->cron_activated = FALSE;
                $this->disconnect();
                $this->log("CONN", "ERROR", "Can't connect to server. Retrying in " . $this->reconnectTime . " seconds.");
                sleep($this->reconnectTime);
                die("The bot is restarting.\n");
            }
        }
        else {
            // Authenticate
            $this->log("LOGIN", "STATUS", "Authenticating $this->username");
            $this->aoc->authenticate($this->username, $this->password);
            // Login the bot character
            $this->log("LOGIN", "STATUS", "Logging in $this->botname");
            $this->aoc->login(ucfirst(strtolower($this->botname)));
        }
        /*
        We're logged in. Make sure we no longer keep username and password in memory.
        */
        unset($this->username);
        unset($this->password);
        if ($this->game == "aoc") {
            $dispg = TRUE;
        }
        else {
            $dispg = FALSE;
        }

        // Create the CORE settings, settings module is initialized here
        $this->core("settings")
            ->create("Core", "RequireCommandPrefixInTells", FALSE, "Is the command prefix (in this bot <pre>) required for commands in tells?");
        $this->core("settings")
            ->create("Core", "LogGCOutput", TRUE, "Should the bots own output be logged when sending messages to organization chat?");
        $this->core("settings")
            ->create("Core", "LogPGOutput", TRUE, "Should the bots own output be logged when sending messages to private groups?");
        $this->core("settings")
            ->create(
            "Core", "SimilarCheck", FALSE, "Should the bot try to match a similar written command if an exact match is not found? This is not recommended if you dont use a prefix!"
        );
        $this->core("settings")
            ->create("Core", "SimilarMinimum", 75, "What is the minimum percentage of similarity that has to be reached to consider two commands similar?", "75;80;85;90;95");
        $this->core("settings")
            ->create("Core", "CommandErrorTell", FALSE, "Should the bot output an Access Level Error if a user tries to use a command in tells he doesn't have access to?");
        $this->core("settings")
            ->create(
            "Core", "CommandErrorPgMsg", FALSE, "Should the bot output an Access Level Error if a user tries to use a command in the private group he doesn't have access to?"
        );
        $this->core("settings")
            ->create("Core", "CommandErrorGc", FALSE, "Should the bot output an Access Level Error if a user tries to use a command in guild chat he doesn't have access to?");
        $this->core("settings")
            ->create(
            "Core", "CommandErrorExtPgMsg", FALSE,
            "Should the bot output an Access Level Error if a user tries to use a command in an external private group he doesn't have access to?"
        );
        $this->core("settings")
            ->create("Core", "CommandDisabledError", FALSE, "Should the bot output a Disabled Error if they try to use a command that is Disabled?");
        $this->core("settings")
            ->create("Core", "DisableGC", FALSE, "Should the Bot output into and reactions to commands in the guildchat be disabled?");
        $this->core("settings")
            ->create("Core", "DisablePGMSG", $dispg, "Should the Bot output into and reactions to commands in it's own private group be disabled?");
        $this->core("settings")
            ->create("Core", "ColorizeTells", TRUE, "Should tells going out be colorized on default? Notice: Modules can set a nocolor flag before sending out tells.");
        $this->core("settings")
            ->create("Core", "ColorizeGC", TRUE, "Should output to guild chat be colorized using the current theme?");
        $this->core("settings")
            ->create("Core", "ColorizePGMSG", TRUE, "Should output to private group be colorized using the current theme?");
        $this->core("settings")
            ->create("Core", "BanReason", TRUE, "Should the Details on the Ban be Given to user when he tries to use bot?");
        $this->core("settings")
            ->create("Core", "DisableGCchat", FALSE, "Should the Bot read none command chat in GC?");
        $this->core("settings")
            ->create("Core", "DisablePGMSGchat", $dispg, "Should the Bot read none command chat in it's own private group?");

        // Tell modules that the bot is connected
        if (!empty($this->commands["connect"])) {
            $keys = array_keys($this->commands["connect"]);
            foreach ($keys as $key) {
                if ($this->commands["connect"][$key] != NULL) {
                    $this->commands["connect"][$key]->connect();
                }
            }
        }
        $this->startupTime = time() + $this->cronDelay;
        // Set the time of the first cronjobs
        foreach ($this->cronTimes as $timestr => $value) {
            $this->cronJobTimer[$timestr] = $this->startupTime;
        }
        // and unlock all cronjobs again:
        $this->cron_activated = TRUE;
        //Store time of connection
        $this->connected_time = time();
    }


    /*
    Reconnect the bot.
    */
    function reconnect()
    {
        $this->cron_activated = FALSE;
        $this->disconnect();
        $this->log("CONN", "ERROR", "Bot has disconnected. Reconnecting in " . $this->reconnectTime . " seconds.");
        sleep($this->reconnectTime);
        die("The bot is restarting.\n");
    }


    /*
    Dissconnect the bot
    */
    function disconnect()
    {
        $this->aoc->disconnect();
        if (!empty($this->commands["disconnect"])) {
            $keys = array_keys($this->commands["disconnect"]);
            foreach ($keys as $key) {
                if ($this->commands["disconnect"][$key] != NULL) {
                    $this->commands["disconnect"][$key]->disconnect();
                }
            }
        }
    }


    function replaceStringTags($msg)
    {
        $msg = str_replace("<botname>", $this->botname, $msg);
        $msg = str_replace("<guildname>", $this->guildname, $msg);
        $msg = str_replace("<pre>", str_replace("\\", "", $this->commPre), $msg);
        return $msg;
    }


    /*
    sends a sendTell asking user to use "help"
    */
    function sendHelp($to, $command = FALSE)
    {
        if ($command == FALSE) {
            $this->sendTell($to, "/sendTell <botname> <pre>help");
        }
        else {
            $this->sendTell(
                $to, $this->core("help")
                    ->show_help($to, $command)
            );
        }
    }


    /*
    sends a message over IRC if it's enabled and connected
    */
    function sendIrc($prefix, $name, $msg)
    {
        //		if (isset($this -> irc) && $this -> existsModule("irc"))
        if ($this->existsModule("irc")) {
            if ($this->core("settings")->get("Irc", "Connected")) {
                // Parse the color codes and let the IRC module deal with filtering.
                $msg = $this->core("colors")->parse($msg);
                $this->core("irc")->send_irc($prefix, $name, $msg);
            }
        }
    }


    /*
    Notifies someone that they are banned, but only once.
    */
    function sendBan($to, $msg = FALSE)
    {
        if (!isset($this->banMessageOut[$to]) || $this->banMessageOut[$to] < (time() - 60 * 5)) {
            $this->banMessageOut[$to] = time();
            if ($msg === FALSE) {
                if ($this->core("settings")->get("Core", "BanReason")) {
                    $why = $this->db->select("SELECT banned_by, banned_for, banned_until FROM #___users WHERE nickname = '" . $to . "'");
                    if ($why[0][2] > 0) {
                        $until = "Temporary ban until " . gmdate(
                            $this
                                ->core("settings")
                                ->get("Time", "FormatString"), $why[0][2]
                        );
                    }
                    else {
                        $until = "Permanent ban.";
                    }
                    $why = " by ##highlight##" . $why[0][0] . "##end## for Reason: ##highlight##" . $why[0][1] . "##end##\n" . $until;
                }
                else {
                    $why = ".";
                }
                $this->sendTell($to, "You are banned from <botname>" . $why);
            }
            else {
                $this->sendTell($to, $msg);
            }
        }
        else {
            return FALSE;
        }
    }


    /*
    Sends a permission denied error to user for the given command.
    */
    function sendPermissionDenied($to, $command, $type = 0)
    {
        $string = "You do not have permission to access $command";
        if ($type == 0) {
            return $string;
        }
        else {
            $this->sendOutput($to, $string, $type);
        }
    }


    /*
    send a sendTell. Set $low to 1 on tells that are likely to cause spam.
    */
    function sendTell(
        $to, $msg, $low = 0, $color = TRUE, $sizeCheck = TRUE,
        $parseColors = TRUE
    )
    {
        // parse all color tags:
        if ($parseColors) {
            $msg = $this->core("colors")->parse($msg);
        }
        $send = TRUE;
        if ($sizeCheck) {
            if (strlen($msg) < 100000) {
                if (preg_match("/<a href=\"(.+)\">/isU", $msg, $info)) {
                    if (strlen($info[1]) > $this->maxSize) {
                        $this->cutSize($msg, "sendTell", $to, $low);
                        $send = FALSE;
                    }
                }
            }
            else {
                $info = explode('<a href="', $msg, 2);
                if (count($info) > 1) {
                    if (strlen($msg) > $this->maxSize) {
                        $this->cutSize($msg, "sendTell", $to, $low);
                        $send = FALSE;
                    }
                }
            }
        }
        if ($send) {
            $msg = $this->replaceStringTags($msg);
            if ($color && $this->core("settings")->get("Core", "ColorizeTells")
            ) {
                $msg = $this->core("colors")->colorize("normal", $msg);
            }
            if ($this->core("chat_queue")->check_queue()) {
                if (is_numeric($to)) {
                    $to_name = $this->core('player')->name($to);
                }
                else {
                    $to_name = $to;
                }
                $this->log("TELL", "OUT", "-> " . $to_name . ": " . $msg);
                $msg = utf8_encode($msg);
                $this->aoc->send_tell($to, $msg);
            }
            else {
                $this->core("chat_queue")->into_queue($to, $msg, "sendTell", $low);
            }
        }
    }


    /*
    send a message to private group
    */
    function sendPrivateGroup(
        $msg, $group = NULL, $checksize = TRUE,
        $parseColors = TRUE
    )
    {
        // Never send any private group message in AoC, because this would disconnect the bot
        if ($this->game == "aoc") {
            /*** FIXME ***/
            // We need to eradicate calls to this from all modules for sanity's sake.
            return FALSE;
        }
        if ($group == NULL) {
            $group = $this->botname;
        }
        if ($group == $this->botname
            && $this->core("settings")
                ->get("Core", "DisablePGMSG")
        ) {
            return FALSE;
        }
        // parse all color tags:
        if ($parseColors) {
            $msg = $this->core("colors")->parse($msg);
        }
        $gid = $this->core("player")->id($group);
        $send = TRUE;
        if ($checksize) {
            if (preg_match("/<a href=\"(.+)\">/isU", $msg, $info)) {
                if (strlen($info[1]) > $this->maxSize) {
                    $this->cutSize($msg, "pgroup", $group);
                    $send = FALSE;
                }
            }
        }
        if ($send) {
            $msg = $this->replaceStringTags($msg);
            $msg = utf8_encode($msg);
            if (strtolower($group) == strtolower($this->botname)) {
                if ($this->core("settings")->get("Core", "ColorizePGMSG")) {
                    $msg = $this->core("colors")->colorize("normal", $msg);
                }
                $this->aoc->send_privgroup($gid, $msg);
            }
            else {
                $this->aoc->send_privgroup($gid, $msg);
            }
        }
    }


    /*
    * Send a message to guild channel
    */
    function sendGuildChat($msg, $low = 0, $checksize = TRUE)
    {
        if ($this->core("settings")->get("Core", "DisableGC")) {
            Return FALSE;
        }
        // parse all color tags:
        $msg = $this->core("colors")->parse($msg);
        $send = TRUE;
        if ($checksize) {
            if (preg_match("/<a href=\"(.+)\">/isU", $msg, $info)) {
                if (strlen($info[1]) > $this->maxSize) {
                    $this->cutSize($msg, "sendToGuildChat", "", $low);
                    $send = FALSE;
                }
            }
        }
        if ($send) {
            $msg = $this->replaceStringTags($msg);
            if ($this->core("settings")->get("Core", "ColorizeGC")) {
                $msg = $this->core("colors")->colorize("normal", $msg);
            }
            if ($this->game == "ao") {
                $guild = $this->guildname;
            }
            else {
                $guild = "~Guild";
            }
            if ($this->core("chat_queue")->check_queue()) {
                $msg = utf8_encode($msg);
                $this->aoc->send_group($guild, $msg);
            }
            else {
                $this->core("chat_queue")->into_queue($guild, $msg, "sendToGuildChat", $low);
            }
        }
    }


    function sendOutput($source, $msg, $type, $low = 0)
    {
        // Parse color tags now to be sure they don't get changed by output filters
        $msg = $this->core("colors")->parse($msg);
        // Output filter
        if ($this->core("settings")->exists('Filter', 'Enabled')) {
            if ($this->core("settings")->get('Filter', 'Enabled')) {
                $msg = $this->core("stringfilter")->output_filter($msg);
            }
        }
        if (!is_numeric($type)) {
            $type = strtolower($type);
        }
        switch ($type) {
        case '0':
        case '1':
        case 'sendTell':
            $this->sendTell($source, $msg, $low);
            break;
        case '2':
        case 'pgroup':
        case 'sendToGroup':
            $this->sendPrivateGroup($msg);
            break;
        case '3':
        case 'sendToGuildChat':
            $this->sendGuildChat($msg, $low);
            break;
        case '4':
        case 'both':
            $this->sendGuildChat($msg, $low);
            $this->sendPrivateGroup($msg);
            break;
        default:
            $this->log("OUTPUT", "ERROR", "Broken plugin, type: $type is unknown to me; source: $source, message: $msg");
        }
    }


    /*
    * This function tries to find a similar written command based compared to $cmd, based on
    * all available commands in $channel. The percentage of match and the closest matching command
    * are returned in an array.
    */
    function findSimilarCommand($channel, $cmd)
    {
        $use = array(0);
        $percentage = 0;
        if (isset($this->commands["sendTell"][$cmd]) || isset($this->commands["sendToGuildChat"][$cmd]) || isset($this->commands["sendToGroup"][$cmd])
            || isset($this->commands["externalPrivateGroupMessage"][$cmd])
        ) {
            return $use;
        }
        $perc = $this->core("settings")->get("Core", "SimilarMinimum");
        foreach ($this->commands[$channel] as $compare_cmd => $value) {
            similar_text($cmd, $compare_cmd, $percentage);
            if ($percentage >= $perc && $percentage > $use[0]) {
                $use = array(
                    $percentage,
                    $compare_cmd
                );
            }
        }
        return $use;
    }


    /*
    * This function checks if $user got access to $command (with possible subcommands based on $msg)
    * in $channel. If the check is positive the command is executed and TRUE returned, otherwise FALSE.
    * $pgname is used to identify which external private group issued the command if $channel = externalPrivateGroupMessage.
    */
    function checkAccessAndExecute($user, $command, $msg, $channel, $pgname)
    {
        if ($this->commands[$channel][$command] != NULL) {
            if ($this->core("access_control")
                ->check_rights($user, $command, $msg, $channel)
            ) {
                if ($channel == "externalPrivateGroupMessage") {
                    $this->commands[$channel][$command]->$channel($pgname, $user, $msg);
                }
                else {
                    $this->commands[$channel][$command]->$channel($user, $msg);
                }
                return TRUE;
            }
        }
        return FALSE;
    }


    /*
    * This function check if $msg contains a command in the channel.
    * If $msg contains a command it checks for access rights based on the $user, command and $channel.
    * If $user may access the command $msg is handed over to the parser of the responsible module.
    * This function returns true if the $msg has been handled, and false otherwise.
    * $pgname is used to identify external private groups.

    This should be reworked to do things in the following manner
    *) Determine the access level of the person sending the message.
    *) If we can rule out that the message is not a command we go to the next step which should be relaying
    *) strip the prefix
    *) search the command library for a match and execute if found
    *) search the command library for a similar command, notify user about the typo and execute if found

    */
    function handleCommandInput($user, $msg, $channel, $pgname = NULL)
    {
        $match = FALSE;
        $this->command_error_text = FALSE;
        if (!empty($this->commands[$channel])) {
            if ($this->core("security")->is_banned($user)) {
                $this->sendBan($user);
                return TRUE;
            }
            $stripped_prefix = str_replace("\\", "", $this->commPre);
            // Add missing command prefix in tells if the settings allow for it:
            if ($channel == "sendTell"
                && !$this->core("settings")
                    ->get("Core", "RequireCommandPrefixInTells")
                && $this->commPre != ""
                && $msg[0] != $stripped_prefix
            ) {
                $msg = $stripped_prefix . $msg;
            }
            // Only if first character is the command prefix is any check for a command needed,
            // or if no command prefix is used at all:
            if ($this->commPre == "" || $msg[0] == $stripped_prefix) {
                // Strip command prefix if it is set - we already checked that the input started with it:
                if ($this->commPre != "") {
                    $msg = substr($msg, 1);
                }
                // Check if Command is an Alias of another Command
                $msg = $this->core("command_alias")->replace($msg);
                $cmd = explode(" ", $msg, 3);
                $cmd[0] = strtolower($cmd[0]);
                $msg = implode(" ", $cmd);
                if (isset($this->commands[$channel][$cmd[0]])) {
                    $match = TRUE;
                    if ($this->checkAccessAndExecute($user, $cmd[0], $msg, $channel, $pgname)) {
                        return TRUE;
                    }
                }
                elseif ($this->core("settings")->get("Core", "SimilarCheck")) {
                    $use = $this->findSimilarCommand($channel, $cmd[0]);
                    if ($use[0] > 0) {
                        $cmd[0] = $use[1];
                        $msg = explode(" ", $msg, 2);
                        $msg[0] = $use[1];
                        $msg = implode(" ", $msg);
                        if (isset($this->commands[$channel][$use[1]])) {
                            $match = TRUE;
                            if ($this->checkAccessAndExecute($user, $use[1], $msg, $channel, $pgname)) {
                                return TRUE;
                            }
                        }
                    }
                }
                if ($this->core("settings")
                    ->get("Core", "CommandError" . $channel)
                    && $match
                ) {
                    $minlevel = $this->core("access_control")
                        ->get_min_rights($cmd[0], $msg, $channel);
                    if ($minlevel == OWNER + 1) {
                        $minstr = "DISABLED";
                    }
                    else {
                        $minstr = $this->core("security")
                            ->get_access_name($minlevel);
                    }
                    $req = array(
                        "Command",
                        $msg,
                        $minstr
                    );
                    if ($req[2] == "DISABLED") {
                        if ($this->core("settings")
                            ->get("Core", "CommandDisabledError")
                        ) {
                            $this->command_error_text
                                = "You're not authorized to use this " . $req[0] . ": ##highlight##" . $req[1] . "##end##, it is Currently ##highlight##DISABLED##end##";
                        }
                    }
                    else {
                        $this->command_error_text
                            = "You're not authorized to use this " . $req[0] . ": ##highlight##" . $req[1] . "##end##, Your Access Level is required to be at least ##highlight##"
                            . $req[2] . "##end##";
                    }
                }
            }
            return FALSE;
        }
    }


    /*
    * This function handles input after a successless try to find a command in it.
    * If some modules has registered a chat handover for $channel it will hand it over here.
    * It checks $found first, if $found = true it doesn't do anything.
    * $group is used by external private groups and to listen to specific chat channels outside the bot.
    * Returns true if some module accessing this chat returns true, false otherwise.
    */
    function handToChat($found, $user, $msg, $channel, $group = NULL)
    {
        if ($found) {
            return TRUE;
        }
        if ($channel == "groupMessage") {
            if ($group == $this->guildname || ($this->game == "aoc" && $group == "~Guild")) {
                $group = "org";
            }
            $registered = $this->commands[$channel][$group];
        }
        else {
            $registered = $this->commands[$channel];
        }
        if (!empty($registered)) {
            $keys = array_keys($registered);
            foreach ($keys as $key) {
                if ($channel == "externalPrivateGroup") {
                    if ($this->commands[$channel][$key] != NULL) {
                        $found = $found | $this->commands[$channel][$key]->$channel($group, $user, $msg);
                    }
                }
                else {
                    if ($channel == "groupMessage") {
                        if ($this->commands[$channel][$group][$key] != NULL) {
                            $found = $found | $this->commands[$channel][$group][$key]->$channel($user, $group, $msg);
                        }
                    }
                    else {
                        if ($this->commands[$channel][$key] != NULL) {
                            $found = $found | $this->commands[$channel][$key]->$channel($user, $msg);
                        }
                    }
                }
            }
        }
        return $found;
    }


    function incoming_chat($message)
    {
    }


    /*
    Incoming Tell
    */
    function incomingTell($args)
    {
        //Get the name of the user. It's easier to handle... or is it?
        $user = $this->core("player")->name($args[0]);
        $found = FALSE;
        // Ignore bot chat, no need to handle it's own output as input again
        if ($user == 'BOTNAME') {
            // Danger will robinson. We just sent a sendTell to ourselves!!!!!!!!!
            $this->log("CORE", "INC_TELL", "Danger will robinson. Received sendTell from myself: $args[1]");
            return;
        }
        //Silently ignore tells from other bots.
        if (isset($this->otherBots[$user])) //TO DO: Do we ever ucfirst(strtolower()) the other bots?
        {
            return;
        }
        if (preg_match("/is AFK .Away from keyboard./i", $args[1]) || preg_match("/.sendTell (.+)help/i", $args[1])
            || preg_match(
                "/I only listen to members of this bot/i", $args[1]
            )
            || preg_match("/I am away from my keyboard right now,(.+)your message has been logged./i", $args[1])
            || preg_match("/Away From Keyboard/i", $args[1])
        ) {
            //We probably sendt someone a sendTell when not here. Let's leave it at that.
            return;
        }
        $args[1] = utf8_decode($args[1]);
        $this->log("TELL", "INC", $user . ": " . $args[1]);
        $found = $this->handleCommandInput($user, $args[1], "sendTell");
        $found = $this->handToChat($found, $user, $args[1], "tells");
        if ($this->command_error_text) {
            $this->sendTell($args[0], $this->command_error_text);
        }
        elseif (!$found
            && $this->core("security")
                ->check_access($user, "GUEST")
        ) {
            $this->sendHelp($args[0]);
        }
        else {
            if (!$found) {
                if ($this->guild_bot) {
                    $this->sendTell($args[0], "I only listen to members of " . $this->guildname . ".");
                }
                else {
                    $this->sendTell($args[0], "I only listen to members of this bot.");
                }
            }
        }
        unset($this->command_error_text);
    }


    /*
    Someone joined privategroup
    */
    function incomingPrivateGroupJoin($args)
    {
        $pgname = $this->core("player")->name($args[0]);
        if (empty($pgname) || $pgname == "") {
            $pgname = $this->botname;
        }
        $user = $this->core("player")->name($args[1]);
        if (strtolower($pgname) == strtolower($this->botname)) {
            $this->log("PGRP", "JOIN", $user . " joined privategroup.");
            if (!empty($this->commands["privateGroupJoin"])) {
                $keys = array_keys($this->commands["privateGroupJoin"]);
                foreach ($keys as $key) {
                    if ($this->commands["privateGroupJoin"][$key] != NULL) {
                        $this->commands["privateGroupJoin"][$key]->pgjoin($user);
                    }
                }
            }
        }
        else {
            $this->log("PGRP", "JOIN", $user . " joined the exterior privategroup of " . $pgname . ".");
            if (!empty($this->commands["externalPrivateGroupJoin"])) {
                $keys = array_keys($this->commands["externalPrivateGroupJoin"]);
                foreach ($keys as $key) {
                    if ($this->commands["externalPrivateGroupJoin"][$key] != NULL) {
                        $this->commands["externalPrivateGroupJoin"][$key]->extpgjoin($pgname, $user);
                    }
                }
            }
        }
    }


    /*
    Someone left privategroup
    */
    function incomingPrivateGroupLeave($args)
    {
        $pgname = $this->core("player")->name($args[0]);
        if (empty($pgname) || $pgname == "") {
            $pgname = $this->botname;
        }
        $user = $this->core("player")->name($args[1]);
        if (strtolower($pgname) == strtolower($this->botname)) {
            $this->log("PGRP", "LEAVE", $user . " left privategroup.");
            if (!empty($this->commands["privateGroupLeave"])) {
                $keys = array_keys($this->commands["privateGroupLeave"]);
                foreach ($keys as $key) {
                    if ($this->commands["privateGroupLeave"][$key] != NULL) {
                        $this->commands["privateGroupLeave"][$key]->pgleave($user);
                    }
                }
            }
        }
        else {
            $this->log("PGRP", "LEAVE", $user . " left the exterior privategroup " . $pgname . ".");
            if (!empty($this->commands["extpgleave"])) {
                $keys = array_keys($this->commands["extpgleave"]);
                foreach ($keys as $key) {
                    if ($this->commands["extpgleave"][$key] != NULL) {
                        $this->commands["extpgleave"][$key]->extpgleave($pgname, $user);
                    }
                }
            }
        }
    }


    /*
    Message in privategroup
    */
    function incomingPrivateGroupMessage($args)
    {
        $pgname = $this->core("player")->name($args[0]);
        $user = $this->core("player")->name($args[1]);
        $found = FALSE;
        if (empty($pgname) || $pgname == "") {
            $pgname = $this->botname;
        }

        $dispgmsg = $this->core("settings")->get("Core", "DisablePGMSG");
        $dispgmsgchat = $this->core("settings")
            ->get("Core", "DisablePGMSGchat");
        if ($pgname == $this->botname && $dispgmsg && $dispgmsgchat) {
            return FALSE;
        }
        $args[2] = utf8_decode($args[2]);
        // Ignore bot chat, no need to handle it's own output as input again
        if (strtolower($this->botname) == strtolower($user)) {
            if ($this->core("settings")->get("Core", "LogPGOutput")) {
                $this->log(
                    "PGRP", "MSG", "[" . $this->core("player")
                    ->name($args[0]) . "] " . $user . ": " . $args[2]
                );
            }
            return;
        }
        else {
            $this->log(
                "PGRP", "MSG", "[" . $this->core("player")
                ->name($args[0]) . "] " . $user . ": " . $args[2]
            );
        }
        if (!isset($this->otherBots[$user])) {
            if (strtolower($pgname) == strtolower($this->botname)) {
                if (!$dispgmsg) {
                    $found = $this->handleCommandInput($user, $args[2], "sendToGroup");
                }
                if (!$dispgmsgchat) {
                    $found = $this->handToChat($found, $user, $args[2], "privateGroup");
                }
            }
            else {
                $found = $this->handleCommandInput($user, $args[2], "externalPrivateGroupMessage", $pgname);
                $found = $this->handToChat($found, $user, $args[2], "externalPrivateGroup", $pgname);
            }
            if ($this->command_error_text) {
                $this->sendPrivateGroup($this->command_error_text, $pgname);
            }
            unset($this->command_error_text);
        }
    }


    /*
    Incoming group announce
    */
    function incomingGroupAnnounce($args)
    {
        if ($args[2] == 32772 && $this->game == "ao") {
            $this->guildname = $args[1];
            $this->log("CORE", "INC_GANNOUNCE", "Detected org name as: $args[1]");
        }
    }


    /*
    * Incoming private group invite
    */
    function incomingPrivateGroupInvite($args)
    {
        $group = $this->core("player")->name($args[0]);
        if (!empty($this->commands["privateGroupInvite"])) {
            $keys = array_keys($this->commands["privateGroupInvite"]);
            foreach ($keys as $key) {
                if ($this->commands["privateGroupInvite"][$key] != NULL) {
                    $this->commands["privateGroupInvite"][$key]->pginvite($group);
                }
            }
        }
    }


    /*
    * Incoming group message (Guildchat, towers etc)
    */
    function incomingGroupMessage($args)
    {
        $found = FALSE;
        $group = $this->core("chat")->lookup_group($args[0]);
        if (!$group) {
            $group = $this->core("chat")->get_gname($args[0]);
        }
        $args[2] = utf8_decode($args[2]);
        if (isset($this->commands["groupMessage"][$group]) || $group == $this->guildname || ($this->game == "aoc" && $group == "~Guild")) {
            if ($this->game == "aoc" && $group == "~Guild") {
                $msg = "[" . $this->guildname . "] ";
            }
            else {
                $msg = "[" . $group . "] ";
            }
            if ($args[1] != 0) {
                $msg .= $this->core("player")->name($args[1]) . ": ";
            }
            $msg .= $args[2];
        }
        else {
            // If we dont have a hook active for the group, and its not guildchat... BAIL now before wasting cycles
            return FALSE;
        }
        $disgc = $this->core("settings")->get("Core", "DisableGC");
        $disgcchat = $this->core("settings")->get("Core", "DisableGCchat");
        if (($group == $this->guildname || ($this->game == "aoc" && $group == "~Guild")) && $disgc && $disgcchat) {
            Return FALSE;
        }
        if ($args[1] == 0) {
            $user = "0";
        }
        else {
            $user = $this->core("player")->name($args[1]);
        }
        // Ignore bot chat, no need to handle it's own output as input again
        if (strtolower($this->botname) == strtolower($user)) {
            if ($this->core("settings")->get("Core", "LogGCOutput")) {
                $this->log("GROUP", "MSG", $msg);
            }
            return;
        }
        else {
            $this->log("GROUP", "MSG", $msg);
        }
        if (!isset($this->otherBots[$user])) {
            if ($group == $this->guildname || ($this->game == "aoc" && $group == "~Guild")) {
                if (!$disgc) {
                    $found = $this->handleCommandInput($user, $args[2], "sendToGuildChat");
                }

                if ($this->command_error_text) {
                    $this->sendGuildChat($this->command_error_text);
                }
                unset($this->command_error_text);
            }

            if (!$disgcchat) {
                $found = $this->handToChat($found, $user, $args[2], "groupMessage", $group);
            }
        }
    }


    /*
    Does all the checks and work for a specific cron time
    */
    function cronJob($time, $duration)
    {
        if (($this->cronJobTimer[$duration] <= $time) && ($this->cronJobActive[$duration] == FALSE)) {
            if (!empty($this->cron[$duration])) {
                $this->cronJobActive[$duration] = TRUE;
                $crons = array_keys($this->cron[$duration]);
                for ($i = 0; $i < count($crons); $i++) {
                    if ($this->cron[$duration][$crons[$i]] != NULL) {
                        $this->cron[$duration][$crons[$i]]->cron($duration);
                    }
                }
            }
            $this->cronJobActive[$duration] = FALSE;
            $this->cronJobTimer[$duration] = time() + $duration;
        }
    }


    /*
    CronJobs of the bot
    */
    function cron()
    {
        if (!$this->cron_activated) {
            return;
        }
        $time = time();
        // Check timers:
        $this->core("timer")->check_timers();
        if (empty($this->cron)) {
            return;
        }
        foreach ($this->cronTimes as $interval) {
            $this->cronJob($time, $interval);
        }
    }


    /*
    Writes events to the console and log if logging is turned on.
    */
    function log($first, $second, $msg, $write_to_db = FALSE)
    {
        //Remove font tags
        $msg = preg_replace("/<font(.+)>/U", "", $msg);
        $msg = preg_replace("/<\/font>/U", "", $msg);
        //Remove color tags
        $msg = preg_replace("/##end##/U", "]", $msg);
        $msg = preg_replace("/##(.+)##/U", "[", $msg);
        //Change links to the text [link]...[/link]
        $msg = preg_replace("/<a href=\"(.+)\">/sU", "[link]", $msg);
        $msg = preg_replace("/<\/a>/U", "[/link]", $msg);
        // Change Encrypted Text to a Simple thing to say its encripted
        $msg = preg_replace('/gcr &\$encrypt\$& ([a-z0-9]+) ([a-z0-9]+) ([a-z0-9]+) /U', "gcr <Encryted Message>", $msg);
        $msg = preg_replace('/gcr &\$encrypt\$& ([a-z0-9]+) ([a-z0-9]+) ([a-z0-9]+)/', "gcr <Encryted Message>", $msg);
        $msg = $this->replaceStringTags($msg);
        if ($this->logTimestamp == 'date') {
            $timestamp = "[" . gmdate("Y-m-d") . "]\t";
        }
        elseif ($this->logTimestamp == 'time') {
            $timestamp = "[" . gmdate("H:i:s") . "]\t";
        }
        elseif ($this->logTimestamp == 'none') {
            $timestamp = "";
        }
        else {
            $timestamp = "[" . gmdate("Y-m-d H:i:s") . "]\t";
        }
        $line = $timestamp . "[" . $first . "]\t[" . $second . "]\t" . $msg . "\n";
        echo $this->botname . " " . $line;
        // We have a possible security related event.
        // Log to the security log and notify guildchat/pgroup.
        if (preg_match("/^security$/i", $second)) {
            if ($this->guildBot) {
                $this->sendGuildChat($line);
            }
            else {
                $this->sendPrivateGroup($line);
            }
            $log = fopen($this->logPath . "/security.txt", "a");
            fputs($log, $line);
            fclose($log);
        }
        if (($this->log == "all") || (($this->log == "chat") && (($first == "GROUP") || ($first == "TELL") || ($first == "PGRP")))) {
            $log = fopen($this->logPath . "/" . gmdate("Y-m-d") . ".txt", "a");
            fputs($log, $line);
            fclose($log);
        }
        if ($write_to_db) {
            $logmsg = substr($msg, 0, 500);
            $this->db->query(
                "INSERT INTO #___log_message (message, first, second, timestamp) VALUES ('" . mysql_real_escape_string($logmsg) . "','" . $first . "','" . $second . "','" . time()
                    . "')"
            );
        }
    }


    /*
    Cut msg into Size Small enough to Send
    */
    function cutSize($msg, $type, $to = "", $pri = 0)
    {
        if (strlen($msg) < 100000) {
            preg_match("/^(.*)<a href=\"(.+)\">(.*)$/isU", $msg, $info);
        }
        else {
            $var = explode("<a href=\"", $msg, 2);
            $var2 = explode("\">", $var[1], 2);
            $info[1] = $var[0];
            $info[2] = $var2[0];
            $info[3] = $var2[1];
        }
        $info[2] = str_replace("<br>", "\n", $info[2]);
        $content = explode("\n", $info[2]);
        $page = 0;
        $result[$page] = "";
        foreach ($content as $line) {
            if ((strlen($result[$page]) + strlen($line) + 12) < $this->maxSize) {
                $result[$page] .= $line . "\n";
            }
            else {
                $page++;
                $result[$page] = $line . "\n";
            }
        }
        $between = "";
        for ($i = 0; $i <= $page; $i++) {
            if ($i != 0) {
                $between = "text://";
            }
            $msg = $info[1] . "<a href=\"" . $between . $result[$i] . "\">" . $info[3] . " <font color=#ffffff>(page " . ($i + 1) . " of " . ($page + 1) . ")</font>";
            if ($type == "sendTell") {
                $this->sendTell($to, $msg, $pri, TRUE, FALSE);
            }
            else {
                if ($type == "pgroup") {
                    $this->sendPrivateGroup($msg, $to, FALSE);
                }
                else {
                    if ($type == "sendToGuildChat") {
                        $this->sendGuildChat($msg, $pri, FALSE);
                    }
                }
            }
        }
    }


    // Registers a new reference to a module, used to access the new module by other modules.
    public function registerModule(&$ref, $name)
    {
        if (isset($this->moduleLinks[strtolower($name)])) {
            $this->log(
                'CORE', 'ERROR',
                "Module '$name' has Already Been Registered by " . get_class($this->moduleLinks[strtolower($name)]) . " so cannot be registered by " . get_class($ref) . "."
            );
            return;
        }
        $this->moduleLinks[strtolower($name)] = &$ref;
    }


    // Unregisters a module link.
    public function unRegisterModule($name)
    {
        $this->moduleLinks[strtolower($name)] = NULL;
        unset($this->moduleLinks[strtolower($name)]);
    }


    public function existsModule($name)
    {
        $name = strtolower($name);
        Return (isset($this->moduleLinks[$name]));
    }


    // Returns the reference to the module registered under $name. Returns NULL if link is not registered.
    public function core($name)
    {
        if (isset($this->moduleLinks[strtolower($name)])) {
            return $this->moduleLinks[strtolower($name)];
        }
        $dummy = new BasePassiveModule($this, $name);
        $this->log('CORE', 'ERROR', "Module '$name' does not exist or is not loaded.");
        return $dummy;
    }


    /*
    * Interface to register and unRegister commands
    */
    public function registerCommand($channel, $command, &$module)
    {
        $channel = strtolower($channel);
        $command = strtolower($command);
        $allChannels = array(
            "sendToGuildChat",
            "sendTell",
            "sendToGroup"
        );
        if ($channel == "all") {
            foreach ($allChannels as $cnl) {
                $this->commands[$cnl][$command] = &$module;
            }
        }
        else {
            $this->commands[$channel][$command] = &$module;
        }
    }


    public function unRegisterCommand($channel, $command)
    {
        $channel = strtolower($channel);
        $command = strtolower($command);
        $allChannels = array(
            "sendToGuildChat",
            "sendTell",
            "sendToGroup"
        );
        if ($channel == "all") {
            foreach ($allChannels as $cnl) {
                $this->commands[$cnl][$command] = NULL;
                unset($this->commands[$cnl][$command]);
            }
        }
        else {
            $this->commands[$channel][$command] = NULL;
            unset($this->commands[$channel][$command]);
        }
    }


    public function existsCommand($channel, $command)
    {
        $channel = strtolower($channel);
        $command = strtolower($command);
        $exists = FALSE;
        $allChannels = array(
            "sendToGuildChat",
            "sendTell",
            "sendToGroup"
        );
        if ($channel == "all") {
            foreach ($allChannels as $cnl) {
                $exists = $exists & isset($this->commands[$cnl][$command]);
            }
        }
        else {
            $exists = isset($this->commands[$channel][$command]);
        }
        return $exists;
    }


    public function getAllCommands()
    {
        Return $this->commands;
    }


    public function getCommandHandler($channel, $command)
    {
        $channel = strtolower($channel);
        $command = strtolower($command);
        $handler = "";
        $allChannels = array(
            "sendToGuildChat",
            "sendTell",
            "sendToGroup"
        );
        if ($channel == "all") {
            $handlers = array();
            foreach ($allChannels as $cnl) {
                $handlers[] = get_class($this->commands[$cnl][$command]);
            }
            // FIXME: Borked
            $handler = implode(", ", $handles);
        }
        else {
            $handler = get_class($this->commands[$channel][$command]);
        }
        return $handler;
    }


    /*
    * Interface to register and unRegister commands
    */
    public function registerEvent($event, $target, &$module)
    {
        $event = strtolower($event);
        $events = array(
            'connect',
            'disconnect',
            'privategroupjoin',
            'privategroupinvite',
            'privategroupleave',
            'externalprivategroupjoin',
            'extpgleave',
            'cron',
            'settings',
            'timer',
            'logon_notify',
            'buddy',
            'privategroup',
            'groupmessage',
            'tells',
            'externalprivategroup',
            'irc'
        );
        if (in_array($event, $events)) {
            if ($event == 'groupmessage') {
                if ($target) {
                    $this->commands[$event][$target][get_class($module)] = &$module;
                    return FALSE;
                }
                else {
                    return "No channel specified for groupMessage. Not registering.";
                }
            }
            elseif ($event == 'cron') {
                $time = strtotime($target, 0);
                if ($time > 0) {
                    if (!isset($this->cronJobActive[$time])) {
                        $this->cronJobActive[$time] = FALSE;
                    }
                    if (!isset($this->cronJobTimer[$time])) {
                        $this->cronJobTimer[$time] = max(time(), $this->startupTime);
                    }
                    $this->cronTimes[$time] = $time;
                    $this->cron[$time][get_class($module)] = &$module;
                    return FALSE;
                }
                else {
                    return "Cron time '$target' is invalid. Not registering.";
                }
            }
            elseif ($event == 'timer') {
                if ($target) {
                    $this->core("timer")->registerCallback($target, $module);
                    return FALSE;
                }
                else {
                    return "No name for the timer callback given! Not registering.";
                }
            }
            elseif ($event == 'logon_notify') {
                $this->core("logon_notifies")->register($module);
                return FALSE;
            }
            elseif ($event == 'settings') {
                if (is_array($target) && isset($target['module']) && isset($target['setting'])) {
                    return $this->core("settings")
                        ->registerCallback($target['module'], $target['setting'], $module);
                }
                return "No module and/or setting defined, can't register!";
            }
            elseif ($event == 'irc') {
				$this->core("irc")->ircmsg[] = &$module;
				return FALSE;
			}
            else {
                $this->commands[$event][get_class($module)] = &$module;
                return FALSE;
            }
        }
        else {
            return "Event '$event' is invalid. Not registering.";
        }
    }


    public function unRegisterEvent($event, $target, &$module)
    {
        $event = strtolower($event);
        $events = array(
            'connect',
            'disconnect',
            'privateGroupJoin',
            'privateGroupInvite',
            'privateGroupLeave',
            'externalPrivateGroupJoin',
            'extpgleave',
            'cron',
            'settings',
            'timer',
            'logon_notify',
            'buddy',
            'privateGroup',
            'groupMessage',
            'tells',
            'externalPrivateGroup'
        );
        if (in_array($event, $events)) {
            if ($event == 'groupMessage') {
                if (isset($this->commands[$event][$target][get_class($module)])) {
                    $this->commands[$event][$target][get_class($module)] = NULL;
                    unset($this->commands[$event][$target][get_class($module)]);
                    return FALSE;
                }
                else {
                    return "GMSG $target is not registered or invalid!";
                }
            }
            elseif ($event == 'cron') {
                $time = strtotime($target, 0);
                if (isset($this->cron[$time][get_class($module)])) {
                    $this->cron[$time][get_class($module)] = NULL;
                    unset($this->cron[$time][get_class($module)]);
                    return FALSE;
                }
                else {
                    return "Cron time '$target' is not registered or invalid!";
                }
            }
            elseif ($event == 'timer') {
                return $this->core("timer")->unregisterCallback($target);
            }
            elseif ($event == 'logon_notify') {
                $this->core("logon_notifies")->unregister($module);
                return FALSE;
            }
            elseif ($event == 'settings') {
                if (is_array($target) && isset($target['module']) && isset($target['setting'])) {
                    return $this->core("settings")
                        ->unregisterCallback($target['module'], $target['setting'], $module);
                }
                return "No module and/or setting defined, can't unRegister!";
            }
            else {
                $this->commands[$event][get_class($module)] = NULL;
                unset($this->commands[$event][get_class($module)]);
                return FALSE;
            }
        }
        else {
            return "Event '$event' is invalid. Not registering.";
        }
    }


    function debugBackTrace()
    {
        $trace = debug_backtrace();
        $r = '';

        foreach ($trace as $i => $call) {
            if (is_object($call['object'])) {
                $call['object'] = 'CONVERTED OBJECT OF CLASS ' . get_class($call['object']);
            }

            if (is_array($call['args'])) {
                foreach ($call['args'] AS &$arg) {
                    if (is_object($arg)) {
                        $arg = 'CONVERTED OBJECT OF CLASS ' . get_class($arg);
                    }
                }
            }

            $r .= "#" . $i . " " . (isset($call['file']) ? $call['file'] : '') . '(' . (isset($call['line']) ? $call['line'] : '') . ') ';
            $r .= (!empty($call['object']) ? $call['object'] . $call['type'] : '');
            $r .= $call['function'] . '(' . implode(', ', $call['args']) . ')';
            $r .= "\n";
        }

        return $r;
    }

}

?>
