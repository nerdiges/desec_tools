<?php
require_once("tfa/class.tfa.php");

class kas_dns {
    # KAS auth data
    protected $user;
    protected $pass;
    protected $totp_secret = null;

    # KAS Session
    protected $session_lifetime = 600;
    protected $session_update = 'Y';
    protected $session_token;

    # flood protection timeout
    protected $flood_protection = 1;

    # data structure to store domain infos
    protected $domains = null;

    # data structure to store DNS zones
    protected $dns_zones = null;
 
    public function __construct($u, $p, $t = null) {
        $this->new_session_token($u, $p, $t);
    }

    public function __cdestruct() {
        unset(
            $this->user,
            $this->pass,
            $this->totp_secret,
            $this->session_lifetime,
            $this->session_update,
            $this->session_token,
            $this->flood_protection,
            $this->domains,
            $this->dns_zones
        );
    }

    # generate TOTP token based on $this->totp_secret
    public function get_totp() {
        $tfa = new tfa();
        return $tfa->getOtp($this->totp_secret);
    }
    
    # get KAS session token
    public function new_session_token($u, $p, $t = null) {
        $this->user = $u;
        $this->pass = $p;
        $this->totp_secret = $t;

        # Get KAS Authentication Token
        try {
            $Params=array(
                'kas_login' => $this->user,                         // das KAS-Login
                'kas_auth_type' => 'plain',
                'kas_auth_data' => $this->pass,                     // das KAS-Passwort
                'session_lifetime' => $this->session_lifetime,      // Gültigkeit des Tokens in Sekunden
                'session_update_lifetime' => $this->session_update  // Session mit jedem Request verlängern
            );
            if (isset($this->totp_secret)) {
                $Params['session_2fa'] = $this->get_totp();        // optional: falls aktiviertm die One-Time-Pin für die 2FA
            }
            $SoapLogon = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl');
            $this->session_token = $SoapLogon->KasAuth(json_encode($Params));
            sleep($this->flood_protection); # avoid flood protection
        }
        catch (SoapFault $fault) {
            trigger_error(" Fehlernummer: {$fault->faultcode},
                            Fehlermeldung: {$fault->faultstring},
                            Verursacher: {$fault->faultactor},
                            Details: {$fault->detail}", E_USER_ERROR);
        }
    }
    public function get_session_token() { return $this->session_token; }

    public function set_session_lifetime(int $value)  { $this->session_lifetime = $value; }
    public function get_session_lifetime()            { return $this->session_lifetime; }

    public function set_session_update(bool $value)    { 
        if ( $value == false ) { 
            $this->session_update = 'N'; 
        } else { $this->session_update = 'Y'; } 
    } 
    public function get_session_update()               { return $this->session_lifetime; }
    public function set_flood_protectione(int $value)  { $this->session_lifetime = $value; }
    public function get_flood_protection()             { return $this->session_lifetime; }

    # get all domains assigned to the KAS account
    public function get_domains(bool $force_reload = false) {
        if (isset($this->domains) && ! $force_reload) {
            return $this->domains;
        } else {
            try {
                $SoapRequest = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl');
                $req = $SoapRequest->KasApi(json_encode(array(
                        'kas_login' => $this->user,             // KAS-User
                        'kas_auth_type' => 'session',           // Auth per Sessiontoken
                        'kas_auth_data' => $this->session_token,// Auth-Token
                        'kas_action' => 'get_domains',          // API-Funktion
                        'KasRequestParams' => null           	// Parameter an die API-Funktion
                        )));
                sleep($this->flood_protection); # avoid flood protection
                $this->domains = $req['Response']['ReturnInfo']; 
                return $this->domains;
            }
            // Fehler abfangen und ausgeben
            catch (SoapFault $fault) {
                print("WARNING: Could not retrieve Domains of KAS account ".$this->user.".\n");
                sleep($this->flood_protection); # avoid flood protection
                return null;
            }
        }
    }

    # get DNS records of a domain
    public function get_zone_records(string $domain) {
    	try {
            $Params = array(
                'zone_host' => $domain
            ); // Parameter für die API-Funktion
        
            $SoapRequest = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl');
            $req = $SoapRequest->KasApi(json_encode(array(
                    'kas_login' => $this->user,                 // KAS-User
                    'kas_auth_type' => 'session',             // Auth per Sessiontoken
                    'kas_auth_data' => $this->session_token,  // Auth-Token
                    'kas_action' => 'get_dns_settings',       // API-Funktion
                    'KasRequestParams' => $Params           	// Parameter an die API-Funktion
                    )));
            sleep($this->flood_protection); # avoid flood protection
            return $req['Response']['ReturnInfo'];
        }
        // Fehler abfangen und ausgeben
        catch (SoapFault $fault) {
            print("WARNING: ".$domain." not found in KAS account ".$this->user.". Nothing to do.\n");
            sleep($this->flood_protection); # avoid flood protection
            return null;
        }
    }

    # get all DNS records of all domains assigned to the KAS account
    public function get_dns_zones(bool $force_reload = false) {
        if (isset($this->dns_zones) && ! $force_reload) {
            return $this->dns_zones;
        } else {
            $this->dns_zones = null;
            foreach ($this->get_domains() as $domain) {
                $this->dns_zones[$domain['domain_name']] = $this->get_zone_records($domain['domain_name']);
            }
            return $this->dns_zones;
        }
    }

    # get all DKIM records of the domains assigned to the KAS account
    public function get_dkim_records() {
        $result = null;
        foreach ($this->get_dns_zones() as $zone) {
            foreach ($zone as $record) {
                if (str_starts_with($record["record_name"], "kas") && 
                    str_ends_with($record["record_name"], "._domainkey") &&
                    $record["record_type"] == "TXT") {
                        $result[] = $record;
                }                 
            }
        }
        return $result;
    }

    # get all DynDNS records of the domains assigned to the KAS account
    public function get_dyndns_records() {
        $result = null;
        foreach ($this->get_dns_zones() as $zone) {
            foreach ($zone as $record) {
                if (str_starts_with($record["record_data"], "dyndns") && 
                    str_ends_with($record["record_data"], ".kasserver.com.") &&
                    $record["record_type"] == "NS") {
                        $result[] = $record;
                }                 
            }
        }
        return $result;
    }

    # check whether a DNS record with the given parameters exists. If yes, return DNS record
    public function find_record(string $domain, string $subname, string $type, string $value = null) {
        if (! in_array($domain, array_keys($this->get_dns_zones()))) {
            print("WARNING: Domain ".$domain." does not exist in KAS account.\n");
            return -1;
        }
        foreach ($this->dns_zones[$domain] as $record) {
            if ($record["record_name"] == $subname && 
                $record["record_type"] == $type) {
                    if ($value == null) {
                        return $record;    
                    }
                    if ($record["record_data"] == $value) {
                        return $record;    
                    } 
           }                     
        }
        return null;
    }

}

?>