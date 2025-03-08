#!/usr/bin/php
<?php

require_once("config.php");

require_once("classes/class.kas-dns.php");
require_once("classes/class.desec-dns.php");

$desec = new desec_dns($desec_token);
$dkims_desec = $desec->get_dkim_records();

foreach (array_keys($kas_credentials) as $kas_user) {
	$kas = new kas_dns($kas_user, $kas_credentials[$kas_user], $kas_totp_secret);
	$dkims_kas = $kas->get_dkim_records();

	# Remove all non used DKIM records from deSEC
	print("INFO: Removing unused DKIM records from deSEC account.\n");
	foreach ($dkims_desec as $dkim) {
		$value = trim(str_replace('" "', '', $dkim["records"][0]), '"');
		if ($kas->find_record($dkim["domain"], 
							$dkim["subname"], 
							$dkim["type"],
							$value) == null) {
			print("INFO: Deleting ".$dkim["subname"]." from ".$dkim["domain"].".\n");
			$desec->delete_dns_record($dkim["domain"], $dkim["subname"], $dkim["type"]);
		} else {
			print("INFO: Keeping ".$dkim["subname"]." in ".$dkim["domain"].".\n");
		}
	}

	# create missing DKIM records in deSEC account
	print("\nINFO: Creating missing DKIM records in deSEC account.\n");
	foreach ($dkims_kas as $dkim) {
		print("INFO: Creating ".$dkim["record_name"]." in ".$dkim["record_zone"].".\n");
		$desec->create_dns_record($dkim["record_zone"], $dkim["record_name"], $dkim["record_type"], '"'.$dkim["record_data"].'"');
	}

	unset($kas);
}

?>
