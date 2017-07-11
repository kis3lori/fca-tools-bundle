<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Scale;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\ParameterBag;

class ScaleService
{

    /**
     * @var StatisticsService
     */
    protected $statisticsService;

    /**
     * @var DatabaseConnectionService
     */
    protected $databaseConnectionService;

    /**
     * @var array
     */
    protected $fcaParams;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->statisticsService = $container->get("app.statistics_service");
        $this->databaseConnectionService = $container->get("app.database_connection_service");
        $this->fcaParams = $container->getParameter('fca');
    }

    /**
     * Generate a context from a scale having the given column as main objects.
     *
     * @param $scale Scale
     * @param $column string
     * @return Context
     */
    public function generateContextFromScale($scale, $column)
    {
        $tableData = $this->databaseConnectionService
            ->getTableData($scale->getDatabaseConnection(), $scale->getTable());

        $objects = array();
        $assocArray = array();
        foreach ($scale->getContext()->getDimension(0) as $index => $object) {
            $assocArray[$index] = array();
        }

        $language = new ExpressionLanguage();
        foreach ($tableData['data'] as $row) {
            $objects[] = $row[$column];

            foreach ($scale->getContext()->getDimension(0) as $index => $expression) {
                $result = $language->evaluate($expression, $row);
                if ($result) {
                    $assocArray[$index][] = $row[$column];
                    break;
                }
            }
        }
        sort($objects);
        $objectToIndex = array_flip($objects);

        $newRelations = array();
        foreach ($scale->getContext()->getRelations() as $relation) {
            foreach ($assocArray[$relation[0]] as $object) {
                $newRelations[] = array($objectToIndex[$object], $relation[1]);
            }
        }

        $newContext = new Context(true);
        $newContext->setDimCount(2);
        $newContext->setDimension(0, $objects);
        $newContext->setDimension(1, $scale->getContext()->getDimension(1));
        $newContext->setRelations($newRelations);

        return $newContext;
    }

    /**
     * Update a context by adding data based on the scale type.
     *
     * @param $context Context
     * @param $scale Scale
     * @param $postData ParameterBag
     * @param $errors array
     * @return array
     */
    public function updateContextByScaleType($context, $scale, $postData, $errors)
    {
        switch ($scale->getType()) {
            case "nominal":
                $column = $postData->get("column");
                $subType = $postData->get("subType");

                $data = array(
                    'column' => $column,
                );

                if ($subType == "simple") {
                    $tableData = $this->databaseConnectionService
                        ->getTableData($scale->getDatabaseConnection(), $scale->getTable());

                    $values = array();
                    foreach ($tableData['data'] as $row) {
                        $values[] = $row[$column];
                    }

                    $values = array_unique($values);
                } else {
                    $values = $postData->get("nominalScaleValues");
                    $data['nominalScaleValues'] = $values;
                }

                $scale->setData($data);

                $objects = array();
                $relationPairs = array();
                foreach ($values as $index => $value) {
                    $objects[] = $column . " == " . $value;
                    $relationPairs[] = array($index, $index);
                }

                $context->setDimension(0, $objects);
                $context->setDimension(1, $objects);
                $context->setRelations($relationPairs);

                break;
            case "ordinal":
                $column = $postData->get("column");
                $order = $postData->get("order");
                $bounds = $postData->get("bounds");

                $values = $postData->get("ordinalScaleValues");
                $values = array_map('floatval', $values);
                sort($values);

                $data = array(
                    'column' => $column,
                    'order' => $order,
                    'bounds' => $bounds,
                    'ordinalScaleValues' => $values,
                );

                $scale->setData($data);
                $mainSign = ($order == "increasing" ? ">" : "<") . ($bounds == "include" ? "=" : "");
                $objectSign = (($order == "increasing" xor $bounds == "include") ? "<=" : "<");
                $objectOpposingSign = (($order == "increasing" xor $bounds == "include") ? ">" : ">=");

                $previousValue = null;
                $objects = array();
                $attributes = array();
                foreach ($values as $index => $value) {
                    if ($index == 0) {
                        $objects[] = $column . " " . $objectSign . " " . $value;
                    } else {
                        $objects[] = $column . " " . $objectOpposingSign . " " . $previousValue . " and " .
                            $column . " " . $objectSign . " " . $value;
                    }

                    $attributes[] = $column . " " . $mainSign . " " . $value;
                    $previousValue = $value;
                }

                $objects[] = $column . " " . $objectOpposingSign . " " . end($values);

                $relationPairs = array();
                foreach ($objects as $objIndex => $obj) {
                    foreach ($attributes as $attrIndex => $attr) {
                        if (($order == "decreasing" && $objIndex <= $attrIndex)
                            || ($order == "increasing" && $objIndex > $attrIndex)
                        ) {
                            $relationPairs[] = array($objIndex, $attrIndex);
                        }
                    }
                }

                $context->setDimension(0, $objects);
                $context->setDimension(1, $attributes);
                $context->setRelations($relationPairs);

                break;
            case "inter-ordinal":
                $column = $postData->get("column");
                $boundsInclusionSide = $postData->get("boundsInclusionSide");

                $values = $postData->get("interOrdinalScaleValues");
                $values = array_map('floatval', $values);
                sort($values);

                $data = array(
                    'column' => $column,
                    'boundsInclusionSide' => $boundsInclusionSide,
                    'ordinalScaleValues' => $values,
                );

                $scale->setData($data);
                $lessSign = ($boundsInclusionSide == "increasing-side" ? "<" : "<=");
                $greaterSign = ($boundsInclusionSide == "increasing-side" ? ">=" : ">");;

                $previousValue = null;
                $objects = array();
                $attributes = array();
                $nrValues = count($values);
                foreach ($values as $index => $value) {
                    if ($index == 0) {
                        $objects[] = $column . " " . $lessSign . " " . $value;
                    } else {
                        $objects[] = $column . " " . $greaterSign . " " . $previousValue . " and " .
                            $column . " " . $lessSign . " " . $value;
                    }

                    $attributes[$index] = $column . " " . $lessSign . " " . $value;
                    $attributes[$index + $nrValues] = $column . " " . $greaterSign . " " . $value;
                    $previousValue = $value;
                }

                $objects[] = $column . " " . $greaterSign . " " . end($values);
                ksort($attributes);

                $relationPairs = array();
                foreach ($objects as $objIndex => $obj) {
                    for ($offset = 0; $offset < $nrValues; $offset++) {
                        $relationPairs[] = array($objIndex, $objIndex + $offset);
                    }
                }

                $context->setDimension(0, $objects);
                $context->setDimension(1, $attributes);
                $context->setRelations($relationPairs);

                break;
            case "grid":
                $firstGridScaleValues = $postData->get("firstGridScaleValues");
                $firstGridScaleValues = array_map('floatval', $firstGridScaleValues);
                sort($firstGridScaleValues);

                $secondGridScaleValues = $postData->get("secondGridScaleValues");
                $secondGridScaleValues = array_map('floatval', $secondGridScaleValues);
                sort($secondGridScaleValues);

                $data = array(
                    'scales' => array(
                        array(
                            'column' => $postData->get("firstScaleColumn"),
                            'order' => $postData->get("firstScaleOrder"),
                            'bounds' => $postData->get("firstScaleBounds"),
                            'scaleValues' => $firstGridScaleValues,
                        ),
                        array(
                            'column' => $postData->get("secondScaleColumn"),
                            'order' => $postData->get("secondScaleOrder"),
                            'bounds' => $postData->get("secondScaleBounds"),
                            'scaleValues' => $secondGridScaleValues,
                        ),
                    ),
                );

                $scale->setData($data);

                $objects = array();
                $attributes = array();
                $relationPairs = array();

                $column = array(
                    $data["scales"][0]["column"],
                    $data["scales"][1]["column"],
                );
                $order = array(
                    $data["scales"][0]["order"],
                    $data["scales"][1]["order"],
                );
                $bounds = array(
                    $data["scales"][0]["bounds"],
                    $data["scales"][1]["bounds"],
                );
                $values = array(
                    $data["scales"][0]["scaleValues"],
                    $data["scales"][1]["scaleValues"],
                );

                $mainSign = array(
                    ($order[0] == "increasing" ? ">" : "<") . ($bounds[0] == "include" ? "=" : ""),
                    ($order[1] == "increasing" ? ">" : "<") . ($bounds[1] == "include" ? "=" : "")
                );
                $objectSign = array(
                    (($order[0] == "increasing" xor $bounds[0] == "include") ? "<=" : "<"),
                    (($order[1] == "increasing" xor $bounds[1] == "include") ? "<=" : "<"),
                );
                $objectOpposingSign = array(
                    (($order[0] == "increasing" xor $bounds[0] == "include") ? ">" : ">="),
                    (($order[1] == "increasing" xor $bounds[1] == "include") ? ">" : ">="),
                );

                $stmt0 = null;
                $stmt1 = null;
                $previousValue = array(null, null);
                foreach ($values[0] as $index0 => $value0) {
                    if ($index0 == 0) {
                        $stmt0 = $column[0] . " " . $objectSign[0] . " " . $value0;
                    } else {
                        $stmt0 = $column[0] . " " . $objectOpposingSign[0] . " " . $previousValue[0] . " and " .
                            $column[0] . " " . $objectSign[0] . " " . $value0;
                    }

                    foreach ($values[1] as $index1 => $value1) {
                        if ($index1 == 0) {
                            $stmt1 = $column[1] . " " . $objectSign[1] . " " . $value1;
                        } else {
                            $stmt1 = $column[1] . " " . $objectOpposingSign[1] . " " . $previousValue[1] . " and " .
                                $column[1] . " " . $objectSign[1] . " " . $value1;
                        }

                        $objects[] = "(" . $stmt0 . ") and (" . $stmt1 . ")";
                        $previousValue[1] = $value1;
                    }

                    $stmt1 = $column[1] . " " . $objectOpposingSign[1] . " " . end($values[1]);
                    $objects[] = "(" . $stmt0 . ") and (" . $stmt1 . ")";

                    $attributes[] = $column[0] . " " . $mainSign[0] . " " . $value0;
                    $previousValue[0] = $value0;
                }

                $stmt0 = $column[0] . " " . $objectOpposingSign[0] . " " . end($values[0]);
                foreach ($values[1] as $index1 => $value1) {
                    if ($index1 == 0) {
                        $stmt1 = $column[1] . " " . $objectSign[1] . " " . $value1;
                    } else {
                        $stmt1 = $column[1] . " " . $objectOpposingSign[1] . " " . $previousValue[1] . " and " .
                            $column[1] . " " . $objectSign[1] . " " . $value1;
                    }

                    $objects[] = "(" . $stmt0 . ") and (" . $stmt1 . ")";
                    $attributes[] = $column[1] . " " . $mainSign[1] . " " . $value1;
                    $previousValue[1] = $value1;
                }

                $stmt1 = $column[1] . " " . $objectOpposingSign[1] . " " . end($values[1]);
                $objects[] = "(" . $stmt0 . ") and (" . $stmt1 . ")";

                $nrValues0 = count($values[0]);
                $nrValues1 = count($values[1]);
                for ($objIndex = 0; $objIndex < count($objects); $objIndex++) {
                    for ($attrIndex = 0; $attrIndex < count($attributes); $attrIndex++) {
                        $objIndex0 = intdiv($objIndex, ($nrValues1 + 1));
                        $objIndex1 = $objIndex % ($nrValues1 + 1);

                        if ($attrIndex < $nrValues0) {
                            $attrIndex0 = $attrIndex;

                            if (($order[0] == "decreasing" && $objIndex0 <= $attrIndex0)
                                || ($order[0] == "increasing" && $objIndex0 > $attrIndex0)
                            ) {
                                $relationPairs[] = array($objIndex, $attrIndex);
                            }
                        } else {
                            $attrIndex1 = $attrIndex - $nrValues0;

                            if (($order[1] == "decreasing" && $objIndex1 <= $attrIndex1)
                                || ($order[1] == "increasing" && $objIndex1 > $attrIndex1)
                            ) {
                                $relationPairs[] = array($objIndex, $attrIndex);
                            }
                        }
                    }
                }

                $context->setDimension(0, $objects);
                $context->setDimension(1, $attributes);
                $context->setRelations($relationPairs);

                break;
            case "custom":
            default:
                $dimensions = array_slice($this->fcaParams['dimensionsPlural'], 0, 2);

                for ($index = 0; $index < $context->getDimCount(); $index++) {
                    $paramName = $dimensions[$index];
                    $elements = $postData->get($paramName, array());
                    foreach ($elements as $elem) {
                        $context->addElement($index, CommonUtils::trim($elem));
                    }
                }

                foreach ($postData->get('relation_tuples', array()) as $tuple) {
                    $parts = explode("###", $tuple);
                    $relation = array();

                    for ($index = 0; $index < 2; $index++) {
                        $elemName = CommonUtils::trim($parts[$index]);
                        $elemId = array_search($elemName, $context->getDimension($index));
                        $relation[] = $elemId;
                    }

                    $context->addRelation($relation);
                }
        }

        return $errors;
    }

    /**
     * @param $errors array
     * @param $databaseConnection DatabaseConnection
     * @return mixed
     */
    public function validateDatabaseConnection($errors, $databaseConnection)
    {
        if (!$databaseConnection) {
            $errors["databaseConnectionId"] = "You have to select a database connection.";
        }

        return $errors;
    }

    /**
     * @param $errors array
     * @param $tableName string
     * @param $scaleName string
     * @param $scaleType string
     * @param $tables array
     * @return mixed
     */
    public function validateGenericScale($errors, $tableName, $scaleName, $scaleType, $tables)
    {
        if (!$tableName) {
            $errors["tableName"] = "You must select a table on which the scale to operate.";
        } else if (!in_array($tableName, $tables)) {
            $errors["tableName"] = "No table was not found with the given name in the database. Please select a valid table.";
        }

        if (!$scaleName) {
            $errors["scaleName"] = "The scale must have a name.";
        }

        if (!$scaleType) {
            $errors["scaleType"] = "You have to select a scale type.";
        } else if (!in_array($scaleType, array("nominal", "ordinal", "inter-ordinal", "grid", "custom"))) {
            $errors["scaleType"] = "The scale type is not valid.";
        }

        return $errors;
    }

    /**
     * @param $errors array
     * @param $scaleType string
     * @param $postData ParameterBag
     * @param $tableData array
     * @return mixed
     */
    public function validateScaleType($errors, $scaleType, $postData, $tableData)
    {
        switch ($scaleType) {
            case "nominal":
                $column = $postData->get("column");
                if (!$column) {
                    $errors["column"] = "The nominal scale must have a column defined.";
                } else if (!in_array($column, $tableData['columns'])) {
                    $errors["column"] = "No column was found with the given name. Please select a valid column.";
                }

                $subType = $postData->get("subType");
                if (!$subType) {
                    $errors["subType"] = "The nominal scale must be simple or custom.";
                } else if ($subType == "custom") {
                    if (!$postData->has("nominalScaleValues")) {
                        $errors["nominalScaleValues"] = "The custom nominal scale must have nominal scale values.";
                    }
                }
                break;
            case "ordinal":
                $column = $postData->get("column");
                if (!$column) {
                    $errors["column"] = "The ordinal scale must have a column defined.";
                } else if (!in_array($column, $tableData['columns'])) {
                    $errors["column"] = "No column was found with the given name. Please select a valid column.";
                }

                $order = $postData->get("order");
                if (!$order || !in_array($order, array("increasing", "decreasing"))) {
                    $errors["order"] = "The ordinal scale must be increasing or decreasing.";
                }

                $bounds = $postData->get("bounds");
                if (!$bounds || !in_array($bounds, array("include", "exclude"))) {
                    $errors["bounds"] = "The ordinal scale must be include or exclude the bounds.";
                }

                $ordinalScaleValues = $postData->get("ordinalScaleValues");
                if (!$ordinalScaleValues) {
                    $errors["ordinalScaleValues"] = "The ordinal scale must have numerical values.";
                } else {
                    try {
                        array_map('floatval', $ordinalScaleValues);
                    } catch (\Exception $exception) {
                        $errors["ordinalScaleValues"] = "The ordinal scale values must be numerical.";
                    }
                }
                break;
            case "inter-ordinal":
                $column = $postData->get("column");
                if (!$column) {
                    $errors["column"] = "The inter-ordinal scale must have a column defined.";
                } else if (!in_array($column, $tableData['columns'])) {
                    $errors["column"] = "No column was found with the given name. Please select a valid column.";
                }

                $boundsInclusionSide = $postData->get("boundsInclusionSide");
                if (!$boundsInclusionSide || !in_array($boundsInclusionSide, array("increasing-side", "decreasing-side"))) {
                    $errors["boundsInclusionSide"] = "The inter-ordinal scale must include the bounds in either the increasing side or the decreasing side.";
                }

                $interOrdinalScaleValues = $postData->get("interOrdinalScaleValues");
                if (!$interOrdinalScaleValues) {
                    $errors["interOrdinalScaleValues"] = "The inter-ordinal scale must have numerical values.";
                } else {
                    try {
                        array_map('floatval', $interOrdinalScaleValues);
                    } catch (\Exception $exception) {
                        $errors["interOrdinalScaleValues"] = "The inter-ordinal scale values must be numerical.";
                    }
                }
                break;
            case "grid":
                $column = $postData->get("firstScaleColumn");
                if (!$column) {
                    $errors["firstScaleColumn"] = "The grid's first scale must have a column defined.";
                } else if (!in_array($column, $tableData['columns'])) {
                    $errors["firstScaleColumn"] = "No column was found with the given name for the grid's first scale. Please select a valid column.";
                }

                $order = $postData->get("firstScaleOrder");
                if (!$order || !in_array($order, array("increasing", "decreasing"))) {
                    $errors["firstScaleOrder"] = "The grid's first scale must be increasing or decreasing.";
                }

                $bounds = $postData->get("firstScaleBounds");
                if (!$bounds || !in_array($bounds, array("include", "exclude"))) {
                    $errors["firstScaleBounds"] = "The grid's first scale must be include or exclude the bounds.";
                }

                $scaleValues = $postData->get("firstGridScaleValues");
                if (!$scaleValues) {
                    $errors["firstGridScaleValues"] = "The grid's first scale must have numerical values.";
                } else {
                    try {
                        array_map('floatval', $scaleValues);
                    } catch (\Exception $exception) {
                        $errors["firstGridScaleValues"] = "The grid's first scale values must be numerical.";
                    }
                }

                $column = $postData->get("secondScaleColumn");
                if (!$column) {
                    $errors["secondScaleColumn"] = "The grid's second scale must have a column defined.";
                } else if (!in_array($column, $tableData['columns'])) {
                    $errors["secondScaleColumn"] = "No column was found with the given name for the grid's second scale. Please select a valid column.";
                }

                $order = $postData->get("secondScaleOrder");
                if (!$order || !in_array($order, array("increasing", "decreasing"))) {
                    $errors["secondScaleOrder"] = "The grid's second scale must be increasing or decreasing.";
                }

                $bounds = $postData->get("secondScaleBounds");
                if (!$bounds || !in_array($bounds, array("include", "exclude"))) {
                    $errors["secondScaleBounds"] = "The grid's second scale must be include or exclude the bounds.";
                }

                $scaleValues = $postData->get("secondGridScaleValues");
                if (!$scaleValues) {
                    $errors["secondGridScaleValues"] = "The grid's second scale must have numerical values.";
                } else {
                    try {
                        array_map('floatval', $scaleValues);
                    } catch (\Exception $exception) {
                        $errors["secondGridScaleValues"] = "The grid's second scale values must be numerical.";
                    }
                }
                break;
            case "custom":
            default:
                if (!$postData->has("objects")) {
                    $errors["objects"] = "The context must have at least one object.";
                }

                if (!$postData->has("attributes")) {
                    $errors["attributes"] = "The context must have at least one attribute.";
                }
        }

        return $errors;
    }

}