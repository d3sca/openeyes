<?php

class m180711_092304_create_refset_from_tags extends CDbMigration
{
	public function up()
	{
        $this->addColumn('ophciexamination_risk_tag', 'ref_set_id', 'int(10) AFTER tag_id');
        $this->createIndex('idx_ref_set_id', 'ophciexamination_risk_tag', 'ref_set_id');
        $this->addForeignKey('fk_ref_set_id', 'ophciexamination_risk_tag', 'ref_set_id', 'ref_set', 'id');
        
        $tags = Yii::app()->db
                ->createCommand('SELECT id, name FROM tag ORDER BY name ASC')
                ->queryAll();
        
        if($tags){
            foreach($tags as $tag){
                $command = Yii::app()->db;
                $command->createCommand("INSERT INTO ref_set(name) values ('".$tag['name']."')")->execute();
                $ref_set_id = $command->getLastInsertID(); 
                Yii::app()->db->createCommand("INSERT INTO ref_set_rules(ref_set_id, usage_code) values (".$ref_set_id.", 'DrugTag' )")->execute();
                
                /*
                 * Update ophciexamination_risk_tag
                 */
                Yii::app()->db->createCommand("UPDATE ophciexamination_risk_tag SET ref_set_id = ".$ref_set_id." WHERE tag_id = ".$tag['id'])->execute();
                
                $drugTags = Yii::app()->db
                    ->createCommand('SELECT drug_id FROM drug_tag WHERE tag_id = '.$tag['id'])
                    ->queryAll();
              
                if($drugTags){
                    foreach($drugTags as $drugTag){
                        $ref_medication_id = Yii::app()->db
                        ->createCommand("SELECT id FROM ref_medication WHERE preferred_code = '".$drugTag['drug_id']."_drug' ")
                        ->queryRow();

                        if($ref_medication_id['id']){
                            Yii::app()->db->createCommand("INSERT INTO ref_medication_set(ref_medication_id, ref_set_id) values (".$ref_medication_id['id'].", ".$ref_set_id." )")->execute();
                        }
                    }
                    
                    $ref_medication_id = null;
                }
                
                $medicationDrugTags = Yii::app()->db
                    ->createCommand('SELECT medication_drug_id FROM medication_drug_tag WHERE tag_id = '.$tag['id'])
                    ->queryAll();

                if($medicationDrugTags){
                    foreach($medicationDrugTags as $medicationDrugTag){
                        $ref_medication_id = Yii::app()->db
                        ->createCommand("SELECT id FROM ref_medication WHERE preferred_code = '".$medicationDrugTag['medication_drug_id']."_medication_drug' ")
                        ->queryRow();
                       
                        if($ref_medication_id['id']){
                            Yii::app()->db->createCommand("INSERT INTO ref_medication_set(ref_medication_id, ref_set_id) values (".$ref_medication_id['id'].", ".$ref_set_id." )")->execute();
                        }
                    }
                }  
                
                
            }
            $ref_set_id = null;
        }

	}

	public function down()
	{
        return true;
	}
}