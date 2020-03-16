<?php

/**
 * @property integer $trialTypeId
 * @property TrialType $trialType
 *
 * @property integer $treatmentTypeId
 * @property TreatmentType $treatmentType
 *
 * @inherit
 */
class PreviousTrialParameter extends CaseSearchParameter implements DBProviderInterface
{
    public $trial;
    public $trialTypeId;
    public $status;
    public $treatmentTypeId;

    private $statusList = array();

    protected $options = array(
        'value_type' => 'multi_select',
    );

    /**
     * @return TrialType
     */
    public function getTrialType()
    {
        return TrialType::model()->findByPk($this->trialTypeId);
    }

    public function getTreatmentType()
    {
        return TreatmentType::model()->findByPk($this->treatmentTypeId);
    }

    /**
     * CaseSearchParameter constructor. This overrides the parent constructor so that the name can be immediately set.
     * @param string $scenario
     */
    public function __construct($scenario = '')
    {
        parent::__construct($scenario);
        $this->name = 'previous_trial';
        $this->status = TrialPatientStatus::model()->find('code = "ACCEPTED"')->id;

        $trialTypes = TrialType::getOptions();
        $treatmentTypes = TreatmentType::getOptions();

        $trials = Trial::getTrialList(isset($this->trialType) ? $this->trialType->id : '');

        $this->statusList = array(
            TrialPatientStatus::model()->find('code = "SHORTLISTED"')->id => 'Shortlisted in',
            TrialPatientStatus::model()->find('code = "ACCEPTED"')->id => 'Accepted in',
            TrialPatientStatus::model()->find('code = "REJECTED"')->id => 'Rejected from',
        );

        $this->options['option_data'] = array(
            array(
                'id' => 'trial-status',
                'field' => 'status',
                'options' => array_map(
                    static function ($item, $key) {
                        return array('id' => $key, 'label' => $item);
                    },
                    $this->statusList,
                    array_keys($this->statusList)
                )
            ),
            array(
                'id' => 'trial-type',
                'field' => 'trialTypeId',
                'options' => array_map(
                    static function ($item, $key) {
                        return array('id' => $key, 'label' => $item);
                    },
                    $trialTypes,
                    array_keys($trialTypes)
                )
            ),
            array(
                'id' => 'trial',
                'field' => 'trial',
                'options' => array_map(
                    static function ($item, $key) {
                        return array('id' => $key, 'label' => $item);
                    },
                    $trials,
                    array_keys($trials)
                )
            ),
            array(
                'id' => 'treatment-type',
                'field' => 'treatmentTypeId',
                'options' => array_map(
                    static function ($item, $key) {
                        return array('id' => $key, 'label' => $item);
                    },
                    $treatmentTypes,
                    array_keys($treatmentTypes)
                )
            ),
        );
    }

    public function getLabel()
    {
        // This is a human-readable value, so feel free to change this as required.
        return 'Previous Trial';
    }

    /**
     * Override this function for any new attributes added to the subclass. Ensure that you invoke the parent function first to obtain and augment the initial list of attribute names.
     * @return array An array of attribute names.
     */
    public function attributeNames()
    {
        return array_merge(
            parent::attributeNames(),
            array(
                'status',
                'trialTypeId',
                'trial',
                'treatmentTypeId',
            )
        );
    }

    /**
     * Override this function if the parameter subclass has extra validation rules. If doing so, ensure you invoke the parent function first to obtain the initial list of rules.
     * @return array The validation rules for the parameter.
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            array(
                array('trialType, trialTypeId,  trial, status, treatmentTypeId', 'safe'),
            )
        );
    }

    public function getValueForAttribute($attribute)
    {
        if (in_array($attribute, $this->attributeNames(), true)) {
            switch ($attribute) {
                case 'trial':
                    return Trial::model()->findByPk($this->$attribute)->name;
                    break;
                case 'trialTypeId':
                    return $this->getTrialType()->name;
                    break;
                case 'treatmentTypeId':
                    return $this->getTreatmentType()->name;
                    break;
                case 'status':
                    return $this->statusList[$this->$attribute];
                    break;
                default:
                    return parent::getValueForAttribute($attribute);
            }
        }
        return null;
    }

    /**
     * Generate a SQL fragment representing the subquery of a FROM condition.
     * @param $searchProvider SearchProvider The search provider. This is used to determine whether or not the search provider is using SQL syntax.
     * @return mixed The constructed query string.
     * @throws CHttpException
     */
    public function query($searchProvider)
    {
        $condition = ' ';
        $joinCondition = 'JOIN';
        if ($this->trialType) {
            if ($this->trial === '') {
                // Any intervention/non-intervention trial
                $condition = "t.trial_type_id = :p_t_trial_type_$this->id";
            } else {
                // specific trial
                $condition = "t_p.trial_id = :p_t_trial_$this->id";
            }
        } else {
            // Any trial
            $condition = 't_p.trial_id IS NOT NULL';
        }

        if ($this->status !== '' && $this->status !== null) {
            //in a trial with a specific status
            $condition .= " AND t_p.status_id = :p_t_status_$this->id";
        } else {
            // in any trial
            $condition .= ' AND t_p.status_id IN (
                      SELECT id FROM trial_patient_status WHERE code IN ("ACCEPTED", "SHORTLISTED", "REJECTED"))';
        }

