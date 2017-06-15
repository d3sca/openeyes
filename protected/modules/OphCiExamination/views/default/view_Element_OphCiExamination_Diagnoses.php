<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
?>
<?php
$right_principals = array();
$left_principals = array();
foreach ($this->patient->episodes as $episode) {
    if ($episode->id != $this->episode->id && $episode->diagnosis) {
        if (in_array($episode->eye_id, array(Eye::RIGHT, Eye::BOTH))) {
            $right_principals[] = array($episode->diagnosis, $episode->getSubspecialtyText());
        }
        if (in_array($episode->eye_id, array(Eye::LEFT, Eye::BOTH))) {
            $left_principals[] = $episode->diagnosis;
        }
    }
} ?>
<div class="element-data element-eyes row">
    <div class="element-eye right-eye column">
        <?php foreach($right_principals as $disorder) { ?>
            <div class="data-row">
                <div class="data-value">
                    <strong>
                        <?= $disorder[0]->term ?> <span class="has-tooltip fa fa-info-circle" data-tooltip-content="Principal diagnosis for <?= $disorder[1] ?>"></span>
                    </strong>
                </div>
            </div>
        <?php
        }
        $principal = OEModule\OphCiExamination\models\OphCiExamination_Diagnosis::model()->find('element_diagnoses_id=? and principal=1 and eye_id in (2,3)', array($element->id));
        if ($principal) {
            ?>
            <div class="data-row">
                <div class="data-value">
                    <strong>
                        <?php echo $principal->disorder->term ?>
                    </strong>
                </div>
            </div>
            <?php
        } ?>
        <?php
        $diagnoses = \OEModule\OphCiExamination\models\OphCiExamination_Diagnosis::model()
            ->findAll('element_diagnoses_id=? and principal=0 and eye_id in (2,3)', array($element->id));
        foreach ($diagnoses as $diagnosis) {
            ?>
            <div class="data-row">
                <div class="data-value">
                    <?php echo $diagnosis->disorder->term ?>
                </div>
            </div>
            <?php
        } ?>
    </div>
    <div class="element-eye left-eye column">
        <?php foreach($left_principals as $disorder) { ?>
            <div class="data-row">
                <div class="data-value">
                    <strong>
                        <?= $disorder[0]->term ?> (<?= $disorder[1] ?>)
                    </strong>
                </div>
            </div>
            <?php
        }
        $principal = \OEModule\OphCiExamination\models\OphCiExamination_Diagnosis::model()
            ->find('element_diagnoses_id=? and principal=1 and eye_id in (1,3)', array($element->id));
        if ($principal) {
            ?>
            <div class="data-row">
                <div class="data-value">
                    <strong>
                        <?php echo $principal->disorder->term ?>
                    </strong>
                </div>
            </div>
            <?php
        } ?>
        <?php
        $diagnoses = \OEModule\OphCiExamination\models\OphCiExamination_Diagnosis::model()
            ->findAll('element_diagnoses_id=? and principal=0 and eye_id in (1,3)', array($element->id));
        foreach ($diagnoses as $diagnosis) {
            ?>
            <div class="data-row">
                <div class="data-value">
                    <?php echo $diagnosis->disorder->term ?>
                </div>
            </div>
            <?php
        } ?>
    </div>
</div>

