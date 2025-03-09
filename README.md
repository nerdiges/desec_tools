# deSEC-Tools

[all-inkl](https://all-inkl.com) unterstützt leider kein DNSSEC direkt. Aber es kann die DNS-Verwaltung für eine Domäne an einen DNS-Provider mit DNSSEC wie [deSEC.io](https://deSEC.io) ausgelagert werden. Dazu müssen im KAS-Verwaltungstool von all-inkl. lediglich der DNS-Server von deSEC eingetragen werden und anschliessend noch über den all-inkl-Support der Domainkey beim Top-Level-Registrar hinterlegt werden.

Damit die Umstellung reibungslos funktioniert müssen z.B. auch noch die bisher konfigurierten DNS-Records bei deSEC oder die DynDNS-Accounts bei deSEC neu angelegt werden. Vor allem aber muss sichergestellt werden, dass jederzeit die korrekten DKIM-Records bei deSEC hinterlegt sind.

Mit dieser Tool-Sammlung sollen diese Aufgaben vereinfacht werden. Folgende Tools stehen zur Verfügung


|Script|Beschreibung|
|---|---|
|sync_kas_dkim.php|Synchronsiert die DKIM Einträge von KAS nach deSEC. Da sich bei einem Serverumzug die DKIM-Records ändern können, sollte eine Synchronisierung regelmäßig durchgeführt werden.|
|migrate_kas_zones.php|Mit diesem Script können einzelne Domänen oder auch alle konfigurierten Records einer in KAS verwalteten Domäne nach deSEC übertragen werden. NS-Records (z.B. von den in KAS konfigurierten DynDNS-Einträge) werden dabei nicht übertragen, damit DNSSEC vollständig umgesetzt ist.|
|gen_ddns_token.php|Legt bei deSEC ein neues Access-Token an. Das Token ist dabei über Policies so eingeschränkt, dass damit nur die A und AAAA Records für die an das Script übergebenen Hosts geändert werden können. Damit ist dann auch eine sichere Verwaltung von DynDNS-Host-Einträgen über [dedyn.io](https://desec.readthedocs.io/en/latest/dyndns/configure.html) möglich.|

## Anwendung der Scripte 
### sync_kas_dkim.php
Wenn die Zugangsdaten in der DAtei config.php richtig hinterlegt sind, sind keine weiteren Parameter erforderlich.

### migrate_kas_zones.php
```
Usage:  migrate_kas_zones.php [DOMAIN DOMAIN DOMAIN ...]

Description:
    Read all DNS records configured in KAS account and create the records in deSEC
    account. If no domain names are given on command line, all domains configured
    in KAS are migrated. Otherwise just the given Domains are migrated. NS records
    are not migrated to ensure proper functionality of DNSSEC.

Parameter:
    -h       Show this help page.
    DOMAIN   Domain that should be migrated to deSEC.  

Example: migrate_kas_zones.php hlpme.de nerdig.es

Version: 0.1.0
```

### gen_ddns_token.php
```
Usage:  gen_ddns_token.php  FQDN [FQDN FQDN ...]

Description:
    Create an access token for deSEC. Token is restricted and can only be used to
    change the A and AAAA records of the domains given on command line.

Parameter:
    -h      Show this help page.
    FQDN    Full qualified domain name for DynDNS client. If multiple FQDNs are
            given, the token generated can be used for several DynDNS clients.

Example: gen_ddns_token.php dyndns.hlpme.de dyndns.nerdig.es

Version: 0.1.0
```


## Installation
Die Toolsammlung wird wie folgt installiert:

**1. Download der Dateien**

```
dpkg -l git || apt install git
git clone https://github.com/nerdiges/desec-tools.git /opt/desec-tools
chmod +x /opt/desec-tools/*.php
cp /opt/desec-tools/config.php.sample /opt/desec-tools/config.php
```

**2. config.php anpassen**

In der Datei config.php müssen noch die Zugangsdaten für KAS und deSEC hinterlegt werden:

```
<?php
# KAS auth data. Multiples KAS accounts can be synced when added to the array. 
$kas_credentials = array(
    "your_kas_user1" => "your_kas_pwd1",
    "your_kas_user2" => "your_kas_pwd2"
);
$kas_totp_secret = 'your_totp_secret'; 

# Auth Info to access deSec API
$desec_token= "your_desec_token";

?>
```

**3. Einrichten der systemd-Services (optional)**
Sollen die Scripte nur bei Bedarf manuell ausgeführt werden, dann ist die Einrichtung von Services nicht erforderlich.
Sollen allerdings z.B. regelmäßig die DKIM-Records synchronisiert werden, dann kann das Script sync_kas_dkim.php entweder über cron oder über systemd regelmäßig gestartet werden:

```
# Install udm-backup.service and timer definition file in /etc/systemd/system via:
ln -s /opt/desec-tools/sync_kas_dkim.service /etc/systemd/system/sync_kas_dkim.service
ln -s /opt/desec-tools/sync_kas_dkim.timer /etc/systemd/system/sync_kas_dkim.timer

# Reload systemd, enable and start the systemd timer:
systemctl daemon-reload
systemctl enable --now sync_kas_dkim.timer

# check status of timer
systemctl status sync_kas_dkim.timer 
```

## Update

Das Script-Sammlung kann mit folgenden Befehlen aktualisiert werden:
```
cd <your_path>/desec_tools
git pull origin
```

## Credits
(Dima Tsvetkov)[https://github.com/dimamedia/PHP-Simple-TOTP-and-PubKey] for the light weight TFA class.