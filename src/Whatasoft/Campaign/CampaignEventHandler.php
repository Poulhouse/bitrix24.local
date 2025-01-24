<?php

namespace Whatasoft\Campaign;

class CampaignEventHandler
{
  public static function OnBeforeCampaignDelete($iBlockElementId)
  {
    $callQueue = new CampaignCallQueue();
    $callQueue->removeCampaignFromQueue($iBlockElementId);
  }
}

