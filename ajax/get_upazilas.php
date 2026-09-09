<?php

require_once '../config/db.php';

if(!isset($_GET['district_id'])){
    exit;
}

$district_id=(int)$_GET['district_id'];

$query=mysqli_query($conn,"
SELECT
id,
name_en
FROM upazilas
WHERE district_id=$district_id
ORDER BY name_en
");

echo '<option value="">Select Upazila</option>';

while($row=mysqli_fetch_assoc($query)){

echo '<option value="'.$row['id'].'">'.$row['name_en'].'</option>';

}

?>