        if ((!$this->trialType || $this->trialType->code !== TrialType::NON_INTERVENTION_CODE)
            && $this->treatmentTypeId !== '' && $this->treatmentTypeId !== null
        ) {
            $condition .= " AND t_p.treatment_type_id = :p_t_treatment_type_id_$this->id";
        }
        switch ($this->operation) {
            case 'IS':
                $query = "SELECT p.id 
                        FROM patient p 
                        $joinCondition trial_patient t_p 
                          ON t_p.patient_id = p.id 
                        $joinCondition trial t
                          ON t.id = t_p.trial_id
                        WHERE $condition";

                break;
            case 'IS NOT':
                $query = "SELECT p.id from patient p WHERE p.id NOT IN (SELECT p.id 
                            FROM patient p 
                            $joinCondition trial_patient t_p 
                              ON t_p.patient_id = p.id 
                            $joinCondition trial t
                              ON t.id = t_p.trial_id
                            WHERE $condition)";
                break;
            default:
                throw new CHttpException(400, 'Invalid operator specified.');
                break;
        }

        return $query;
    }

    /**
     * Get the list of bind values for use in the SQL query.
     * @return array An array of bind values. The keys correspond to the named binds in the query string.
     */
    public function bindValues()
    {
        // Construct your list of bind values here. Use the format "bind" => "value".
        $binds = array();
        if ($this->trialType) {
            if ($this->trial === '') {
                $binds[":p_t_trial_type_$this->id"] = $this->trialTypeId;
            } else {
                $binds[":p_t_trial_$this->id"] = $this->trial;
            }
        }

        if ($this->status !== '' && $this->status !== null) {
            $binds[":p_t_status_$this->id"] = $this->status;
        }
        if ((!$this->trialType || $this->trialType->code !== TrialType::NON_INTERVENTION_CODE)
            && $this->treatmentTypeId !== '' && $this->treatmentTypeId !== null
        ) {
            $binds[":p_t_treatment_type_id_$this->id"] = $this->treatmentTypeId;
        }

        return $binds;
    }

    /**
     * @inherit
     */
    public function getAuditData()
    {
        $trialTypes = TrialType::getOptions();

        $statusList = array(
            TrialPatientStatus::model()->find('code = "SHORTLISTED"')->id => 'Shortlisted in',
            TrialPatientStatus::model()->find('code = "ACCEPTED"')->id => 'Accepted in',
            TrialPatientStatus::model()->find('code = "REJECTED"')->id => 'Rejected from',
        );
        $trials = Trial::getTrialList(isset($this->trialType) ? $this->trialType->id : '');
        $treatmentTypeList = TreatmentType::getOptions();

        $status = $this->status === null || $this->status === '' ? 'Included in' : $statusList[$this->status];
        $type = !$this->trialType ? 'Any Trial Type with' : $trialTypes[$this->trialTypeId];
        $trial = $this->trial === null || $this->trial === '' ? 'Any trial with' : $trials[$this->trial] . ' with ';
        $treatment = $this->treatmentTypeId === null || $this->treatmentTypeId === '' ? 'Any Treatment' : $treatmentTypeList[$this->treatmentTypeId];

        return "$this->name: $this->operation $status $type $trial $treatment";
    }

    public function saveSearch()
    {
        return array_merge(
            parent::saveSearch(),
            array(
                'trial' => $this->trial,
                'trialTypeId' => $this->trialTypeId,
                'status' => $this->status,
                'treatmentTypeId' => $this->treatmentTypeId,
            )
        );
    }

    public function getDisplayString()
    {
        $op = 'IS';
        if ($this->operation) {
            $op = 'IS NOT';
        }

        $status = TrialPatientStatus::model()->findbyPk($this->status)->name;

        $trialType = $this->trialType ? $this->trialType->name : 'any trial type';

        $trial = Trial::model()->findByPk($this->trial);
        $trialStr = $trial ? $trial->name : 'for any trial';
        $treatment_type = $this->getTreatmentType();
        $treatment_str = $treatment_type ? $treatment_type->name : 'any';

        return "$op $status $trialType $trialStr with $treatment_str treatment";
    }
}
