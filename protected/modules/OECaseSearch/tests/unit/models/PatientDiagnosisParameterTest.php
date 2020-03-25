<?php

use OEModule\OphCiExamination\models\Element_OphCiExamination_Diagnoses;
use OEModule\OphCiExamination\models\OphCiExamination_Diagnosis;

/**
 * Class PatientDiagnosisParameterTest
 * @method Patient patient($fixtureId)
 * @method OphCiExamination_Diagnosis ophciexamination_diagnosis($fixtureId)
 * @method Element_OphCiExamination_Diagnoses et_ophciexamination_diagnoses($fixtureId)
 * @method Disorder disorder($fixtureId)
 * @method Episode episode($fixtureId)
 * @method Event event($fixtureId)
 */
class PatientDiagnosisParameterTest extends CDbTestCase
{
    /**
     * @var PatientDiagnosisParameter $parameter
     */
    protected $parameter;

    /**
     * @var DBProvider $searchProvider
     */
    protected $searchProvider;

    protected $fixtures = array(
        'disorder' => 'Disorder',
        'ophciexamination_diagnosis' => OphCiExamination_Diagnosis::class,
        'et_ophciexamination_diagnoses' => Element_OphCiExamination_Diagnoses::class,
        'event' => 'Event',
        'patient' => 'Patient',
        'episode' => 'Episode',
    );

    public function setUp()
    {
        parent::setUp();
        $this->parameter = new PatientDiagnosisParameter();
        $this->searchProvider = new DBProvider('mysql');
        $this->parameter->id = 0;
    }

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Yii::app()->getModule('OECaseSearch');
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->parameter, $this->searchProvider);
    }

    public function testBindValues()
    {
        $this->parameter->value = 'Diabetes';
        $this->parameter->firm_id = 1;
        $expected = array(
            'p_d_value_0' => $this->parameter->value,
            'p_d_firm_0' => $this->parameter->firm_id,
            'p_d_only_latest_event_0' => $this->parameter->only_latest_event,
        );

        // Ensure that all bind values are returned.
        $this->assertEquals($expected, $this->parameter->bindValues());

        $this->parameter->firm_id = '';

        $expected = array(
            'p_d_value_0' => $this->parameter->value,
            'p_d_firm_0' => null,
            'p_d_only_latest_event_0' => $this->parameter->only_latest_event,
        );

        $this->assertEquals($expected, $this->parameter->bindValues());
    }

    public function attributeValueTestList()
    {
        return array(
            'Operation' => array(
                'attribute' => 'operation',
                'expected' => '=',
            ),
            'Value' => array(
                'attribute' => 'value',
                'expected' => 'Myopia',
            ),
            'Firm' => array(
                'attribute' => 'firm_id',
                'expected' => 'by Aylward Firm',
            ),
            'Only latest event' => array(
                'attribute' => 'only_latest_event',
                'expected' => 'Only patient\'s latest event',
            ),
            'Invalid attribute' => array(
                'attribute' => 'invalid',
                'expected' => null,
            ),
        );
    }

    /**
     * @dataProvider attributeValueTestList
     * @param $attribute
     * @param $expected
     */
    public function testGetValueForAttribute($attribute, $expected)
    {
        $this->parameter->operation = '=';
        $this->parameter->value = 1;
        $this->parameter->firm_id = 1;
        $this->parameter->only_latest_event = true;
        $this->assertEquals($expected, $this->parameter->getValueForAttribute($attribute));
    }

    /**
     * @covers PatientDiagnosisParameter::getCommonItemsForTerm
     */
    public function testGetCommonItemsForTerm()
    {
        // Full match
        $this->assertCount(1, PatientDiagnosisParameter::getCommonItemsForTerm('Myopia'));
        $this->assertEquals('Myopia', PatientDiagnosisParameter::getCommonItemsForTerm('Myopia')[0]['label']);
        $this->assertEquals(1, PatientDiagnosisParameter::getCommonItemsForTerm('Myopia')[0]['id']);

        // Partial match
        $this->assertCount(8, PatientDiagnosisParameter::getCommonItemsForTerm('m'));
    }

    public function testSearchLike()
    {
        $expected = array();
        foreach (array(1, 2, 3, 7) as $patientNum) {
            $expected[] = $this->patient("patient$patientNum");
        }

        $this->parameter->operation = false;
        $this->parameter->value = 1;
        $this->parameter->firm_id = '';

        $results = $this->searchProvider->search(array($this->parameter));

        $ids = array();
        foreach ($results as $result) {
            $ids[] = $result['id'];
        }

        $patients = Patient::model()->findAllByPk($ids);

        $this->assertEquals($expected, $patients);
    }

    public function testFirmEqualitySearch()
    {
        $expected = array();

        $this->parameter->operation = 'IN';
        $this->parameter->value = 1;
        $this->parameter->firm_id = 2;

        $results = $this->searchProvider->search(array($this->parameter));

        $ids = array();
        foreach ($results as $result) {
            $ids[] = $result['id'];
        }

        $patients = Patient::model()->findAllByPk($ids);

        $this->assertEquals($expected, $patients);
    }

    public function testSearchNotLike()
    {
        $expected = array();
        foreach (array(4, 5, 6, 8, 9) as $patientNum) {
            $expected[] = $this->patient("patient$patientNum");
        }

        $this->parameter->operation = 'NOT IN';
        $this->parameter->value = 1;
        $this->parameter->firm_id = '';

        $results = $this->searchProvider->search(array($this->parameter));

        $ids = array();
        foreach ($results as $result) {
            $ids[] = $result['id'];
        }

        $patients = Patient::model()->findAllByPk($ids);

        $this->assertEquals($expected, $patients);
    }

    public function testSearchFirmInequality()
    {
        $expected = array();
        foreach (array(1, 2, 3, 4, 5, 6, 7, 8, 9) as $patientNum) {
            $expected[] = $this->patient("patient$patientNum");
        }

        $this->parameter->operation = 'NOT IN';
        $this->parameter->value = 1;
        $this->parameter->firm_id = 2;

        $results = $this->searchProvider->search(array($this->parameter));

        $ids = array();
        foreach ($results as $result) {
            $ids[] = $result['id'];
        }
        $patients = Patient::model()->findAllByPk($ids);

        $this->assertEquals($expected, $patients);
    }
}
