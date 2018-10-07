<?php
/* @var $this PatientController */
/* @var $model Patient */
?>
<div class="oe-full-header flex-layout">
  <div class="title wordcaps">Update <b>Patient</b></div>
</div>
<?php $this->renderPartial('crud/_form', array(
    'patient' => $patient,
    'contact' => $contact,
    'address' => $address,
)); ?>
