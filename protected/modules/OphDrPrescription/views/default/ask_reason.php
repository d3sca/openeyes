<?php $this->beginContent('//patient/event_container'); ?>

<?php

    $this->event_actions[] = EventAction::link('Cancel', '/OphDrPrescription/default/view/'.$id, [], ['class'=>'button small cancel']);
    $this->event_tabs[] = array(
        'label' => 'View',
        'href' => '/OphDrPrescription/default/view/'.$id
    );

    $this->event_tabs[] = array(
        'label' => 'Edit',
        'href' => '#',
        'active' => true
    );
?>


<section class="element">
  <div class="element-fields">
<div style="padding-left: 25px;">

<h1><i class="oe-i triangle"></i> Reason required</h1>

    <?php
        $text = '';
        if(($draft == 0) && ($printed == 0)){
            $text = 'finalised';
        } else {
            $text = 'printed';
        }
    ?>
    This prescription has been <?php echo $text; ?>. Changes to <?php echo $text; ?> prescriptions must
    only be made under specific circumstances. Please select a reason from the list below:


<?php

$reasons = OphDrPrescriptionEditReasons::model()->findAll(array('order'=>'display_order', 'condition'=>'active = 1'));

?>
<?php echo CHtml::form('/OphDrPrescription/default/update/'.$id.'?reason=selected', 'get'); ?>
    <input type="hidden" name="do_not_save" value="1" />
    <input type="hidden" name="reason" id="reason" />


    <?php foreach ($reasons as $key=>$reason): ?>
        <div>
            <button class="hint blue submit" data-value="<?php echo $reason->id; ?>" id="reason_<?php echo $reason->id; ?>" style="margin-bottom: 15px;"><?php echo htmlentities($reason->caption); ?></button>
        </div>
    <?php endforeach; ?>


  <div class="row">
        <div class="cols-6 column">
            <textarea rows="5" cols="40" readonly type="text" id="reason_other_text" name="reason_other"></textarea>
        </div>
        <div class="column cols-6">
            <div id="other_reason_controls" style="display: none;">
                <a href="javascript:void(-1);" id="submit_other" style="color: #3fa522;"><i class="fa fa-check fa-2x"></i></a>
                <br/>
                <a href="javascript:void(-1);" id="cancel_other" style="color: #eb5911;"><i class="fa fa-times fa-2x"></i></a>
            </div>
        </div>
    </div>

<?php echo CHtml::endForm() ?>
<br/>
  <div class="alert-box warning">
    Any old paper copies of this prescription MUST BE DESTROYED.
  </div>
</div>

  </div>
</section>

<?php $this->endContent();?>



<script type="text/javascript">
    $(function(){
        window.onbeforeunload = null;
        $(document).on("click","button.submit", function(e){
            e.preventDefault();
            var value = $(this).data('value');
            if(value=='1')
            {
                $('#reason').val(1);
                $('#reason_other_text').removeAttr("readonly").focus();
                $('#other_reason_controls').show();
                var $buttons = $('button.submit').not(this);
                $buttons.attr("disabled", "disabled");
                $buttons.css("pointer-events", "none");
                $buttons.removeClass("blue");
            }
            else
            {
                $('#reason_other_text').val("").attr("readonly", "readonly");
                $('#other_reason_controls').hide();
                $('#reason').val(value);
                $(this).closest('form').submit();
            }
        });

        $("#cancel_other").click(function(e){
            $('#reason_other_text').val("").attr("readonly", "readonly");
            $('#other_reason_controls').hide();
            $('#reason').val('');
            var $buttons = $('button.submit').not(this);
            $buttons.removeAttr("disabled");
            $buttons.css("pointer-events", "");
            $buttons.addClass("blue");
        });

        $("#submit_other").click(function(e){
            $(this).closest('form').submit();
        });

    });
</script>