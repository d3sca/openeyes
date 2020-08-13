<?php
/**
 * (C) Copyright Apperta Foundation, 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2020, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

$form_id = 'theatreadmission-create';
$this->beginContent('//patient/event_container', array('no_face'=>true , 'form_id' => $form_id));?>

<?php $form = $this->beginWidget('BaseEventTypeCActiveForm', array(
    'id'=>'create-form',
    'enableAjaxValidation'=>false,
    'layoutColumns' => array(
        'label' => 2,
        'field' => 10
    )
));

// Event actions
$this->event_actions[] = EventAction::button('Save draft', 'savedraft', array('level' => 'secondary'), array('id' => 'et_save_draft', 'class' => 'button small', 'form' => 'create-form'));
$this->event_actions[] = EventAction::button('Save', 'save', array('level' => 'save'), array('form'=>'create-form'));

?>
    <script type='text/javascript'>
        $(document).ready( function(){
            window.formHasChanged = true;
        });
    </script>

<?php $this->displayErrors($errors)?>
    <input type="hidden" id="isDraft" name="isDraft" value="<?php echo isset($_POST['isDraft']) ? $_POST['isDraft'] : '' ?>" />
<?php $this->renderPartial('//patient/event_elements', array('form' => $form));?>
<?php $this->displayErrors($errors, true)?>

<?php $this->endWidget()?>
<?php $this->endContent();?>