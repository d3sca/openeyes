<?php

/**
 * Class PatientNameParameterTest
 * @method Patient patient($fixtureId)
 * @method Contact contact($fixtureId)
 */
class PatientNameParameterTest extends CDbTestCase
{
    protected $parameter;
    protected $searchProvider;
    protected $fixtures = array(
        'patient' => 'Patient',
        'contact' => 'Contact',
    );

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Yii::app()->getModule('OECaseSearch');
    }

    public function setUp()
    {
        parent::setUp();
        $this->parameter = new PatientNameParameter();
        $this->searchProvider = new DBProvider('mysql');
        $this->parameter->id = 0;
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->parameter, $this->searchProvider);
    }

    public function testSearch()
    {
        $expected = array($this->patient('patient1'));

        $this->parameter->operation = '=';
        $this->parameter->value = 1;

        $secondParam = new PatientNameParameter();
        $secondParam->operation = '=';
        $secondParam->value = $this->patient('patient1')->id;

        $results = $this->searchProvider->search(array($this->parameter, $secondParam));

        $ids = array();

        foreach ($results as $result) {
            $ids[] = $result['id'];
        }
        $actual = Patient::model()->findAllByPk($ids);

        $this->assertEquals($expected, $actual);

        $this->parameter->value = $this->patient('patient1')->id;

        $results = $this->searchProvider->search(array($this->parameter));

        $ids = array();

        foreach ($results as $result) {
            $ids[] = $result['id'];
        }
        $actual = Patient::model()->findAllByPk($ids);
        $this->assertEquals($expected, $actual);
    }
}
