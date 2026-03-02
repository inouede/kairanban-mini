<?php

define('VAPID_KEYS_FILE', __DIR__ . '/../data/vapid.json');

function getVapidKeys() {
    if (!file_exists(VAPID_KEYS_FILE)) {
        error_log('[VAPID] ファイルが存在しません');
        return false;
    }
    
    $json = file_get_contents(VAPID_KEYS_FILE);
    $data = json_decode($json, true);
    
    if (
    empty($data['publicKey']) ||
    empty($data['privateKey']) ||
    empty($data['subject'])
    ) {
        error_log('[VAPID] 鍵の内容が不正です');
        return false;
    }
    
    return $data;
}
