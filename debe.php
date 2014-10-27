<?php


class Debe{


    /**
     * Returns the queryString of the queries instead of the results
     * @var boolean
     */
    private $debug = FALSE;

    /**
     * The PDO Instance
     * @var Mixed
     */
    private $pdo;



    /**
     * Contructor, you must pass the connection paramateres
     * @param String  $host Host to connect to
     * @param String  $user Database user
     * @param String  $pass Database password
     * @param String  $db   Database name to connect
     * @param integer $port MySQL port, defaults to 3306
     */
    function __construct($host, $user, $pass, $db, $port = 3306)
    {

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$db};port={$port}", 
                $user, 
                $pass
            );
            $this->pdo->exec("SET NAMES 'utf8';");
        } catch (PDOException $e) {
            print "Error!: " . $e->getMessage() ;
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


    /**
     * Executes a simple query throug the pdo exec() method
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function exec($query)
    {
        return $this->pdo->exec($query);
    }


    /**
     * Executes a custom query to the PDO instance. 
     * You must build the query with the parameters to bind
     * specified in the second argument ($params). It uses the 
     * pdo prepare method to bind the parameters, so you need
     * to write them accordingly.
     * @param  String $sql    The query to run, For example: 
     *                        "SELECT * FROM category"
     * @param  Array $params  Array of parameters to bind with the query.
     * @param  string $result Type of return value, "fetchAll" is the default, 
     *                        returns an array of rows (for SELECT.. type sentences).
     *                        "fetch" returns just the first row that encounters.
     *                        "exec" returns the row count, this one is appropiate 
     *                        for DELETE sentences.
     *                        "insert" returns the last inserted id.
     * @return Array          Array of results depending of the $result parameter
     */
    public function query($sql, $params = Array(), $result = "fetchAll")
    {

        $cursor = $this->pdo->prepare($sql);
        $cursor->execute($params);

        if($this->getDebug()){
            return $this->debug($cursor, $params);
        }

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
     * @param  array    $where      An array which its key is the name of the column 
     *                              and its value the condition, for example: 
     *                              <code>$where["idcategory"] > 4</code> will search 
     *                              the record where idcateogry > 4 (if operator is equal to ">")
     * @param  string   $select     The colums you want to get
     * @param  string   $operator   The operator where the WHERE clause will execute to. 
     * @param  string   $order      The ORDER clause, for example: " ORDER BY name ASC"
     * @return array    The results
     */
    public function findAll($table, $where = Array(), $select = "*", $operator = "=", $order = "")
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


    /**
     * Retrieves a single row from a table, given it's 
     * key and value(along the operator). Example:
     *<code>
     *  $db->find("articles", "articleid" "")
     *</code>
     * 
     * @param  [type] $table    [description]
     * @param  [type] $key      [description]
     * @param  [type] $value    [description]
     * @param  string $operator [description]
     * @return [type]           [description]
     */
    public function find($table, $conditions, $operator = "=")
    {

        $query          = "SELECT * FROM {$table} WHERE ";
        $params         = array();
        $query_where    = array();

        foreach ($conditions as $key => $value) {
            $query_where[]= " {$key} {$operator} :{$key}";
            $params[":{$key}"]  = $value;
        }

        $query .= implode(" AND ", $query_where) ;

        return $this->query(
            $query,
            $params,
            "fetch"
        );

    }


    /**
     * Inserts a new row in a table
     * @param  String $table  The table name
     * @param  Array $values  The new values in the next form:
     *                        array(
     *                            "name" => "John",
     *                            "last_name" => "Doe"
     *                        )
     * @return Int            The last ID of the columns inserted
     */
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


    /**
     * Updates rows from a table, the new values are passed in
     * the second parameter ($valuers)
     * @param  String $table    The table name
     * @param  Array $values    The colums and ther new values
     * @param  Array $where     The conditions that the UPDATE must accept in order 
     *                          to execute correclty in an array form, like 
     *                          array("id" => 9) equals to "WHERE id = 9" IF 
     *                          $operator is "="
     * @param  string $operator Operator to concatenate in the condition
     * @return Int              Number of rows updated
     */
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


    /**
     * Builds an array for debugging purpuses, containing
     * the query and the parameters.
     * @param  Mixed $PDOStatement The cursor object of PDO
     * @param  Array $params       Array of parameters
     * @return Array               Array of data
     */
    private function debug($PDOStatement, $params)
    {
        return array(
            "queryString" => $PDOStatement->queryString,
            "params" => $params
        );
    }


    /**
     * Sets the debug status
     * @param Boolean $debug Status of the debug state
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }


    /**
     * Returns de debug status
     * @return Boolean The debug status
     */
    public function getDebug()
    {
        return $this->debug;
    }

    
}











