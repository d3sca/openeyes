<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>
<?php
//$clinical = $this->checkAccess('OprnViewClinical');
$warnings = $this->patient->getWarnings($allow_clinical);
Yii::app()->assetManager->registerCssFile('components/font-awesome/css/font-awesome.css', null, 10);
$navIconsUrl = Yii::app()->assetManager->publish(Yii::getPathOfAlias('application.assets.newblue.svg') . '/oe-nav-icons.svg');

?>

<div id="oe-patient-details" class="oe-patient">
  <div class="patient-name">
    <span class="patient-surname"><?php echo $this->patient->getLast_name(); ?></span>,
    <span class="patient-firstname">
      <?php echo $this->patient->getFirst_name(); ?>
      <?php echo $this->patient->getTitle() ? "({$this->patient->getTitle()})" : ''; ?>
    </span>
  </div>

  <div class="patient-details">
    <div class="hospital-number">
      <span>No. </span>
        <?php echo $this->patient->hos_num ?>
    </div>
    <div class="nhs-number">
      <span>NHS</span>
        <?php echo $this->patient->nhsnum ?>
        <?php if ($this->patient->nhsNumberStatus && $this->patient->nhsNumberStatus->isAnnotatedStatus()): ?>
          <i class="fa fa-asterisk" aria-hidden="true"></i><span
              class="messages"><?= $this->patient->nhsNumberStatus->description; ?></span>
        <?php endif; ?>
    </div>

    <div class="patient-gender">
      <em>Gender</em>
        <?php echo $this->patient->getGenderString() ?>
    </div>

    <div class="patient-age">
      <em>Age</em>
        <?php echo $this->patient->getAge(); ?>
    </div>

  </div>

  <div id="js-allergies-risks-btn" class="patient-allergies-risks">
    <div class="patient-warning">Allergies, Risks</div>
    <svg class="icon" viewBox="0 0 30 30">
      <use xlink:href="<?php echo $navIconsUrl; ?>#warning-icon"></use>
    </svg>
  </div>
  <div id="js-demographics-btn" class="patient-demographics">
    <svg class="icon" viewBox="0 0 60 60">
      <use xlink:href="<?php echo $navIconsUrl; ?>#patient-icon"></use>
    </svg>
  </div>
  <div id="js-quicklook-btn" class="patient-quicklook">
    <svg class="icon" viewBox="0 0 30 30">
      <use xlink:href="<?php echo $navIconsUrl; ?>#quicklook-icon"></use>
    </svg>
  </div>
  <div id="js-lightening-viewer-btn" class="patient-lightening-viewer">
    <svg viewBox="0 0 30 30" class="icon">
      <use xlink:href="<?php echo $navIconsUrl; ?>#lightening-viewer-icon"></use>
    </svg>
  </div>

     <!-- Widgets (extra icons, links etc) -->
  <ul class="patient-widgets">
      <?php foreach ($this->widgets as $widget) {
        echo "<li>{$widget}</li>";
        }?>
        </ul>
  </div>
</div>