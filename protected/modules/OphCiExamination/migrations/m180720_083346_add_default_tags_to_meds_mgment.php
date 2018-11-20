<?php

class m180720_083346_add_default_tags_to_meds_mgment extends CDbMigration
{
	public function up()
	{
        $transaction=$this->getDbConnection()->beginTransaction();
        try {
            $this->execute("INSERT INTO ref_set (`name`) VALUES ('Antibiotic')");
            $ref_set_id1 = Yii::app()->db->getLastInsertID();
            $this->execute("INSERT INTO ref_set (`name`) VALUES ('Ophthalmic')");
            $ref_set_id2 = Yii::app()->db->getLastInsertID();
            $this->execute("INSERT INTO ref_medication_set (ref_medication_id, ref_set_id, default_form, default_dose, default_route, default_frequency, default_dose_unit_term)
                            SELECT ref_medication_id, 
                            $ref_set_id2, 
                            default_form, default_dose, default_route, default_frequency, default_dose_unit_term
                            FROM ref_medication_set WHERE default_route IN (SELECT id FROM ref_medication_route WHERE term IN ('Eye', 'Ocular', 'Interocular'));
                            ");

            $this->execute("INSERT INTO ref_set_rules (ref_set_id, usage_code) 
                            SELECT id, 'Management' FROM ref_set WHERE `name` IN ('Cytotoxic', 'Antiviral', 'Tear Film Substitute', 'Glaucoma', 'Antibiotic', 'Ophthalmic')");

            $transaction->commit();
        }
        catch(Exception $e) {
            echo "Exception: ".$e->getMessage()."\n";
            $transaction->rollback();
            return false;
        }

        return true;
	}

	public function down()
	{
        $this->execute("DELETE FROM ref_set WHERE `name` IN ('Antibiotic', 'Ophtalmic')");
        $this->execute("DELETE FROM ref_set_rules WHERE usage_code = 'Management';");

	}

	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
	}

	public function safeDown()
	{
	}
	*/
}