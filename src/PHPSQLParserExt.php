<?php
/**
 * eg：
 * //第一步：必须配置config.php文件
 * //第二步：非必要步骤，写在index.php入口文件，用于记录访问url
 * $fileName = "sql.log";//这里的文件名要和配置文件的文件名一致
 * $message = '[<font style="color: red">URL</font>]' . strip_tags($_SERVER['REQUEST_URI']) . "\r\n<br>";
 * error_log($message, 3, $fileName . ".html");
 * //第三步：代码写在系统需要记录日志地方
 * $PHPSQLParserExt = new PHPSQLParserExt($sql);
 * $new_message = $PHPSQLParserExt->doIt();
 * //第四步：浏览器打开看看吧
 */

namespace PHPSQLParserExt;

use PHPSQLParser\PHPSQLParser;
use think\Exception;

class PHPSQLParserExt
{
    static private $config;
    /**
     * @var string 需要解析的sql语句
     */
    private $_sql;
    /**
     * @var 以小写字母方式返回sql语句操作动作，例如insert等
     */
    private $_sqlAction;
    /**
     * @var 表名
     */
    private $_tableName;
    /**
     * @var sql语句解析后的数组信息
     */
    private $_sqlInfo = array();//当为true表示发生异常，所有数据不通过cache处理
    private $_exception = false;
    private $_config;
    /**
     * @var sql日志
     */
    private $_log = '';
    private $pdo;

    function __construct(string $sql)
    {
        if (empty(self::$config)) {
            self::$config = include_once("config.php");
        }
        $this->__mysqli = new  \mysqli(self::$config['dbHost'], self::$config['dbUser'], self::$config['dbPwd'], self::$config['dbName']);
        $hostname = self::$config['dbHost'];
        $dbname = self::$config['dbName'];
        $username = self::$config['dbUser'];
        $passwd = self::$config['dbPwd'];
        $this->pdo = new \PDO("mysql:host={$hostname};dbname={$dbname}", "{$username}", "{$passwd}");
//        $sql = "SELECT * FROM information_schema.TABLES WHERE table_schema = '".self::$config['database']."' ORDER BY table_name";
//        $PDOStatement = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $this->_sql = $sql;
        try {
            $this->_init();
        } catch (Exception $e) {
            $this->_exception = true;
            $this->_debugInfo(print_r($e->__toString(), true), '异常内容');
        }
    }

    private function _init()
    {
        #格式化sql
//        $this->_formate();
        #解析sql
        $parse = new \PHPSQLParser\PHPSQLParser($this->_sql);
        $this->_sqlInfo = $parse->parsed;
        #获取动作
        $this->_getActionFromSql();
        #获取表名
        $this->getTableName();
    }

    private function _formate()
    {
        return $this->_sql = trim(str_replace(PHP_EOL, '', $this->_sql));
    }

    #首尾去空格并去除回车和换行符

    /**
     * 选取第一个单词作为动作
     * @return unknown
     * @throws Exception
     * @example select * from table //select
     *          INSERT INTO tbl_name (col1,col2) VALUES(15,col1*2); //insert
     *          update table set name='rose'//update
     */
    private function _getActionFromSql()
    {
        $current_key = key($this->_sqlInfo);
        $this->_sqlAction = strtolower($current_key);
    }

    function getTableName()
    {
        $current = current($this->_sqlInfo);
        if ($this->_sqlAction == 'insert') {
            $tmpTableName = $current[1]['no_quotes']['parts'][0];
        } elseif ($this->_sqlAction == 'show') {
            $tmpTableName = current($this->_sqlInfo)[2]['table'];
        } elseif ($this->_sqlAction == 'update') {
            $tmpTableName = $this->_sqlInfo['UPDATE'][0]['table'];
        } else {
            if (isset($this->_sqlInfo['FROM'][0]['table'])) {
                $table = $this->_sqlInfo['FROM'][0]['table'];
                //这里需要增加递归查找第一个table作为表名
            } elseif (isset($this->_sqlInfo['FROM'][0]['sub_tree']['FROM'][1]['table'])) {
                $table = $this->_sqlInfo['FROM'][0]['sub_tree']['FROM'][1]['table'];
            } else {
                throw new Exception("临时表，表比较复杂");
            }
            $tmpTableName = $table;
        }

        $this->_tableName[] = trim(trim($tmpTableName), "`");
        return $this->_tableName;
    }

