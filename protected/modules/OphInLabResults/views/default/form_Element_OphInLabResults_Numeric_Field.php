<section id="result-output" class="element-fields">
    <div class="element-fields">
        <div class="active-form">
            <table class="standard cols-full">
                <colgroup>
                    <col class="cols-2">
                    <col class="cols-4">
                </colgroup>
                <tbody>
                <tr>
                    <td>
                        <?= $form->hiddenInput($element, 'type'); ?>
                        <?php echo $form->textField($element, 'time',['type' => 'time']); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="data-group flex-layout cols-full">
                            <div class="cols-2">Result</div>
                            <div class="cols-10">
                                <?php echo $form->numberField($element, 'result'); ?>
                                <span class="large-text highlighter orange js-lab-result-warning"
                                      style="<?php if (isset($element->result) &&
                                          ($element->result > $element->resultType->normal_max || $element->result < $element->resultType->normal_min)) {
                                          echo "display:block";
                                      } else {
                                          echo "display:none";
                                      } ?>">
                    <?php if ($element->resultType->custom_warning_message) {
                        echo $element->resultType->custom_warning_message;
                     } else { ?>
                        The value is outside the normal range. Normal min: <?= $element->resultType->normal_min ?> Normal max: <?= $element->resultType->normal_max ?>
                    <?php } ?> </span>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php $element->setDefaultUnit();?>
                        <?php echo $form->textField($element, 'unit'); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php echo $form->textArea($element, 'comment', $element->getHtmlOptionsForInput('comment'), array(), ['maxlength' => '250']); ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script type="text/javascript">
    $(document).ready(function () {
        console.log($('#result-output'));
        console.log($('#<?= CHtml::modelName($element)?>_result'));
        $('#<?= CHtml::modelName($element)?>_result').on('input', function () {
            console.log("show me what's going on");
        });
    })
</script>