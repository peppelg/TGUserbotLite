#!/usr/bin/php
<?php
define('PID', getmypid());
chdir(__DIR__);
if (!file_exists('madeline.phar')) {
  echo 'Downloading MadelineProto...';
  copy('https://phar.madelineproto.xyz/madeline.phar?v=new', 'madeline.phar');
  echo PHP_EOL.'Done.'.PHP_EOL;
}
if (!file_exists('sessions')) mkdir('sessions');
require_once 'madeline.phar';
include_once 'settings.php';
$MadelineProto = NULL;
$update = NULL;
$settings_default = ['session' => 'sessions/default.madeline', 'readmsg' => true, 'auto_reboot' => true, 'multithread' => false, 'old_update_parser' => false, 'madeline' => ['app_info' => ['api_id' => 6, 'api_hash' => 'eb06d4abfb49dc3eeb1aeb98ae0f581e', 'lang_code' => 'it', 'app_version' => '4.7.0'], 'logger' => ['logger' => 0], 'updates' => ['handle_old_updates' => 0]]];
if (isset($settings) and is_array($settings)) $settings = array_merge($settings_default, $settings); else $settings = $settings_default;
unset($settings_default);
if (isset($argv[1]) and $argv[1]) $settings['session'] = 'sessions/'.$argv[1].'.madeline';
if ($settings['auto_reboot'] and function_exists('pcntl_exec')) {
  register_shutdown_function(function () {
    if (PID === getmypid()) pcntl_exec($_SERVER['_'], [__FILE__, $settings['session']]);
  });
}
$MadelineProto = new \danog\MadelineProto\API($settings['session'], $settings['madeline']);
$MadelineProto->start();
echo 'TGUserbotLite is ready¡'.PHP_EOL;
class TGUserbotEventHandler extends \danog\MadelineProto\EventHandler {
  public function onAny($update) {
    try {
      global $settings;
      global $MadelineProto;
      foreach ($this->parse_update($update) as $varname => $var) if ($varname !== 'update') $$varname = $var;
      if ($settings['old_update_parser']) {
        if (isset($msg) and isset($chatID) and $msg and $chatID) echo $chatID.' >>> '.$msg.PHP_EOL;
      } else {
        if (isset($msg) and isset($chatID) and isset($type) and $msg) {
          if ($type == 'user') {
            echo $name.' ('.$userID.') >>> '.$msg.PHP_EOL;
          } elseif ($type == 'channel') {
            echo $title.' ('.$chatID.') >>> '.$msg.PHP_EOL;
          } else {
            echo $name.' ('.$userID.') -> '.$title.' ('.$chatID.') >>> '.$msg.PHP_EOL;
          }
        }
      }
      include 'bot.php';
      if ($settings['readmsg'] and isset($chatID) and isset($msgid) and $msgid and isset($type)) {
        try {
          if (in_array($type, ['user', 'bot', 'group'])) {
            $MadelineProto->messages->readHistory(['peer' => $chatID, 'max_id' => $msgid]);
          } elseif(in_array($type, ['channel', 'supergroup'])) {
            $MadelineProto->channels->readHistory(['channel' => $chatID, 'max_id' => $msgid]);
          }
        } catch(Exception $e) { }
      }
    } catch (Exception $e) {
      $this->error($e);
    }
  }
  public function parse_update($mUpdate) {
    global $settings;
    global $MadelineProto;
    global $update;
    $update = $mUpdate;
    $result = ['chatID' => NULL, 'userID' => NULL, 'msgid' => NULL, 'type' => NULL, 'name' => NULL, 'username' => NULL, 'chatusername' => NULL, 'title' => NULL, 'msg' => NULL, 'cronjob' => NULL, 'info' => NULL, 'update' => $update];
    try {
      if ($settings['old_update_parser']) {
        if (isset($update['message']['from_id'])) $result['userID'] = $update['message']['from_id'];
        if (isset($update['message']['id'])) $result['msgid'] = $update['message']['id'];
        if (isset($update['message']['message'])) $result['msg'] = $update['message']['message'];
        if (isset($update['message']['to_id']['channel_id'])) {
          $result['chatID'] = '-100'.$update['message']['to_id']['channel_id'];
          $result['type'] = 'supergroup';
        }
        if (isset($update['message']['to_id']['chat_id'])) {
          $result['chatID'] = '-'.$update['message']['to_id']['chat_id'];
          $result['type'] = 'group';
        }
        if (isset($update['message']['to_id']['user_id'])) {
          $result['chatID'] = $update['message']['from_id'];
          $result['type'] = 'user';
        }
      } else {
        if (isset($update['message'])) {
          if (isset($update['message']['from_id'])) $result['userID'] = $update['message']['from_id'];
          if (isset($update['message']['id'])) $result['msgid'] = $update['message']['id'];
          if (isset($update['message']['message'])) $result['msg'] = $update['message']['message'];
          if (isset($update['message']['to_id'])) $result['info']['to'] = $MadelineProto->get_info($update['message']['to_id']);
          if (isset($result['info']['to']['bot_api_id'])) $result['chatID'] = $result['info']['to']['bot_api_id'];
          if (isset($result['info']['to']['type'])) $result['type'] = $result['info']['to']['type'];
          if (isset($result['userID'])) $result['info']['from'] = $MadelineProto->get_info($result['userID']);
          if (isset($result['info']['to']['User']['self']) and isset($result['userID']) and $result['info']['to']['User']['self']) $result['chatID'] = $result['userID'];
          if (isset($result['type']) and $result['type'] == 'chat') $result['type'] = 'group';
          if (isset($result['info']['from']['User']['first_name'])) $result['name'] = $result['info']['from']['User']['first_name'];
          if (isset($result['info']['to']['Chat']['title'])) $result['title'] = $result['info']['to']['Chat']['title'];
          if (isset($result['info']['from']['User']['username'])) $result['username'] = $result['info']['from']['User']['username'];
          if (isset($result['info']['to']['Chat']['username'])) $result['chatusername'] = $result['info']['to']['Chat']['username'];
        }
      }
    } catch (Exception $e) {
      $this->error($e);
    }
    return $result;
  }
  public function error($e) {
    echo 'Error: '.$e;
  }
}
$MadelineProto->setEventHandler('\TGUserbotEventHandler');
if ($settings['multithread']) $MadelineProto->loop(-1); else $MadelineProto->loop();
