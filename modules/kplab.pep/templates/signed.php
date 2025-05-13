<?php
use Kplab\Pep\Service\SmsService;
use Kplab\Pep\Service\PdfGenerator;
use Kplab\Pep\Logger;
use Kplab\Pep\Service\SignResultSaver;
use Bitrix\Main\Loader;

// Инициализация
$sentSessionKey = 'pep_sms_sent_' . $data['documentHash'];
$client = $data['client'];
$docHash = $data['documentHash'];

// Автоотправка СМС
if (!isset($data['session'][$sentSessionKey]) && !$data['skipSms']) {
    SmsService::sendCode($client['phone'], $docHash);
    $data['session'][$sentSessionKey] = time();
    $smsSent = true;
    Logger::log($docHash, 'sms_send', 'Автоотправка', $client['phone']);
}
?>

<h2>Подписание документа</h2>
<p><strong>ФИО:</strong> <?=htmlspecialchars($client['full_name'])?></p>
<p><strong>Email:</strong> <?=htmlspecialchars($client['email'])?></p>
<p><strong>Телефон:</strong> <?=htmlspecialchars($client['phone'])?></p>

<iframe src="<?=htmlspecialchars($data['filePath'])?>" width="100%" height="600px" style="border:1px solid #ccc"></iframe>

<form method="post">
    <input type="hidden" name="action" value="send_code">
    <button type="submit" id="resend-btn">Отправить код повторно</button>
    <span id="timer-text" style="margin-left:10px;color:gray;"></span>
</form>

<form method="post" style="margin-top: 20px;">
    <input type="hidden" name="action" value="confirm_code">
    <input type="text" name="sms_code" placeholder="Введите СМС-код" required>
    <button type="submit">Подписать</button>
</form>

<script>
    let countdown = 60;
    let resendBtn = document.getElementById("resend-btn");
    let timerText = document.getElementById("timer-text");

    function startTimer() {
        resendBtn.disabled = true;
        timerText.style.display = "inline";
        let interval = setInterval(() => {
            countdown--;
            timerText.innerText = `Отправить повторно через ${countdown} сек`;
            if (countdown <= 0) {
                clearInterval(interval);
                timerText.innerText = '';
                resendBtn.disabled = false;
            }
        }, 1000);
    }

    <?php if ($smsSent ?? false): ?> startTimer(); <?php endif; ?>
</script>

<?php
// Обработка
$action = $data['request']->getPost('action');

if ($action === 'send_code') {
    if ($data['isSigned']) {
        echo "<p style='color:red'>Документ уже подписан. Повторная отправка невозможна.</p>";
        return;
    }

    if (!\Kplab\Pep\Service\SignProcess::resendCode($data['client'], $data['documentHash'])) {
        echo "<p style='color:red'>Повторная отправка доступна не чаще, чем раз в минуту.</p>";
        return;
    }

    echo "<p style='color:green'>Код повторно отправлен</p>";
    echo "<script>startTimer();</script>";
}

if ($action === 'confirm_code') {
    if ($data['isSigned']) {
        echo "<p style='color:red'>Документ уже подписан.</p>";
        return;
    }

    $code = $data['request']->getPost('sms_code');

    $newFileId = \Kplab\Pep\Service\SignProcess::handleConfirmation(
        $data['client'],
        $data['documentHash'],
        $code,
        $data['filePath'],
        $data['fileId'],
        $data['companyId']
    );

    if ($newFileId) {
        echo "<p style='color:green'>Документ успешно подписан!</p>";
        echo "<p><a href='" . \CFile::GetPath($newFileId) . "' target='_blank'>Скачать подписанный документ</a></p>";
    } else {
        echo "<p style='color:red'>Неверный или просроченный код</p>";
    }
}


?>
