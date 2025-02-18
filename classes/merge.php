<?php

use Bitrix\Main\Loader;

define("STOP_STATISTICS", true);
define("NO_KEEP_STATISTIC", 'Y');
define("NO_AGENT_STATISTIC",'Y');
define("NO_AGENT_CHECK", true);
define("DisableEventsCheck", true);
Loader::includeModule('main');
Loader::includeModule("crm");
require_once $_SERVER["DOCUMENT_ROOT"]. '/local/vendor/autoload.php';

/*Для использования обязательно должен быть установлен Ghostscript
 * если при проверке gs --version ничего не покажет, тогда установить
 * yum install ghostscript -y
 * */
class MergePDF
{
    public function __construct()
    {

    }

    /**
     * @throws \setasign\Fpdi\PdfParser\Type\PdfTypeException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     */
    public function init(int $entityTypeID = 4, int $entityID = 43019) {

        $soglSign = "UF_CRM_UNSIGNED_CONSENT";
        $soglEDO = "UF_CRM_1733311830";
        $passport = "UF_CRM_6433D94467769";

        $targetFieldCode = "UF_CRM_1734360408847"; //UF_CRM_1734361075459
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID);
        $item = $factory->getItem($entityID);
        $itemData = $item->getData();
        $title = "";
        if($entityTypeID == 4) $title = $item->getTitle();
        if($entityTypeID == 3) $title = $itemData['FULL_NAME'];
        //print_r($itemData);

        $files = [];
        $soglEDOFiles = $itemData[$soglEDO];
        $soglSignFiles = $itemData[$soglSign];
        $passportFiles = $itemData[$passport];

        //array_merge($files,$files1);
        //array_merge($files,$files2);

        $newFiles = [];
        $mpdf = new \Mpdf\Mpdf();

        $originalFileName = pathinfo("Согласие на запрос КИ ".$title, PATHINFO_FILENAME);
        $uploadFilePath = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $originalFileName . '.pdf';

