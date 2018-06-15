<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2017
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

use OEModule\OphCiExamination\models\SystemicDiagnoses_Diagnosis;
/** @var \OEModule\OphCiExamination\models\SystemicDiagnoses $element */
?>

<script type="text/javascript" src="<?=$this->getJsPublishedPath('SystemicDiagnoses.js')?>"></script>
<script type="text/javascript" src="<?=$this->getJsPublishedPath('OpenEyes.UI.DiagnosesSearch.js', true)?>"></script>

<?php
    $model_name = CHtml::modelName($element);
    $missing_req_diagnoses = $this->getMissingRequiredSystemicDiagnoses();
    $required_diagnoses_ids = array_map(function($r) { return $r->id; }, $this->getRequiredSystemicDiagnoses());
?>

<div class="element-fields flex-layout full-width" id="<?=CHtml::modelName($element);?>_element">

    <input type="hidden" name="<?= $model_name ?>[present]" value="1" />
    <table class="cols-10" id="<?= $model_name ?>_diagnoses_table">
        <thead>
        <tr>
          <th></th>
          <th>Checked</th>
          <th>Right</th>
          <th>Both</th>
          <th>Left</th>
          <th>N/A</th>
          <th>Date (optional)</th>
          <th></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $row_count = 0;
        foreach ($missing_req_diagnoses as $diagnosis) {
            $this->render(
                'SystemicDiagnosesEntry_event_edit',
                array(
                    'diagnosis' => $diagnosis,
                    'form' => $form,
                    'model_name' => CHtml::modelName($element),
                    'row_count' => $row_count,
                    'field_prefix' => $model_name . "[entries][$row_count]",
                    'removable' => false,
                    'posted_checked_status' => $element->widget->getPostedCheckedStatus($row_count),
                )
            );
            $row_count++;
        } ?>

        <?php
        foreach (array_merge($element->diagnoses, $this->getCheckedRequiredSystemicDiagnoses()) as $diagnosis) {
            $this->render(
                'SystemicDiagnosesEntry_event_edit',
                array(
                    'diagnosis' => $diagnosis,
                    'form' => $form,
                    'model_name' => CHtml::modelName($element),
                    'row_count' => $row_count,
                    'field_prefix' => $model_name . "[entries][$row_count]",
                    'removable' => !in_array($diagnosis->disorder_id, $required_diagnoses_ids),
                    'posted_checked_status' => $element->widget->getPostedCheckedStatus($row_count)
                )
            );
            $row_count++;
        }
        ?>
        </tbody>
    </table>
  <div class="flex-item-bottom" id="systemic-diagnoses-popup">
    <button class="button hint green js-add-select-search" type="button" id="add-history-systemic-diagnoses">
      <i class="oe-i plus pro-theme"></i>
    </button>

    <div id="add-to-systemic-diagonses" class="oe-add-select-search" style="display: none;">
      <!-- icon btns -->
      <div class="close-icon-btn" type="button"><i class="oe-i remove-circle medium"></i></div>
      <div class="select-icon-btn" type="button"><i id="systemic-diagonses-select-btn" class="oe-i menu"></i></div>
      <button class="button hint green add-icon-btn" type="button">
        <i class="oe-i plus pro-theme"></i>
      </button>
      <!-- select (and search) options for element -->
      <table class="select-options" id='systemic-diagnoses-select-options'>
        <tbody>
        <tr>
          <td>
            <div class="flex-layout flex-top flex-left">
              <ul class="add-options" data-multi="true" data-clickadd="false" id="systemic-diagonses-option">
                  <?php $systemicDiagnoses = [];
                  foreach (CommonSystemicDisorder::getDisorders() as $disorder) { ?>
                      <li data-str="<?php echo $disorder->term ?>" data-id="<?php echo $disorder->id?>">
                        <span class="auto-width">
                          <?php echo $disorder->term; ?>
                      </span>
                    </li>
                  <?php } ?>
              </ul>
            </div>
            <!-- flex-layout -->
          </td>
        </tr>
        </tbody>
      </table>
      <div class="search-icon-btn"><i id="systemic-diagonses-search-btn" class="oe-i search"></i></div>
      <div class="systemic-diagonses-search-options" style="display: none;">
        <table class="cols-full last-left">
          <thead>
          <tr>
            <th>
              <input id="systemic-diagonses-search-field"
                     class="search"
                     placeholder="Search for Drug"
                     type="text">
            </th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <td>
              <ul id="systemic-diagonses-search-results" class="add-options" data-multi="true" style="width: 100%;">
              </ul>
              <span id="systemic-diagonses-search-no-results">No results found</span>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div><!-- oe-add-select-search -->

  </div>
</div>
<script type="text/template" class="entry-template hidden" id="<?= CHtml::modelName($element).'_template'?>">
    <?php
    $empty_entry = new \OEModule\OphCiExamination\models\SystemicDiagnoses_Diagnosis();
    $this->render(
        'SystemicDiagnosesEntry_event_edit',
        array(
            'diagnosis' => $empty_entry,
            'form' => $form,
            'model_name' => $model_name,
            'field_prefix' => $model_name . '[entries][{{row_count}}]',
            'row_count' => '{{row_count}}',
            'removable' => true,
            'posted_checked_status' => false,
            'has_disorder' => false,
            'values' => array(
                'id' => '',
                'disorder_id' => '{{disorder_id}}',
                'disorder_display' => '{{disorder_display}}',
                'side_id' => '{{side_id}}',
                'side_display' => '{{side_display}}',
                'date' => '{{date}}',
                'date_display' => '{{date_display}}',
                'row_count' => '{{row_count}}',
                'has_disorder' => SystemicDiagnoses_Diagnosis::$PRESENT,
            )
        )
    );
    ?>
</script>
<script type="text/javascript">
  var systemicDiagnosesController;
  $(document).ready(function() {
    systemicDiagnosesController =  new OpenEyes.OphCiExamination.SystemicDiagnosesController({
      element: $('#<?=$model_name?>_element')
    });
  });

  var popup = $('#add-to-systemic-diagonses');

  function addSystemicDiagnoses() {
    systemicDiagnosesController.addEntry();
  }

  setUpAdder(
    popup,
    'multi',
    addSystemicDiagnoses,
    $('#add-history-systemic-diagnoses'),
    popup.find('.add-icon-btn'),
    $('.close-icon-btn, .add-icon-btn')
  );
</script>