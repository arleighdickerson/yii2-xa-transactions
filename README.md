XA Transactions in yii2
see https://dev.mysql.com/doc/refman/5.6/en/xa.html

to run tests:

```bash
#!/bin/bash
cp vagrant/config/vagrant-local.example.yml vagrant/config/vagrant-local.yml
```
Update vagrant/config/vagrant-local.yml with your personal github token

```bash
#!/bin/bash
vagrant up
vagrant ssh
cd /app
./vendor/bin/phpunit
```

moar docs to follow
