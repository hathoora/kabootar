<?php
namespace hathoora\kabootar\lib\smtp
{
    use Evenement\EventEmitter,
        React\EventLoop\LoopInterface,
        React\Socket\Server as SocketServer;

    /** @event request */
    class server extends EventEmitter implements serverInterface
    {
        /**
         * @var \React\EventLoop\LoopInterface
         */
        private $loop;

        /**
         * @var \React\Socket\ServerInterface
         */
        private $socket;
        private $config;
        private $version = 0.1;
        private $arrClientConnections = array();

        public function __construct(LoopInterface $loop, array $config = array())
        {
            $this->loop = $loop;
            $this->socket = new SocketServer($this->loop);

            // default configs
            if (!isset($config['port']))
                $config['port'] = 25;
            if (!isset($config['listenIP']))
                $config['listenIP'] = 'localhost';
            if (!isset($config['hostname']))
                $config['hostname'] = 'Kabootar Mail';
            if (!isset($config['maxMailSize']))
                $config['maxMailSize'] = 35882577;
            if (!isset($config['maxIdleTime']))
                $config['maxIdleTime'] = 20;
            if (!isset($config['maxClientErrors']))
                $config['maxClientErrors'] = 15;

            $this->config = $config;
            $this->socket->listen(
                                    $this->getConfig('port'),
                                    $this->getConfig('listenIP'));

            $this->socket->on('connection', function($conn)
            {
                $client = new client($conn, $this->config);
                $sessionid = $client->getSessionId();

                // @var \React\EventLoop\Timer\TimerInterface
                $emailConnectionTimeoutTimer =  $this->loop->addPeriodicTimer(10, function() use($client)
                {
                    $time = time();
                    $idle = (time() - $client->timeLastActivityAt);
                    echo "Connection timeout check for ". $client->getSessionId() .": idle for $idle secs \n";
                    if ($idle > $this->getConfig('maxIdleTime'))
                        $client->closeTimeout();
                });

                $this->arrClientConnections[$sessionid] = array(
                                                                'emailConnection' => $client,
                                                                'timeoutTimer' => $emailConnectionTimeoutTimer);



                $this->emit('connection', array($client));

                $client->on('stream', function($client)
                {
                    $this->emit($client->getSessionState(), array($client));
                });



                $conn->on('data', function($data) use($client)
                {
                    $client->feed($data);
                });



                // we need to add some wait before killing the connection to flush out all the messages to client
                // so "close-delay" was emitted
                $client->on('close-delay', function($conn)
                {
                    $this->loop->addTimer(rand(1,2), function() use($conn)
                    {
                       $conn->close();
                    });
                });


                $conn->on('close', function($conn) use ($client)
                {
                    echo '-------------------' . "\n" . $client->getEmailBag()->getRaw() . "\n" . '-------------------';
                    $client->close();
                    $sessionid = $client->getSessionId();
                    if (isset($this->arrClientConnections[$sessionid]))
                    {
                        // clear timeout connection timer
                        if (isset($this->arrClientConnections[$sessionid]['timeoutTimer']))
                            $this->loop->cancelTimer($this->arrClientConnections[$sessionid]['timeoutTimer']);

                        unset($this->arrClientConnections[$sessionid]);
                    }

                    $this->emit('close', array($client));
                });
            });

            $this->loop->addPeriodicTimer(50, function ()
            {
                $memory = memory_get_usage() / 1024;
                $formatted = number_format($memory, 3).'K';
                echo ' -> ' . "Current memory usage: {$formatted}\n";

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