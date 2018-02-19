<?php

namespace AppBundle\Helper;


class CommonUtils
{
    /**
     * Trim a text of all useless characters.
     *
     * @param $text
     * @return string
     */
    public static function trim($text)
    {
        if (empty($text)) return $text;

        $text = trim($text, " \t\n\r\0\x0B");
        while ($text[0] == $text[strlen($text) - 1] && ($text[0] == "\"" || $text[0] == "'")) {
            $text = trim(substr($text, 1, strlen($text) - 2), " \t\n\r\0\x0B");
        }

        return $text;
    }

    /**
     * Trim a text of all spaces.
     *
     * @param $text
     * @return string
     */
    public static function simpleTrim($text)
    {
        if (empty($text)) return $text;

        $text = trim($text, " \t\n\r\0\x0B");

        return $text;
    }

    /**
     * Filter object by a list of ids.
     *
     * @param $objects
     * @return array
     */
    public static function filterIds($objects)
    {
        $ids = array();
        foreach ($objects as $item) {
            $ids[] = $item->getId();
        }

        return $ids;
    }

    /**
     * Generate a temporary file name with the given extension.
     *
     * @param String $extension
     * @return string
     */
    public static function generateTempFileName($extension)
    {
        return uniqid("temp_") . "." . $extension;
    }

    /**
     * Generate a file name with the given extension.
     *
     * @param String $extension
     * @return string
     */
    public static function generateFileName($extension)
    {
        return uniqid() . "." . $extension;
    }

}