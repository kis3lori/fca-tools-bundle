<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Statistics;
use AppBundle\Document\User;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DatabaseConnectionService
{

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
    }

    /**
     * @param DatabaseConnection $databaseConnection
     * @param $tableName
     * @return array
     */
    public function getTableData(DatabaseConnection $databaseConnection, $tableName)
    {
        $config = new Configuration();
        $connectionParams = array(
            'dbname' => $databaseConnection->getName(),
            'user' => $databaseConnection->getUsername(),
            'password' => $databaseConnection->getPassword(),
            'host' => $databaseConnection->getHost() . ':' . $databaseConnection->getPort(),
            'driver' => ($databaseConnection->getType() == "mysql" ? 'pdo_mysql' : ''),
        );

        $conn = DriverManager::getConnection($connectionParams, $config);
        $sql = "SELECT * FROM " . $tableName;
        $stmt = $conn->query($sql);

        $tableData = array(
            "columns" => array(),
        );
        if ($stmt->rowCount() != 0) {
            $tableData["data"] = $stmt->fetchAll();
            $tableData["columns"] = array_keys($tableData["data"][0]);
        }

        return $tableData;
    }

    /**
     * @param DatabaseConnection $databaseConnection
     * @return array
     */
    public function getTables(DatabaseConnection $databaseConnection)
    {
        $config = new Configuration();
        $connectionParams = array(
            'dbname' => $databaseConnection->getName(),
            'user' => $databaseConnection->getUsername(),
            'password' => $databaseConnection->getPassword(),
            'host' => $databaseConnection->getHost() . ':' . $databaseConnection->getPort(),
            'driver' => ($databaseConnection->getType() == "mysql" ? 'pdo_mysql' : ''),
        );

        $conn = DriverManager::getConnection($connectionParams, $config);
        $sql = "SHOW TABLES";
        $stmt = $conn->query($sql);

        $tables = array();
        $result = $stmt->fetchAll();
        foreach ($result as $item) {
            $tables[] = array_pop($item);
        }

        return $tables;
    }
}
