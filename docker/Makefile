## devel setup helpers and docker-compose commands helpers

DEBUGFILE = "../vendor/keboola/syrup/src/Keboola/Syrup/Debug/ExceptionHandler.php"
bash:
	docker-compose run --rm apache /bin/bash
up:
	docker-compose up
down:
	docker-compose down
test:
	docker-compose run --rm tests
clean-vendor:
	sudo rm -rf ../vendor
clean-logs:
	sudo rm -rf ../s3logs

clean-all: clean-vendor clean-logs

composer-install:
	docker-compose run --rm apache composer install

setup-dirs:
	docker-compose run --rm apache mkdir -p /var/www/html/vendor/keboola/syrup/app/cache
	docker-compose run --rm apache chmod -R 777 /var/www/html/vendor/
copy-parameters-yml:
	cp docker-parameters.yml ../vendor/keboola/syrup/app/config/parameters.yml
	cp parameters_shared.yml ../vendor/keboola/syrup/app/config/

adjust-logs:
	chmod +x ./adjust-logs.sh
	./adjust-logs.sh

docker-dev: clean-all composer-install setup-dirs copy-parameters-yml adjust-logs

build-images:
	docker-compose build
reset-db:
	sudo rm -rf ./.mysql-datadir/
