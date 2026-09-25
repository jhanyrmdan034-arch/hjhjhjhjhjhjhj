<?php require_once dirname(__DIR__).'/core/bootstrap.php';
if(PHP_SAPI!=='cli'){
    $token=$_GET['token']??'';$hash=setting($pdo,'cron_token_hash','');
    if(!$token||!$hash||!password_verify($token,$hash)){http_response_code(404);exit('Not Found');}
}
$r=refresh_all_subscriptions($pdo,true);echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