    private function _debugInfo($var, $tips = "提示:")
    {
        if(self::$config['debug']){
            if(file_exists(self::$config['debug_file']) === false){
                $str = '
                    <style type="text/css">
                    .datalist {
                      border: 1px solid #429fff; /* 表格边框 */
                      font-family: Arial;
                      font-size: 14px;
                      border-collapse: collapse; /* 边框重叠 */
                    }
                    .datalist tr:hover {
                      background-color: #c4e4ff; /* 动态变色,IE6下无效！*/
                    }
                    .datalist caption {
                      padding-top: 3px;
                      padding-bottom: 2px;
                      font: bold 1.1em;
                      background-color: #f0f7ff;
                      border: 1px solid #429fff; /* 表格标题边框 */
                    }
                    .datalist th {
                      border: 1px solid #429fff; /* 行、列名称边框 */
                      background-color: #d2e8ff;
                      font-weight: bold;
                      padding-top: 4px;
                      padding-bottom: 4px;
                      padding-left: 10px;
                      padding-right: 10px;
                      text-align: center;
                    }
                    .datalist td {
                      border: 1px solid #429fff; /* 单元格边框 */
                      text-align: right;
                      padding: 4px;
                    }
                    </style>
                ';
                $error_log = error_log($str, 3, self::$config['debug_file']);
            }

//            if($var['type'] == 'sql'){
//                $error_log = error_log($tips . print_r($var, true) . "\n<br>", 3, self::$config['debug_file']);
//
//            }elseif($var['type'] == 'table_name'){
//                $error_log = error_log($tips . print_r($var, true) . "\n<br>", 3, self::$config['debug_file']);
//
//            }else{//type=table
//                $error_log = error_log($tips . print_r($var, true) . "\n<br>", 3, self::$config['debug_file']);
//
//            }

            $error_log = error_log($tips . print_r($var, true) . "\n<br>", 3, self::$config['debug_file']);
        };
//        error_log("1111111111",3,"../../www/api_v2/public/xxx.html");
//        die();
    }

    function getAction()
    {
        return $this->_sqlAction;
    }

    function doIt()
    {
//        $this->_tableName;
//        $tablename = $this->_tableName[0];
//        $sql = "insert into lr_tmp(`name`) values ('{$tablename}')";
//        $sql = "INSERT INTO MyGuests (firstname, lastname, email) VALUES ('John', 'Doe', 'john@example.com')";

//        try {
//            $queryObj = $this->__mysqli->query($sql);
//        } catch (\Exception $e) {
//            $a = 1;
//        }

        if ($this->_sqlAction == 'update') {
            $log = $this->parseUpdate();
        } elseif ($this->_sqlAction == 'insert') {
            $log = $this->parseInsert();
        } elseif ($this->_sqlAction == 'select') {
            $log = $this->parseSelect();
        } else {
            $log = $this->_sql;
        }
        if (in_array($this->_sqlAction, self::$config['action'])) {
            $this->_debugInfo($this->_sql, "[SQL:]");
            $this->_debugInfo($log, "");
        }
        return $log;
    }

