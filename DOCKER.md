```shell
# Remove previous container
docker rm -f adminer-php-9999

# Start PHP 5.6 container
docker run \
	--log-driver local \
	--name adminer-php-9999 \
	--publish=9999:8000 \
	--volume ~/Documents/adminer:/usr/src/app \
	--workdir /usr/src/app \
	--detach \
	--interactive \
	--tty \
	php:5.6-alpine \
	php -S 0.0.0.0:8000

# Attach shell to PHP container
docker exec -it adminer-php-9999 /bin/sh

# Connect PHP container to a network where DB is
docker network connect net adminer-php-9999

# Install PostgreSQL extensions for PHP
wget -O - https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions | sh -s pgsql pdo_pgsql

# Restart PHP container
docker restart adminer-php-9999
```

* [Adminer](http://localhost:9999/adminer)
