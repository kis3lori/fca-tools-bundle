<?php

namespace AppBundle\Service;


use AppBundle\Document\Scale;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class CsvTableService
{
    /**
     * @var Kernel
     */
    protected $kernel;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->kernel = $container->get('kernel');
    }

    /**
     * @param Scale $scale
     * @return array
     */
    public function getTableData($scale)
    {
        $csvFilePath = $this->kernel->getRootDir() . "/../" . $scale->getCsvFilePath();
        $csvFileContents = file_get_contents($csvFilePath);

        return $this->getTableDataFromFileContents($csvFileContents);
    }

    /**
     * @param string $csvFileContent
     *
     * @return array
     */
    public function getTableDataFromFileContents($csvFileContent)
    {
        $csvFileContent = CommonUtils::simpleTrim($csvFileContent);
        $csvFileContent = str_replace(",,", ",\"\",", $csvFileContent);
        $csvFileContent = str_replace("\n", "],[", $csvFileContent);
        $csvFileContent = "[[" . $csvFileContent . "]]";

        $data = json_decode($csvFileContent);
        $columns = array();

        foreach ($data[0] as $column) {
            $columns[] = CommonUtils::trim($column);
        }

        $tableData = array(
            "columns" => $columns,
            "data" => array()
        );

        unset($data[0]);
        foreach ($data as $row) {
            $values = array();

            foreach ($row as $key => $value) {
                $values[$columns[$key]] = CommonUtils::trim($value);
            }

            $tableData["data"][] = $values;
        }

        return $tableData;
    }

}
