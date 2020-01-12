<?php
namespace DbTool;
/**
 * @desc: 将数据库中的表字段进行重新排序
 * @author: benjamin
 * @date: 2015年7月21日
 * @example：$formatDbFields = new FormatDbFields();
 *           $formatDbFields->db_tb_freorder();
 */
//echo "<pre>";
//$formatDbFields = new FormatDbFields();
//清空表中的所有内容
//$formatDbFields->truncate();
#数据库表的列表
//$tables = $formatDbFields->printTableList();
#数据库表字段统计
//$formatDbFields->printParamList();
#数据库表记录数统计
//$formatDbFields->printRecordsNumber();
#数据库内表字段按照a-z重排，id不参与
// $formatDbFields->db_tb_freorder();
#输出数据库文档
//$formatDbFields->createDbDocument();


//可否加入字段校验，比如同样是id，但是有些是int，有些是smallint，这个也要注意，必须统一
class DbTool
{
    public $__tabelsArr;
    public $__fileLineArr;
    private $__dbHost;
    private $__dbName;
    private $__dbPwd;
    private $__dbUser;
    private $__dbPort;
    private $__mysqli;

    function __construct()
    {
//        $this->__dbHost = "localhost";
//        $this->__dbName = "cr_db";
//        $this->__dbUser = "csl";
//        $this->__dbPwd  = "e4ce2d9a415511e798fbb70e84c710C";
//        $this->__dbPort = "3357";


        $this->__dbHost = "127.0.0.1";
        $this->__dbName = "store";
        $this->__dbUser = "root";
        $this->__dbPwd = "";
        $this->__mysqli = new  mysqli ($this->__dbHost, $this->__dbUser, $this->__dbPwd, $this->__dbName);
        $this->__mysqli->query('set names utf8');

    }

    //清空数据库内的所有数据
    function truncate()
    {
        $tableListArr = $this->getTableList();
        echo "<h3>总共有：" . count($tableListArr) . "个表</h3><br>";
        foreach ($tableListArr as $k => $v) {
            $sql = "truncate table `" . $v . "`";
            $this->__mysqli->query($sql);
            echo "已经清空{$v}表<br>";

        }
    }

    function getTableList()
    {
        $sql = "SHOW FULL TABLES FROM `" . $this->__dbName . "`";

        $queryObj = $this->__mysqli->query($sql);
        if ($queryObj) {
            while ($row = $queryObj->fetch_object()) {
                $k = 'Tables_in_' . $this->__dbName;
                $this->__tabelsArr[$row->$k] = $row->$k;
            }
        }
        return $this->__tabelsArr;
    }

