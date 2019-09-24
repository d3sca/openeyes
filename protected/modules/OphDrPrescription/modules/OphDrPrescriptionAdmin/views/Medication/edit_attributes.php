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
<?php
    /** @var Medication $medication */

    $attributes = array_map(function ($e) {
        return ['id' => $e->id, 'label' => $e->name, 'type' => 'attr'];
    }, MedicationAttribute::model()->findAll(array("order" => "name")));
    $options = array_map(function ($e) {
        return ['id' => $e->id, 'label' => $e->description." - ".$e->value, 'attr_id' => $e->medication_attribute_id];
    }, MedicationAttributeOption::model()->findAll(array("select"=>array("id","medication_attribute_id","value","description"), "order" => "value")));
    ?>
<script id="row_template" type="x-tmpl-mustache">
    <tr data-key="{{ key }}">
        <td>
            <input class="js-attribute" type="hidden" name="Medication[medicationAttributeAssignments][{{key}}][medication_id]" value="{{attribute_id}}" />
            {{attribute_name}}
        </td>
        <td>
            <input class="js-option" type="hidden" name="Medication[medicationAttributeAssignments][{{key}}][medication_attribute_option_id]" value={{option_id}} />
            {{option_name}}
        </td>
        <td>
            <a href="javascript:void(0);" class="js-delete-attribute"><i class="oe-i trash"></i></a>
        </td>
    </tr>
</script>
<h3>Attributes</h3>
<table class="standard" id="medication_attribute_assignment_tbl">
    <thead>
        <tr>
            <th width="25%">Name</th>
            <th width="50%">Value</th>
            <th width="25%">Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($medication->medicationAttributeAssignments as $rowkey => $assignment) : ?>
        <?php
            $attr_id = isset($assignment->medicationAttributeOption) ? $assignment->medicationAttributeOption->medication_attribute_id : null;
            $attr_name = isset($assignment->medicationAttributeOption) ? $assignment->medicationAttributeOption->medicationAttribute->name : "";
            $option_id = isset($assignment->medicationAttributeOption) ? $assignment->medicationAttributeOption->id : null;
            $option_name = isset($assignment->medicationAttributeOption) ? $assignment->medicationAttributeOption->description." - ".$assignment->medicationAttributeOption->value : null;
        ?>
        <tr data-key="<?=$rowkey?>">
            <td>
                <input type="hidden" name="Medication[medicationAttributeAssignments][<?=$rowkey?>][id]" value="<?=$assignment->id?>" />
                <input type="hidden" name="Medication[medicationAttributeAssignments][<?=$rowkey?>][medication_id]" value="<?=$attr_id?>" />
                <?= CHtml::encode($attr_name); ?>
            </td>
            <td>
                <input type="hidden" name="Medication[medicationAttributeAssignments][<?=$rowkey?>][medication_attribute_option_id]" value="<?=$option_id?>" />
                <?= CHtml::encode($option_name); ?>
            </td>
            <td>
                <a href="javascript:void(0);" class="js-delete-attribute"><i class="oe-i trash"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="pagination-container">
        <tr>
            <td colspan="3">
                <div class="flex-layout flex-right">
                    <button class="button hint green js-add-attribute" type="button"><i class="oe-i plus pro-theme"></i></button>
                    <script type="text/javascript">
                        new OpenEyes.UI.AdderDialog({
                            openButton: $('.js-add-attribute'),
                            itemSets: [
                                new OpenEyes.UI.AdderDialog.ItemSet(<?= CJSON::encode($attributes) ?>, {'multiSelect': false, header: "Attributes"}),
                                new OpenEyes.UI.AdderDialog.ItemSet(<?= CJSON::encode($options) ?>, {'multiSelect': false, header: "Options"})
                            ],
                            onOpen: function(adderDialog) {
                                let $items = adderDialog.$tr.children("td:eq(1)").find("ul.add-options li");
                                $items.hide();
                            },
                            onReturn: function (adderDialog, selectedItems) {
                                if(selectedItems.length < 2) {
                                    let alert = new OpenEyes.UI.Dialog.Alert({
                                        content: "Please select an attribute and an option"
                                    });

                                    alert.open();
                                    return false;
                                }
                                let attr = selectedItems[0];
                                let opt  = selectedItems[1];
                                let key = OpenEyes.Util.getNextDataKey('#medication_attribute_assignment_tbl tbody tr', 'key');

                                let template = $('#row_template').html();
                                Mustache.parse(template);
                                let rendered = Mustache.render(template, {
                                    "key": key,
                                    "attribute_id": attr.id,
                                    "attribute_name": attr.label,
                                    "option_id": opt.id,
                                    "option_name": opt.label
                                });
                                $("#medication_attribute_assignment_tbl > tbody").append(rendered);
                                return true;
                            },
                            onSelect: function(e) {
                                let $item = $(e.target).is("span") ? $(e.target).closest("li") : $(e.target);
                                let $tr = $item.closest("tr");
                                if($item.data('type') === "attr") {
                                    let $all_options = $tr.children("td:eq(1)").find("ul.add-options li");
                                    let $relevant_options = $tr.children("td:eq(1)").find("ul.add-options li[data-attr_id=" + $item.data('id') + "]");
                                    $all_options.hide();
                                    $relevant_options.show();
                                }
                            },
                            enableCustomSearchEntries: true,
                        });
                    </script>
                </div>
            </td>
        </tr>
    </tfoot>
</table>
<script>
    $(document).ready(function(){
        $("#medication_attribute_assignment_tbl").on("click", ".js-delete-attribute", function (e) {
            $(this).closest("tr").remove();
        });
    });
</script>
