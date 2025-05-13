<?php

declare(strict_types=1);

?>
<pre>
    Application installation has been started, auth-tokens from Bitrix24:
    <?= print_r($_REQUEST, true) ?>
</pre>
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
    BX24.init(function(){
        BX24.installFinish();
    });
</script>