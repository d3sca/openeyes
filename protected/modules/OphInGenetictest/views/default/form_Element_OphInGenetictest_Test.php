<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
?>

<section class="element <?php echo $element->elementType->class_name?>"
	data-element-type-id="<?php echo $element->elementType->id?>"
	data-element-type-class="<?php echo $element->elementType->class_name?>"
	data-element-type-name="<?php echo $element->elementType->name?>"
	data-element-display-order="<?php echo $element->elementType->display_order?>">

	<div class="element-fields">
		<?php echo $form->dropDownList($element, 'gene_id', CHtml::listData(PedigreeGene::model()->findAll(array('order'=>'name asc')),'id','name'), array('empty' => '- Select -'), false, array('label' => 3, 'field' => 9))?>
		<?php echo $form->dropDownList($element, 'method_id', CHtml::listData(OphInGenetictest_Test_Method::model()->findAll(array('order'=>'name asc')),'id','name'), array('empty' => '- Select -'), false, array('label' => 3, 'field' => 9))?>
		<?php echo $form->dropDownList($element, 'effect_id', CHtml::listData(OphInGenetictest_Test_Effect::model()->findAll(array('order'=>'name asc')),'id','name'), array('empty' => '- Select -'), false, array('label' => 3, 'field' => 9))?>
		<?php echo $form->textField($element, 'exon', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->textField($element, 'prime_rf', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->textField($element, 'prime_rr', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->textField($element, 'base_change', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->textField($element, 'amino_acid_change', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->textField($element, 'assay', array(), array(), array('label' => 3, 'field' => 3))?>
		<?php echo $form->radioBoolean($element, 'homo', array(), array('label' => 3, 'field' => 9))?>
		<?php echo $form->textField($element, 'result', array(), array(), array('label' => 3, 'field' => 5))?>
		<?php echo $form->datePicker($element, 'result_date', array(), array(), array('label' => 3, 'field' => 2))?>
		<?php echo $form->textArea($element, 'comments', array(), false, array(), array('label' => 3, 'field' => 5))?>
	</div>
</section>
