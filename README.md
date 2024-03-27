# OPNsenseCertUpdate.php
Scripted update of a OPNsense certificate

This PHP script replaces certificates in the OPNsense.
It essentially does the same thing as the Python script of the same name.

- copy /conf/config.xml from the OPNsense to the local directory
- load the new certificate and key file
- replace the certificate and keys in config.xml with the new ones
- copy the current config.xml to the Backup directory on the OPNsense
- copy the modified config.xml back to the OPNsense
- start the OPNsense WebGUI and synchronize config to backup OPNsense

Preparation in the OPNsense GUI:
- import the certificate manually for the first time
- add the public key to a user with admin rights for ssh login without password

Run the script:

./OPNsenseCertUpdate.php OPNsenseIpAddr OPNsenseCertName fullchainCertPath privateKeyPath OPNsenseUser

Example:

/opt/bin/OPNsenseCertUpdate 172.23.1.1 LEWildcardCert /var/lib/certificates/fullchain.pem /var/lib/certificates/privkey.pem root
