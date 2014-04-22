<?php
namespace hathoora\kabootar\lib\email\smtp
{
    use Evenement\EventEmitter,
        React\Socket\ServerInterface as SocketServerInterface;

    /** @event request */
    class server extends EventEmitter implements serverInterface
    {
        /**
         * @var \React\Socket\ServerInterface
         */
        private $socket;
        private $config;
        private $version = 0.1;
        private $arrEmailConnections = array();

        public function __construct(SocketServerInterface $socket, array $config = array())
        {
            $this->socket = $socket;

            // default configs
            if (!isset($config['port']))
                $config['port'] = 25;
            if (!isset($config['listenIP']))
                $config['listenIP'] = 'localhost';
            if (!isset($config['hostname']))
                $config['hostname'] = 'kabootar mail';
            if (!isset($config['maxMailSize']))
                $config['maxMailSize'] = 35882577;

            $this->config = $config;
            $this->socket->listen(
                                    $this->getConfig('port'),
                                    $this->getConfig('listenIP'));

            $this->socket->on('connection', function($conn)
            {
                $emailConnection = new emailConnection($conn);
                $sessionid = $emailConnection->getSessionId();

                $this->arrEmailConnections[$sessionid] = array(
                                                                'totalCommands' => 0,
                                                                'timestamp' => time(),
                                                                'emailConnection' => &$emailConnection);

                $emailConnection->respond(220, $this->getconfig('hostname') .' Kabootar Mail Server v' . $this->version);

                $emailConnection->on('stream', function($email, $secondaryObject)
                {
                    $command = $email->getCommand();
                    if ($command == 'MAIL' || $command == 'RCPT')
                        $this->emit($email->getCommand(), array($email, $secondaryObject));
                    else
                        $this->emit($email->getCommand(), array($email));
                });

                $conn->on('data', function($data) use($emailConnection)
                {
                    $sessionid = $emailConnection->getSessionId();
                    if (isset($this->arrEmailConnections[$sessionid]))
                    {
                        if (!isset($this->arrEmailConnections[$sessionid]['totalCommands']))
                            $this->arrEmailConnections[$sessionid]['totalCommands'] = 0;

                        $this->arrEmailConnections[$sessionid]['totalCommands']++;
                    }

                    $emailConnection->feed($data);
                });

                $conn->on('close', function($conn) use ($emailConnection)
                {
                    $emailConnection->close();
                    $sessionid = $emailConnection->getSessionId();
                    if (isset($this->arrEmailConnections[$sessionid]))
                        unset($this->arrEmailConnections[$sessionid]);
                });
            });
        }

        /**
         * Get config
         */
        public function getConfig($key, $defaultValue = null)
        {
            $value = $defaultValue;

            if (array_key_exists($key, $this->config))
                $value = $this->config[$key];

            return $value;
        }




        ################################################
        ##
        ##      Helper functions
        ##

        /**
         * Log level
         *
         * @param $message
         * @param string $level
         */
        private function log($message, $level = 'debug')
        {
           //echo '--->' . $message . "\r\n";
        }
    }
}