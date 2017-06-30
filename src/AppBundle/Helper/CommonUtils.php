<?php

namespace AppBundle\Helper;


class CommonUtils
{
    public static function trim($text) {
        $text = trim($text, " \t\n\r\0\x0B");
        while ($text[0] == $text[strlen($text) - 1] && ($text[0] == "\"" || $text[0] == "'")) {
            $text = trim(substr($text, 1, strlen($text) - 2), " \t\n\r\0\x0B");
        }

        return $text;
    }

    public static function filterIds($objects)
    {
        $ids = array();
        foreach ($objects as $item) {
            $ids[] = $item->getId();
        }

        return $ids;
    }

}