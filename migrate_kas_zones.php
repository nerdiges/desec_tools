#!/usr/bin/php
<?php

require_once("config.php");

require_once("classes/class.kas-dns.php");
require_once("classes/class.desec-dns.php");

$version = "0.1.0";
$me = basename($argv[0]);
array_shift($argv);

function usage() {
    global $me, $version;

    echo <<< END

Usage:  $me [DOMAIN DOMAIN DOMAIN ...]

Description:
    Read all DNS records configured in KAS account and create the records in deSEC
    account. If no domain names are given on command line, all domains configured
    in KAS are migrated. Otherwise just the given Domains are migrated. NS records
    are not migrated to ensure proper functionality of DNSSEC.

Parameter:
    -h       Show this help page.
    DOMAIN   Domain that should be migrated to deSEC.  

Example: $me hlpme.de nerdig.es

Version: $version


END;
}

foreach ($argv as $domain) {
    if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        usage();
        exit(1);
    }
    $parameter[] = $domain;
}

$desec = new desec_dns($desec_token);
$desec_zones = $desec->get_dns_zones();

foreach (array_keys($kas_credentials) as $kas_user) {
    $kas = new kas_dns($kas_user, $kas_credentials[$kas_user], $kas_totp_secret);
	$kas_zones = $kas->get_dns_zones();

    $domains = array();
    if (isset($parameter)) {
        $domains = $parameter;
    } else {
        $domains = $kas->get_domains();
    }

	# create missing DNS records in deSEC account
	print("\nINFO: Creating missing DNS records in deSEC account.\n");
    foreach ($domains as $domain) {
        if (! isset($kas_zones[$domain])) {
            print("WARNING: Skipping zone ".$domain." as it not managed in KAS account ".$kas_user.".\n");
            continue;
        }

        # TODO: create Zone in deSec Account if not available 

        foreach ($kas_zones[$domain] as $record) {
            # Skip NS records as NS should be deSEC only due to DNSSEC
            switch ($record["record_type"]) {
                case "NS":
                    print("INFO: Skipping NS record ".$record["record_name"]." in ".$record["record_zone"].".\n");
                    break;
                case "TXT":
                    print("INFO: Creating ".$record["record_name"]." in ".$record["record_zone"].".\n");
                    $desec->create_dns_record($record["record_zone"], $record["record_name"], $record["record_type"], '"'.$record["record_data"].'"');
                    break;
                case "MX":
                    print("INFO: Creating ".$record["record_name"]." in ".$record["record_zone"].".\n");
                    $desec->create_dns_record($record["record_zone"], $record["record_name"], $record["record_type"], $record["record_aux"].' '.$record["record_data"]);
                    break;
                default:
                    print("INFO: Creating ".$record["record_name"]." in ".$record["record_zone"].".\n");
                    $desec->create_dns_record($record["record_zone"], $record["record_name"], $record["record_type"], $record["record_data"]);
                    break;
            }
        }
    }

	unset($kas);

}
?>