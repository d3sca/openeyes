<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>
<div class="row divider">
    <h2>Drug Sets</h2>
</div>
<?php $isSelected = function($usage_code) use ($search) {
    if (isset($search['usage_codes']) && in_array($usage_code, $search['usage_codes'])) {
        return 'green hint';
    }

    return '';
} ?>

<div class="row divider">
    <form id="drug_set_search" method="post">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?= Yii::app()->request->csrfToken ?>"/>

        <div id="set-filters" class="flex-layout row">
            <button
                    data-usage_code=""
                    type="button"
                    class="large js-set-select js-all-sets<?= !isset($search['usage_codes']) || empty($search['usage_codes']) ? ' green hint' : '' ?>"
            >All Sets</button>

            <?php $codes = [
                    'COMMON_OPH' => 'Common Ophthalmic Drug Sets',
                    'COMMON_SYSTEMIC' => 'Common Systemic Drug  Sets',
                    'PRESCRIPTION_SET' => 'Prescription Drug Sets',
                    'Drug' => 'Drug',
                    'DrugTag' => 'Drug Tags',
                    'Formulary' => 'Formulary Drugs',
                    'MedicationDrug' => 'Medication Drug',
            ]; ?>
            <?php foreach ($codes as $usage_code => $desc) :?>
                <button
                        data-usage_code="<?=$usage_code;?>"
                        type="button"
                        class="large js-set-select <?=$isSelected($usage_code);?>"
                ><?=$desc;?>
                </button>
            <?php endforeach; ?>
        </div>

        <hr class="">

            <table class="cols-8">
                <colgroup>
                    <col class="cols-6">
                    <col class="cols-2">
                    <col class="cols-2">
                    <col class="cols-2">
                </colgroup>

                <tbody>
                <tr class="col-gap">
                    <td>
                        <?= \CHtml::textField(
                            'search[query]',
                            $search['query'],
                            ['class' => 'cols-full', 'placeholder' => "Name"]
                        ); ?>
                    </td>

                    <td><?= \CHtml::dropDownList('search[subspecialty_id]', $search['subspecialty_id'],
                            \CHtml::listData(Subspecialty::model()->findAll(), 'id', 'name'),
                            ['empty' => '- Subspecialty -']
                        ) ?>
                    </td>
                    <td><?= \CHtml::dropDownList('search[site_id]', $search['site_id'],
                            \CHtml::listData(Site::model()->findAll(), 'id', 'name'),
                            ['empty' => '- Site -']
                        ) ?>
                    </td>
                    <td>
                        <button class="blue hint" type="button" id="et_search">Search</button>
                    </td>
                </tr>
                </tbody>
            </table>
    </form>
</div>

<div class="cols-12">
    <form id="admin_DrugSets">
        <table id="drugset-list" class="standard">
            <colgroup>
                <col class="cols-1" style="width:3.33333%">
                <col class="cols-1" style="width:3.33333%">
                <col class="cols-2">
                <col class="cols-4">
                <col class="cols-1">
                <col class="cols-1">
            </colgroup>
            <thead>
            <tr>
                <th><?= \CHtml::checkBox('selectall'); ?></th>
                <th>Id</th>
                <th>Name</th>
                <th>Rule</th>
                <th>Count</th>
                <th>Hidden/system</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($data_provider->getData() as $set) {
                        $this->renderPartial('/DrugSet/_row', ['set' => $set]);
                    }
                ?>
            </tbody>
            <tfoot class="pagination-container">
            <tr>
                <td colspan="4">
                    <?= \CHtml::submitButton('Add', [
                        'id' => 'et_add',
                        'data-uri' => "/OphDrPrescription/admin/drugSet/edit",
                        'class' => 'button large'
                    ]); ?>
                    <?= \CHtml::submitButton('Delete', [
                        'id' => 'et_delete',
                        'data-uri' => '/OphDrPrescription/admin/drugSet/delete',
                        'class' => 'button large',
                        'data-object' => 'DrugSet'
                    ]); ?>
                </td>
                <td colspan="4">
                    <?php $this->widget('LinkPager', ['pages' => $data_provider->pagination]); ?>
                </td>
            </tr>
            </tfoot>
        </table>
    </form>
</div>

<script type="text/html" id="medication_set_template" style="display:none">
    <tr>
        <td><input type="checkbox" value="{{id}}" name="delete-ids[]" /></td>
        <td>{{id}}</td>
        <td>{{name}}</td>
        <td>{{rules}}</td>
        <td>{{count}}</td>
        <td>{{hidden}}</td>
        <td><a href="/OphDrPrescription/admin/DrugSet/edit/{{id}}" class="button">Edit</a></td>
    </tr>
</script>

<script>
    var drugSetController = new OpenEyes.OphDrPrescriptionAdmin.DrugSetController();
</script>