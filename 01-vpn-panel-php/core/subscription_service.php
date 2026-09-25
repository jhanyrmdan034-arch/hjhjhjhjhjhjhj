<?php
function is_public_remote_url(string $url): bool {
    if (!validate_http_url($url)) return false;
    $host=(string)parse_url($url,PHP_URL_HOST);
    if ($host==='' || strtolower($host)==='localhost') return false;
    // Literal IP: reject private/reserved ranges.
    if (filter_var($host,FILTER_VALIDATE_IP)) {
        return (bool)filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    }
    $records=@dns_get_record($host,DNS_A|DNS_AAAA);
    if (!$records) return false;
    foreach($records as $r){
        $ip=$r['ip']??($r['ipv6']??null);
        if($ip && !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

function absolutize_redirect(string $base, string $location): string {
    if (preg_match('~^https?://~i',$location)) return $location;
    $p=parse_url($base); if(!$p||empty($p['host'])) return '';
    $scheme=$p['scheme']??'https'; $port=isset($p['port'])?':'.$p['port']:'';
    if(str_starts_with($location,'/')) return $scheme.'://'.$p['host'].$port.$location;
    $path=$p['path']??'/'; $dir=rtrim(str_replace('\\','/',dirname($path)),'/');
    return $scheme.'://'.$p['host'].$port.($dir?'/'.$dir:'').'/'.$location;
}

function http_fetch_subscription(string $url, int $timeout=8): array {
    $current=$url;
    for($i=0;$i<4;$i++){
        if(!is_public_remote_url($current)) return [false,'Remote URL is not allowed'];
        $headers=[];
        $ch = curl_init($current);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>$timeout,
            CURLOPT_USERAGENT=>'VPNPanel/1.0',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$headers){$len=strlen($line);$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return $len;},
        ]);
        $body=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if(in_array($status,[301,302,303,307,308],true) && !empty($headers['location'])){$current=absolutize_redirect($current,$headers['location']);continue;}
        if ($body===false || $status<200 || $status>=300) return [false,'HTTP '.$status.($err?': '.$err:'')];
        if (strlen($body)>2_000_000) return [false,'Payload too large'];
        return [true,$body];
    }
    return [false,'Too many redirects'];
}

function decode_subscription_payload(string $body): string {
    $trim=trim($body);
    if ($trim==='') return '';
    // If already contains common URI schemes, use as-is.
    if (preg_match('~(?:^|\n)(?:vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic)://~i',$trim)) return $trim;
    $normalized=strtr(preg_replace('/\s+/','',$trim), '-_', '+/');
    $pad=strlen($normalized)%4;
    if ($pad) $normalized .= str_repeat('=',4-$pad);
    $decoded=base64_decode($normalized,true);
    if ($decoded!==false && preg_match('~(?:vmess|vless|trojan|ss|ssr|hysteria2|hy2|tuic)://~i',$decoded)) return $decoded;
    return $trim;
}

function parse_nodes(string $payload, int $sourceId): array {
    $text=decode_subscription_payload($payload);
    $lines=preg_split('/\r\n|\r|\n/', $text);
    $allowed=['vmess','vless','trojan','ss','ssr','hysteria2','hy2','tuic'];
    $nodes=[];
    foreach($lines as $line){
        $line=trim($line);
        if ($line==='' || strlen($line)>8192) continue;
        $scheme=strtolower((string)parse_url($line,PHP_URL_SCHEME));
        if (!in_array($scheme,$allowed,true)) continue;
        $label='Server';
        $frag=parse_url($line,PHP_URL_FRAGMENT);
        if (is_string($frag) && $frag!=='') $label=rawurldecode($frag);
        elseif($scheme==='vmess'){
            $b=substr($line,8); $d=base64_decode(strtr($b,'-_','+/'),true);
            if($d){$j=json_decode($d,true); if(is_array($j)&&!empty($j['ps']))$label=(string)$j['ps'];}
        }
        $nodes[]=[
            'id'=>hash('sha256',$line),
            'source_id'=>$sourceId,
            'name'=>text_substr($label,0,120),
            'protocol'=>$scheme,
            'config'=>$line,
        ];
    }
    return $nodes;
}

function refresh_subscription(PDO $pdo, array $sub): array {
    [$ok,$data]=http_fetch_subscription($sub['url']);
    if(!$ok){
        $pdo->prepare('UPDATE subscriptions SET last_error=?, last_checked_at=NOW() WHERE id=?')->execute([$data,$sub['id']]);
        return ['ok'=>false,'error'=>$data];
    }
    $nodes=parse_nodes($data,(int)$sub['id']);
    if(!$nodes){
        $err='No supported nodes found';
        $pdo->prepare('UPDATE subscriptions SET last_error=?, last_checked_at=NOW() WHERE id=?')->execute([$err,$sub['id']]);
        return ['ok'=>false,'error'=>$err];
    }
    $json=json_encode($nodes,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $pdo->prepare('UPDATE subscriptions SET cached_nodes=?, node_count=?, last_error=NULL, last_checked_at=NOW(), cache_updated_at=NOW() WHERE id=?')->execute([$json,count($nodes),$sub['id']]);
    return ['ok'=>true,'count'=>count($nodes)];
}

function refresh_all_subscriptions(PDO $pdo, bool $force=false): array {
    $subs=$pdo->query('SELECT * FROM subscriptions WHERE active=1 ORDER BY priority DESC,id ASC')->fetchAll();
    $ttl=(int)setting($pdo,'subscription_ttl_minutes','10');
    $out=[];
    foreach($subs as $sub){
        $fresh=!$force && !empty($sub['cache_updated_at']) && strtotime($sub['cache_updated_at']) > time()-($ttl*60);
        $out[$sub['id']]=$fresh?['ok'=>true,'skipped'=>true]:refresh_subscription($pdo,$sub);
    }
    return $out;
}

function get_cached_nodes(PDO $pdo): array {
    $subs=$pdo->query('SELECT id,name,cached_nodes FROM subscriptions WHERE active=1 ORDER BY priority DESC,id ASC')->fetchAll();
    $seen=[];$nodes=[];
    foreach($subs as $s){
        $arr=json_decode($s['cached_nodes']??'[]',true);
        if(!is_array($arr))continue;
        foreach($arr as $n){
            if(empty($n['id'])||isset($seen[$n['id']]))continue;
            $seen[$n['id']]=true;
            $n['source_name']=$s['name'];
            unset($n['source_id']);
            $nodes[]=$n;
        }
    }
    return $nodes;
}

function active_ad(PDO $pdo): ?array {
    $stmt=$pdo->query("SELECT id,title,image_path,target_url,display_seconds FROM ads WHERE active=1 AND placement='pre_connect' AND plan='free' AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY priority DESC,id DESC LIMIT 1");
    $a=$stmt->fetch();
    if(!$a)return null;
    $base=rtrim(setting($pdo,'base_url',''),'/' );
    return [
        'id'=>(int)$a['id'],
        'title'=>$a['title'],
        'image_url'=>$base . '/' . ltrim($a['image_path'],'/'),
        'target_url'=>$a['target_url'],
        'display_seconds'=>(int)$a['display_seconds'],
        'show_before_connect'=>true,
    ];
}
