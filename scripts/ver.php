<?php
echo(phpinfo());

// echo json_encode(ini_get_all());
$iniVals = ini_get_all();


echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">';
echo '<DIV class="col-md-12 order-md-1 table-responsive">';
echo '<H2>PHP Ini Values</H2>';
echo '<TABLE class="table-fixed table table-striped table-bordered table-sm">';
echo '<thead>';
echo '<TR class="th-sm"><TH>Key</TH><TH>Value</TH></TR>';
echo '</thead>';
echo '<tbody>';

function doThisOne ($theKey, $theVals)
{
    echo '<TR><TH class="th-sm">' . $theKey . '</TH>';
    echo '<TD' . (($theVals['global_value'] == $theVals['local_value']) ? '' : ' class="table-success"') . '>';
    echo $theVals['local_value'] .  (($theVals['global_value'] == $theVals['local_value']) ? '' : ' (' . $theVals['global_value'] . ')');
    echo '</TD></TR>';
}

foreach (array_keys($iniVals) as $key)
{
    if (! strlen($iniVals[$key]['local_value']) == 0)
    {
        doThisOne ($key, $iniVals[$key]);
    }
}
foreach (array_keys($iniVals) as $key)
{
    if (strlen($iniVals[$key]['local_value']) == 0)
    {
        doThisOne ($key, $iniVals[$key]);
    }
}

echo '</tbody>';
echo '</TABLE>';
echo '</DIV>';

?>
