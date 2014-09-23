<?php
namespace hathoora\kabootar\lib
{
    use React\Promise\Deferred;

    class smtpRun
    {
        public static function run()
        {
            $loop = \React\EventLoop\Factory::create();

            $arrConfig = array(
                'port' => 25,
                'listenIP' => '0.0.0.0',
                'hostname' => 'hathoora.org');

            $smtp = new \hathoora\kabootar\lib\smtp\server($loop, $arrConfig);

            $smtp->on('connection', function($client)
            {
            });

            $smtp->on('RCPT', function($client)
            {
            });

            $smtp->on('close', function($client)
            {

            });

            $loop->run();
        }
    }
}