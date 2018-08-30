<?php

// Set some useful constants that the core may require or use
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'predictions.php');

// Including global.php gives us access to a bunch of MyBB functions and variables
require_once "./global.php";

$lang->load('predictions');

// Only required because we're using misc_help for our page wrapper
$lang->load("misc");

predictions_add_team();

// Add a breadcrumb
add_breadcrumb('Predictions', "predictions.php");

// Calculate votes
$query = $db->query("
SELECT v.*, u.username
FROM ".TABLE_PREFIX."pollvotes v
LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=v.uid)
WHERE v.pid='{$poll['pid']}'
ORDER BY u.username
");
while($voter = $db->fetch_array($query))
{
}

$results = $db->simple_select("predictions_team", "tid,name", "", array(
    "order_by" => 'name',
    "order_dir" => 'ASC'
));

$year = "2018";
$test = <<<HERE
<option value="test">TEST</option>
HERE;


$teams = "";
foreach($results as $value) {
    $teams .= '<option value="'.$value["tid"].'">'.$value["name"].'</option>';
}

$add_team = $templates->get('predictions_add_team');
$add_game = $templates->get('predictions_add_game');
eval('$sections  = "' . $add_team . '\n<br /><br />\n' . $add_game . '";');

// Using the misc_help template for the page wrapper
eval("\$page = \"".$templates->get("misc_help")."\";");

// Spit out the page to the user once we've put all the templates and vars together
output_page($page);

?>