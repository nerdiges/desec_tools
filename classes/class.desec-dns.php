<?php

class desec_dns {

    # curl handle
    protected $curl = null;
 
    # data structure to store domain infos
    protected $domains = null;

    # data structure to store DNS zones
    protected $dns_zones = null;

    public function __construct(string $token) {
        # init curl
        $desec_headers = array(
            "Authorization: Token ".$token,
            "Content-Type: application/json"
        );
        
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_HEADER, 0);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $desec_headers);
    }

    public function __destruct() {
        unset(
            $this->curl
        );
    }

    # delete a dns record from $domain
    public function delete_dns_record(string $domain, string $subname, string $type) {
		curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/domains/".$domain."/rrsets/".$subname."/".$type);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
		$data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);
    
        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when trying to delete ".$subname." from ".$domain.".\n");
            return null;
        } else {
            # Reload DNS records after delete
            $this->get_dns_zones(true);
            return json_decode($data, true);
        }
    }

    # create a dns record in $domain
    public function create_dns_record(string $domain, string $subname, string $type, string $record, int $ttl = 3600) {
        $current = $this->find_record($domain, $subname, $type);
        if ($current == -1) { 
            print("INFO: Skip creation of ".$subname."|".$record." for non-configured domain ".$domain.".\n");
            return null;
        }
        $value = str_replace('"', '\"', $record);
        if (is_array($current)) {
            foreach ($current["records"] as $r) {
                if (str_replace('" "', '', $r) == $record) {
                    print("INFO: Skip creation of ".$subname."|".$record.". Record already in ".$domain.".\n");
                    return null;    
                } else {
                    $value = $value . '","' . str_replace('"', '\"', $r);
                }
            }
        }

        $body = '[{"subname": "'.$subname.'", "type": "'.$type.'", "ttl": '.$ttl.', "records": ["'.$value.'"]}]';
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/domains/".$domain."/rrsets/");
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);

        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when trying to create ".$subname." from ".$domain.".\n");
            return null;
        } else {
            # Reload DNS records after creation
            $this->get_dns_zones(true);
            return json_decode($data, true);
        }
    }


    # get all domains assigned to the desec account
    public function get_domains(bool $force_reload = false) {
        if (isset($this->domains) && ! $force_reload) {
            return $this->domains;
        } else {
            curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/domains/"); 
            curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "GET");
            $data = curl_exec($this->curl);
            $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            curl_close($this->curl);
            
            if ($http_code >= 400) {
                print("WARNING: Could not retrieve Domains of desec.\n");
                return null;
            } else { 
                $this->domains = json_decode($data, true); 
                return $this->domains;
            }
        }
    }

    # create a new DNS zone
    public function create_domain(string $name) {
        $body = '{"name": "'.$name.'"}';
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/domains/");
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);

        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when trying to create Domain ".$domain.".\n");
            return null;
        } else {
            # Reload DNS records after creation
            $this->get_dns_zones(true);
            $this->get_domains();
            return json_decode($data, true);
        }
    }

    # get DNS records of a domain
    public function get_zone_records(string $domain) { 
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/domains/".$domain."/rrsets/"); 
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "GET");
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);

        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when requesting infos from deSEC for " . $domain . ".\n");
            print("         Possible Reasons: Domain not configured in deSec or API key invalid.\n");
            return null;
        } else {
            return json_decode($data, true);
        }
    }

    # get all DNS records of all domains assigned to the desec account
    public function get_dns_zones(bool $force_reload = false) {
        if (isset($this->dns_zones) && ! $force_reload) {
            return $this->dns_zones;
        } else {
            $this->dns_zones = null;
            foreach ($this->get_domains() as $domain) {
                $this->dns_zones[$domain['name']] = $this->get_zone_records($domain['name']);
            }
            return $this->dns_zones;
        }
    }

    # get all DKIM records of the domains assigned to the KAS account
    public function get_dkim_records() {
        $result = null;
        foreach ($this->get_dns_zones() as $zone) {
            foreach ($zone as $record) {
                if (str_starts_with($record["subname"], "kas") && 
                    str_ends_with($record["subname"], "._domainkey") &&
                    $record["type"] == "TXT") {
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
                if (str_starts_with($record["records"][0], "dyndns") && 
                    str_ends_with($record["records"][0], ".kasserver.com.") &&
                    $record["type"] == "NS") {
                        $result[] = $record;
               }                 
            }
        }
        return $result;
    }

    # check whether a DNS record with the given parameters exists. If yes, return DNS record
    public function find_record(string $domain, string $subname, string $type, string $value = null) {
        if (! in_array($domain, array_keys($this->get_dns_zones()))) {
            print("WARNING: Domain ".$domain." does not exist in desec account.\n");
            return -1;
        }
        foreach ($this->dns_zones[$domain] as $record) {
            if ($record["subname"] == $subname && 
                $record["type"] == $type) {
                    if ($value == null) {
                        return $record;    
                    }
                    foreach ($record["records"] as $r) {
                        if (str_replace('" "', '', $r) == $value) {
                            return $record;    
                        } 
                    }
           }                     
        }
        return null;
    }

    # List access tokens
    public function list_tokens() {
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/auth/tokens"); 
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "GET");
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);
    
        if ($http_code >= 400) {
            print("ERROR: Received HTTP code " . $http_code . " when requesting tokenlist from deSEC.\n");
            print($data);
            return null;
        } else {
            return json_decode($data, true);
        }
    }

    # Create general access token
    # Example for $param:
    #      $param = array(
    #          "name" => "testtoken",
    #          "allowed_subnets" => array("0.0.0.0/0", "::/0"),
    #          "perm_create_domain" => false,
    #          "perm_delete_domain" => false,
    #          "perm_manage_tokens" => false,
    #          "max_age" => null,
    #          "max_unused_period" => null
    #       );
    public function create_token(array $param) {
        $body = json_encode($param);
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/auth/tokens/");
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);

        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when trying to create a general access token.\n");
            print($data);
            return null;
        } else {
            return json_decode($data, true);
        }
    }

    # Modify an access token
    # Example for $param:
    #      $param = array(
    #          "name" => "testtoken",
    #          "allowed_subnets" => array("0.0.0.0/0", "::/0"),
    #          "perm_create_domain" => false,
    #          "perm_delete_domain" => false,
    #          "perm_manage_tokens" => false,
    #          "max_age" => null,
    #          "max_unused_period" => null
    #       );
    public function modify_token(string $id, array $param) {
        $body = json_encode($param);
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/auth/tokens/".$id."/");
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $data = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        curl_close($this->curl);

        if ($http_code >= 400) {
            print("WARNING: Received HTTP code " . $http_code . " when trying to create a general access token.\n");
            print($data);
            return null;
        } else {
            # Reload DNS records after creation
            return json_decode($data, true);
        }
    }

    # Create restricted DynDNS token
    public function create_dyndns_token(array $fqdns) {
        $subnames = "";
        $bodies = array('{"domain": null, "subname": null, "type": null}'); # Default read policy
        foreach ($fqdns as $fqdn) {
            if (! (filter_var($fqdn, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && substr_count($fqdn, ".") > 1)) {
                print("WARNING: " . $fqdn . " is not a valid FQDN - skipping.\n");
                continue;
            }
            $subname = strtok($fqdn, '.');
            $domain = ltrim(strstr($fqdn, '.'), '.');

            $bodies[] = '{"domain": "'.$domain.'", "subname": "'.$subname.'", "type": "A", "perm_write": true}';
            $bodies[] = '{"domain": "'.$domain.'", "subname": "'.$subname.'", "type": "AAAA", "perm_write": true}';        

            if ($subnames == "") {
                $subnames = $subname;                
            } else {
                $subnames = $subnames . ", " . $subname;
            }
        }

        # create general token first and restrict usage afterwards
        $token = $this->create_token(array("name"=>"DDNS ".$subname));
        
        # init curl
        curl_setopt($this->curl, CURLOPT_URL, "https://desec.io/api/v1/auth/tokens/".$token["id"]."/policies/rrsets/");
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");

        foreach ($bodies as $body) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
            $data = curl_exec($this->curl);
            $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            curl_close($this->curl);
            if ($http_code >= 400) {
                print("ERROR: Received HTTP code " . $http_code . " when creating policy '".$body."' for DDNS token.\n");
                print($data);
                return null;
            }     
        }
        
        return $token;
    }
}
?>