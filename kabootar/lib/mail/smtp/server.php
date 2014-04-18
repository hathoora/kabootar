<?php
namespace hathoora\kabootar\lib\mail\smtp
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

        public function __construct(SocketServerInterface $socket, array $config = null)
        {
            $this->socket = $socket;
            $this->config = $config;

            $this->socket->listen(
                                    $this->getConfig('port', 25),
                                    $this->getConfig('listenIP', 'localhost'));

            $this->socket->on('connection', function($conn)
            {
                $mail = new mail($conn);

                $mail->write(220, $this->getconfig('hostname') .' running Kabootar Mail Server version ' . $this->version);

                $mail->on('stream', function($mail)
                {
                    //echo '---> stream ' . $mail->getSessionId() . "\r\n";
                    $this->emit($mail->getSessionState(), array($mail));
                });

                $conn->on('data', array($mail, 'feed'));
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
           echo $message . "\r\n";
        }
    }
}