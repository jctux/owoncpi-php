<?php
// ETX and STX
date_default_timezone_set('America/Monterrey');
require dirname(__FILE__) . "/Sequence.php";

define('STX', chr(2));
define('ETX', chr(3));

class OwonCPI
{

    public $debug = true;

    public $ip = null;

    public $port = null;

    public $socket;

    public $sequence = null;

    /**
     * Session, and device parameters
     */
    public $session = null;

    public $mac = null;

    public $version = null;

    public $versionnum = null;

    public $utc0 = null;

    public $dst = null;

    public $timezone = null;

    public $area = null;

    public $chiptype = null;

    public $wifitype = null;

    public $deviceType = null;

    public static $JSON_FIELD_DESCRIPTION = "description";

    public static $JSON_FIELD_RESULT = "result";

    public static $JSON_FIELD_SEQUENCE = "sequence";

    public static $JSON_FIELD_TYPE = "type";

    public static $JSON_FIELD_SESSION = "session";

    public static $JSON_FIELD_COMMAND = "command";

    public static $JSON_FIELD_ARGUMENT = "argument";

    function __construct($ip = "192.168.1.1", $port = 11500)
    {
        $this->ip = $ip;
        $this->port = $port;
        /**
         * TODO: Revisar si php esta cargargado e implementar el logger desde php
         */

        require dirname(__FILE__) . "/Logger.php";
        $this->Logger = new Logger(null, 'stdout');

    }

    function __destruct()
    {
        if (is_resource($this->socket)) {
            $this->Logout();
            socket_close($this->socket);
        }
    }

    private function connect()
    {
        if (is_resource($this->socket))
            return true;
        try {
            // create socket
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($this->socket) {
                $this->Logger->d(__METHOD__, "socket_create() socket created");
            } else {
                $this->Logger->d(__METHOD__, "socket_create() error creating socket");
            }
            // connect to server
            if (socket_connect($this->socket, $this->ip, $this->port)) {
                $this->Logger->d(__METHOD__, "socket_connect() socket connected");
            } else {
                $$this->Logger->d(__METHOD__, "socket_connect() error connecting to server");
            }
        } catch (Exception $e) {
            $this->Logger->e(__METHOD__, $e->getMessage());
        }
    }

    private function send(array $data)
    {
        $message = STX . self::jsonEncode($data) . ETX;
        $message_len = strlen($message);
        if ($message_len > 250) {
            $this->Logger->e(__METHOD__, "CPIProtocol: send maximum length reached! (250)");
            return false;
        }
        $this->connect();
        try {
            // send string to server
            if (socket_write($this->socket, $message, $message_len)) {
                $this->Logger->d(__METHOD__, "socket_write() message sent: $message");
            } else {
                throw new Exception('socket_write(), could not send data to server');
            }
            // get server response
            $response = socket_read($this->socket, 2048);
            if ($response) {
                // remove STX y ETX
                $this->Logger->d(__METHOD__, "socket_read() message received: " . self::jsonEncode(self::jsonDecode(substr($response, 1, - 1))));
                return self::jsonDecode(substr($response, 1, - 1), true);
            } else {
                $extra = socket_strerror(socket_last_error($this->socket));
                $this->Logger->d(__METHOD__, "socket_read() " . $extra);
                return false;
            }
        } catch (Exception $e) {
            $this->Logger->e(__METHOD__, $e->getMessage());
        }
        return false;
    }

    private static function jsonEncode($data)
    {
        return json_encode($data);
    }

    private static function jsonDecode($json, $toAssoc = false)
    {
        $result = json_decode($json, $toAssoc);
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                $error = ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_NONE:
            default:
                $error = '';
        }
        if (! empty($error))
            throw new Exception('JSON Error: ' . $error);

