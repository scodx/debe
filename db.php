<?php


class debe{

    private $host = "localhost";
    private $user = "root";
    private $pass = "GSG2013";
    private $db   = "rhbd_miguel"; 
    private $port = 3306;

    private $pdo;


    function __construct()
    {

        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->db};port={$this->port}", 
                $this->user, 
                $this->pass
            );
        } catch (PDOException $e) {
            print "Error!: " . $e->getMessage() . "<br/>";
            die();
        }

    }


    public function getPDOInstance()
    {
        return $this->pdo;
    }


    public function query($sql, $params = Array(), $result = "fetchAll")
    {

        $cursor = $this->pdo->prepare($sql);
        $cursor->execute($params);

        // var_dump($params);

        $fetch_mode = PDO::FETCH_ASSOC;
        $ret = FALSE;

        switch ($result) {
            case 'fetch':
                    $ret = $cursor->fetch($fetch_mode);
                break;
            case 'exec':
                    $ret = $cursor->rowCount();
                    $cursor->fetchAll();
                break;
            case 'insert':
                    $ret = $this->pdo->lastInsertId();
                break;
            case 'fetchAll':
            default:
                    $ret = $cursor->fetchAll($fetch_mode);
                break;
        }

        return $ret;

    }


    public function queryPager(
            $sql, 
            $params = Array(), 
            $pager = Array(
                "page"  => 1, 
                "num"   => 20,
                "count_query" => FALSE
                )
            )
    {

        $count = array();

        if(isset($pager['query_count'])){
            
        }else{

        }




    }


    public function findAll($table, $order = Array(), $select = "*")
    {

        $params = array();
        $order_query    = "";

        if(!empty($order)){
            $order_query        = "ORDER BY :order";
            $params[":order"]   = $order;
        }

        return $this->query(
            "SELECT {$select} FROM {$table} {$order_query} ",
            $params
        );

    }


    public function find($table, $key, $value, $operator = "=")
    {

        return $this->query(
            "SELECT * FROM {$table} WHERE {$key} {$operator} :value ",
            array(
                ":value" => $value
            ),
            FALSE
        );

    }


    public function insert($table, $values)
    {

        $query          = "INSERT INTO {$table} ";
        $query_keys     = array();
        $query_values   = array();
        $params         = array();

        foreach ($values as $key => $value) {
            $params[":{$key}"]  = $value;
        }

        $query .= "(" . implode(", ", array_keys($values)) . ")" ;
        $query .= " VALUES( ". implode(", ", array_keys($params)) .")";

        return $this->query(
            $query,
            $params,
            "insert"
        );



    }



    public function update($table, $values, $where, $operator = "=")
    {

        $query      = "UPDATE {$table} SET ";
        $params     = array();
        $query_set  = array();
        $where_key  = key($where);

        foreach ($values as $key => $value) {
            $query_set[] = " {$key} = :{$key} ";
            $params[":{$key}"] = $value;
        }

        $params[":{$where_key}w"]    = $where[$where_key];

        $query .= implode(", ", $query_set) . " WHERE {$where_key} = :{$where_key}w ";

        return $this->query(
            $query,
            $params,
            "exec" 
        );

    }


    public function delete($table, $where, $operator = "=")
    {

        $query      = "DELETE FROM {$table} ";
        $params     = array();
        $where_key  = key($where);

        $params[":{$where_key}"]    = $where[$where_key];

        $query .= " WHERE {$where_key} = :{$where_key} ";

        return $this->query(
            $query,
            $params,
            "exec" 
        );

    }

    
}











