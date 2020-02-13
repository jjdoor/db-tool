<?php
/**
 * @desc: 将数据库中的表字段进行重新排序
 * @author: benjamin
 * @date: 2015年7月21日
 */
$formatDbFields = new DbTool();
//清空表中的所有内容
//$formatDbFields->truncate();
#数据库表的列表
$tables = $formatDbFields->printTableList();
#数据库表字段统计
$formatDbFields->printParamList();
#数据库表记录数统计
$formatDbFields->printRecordsNumber();
#数据库内表字段按照a-z重排，id不参与
 $formatDbFields->db_tb_freorder();
#输出数据库文档
$formatDbFields->createDbDocument();

?>