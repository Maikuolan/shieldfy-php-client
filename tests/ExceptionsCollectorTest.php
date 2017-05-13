<?php
namespace Shieldfy\Test;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Shieldfy\Config;
use Shieldfy\Collectors\ExceptionsCollector;
use Exception;
use ErrorException;



use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;


class ExceptionsCollectorTest extends TestCase
{
    protected $root;
    protected $config;
    public $callbackCheckValue = 1;
    public function setUp()
    {
        //set virtual filesystem
        $this->root = vfsStream::setup();
        mkdir($this->root->url().'/log/', 0700, true);
        mkdir($this->root->url().'/src/', 0700, true);
        $config = new Config();
        //$config['debug'] = true;
        $config['rootDir'] = $this->root->url().'/src/';
        $this->config = $config;
    }

    public function testHandleErrors()
    {
        $exceptions = new ExceptionsCollector($this->config);
        $exceptions->listen(function(){
            $this->assertTrue(true);
        });
        $this->expectException(Error::class);
        $exceptions->handleErrors(1, 'h', 'h.php', 2, []);
    }

    public function testHandleExceptions()
    {
        $exceptions = new ExceptionsCollector($this->config);
        $exceptions->listen(function(){
            $this->assertTrue(true);
        });
        $customException = new Exception('Hello');
        $exceptions->handleExceptions($customException);
    }

    public function testInternalErrorLog()
    {
        $exceptions = new ExceptionsCollector($this->config);
        $exceptions->listen(function(){});
        $this->expectException(Error::class);
        $exceptions->handleErrors(1, 'h',$this->root->url().'/src/index.php', 2, []);
    }

}
