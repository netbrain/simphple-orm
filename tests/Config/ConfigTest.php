<?php

namespace SimphpleOrm\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase {

    /**
     * This tests will pass with data from config.ini.sample
     */
    public function testCanGetMysqlConfig() {
        $config = Config::getMysql();
        $this->assertNotNull($config);
        $this->assertNotNull($config['username']);
        $this->assertNotNull($config['password']);
        $this->assertNotNull($config['host']);
        $this->assertNotNull($config['database']);
        $this->assertNull($config['port']);
        $this->assertNull($config['socket']);
    }
}