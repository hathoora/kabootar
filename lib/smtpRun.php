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

            $smtp->on('connection', function($conn)
            {
            });

            $smtp->on('EHLO', function($email)
            {
                $deferred = new Deferred();
                $email->answer($deferred->promise());

                $x = function($email) use($deferred)
                {
                    if (rand(1,5) < 3)
                        $deferred->resolve(array(array(200, 'success..')));
                    else
                        $deferred->reject(array(array(354, 'error')));
                };
                $x($email);
            });

            $smtp->on('RCPT', function($email)
            {
            });

            $smtp->on('close', function($email)
            {

            });

            $loop->run();
        }
    }
}