        if ($itemData[$soglSign]) {
            if (is_array($itemData[$soglSign])) {
                foreach ($itemData[$soglSign] as $fileId) {
                    $fileArray = \CFile::GetFileArray($fileId);
                    $filename = $fileArray['FILE_NAME'];
                    $random = rand(1,10000);
                    if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                    {
                        $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compress-'.$filename;

                        $filepdf = fopen($srcfile,"r");
                        if($filepdf) {
                            $line_first = fgets($filepdf);
                            fclose($filepdf);
                        }
                        else{
                            echo "error opening the file."."\n";
                        }

                        preg_match_all('!\d+!', $line_first, $matches);
                        $pdfversion = implode('.', $matches[0]);
                        if($pdfversion > "1.4"){
                            shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            //shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                        }
                        else{
                            //shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                        }
                        try {
                            echo "Путь к файлу: $srcfile_new\n";
                            $pagecount = $mpdf->SetSourceFile($srcfile_new);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                        } catch (\Mpdf\MpdfException $e) {
                            echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                            exit;
                        }

                    }
                }
            }
            else {
                $fileArray = \CFile::GetFileArray($itemData[$soglSign]);
                $filename = $fileArray['FILE_NAME'];
                $random = rand(1,10000);
                if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                {
                    $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/local/tmp/' . $filename;
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/local/tmp/encrypted-'.$random.'-'.$filename;
                    $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/local/tmp/compress-'.$filename;

                    $filepdf = fopen($srcfile,"r");
                    if($filepdf) {
                        $line_first = fgets($filepdf);
                        fclose($filepdf);
                    }
                    else{
                        echo "error opening the file."."\n";
                    }

                    preg_match_all('!\d+!', $line_first, $matches);
                    $pdfversion = implode('.', $matches[0]);
                    if($pdfversion > "1.4"){
                        shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        //shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                    }
                    else{
                        //shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                    }
                    try {
                        echo "Путь к файлу: $srcfile_new\n";
                        $pagecount = $mpdf->SetSourceFile($srcfile_new);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                    } catch (\Mpdf\MpdfException $e) {
                        echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                        exit;
                    }

                }
            }

        }
        // Удаление временных файлов
        $filesToDelete = [
            $srcfile_new,             // Временный сконвертированный файл
            $dest_file,               // Временный зашифрованный файл
            $srcfile_new_compress     // Временный сжатый файл
        ];

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "Удален временный файл: $file\n";
            }
        }
        echo "\n";
        if ($itemData[$soglEDO])
        {
            if (is_array($itemData[$soglEDO]))
            {
                foreach ($itemData[$soglEDO] as $fileId)
                {
                    $fileArray = \CFile::GetFileArray($fileId);
                    $filename = $fileArray['FILE_NAME'];
                    $random = rand(1,10000);
                    if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                    {
                        $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compress-'.$filename;

                        $filepdf = fopen($srcfile,"r");
                        if($filepdf) {
                            $line_first = fgets($filepdf);
                            fclose($filepdf);
                        }
                        else{
                            echo "error opening the file."."\n";
                        }
                        preg_match_all('!\d+!', $line_first, $matches);
                        $pdfversion = implode('.', $matches[0]);
                        if($pdfversion > "1.4"){
                            shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                        }
                        else{
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                        }
                        try {
                            echo "Путь к файлу: $dest_file\n";
                            $pagecount = $mpdf->SetSourceFile($dest_file);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                        } catch (\Mpdf\MpdfException $e) {
                            echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                            exit;
                        }
                    }
                }
            }
            else {
                $fileArray = \CFile::GetFileArray($itemData[$soglEDO]);
                $filename = $fileArray['FILE_NAME'];
                $random = rand(1,10000);
                if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                {
                    $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                    $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compress-'.$filename;

                    $filepdf = fopen($srcfile,"r");
                    if($filepdf) {
                        $line_first = fgets($filepdf);
                        fclose($filepdf);
                    }
                    else{
                        echo "error opening the file."."\n";
                    }
                    preg_match_all('!\d+!', $line_first, $matches);
                    $pdfversion = implode('.', $matches[0]);
                    if($pdfversion > "1.4"){
                        shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                    }
                    else{
                        shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                    }
                    try {
                        echo "Путь к файлу: $dest_file\n";
                        $pagecount = $mpdf->SetSourceFile($dest_file);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                    } catch (\Mpdf\MpdfException $e) {
                        echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                        exit;
                    }

                }
            }

        }
        // Удаление временных файлов
        $filesToDelete = [
            $srcfile_new,             // Временный сконвертированный файл
            $dest_file,               // Временный зашифрованный файл
            $srcfile_new_compress     // Временный сжатый файл
        ];

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "Удален временный файл: $file\n";
            }
        }
        echo "\n";
        if ($itemData[$passport]) {
            if (is_array($itemData[$passport])) {
                foreach ($itemData[$passport] as $fileId) {
                    $fileArray = \CFile::GetFileArray($fileId);
                    $filename = $fileArray['FILE_NAME'];
                    $random = rand(1,10000);
                    if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                    {
                        $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compress-'.$filename;

                        $filepdf = fopen($srcfile,"r");
                        if($filepdf) {
                            $line_first = fgets($filepdf);
                            echo "\n Есть файл Паспорта \n";
                            fclose($filepdf);
                        }
                        else{
                            echo "error opening the file."."\n";
                        }
                        preg_match_all('!\d+!', $line_first, $matches);
                        $pdfversion = implode('.', $matches[0]);
                        if($pdfversion > "1.4"){
                            shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                        }
                        else{
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                        }
                        try {
                            echo "Путь к файлу: $dest_file\n";
                            $pagecount = $mpdf->SetSourceFile($dest_file);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                        } catch (\Mpdf\MpdfException $e) {
                            echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                            exit;
                        }
                    }
                }
            }
            else {
                $fileArray = \CFile::GetFileArray($itemData[$passport]);
                $filename = $fileArray['FILE_NAME'];
                $random = rand(1,10000);
                if ($fileArray['CONTENT_TYPE'] == 'application/pdf')
                {
                    $srcfile = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';


                    $filepdf = fopen($srcfile,"r");
                    if($filepdf) {
                        $line_first = fgets($filepdf);
                        fclose($filepdf);
                    }
                    else{
                        echo "error opening the file."."\n";
                    }
                    preg_match_all('!\d+!', $line_first, $matches);
                    $pdfversion = implode('.', $matches[0]);
                    if($pdfversion > "1.4"){
                        shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$dest_file.'" "'.$srcfile_new.'"');
                    }
                    else{
                        shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$dest_file.'" "'.$srcfile.'"');
                    }
                    try {
                        echo "Путь к файлу: $dest_file\n";
                        $pagecount = $mpdf->SetSourceFile($dest_file);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                    } catch (\Mpdf\MpdfException $e) {
                        echo "Ошибка MPDF: " . $e->getMessage() . "\n";
                        exit;
                    }

                }
            }


        }
        // Удаление временных файлов
        $filesToDelete = [
            $srcfile_new,             // Временный сконвертированный файл
            $dest_file,               // Временный зашифрованный файл
            $srcfile_new_compress     // Временный сжатый файл
        ];

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "Удален временный файл: $file\n";
            }
        }
        echo "\n";
        echo "Конечный Step"."\n";

        $mpdf->OutputFile($uploadFilePath);
        echo $uploadFilePath."\n";

        // Проверяем размер после сохранения
        $finalSize = filesize($uploadFilePath) / 1000000;
        echo "Размер сохраненного файла: " . $finalSize . " МБ\n";

        if ($finalSize > 10) {
            $compressedFilePath = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compressed_' . basename($uploadFilePath);

            shell_exec('gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="' . $compressedFilePath . '" "' . $uploadFilePath . '"');

            if (file_exists($compressedFilePath)) {
                $compressedSize = filesize($compressedFilePath) / 1000000;
                echo "Размер сжатого файла: " . round($compressedSize, 2) . " МБ\n";

                // Если сжатый файл стал меньше, заменяем оригинал
                if ($compressedSize < $finalSize) {
                    rename($compressedFilePath, $uploadFilePath);
                    echo "Файл успешно заменен на сжатую версию\n";
                } else {
                    echo "Сжатие не дало результата, оставляем оригинальный файл\n";
                    unlink($compressedFilePath);
                }
            } else {
                echo "Ошибка: Ghostscript не смог создать сжатый PDF\n";
            }
        }

        //$mpdf->SetCompression(true);

        $fileArray = \CFile::MakeFileArray($uploadFilePath);
        $fid = CFile::SaveFile($fileArray, "main");
        array_push($newFiles, CFile::MakeFileArray($fid));

        echo "<p>КОНЕЧНЫЙ newFiles:</p> <pre>";
        print_r($newFiles);
        echo "</pre>";
        $arFile = CFile::GetByID($fid);
        echo "<p>arFile:</p> <pre>";
        print_r($arFile);
        echo "</pre>";
        /*if($files2) {
            foreach ($files2 as $fileId) {
                $fileArray = \CFile::GetFileArray($fileId);
                if (in_array($fileArray['CONTENT_TYPE'], ['application/pdf'])) {
                    $mpdf = new \Mpdf\Mpdf();
                    $originalFileName = pathinfo($fileArray['SRC'], PATHINFO_FILENAME);
                    $uploadFilePath = __DIR__ . '/tmp/' . $originalFileName . '.pdf';

                    $pagecount = $mpdf->SetSourceFile($uploadFilePath);
                    $tplId = $mpdf->ImportPage($pagecount);
                    $mpdf->UseTemplate($tplId);

                    $mpdf->Output($uploadFilePath);
                    $fileArray = \CFile::MakeFileArray($uploadFilePath);
                    $newFiles[] = $fileArray;

                }
            }
        }*/

        // Удаление временных файлов
        $filesToDelete = [
            $uploadFilePath,          // Итоговый объединенный PDF
            $srcfile_new,             // Временный сконвертированный файл
            $dest_file,               // Временный зашифрованный файл
            $srcfile_new_compress     // Временный сжатый файл
        ];

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "Удален временный файл: $file\n";
            }
        }
        $item->set($targetFieldCode, $newFiles);
        $item->setFromCompatibleData([
            $targetFieldCode => $newFiles,
        ]);
        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operationResult = $operation->launch();
        if ($operationResult->isSuccess()) {
            if($entityTypeID == 4) echo "<pre>Файл прикрепился к карточке Компании ID: " . $item->getId() . "</pre>";
            if($entityTypeID == 3) echo "<pre>Файл прикрепился к карточке Контакта ID: " . $item->getId() . "</pre>";

            /*echo "<p>Data:</p> <pre>";
            print_r($item->getData());
            echo "</pre>";*/
        }
    }
}