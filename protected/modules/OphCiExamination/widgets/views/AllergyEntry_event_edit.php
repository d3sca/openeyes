<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2016
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2016, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

use OEModule\OphCiExamination\models\AllergyEntry;
?>

<?php
if (!isset($values)) {
    $values = array(
        'id' => $entry->id,
        'allergy_id' => $entry->allergy_id,
        'allergy_display' => $entry->displayallergy,
        'other' => $entry->other,
        'comments' => $entry->comments,
        'has_allergy' => $entry->has_allergy
    );
}
?>

<tr class="row-<?=$row_count;?><?php if(!$removable){ echo " read-only"; } ?>" data-key="<?=$row_count;?>">
    <td>
        <input type="hidden" name="<?= $field_prefix ?>[id]" value="<?=$values['id'] ?>" />
        <input type="hidden" name="<?= $field_prefix ?>[other]" value="<?=$values['other'] ?>" />

        <?php if ($removable): ?>
            <?=$values['allergy_display']; ?>
            <input type="hidden" name="<?= $field_prefix ?>[allergy_id]" value="<?=$values['allergy_id'] ?>" />
        <?php endif; ?>
    </td>
    <td id="<?= $model_name ?>_entries_<?=$row_count?>_allergy_has_allergy">
        <label class="inline highlight">
            <?php echo CHtml::radioButton($field_prefix . '[has_allergy]', $posted_not_checked, array('value' => AllergyEntry::$NOT_CHECKED)); ?>
            Not checked
        </label>
        <label class="inline highlight">
            <?php echo CHtml::radioButton($field_prefix . '[has_allergy]', $values['has_allergy'] === (string) AllergyEntry::$PRESENT, array('value' => AllergyEntry::$PRESENT)); ?>
            yes
        </label>
        <label class="inline highlight">
            <?php echo CHtml::radioButton($field_prefix . '[has_allergy]', $values['has_allergy'] === (string) AllergyEntry::$NOT_PRESENT, array('value' => AllergyEntry::$NOT_PRESENT)); ?>
            no
        </label>
    </td>
    <td>
        <?php if ($removable): ?>
            <?php echo CHtml::textField($field_prefix . '[comments]', $values['comments'], array('autocomplete' => Yii::app()->params['html_autocomplete']))?>
        <?php else: ?>
          <div class="cols-full ">
            <button class="button  js-add-comments" data-input="next" style="display: none;">
              <i class="oe-i comments  small-icon"></i>
            </button>
            <textarea placeholder="Comments" autocomplete="off" rows="1" class="cols-full" style="overflow-x: hidden; word-wrap: break-word;"></textarea>
          </div>
        <?php endif; ?>
    </td>

    <td>
      <i class="oe-i trash"></i>
    </td>
</tr>