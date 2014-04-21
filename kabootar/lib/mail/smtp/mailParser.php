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

        /**
         * Returns true when an email address is valid
         */
        public static function validateEmailAddress($email)
        {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        }
    }
}