    /**
     * 根据数据库表结构和注释输出文档
     */
    public function createDbDocument()
    {
        unlink("{$this->__dbName}.md");
        header("Content-type:text/html;charset=utf-8");
        echo "开始生成文档....<br>";
        $pdo = new PDO("mysql:host={$this->__dbHost};dbname={$this->__dbName}", "{$this->__dbUser}", "{$this->__dbPwd}");

        //<editor-fold desc="获取表注释">
        $tables = call_user_func(function()use($pdo){
            $sql = "SELECT * FROM information_schema.TABLES WHERE table_schema = '{$this->__dbName}' ORDER BY table_name";
            $PDOStatement = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $return = [];
            foreach($PDOStatement as $k => $v){
                $return[$v['TABLE_NAME']] = $v['TABLE_COMMENT'];
            }
            return $return;
        });

        $rows = call_user_func(function()use($pdo,$tables){
            $sql = "SELECT
                    * 
                    FROM
                    information_schema. TABLES a
                    LEFT JOIN information_schema. COLUMNS b ON a.table_name = b.TABLE_NAME
                    WHERE
                    a.table_schema = '{$this->__dbName}' and b.TABLE_SCHEMA='store_sandbox' 
                    ORDER BY
                    a.table_name";
            $PDOStatement = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $return = array_fill_keys(array_keys($tables),[]);
            foreach($PDOStatement as $k => $v){
                //字段
                $return[$v['TABLE_NAME']][$k]['COLUMN_NAME'] = $v['COLUMN_NAME'];
                //类型
                $return[$v['TABLE_NAME']][$k]['COLUMN_TYPE'] = $v['COLUMN_TYPE'];
                //是否为空??
//                $return[$v['TABLE_NAME']][$k]['COLUMN_DEFAULT'] = $v['COLUMN_DEFAULT'];
                //默认
                $return[$v['TABLE_NAME']][$k]['COLUMN_DEFAULT'] = $v['COLUMN_DEFAULT'];
                //注释
                $return[$v['TABLE_NAME']][$k]['COLUMN_COMMENT'] = $v['COLUMN_COMMENT'];
                //索引
//                $return[$v['TABLE_NAME']][$k]['COLUMN_DEFAULT'] = $v['COLUMN_DEFAULT'];
            }
            return $return;
        });
        //</editor-fold>

        $i = 1;
        foreach ($tables as $table => $table_comment){
            $marks = '';
            $marks .= "### &nbsp;&nbsp;{$i}、{$table}({$table_comment})\r\n\r\n";
            $marks .= '|字段|类型|默认|注释|' . "\r\n";
            $marks .= '|:---|:---|:---|:---|' . "\r\n";
            foreach($rows[$table] as $k => $row){
                $marks .= "|{$row['COLUMN_NAME']}|{$row['COLUMN_TYPE']}|&nbsp;{$row['COLUMN_DEFAULT']}&nbsp;|&nbsp;{$row['COLUMN_COMMENT']}&nbsp;|"."\r\n";
            }
            file_put_contents("./{$this->__dbName}.md", str_replace('<br/>', "\n", $marks."\r\n"), FILE_APPEND);
            $i++;
        }
        echo "文档生成结束<a href='{$this->__dbName}.md'>打开{$this->__dbName}文档</a>";
    }

    function dump($var)
    {
        $this->__dump($var);
    }

    private function __dump($var, $debugType = 1)
    {
        echo "<pre>";
        if ($debugType == 1) {
            print_r($var);
        } elseif ($debugType == 2) {
            var_dump($var);
        }

    }

    #表字段统计

    function printParamList()
    {
        $totalFields = array();
        $totalFields['p'] = $totalFields['t'] = array();
        $tables = $this->getTableList();

        foreach ($tables as $k => $table) {
            $fields = $this->getFieldList($table);
            foreach ($fields as $key => $field) {
                if (isset($totalFields['p'][$field])) {
                    $totalFields['p'][$field] = $totalFields['p'][$field] + 1;
                    $totalFields['t'][$field] = $totalFields['t'][$field] . "、" . $table;
                } else {
                    $totalFields['p'][$field] = 1;
                    $totalFields['t'][$field] = $table;
                }
            }
        }

        asort($totalFields);
//         ksort($totalFields);
        echo '<hr style="height:1px;border:none;border-top:1px solid #555555;" />';
        echo '<h3>total&nbsp;&nbsp;:&nbsp;&nbsp;' . count($totalFields['p']) . "&nbsp;&nbsp;字段</h3><br>";

        foreach ($totalFields['p'] as $key => $value) {
            echo $value . "&nbsp;&nbsp;" . trim($key, "`") . "&nbsp;--------&nbsp;" . $totalFields['t'][$key] . "<br>";
        }
    }

    function getFieldList($tableName)
    {
        $sql = "SHOW CREATE TABLE `" . $tableName . "`";
        $queryObj = $this->__mysqli->query($sql);
        $row = (array)$queryObj->fetch_object();
        preg_match_all('~\n\s+((`[^`]+`)([^\(]+)[^COMMENT].+COMMENT(.+)),~i', $row['Create Table'], $ms);
        $field = array();
        foreach ($ms[2] as $i => $k) {
            $field[$k] = $k;
        }
        return $field;
    }

