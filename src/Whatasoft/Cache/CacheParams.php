<?
namespace Whatasoft\Cache;

use Bitrix\Main\Data\Cache;
use CPHPCache;

class CacheParams {
  const CUSTOM_DIR = 'cache_whatasoft';
  
  public static function AgentClear(){
    self::ClearExpired();
    return "Whatasoft\Cache\CacheParams::AgentClear();";
  }
  
  private static function ClearExpired($initdir=""){
    $res = true;
    $path = $_SERVER["DOCUMENT_ROOT"] . BX_PERSONAL_ROOT ."/". self::CUSTOM_DIR . $initdir;
    if(is_dir($path) && ($handle = opendir($path))){
      while(($file = readdir($handle)) !== false){
        if($file == "." || $file == ".."){
          continue;
        }
        
        if(is_dir($path."/".$file)){
          if(!self::ClearExpired($initdir."/".$file)){
            $res = false;
          }else{
            @chmod($path."/".$file, BX_DIR_PERMISSIONS);
            @rmdir($path."/".$file);
          }
        }elseif(substr($file, -4)==".php"){
          $obCache = new CPHPCache();
          if($obCache->IsCacheExpired($path."/".$file)){
            @chmod($path."/".$file, BX_FILE_PERMISSIONS);
            if(!unlink($path."/".$file)){
              $res = false;
            }
          }
        }
      }
      closedir($handle);
    }
    
    return $res;
  }
  
  private static function _SetCache($_cache_id, $_params, $_cache_time){
    $obCache = new CPHPCache;
    if($_cache_time < 300){
      $_cache_time = 300;
    }
    $cache_path = "/";
    
    if($obCache->InitCache($_cache_time, $_cache_id, $cache_path, self::CUSTOM_DIR)){
      //$obCache->GetVars();
      //$obCache->Clean($_cache_id, $cache_path, self::CUSTOM_DIR);
    }else if($obCache->StartDataCache($_cache_time, $_cache_id, $cache_path, array(), self::CUSTOM_DIR)){
      $obCache->EndDataCache($_params);
    }
    
    return true;
  }
  
  //update cache every 5 * 60 seconds
  public static function SetCache($_component_name, $_template_name, $_params, $_additional_cache_id = false, $_cache_time = 300){
    //dont process ajax call
    if(isset($_params["WAS_FROM_AJAX"]) && isset($_params["WAS_CACHE_ID"])){
      return $_params["WAS_CACHE_ID"];
    }
    
    $_params["COMPONENT_NAME"] = $_component_name;
    $_params["COMPONENT_TEMPLATE"] = $_template_name;
    
    $cache_id_items = array();
    $cache_id_items[] = SITE_ID;
    $cache_id_items[] = SITE_TEMPLATE_ID;
    $cache_id_items[] = $_component_name;
    $cache_id_items[] = $_template_name;
    $cache_id_items[] = sha1(serialize($_params));
    if($_additional_cache_id !== false){
      $cache_id_items[] = sha1(serialize($_additional_cache_id));
    }
    $cache_id = implode("|", $cache_id_items);
    self::_SetCache($cache_id, $_params, $_cache_time);
    $cache_id = base64_encode($cache_id);
    
    return $cache_id;
  }
  
  //validate cache every 24 * 60 * 60 seconds
  public static function GetCache($_cache_id, $_cache_time = 86400){
    $res = false;
    //turn off clear cache
    $clearCache = Cache::setClearCache(false);
    $clearCacheSession = (isset($_SESSION["SESS_CLEAR_CACHE"]) && $_SESSION["SESS_CLEAR_CACHE"] === "Y");
    Cache::setClearCacheSession(false);
    if($clearCacheSession){
      unset($_SESSION["SESS_CLEAR_CACHE"]);
    }
    //////////////////////
    
    $cache_id = base64_decode($_cache_id);
    $obCache = new CPHPCache;
    if($_cache_time < 600){
      $_cache_time = 600;
    }
    $cache_path = "/";

    if($obCache->InitCache($_cache_time, $cache_id, $cache_path, self::CUSTOM_DIR)){
      $res = $obCache->GetVars();
      if(is_array($res)){
        $res["WAS_FROM_AJAX"] = "Y";
        $res["WAS_CACHE_ID"] = $_cache_id;
      }
    }
    
    //turn on clear cache
    if($clearCache){
      Cache::setClearCache(true);
    }
    if($clearCacheSession){
       Cache::setClearCacheSession(true);
       $_SESSION["SESS_CLEAR_CACHE"] = "Y";
    }
    /////////////////////
    return $res;
  }
}
?>