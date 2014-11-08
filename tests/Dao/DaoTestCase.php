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
     * @var Database
     */
    protected $database;

    /**
     * @var DaoFactory
     */
    protected $daoFactory;


    public static function setUpBeforeClass() {
        self::configureLogging();
    }

    protected function setUp() {
        $this->configureDatabase();
        $this->dropTables();
        $this->daoFactory = new DaoFactory($this->database);
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

    private function configureDatabase() {
        $config = Config::getMysql();
        $mysqli = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);
        if ($mysqli->connect_errno) {
            self::$logger->error("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
        }
        $this->database = new Database($mysqli);
    }


    private function dropTables() {
        self::runQuery("SET FOREIGN_KEY_CHECKS = 0");
        self::runQueryOnAllTables("DROP TABLE %s");
        self::runQuery("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function runQueryOnAllTables($query) {
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
        $fixtureSql = preg_replace('/^[ ]+/', ' ', explode("\n", $fixtureSql));
        $fixtureSql = preg_replace('/[ ]+/', ' ', $fixtureSql);
        $fixtureSql = join('', $fixtureSql);
        $fixtureSql = preg_replace('/\( /', '(', $fixtureSql);
        $fixtureSql = preg_replace('/, /', ',', $fixtureSql);

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
    protected function runQuery($sql) {
        $mysqli = $this->database->getMysqli();
        $result = $mysqli->query($sql);
        self::$logger->debug("Ran query: " . $sql);
        if (!$result) {
            self::fail($mysqli->error);
        }
        return $result;
    }

}