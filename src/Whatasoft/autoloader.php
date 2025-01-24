<?php

// PSR-4 Autoloader
if (defined('SERVER_DOCUMENT_ROOT')) {
    spl_autoload_register(function($class) {
        // Префикс namespace для проекта
        $prefix = 'Whatasoft\\';
        // Базовая директория поиска классов
        $baseDir = SERVER_DOCUMENT_ROOT . "/local/src/Whatasoft";
        $len = strlen($prefix);

        // Проверка на соответсвие подключаемого класса нашему проекту
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Получаем имя класса без префикса проекта
        $relativeClass = substr($class, $len);
        // Формируем путь до файла нашего класса
        $relativeClassFilePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $file = $baseDir . DIRECTORY_SEPARATOR . $relativeClassFilePath;

        // Подключаем файл, если он существует
        if (is_file($file)) {
            require_once $file;
        }
    });
}

