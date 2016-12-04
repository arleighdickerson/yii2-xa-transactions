#!/usr/bin/env bash

#== Bash helpers ==

function info {
  echo " "
  echo "--> $1"
  echo " "
}

#== Provision script ==

info "Provision-script user: `whoami`"

info "Restart web-stack"
service php5-fpm restart
service nginx restart
service mysql restart

if [ -a /var/run/mysqld/mysqld/mysqld2.sock ]; then
echo 'mysql server on port 3337 already running';
else
    echo 'starting mysql server on port 3337';
    sudo nohup mysqld_safe --defaults-file=/etc/mysql2/my.cnf --skip-grant-tables &
fi
