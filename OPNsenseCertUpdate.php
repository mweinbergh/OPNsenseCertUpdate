#!/usr/bin/php
<?php
logger("Starting...");
$usage="Usage: $argv[0] OPNsenseIpAddr OPNsenseCertname fullchainCertPath privateKeyPath OPNsenseAdminUser";
if( $argc!=6 ) log_and_die("$usage\n");
if( !file_exists($argv[3]) ) log_and_die("$usage\nFullchain certificate $argv[3] not found\n");
if( !file_exists($argv[4]) ) log_and_die("$usage\nPrivate key $argv[4] not found\n");

update_opnsense_cert($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);

function update_opnsense_cert( $OPNsenseIpAddr, $OPNsenseCertname, $fullchain, $privkey, $OPNsenseAdminUser ) {
	$cwd=__DIR__;
	preg_match('/(.*):(.*)/', $OPNsenseIpAddr, $m);
	if( isset($m[2]) ) {
		$OPNsenseIpAddr=$m[1];
		$OPNsensePort=$m[2]?$m[2]:22;
	} else $OPNsensePort=22;
	$SSH="ssh -p $OPNsensePort -o StrictHostKeyChecking=no -o LogLevel=ERROR";
	$SCP="scp -q -P $OPNsensePort -o StrictHostKeyChecking=no -o LogLevel=ERROR";
	$fullchain = file_get_contents($fullchain);
	$cert=openssl_x509_parse($fullchain);
	$privkey = file_get_contents($privkey);
	$key=openssl_x509_parse($privkey);
	if( !isset($cert['validFrom']) || !isset($key['validFrom']) ) log_and_die("Invalid certificate or private key files\n");
	logger( "New certificate $cert[serialNumber] is valid from " . date('Y-m-d', $cert['validFrom_time_t']). " to " . date('Y-m-d', $cert['validTo_time_t']) );
	logger( "New private key $key[serialNumber] is valid from " . date('Y-m-d', $key['validFrom_time_t']). " to " . date('Y-m-d', $key['validTo_time_t']) );
	$fullchain = base64_encode($fullchain);
	$privkey = base64_encode($privkey);
	$localConfDir='OPNsense.conf';
	$localConf="$cwd/$localConfDir/config.xml";
	$localConfNew="$cwd/$localConfDir/config.new.xml";
	logger("Copying $OPNsenseIpAddr:/conf/config.xml from OPNsense to $localConf");
	system("mkdir -p $localConfDir; $SCP $OPNsenseAdminUser@$OPNsenseIpAddr:/conf/config.xml $localConf");
	
	$dom = new DOMDocument();
	$dom->load($localConf);
	$root = $dom->documentElement;
	foreach( $root->childNodes as $node ) {
		if( $node->nodeName=='cert') {
			$descr=$node->getElementsByTagName('descr');
			if( $descr->item(0)->nodeValue==$OPNsenseCertname ) {
				$crt=$node->getElementsByTagName('crt');
				$cert=openssl_x509_parse(base64_decode($crt->item(0)->nodeValue));
				logger( "Old certificate $cert[serialNumber] was valid from " . date('Y-m-d', $cert['validFrom_time_t']). " to " . date('Y-m-d', $cert['validTo_time_t']) );
				$crt->item(0)->nodeValue=$fullchain;
				$prv=$node->getElementsByTagName('prv');
				$key=openssl_x509_parse(base64_decode($prv->item(0)->nodeValue));
				logger( "Old private key $key[serialNumber] was valid from " . date('Y-m-d', $key['validFrom_time_t']). " to " . date('Y-m-d', $key['validTo_time_t']) );
				$prv->item(0)->nodeValue=$privkey;
				break;
			}
		}
	}
	logger("Saving $localConfNew");
	$dom->save($localConfNew);
	$bup="/conf/backup/config-" . microtime(true) . ".xml";
	logger("Backup /conf/config.xml to $bup on OPNsense");
	$cmd="$SSH $OPNsenseAdminUser@$OPNsenseIpAddr 'cp -a /conf/config.xml $bup'";
	system($cmd);
	logger("Copying $localConfNew to $OPNsenseIpAddr:/conf/config.xml");
	$cmd="$SCP $localConfNew $OPNsenseAdminUser@$OPNsenseIpAddr:/conf/config.xml";
	system($cmd);
	logger("Restarting OPNsense Weg GUI");
	$cmd="$SSH $OPNsenseAdminUser@$OPNsenseIpAddr '/usr/local/etc/rc.restart_webgui'";
	exec($cmd, $out); foreach( $out as $s ) logger($s);
	logger("Syncing to slave");
	$cmd="$SSH $OPNsenseAdminUser@$OPNsenseIpAddr '/usr/local/bin/flock -n -E 0 -o /tmp/ha_reconfigure_backup.lock /usr/local/etc/rc.filter_synchronize pre_check_master'";
	exec($cmd, $out); foreach( $out as $s ) logger($s);
	logger("Completed.");
}

function logger($s, $bti=0) {
	$backtrace = debug_backtrace();
	syslog( LOG_INFO, basename(__FILE__) . ":" . $backtrace[$bti]['line'] . " $s");
}

function log_and_die($s) {
	$as=explode("\n", trim($s));
	foreach($as as $v) logger($v, 1);
	logger("Died.", 1);
	die($s);
}
