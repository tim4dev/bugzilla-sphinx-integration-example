<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<style type="text/css">
table { width: 100%; padding: 0px; border: none; }
table th { border: none; font-size: 0.9em; font-weight: bold; background-color: #CCCC66; }
table td { border: none; font-size: 0.9em; background-color: #F5E7D4; vertical-align: middle; padding: 5px; }
</style>

<title>Sphinx поиск</title>
</head>

<body>

<!-- GPLv3 (c) 2010 Yuri Timofeev <tim4dev@gmail.com> -->

<br>
<?php

//***** setup *****************************************************
require('api/sphinxapi.php');
$limit = 500;
$bugzilla_show_bug = 'http://bugzilla.domain.org/show_bug.cgi?id=';
//*** Sphinx ***
$sp_host = 'localhost';
$sp_port = 9312;
//*** DB bugzilla ***
$db_host = 'localhost';
$db_user = 'sphinx';
$db_pwd  = 'sphinx-password';
$table_temp = 'sphinx_temp';
//***** end setup *************************************************

$time1 = microtime(true);

if ($_GET && $_GET['text'] )
    $text = $_GET['text'];
else
    $text = '';

$text = addslashes(trim($text));

if ( empty($text) ) echo '<div align="center"><p>Релевантный поиск по Bugzilla с учетом морфологии :</p>';
echo '<form action="." method="get">
     <input type="text" name="text" size="80" value="'.$text.'" />
     <input type="submit" value="Найти" /></form>
     <br>';
if ( empty($text) ) echo '</div>';


if (empty($text)) exit;


$cl = new SphinxClient();
$cl->SetServer($sp_host, $sp_port);

// все слова
$cl->SetMatchMode(SPH_MATCH_ALL);
$cl->SetLimits(0, $limit);
//Задаем полям веса
$cl->SetFieldWeights(array('short_desc' => 10));
//Результаты сортировать по релевантности
$cl->SetSortMode(SPH_SORT_RELEVANCE);

$res = $cl->Query($text, 'bugzilla');

if ($res === false)
    die( 'Error: '. $cl->GetLastError(). "\n\n");

$warn = $cl->GetLastWarning();
if ($warn) 
    echo 'Warning: ', $warn, "\n";

// время работы
$time2 = microtime(true);
$time = $time2 - $time1;

$total = $res['total'];
echo '<p><b>Всего найдено : ', $total, '</b> (максимум ', $limit,') за '.number_format($time, 3).' сек.</p>';
if ($total > 0) {
    // соединение с БД
    $db = mysql_connect($db_host, $db_user, $db_pwd);
    mysql_select_db('bugs', $db);
    mysql_query('SET NAMES UTF8', $db);
    // создаем временную таблицу для хранения id и weight
    mysql_query('CREATE TEMPORARY TABLE '.$table_temp.' (id INT, weight INT)', $db);
    foreach ($res['matches'] as $id=>$inf)  {
        $query = 'INSERT INTO '.$table_temp.' (id, weight) VALUE ('.$id.','.$inf['weight'].')';
        mysql_query($query, $db);
    }
}
if ( $total <= 0 ) exit;


// вывод подробных результатов
$query = 'SELECT
        b.bug_id AS bug_id, b.short_desc AS short_desc, b.creation_ts AS creation_ts,
        b.rep_platform AS rep_platform, b.op_sys AS op_sys,
        p.name AS product, c.name AS component,
        p_assignedto.realname AS assignedto, p_reporter.realname AS reporter,
        temp.weight AS weight
     FROM bugs AS b 
     LEFT JOIN '.$table_temp.' AS temp ON temp.id=b.bug_id
     LEFT JOIN products   AS p ON p.id=b.product_id
     LEFT JOIN components AS c ON c.id=b.component_id
     LEFT JOIN profiles   AS p_assignedto ON p_assignedto.userid=b.assigned_to
     LEFT JOIN profiles   AS p_reporter   ON p_reporter.userid=b.reporter
     WHERE b.bug_id = temp.id
     ORDER BY temp.weight DESC';
$res = mysql_query($query, $db);

if (!$res) die('MySQL error: '. mysql_errno() . ' ' . mysql_error());

?>

<table>
<tr>
<th>Вес</th>
<th>id</th>
<th>Дата регистрации</th>
<th>rep_platform</th>
<th>op_sys</th>
<th>product</th>
<th>Компонент</th>
<th>Исполнитель</th>
<th>Аннотация</th>
</tr>
<?php
for ( $data=array(); $row=mysql_fetch_assoc($res); $data[]=$row )  {
    echo '<tr>';
    echo 
        '<td>', $row['weight'], '</td>',
        '<td><a href="'.$bugzilla_show_bug.$row['bug_id'].'" target="_blank">',$row['bug_id'], '</a></td>',
        '<td>', $row['creation_ts'], '</td>',
        '<td>', $row['rep_platform'], '</td>',
        '<td>', $row['op_sys'], '</td>',
        '<td>', $row['product'], '</td>',
        '<td>', $row['component'], '</td>',
        '<td>', $row['assignedto'], '</td>',
        '<td>', $row['short_desc'], '</td>';
    echo '</tr>';
}
mysql_close($db);
?>

</table>
</body>
</html>

