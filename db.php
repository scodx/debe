<?php


class debe{

    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $db   = "db"; 
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


    /**
     * Return the PDO Instance, since this is just a little 
     * class/helper/wrapper around PDO, maybe you need to 
     * execute certains methods.
     * @return Mixed The PDO Instance
     */
    public function getPDOInstance()
    {
        return $this->pdo;
    }


    public function query($sql, $params = Array(), $result = "fetchAll")
    {

        $cursor = $this->pdo->prepare($sql);
        $cursor->execute($params);
        print_r($cursor);

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


    /**
     * Function that paginate a query. You must pass at least the whole query, 
     * the function will append at the very end the LIMIT clauses. If you only 
     * pass the first argument (the whole query and not the count_query argument)
     * the function will execute this query twice, the first one is to know 
     * how many rows will throw, and second one will execute the query with the 
     * LIMIT clauses. SO DON'T DO THIS, ALWAYS SUPPLY THE LAST ARGUMENT (@count_query) 
     * since not doing this will result in performance issues.
     * 
     * @param  string  $sql         The main query to execute without the limit clauses. 
     *                              For example: "SELECT * FROM category"
     * @param  array   $params      Array of parameters to bind with the main query.
     * @param  integer $page        Number of the page that the function will return. Default is 1
     * @param  integer $num         Number of the rows that the function will return. Default is 10
     * @param  string  $count_query A query that once executed will return the total rows 
     *                              of the main query without the LIMIT clauses. Must be a 
     *                              query like: "SELECT count(*) as count FROM category". 
     *                              Notice the alias (" as count "), the function will search 
     *                              for this index. Simply said, the query is very similar to 
     *                              the main query, except for the count function. Ideally, you
     *                              should include the least columns as possible since you are 
     *                              only counting the rows, not displaying them.
     * @return array                An array with the folowing keys and values:
     *                              <code>
     *                                  [total_pages]   = The number of pages the pager detected.
     *                                  [page]          = The current page the pager is in.
     *                                  [rows]          = The number of rows the current page has.
     *                                  [data]          = The results.
     *                              </code> 
     */
    public function queryPager(
            $sql, 
            $params         = Array(), 
            $page           = 1,
            $num_rows       = 10,
            $count_query    = FALSE
        )
    {

        $count = 0;

        if($count_query){
            $tmp_count = $this->query($count_query, $params, "fetch");
            $count = $tmp_count['count'];
        }else{
            $count = $this->query($sql, $params, "exec");
        }

        $start = ($page - 1) * $num_rows;

        $data = $this->query($sql . " LIMIT {$start}, {$num_rows} ", $params);

        $total_pages = (int)ceil($count / $num_rows);

        return array(
            "total_pages"   => $total_pages,
            "page"          => $page,
            "rows"          => count($data),
            "data"          => $data,
        );

    }


    /**
     * Finds all the records in a given table.
     * @param  string   $table      The name of the table
     * @param  string   $select     The colums you want to get
     * @param  array    $where      An array which its key is the name of the column 
     *                              and its value the condition, for example: 
     *                              <code>$where["idcategory"] > 4</code> will search 
     *                              the record where idcateogry > 4 (if operator is equal to ">")
     * @param  string   $operator   The operator where the WHERE clause will execute to. 
     * @param  string   $order      The ORDER clause, for example: " ORDER BY name ASC"
     * @return array    The results
     */
    public function findAll($table, $select = "*", $where = Array(), $operator = "=", $order = "")
    {

        $query          = "SELECT {$select} FROM {$table} ";
        $params         = array();
        $where_query    = "";
        $order_query    = "";


        if(!empty($where)){
            $where_key                  = key($where);
            $params[":{$where_key}"]    = $where[$where_key];
            $where_query                = " WHERE {$where_key} {$operator} :{$where_key} ";
        }

        if(!empty($order)){
            $order_query        = "ORDER BY {$order}";
        }

        return $this->query(
            $query . $where_query . $order_query,
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
            "fetch"
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

        $query .= implode(", ", $query_set) . " WHERE {$where_key} {$operator} :{$where_key}w ";

        return $this->query(
            $query,
            $params,
            "exec" 
        );

    }



    /**
     * Deletes a record from a given table.
     * @param  string $table    The name of the table
     * @param  array $where     An array which its key is the name of the column 
     *                          and its value the condition, for example: 
     *                          <code>$where["idcategory"] = 4</code> will delete 
     *                          the record where idcateogry = 4 (if operator is equal to "=")
     * @param  string $operator The operator where the WHERE clause will execute to. 
     * @return Int              The number of records deleted
     */
    public function delete($table, $where, $operator = "=")
    {

        $query      = "DELETE FROM {$table} ";
        $params     = array();
        $where_key  = key($where);

        $params[":{$where_key}"]    = $where[$where_key];

        $query .= " WHERE {$where_key} {$operator} :{$where_key} ";

        return $this->query(
            $query,
            $params,
            "exec" 
        );

    }

    
}











