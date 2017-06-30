<?php

namespace AppBundle\Controller;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends BaseController
{
    /**
     * @Route("/", name="homepage")
     *
     * @return Response
     */
    public function indexAction()
    {
        $this->clearBreadcrumb();

        return $this->render('@App/Default/index.html.twig', array(
            'activeMenu' => "home",
        ));
    }

    /**
     * @Route("/test2", name="test2")
     */
    public function test2Action()
    {
        $expression = '(balance >= 200.0 and balance < 1000.0) or (currency matches "/EUR/")';

        $config = new Configuration();
        $connectionParams = array(
            'dbname' => 'proj_db_2',
            'user' => 'root',
            'password' => '',
            'host' => 'localhost:3307',
            'driver' => 'pdo_mysql',
        );

        $conn = DriverManager::getConnection($connectionParams, $config);

        $sql = "SELECT * FROM account";
        $stmt = $conn->query($sql); // Simple, but has several drawbacks

        $language = new ExpressionLanguage();
        while ($row = $stmt->fetch()) {
            var_dump($language->evaluate($expression, $row));
        }

        echo "Done";
        exit;
    }

    /**
     * @Route("/test", name="test")
     */
    public function testAction()
    {

        $config = new Configuration();
        $connectionParams = array(
            'dbname' => 'proj_db_2',
            'user' => 'root',
            'password' => '',
            'host' => 'localhost:3307',
            'driver' => 'pdo_mysql',
        );

        $conn = DriverManager::getConnection($connectionParams, $config);

        $sql = "SELECT * FROM account";
        $stmt = $conn->query($sql); // Simple, but has several drawbacks

        $expression = "(balance >= 200.0 AND balance < 1000.0) OR (currency = 'EUR') OR (currency )";
        $expression = $this->sanitizeExpressionForEval($expression);
        $expression = $this->prepareForEval($expression);

        while ($row = $stmt->fetch()) {
            $result = eval("return $expression;");
            var_dump($result);
        }

        echo "Done";
        exit;
    }

    private function prepareForEval($expression)
    {
        $expression = preg_replace(array(
            " AND ",
            " OR ",
            " = ",
            "/(?<!['\"])(?>[a-zA-Z][a-zA-Z0-9_\-]+)(?!['\"])/"
        ), array(
            " && ",
            " || ",
            " == ",
            "\$row['$0']"
        ), $expression);

        return $expression;
    }

    private function sanitizeExpressionForEval($expression)
    {
        return str_replace(
            array("\"", "$", ";", "()"),
            array("'"),
            $expression
        );
    }
}
