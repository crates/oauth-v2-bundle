<?php
use Keboola\Temp\Temp;

class OAuthTestCase extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Temp
	 */
	protected $temp;

	protected function getTemp()
	{
		if (empty($this->temp)) {
			$this->temp = new Temp('restbox-test');
		}
		return $this->temp;
	}

	protected static function callMethod($obj, $name, array $args)
	{
		$class = new \ReflectionClass($obj);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method->invokeArgs($obj, $args);
	}

	protected function getLogger($name = 'test', $null = false)
	{
		return new \Monolog\Logger(
			$name,
			$null ? [new \Monolog\Handler\NullHandler()] : []
		);
	}

	protected function cloneFile($pathname)
	{
		$temp = $this->getTemp();

		$tmpFile = $temp->createTmpFile();
		copy($pathname, $tmpFile->getPathName());
		return $tmpFile->getPathName();
	}
}
