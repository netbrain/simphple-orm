<?php

namespace SimphpleOrm\Dao;


use Logger;
use mysqli;
use SimphpleOrm\Config\Config;

abstract class DaoTestFramework extends \PHPUnit_Framework_TestCase {

    /**
     * @var Logger
     */
    protected static $logger;


    /**
     * @var mysqli
     */
    protected static $db;

    function __construct(){

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
        self::$db = new mysqli($config['host'], $config['username'], $config['password'], $config['database'],$config['port'],$config['socket']);
        if (self::$db->connect_errno) {
            self::$logger->error("Failed to connect to MySQL: (" . self::$db->connect_errno . ") " . self::$db->connect_error);
        }
    }

    public function tearDown() {
        self::runQueryOnAllTables("TRUNCATE TABLE %s");
    }

    public static function tearDownAfterClass() {
        self::runQueryOnAllTables("DROP TABLE %s");
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

    private static function runQueryOnAllTables($query) {
        $showTablesQuery = "SHOW TABLES";
        $result = self::runQuery($showTablesQuery);
        if ($result->num_rows > 0) {
            while (true) {
                $row = $result->fetch_row();
                if ($row == null) {
                    break;
                }
                self::runQuery(sprintf($query,$row[0]));
            }
        }
    }

}