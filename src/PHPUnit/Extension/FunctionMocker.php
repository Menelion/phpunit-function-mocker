<?php
namespace PHPUnit\Extension;

use PHPUnit\Extension\FunctionMocker\CodeGenerator;
use PHPUnit\Framework\TestCase;

class FunctionMocker
{
    /** @var TestCase */
    private $testCase;

    /** @var string */
    private $namespace;

    /** @var array */
    private $functions = array();

    /** @var array */
    private static $mockedFunctions = array();

    private function __construct(TestCase $testCase, $namespace)
    {
        $this->testCase = $testCase;
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Create a mock for the given namespace to override global namespace functions.
     *
     * Example: PHP global namespace function setcookie() needs to be overridden in order to test
     * if a cookie gets set. When setcookie() is called from inside a class in the namespace
     * \Foo\Bar the mock setcookie() created here will be used instead to the real function.
     *
     * @param TestCase $testCase
     * @param string $namespace
     * @return FunctionMocker
     */
    public static function start(TestCase $testCase, $namespace)
    {
        return new static($testCase, $namespace);
    }

    public static function tearDown()
    {
        unset($GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER']);
    }

    public function mockFunction($function)
    {
        $function = trim(strtolower($function));

        if (!in_array($function, $this->functions, true)) {
            $this->functions[] = $function;
        }

        return $this;
    }

    public function getMock()
    {
        $mock = $this->testCase->getMockBuilder('stdClass')
            ->setMethods($this->functions)
            ->setMockClassName('PHPUnit_Extension_FunctionMocker_' . uniqid())
            ->getMock();

        foreach ($this->functions as $function) {
            $fqFunction = $this->namespace . '\\' . $function;
            if (in_array($fqFunction, static::$mockedFunctions, true)) {
                continue;
            }

            if (!extension_loaded('runkit') || !ini_get('runkit.internal_override')) {
                CodeGenerator::defineFunction($function, $this->namespace);
            } elseif (!function_exists('__phpunit_function_mocker_' . $function)) {
                runkit_function_rename($function, '__phpunit_function_mocker_' . $function);
                error_log($function);
                runkit_method_redefine(
                    $function,
                    function () use ($function) {
                        if (!isset($GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER'][$this->namespace])) {
                            return call_user_func_array('__phpunit_function_mocker_' . $function, func_get_args());
                        }

                        return call_user_func_array(
                            array($GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER'][$this->namespace], $function),
                            func_get_args()
                        );
                    }
                );
                var_dump(strlen("foo"));
            }

            static::$mockedFunctions[] = $fqFunction;
        }

        if (!isset($GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER'])) {
            $GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER'] = array();
        }

        $GLOBALS['__PHPUNIT_EXTENSION_FUNCTIONMOCKER'][$this->namespace] = $mock;

        return $mock;
    }
}
