<?php

if(!isset($currentStep)){
    $currentStep=1;
}

$totalSteps=7;

$progress=round(($currentStep/$totalSteps)*100);

$steps=[

1=>[
"title"=>"Personal",
"icon"=>"bi-person-fill"
],

2=>[
"title"=>"Family",
"icon"=>"bi-people-fill"
],

3=>[
"title"=>"Lifestyle",
"icon"=>"bi-heart-pulse-fill"
],

4=>[
"title"=>"Location",
"icon"=>"bi-geo-alt-fill"
],

5=>[
"title"=>"Photo",
"icon"=>"bi-camera-fill"
],

6=>[
"title"=>"Preference",
"icon"=>"bi-heart-fill"
],

7=>[
"title"=>"Values",
"icon"=>"bi-stars"
]

];

?>

<div class="mb-4">

<div class="d-flex justify-content-between align-items-center mb-3">

<?php foreach($steps as $number=>$step){ ?>

<div class="text-center flex-fill">

<div
class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center

<?= $number<$currentStep ? 'bg-success text-white' : '' ?>

<?= $number==$currentStep ? 'bg-success text-white shadow' : '' ?>

<?= $number>$currentStep ? 'bg-light text-secondary border' : '' ?>

"

style="width:46px;height:46px;">

<i class="bi <?= $step['icon'] ?>"></i>

</div>

<div style="font-size:13px;">

<?= $step['title'] ?>

</div>

</div>

<?php } ?>

</div>

<div class="progress rounded-pill" style="height:10px;">

<div
class="progress-bar bg-success"

style="width:<?= $progress ?>%">

</div>

</div>

<div class="text-end mt-2">

<small class="text-muted">

Step <?= $currentStep ?>

of

<?= $totalSteps ?>

</small>

</div>

</div>