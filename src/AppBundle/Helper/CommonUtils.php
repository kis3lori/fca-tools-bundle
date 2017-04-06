<?php

namespace AppBundle\Helper;


class CommonUtils
{
    public static function trim($text) {
        return trim($text, " '\"\t\n\r\0\x0B");
    }
}