#!/bin/bash

set -v

sed -i '' -e '/echo json_encode($response);/a\
var_dump($logData); die;' "../vendor/keboola/syrup/src/Keboola/Syrup/Debug/ExceptionHandler.php"

sed -i '' -e '/getException/a\
echo $exception; die;' "../vendor/keboola/syrup/src/Keboola/Syrup/Listener/SyrupExceptionListener.php"
