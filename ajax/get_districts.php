<?php

require_once '../config/db.php';

if(!isset($_GET['division_id'])){
    exit;
}

$division_id=(int)$_GET['division_id'];

$query = mysqli_query($conn,"
SELECT
id,
name_bn
FROM districts
WHERE division_id=$division_id
ORDER BY name_bn
");

echo '<option value="">Select District</option>';

while($row=mysqli_fetch_assoc($query)){

echo '<option value="'.$row['id'].'">'.$row['name_bn'].'</option>';

}

?>