        return $result;
    }

    public function Login($username = "owon", $password = "123456")
    {
        $data_struct = array(
            'type' => 'login',
            'argument' => array(
                'username' => $username,
                'password' => $password
            )
        );
        $response = $this->send($data_struct);

        /**
         * [response] => Array (
         * [session] => im0en9ilj2wad5t
         * [mac] => 00606EFFFEA4CA92
         * [version] => X3_HA_V2.2.2_20160428
         * [versionnum] => 20202
         * [utc0] => 516995885
         * [dst] =>
         * [timezone] => 0
         * [area] =>
         * [chiptype] => 103_VG
         * [wifitype] => 1011
         * [deviceType] => 1 )
         */
        if (empty($response['response']['session']))
            throw new Exception('CPIProtocol Error: Login Failed ' . $response['response']['description']);

        $this->session = $response['response']['session'];

        if (! empty($response['response']['mac']))
            $this->mac = $response['response']['mac'];

        if (! empty($response['response']['version']))
            $this->version = $response['response']['version'];

        if (! empty($response['response']['versionnum']))
            $this->versionnum = $response['response']['versionnum'];

        if (! empty($response['response']['utc0']))
            $this->utc0 = $response['response']['utc0'];

        if (! empty($response['response']['dst']))
            $this->dst = $response['response']['dst'];

        if (! empty($response['response']['timezone']))
            $this->timezone = $response['response']['timezone'];

        if (! empty($response['response']['area']))
            $this->area = $response['response']['area'];

        if (! empty($response['response']['chiptype']))
            $this->chiptype = $response['response']['chiptype'];

        if (! empty($response['response']['wifitype']))
            $this->wifitype = $response['response']['wifitype'];

        if (! empty($response['response']['deviceType']))
            $this->deviceType = $response['response']['deviceType'];

        $this->Logger->d(__METHOD__, 'X3 Session: ' . $this->session);
        $this->Logger->d(__METHOD__, 'X3 Mac: ' . $this->mac);
        $this->Logger->d(__METHOD__, 'X3 Version: ' . $this->version);
        $this->Logger->d(__METHOD__, 'X3 VersionNum: ' . $this->versionnum);
        $this->Logger->d(__METHOD__, 'X3 Utc0: ' . $this->utc0);
        $this->Logger->d(__METHOD__, 'X3 Dst: ' . $this->dst);
        $this->Logger->d(__METHOD__, 'X3 TimeZone: ' . $this->timezone);
        $this->Logger->d(__METHOD__, 'X3 Area: ' . $this->area);
        $this->Logger->d(__METHOD__, 'X3 ChipType: ' . $this->chiptype);
        $this->Logger->d(__METHOD__, 'X3 WifiType: ' . $this->wifitype);
        $this->Logger->d(__METHOD__, 'X3 DeviceType: ' . $this->deviceType);
    }

    public function LoginWMAC($username, $password, $mac)
    {
        $data_struct = array(
            'type' => 'login',
            'argument' => array(
                'username' => $username,
                'password' => $password,
                'mac' => $mac
            )
        );
        $response = $this->send($data_struct);

        /**
         * [response] => Array (
         * [session] => im0en9ilj2wad5t
         * [mac] => 00606EFFFEA4CA92
         * [version] => X3_HA_V2.2.2_20160428
         * [versionnum] => 20202
         * [utc0] => 516995885
         * [dst] =>
         * [timezone] => 0
         * [area] =>
         * [chiptype] => 103_VG
         * [wifitype] => 1011
         * [deviceType] => 1 )
         */
        if (empty($response['response']['session']))
            throw new Exception('CPIProtocol Error: Login Failed ' . $response['response']['description']);

        $this->session = $response['response']['session'];

        if (empty($response['response']['mac']))
            $this->mac = $response['response']['mac'];

        if (empty($response['response']['version']))
            $this->version = $response['response']['version'];

        if (empty($response['response']['versionnum']))
            $this->versionnum = $response['response']['versionnum'];

        if (empty($response['response']['utc0']))
            $this->utc0 = $response['response']['utc0'];

        if (empty($response['response']['dst']))
            $this->dst = $response['response']['dst'];

        if (empty($response['response']['timezone']))
            $this->timezone = $response['response']['timezone'];

        if (empty($response['response']['area']))
            $this->area = $response['response']['area'];

        if (empty($response['response']['chiptype']))
            $this->chiptype = $response['response']['chiptype'];

        if (empty($response['response']['wifitype']))
            $this->wifitype = $response['response']['wifitype'];

        if (empty($response['response']['deviceType']))
            $this->deviceType = $response['response']['deviceType'];
    }

    public function Logout()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'logout',
            self::$JSON_FIELD_SESSION => $this->session
        );
        $response = $this->send($data_struct);
    }

    public function GetTimeFromSegX3()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'system',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'getTime',
            self::$JSON_FIELD_SEQUENCE => Sequence::$GetTimeFromSegX3
        );
        $response = $this->send($data_struct);
    }

    public function WIFIReset()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'reset',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIReset
        );
        $response = $this->send($data_struct);
    }

    public function WIFIScan()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'environment',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIScan
        );
        $response = $this->send($data_struct);
    }

    public function WIFIConfigSTA($ssid, $sskey)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'sta',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIConfigSTA,
            self::$JSON_FIELD_ARGUMENT => array(
                "ssid" => $ssid,
                "sskey" => $sskey
            )
        );
        $response = $this->send($data_struct);
    }

    public function WIFIQuerySTA()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'sta',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIQuerySTA,
            self::$JSON_FIELD_ARGUMENT => array()
        );
        $response = $this->send($data_struct);
    }

    public function WIFIConfigAP($ssid, $sskey)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'ap',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIConfigAP,
            self::$JSON_FIELD_ARGUMENT => array(
                "ssid" => $ssid,
                "sskey" => $sskey
            )
        );
        $response = $this->send($data_struct);
    }

    public function WIFIQueryAP()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'ap',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIQueryAP,
            self::$JSON_FIELD_ARGUMENT => array()
        );
        $response = $this->send($data_struct);
    }

    public function WIFIQueryMode()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'mode',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIQueryMode
        );
        $response = $this->send($data_struct);
    }

    public function WIFISetMode($mode)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'wifiConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'mode',
            self::$JSON_FIELD_SEQUENCE => Sequence::$WIFIQueryMode,
            self::$JSON_FIELD_ARGUMENT => array(
                'mode' => $mode
            )
        );
        $response = $this->send($data_struct);
    }

    public function NetworkSetupMode($mode)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'netConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'netMode',
            self::$JSON_FIELD_SEQUENCE => Sequence::$NetworkSetupMode,
            self::$JSON_FIELD_ARGUMENT => array(
                'netMode' => $mode
            )
        );
        $response = $this->send($data_struct);
    }

    public function NetworkQueryCurrentDomainName()
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'netConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'config',
            self::$JSON_FIELD_SEQUENCE => Sequence::$NetworkQueryCurrentDomainName
        );
        $response = $this->send($data_struct);
    }

    public function NetworkSetupDomainName($hostname, $ip, $port, $sslport)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'netConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'config',
            self::$JSON_FIELD_SEQUENCE => Sequence::$NetworkSetupDomainName,
            self::$JSON_FIELD_ARGUMENT => array(
                'web' => $hostname,
                'portNum' => $port,
                'sslPortNum' => $sslport,
                'ipAddr' => $ip
            )
        );
        $response = $this->send($data_struct);
    }

    public function NetworkRegisterDevice($username, $password)
    {

        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'netConfig',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'register',
            self::$JSON_FIELD_SEQUENCE => Sequence::$NetworkRegisterDevice,
            self::$JSON_FIELD_ARGUMENT => array(
                'username' => $username,
                'password' => $password
            )
        );
        $response = $this->send($data_struct);

    }

    public static function X3Scanning($ip)
    {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_bind($sock, '0.0.0.0', 22154);

        $data = array(
            'ip' => '192.168.1.2',
            'type' => 'scan',
            'port' => "22154"
        );
        $message = STX . self::jsonEncode($data) . ETX;
        $message_len = strlen($message);

        echo "Sending message: " . $message;
        echo "Sending message lenght: " . $message_len;

        socket_sendto($sock, $message, $message_len, 0, '231.0.1.1', 21555);

        $rb = null;
        $rbuf = null;
        $buffer = null;
        $rbuf_started = false;
        $rbuf_ended = false;

        while (true) {
            usleep(50);
            $rb = socket_recv($sock, $buffer, 1, MSG_WAITALL);
            $sckt_last_error = socket_last_error($sock);
            if ($sckt_last_error != 11 && $sckt_last_error > 0)
                $continue = false;

            if ($rb === false && $sckt_last_error != 11)
                break;

        }
        socket_close($sock);
        echo $rbuf;
        return $rbuf;
    }

    public function X3Register($hostname, $ip, $port, $sslport)
    {
        if (empty($this->session))
            $this->Login();

        $data_struct = array(
            self::$JSON_FIELD_TYPE => 'server',
            self::$JSON_FIELD_SESSION => $this->session,
            self::$JSON_FIELD_COMMAND => 'setreg',
            self::$JSON_FIELD_SEQUENCE => Sequence::$NetworkSetupDomainName,
            self::$JSON_FIELD_ARGUMENT => array(
                'username' => $hostname,
                'pwd' => $port,
                'mac' => $sslport
            )
        );
        $response = $this->send($data_struct);
    }
}
