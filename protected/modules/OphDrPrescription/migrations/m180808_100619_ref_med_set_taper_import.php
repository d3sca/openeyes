<?php

class m180808_100619_ref_med_set_taper_import extends CDbMigration
{
	public function up()
	{
        $transaction=$this->getDbConnection()->beginTransaction();
        try {
            $tapers = Yii::app()->db->createCommand(
                "SELECT t.id, t.dose, t.frequency_id, t.duration_id, i.id AS drug_set_item_id, 
              i.drug_id AS drug_id,
              s.id AS drug_set_id,
              s.name AS drug_set_name,
              s.subspecialty_id,
              s.active 
              FROM drug_set_item_taper AS t
              LEFT JOIN drug_set_item AS i ON i.id = t.item_id
              LEFT JOIN drug_set AS s ON i.drug_set_id = s.id
              ")->queryAll();
            if($tapers) {
                foreach ($tapers as $taper) {

                    Yii::app()->db->createCommand("INSERT INTO ref_medication_set_taper (
                                      ref_medication_set_id,
                                      dose,
                                      frequency_id,
                                      duration_id
                                      ) VALUES 
                                      (
                                          ( 
                                           SELECT id FROM ref_medication_set 
                                           WHERE
                                            ref_medication_id = ( SELECT id FROM ref_medication WHERE source_old_id = :drug_id AND source_subtype = 'drug' )
                                            AND ref_set_id =
                                              ( SELECT id FROM ref_set WHERE `name` LIKE CONCAT('%', :ref_set_name) AND id IN 
                                                ( SELECT ref_set_id FROM ref_set_rules WHERE subspecialty_id = :subspecialty_id AND usage_code = 'Drug')
                                              )
                                          ),
                                          
                                          :dose,
                                          :frequency_id,
                                          :duration_id
                                      )
                                      ")->bindValues(array(
                        ":drug_id" => $taper["drug_id"],
                        ":ref_set_name" => $taper['drug_set_name'],
                        ":subspecialty_id" => $taper['subspecialty_id'],
                        ":dose" => (float)$taper['dose'],
                        ":frequency_id" => $taper["frequency_id"],
                        ":duration_id" => $taper['duration_id']
                    ))->execute();
                }
            }
        }
        catch(Exception $e) {
            echo "Exception: ".$e->getMessage()."\n";
            $transaction->rollback();
            return false;
        }

        $transaction->commit();

        return true;
	}

	public function down()
	{
		$this->execute("DELETE FROM ref_medication_set_taper WHERE 1=1");
	}
}