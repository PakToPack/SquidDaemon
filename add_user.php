<?php
require __DIR__ . 'htpasswd_gen.php';

$htpasswd = new HtpasswdGenerator('/etc/squid/passwd');

header('Content-Type: application/json; charset=utf-8');

$conf_text = "";
$usr_name = $_GET['usr'] ?? null;
$ttl = $_GET['ttl'] ?? 4;
$conf_name = $_GET['country'] ?? 'us';

$ip = 'IP'; // Your IP here

if ($usr_name !== null) {
    try {
        $port = 10000;
        $ports = 2000;

        $day_mins = 1440; // Day in minutes

        $ttl_arr = [5, 10, 15, 30, 60];

        if ($ttl > count($ttl_arr))
            throw new Exception("TTL is a number in range of 0 to 4. Accepting TTL's:\n[0] => 5,\n[1] => 10,\n[2] => 15,\n[3] => 30,\n[4] => 60");

        $max_ttl = 60;

        $divider = $max_ttl / $ttl;

        $proxy_ttl = $ports / $divider;

        // Connect to proxy list database
        $db_c = [
            'db_user' => '', // Your DB USERNAME HERE
            'db_pass' => '', // Your DB PASSWORD HERE
            'db_name' => '',  // Your DB NAME HERE
            'db_ip' => '127.0.0.1',
            'db_port' => '3306',
        ];
        $mysqli = new mysqli($db_c['db_ip'], $db_c['db_user'], $db_c['db_pass'], $db_c['db_name']);
        // End of mysqli connection

        $all_query = $mysqli->query("SELECT conf_name FROM forwards");
        $allconf = mysqli_fetch_all($all_query, MYSQLI_ASSOC);
        $confs = "";
        foreach ($allconf as $acnf)
            $confs .= sprintf("\n'%s'", $acnf['conf_name']);

        $result = $mysqli->query(sprintf("SELECT * FROM forwards WHERE conf_name='%s'", $conf_name));
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $pcred = [
            'ip' => null,
            'login' => null,
            'pass' => null,
        ];

        if ($rows > 0) {
            $pcred['ip'] = $rows[0]['ip'];
            $pcred['login'] = $rows[0]['login'];
            $pcred['pass'] = $rows[0]['pass'];
        } else {
            throw new Exception("Not existing config file! Try to rebuild your query based on database! Existing configs:" . $confs);
        }
        // Execute main login ACL
        $text_exec = "acl " . $usr_name . "_proxy proxy_auth " . $usr_name . "\nacl proxy_ports_" . $conf_name . " port " . $port . '-' . ($port + $proxy_ttl - 1) . "\nhttp_access allow " . $usr_name . "_proxy proxy_ports_" . $conf_name . "\n\n";

        $conf_text .= $text_exec;
        // End of login ACL

        // Cache peer's
        $text = "";
        if ($pcred['login'] !== null)
            $text = "
            cache_peer " . $pcred['ip'] . " parent %s 0 login=" . $pcred['login'] . ":" . $pcred['pass'] . " name=usr" . $usr_name . "_%s_proxy
            cache_peer_access usr" . $usr_name . "_%s_proxy allow ttl_%s port%s_acl us_proxy
            cache_peer_access usr" . $usr_name . "_%s_proxy deny all
            ";
        else
            $text = "
            cache_peer " . $pcred['ip'] . " parent %s 0 name=usr" . $usr_name . "_%s_proxy
            cache_peer_access usr" . $usr_name . "_%s_proxy allow ttl_%s port%s_acl us_proxy
            cache_peer_access usr" . $usr_name . "_%s_proxy deny all
            ";

        $main_port = 10000;

        // Regenerate user
        $pass = substr(md5(md5(rand(13513,6311641))), 0, 8); // Generate 8-digit md5-encrypted(to randomize string in vanilla php) password
        if($htpasswd->userExists($usr_name))
        {
            $htpasswd->updateUser($usr_name, $pass);
        } else {
            $htpasswd->addUser($usr_name, $pass);
        }
        //

        $ports_access = [];
        for ($i = 0; $i < $ports; $i += 4) {
            for ($m = 1; $m <= 4; $m++) {
                $port_def = $port + $i + $m - 1;
                $conf_text .= sprintf($text, $port_def, $port_def, $port_def, sprintf("%s_%s", $ttl, $m), $main_port, $port_def);
            }
            
            $ports_access[] = sprintf("%s:%s@%s:%s", $usr_name, $pass, $ip, $main_port);
            $main_port += 1;
        }
        
        // Rebuild user config file
        $conf_dir = '/etc/squid/user_conf/' . $usr_name . '.conf';
        if (file_exists($conf_dir)) {
            unlink($conf_dir);
        }
        $conf_fo = fopen($conf_dir, 'w+');
        fwrite($conf_fo, $conf_text);
        fclose($conf_fo);
        // End of rebuild
        http_response_code(200);
        echo json_encode($ports_access);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode($e->getMessage());
        die();
    }
}
?>