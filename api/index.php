<?php declare(strict_types = 1);

use Nette\Neon\Neon;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use PHPStan\Analyser\Analyser;
use PHPStan\DependencyInjection\ContainerFactory;

require __DIR__ . '/../vendor/autoload.php';

final class App
{

	public static ?string $ROOT_DIR;
	public static ?string $TMP_DIR;
	public static ?string $PHPSTAN_TMP_DIR;
	public static ?string $CODE_FILE;
	public static ?string $PHPSTAN_FILE;

	public static function init()
	{
		if (self::isVercel()) {
			self::$TMP_DIR = '/tmp';
		} else {
			self::$TMP_DIR = self::$ROOT_DIR . '/tmp';
		}

		self::$ROOT_DIR = realpath(__DIR__ . '/..');
		self::$PHPSTAN_TMP_DIR = self::$TMP_DIR . '/phpstan';
		self::$CODE_FILE = self::$TMP_DIR . '/code.php';
		self::$PHPSTAN_FILE = self::$TMP_DIR . '/phpstan.neon';
	}

	public static function error(string $error, int $code = 400): void
	{
		self::json(['error' => $error], $code);
	}

	public static function json(array $data, int $code = 200): void
	{
		self::header('content-type', 'application/json');
		self::statusCode($code);
		echo Json::encode($data);
		exit();
	}

	public static function isPreflight(): bool
	{
		return mb_strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS';
	}

	public static function cors(): void
	{
		self::header('Access-Control-Allow-Origin', '*');
		self::header('Access-Control-Allow-Methods', '*');
		self::header('Access-Control-Allow-Headers', '*');
	}

	public static function header(string $name, string $value): void
	{
		header($name . ': ' . $value, false);
	}

	public static function statusCode(int $code): void
	{
		http_response_code($code);
	}

	public static function terminate(): void
	{
		exit();
	}

	public static function isVercel(): bool
	{
		return !($_SERVER['VERCEL_REGION'] ?? null);
	}

	public static function analyse(): void
	{
		$code = $_POST['code'] ?? null;
		$level = $_POST['level'] ?? 9;

		// Code is required
		if (!$code) {
			self::error('No code given');
			self::terminate();
		}

		// Dump code.php
		FileSystem::write(self::$CODE_FILE, "<?php " . Strings::replace($code, '#^\<\?php#', ''));

		// Dump phpstan.neon
		FileSystem::write(self::$PHPSTAN_FILE, Neon::encode([
			'includes' => [
				'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/conf/staticReflection.neon',
			],
			'parameters' => [
				'inferPrivatePropertyTypeFromConstructor' => true,
				'treatPhpDocTypesAsCertain' => true,
				'phpVersion' => 80000,
			],
		]));

		// Require PHPStan's internal files
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionUnionType.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionIntersectionType.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionAttribute.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Attribute.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Enum/UnitEnum.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Enum/BackedEnum.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Enum/ReflectionEnum.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Enum/ReflectionEnumUnitCase.php';
		require_once 'phar://' . self::$ROOT_DIR . '/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Enum/ReflectionEnumBackedCase.php';

		// Create PHPStan container
		$containerFactory = new ContainerFactory(self::$PHPSTAN_TMP_DIR);
		$container = $containerFactory->create(
			self::$PHPSTAN_TMP_DIR,
			[sprintf('%s/config.level%s.neon', $containerFactory->getConfigDirectory(), $level), self::$PHPSTAN_FILE],
			[self::$CODE_FILE]
		);

		// Analyse code
		/** @var Analyser $analyser */
		$analyser = $container->getByType(Analyser::class);
		$results = $analyser->analyse([self::$CODE_FILE], null, null, false, [self::$CODE_FILE])->getErrors();

		// Collect errors
		$errors = [];
		foreach ($results as $result) {
			if (is_string($result)) {
				$errors[] = [
					'message' => $result,
					'line' => 1,
				];
			} else {
				$errors[] = [
					'message' => $result->getMessage(),
					'line' => $result->getLine(),
				];
			}
		}

		self::json(['errors' => $errors, 'level' => $level]);
	}

}

App::init();

// Check if it's preflight request
if (App::isPreflight()) {
	App::cors();
	App::terminate();
}

// Analyse given code
App::cors();
App::analyse();
