<?php

namespace Keboola\DeploymentHandler;

use Composer\Script\Event;
use Composer\IO\IOInterface;
use Aws\S3\S3Client;


class ScriptHandler
{
    const PARAMETERS_DIR = "vendor/keboola/syrup/app/config/";

    public static function getSharedParameters(Event $event)
    {
        self::getFile($event, "parameters_shared.yml", "syrup/parameters_shared.yml");
    }

    public static function getParameters(Event $event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();
        if (empty($extra['syrup-app-name'])) {
            // interactive if $event->getIO()->isInteractive() ?
            throw new \Exception('The app name (ie "ex-dummy") has to be set in composer.json "extra": {"syrup-app-name": "ex-dummy"}');
        }

        $appName = $extra['syrup-app-name'];

        self::getFile($event, "parameters.yml", "syrup/{$appName}/parameters.yml");
    }

    /**
     * @param Event $event
     * @param       $filename
     * @param       $s3key
     * @throws \Exception
     */
    protected static function getFile(Event $event, $filename, $s3key)
    {
        $env = getenv('SYRUP_ENV');

        if (!$env) {
            $env = $event->isDevMode()?'dev':'prod';
        }

        if (!in_array($env, ['dev', 'test', 'prod'])) {
            throw new \Exception('SYRUP_ENV only accepts value "dev", "test" or "prod"');
        }

        if ($env == 'dev') {
            if ($event->getIO()->isInteractive()) {
                $event->getIO()->askAndValidate(
                    "<comment>Get <question>{$filename}</question> from development S3 bucket? [<options=bold>y</options=bold>/n/s]:
y - yes <info>(default)</info>
n - no <info>(manually input folder containing the file)</info>
s - skip <info>(keep current file)</info>
</comment>",
                    function ($answer) use ($event, $s3key, $filename, $env) {
                        switch ($answer) {
                            case "y":
                                self::getFromS3($event->getIO(), $s3key, self::PARAMETERS_DIR . $filename, $env);
                                break;
                            case "n":
                                self::getFromIO($event->getIO(), $filename);
                                break;
                            case "s":
                                break;
                            default:
                                throw new \InvalidArgumentException("Invalid option! Please choose either y, n or s");
                        }
                    },
                    3,
                    "y"
                );
            } elseif (file_exists("./{$filename}")
                && $event->getIO()->askConfirmation("Use './{$filename}'? [<options=bold>y</options=bold>/n]", true)) {
                self::getFromIO($event->getIO(), $filename, ".");
            } else {
                self::getFromS3($event->getIO(), $s3key, self::PARAMETERS_DIR . $filename, $env);
            }
        } else {
            self::getFromS3($event->getIO(), $s3key, self::PARAMETERS_DIR . $filename, $env);
        }
    }

    /**
     * @brief Ask for a path to a file
     * @param IOInterface $io
     * @param string      $filename parameters.yml or parameters_shared.yml
     * @param string      $pathname file to look for before asking in IO
     * @throws \Exception
     * @internal param Event $event
     */
    protected static function getFromIO(IOInterface $io, $filename, $pathname = "")
    {
        if (!file_exists($pathname . "/" . $filename)) {
            if ($io->isInteractive()) {
                $try = 0;
                while (!file_exists($pathname . '/' . $filename)) {
                    if ($try >= 3) {
                        throw new \InvalidArgumentException("3 attempts exhausted, {$filename} not found!");
                    }
                    if ($try > 0) {
                        $io->write("<error>File <options=bold;fg=yellow>{$pathname}/{$filename}</options=bold;fg=yellow> does not exist!</error>");
                    }

                    $try++;
                    $pathname = $io->ask("<comment>Path to folder containing '{$filename}':</comment> ", $pathname);
                }
            } else {
                throw new \Exception("Failed to retrieve {$filename} from IO: Input is not interactive");
            }
        }

        $dest = self::PARAMETERS_DIR . $filename;
        copy($pathname . '/' . $filename, $dest);
        $io->write("<info>File {$pathname}/{$filename} copied to {$dest}</info>");
    }

    /**
     * @brief Download a file from S3 to $path
     *
     * @param string $key "path" to a file on S3
     * @param string $path Local path to save the file within app
     * @param string $env environment variable, one of 'dev', 'test' or 'prod'
     * @return void
     */
    protected static function getFromS3(IOInterface $io, $key, $path, $env)
    {
        $bucket = 'keboola-configs';
        if ($env == 'test') {
            $bucket = 'keboola-configs-testing';
        } elseif ($env == 'dev') {
            $bucket = 'keboola-configs-devel';
        }

        if (getenv('KEBOOLA_SYRUP_CONFIGS_BUCKET')) {
            $bucket = getenv('KEBOOLA_SYRUP_CONFIGS_BUCKET');
        }

        $awsRegion = 'us-east-1';
        if (getenv('AWS_REGION')) {
            $awsRegion = getenv('AWS_REGION');
        }

        $client = new S3Client(array(
            'version' => '2006-03-01',
            'region' => $awsRegion
        ));
        $client->getObject(array(
            'Bucket' => $bucket,
            'Key'    => $key,
            'SaveAs' => $path
        ));
        $io->write("<info>File <comment>{$path}</comment> downloaded from S3 ({$bucket})</info>");
    }
}