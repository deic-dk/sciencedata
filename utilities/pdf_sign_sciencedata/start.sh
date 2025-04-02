#!/bin/bash

# SSH access to account with sudo rights - or just set password for root
if [[ -n "$SSH_PUBLIC_KEY" ]]; then
	  echo "$SSH_PUBLIC_KEY" >> /var/lib/caddy/.ssh/authorized_keys
fi
if [[ -n "$ROOT_PASSWORD" ]]; then
	  echo "root:$ROOT_PASSWORD" | chpasswd;
fi

# Resolve sciencedata to the 10.2.0.0/24 address of the silo of the user
[[ -n $HOME_SERVER ]] && echo "$HOME_SERVER	sciencedata" >> /etc/hosts
[[ -n $HOME_SERVER ]] && echo "*/5 * * * * root grep sciencedata /etc/hosts || echo \"$HOME_SERVER	sciencedata\" >> /etc/hosts" > /etc/cron.d/sciencedata_hosts
[[ -n $PUBLIC_HOME_SERVER ]] && echo "$PUBLIC_HOME_SERVER" >> /tmp/public_home_server
[[ -n $SETUP_SCRIPT  && -f "$SETUP_SCRIPT" ]] && . "$SETUP_SCRIPT"

su www -c bash << "EOF"
# Grab ScienceData CA cert
curl -o ~/sciencedata.pem https://sciencedata.dk/my_ca_cert.pem
# Grab ScienceData signature icon
curl -o ~/sciencedata_signature.png https://sciencedata.dk/themes/deic_theme_oc7/core/img/sciencedata_signature.png
# Configure nss
mkdir -p ~/.pki/nssdb
certutil -N -d ~/.pki/nssdb --empty-password
certutil -d ~/.pki/nssdb/ -A -i ~/sciencedata.pem -n ScienceData -t CT,CT,CT
EOF

service cron start
phpfpm=`service --status-all 2>&1 | grep php | awk '{print $NF}'`
service $phpfpm start
ln -s `basename $(ls /run/php/php*-fpm.sock)` /run/php/php-fpm.sock

export HOSTNAME
/usr/bin/caddy --config /etc/caddy/Caddyfile start
/usr/sbin/dropbear -p 22 -W 65536 -F -E
