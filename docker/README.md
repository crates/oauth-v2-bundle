## Guide To Setup Local Development via Docker
### 1. For OSX install dlite according to [keboola/connection setup](https://github.com/keboola/connection/blob/master/DOCKER.md#mac-osx)

### 2. Build docker services
```bash
docker-compose build
```
or in docker directory run
```bash
make build-images
```
### 3. Setup devel environment
go to docker directory
```bash
cd ./docker
```
and then having file `parameters_shared.yml` prepared in the current directory(docker) run make command that prepares everything
```bash
make docker-dev
```
this command cleans vendor dir then prepares cache dir,runs composer install, set 777 permissions on vendor dir and copies both parameters files

### 4. Adjust logs
Run the foolowing command to adjust logs to dump the exceptions log directly to the screen.
```bash
sed -i '' -e '/echo json_encode($response);/a\
var_dump($logData); die;' "../vendor/keboola/syrup/src/Keboola/Syrup/Debug/ExceptionHandler.php"
```
Logs are then found in `./s3Logs` folder.

### 5. Running app
run all services
```bash
docker-compose up
```
or from docker dir
```bash
make up
```

The api is then running on the following URL:
`http://docker.local:8000/app.php/oauth-v2`

To run bash on the running server type:
```bash
docker-compose run --rm apache bash
```
or from docker
```bash
make bash
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
