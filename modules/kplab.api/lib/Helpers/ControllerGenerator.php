<?php
namespace KPLab\API\V2\Helpers;

class ControllerGenerator
{
    const MODULE_ID = 'kplab.api';
    const CONTROLLER_NAMESPACE = 'KPLab\\API\\V2\\Controller';

    public static function generateControllerAndMethod($controllerName, $methodName)
    {
        // Валидация имён
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $controllerName) || !preg_match('/^[a-zA-Z0-9_]+$/', $methodName)) {
            throw new \InvalidArgumentException('Invalid controller or method name');
        }

        $controllerDir = __DIR__ . '/../controller/';
        $controllerFile = $controllerDir . $controllerName . '.php';

        // Создаём директорию, если её нет
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        // Если файла нет — создаём базовый класс
        if (!file_exists($controllerFile)) {
            $fileContent = "<?php\n\n"
                . "namespace " . self::CONTROLLER_NAMESPACE . ";\n\n"
                . "use Bitrix\\Main\\Engine\\Controller;\n"
                . "use KPLab\\API\\V2\\Controller\\ActionFilter\\Authentication;\n\n"
                . "class $controllerName extends Controller\n"
                . "{\n"
                . "    public function getDefaultPreFilters()\n"
                . "    {\n"
                . "        return [new Authentication()];\n"
                . "    }\n"
                . "}\n";

            file_put_contents($controllerFile, $fileContent, LOCK_EX);
        }

        // Проверяем, есть ли метод в файле
        $fileContent = file_get_contents($controllerFile);
        if (strpos($fileContent, "function $methodName") === false) {
            $closingBracketPos = strrpos($fileContent, "}");
            $methodContent = "\n    public function $methodName()\n    {\n        // TODO: Implement logic\n    }\n";
            $fileContent = substr_replace($fileContent, $methodContent, $closingBracketPos, 0);
            file_put_contents($controllerFile, $fileContent, LOCK_EX);
        }
    }
}