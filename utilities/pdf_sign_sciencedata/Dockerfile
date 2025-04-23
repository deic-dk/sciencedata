# Build command: docker build -t sciencedata/pdf_sign_sciencedata .
# Push command: docker push sciencedata/pdf_sign_sciencedata
# Run command: docker run -p 8080:80 -p 4022:22 [-e ROOT_PASSWORD=my_root_password] -e SSH_PUBLIC_KEY=my_public_key sciencedata/pdf_sign_sciencedata
# Run command: docker run -p 8080:80 -p 4022:22 -e SSH_PUBLIC_KEY="`cat ~/.ssh/id_rsa.pub`" sciencedata/pdf_sign_sciencedata

FROM ubuntu:24.04
MAINTAINER Frederik Orellana "https://github.com/deltafunction"

LABEL vendor="sciencedata.dk"
LABEL version="1.0"
LABEL description="Ubuntu noble with X.509 signing web service for deployment on sciencedata.dk"

ENV DEBIAN_FRONTEND="noninteractive"
ENV LANG C.UTF-8

RUN echo "LC_ALL=en_US.UTF-8" >> /etc/environment
RUN echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen
RUN echo "LANG=en_US.UTF-8" > /etc/locale.conf
RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen
RUN apt update && apt install -y locales && \
    locale-gen

RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get -y install ca-certificates

RUN apt install -y gpg gpgv1 curl apt-transport-https

# Add cyberduck, get key manually due to apt-key deprecation
RUN echo "deb [signed-by=/usr/local/share/keyrings/cyberduck.gpg] https://s3.amazonaws.com/repo.deb.cyberduck.io stable main" | tee -a /etc/apt/sources.list.d/cyberduck.list \
    && mkdir -p /usr/local/share/keyrings \
    && gpg --keyserver keyserver.ubuntu.com --recv-keys FE7097963FEFBE72 \
    && gpg --export FE7097963FEFBE72 > /usr/local/share/keyrings/cyberduck.gpg \
    && rm -rf /root/.gnupg

# Add caddy
RUN echo 'deb [signed-by=/usr/share/keyrings/caddy.gpg] https://dl.cloudsmith.io/public/caddy/stable/deb/debian any-version main' >  /etc/apt/sources.list.d/caddy-stable.list
RUN curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' |  gpg --dearmor > /usr/share/keyrings/caddy.gpg

RUN DEBIAN_FRONTEND=noninteractive apt-get install -y openssh-client net-tools inetutils-tools curl \
bind9-dnsutils bind9-host psmisc vim pciutils dkms vlan unicode-data gnupg git golang duck cron \
apt-transport-https wget jq nfs-common caddy php-cli php-fpm dropbear openssh-sftp-server \
iputils-ping traceroute php-yaml php-xml php-sqlite3 php-mbstring php-gd php-curl php-zip \
poppler-utils libnss3-tools default-jre

RUN sed -i 's|^memory_limit = .*|memory_limit = 1024M|' /etc/php/*/*/php.ini

# "unminimize" script provided by ubuntu:22.04 container restores expected shell features like installing
# man pages. The script itself is interactive, so this a modified version that runs without interaction
#RUN sed -i 's/^read.*//g' /usr/local/sbin/unminimize \
#    && sed -i 's/exit 1/echo "skip"/g' /usr/local/sbin/unminimize \
#    && sed -i 's/apt-get upgrade/apt-get upgrade -y/g' /usr/local/sbin/unminimize \
#    && /usr/local/sbin/unminimize

# Configure locale
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y locales \
    && locale-gen --purge en_US.UTF-8 en_DK.UTF-8 da_DK.UTF-8

# Configure keyboard - not necessary
#RUN DEBIAN_FRONTEND=noninteractive apt-get -y install console-data

# Create user www with UID 80 - to support r/w on NFS-mounted ScienceData partition.
# When starting caddy as root, it switches to the user with $HOME /var/lib/caddy
RUN sed -i.bak -e 's/^%admin/#%admin/' /etc/sudoers && \
    sed -i.bak -e 's/^%sudo/#%sudo/' /etc/sudoers && \
    deluser caddy && \
    adduser --uid 80 --home /var/lib/caddy --disabled-password --gecos '' www && \
    chown -R www:www /var/lib/caddy

#RUN echo "www:secret" | chpasswd
RUN echo "www ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/www && chmod 0440 /etc/sudoers.d/www

# Configure caddy
RUN sed -i 's|www-data|www|' /etc/php/*/fpm/pool.d/www.conf
ADD Caddyfile /etc/caddy/Caddyfile
ADD index.php /var/www/index.php
ADD api.php /var/www/api.php
ADD start.sh /usr/local/sbin/start.sh
ADD open-pdf-sign.jar /var/lib/caddy/open-pdf-sign.jar
RUN chmod +x /usr/local/sbin/start.sh /var/www/index.php /var/www/api.php
RUN rmdir /var/log/caddy && touch /var/log/caddy.log && chown -R www:www /var/log/caddy.log /var/www

# Configure ssh access
RUN echo "alias ls='ls --color=auto'" >> .bashrc && \
  echo "PATH=${PATH}:/sbin/:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin" >> ~/.bashrc && \
  mkdir /var/lib/caddy/.ssh && touch /var/lib/caddy/.ssh/authorized_keys && \
  chown -R www:www /var/lib/caddy/.ssh && chmod -R go-rw /var/lib/caddy/.ssh

RUN apt-get -y autoremove && apt-get -y clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

EXPOSE 22
EXPOSE 80
# Caddy runs admin endpoint on port 2019
#EXPOSE 2019

CMD ["/usr/local/sbin/start.sh"]
