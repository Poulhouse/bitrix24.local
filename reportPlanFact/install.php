<?php
require_once ('include/crest/crest.php');
require_once ('include/crest/settings.php');

$result = CRest::installApp();
if($result['rest_only'] === false):?>
   <head>
      <script src="//api.bitrix24.com/api/v1/"></script>
      <?php if($result['install'] == true):?>
         <script>
            BX24.init(function(){
                BX24.installFinish();
            });
         </script>
      <?php endif;?>
   </head>
   <body>
      <?php if($result['install'] == true):?>
         <p>installation has been finished</p>
      <?php else:?>
         <p>installation error</p>
      <?php endif;?>
   </body>
<?php endif;?>