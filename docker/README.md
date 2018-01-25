## Guide To Setup Local Development via Docker
### 1. Istall docker
For OSX install dlite according to [keboola/connection setup](https://github.com/keboola/connection/blob/master/DOCKER.md#mac-osx)

### 2. Build docker services
```bash
docker-compose build
```
or in `docker` directory run
```bash
make build-images
```
### 3. Setup devel environment
go to `docker` directory
```bash
cd ./docker
```
and then having file `parameters_shared.yml` prepared in the current directory(docker) run make command that prepares everything
```bash
make docker-dev
```
this command does the following:
- cleans vendor dir
- prepares cache dir
- runs composer install (requires interaction during installation to type `s` - skip copying parameters.yml )
- set 777 permissions on vendor dir
- copies both parameters files
- adjust logs
- migrates DB - creates DB tables

### 4. Logging
Script `./adjust-logs.sh` or `make adjust-logs` command in `docker` dir to adjusts logs to dump the exceptions log directly to the screen. See the `adjust-logs.sh` script for more details. This script is called automatically on `make docker-dev` command;

### 5. Running app
run all services
```bash
docker-compose up apache
```
or from `docker` dir
```bash
make up
```

The api is then running on the following URL:
`http://docker.local:8000/app.php/oauth-v2`

To run bash on the running server type:
```bash
docker-compose run --rm apache bash
```
or from `docker` dir
```bash
make bash
```

### 6. Running tests

```bash
make test
```

or 

```bash
docker-compose run --rm apache ./vendor/bin/phpunit
```

## Database
Database is setup and initialized with structure defined in `./Resources/sql` and running on port *8701*.

*Credentials to db:*
host: docker.local
port: 8701
database: oauth_v2
username: docker
passwor: docker
ROOT_PASSWORD: root
