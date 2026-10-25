<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/db.php';

if(!isset($_GET['division_id'])){
    exit;
}

$division_id=(int)$_GET['division_id'];

$query = mysqli_query($conn,"
SELECT
id,
name_en, name_bn
FROM districts
WHERE division_id=$division_id
ORDER BY name_en
");

echo '<option value="">' . (((($_SESSION['sm_language'] ?? $_COOKIE['sm_language'] ?? 'bn') === 'en')) ? 'Select District' : 'জেলা নির্বাচন করুন') . '</option>';

while($row=mysqli_fetch_assoc($query)){

echo '<option value="'.$row['id'].'">'.htmlspecialchars(((($_SESSION['sm_language'] ?? $_COOKIE['sm_language'] ?? 'bn') === 'en') ? $row['name_en'] : ($row['name_bn'] ?: $row['name_en']))).'</option>';

}

?>