## devel setup helpers and docker-compose commands helpers

DEBUGFILE = "../vendor/keboola/syrup/src/Keboola/Syrup/Debug/ExceptionHandler.php"
bash:
	docker-compose run --rm apache /bin/bash
up:
	docker-compose up
down:
	docker-compose down
clean-vendor:
	sudo rm ../vendor -rf
clean-logs:
	sudo rm ../s3logs -rf

clean-all: clean-vendor clean-logs

composer-install:
	docker-compose run --rm apache composer install

setup-dirs:
	docker-compose run --rm apache mkdir -p /var/www/html/vendor/keboola/syrup/app/cache
	docker-compose run --rm apache chmod -R 777 /var/www/html/vendor/
copy-parameters-yml:
	cp docker-parameters.yml ../vendor/keboola/syrup/app/config/parameters.yml
	cp parameters_shared.yml ../vendor/keboola/syrup/app/config/

docker-dev: clean-all composer-install setup-dirs copy-parameters-yml

build-images:
	docker-compose build
adjust-logs:
	sed -i '' -e '/echo json_encode($$response);/a\
	var_dump($$logData); die;' $(DEBUGFILE)
