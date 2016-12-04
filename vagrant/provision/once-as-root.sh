#!/usr/bin/env bash

#== Import script args ==

timezone=$(echo "$1")

#== Bash helpers ==

function info {
  echo " "
  echo "--> $1"
  echo " "
}

#== Provision script ==

info "Provision-script user: `whoami`"

info "Allocate swap for MySQL 5.6"
fallocate -l 2048M /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap defaults 0 0' >> /etc/fstab

#info "Allocate moar file watchers"
#echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p
#sysctl --system

info "Configure locales"
update-locale LC_ALL="C"
dpkg-reconfigure locales

info "Configure timezone"
echo ${timezone} | tee /etc/timezone
dpkg-reconfigure --frontend noninteractive tzdata

info "Prepare root password for MySQL"
debconf-set-selections <<< "mysql-server-5.6 mysql-server/root_password password \"''\""
debconf-set-selections <<< "mysql-server-5.6 mysql-server/root_password_again password \"''\""
echo "Done!"

info "Update OS software"
apt-get update
apt-get upgrade -y

info "Install additional software"
apt-get install -y git php5-curl php5-cli php5-intl php5-mysqlnd php5-gd php5-fpm nginx mysql-server-5.6 php5-xdebug

info "Configure PHP-FPM"
sed -i 's/user = www-data/user = vagrant/g' /etc/php5/fpm/pool.d/www.conf
sed -i 's/group = www-data/group = vagrant/g' /etc/php5/fpm/pool.d/www.conf
sed -i 's/owner = www-data/owner = vagrant/g' /etc/php5/fpm/pool.d/www.conf
echo "Done!"

info "Configure NGINX"
sed -i 's/user www-data/user vagrant/g' /etc/nginx/nginx.conf
echo "Done!"

info "Configure alternate MySQL instance"
sudo cp /etc/mysql/my.cnf /usr/share/mysql/my-default.cnf
cp -prvf /etc/mysql/ /etc/mysql2
mkdir -p /var/lib/mysql2
chown --reference /var/lib/mysql /var/lib/mysql2
chmod --reference /var/lib/mysql /var/lib/mysql2
mkdir -p /var/log/mysql2
chown --reference /var/log/mysql /var/log/mysql2
chmod --reference /var/log/mysql /var/log/mysql2
echo "[client]
port		= 3337
socket		= /var/run/mysqld/mysqld2.sock
[mysqld_safe]
socket		= /var/run/mysqld/mysqld2.sock
nice		= 0
[mysqld]
user		= mysql
pid-file	= /var/run/mysqld/mysqld2.pid
socket		= /var/run/mysqld/mysqld2.sock
port		= 3337
basedir		= /usr
datadir		= /var/lib/mysql2
tmpdir		= /tmp
lc-messages-dir	= /usr/share/mysql
skip-external-locking
bind-address		= 127.0.0.1
key_buffer		= 16M
max_allowed_packet	= 16M
thread_stack		= 192K
thread_cache_size       = 8
myisam-recover         = BACKUP
query_cache_limit	= 1M
query_cache_size        = 16M
log_error = /var/log/mysql2/error.log
expire_logs_days	= 10
max_binlog_size         = 100M
[mysqldump]
quick
quote-names
max_allowed_packet	= 16M
[mysql]
[isamchk]
key_buffer		= 16M
!includedir /etc/mysql2/conf.d/" > /etc/mysql2/my.cnf
sed --in-place 's/mysqld/mysqld2/g' /etc/mysql2/debian.cnf
sed --in-place '/\}/d' /etc/apparmor.d/usr.sbin.mysqld
echo '/etc/mysql2/*.pem r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/etc/mysql2/conf.d/ r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/etc/mysql2/conf.d/* r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/etc/mysql2/*.cnf r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/var/lib/mysql2/ r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/var/lib/mysql2/** rwk,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/var/log/mysql2/ r,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/var/log/mysql2/* rw,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/{,var/}run/mysqld/mysqld2.pid w,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '/{,var/}run/mysqld/mysqld2.sock w,' >> /etc/apparmor.d/usr.sbin.mysqld
echo '}' >> /etc/apparmor.d/usr.sbin.mysqld
rm /etc/apparmor.d/usr.sbin.mysqld
ln -s /app/vagrant/apparmor/usr.sbin.mysqld /etc/apparmor.d/.
/etc/init.d/apparmor restart
mysql_install_db --user=mysql --datadir=/var/lib/mysql2
sed -i 's/key_buffer/key_buffer_size/' /etc/mysql2/my.cnf
nohup mysqld_safe --defaults-file=/etc/mysql2/my.cnf --skip-grant-tables &


info "Initialize databases for MySQL"
mysql -uroot <<< "CREATE SCHEMA xa_transactions_test;"
echo "Done!"

info "Initialize databases for MySQL"
mysql -uroot -P 3337 -h 127.0.0.1 <<< "CREATE SCHEMA xa_transactions_test;"
echo "Done!"

info "Install composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

info "Configure XDEBUG"
echo "xdebug.remote_enable=1
xdebug.default_enable=1
xdebug.profiler_enable=1
xdebug.remote_handler=dbgp
xdebug.remote_autostart=1
xdebug.remote_host=10.0.2.2
xdebug.max_nesting_level=256
" >> /etc/php5/mods-available/xdebug.ini

echo "xdebug.idekey=CLI" >> /etc/php5/cli/php.ini

sed --in-place '/session.save_handler/d' /etc/php5/fpm/php.ini
sed --in-place 's/sendfile\ on/sendfile\ off/g' /etc/nginx/nginx.conf
