<?php
namespace kabootar\controller
{
    use hathoora\controller\controller;

    class defaultController extends controller
    {
        public function mail()
        {
            ini_set('max_execution_time', 0);
            $loop = \React\EventLoop\Factory::create();
            $socket = new \React\Socket\Server($loop);


            $socket->on('error', function ($conn) 
            {
                echo date('Y-m-d') . ' -> '. $conn->getRemoteAddress() .' has error.' . "\r\n";
            });
            
            $socket->on('ended', function ($conn) 
            {
                echo date('Y-m-d') . ' -> '. $conn->getRemoteAddress() .' has ended.' . "\r\n";
            });

            $socket->on('close', function ($conn) 
            {
                echo date('Y-m-d') . ' -> '. $conn->getRemoteAddress() .' has closed.' . "\r\n";
            });
            
            $socket->on('connection', function ($conn) 
            {
                echo date('Y-m-d') . ' -> '. $conn->getRemoteAddress() .' has connected.' . "\r\n";
                $conn->write('220 KabotarMail Server' . "\r\n");

                // support for RSET @ http://cr.yp.to/smtp/helo.html
                
                $conn->on('data', function($data) use ($conn) 
                {
                    echo '--->' . $data . "<-----------------\r\n";
                    
                    // EHLO
                    if (preg_match('/^(EHLO|HELO)/i', $data))
                    {
                        $conn->write('250-KabotarMail at your service' . "\r\n");
                        $conn->write('250-SIZE 35882577' . "\r\n");
                        $conn->write('250-8BITMIME' . "\r\n");
                        #$conn->write('250-STARTTLS' . "\r\n");
                        // https://tools.ietf.org/html/rfc2034
                        $conn->write('250-ENHANCEDSTATUSCODES' . "\r\n");
                        $conn->write('250 CHUNKING' . "\r\n");   
                        echo "Hell Yeah Hello \r\n";
                    }
                    else if (preg_match('/^MAIL/i', $data))
                    {
                        $resp = '250 2.1.0 MAIL ok' . "\r\n";
                        $conn->write($resp);
                        echo $resp;
                    }
                    else if (preg_match('/^RCPT/i', $data))
                    {
                        $resp = '250 2.1.5 <support@port25.com> ok' . "\r\n";
                        $conn->write($resp);
                        echo $resp;
                    }                    
                    else if (preg_match('/^DATA/i', $data))
                    {
                        $resp = '354 send message' . "\r\n";
                        $conn->write($resp);
                        echo $resp;
                    }                    
                    else if (preg_match('/\r\n.\r\n$/', $data))
                    {
                        $resp = '250 2.6.0 message received' . "\r\n";
                        $conn->write($resp);
                        echo $resp;
                    }
                    else if (preg_match('/^QUIT/i', $data))
                    {
                        $resp = '221 2.0.0 mail.port25.com says goodbye' . "\r\n";
                        $conn->write($resp);
                        echo $resp;
                    }                    
                });
            });


            $socket->listen(25, '0.0.0.0');
            
            $loop->addPeriodicTimer(50, function () 
            {
                $memory = memory_get_usage() / 1024;
                $formatted = number_format($memory, 3).'K';
                echo date('Y-m-d') . ' -> ' . "Current memory usage: {$formatted}\n";
            });            
            $loop->run();
        }
        