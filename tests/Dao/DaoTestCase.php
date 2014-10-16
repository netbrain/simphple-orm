<?php

namespace SimphpleOrm\Dao;


use Logger;
use mysqli;
use SimphpleOrm\Config\Config;

abstract class DaoTestCase extends \PHPUnit_Framework_TestCase {

    /**
     * @var Logger
     */
    protected static $logger;


    /**
     * @var mysqli
     */
    protected static $db;

    function __construct() {

    }


    public static function setUpBeforeClass() {
        self::configureLogging();
        self::configureMysqli();
    }

    private static function configureLogging() {
        Logger::configure(array(
            'rootLogger' => array(
                'appenders' => array('default'),
            ),
            'appenders' => array(
                'default' => array(
                    'class' => 'LoggerAppenderConsole',
                    'layout' => array(
                        'class' => 'LoggerLayoutSimple'
                    )
                )
            )
        ));
        self::$logger = Logger::getRootLogger();
    }

    private static function configureMysqli() {
        $config = Config::getMysql();
        self::$db = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);
        if (self::$db->connect_errno) {
            self::$logger->error("Failed to connect to MySQL: (" . self::$db->connect_errno . ") " . self::$db->connect_error);
        }
    }

    public static function tearDownAfterClass() {
        self::runQueryOnAllTables("DROP TABLE %s");
    }

    public function tearDown() {
        self::runQueryOnAllTables("TRUNCATE TABLE %s");
    }

    private static function runQueryOnAllTables($query) {
        $showTablesQuery = "SHOW TABLES";
        $result = self::runQuery($showTablesQuery);
        if ($result->num_rows > 0) {
            while (true) {
                $row = $result->fetch_row();
                if ($row == null) {
                    break;
                }
                self::runQuery(sprintf($query, $row[0]));
            }
        }
    }

    /**
     * @param $str
     * @param $function
     * @param mixed $_ [optional]
     * @return mixed|string
     */
    protected function assertEqualsFixture($str,$function, $_ = null) {
        $fixture = "tests/Fixtures/$function.sql";

        if (!file_exists($fixture)) {
            $this->fail("$fixture doesn't exist");
        }

        $fixtureSql = file_get_contents($fixture);
        $fixtureSql = preg_replace('/^[ ]+/', "", explode("\n", $fixtureSql));
        $fixtureSql = preg_replace('/[ ]+/', " ", $fixtureSql);
        $fixtureSql = join('', $fixtureSql);

        $args = array($fixtureSql);
        for ($x = 2; $x < func_num_args(); $x++) {
            $args[] = func_get_arg($x);
        }

        $fixtureSql = call_user_func_array("sprintf", $args);
        $this->assertEquals($fixtureSql, $str, __DIR__ . "/$fixture");
    }

    /**
     * @param $sql
     * @return bool|\mysqli_result
     */
    protected static function runQuery($sql) {
        $result = self::$db->query($sql);
        self::$logger->debug("Ran query: " . $sql);
        if (!$result) {
            self::fail(self::$db->error);
        }
        return $result;
    }

}