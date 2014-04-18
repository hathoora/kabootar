<?php
namespace hathoora\kabootar\lib\mail\smtp
{
    /**
     * mail parsing class
     */
    class mailParser
    {
        /**
         * Returns string with line ending
         *
         * @param $str
         * @param string $lineEnd
         */
        public static function messageln($str, $lineEnd = "\r\n")
        {
            return $str . $lineEnd;
        }
    }
}