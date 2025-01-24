<?php

if (!function_exists('dd_var')) {
    function dd_var($data, bool $die = true) 
    {
        echo "<pre>", var_dump($data), "</pre>";
        return ($die) ? die() : null;
    }
}

if (!function_exists('dd_to_log')) {
    function dd_to_log($data, $filename = 'log.txt')
    {
      $content = print_r($data, true);
      $filepath = $_SERVER['DOCUMENT_ROOT'] . '/logs/' . $filename;
      error_log($content, 3, $filepath);
    }
}