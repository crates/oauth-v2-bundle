## Guide To Setup Local Development via Docker
### 1. For OSX install dlite according to [keboola/connection setup](https://github.com/keboola/connection/blob/master/DOCKER.md#mac-osx)

### 2. Build docker services
```bash
docker-compose build
```
### 3. Run composer install
```bash
docker-compose run --rm apache composer install
```
### 4. set permissions for cache and s3 logs
docker-compose run --rm apache chmod -R 777 /var/www/html/vendor/keboola/syrup/app/cache
docker-compose run --rm apache chmod -R 777 /var/www/html/s3logs

### 5. copy parameters
##### parameters.yml
```bash
cp docker/docker-parameters.yml ./vendor/keboola/syrup/app/config/parameters.yml
```
##### shared_arameters.yml
You must download `shared_parameters.yml` from s3 bucket `keboola-configs-testing` and copy accordingly to `./vendor/keboola/syrup/app/config/`


### 6. Adjust logs
Instead of uploading logs to S3 rewrite function `uploadString` in `/vendor/keboola/syrup/src/Keboola/Syrup/Aws/S3/Uploader.php` to upload log files locally such as:
```php
    public function uploadString($name, $content, $contentType = 'text/plain')
    {
        $s3FileName = sprintf('%s-%s-%s', date('Y/m/d/Y-m-d-H-i-s'), uniqid(), $name);
        $localfilename = '/var/www/html/s3logs/' . $s3FileName;
        (new \Symfony\Component\Filesystem\Filesystem())->dumpFile($localfilename, $content);
        return $localfilename;
    }
```
Logs are then found in `./s3Logs` folder.

### 7. Running app
run all services
```bash
docker-compose up
```

The api is then running on the following URL:
`http://docker.local:8000/app.php/oauth-v2`

To run bash on the running server type:
```bash
docker-compose run --rm apache bash
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