    function printTableList()
    {
        $tableListArr = $this->getTableList();
        echo "<h3>总共有：" . count($tableListArr) . "个表</h3><br>";
        $i = 1;
        foreach ($tableListArr as $k => $v) {
            echo $i . "&nbsp;&nbsp;" . $v . "<br>";
            $i++;
        }
    }

    function printRecordsNumber()
    {
        echo '<hr style="height:1px;border:none;border-top:1px solid #555555;" />';
        echo '<h3>记录数如下&nbsp;&nbsp;:&nbsp;&nbsp;' . "" . "&nbsp;&nbsp;</h3><br>";
        $sql = "SHOW FULL TABLES FROM `" . $this->__dbName . "`";

        $queryObj = $this->__mysqli->query($sql);
        if ($queryObj) {
            while ($row = $queryObj->fetch_object()) {
                $k = 'Tables_in_' . $this->__dbName;
                $this->__tabelsArr[$row->$k] = $row->$k;
            }
        }

        $tableRecords = array();
        foreach ($this->__tabelsArr as $k => $v) {
            $sql = "select count(*) as c from " . $v;
            $queryObj = $this->__mysqli->query($sql);
            $row = (array)$queryObj->fetch_object();
            $array[$row['c']] = $v;
            $tableRecords[$v] = $row['c'];
        }
        asort($tableRecords);
        echo "<table border='1'>";
        echo "<tr><th>序号</th><th>表名</th><th>表记录数</th></tr>";
        $i = 1;
        foreach ($tableRecords as $k => $v) {
            echo "  <tr>";
            echo "      <td>{$i}</td><td>{$k}</td><td>{$v}</td>";
            echo "  </tr>";
            $i++;
        }
        echo "</table>";
        return $this->__tabelsArr;
    }

    function readFile()
    {
        $handle = @fopen("index.php", "r");
        if ($handle) {
            while (!feof($handle)) {
                $buffer = fgets($handle, 4096);
                $buffer = trim($buffer);
                $this->__fileLineArr[$buffer] = $buffer;
            }
            fclose($handle);
        }
        return $this->__fileLineArr;
    }

    /* 对本次连接的数据库中所有表进行字段重排，按自增量ID再a-z进行排序 */
    // database table filed reorder
    public function db_tb_freorder()
    {
        if ($this->__mysqli->connect_errno) {
            printf("Connect failed: %s\n", $this->__mysqli->connect_error);
            exit();
        }
        $sql = "SHOW FULL TABLES FROM `" . $this->__dbName . "`";

        $queryObj = $this->__mysqli->query($sql);
        if ($queryObj) {
            while ($row = $queryObj->fetch_object()) {
                $k = 'Tables_in_' . $this->__dbName;
                $name = "`{$row->$k}`";
                $this->tb_field_reorder($name);
                echo "$name is ok<br>";
            }
        }
    }

    private function tb_field_reorder($name)
    {
        $sql = "SHOW CREATE TABLE " . $name;
        $queryObj = $this->__mysqli->query($sql);
        $row = (array)$queryObj->fetch_object();
        preg_match_all('~\n\s+((`[^`]+`).+),~i', $row['Create Table'], $ms);
        $fs = array();
        $pk = $pv = '';
        foreach ($ms[2] as $i => $k) {
            $fs[$k] = $ms[1][$i];
            if (false !== stripos($fs[$k], 'AUTO_INCREMENT')) {
                $pk = $k;
                $pv = $fs[$k];
                unset($fs[$k]);
            }
        }
        ksort($fs);
        $sqls = array();
        $l = '';
        if ($pk && $pv) {
            $sqls[] = " CHANGE $pk $pv FIRST ";
            $l = $pk;
        }
        foreach ($fs as $k => $v) {
            $sqls[] = " CHANGE $k $v " . ($l ? " AFTER $l " : " FIRST ");
            $l = $k;
        }
        $sql = "ALTER TABLE " . $name . implode(" , ", $sqls);
        $this->__mysqli->query($sql);
    }
}

?>