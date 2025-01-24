<?php
namespace KPLab\API\V2\Helpers;

class ControllerGenerator
{
    const MODULE_ID = 'kplab.api.v2';

    public static function generateControllerAndMethod($controllerName, $methodName)
    {
        $moduleDir = "/bitrix/modules/kplab.api.v2";
        if (!file_exists($moduleDir)) {
            $moduleDir = "/local/modules/kplab.api.v2";
        }

        // Путь к директории контроллеров
        $controllerDir = $moduleDir.'/controller/';

        // Проверяем наличие директории, если её нет — создаём
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0775, true);
        }

        // Имя файла контроллера
        $controllerFile = $controllerDir . $controllerName . '.php';

        // Если файл контроллера уже существует, открываем его, иначе создаем новый
        if (file_exists($controllerFile)) {
            $fileContent = file_get_contents($controllerFile);
            $classExists = strpos($fileContent, "class $controllerName");
        } else {
            // Создаем содержимое файла, если он не существует
            $fileContent = "<?php\n\n";
            $fileContent .= "namespace KPLab\\API\\V2\\Controller;\n\n";
            $fileContent .= "\\Bitrix\\Main\\Loader::includeModule('kplab.api.v2');\n\n";
            $fileContent .= "class $controllerName extends \\Bitrix\\Main\\Engine\\Controller\n{";
            $fileContent .= "\n    public function getDefaultPreFilters()\n    {";
            $fileContent .= "\n        return [";
            $fileContent .= "\n            new \\KPLab\\API\\V2\\Controller\\ActionFilter\\Authentication(),\n";
            $fileContent .= "\n        ];\n    }\n";
            $fileContent .= "\n    public function getDefaultPostFilters()\n    {\n        return array();\n    }\n";
            $fileContent .= "\n    protected function prepareParams()\n    {\n        return parent ::prepareParams();\n    }\n";
            $fileContent .= "}\n";
            $classExists = false;
        }

        // Проверяем, существует ли уже этот метод в контроллере
        if (strpos($fileContent, "public function $methodName") === false) {
            // Генерируем содержание метода
            $methodContent = "\n    public function $methodName(array \$params = [])\n    {\n        // TODO: Реализуйте логику $methodName\n    }\n";

            // Находим позицию закрывающей фигурной скобки класса
            $closingBracketPos = strrpos($fileContent, "}");

            // Вставляем метод перед последней закрывающей скобкой класса
            $fileContent = substr_replace($fileContent, $methodContent, $closingBracketPos, 0);

            // Записываем обновленное содержимое обратно в файл
            file_put_contents($controllerFile, $fileContent);
        }

        // Регистрируем класс в автозагрузке Bitrix
        \Bitrix\Main\Loader::registerAutoLoadClasses(null, [
            "KPLab\\API\\V2\\Controller\\$controllerName" => $controllerFile
        ]);
    }
}