    function parseUpdate()
    {
        foreach ($this->_sqlInfo['SET'] as $k => $v) {
            //字段名
            $param_name = $v['sub_tree'][0]['no_quotes']['parts'][0];
            //字段值
            $param_value = $v['sub_tree'][2]['base_expr'];
            $t[$param_name] = $param_value;
        }
        $tmp = $this->getComment($this->_tableName[0]);
        $table_comment = $this->getTableComment($this->_tableName[0]);
        $column_comment = $this->getColumnComment($this->_tableName[0]);

        $table = "更新表{$this->_tableName[0]}({$table_comment})";
        $table.="<table class='datalist' border='1' cellspacing='0' cellpadding='1'>";

        //字段
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <th>" . $k . "</th>";
        }
        $table .= "  </tr>";
        //修改值
        $table .= "  <tr>";
        foreach ($tmp['param'] as $k => $v) {
            if (isset($t[$k])) {
                $table .= "    <td>" . $t[$k] . "</td>";
            } else {
                $table .= "    <td></td>";
            }
        }
        $table .= "  </tr>";
        //默认
        $table .= "  </tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[0] . "</td>";
        }
        $table .= "  </tr>";
        //注释
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[1] . "</td>";
        }
        $table .= "  </tr>";

        $table .= "</table>";
        return $table;
    }

    function getComment($tableName)
    {
        $p = $this->getCreateTableSql($tableName);

        $param = preg_split("#\n\s#", $p['Create Table'], -1, PREG_SPLIT_NO_EMPTY);
        $paramComment = array();
        #第一步取出字段和注释对照,尚未解决默认值存在情况
        foreach ($param as $k => $v) {
            if (strpos(trim($v), 'CREATE') === 0) {
                continue;
            }
            if (strpos(trim($v), 'PRIMARY') === 0) {
                continue;
            }
            if (strpos(trim($v), 'UNIQUE') === 0) {
                continue;
            }
            if (strpos(trim($v), 'KEY') === 0) {
                continue;
            }
            preg_match_all("/`([^`]+)`([^']+)(.*)/", $v, $matches);
            $paramComment[$matches[1][0]] = trim($matches[3][0], ",'");
        }
        if (strpos(trim($v), 'ENGINE') !== false) {
            $tableComment = '';
            $PHPSQLParser = new PHPSQLParser($p['Create Table']);
            $parse = $PHPSQLParser->parsed;
            if (count($parse['TABLE']['options']) === 5) {
                $tableComment = $parse['TABLE']['options'][4]['sub_tree']['2']['base_expr'];
            }
        }

        return array('table' => $tableComment, 'param' => $paramComment);
    }

    private function getCreateTableSql($tableName)
    {
        $sql = "SHOW CREATE TABLE `" . $tableName . "`";
        $queryObj = $this->__mysqli->query($sql);
        if ($queryObj === false) {
            //复杂的表目前不处理，主要是一些嵌套的情况
            return [];
        }
        $row = (array)$queryObj->fetch_object();
        return $row;
    }

    function parseInsert()
    {
        $tmp = preg_split("#\s+#", $this->_sql, -1, PREG_SPLIT_NO_EMPTY);
        $param = trim(trim($tmp[3], "("), ")");
        $value = trim(trim($tmp[5], "("), ")");
        $paramArr = explode(",", $param);
        $valueArr = explode(",", $value);

        $sqlInfo = $this->_sqlInfo;
        $paramArr = call_user_func(function () use ($sqlInfo) {
            $arr = explode(",", trim($this->_sqlInfo['INSERT'][2]['base_expr'], "()"));
            $arr = array_map(function ($a) {
                return trim(trim($a), "`");
            }, $arr);
            return $arr;
        });
        $valueArr = call_user_func(function () use ($sqlInfo) {
            $key = explode(",", trim($this->_sqlInfo['INSERT'][2]['base_expr'], "()"));
            $key = array_map(function ($a) {
                return trim(trim($a), "`");
            }, $key);
            $value = explode(",", trim($this->_sqlInfo['VALUES'][0]['base_expr'], "()"));
            $value = array_map(function ($a) {
                return trim(trim($a), "`");
            }, $value);
            return array_combine($key,$value);
        });

//        $comment = $this->getComment($this->_tableName[0]);
        $table_comment = $this->getTableComment($this->_tableName[0]);
        $column_comment = $this->getColumnComment($this->_tableName[0]);
        $table = "插入表{$this->_tableName[0]}({$table_comment})";
        $table.= "<table class='datalist' border='1' cellspacing='0' cellpadding='1'>";

        //字段
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <th>" . $k . "</th>";
        }
        $table .= "  </tr>";
        //插入值
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v){
            if(isset($valueArr[$k])){
                $table .= "    <td>" . $valueArr[$k] . "</td>";
            }else{
                $table .= "    <td></td>";
            }
        }
//        foreach ($valueArr as $k => $v) {
//            $table .= "    <td>" . trim($v, "	'") . "</td>";
//        }
        $table .= "  </tr>";
        //默认
        $table .= "  </tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[0] . "</td>";
        }
        $table .= "  </tr>";
        //注释
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[1] . "</td>";
        }
        $table .= "  </tr>";
        $table .= "</table>";
        return $table;
    }

    //获取字段和注释的映射关系
    function getColumnComment($table){
        $db = self::$config['database'];
        $sql = "select `column_name` ,column_comment,column_default from `information_schema`.`COLUMNS` where table_schema = '{$db}' and table_name='{$table}'";
        $PDOStatement = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $return = [];
        foreach($PDOStatement as $k => $v){
//            $return[$v['COLUMN_NAME']] = [$v['COLUMN_DEFAULT'],$v['COLUMN_COMMENT']];
            $return[$v['column_name']] = [$v['column_default'],$v['column_comment']];
        }
        return $return;
    }

    //获取表和注释的映射关系
    function getTableComment($table){
        static $return;
        if($return){
            return $return[$table];
        }
        $sql = "SELECT * FROM information_schema.TABLES WHERE table_schema = '".self::$config['database']."' ORDER BY table_name";
        $PDOStatement = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $return = [];
        foreach($PDOStatement as $k => $v){
            $return[$v['TABLE_NAME']] = $v['TABLE_COMMENT'];
        }
        return $return[$table];
    }

    function parseSelect()
    {
//        $tmp = preg_split("#\s+#", $this->_sql, -1, PREG_SPLIT_NO_EMPTY);
//        $sqlInfo = $this->_sqlInfo;
//        $comment = $this->getComment($this->_tableName[0]);
        $table_comment = $this->getTableComment($this->_tableName[0]);
        $column_comment = $this->getColumnComment($this->_tableName[0]);
        $table = "表名：{$this->_tableName[0]}({$table_comment})";
        $table .= "<table class='datalist' border='1' cellspacing='0' cellpadding='1'>";
        //字段
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <th>" . $k . "</th>";
        }
        $table .= "  </tr>";
        //默认
        $table .= "  </tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[0] . "</td>";
        }
        $table .= "  </tr>";
        //注释
        $table .= "  <tr>";
        foreach ($column_comment as $k => $v) {
            $table .= "    <td>" . $v[1] . "</td>";
        }
        $table .= "  </tr>";

        $table .= "</table>";
        return $table;
    }
}