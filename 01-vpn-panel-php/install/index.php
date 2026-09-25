<?php
$root=dirname(__DIR__);
$requiredExt=['pdo_mysql','curl','json','openssl','fileinfo']; $missingExt=array_values(array_filter($requiredExt,fn($e)=>!extension_loaded($e)));
if(is_file($root.'/storage/installed.lock')){http_response_code(404);exit('Not Found');}
$error='';$success=false;$apiKey='';$cronToken='';$adminGate='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if($missingExt){$error='افزونه‌های PHP موردنیاز نصب نیست: '.implode(', ',$missingExt);} else {
    $host=trim($_POST['db_host']??'localhost');$name=trim($_POST['db_name']??'');$user=trim($_POST['db_user']??'');$pass=(string)($_POST['db_pass']??'');
    $adminUser=trim($_POST['admin_user']??'admin');$adminPass=(string)($_POST['admin_pass']??'');
    $base=trim($_POST['base_url']??'');
    try{
        if(!$name||!$user||strlen($adminPass)<10) throw new Exception('نام دیتابیس، کاربر دیتابیس و رمز مدیر حداقل ۱۰ کاراکتری الزامی است.');
        $pdo=new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $sql=file_get_contents(__DIR__.'/schema.sql');$pdo->exec($sql);
        $stmt=$pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW())');$stmt->execute([$adminUser,password_hash($adminPass,PASSWORD_DEFAULT)]);
        $apiKey=bin2hex(random_bytes(24));$cronToken=bin2hex(random_bytes(24));$adminGate=bin2hex(random_bytes(18));
        $settings=[
            'base_url'=>$base,
            'app_api_key_hash'=>password_hash($apiKey,PASSWORD_DEFAULT),
            'cron_token_hash'=>password_hash($cronToken,PASSWORD_DEFAULT),
            'admin_gate_hash'=>password_hash($adminGate,PASSWORD_DEFAULT),
            'subscription_ttl_minutes'=>'10',
            'maintenance_mode'=>'0',
            'minimum_app_version'=>'1.0.0',
        ];
        $s=$pdo->prepare('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');foreach($settings as $k=>$v)$s->execute([$k,$v]);
        $pdo->prepare('INSERT IGNORE INTO schema_migrations(id,applied_at) VALUES(?,NOW())')->execute(['001_initial']);
        $cfg="<?php\nreturn ".var_export(['db'=>['host'=>$host,'name'=>$name,'user'=>$user,'pass'=>$pass,'charset'=>'utf8mb4'],'app'=>['base_url'=>$base,'session_name'=>'vpn_admin_session','debug'=>false]],true).";\n";
        if(file_put_contents($root.'/config.php',$cfg,LOCK_EX)===false)throw new Exception('امکان نوشتن config.php وجود ندارد. Permission پوشه را بررسی کنید.');
        @chmod($root.'/config.php',0640);
        if(!is_dir($root.'/storage'))mkdir($root.'/storage',0750,true);
        file_put_contents($root.'/storage/installed.lock',date('c'));
        $success=true;
    }catch(Throwable $e){$error=$e->getMessage();}
}}
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$scriptName=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/install/index.php'));
$installDir=rtrim(str_replace('\\','/',dirname($scriptName)),'/');
$basePath=rtrim(str_replace('\\','/',dirname($installDir)),'/');
if($basePath==='.'||$basePath==='/')$basePath='';
$auto=$scheme.'://'.($_SERVER['HTTP_HOST']??'example.com').$basePath;
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نصب VPN Panel</title><style>
body{font-family:Tahoma,Arial;background:#0b1020;color:#edf2ff;margin:0;min-height:100vh;display:grid;place-items:center}.card{width:min(720px,92%);background:#141b2d;border:1px solid #26314c;border-radius:22px;padding:28px;box-shadow:0 20px 60px #0008}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:block;font-size:13px;color:#aebbd8;margin-bottom:6px}input{width:100%;box-sizing:border-box;padding:13px;border-radius:12px;border:1px solid #34415e;background:#0e1525;color:white}button{width:100%;padding:14px;border:0;border-radius:12px;background:#6e8cff;color:#07101f;font-weight:bold;cursor:pointer}.full{grid-column:1/-1}.err{background:#4e1c25;padding:12px;border-radius:10px}.ok{background:#123c2c;padding:16px;border-radius:12px;word-break:break-all}small{color:#91a0bf}@media(max-width:640px){.grid{grid-template-columns:1fr}.card{padding:20px}}
</style></head><body><div class="card"><h1>VPN Panel</h1><p>نصب سریع PHP/MySQL</p><?php if($missingExt):?><div class="err">افزونه‌های PHP موردنیاز روی این هاست فعال نیست: <?=htmlspecialchars(implode(', ',$missingExt))?></div><?php elseif($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="ok"><h3>نصب انجام شد</h3><p><b>App API Key:</b><br><?=htmlspecialchars($apiKey)?></p><p><b>Cron Token:</b><br><?=htmlspecialchars($cronToken)?></p><p><b>Admin Gate:</b><br><?=htmlspecialchars($adminGate)?></p><p>این سه مقدار فقط همین بار نمایش داده می‌شوند؛ در جای امن نگه دارید.</p><p><b>آدرس ورود مخفی:</b><br><code><?=htmlspecialchars(rtrim($base,'/').'/admin/login.php?gate='.$adminGate)?></code></p></div><?php else:?><form method="post"><div class="grid"><div><label>DB Host</label><input name="db_host" value="localhost" required></div><div><label>DB Name</label><input name="db_name" required></div><div><label>DB User</label><input name="db_user" required></div><div><label>DB Password</label><input type="password" name="db_pass"></div><div><label>نام کاربری مدیر</label><input name="admin_user" value="admin" required></div><div><label>رمز مدیر (حداقل ۱۰ کاراکتر)</label><input type="password" name="admin_pass" minlength="10" required></div><div class="full"><label>Base URL</label><input name="base_url" value="<?=htmlspecialchars($auto)?>" required></div><div class="full"><button <?= $missingExt?'disabled':'' ?>>نصب</button></div></div></form><?php endif;?></div></body></html>
