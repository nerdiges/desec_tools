#!/usr/bin/php
<?php

require_once("config.php");
require_once("classes/class.desec-dns.php");

$version = "0.1.0";
$me = basename($argv[0]);
array_shift($argv);


function usage() {
    global $me, $version;

    echo <<< END

Usage:  $me  FQDN [FQDN FQDN ...]

Description:
    Create an access token for deSEC. Token is restricted and can only be used to
    change the A and AAAA records of the domains given on command line.

Parameter:
    -h      Show this help page.
    FQDN    Full qualified domain name for DynDNS client. If multiple FQDNs are
            given, the token generated can be used for several DynDNS clients.

Example: $me dyndns.hlpme.de dyndns.nerdig.es

Version: $version


END;
}

if ($argc < 2) { 
    usage(); 
    exit(1);
}

foreach ($argv as $fqdn) {
    if (! (filter_var($fqdn, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && substr_count($fqdn, ".") > 1)) {
        usage();
        exit(1);
    }
    $fqdns[] = $fqdn;
}

$desec = new desec_dns($desec_token);
$ddns_token = $desec->create_dyndns_token($fqdns);
$token = $ddns_token['token'];

echo <<< END

Hallo,

du kannst folgende Zugangsdaten für dein DynDNS verwenden:

    Update URL v4/v6:   https://update.dedyn.io
    Update URL v6 only: https://update6.dedyn.io
    Domain/User:        $fqdns[0]
    Token:              $token

Konfiguration für die Fritz!Box:

    DDNS Provuder:  desec.io bzw. User defined 
    Update URL:     https://update.dedyn.io/?myipv4=<ipaddr>&myipv6=<ip6addr>
    Domain:         $fqdns[0]
    Username:       $fqdns[0]
    Passwort:       $token

Weitere Informationen unter https://desec.readthedocs.io/en/latest/dyndns/configure.html.

Viel Erfolg... ;)
Stephan


END;
?>