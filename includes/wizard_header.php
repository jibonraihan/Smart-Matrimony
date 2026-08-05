<?php

if (!isset($currentStep)) {
    $currentStep = 1;
}

if (!isset($pageTitle)) {
    $pageTitle = "";
}

if (!isset($pageDescription)) {
    $pageDescription = "";
}

$totalSteps = 7;

$progress = round(($currentStep / $totalSteps) * 100);

?>

<div class="d-flex align-items-center justify-content-between mb-2">

    <div>

        <h2 class="fw-bold text-success mb-1">

            Step <?= $currentStep ?>

        </h2>

        <p class="text-muted mb-0">

            <?= htmlspecialchars($pageTitle) ?>

        </p>

    </div>

    <div>

        <img
src="<?= BASE_URL ?>assets/images/logo/logo.png"
        alt="Smart Matrimony"
        style="width:95px;height:auto;"
        class="img-fluid"
    object-fit:contain;">

    </div>

</div>

<?php if($pageDescription!=""){ ?>

<div class="alert alert-info py-2 px-3 small mb-4">

    <i class="bi bi-info-circle me-2"></i>

    <?= htmlspecialchars($pageDescription) ?>

</div>

<?php } ?>

<?php

$steps=[

1=>[
"title"=>"Personal",
"icon"=>"bi-person-circle"
],

2=>[
"title"=>"Family",
"icon"=>"bi-people"
],

3=>[
"title"=>"Lifestyle",
"icon"=>"bi-heart"
],

4=>[
"title"=>"Location",
"icon"=>"bi-geo-alt"
],

5=>[
"title"=>"Privacy",
"icon"=>"bi-shield-lock"
],

6=>[
"title"=>"Partner",
"icon"=>"bi-heart-fill"
],

7=>[
"title"=>"Values",
"icon"=>"bi-chat-square-heart"
]

];

?>

<div class="mb-4">

    <div class="d-flex justify-content-between align-items-center">

        <?php foreach($steps as $stepNo=>$step){ ?>

        <div class="text-center flex-fill">

            <div

            class="mx-auto rounded-circle d-flex align-items-center justify-content-center

            <?=

            $stepNo<$currentStep

            ?'bg-success text-white'

            :

            (

            $stepNo==$currentStep

            ?

            'bg-success text-white shadow'

            :

            'bg-light border text-secondary'

            )

            ?>

            "

            style="

            width:52px;

            height:52px;

            ">

                <i class="bi <?= $step['icon'] ?>"></i>

            </div>

            <small class="d-block mt-2">

                <?= $step['title'] ?>

            </small>

        </div>

        <?php } ?>

    </div>

    <div class="progress mt-4 rounded-pill"

    style="height:10px;">

        <div

        class="progress-bar bg-success"

        style="width:<?= $progress ?>%">

        </div>

    </div>

    <div class="text-end mt-2">

        <small class="fw-semibold text-success">

            <?= $progress ?>%

        </small>

    </div>

</div>