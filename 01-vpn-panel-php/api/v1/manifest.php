<?php require_once dirname(__DIR__,2).'/core/bootstrap.php';require_app_key($pdo);
if(setting($pdo,'maintenance_mode','0')==='1')json_response(['ok'=>true,'maintenance'=>true,'minimum_app_version'=>setting($pdo,'minimum_app_version','1.0.0'),'servers'=>[],'pre_connect_ad'=>active_ad($pdo)]);
// Refresh only stale sources. For best performance, also configure cron.
refresh_all_subscriptions($pdo,false);
$nodes=get_cached_nodes($pdo);
json_response(['ok'=>true,'maintenance'=>false,'generated_at'=>gmdate('c'),'minimum_app_version'=>setting($pdo,'minimum_app_version','1.0.0'),'servers'=>$nodes,'pre_connect_ad'=>active_ad($pdo)]);
