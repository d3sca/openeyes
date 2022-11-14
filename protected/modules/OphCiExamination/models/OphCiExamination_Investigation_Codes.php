<?php

namespace OEModule\OphCiExamination\models;

/**
 * This is the model class for table "et_ophciexamination_investigation_codes".
 *
 * The followings are the available columns in table 'et_ophciexamination_investigation_codes':
 * @property integer $id
 * @property string $name
 * @property string $snomed_code
 * @property string $snomed_term
 * @property string $ecds_code
 * @property string $specialty_id
 * @property string $last_modified_user_id
 * @property string $last_modified_date
 * @property string $created_user_id
 * @property string $created_date
 *
 * The followings are the available model relations:
 * @property \User $createdUser
 * @property \User $lastModifiedUser
 * @property \Specialty $specialty
 * @property OphCiExamination_Investigation_Entry[] $etOphciexaminationInvestigationEntries
 */
class OphCiExamination_Investigation_Codes extends \BaseActiveRecordVersioned
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'et_ophciexamination_investigation_codes';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('name, snomed_term', 'length', 'max' => 256),
            array('snomed_code, ecds_code', 'length', 'max' => 20),
            array('specialty_id, last_modified_user_id, created_user_id', 'length', 'max' => 10),
            array('last_modified_date, created_date', 'safe'),
            // The following rule is used by search().
            array('id, name, snomed_code, snomed_term, ecds_code, specialty_id', 'safe', 'on' => 'search'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
            'createdUser' => array(self::BELONGS_TO, 'User', 'created_user_id'),
            'lastModifiedUser' => array(self::BELONGS_TO, 'User', 'last_modified_user_id'),
            'specialty' => array(self::BELONGS_TO, 'Specialty', 'specialty_id'),
            'etOphciexaminationInvestigationEntries' => array(self::HAS_MANY, 'OEModule\OphCiExamination\models\OphCiExamination_Investigation_Entry', 'investigation_code'),
            'investigationComments' => [self::HAS_MANY, InvestigationComments::class, 'investigation_code']
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'name' => 'Name',
            'snomed_code' => 'Snomed Code',
            'snomed_term' => 'Snomed Term',
            'ecds_code' => 'Ecds Code',
            'specialty_id' => 'Specialty',
            'last_modified_user_id' => 'Last Modified User',
            'last_modified_date' => 'Last Modified Date',
            'created_user_id' => 'Created User',
            'created_date' => 'Created Date',
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function search()
    {
        // @todo Please modify the following code to remove attributes that should not be searched.

        $criteria = new CDbCriteria();

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('snomed_code', $this->snomed_code, true);
        $criteria->compare('snomed_term', $this->snomed_term, true);
        $criteria->compare('ecds_code', $this->ecds_code, true);
        $criteria->compare('specialty_id', $this->specialty_id, true);
        $criteria->compare('last_modified_user_id', $this->last_modified_user_id, true);
        $criteria->compare('last_modified_date', $this->last_modified_date, true);
        $criteria->compare('created_user_id', $this->created_user_id, true);
        $criteria->compare('created_date', $this->created_date, true);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return OphCiExamination_Investigation_Codes the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function __toString()
    {
        return $this->name;
    }
}
