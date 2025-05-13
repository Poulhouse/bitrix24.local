<?php

namespace Kplab\Pep\Service;

use Bitrix\Main\Config\Option;
use Mpdf\Mpdf;
use Mpdf\QrCode\QrCode;
use Mpdf\QrCode\Output;

class PdfGenerator
{
    public static function generate(string $sourcePath, array $clientData, string $savePath): array
    {
        $docHash = hash_file('sha256', $sourcePath);
        $secret = Option::get('kplab.pep', 'pep_secret_key');
        $verifyUrl = Option::get('kplab.pep', 'pep_verify_url') . '?hash=' . $docHash;

        $signature = hash_hmac('sha256', implode('|', [
            $docHash,
            $clientData['full_name'],
            $clientData['phone'],
            $clientData['email'],
            $clientData['signed_at']
        ]), $secret);

        $footer = <<<HTML
            <div style="font-size:8pt; font-family: monospace;">
            Подписано через ПЭП: {$clientData['full_name']} | {$clientData['phone']} | {$clientData['email']}<br>
            Дата: {$clientData['signed_at']}<br>
            Хеш: {$docHash}<br>
            Подпись: {$signature}<br>
            Проверка: {$verifyUrl}
            </div>
        HTML;

        $mpdf = new Mpdf(['tempDir' => $_SERVER['DOCUMENT_ROOT'].'/upload/tmp_mpdf']);
        $mpdf->SetImportUse();
        $pageCount = $mpdf->SetSourceFile($sourcePath);
        $mpdf->SetFooter($footer);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $mpdf->ImportPage($i);
            $mpdf->AddPage();
            $mpdf->UseTemplate($tplId);
        }

        $qr = new QrCode($verifyUrl);
        $qrOutput = new Output\Png();
        $qrImage = 'data:image/png;base64,' . base64_encode($qrOutput->output($qr, 100));

        $mpdf->AddPage();
        $mpdf->WriteHTML("<h3>Подпись</h3>
            <p>ФИО: {$clientData['full_name']}</p>
            <p>Телефон: {$clientData['phone']}</p>
            <p>Email: {$clientData['email']}</p>
            <p>Дата: {$clientData['signed_at']}</p>
            <p>Хеш: {$docHash}</p>
            <p>Подпись (HMAC): {$signature}</p>
            <p><a href='{$verifyUrl}'>{$verifyUrl}</a></p>
            <img src='{$qrImage}' width='100' height='100'>"
        );

        $mpdf->Output($savePath, \Mpdf\Output\Destination::FILE);

        return [
            'document_hash' => $docHash,
            'signature' => $signature
        ];
    }
}
