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
                        echo $srcfile ."\n";
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        echo $srcfile_new."\n";
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        echo $dest_file."\n";

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
                            // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            $pagecount = $mpdf->SetSourceFile($srcfile_new);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
                        }
                        else{
                            $pagecount = $mpdf->SetSourceFile($srcfile);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
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
                    echo $srcfile."\n";
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                    echo $srcfile_new."\n";
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                    echo $dest_file."\n";

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
                        // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                        shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        $pagecount = $mpdf->SetSourceFile($srcfile_new);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }
                    else{
                        $pagecount = $mpdf->SetSourceFile($srcfile);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }

                }
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
                        echo $srcfile."\n";
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        echo $srcfile_new."\n";
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        echo $dest_file."\n";

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
                            // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            $pagecount = $mpdf->SetSourceFile($srcfile_new);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
                        }
                        else{
                            $pagecount = $mpdf->SetSourceFile($srcfile);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
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
                    echo $srcfile."\n";
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                    echo $srcfile_new."\n";
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                    echo $dest_file."\n";

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
                        // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                        shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        $pagecount = $mpdf->SetSourceFile($srcfile_new);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }
                    else{
                        $pagecount = $mpdf->SetSourceFile($srcfile);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }

                }
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
                        echo $srcfile."\n";
                        $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                        echo $srcfile_new."\n";
                        $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                        echo $dest_file."\n";
                        $srcfile_new_compress = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/compress-'.$filename;
                        echo $srcfile_new_compress;

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
                        echo "\n preg_match_all Паспорта \n";
                        $pdfversion = implode('.', $matches[0]);
                        echo "\n pdfversion Паспорта {$pdfversion}\n";
                        if($pdfversion > "1.4"){

                            echo 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"';
                            echo 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$srcfile_new_compress.'" "'.$srcfile_new.'"';

                            // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$srcfile_new_compress.'" "'.$srcfile_new.'"');
                            $pagecount = $mpdf->SetSourceFile($srcfile_new_compress);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId,-1,-1,210);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
                        }
                        else{
                            echo 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"';

                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/screen -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                            $pagecount = $mpdf->SetSourceFile($srcfile_new);
                            for ($i = 1;
                                 $i <= $pagecount;
                                 $i++)
                            {
                                $mpdf->AddPage('', '', '1', 'i', 'on');
                                $tplId = $mpdf->ImportPage($i);
                                $mpdf->UseTemplate($tplId,-1,-1,210);
                            }
                            //$mpdf->SetProtection(array(), '', 'MyPassword');
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
                    echo $srcfile."\n";
                    $srcfile_new = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/' . $filename;
                    echo $srcfile_new."\n";
                    $dest_file = $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/encrypted-'.$random.'.pdf';
                    echo $dest_file."\n";

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
                        // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
                        shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$srcfile_new.'" "'.$srcfile.'"');
                        $pagecount = $mpdf->SetSourceFile($srcfile_new);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }
                    else{
                        $pagecount = $mpdf->SetSourceFile($srcfile);
                        for ($i = 1;
                             $i <= $pagecount;
                             $i++)
                        {
                            $mpdf->AddPage('', '', '1', 'i', 'on');
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);
                        }
                        //$mpdf->SetProtection(array(), '', 'MyPassword');
                    }

                }
            }


        }
        echo "\n";
        echo "Конечный Step"."\n";
        
        $mpdf->SetCompression(true);
        $mpdf->OutputFile($uploadFilePath);
        echo $uploadFilePath."\n";
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