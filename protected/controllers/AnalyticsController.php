<?php

use OEModule\OphCiExamination\models\OphCiExamination_VisualAcuityUnit as VisualAcuityUnit;
use OEModule\OphCiExamination\widgets\OphCiExamination_Episode_VisualAcuityHistory;

class AnalyticsController extends BaseController
{
    const DAYTIME_ONE = 86400;
    const DAYTIME_THREE = 259200;
    const WEEKTIME = 604800;
    const MONTHTIME_THIRTY = 2592000;
    const PERIOD_DAY = 1;
    const PERIOD_WEEK = 7;
    const PERIOD_MONTH = 30;
    const PERIOD_YEAR = 365;
    const FOLLOWUP_WEEK_LIMITED = 78;
    private $current_user ;

    public $layout = '//layouts/events_and_episodes';
    protected $patient_list = array();
    protected $filters;
    protected $surgeon;
    public $fixedHotlist = false;
    protected $custom_csv_data = array();

    /**
     * @param $subspecialty_name
     * @return int
     * Get subspecialty ID by name, used in each actionXXX function to filter data by subspecialty.
     */
    protected function getSubspecialtyID($subspecialty_name)
    {
        return Subspecialty::model()->findByAttributes(array('name'=>$subspecialty_name))->id;
    }

    public function accessRules()
    {
        return array(
            array('allow',
                'actions' => array('analyticsReports','cataract', 'medicalRetina', 'glaucoma', 'updateData','allSubspecialties', 'GetDrillDown', 'DownLoadCSV', 'getCustomPlot', 'DownloadCustomCSV'),
                'users'=> array('@')
            ),
        );
    }
    public function actionDownloadCSV()
    {
        $ret = null;
        $params = Yii::app()->request->getParam('params');
        $ret = $this->getPatientList($params);
        echo (json_encode($ret));
        Yii::app()->end();
    }

    /**
     * @return array
     * separated from actionDownloadCSV to avoid too many nested if statements
    */
    public function actionDownloadCustomCSV()
    {
        $ret = null;
        $this->checkAuth();
        $this->obtainFilters();
        $ti = $this->filters['time_interval'];

        if ($this->filters['specialty'] === 'Glaucoma') {
            $plot_type = 'IOP';
            $other_reading_cmd = $this->queryIOPReading(false)->text;
        } else {
            $plot_type = 'CRT';
            $other_reading_cmd = $this->queryCRTReading(false)->text;
        }

        $other_cmd = $this->queryCustomData($this->filters['specialty'], null, $plot_type, $ti, false)->text;

        $statistical_report = $this->statisticalCSV($other_cmd, $ti);

        $patient_report = $this->patientCSV($other_reading_cmd);

        ksort($statistical_report, SORT_NATURAL);
        $ret = array(
            'statistical_report' => $statistical_report,
            'patient_report' => $patient_report
        );
        $this->renderJSON($ret);
        Yii::app()->end();
    }
    /**
     * @param $other_cmd: IOP / CRT reading with procedure as a baseline
     * @param $ti: $time_interval
     * @return array
     * the logic below is
        (VA with procedure LEFT JOIN IOP / CRT with procedure)

        UNION

        (VA with procedure RIGHT JOIN IOP / CRT with procedure)
    */
    private function statisticalCSV($other_cmd, $ti)
    {

        $query_conditions = array('and');

        $va_cmd = $this->queryCustomData($this->filters['specialty'], null, 'VA', $ti, false);

        $va_left_join_other = Yii::app()->db->createCommand()
        ->select("
            va.patient_id,
            IF(va.time_interval < 0, 'pre', va.time_interval) va_time_interval,
            va.reading va_reading,
            IF(other.time_interval < 0, 'pre', other.time_interval) other_time_interval,
            other.reading other_reading
        ")
        ->from("($va_cmd->text) va")
        ->leftJoin("($other_cmd) other", "va.patient_id = other.patient_id AND IF(va.time_interval < 0, 'pre', va.time_interval) = IF(other.time_interval < 0, 'pre', other.time_interval)");

        $va_right_join_other = Yii::app()->db->createCommand()
        ->select("
            IF(va.patient_id IS NULL, other.patient_id, va.patient_id) patient_id,
            IF(va.time_interval < 0, 'pre', va.time_interval) va_time_interval,
            va.reading va_reading,
            IF(other.time_interval < 0, 'pre', other.time_interval) other_time_interval,
            other.reading other_reading
        ")
        ->from("($va_cmd->text) va")
        ->rightJoin("($other_cmd) other", "va.patient_id = other.patient_id AND IF(va.time_interval < 0, 'pre', va.time_interval) = IF(other.time_interval < 0, 'pre', other.time_interval)");
        $elements = $va_left_join_other->union("$va_right_join_other->text")->queryAll();
        $time_interval_num = $this->filters['time_interval']['num'];
        $time_interval_unit = $this->filters['time_interval']['unit'];
        $statistical_report = array();

        $test = array();

        foreach ($elements as $element) {
            $time_interval = isset($element['va_time_interval']) ? $element['va_time_interval'] : $element['other_time_interval'];
            if ($time_interval === 'pre') {
                $selected_interval = "($time_interval_unit) PRE-OP";
                $va_reading = $element['va_reading'];
                $other_reading = $element['other_reading'];
                if (array_key_exists($selected_interval, $statistical_report)) {
                    if (isset($va_reading)) {
                        $statistical_report[$selected_interval]['va'] = $this->processCustomData($statistical_report[$selected_interval]['va'], $element['patient_id'], $va_reading);
                    }
                    if (isset($other_reading)) {
                        $statistical_report[$selected_interval]['other'] = $this->processCustomData($statistical_report[$selected_interval]['other'], $element['patient_id'], $other_reading);
                    }
                } else {
                    $statistical_report[$selected_interval]['va'] = $this->processCustomData(null, $element['patient_id'], $va_reading, true);
                    $statistical_report[$selected_interval]['other'] = $this->processCustomData(null, $element['patient_id'], $other_reading, true);
                }
            }
        }
        if (isset($statistical_report["($time_interval_unit) PRE-OP"])) {
            foreach ($elements as $element) {
                $time_interval = isset($element['va_time_interval']) ? $element['va_time_interval'] : $element['other_time_interval'];
                if ($time_interval !== 'pre') {
                    if (!in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['va']['patients']) && !in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['other']['patients'])) {
                        continue;
                    }

                    $selected_interval = "$time_interval_unit " . $time_interval * $time_interval_num;
                    $va_reading = $element['va_reading'];
                    $other_reading = $element['other_reading'];


                    if (array_key_exists($selected_interval, $statistical_report)) {
                        if (in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['va']['patients'])) {
                            if (isset($va_reading)) {
                                $statistical_report[$selected_interval]['va'] = $this->processCustomData($statistical_report[$selected_interval]['va'], $element['patient_id'], $va_reading);
                            }
                        }
                        if (in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['other']['patients'])) {
                            if (isset($other_reading)) {
                                $statistical_report[$selected_interval]['other'] = $this->processCustomData($statistical_report[$selected_interval]['other'], $element['patient_id'], $other_reading);
                            }
                        }
                    } else {
                        if (in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['va']['patients'])) {
                            $statistical_report[$selected_interval]['va'] = $this->processCustomData(null, $element['patient_id'], $va_reading, true);
                        } else {
                            $statistical_report[$selected_interval]['va'] = $this->processCustomData(null, null, null, true);
                        }

                        if (in_array($element['patient_id'], $statistical_report["($time_interval_unit) PRE-OP"]['other']['patients'])) {
                            $statistical_report[$selected_interval]['other'] = $this->processCustomData(null, $element['patient_id'], $other_reading, true);
                        } else {
                            $statistical_report[$selected_interval]['other'] = $this->processCustomData(null, null, null, true);
                        }
                    }
                }
            }
        }

        ksort($statistical_report, SORT_NATURAL);
        return $statistical_report;
    }

    /**
     * @param $other_reading_cmd: IOP / CRT reading
     * @return array
     * the logic below is
        (VA reading LEFT JOIN IOP / CRT reading)

        UNION

        (VA reading RIGHT JOIN IOP / CRT reading)
    */
    private function patientCSV($other_reading_cmd)
    {
        $patient_report = array();
        $patient_list = $this->queryCustomDiagnoses()->text;
        $va_reading_cmd = $this->queryBestVAReading(false)->text;

        $query_conditions = array('and');

        if (isset($this->filters['custom_diagnosis'])) {
            $diagnoses = implode(",", $this->filters['custom_diagnosis']);
            $query_conditions[] = "diag.disorder_id IN ( $diagnoses )";
            $query_conditions[] = 'diag.active = 1';
        }

        if (isset($this->filters['date_from'])) {
            $date_from = $this->filters['date_from'];
            $query_conditions[] = "UNIX_TIMESTAMP(reading.event_date) >= $date_from";
        }
        if (isset($this->filters['date_to'])) {
            $date_to = $this->filters['date_to'];
            $query_conditions[] = "UNIX_TIMESTAMP(reading.event_date) <= $date_to";
        }

        $patient_va = Yii::app()->db->createCommand()
        ->select("
            diag.full_name,
            diag.patient_id,
            diag.age,
            reading.event_date,
            reading.value,
            IF(reading.eye_id = 0, 'R', 'L') side
        ")
        ->from("($patient_list) diag")
        ->join("($va_reading_cmd) reading", 'diag.episode_id = reading.episode_id')
        ->where($query_conditions)
        ->text;
        $patient_other = Yii::app()->db->createCommand()
        ->select("
            diag.full_name,
            diag.patient_id,
            diag.age,
            reading.event_date,
            reading.value,
            IF(reading.eye_id = 0, 'R', 'L') eye_id
        ")
        ->from("($patient_list) diag")
        ->join("($other_reading_cmd) reading", 'diag.episode_id = reading.episode_id')
        ->where($query_conditions)
        ->text;

        $patient_va_left_join_other = Yii::app()->db->createCommand()
        ->select("
            va.full_name,
            va.patient_id,
            va.age,
            va.event_date va_date,
            va.side va_side,
            va.value va_reading,
            IF(other.value IS NULL, 'N/A', other.value) other_reading
        ")
        ->from("($patient_va) va")
        ->leftJoin("($patient_other) other", 'va.patient_id = other.patient_id AND va.event_date = other.event_date AND va.side = other.eye_id');

        $patient_va_right_join_other = Yii::app()->db->createCommand()
        ->select("
            IF(va.full_name IS NULL, other.full_name, va.full_name) full_name,
            IF(va.patient_id IS NULL, other.patient_id, va.patient_id) patient_id,
            IF(va.age IS NULL, other.age, va.age) age,
            IF(va.event_date IS NULL, other.event_date, va.event_date) va_date,
            IF(va.side IS NULL, other.eye_id, va.side) va_side,
            IF(va.value IS NULL, 'N/A', va.value) va_reading,
            other.value other_reading
        ")
        ->from("($patient_va) va")
        ->rightJoin("($patient_other) other", 'va.patient_id = other.patient_id AND va.event_date = other.event_date AND va.side = other.eye_id');
        $patient_report = $patient_va_left_join_other->union("$patient_va_right_join_other->text")->order('patient_id, va_date, va_side')->queryAll();
        return $patient_report;
    }
    public function actionGetDrillDown($specialty = null)
    {
        $ret = null;
        if (Yii::app()->request->getParam('drill')) {
            $specialty = Yii::app()->request->getParam('specialty');
            $params = Yii::app()->request->getParam('params');
            if (isset($params['ids'])) {
                if ($specialty === 'Cataract') {
                    $event_list = $this->queryCataractEventList($params);
                    $ret['event_list'] = $event_list;
                } else {
                    $patient_list = $this->getPatientList($params);
                }
            } elseif (isset($params['diagnosis'])) {
                $patient_list = $this->getPatientList($params);
            }
            if (isset($patient_list)) {
                $ret['patient_list'] = $patient_list;
            }
            $data = isset($event_list) ? count($event_list) : count($patient_list);
            if ($data > 0) {
                $dom = $this->renderPartial('/analytics/analytics_drill_down_list', array(
                    'data' => $ret,
                ), true);
                echo (json_encode(
                    array(
                        'dom' => $dom,
                        'count' => $data
                    )
                ));
                Yii::app()->end();
            } else {
                echo (json_encode('reachedMax'));
                Yii::app()->end();
            }
        }
    }

    public function actionAnalyticsReports()
    {
        $this->render('/analytics/analytics_report', null);
    }

    private function reportDataDOM()
    {
        $this->checkAuth();
        $this->obtainFilters();
        $specialty = Yii::app()->getRequest()->getParam("specialty");
        $subspecialty_id = $specialty === 'All' ? null : $this->getSubspecialtyID($specialty);
        // different user and different subspecialty
        // should have different result
        $follow_patient_list = $this->getFollowUps($subspecialty_id, $this->filters['date_from'], $this->filters['date_to'], $this->filters['service_diagnosis'], $this->surgeon);
        if (Yii::app()->request->getParam('report')) {
            $this->renderJSON(array(
                'plot_data'=>$follow_patient_list['plot_data'],
                'csv_data'=>$follow_patient_list['csv_data'],
                'data_sum'=>$follow_patient_list['data_sum'],
            ));
            return;
        }
        $disorder_data = $this->getDisorders($subspecialty_id, $this->surgeon);
        if (!isset($this->current_user)) {
            $this->current_user = User::model()->findByPk(Yii::app()->user->id);
        }
        $clinical_data = array(
            'title' => 'Disorders Section',
            'x' => $disorder_data['x'],
            'y' => $disorder_data['y'],
            'text' => $disorder_data['text'],
            'customdata' =>$disorder_data['customdata'],
        );
        $this->filters = array(
            'date_from' => 0,
            'date_to' => strtotime(date("Y-m-d H:i:s")),
        );
        $common_ophthalmic_disorders = $this->getCommonDisorders($subspecialty_id, true);
        $side_bar_user_list = null;
        if (isset($this->surgeon)) {
            $user_list = null;
        } else {
            $user_list = User::model()->findAll();
            foreach ($user_list as $user) {
                $side_bar_user_list[$user->getFullName()] = $user->id;
            }
        }

        $data = array(
            'dom'=>array(),
            'data'=>array(),
        );
        $data['data'] = array(
            'service_data'=> $follow_patient_list,
            'clinical_data' => $clinical_data,
            'current_user'=>$this->current_user->id,
            'user_list'=>$side_bar_user_list,
        );
        $data['dom']['sidebar'] = $this->renderPartial('/analytics/analytics_sidebar', array(
            'specialty'=>$specialty,
            'current_user'=>$this->current_user,
            'common_disorders'=>$common_ophthalmic_disorders,
            'user_list'=>$user_list,
        ), true);
        $data['dom']['plot'] = $this->renderPartial('/analytics/analytics_plots', null, true);
        if ($specialty !== 'All') {
            $data['dom']['plot'] .= $this->renderPartial('/analytics/analytics_custom', null, true);
        }
        echo (json_encode($data));
        Yii::app()->end();
    }

    /**
     * Function actionCataract(), actionMedicalRetina(), actionGlaucoma() are the main function for those three subspecialties
     * The function grab data for all the plots.
     */
    public function actionAllSubspecialties()
    {
        $this->reportDataDOM();
    }

    public function actionCataract()
    {
        $specialty = Yii::app()->getRequest()->getParam("specialty");
        $assetManager = Yii::app()->getAssetManager();
        $assetManager->registerScriptFile('js/dashboard/OpenEyes.Dash.js', null, null, AssetManager::OUTPUT_ALL, false);
        if (!isset($this->current_user)) {
            $this->current_user = User::model()->findByPk(Yii::app()->user->id);
        }
        $current_user = array(
            'id'=>$this->current_user->id,
            'name'=>$this->current_user->first_name . ' ' . $this->current_user->last_name
        );
        $event_dates = $this->getEventDate();
        if (isset($this->surgeon)) {
            $user_list = null;
        } else {
            $user_list = User::model()->findAll();
        }
        $data = array(
            'dom'=>array(),
            'data'=>array(
              'current_user'=>$current_user,
              'event_date'=>$event_dates,
            ),
        );
        $data['dom']['sidebar'] = $this->renderPartial('/analytics/analytics_sidebar_cataract', array(
            'specialty'=>$specialty,
            'current_user'=>$this->current_user,
            'user_list'=>$user_list,
        ), true);
        $data['dom']['plot'] = $this->renderPartial('/analytics/analytics_cataract', null, true);
        echo (json_encode($data));
        Yii::app()->end();
    }
    public function actionMedicalRetina()
    {
        $this->reportDataDOM();
    }
    public function actionGlaucoma()
    {
        $this->reportDataDOM();
    }
    public function actionGetCustomPlot()
    {
        $va_unit = VisualAcuityUnit::model()->getVAUnit(4);
        $va_init_ticks = VisualAcuityUnit::model()->getInitVaTicks($va_unit);
        $va_final_ticks = VisualAcuityUnit::model()->sliceVATicks($va_init_ticks, 20);
        $specialty = Yii::app()->getRequest()->getParam("specialty");
        $custom_data = $this->getCustomData($specialty);
        $data = array(
            'custom_data'=>$custom_data,
            'va_final_ticks'=>$va_final_ticks,
        );
        echo (json_encode($data));
        Yii::app()->end();
    }
    private function getCustomData($sn)
    {
        $this->checkAuth();
        $this->obtainFilters();
        $custom_data = array();
        $va_list_name = '_va_list';

        $second_list_name = $sn === 'Glaucoma' ? '_iop_list' : '_crt_list';
        $plot_type = $sn === 'Glaucoma' ? 'IOP' : 'CRT';

        list(${'left' . $va_list_name}, ${'right' . $va_list_name}) = $this->getCustomDataListQueryNew($sn, 'VA');

        list(${'left' . $second_list_name}, ${'right' . $second_list_name}) = $this->getCustomDataListQueryNew($sn, $plot_type);

        foreach (['left', 'right'] as $side) {
            $custom_data[] = array(
                array(
                    'name' => 'VA',
                    'x' => array_keys(${$side . $va_list_name}),
                    'y' => array_map(
                        function ($item) {
                            return $item['average'];
                        },
                        array_values(${$side . $va_list_name})
                    ),
                    'customdata' => array_map(
                        function ($item) {
                            return $item['patients'];
                        },
                        array_values(${$side . $va_list_name})
                    ),
                    'error_y' => array(
                        'type' => 'data',
                        'array' => array_map(
                            function ($item) {
                                return $item['SD'];
                            },
                            array_values(${$side . $va_list_name})
                        ),
                        'visible' => true,
                        'color' => '#aaa',
                        'thickness' => 1
                    ),
                    'hoverinfo' => 'text',
                    'hovertext' => array_map(
                        function ($item) {
                            return " Mean: " . $item['average'] . "<br> SD: " . $item['SD'] ."<br> N: ".count($item['patients']);
                        },
                        array_values(${$side . $va_list_name})
                    ),
                ),
                array(
                    'name' => $sn === 'Glaucoma' ? 'IOP' : 'CRT',
                    'yaxis' => 'y2',
                    'x' => array_keys(${$side . $second_list_name}),
                    'y' => array_map(
                        function ($item) {
                            return $item['average'];
                        },
                        array_values(${$side . $second_list_name})
                    ),
                    'customdata' => array_map(
                        function ($item) {
                            return $item['patients'];
                        },
                        array_values(${$side . $second_list_name})
                    ),
                    'error_y' => array(
                        'type' => 'data',
                        'array' => array_map(
                            function ($item) {
                                return $item['SD'];
                            },
                            array_values(${$side . $second_list_name})
                        ),
                        'visible' => true,
                        'color' => '#aaa',
                        'thickness' => 1
                    ),
                    'hoverinfo' => 'text',
                    'hovertext' => array_map(
                        function ($item) {
                            return " Mean: " . $item['average'] . "<br> SD: " . $item['SD'] ."<br> N: ".count($item['patients']);
                        },
                        array_values(${$side . $second_list_name})
                    ),
                )
            );
        }
        return $custom_data;
    }
    private function getPatientList($params = null)
    {
        // Future implementation:
        // - use pagination instead of id for service page
        $this->checkAuth();
        $params['from'] = empty($params['from']) ? null : $params['from'];
        $params['to'] = empty($params['to']) ? null : $params['to'];

        $specialty = Yii::app()->request->getParam('specialty');
        $subspecialty_id = isset($specialty) ?
        (
            $specialty === 'All' ? null : $this->getSubspecialtyID($specialty)
        ) : null;
        // if there is surgeon id passed in, use that
        // but if logged in user has surgeon id, which means
        // he/she is not a service manager, overwrite the $surgeon_id
        $surgeon_id = isset($params['clinical_surgeon']) ? $params['clinical_surgeon'] : null;
        if ($this->surgeon) {
            $surgeon_id = $this->surgeon;
        }
        // one of the field in select statement at the end of this function
        // used to switch between no diagnosis and with diagnosis
        $diagnosis_term = 'GROUP_CONCAT(DISTINCT diagnosis.term)';

        // prepare diagnoses list from existing function
        $diagnoses = $this->queryDiagnosis($subspecialty_id, $surgeon_id, strtotime($params['from']), strtotime($params['to']))
        ->select('
            t.disorder_id disorder_id,
            t.term term,
            t.patient_id
        ');
        $paitent_list_command = Yii::app()->db->createCommand()
            ->from('patient p')
            ->leftJoin('contact c', 'p.contact_id = c.id')
            ->leftJoin('episode e', 'p.id = e.patient_id')
            ->leftJoin('(
                SELECT
                    e2.patient_id,
                    GROUP_CONCAT(p.short_format) AS procedures
                FROM et_ophtroperationnote_procedurelist eop
                LEFT JOIN ophtroperationnote_procedurelist_procedure_assignment oppa
                ON eop.id = oppa.procedurelist_id
                LEFT JOIN proc p ON oppa.proc_id = p.id
                LEFT JOIN event e ON eop.event_id = e.id
                LEFT JOIN episode e2 ON e.episode_id = e2.id
                WHERE p.short_format IS NOT NULL
                GROUP BY e2.patient_id
            ) proc', 'proc.patient_id = p.id')
            ->group('p.id');
        // triggered from clinical screen
        if (isset($params['diagnosis'])) {
            if ($params['diagnosis'] === "No Diagnoses") {
                $diagnosis_term = 'NULL';
                $no_diagnosis = $this->getPatientWithoutDisorders($subspecialty_id, $surgeon_id, strtotime($params['from']), strtotime($params['to']))
                    ->select('
                        p.id patient_id
                    ', 'DISTINCT');
                $paitent_list_command
                    ->join('(' . $no_diagnosis->getText() . ') patient_without_diagnosis', 'p.id = patient_without_diagnosis.patient_id');
            } else {
                $paitent_list_command
                    ->join('(' .
                    $diagnoses
                    ->where("LOWER(t.term) = '" . strtolower($params['diagnosis']) . "'")
                    ->getText()
                    . ') diagnosis', 'e.patient_id = diagnosis.patient_id');
            }
            $paitent_list_command->limit($params['limit'])->offset($params['offset']);
        }
        // triggered by download csv
        if (isset($params['diagnoses_csv'])) {
            $paitent_list_command
                ->leftJoin('(' . $diagnoses->getText() . ') diagnosis', 'e.patient_id = diagnosis.patient_id');
            $paitent_list_command->where("diagnosis.term IS NOT NULL");
        }
        // triggered from service screen

        if (isset($params['ids'])&&((is_array($params['ids']) && count($params['ids'])) || $params['ids'])) {
            $params['ids'] = json_decode($params['ids']);
            $paitent_list_command
                ->leftJoin('(' . $diagnoses->getText() . ') diagnosis', 'e.patient_id = diagnosis.patient_id');
            $paitent_list_command->where('p.id IN (' . implode(', ', $params['ids']) . ')');
        }

        $res = $paitent_list_command
            ->select("
                p.hos_num as hos_num,
                p.nhs_num as nhs_num,
                p.id AS patient_id,
                CONCAT(c.first_name, ' ', c.last_name) AS name,
                p.dob as dob,
                IF(p.date_of_death IS NOT NULL,
                YEAR(p.date_of_death) - YEAR(p.dob) - IF( DATE_FORMAT(p.date_of_death,'%m-%d') < DATE_FORMAT(p.dob,'%m-%d'), 1, 0),
                YEAR(CURRENT_DATE())-YEAR(p.dob)-IF(DATE_FORMAT(CURRENT_DATE(),'%m-%d') < DATE_FORMAT(p.dob,'%m-%d'), 1, 0)) as age,
                p.gender as gender,
                $diagnosis_term diagnoses,
                proc.procedures
            ", 'DISTINCT')
            ->group('p.id')
            ->queryAll();
        return $res;
    }

    private function getEventDate()
    {
        if (isset(Yii::app()->modules['OphOuCatprom5'])) {
            $event_date_command = Yii::app()->db->createCommand()
            ->select('
                MAX(t.date_to) as date_to,
                MIN(t.date_from) as date_from
            ')
            ->from('
                (
                    SELECT
                        MAX(e.event_date) as date_to,
                        MIN(e.event_date) as date_from
                    FROM et_ophtroperationnote_cataract eoc
                    JOIN event e on e.id = eoc.event_id
                    UNION 
                    SELECT
                        MAX(e2.event_date) as date_to,
                        MIN(e2.event_date) as date_from
                    FROM cat_prom5_event_result cat
                    JOIN event e2 on e2.id = cat.event_id
                ) t');
        } else {
            $event_date_command = Yii::app()->db->createCommand()
            ->select('
                MAX(e.event_date) as date_to,
                MIN(e.event_date) as date_from
            ')
            ->from('et_ophtroperationnote_cataract eoc')
            ->join('event e', 'e.id = eoc.event_id');
        }
        $event_date = $event_date_command->queryAll();
        return $event_date;
    }

    /**
     * @param $data_list
     * @param $sum
     * @param $count
     * @return float custom_csv_data
     * This function is used to calculate the standard deviation of an array.
     * Used for Glaucoma and MR subspecialties VA/IOP/CRT plots.
     * Normally we don't need the sum and number count passed as variables because they are can be get from the original array
     * But in this situation, we get the sum and count from the upper function, pass and use them directly will cause less calculations.
     */
    public function calculateStandardDeviation($data_list, $sum, $count)
    {
        $variance = 0;
        $average = $sum/$count;
        foreach ($data_list as $value) {
            $current_deviation = $value - $average;
            $variance += $current_deviation * $current_deviation;
        }
        $variance /= $count;

        return sqrt($variance);
    }
    /**
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return treatment procedures query object
    */
    private function queryCRTProcedure($for_plot = true)
    {
        $query_conditions = array('and');
        if ($for_plot) {
          // parameter :side will be passed in in the main query
            $query_conditions[] = 'IF(eot.eye_id = 2, 0, eot.eye_id) IN (:side, 3)';
        }
        if (isset($this->surgeon)) {
            $query_conditions[] = 'e.created_user_id = ' . $this->surgeon;
        } elseif (isset($this->filters['custom_surgeon_id'])) {
            $query_conditions[] = 'e.created_user_id = ' . $this->filters['custom_surgeon_id'];
        }
        if (isset($this->filters['treatment'])) {
            $query_conditions[] = 'eot.drug_id ' . $this->filters['treatment'];
        }
        $injection_proc = Yii::app()->db->createCommand()
        ->select('
            ep.patient_id patient_id,
            MAX(e.event_date) event_date,
            eot.eye_id eye_side,
            eot.drug_id
        ')
        ->from('(
            SELECT 
              event_id event_id,
              left_drug_id drug_id,
              1 eye_id
            FROM et_ophtrintravitinjection_treatment
  
            UNION
  
            SELECT
              event_id event_id,
              right_drug_id drug_id,
              0 eye_id
            FROM et_ophtrintravitinjection_treatment
        ) eot')
        ->join('event e', 'eot.event_id = e.id')
        ->join('episode ep', 'e.episode_id = ep.id')
        ->where($query_conditions)
        ->group('ep.patient_id');

        return $injection_proc;
    }

    /**
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return general procedures and laser procedures query object
    */
    private function queryVAIOPProcedure($for_plot = true)
    {
        $query_conditions = array('and');
        if ($for_plot) {
          // parameter :side will be passed in in the main query
            $query_conditions[] = 'ops.eye_side IN (:side, 3)';
        }
        if (isset($this->surgeon)) {
            $query_conditions[] = 'ops.created_user_id = ' . $this->surgeon;
        } elseif (isset($this->filters['custom_surgeon_id'])) {
            $query_conditions[] = 'ops.created_user_id = ' . $this->filters['custom_surgeon_id'];
        }
        if ($this->filters['procedure']) {
            $query_conditions[] = 'ops.procedure_id ' . $this->filters['procedure'];
        }
        $op_proc = Yii::app()->db->createCommand()
        ->select('
            ep.patient_id patient_id,
            e.event_date event_date,
            IF(eop.eye_id = 2, 0, eop.eye_id) eye_side,
            e.created_user_id created_user_id,
            opa.proc_id procedure_id
        ')
        ->from('et_ophtroperationnote_procedurelist eop')
        ->join('ophtroperationnote_procedurelist_procedure_assignment opa', 'eop.id = opa.procedurelist_id')
        ->join('event e', 'eop.event_id = e.id')
        ->join('episode ep', 'e.episode_id = ep.id')
        ->join('patient p', 'p.id = ep.patient_id');
        $laser_proc = Yii::app()->db->createCommand()
        ->select('
            ep.patient_id patient_id,
            e.event_date event_date,
            IF(ola.eye_id = 2, 0, ola.eye_id) eye_side,
            e.created_user_id created_user_id,
            ola.procedure_id procedure_id
        ', 'DISTINCT')
        ->from('et_ophtrlaser_treatment eot')
        ->join('ophtrlaser_laserprocedure_assignment ola', 'eot.id = ola.treatment_id')
        ->join('event e', 'eot.event_id = e.id')
        ->join('episode ep', 'e.episode_id = ep.id')
        ->join('patient p', 'p.id = ep.patient_id');
        $patient_proc = Yii::app()->db->createCommand()
        ->select('
            ops.patient_id patient_id,
            MAX(ops.event_date) event_date,
            ops.eye_side eye_side,
            ops.created_user_id created_user_id
        ')
        // union normal procedure and laser procedure
        ->from('(' . $op_proc->union("$laser_proc->text")->text . ') ops')
        ->where($query_conditions)
        ->group('ops.patient_id, ops.eye_side');

        return $patient_proc;
    }

    /**
     * @return object
     * return current patients query object
    */
    private function queryCustomDiagnoses()
    {
        $query_conditions = array('and');

        $query_conditions[] = 'ep.deleted = 0';

        if (isset($this->filters['age_min']) && isset($this->filters['age_max'])
        && isset($this->filters['age_min']) <= isset($this->filters['age_max'])) {
            $age_min = $this->filters['age_min'];
            $age_max = $this->filters['age_max'];
            $query_conditions[] = "p.age >= $age_min AND p.age <= $age_max";
            $query_conditions[] = "p.is_deceased = 0";
        }
        $patient_episode_diagnoses = Yii::app()->db->createCommand()
        ->select('
            ep.patient_id patient_id, 
            ep.disorder_id disorder_id,
            IF(ep.eye_id = 2, 0, ep.eye_id) eye_id,
            ep.id episode_id,
            d.active,
            p.full_name full_name,
            p.age age
        ')
        ->from('episode ep')
        ->join('v_patient_details p', 'ep.patient_id = p.patient_id')
        ->leftJoin('disorder d', 'ep.disorder_id = d.id')
        ->where($query_conditions);

        $patient_secondary_diagnosis = Yii::app()->db->createCommand()
        ->select('
            sd.patient_id patient_id,
            sd.disorder_id disorder_id,
            IF(sd.eye_id = 2, 0, sd.eye_id) eye_id,
            ep.id episode_id,
            d.active,
            p.full_name full_name,
            p.age age
        ')
        ->from('secondary_diagnosis sd')
        ->join('v_patient_details p', 'sd.patient_id = p.patient_id')
        ->join('episode ep', 'p.patient_id = ep.patient_id')
        ->leftJoin('disorder d', 'sd.disorder_id = d.id')
        ->where($query_conditions);

        return $patient_episode_diagnoses
              ->union("$patient_secondary_diagnosis->text");
    }


    /**
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return best VA reading query object
    */
    private function queryBestVAReading($for_plot = true)
    {
        $query_conditions = array('and');
        if ($for_plot) {
            $query_conditions[] = 'reading.side = :side';
        }
        $query_conditions[] = 'ep.deleted = 0 AND e.deleted = 0';
        $best_reading = Yii::app()->db->createCommand()
        ->select('
            e.id event_id,
            reading.side eye_id,
            reading.value value,
            e.event_date event_date,
            e.episode_id episode_id
        ')
        ->from('event e')
        ->join('et_ophciexamination_visualacuity eov', 'e.id = eov.event_id')
        ->join('(
            SELECT 
                ovr.element_id,
                ovr.value,
                ovr.side,
                ovr.method_id
            FROM (
                SELECT 
                    element_id, 
                    side, 
                    max(value) value
                FROM ophciexamination_visualacuity_reading
                GROUP BY element_id, side
            ) max_val
            INNER JOIN ophciexamination_visualacuity_reading ovr USING (element_id, side, value)
        ) reading', 'reading.element_id = eov.id')
        ->join('episode ep', 'e.episode_id = ep.id')
        ->where($query_conditions)
        // need to confirm with James if the following is needed or not
        ->andWhere('eov.unit_id in (2, 4) and reading.method_id in (2, 4)');

        return $best_reading;
    }

    /**
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return IOP reading query object
    */
    private function queryIOPReading($for_plot = true)
    {
        $query_conditions = array('and');
        if ($for_plot) {
            $query_conditions[] = 'IF(oiv.eye_id = 2, 0, oiv.eye_id) = :side';
        }
        $query_conditions[] = 'e.deleted = 0';
        $iop_reading = Yii::app()->db->createCommand()
        ->select('
            e.id event_id,
            IF(oiv.eye_id = 2, 0, oiv.eye_id) eye_id,
            oir.value value,
            e.event_date event_date,
            e.episode_id episode_id
        ')
        ->from('event e')
        ->join('et_ophciexamination_intraocularpressure eoi', 'e.id = eoi.event_id')
        ->join('ophciexamination_intraocularpressure_value oiv', 'eoi.id = oiv.element_id')
        ->join('ophciexamination_intraocularpressure_reading oir', 'oiv.reading_id = oir.id')
        ->where($query_conditions);

        return $iop_reading;
    }

    /**
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return CRT reading query object
    */
    private function queryCRTReading($for_plot = true)
    {
        $query_conditions = array('and');
        if ($for_plot) {
            $query_conditions[] = 'crt.side = :side';
        }
        $query_conditions[] = 'e.deleted = 0';
        $query_conditions[] = 'crt.value IS NOT NULL';
        $crt_reading = Yii::app()->db->createCommand()
        ->select('
            e.id event_id,
            crt.side eye_id,
            crt.value value,
            e.event_date event_date,
            e.episode_id episode_id
        ')
        ->from('event e')
        ->join('(
            SELECT event_id, left_sft value, 1 side
            FROM et_ophciexamination_oct
            UNION
            SELECT event_id, right_sft, 0 side
            FROM et_ophciexamination_oct
        ) crt', 'e.id = crt.event_id')
        ->where($query_conditions);

        return $crt_reading;
    }
    /**
     * @param $subsepcialty: 'Glaucoma' / 'Medical Retina'
     * @param $eye_side: default to true, pass in as false in CSV download function
     * @param $plot_type: VA, IOP, CRT
     * @param $time_interval: 1, 2, 3, 4 Weeks/Months
     * @param $for_plot: default to true, pass in as false in CSV download function
     * @return object
     * return VA/IOP/CRT with latest op as baseline query object
    */
    private function queryCustomData($subsepcialty, $eye_side, $plot_type, $time_interval, $for_plot = true)
    {
        $patient_diagnosis_query = $this->queryCustomDiagnoses()->text;

        switch ($plot_type) {
            case 'VA':
                $reading_query = $this->queryBestVAReading($for_plot)->text;
                break;
            case 'IOP':
                $reading_query = $this->queryIOPReading($for_plot)->text;
                break;
            case 'CRT':
                $reading_query = $this->queryCRTReading($for_plot)->text;
                break;
        }

        switch ($subsepcialty) {
            case 'Glaucoma':
                $op_query = $this->queryVAIOPProcedure($for_plot)->text;
                break;
            case 'Medical Retina':
                $op_query = $this->queryCRTProcedure($for_plot)->text;
                break;
        }

        $query_conditions = array('and');
        $query_params = array();
        if (isset($this->filters['custom_diagnosis'])) {
            $diagnoses = implode(",", $this->filters['custom_diagnosis']);
            $query_conditions[] = "diag.disorder_id IN ( $diagnoses )";
            $query_conditions[] = 'diag.active = 1';
        }
        if (isset($eye_side)) {
            $query_params[':side'] = $eye_side;
        }
        if (isset($this->filters['date_from']) && isset($this->filters['date_to'])
        && $this->filters['date_from'] < $this->filters['date_to']) {
            $date_from = $this->filters['date_from'];
            $date_to = $this->filters['date_to'];
            $query_conditions[] = "UNIX_TIMESTAMP(exam.event_date) >= $date_from";
            $query_conditions[] = "UNIX_TIMESTAMP(patient_ops.event_date) >= $date_from";
            $query_conditions[] = "UNIX_TIMESTAMP(exam.event_date) <= $date_to";
            $query_conditions[] = "UNIX_TIMESTAMP(patient_ops.event_date) <= $date_to";
        }

        $time_interval_unit = $time_interval['unit'];
        $time_interval_num = $time_interval['num'];

        $final_data_cmd = Yii::app()->db->createCommand()
        ->select("
            diag.patient_id patient_id,
            IF(patient_ops.event_date IS NOT NULL, FLOOR(TIMESTAMPDIFF($time_interval_unit, patient_ops.event_date, exam.event_date) / $time_interval_num), -5) time_interval,
            exam.value reading,
            exam.eye_id eye_side
        ")
        ->from("($patient_diagnosis_query) diag")
        ->join("($reading_query) exam", 'exam.episode_id = diag.episode_id')
        ->join("($op_query) patient_ops", 'patient_ops.patient_id = diag.patient_id')
        ->where($query_conditions, $query_params);

        return $final_data_cmd;
    }

    /**
     * @param $subsepcialty: 'Glaucoma' / 'Medical Retina'
     * @param $plot_type: VA, IOP, CRT
     * @return object
     * manipulate the query data
    */
    private function getCustomDataListQueryNew($subsepcialty, $plot_type)
    {
        $left_list = array();
        $right_list = array();
        $time_interval = $this->filters['time_interval'];
        foreach (['right','left'] as $side) {
            $eye_side = $side === 'right' ? 0 : 1;
            $elements = $this->queryCustomData($subsepcialty, $eye_side, $plot_type, $time_interval)->queryAll();
            foreach ($elements as $element) {
                // all the data with negative time_interval value is pre-op data
                if ($element['time_interval'] < 0) {
                    $reading = $element['reading'];

                    $selected_interval = -5;

                    if (array_key_exists($selected_interval, ${$side.'_list'})) {
                        ${$side.'_list'}[$selected_interval] = $this->processCustomData(${$side.'_list'}[$selected_interval], $element['patient_id'], $reading);
                    } else {
                        ${$side.'_list'}[$selected_interval] = $this->processCustomData(null, $element['patient_id'], $reading, true);
                    }
                }
            }
            // if there is no pre-op data at all, the post-op data will not be considered
            if (isset(${$side.'_list'}[-5])) {
                foreach ($elements as $element) {
                    // all the data with positive time_interval value is post-op data
                    if ($element['time_interval'] >= 0) {
                        if (!in_array($element['patient_id'], ${$side.'_list'}[-5]['patients'])) {
                            continue;
                        }
                        $reading = $element['reading'];

                        $selected_interval = $element['time_interval'] * $time_interval['num'];
                        if (array_key_exists($selected_interval, ${$side.'_list'})) {
                            ${$side.'_list'}[$selected_interval] = $this->processCustomData(${$side.'_list'}[$selected_interval], $element['patient_id'], $reading);
                        } else {
                            ${$side.'_list'}[$selected_interval] = $this->processCustomData(null, $element['patient_id'], $reading, true);
                        }
                    }
                }
            }
        }
        ksort($left_list, SORT_NATURAL);
        ksort($right_list, SORT_NATURAL);

        return [$left_list,$right_list];
    }
    /**
     * @param $element: dataset at certain time interval point. can be NULL
     * @param $patient_id: current patient id
     * @param $reading: VA/IOP/CRT reading
     * @param $init: init flag
     * @return object
     * init or manipulate query data
    */
    private function processCustomData($element, $patient_id, $reading, $init = false)
    {
        if ($init) {
            if (isset($reading)) {
                return array(
                    'count'=> 1,
                    'count_avg'=> 1,
                    'sum' => $reading,
                    'square_sum'=> $reading ** 2,
                    'average'=>$reading,
                    'SD'=>0,
                    'patients' => array($patient_id),
                );
            } else {
                return array(
                    'count'=> 0,
                    'count_avg'=> 0,
                    'sum' => null,
                    'square_sum'=> null,
                    'average'=> null,
                    'SD'=> null,
                    'patients' => array(),
                );
            }
        }
        if (!in_array($patient_id, $element['patients'])) {
            $element['count']+=1;
            $element['patients'][] =  $patient_id;
        }
        $element['count_avg'] += 1;
        $element['sum'] += $reading;
        $element['square_sum'] += $reading ** 2;
        $element['average'] = round($element['sum']/ $element['count_avg']);
        $element['SD'] = $this->calculateStandardDeviationByDataSet($element);
        return $element;
    }
    public function calculateStandardDeviationByDataSet($data_set)
    {
        $square_average = $data_set['average'] ** 2;
        $square_sum = $data_set['square_sum'];
        $sum = $data_set['sum'];
        $average = $data_set['average'];
        $count = $data_set['count_avg'];

        $SD = sqrt((($square_sum-(2*$average*$sum))/$count) + $square_average);
        return number_format($SD, 2, '.', '');
    }
    /**
     * @return mixed
     * Get all the cataract elements in operation note event
     * Used for the drill down list.
     */
    public function queryCataractEventList($params = mull)
    {
        $return_data = array();
        $command = Yii::app()->db->createCommand()
            ->select('
                p.hos_num as hos_num,
                p.nhs_num as nhs_num,
                eoc.event_id as event_id,
                e.event_date as event_date,
                eye.name as eye_side,
                CONCAT(c.first_name, " ", c.last_name) as name,
                p.id as patient_id,
                p.dob as dob,
                IF(p.date_of_death IS NOT NULL,
                YEAR(p.date_of_death) - YEAR(p.dob) - IF( DATE_FORMAT(p.date_of_death,"%m-%d") < DATE_FORMAT(p.dob,\'%m-%d\'), 1, 0),
                YEAR(CURRENT_DATE())-YEAR(p.dob)-IF(DATE_FORMAT(CURRENT_DATE(),"%m-%d") < DATE_FORMAT(p.dob,\'%m-%d\'), 1, 0)) as age,
                p.gender as gender,
                GROUP_CONCAT(proc.term separator \', \') as procedures,
                patient_diagnoses.diagnoses
            ')
            ->from('et_ophtroperationnote_cataract eoc')
            ->join('event e', 'e.id = eoc.event_id')
            ->join('episode ep', 'ep.id = e.episode_id')
            ->join('patient p', 'p.id = ep.patient_id')
            ->join('contact c', 'c.id = p.contact_id')
            ->join('et_ophtroperationnote_procedurelist eop', 'eop.event_id = eoc.event_id')
            ->leftJoin('ophtroperationnote_procedurelist_procedure_assignment oppa', 'oppa.procedurelist_id = eop.id')
            ->leftJoin('proc', 'proc.id = oppa.proc_id')
            ->leftJoin('eye', 'eye.id = eop.eye_id')
            ->leftJoin('(
                SELECT patient_id AS patient_id, GROUP_CONCAT(diagnoses) AS diagnoses
                FROM (
                    SELECT ep.patient_id AS patient_id, GROUP_CONCAT(d.term) AS diagnoses
                    FROM episode ep
                        LEFT JOIN disorder d ON ep.disorder_id = d.id
                    WHERE d.term IS NOT NULL
                    GROUP BY ep.patient_id
                    UNION
                    SELECT sd.patient_id AS patient_id, GROUP_CONCAT(d.term) AS diagnoses
                    FROM secondary_diagnosis sd
                        LEFT JOIN disorder d ON sd.disorder_id = d.id
                    WHERE d.term IS NOT NULL
                    GROUP BY sd.patient_id
                 ) t2
                GROUP BY t2.patient_id) patient_diagnoses', 'patient_diagnoses.patient_id = p.id')
            ->group('p.id, e.id, eye.name')
            ->order('name, e.event_date DESC');
        if (isset($params['ids'])&&count($params['ids'] > 0)) {
            $params['ids'] = json_decode($params['ids']);
            $command->where('e.id IN (' . implode(', ', $params['ids']) . ')');
        }
        $return_data = $command->queryAll();
        return $return_data;
    }

    public function insertIntoCustomCSV($current_patient, $right_reading, $left_reading, $type)
    {
        if (!array_key_exists($current_patient->id, $this->custom_csv_data) && (isset($right_reading) || isset($left_reading))) {
            $this->custom_csv_data[$current_patient->id] = array(
                'first_name'=>$current_patient->getFirst_name(),
                'last_name'=>$current_patient->getLast_name(),
                'hos_num'=>$current_patient->hos_num,
                'dob'=>$current_patient->dob,
                'age'=>$current_patient->getAge(),
                'diagnoses'=>$current_patient->getDiagnosesTermsArray(),
                'left'=>array(
                    'VA' =>array(),
                    'CRT' =>array(),
                    'IOP' =>array(),
                ),
                'right'=>array(
                    'VA' =>array(),
                    'CRT' =>array(),
                    'IOP' =>array(),
                ),
            );
        }
        if (isset($right_reading)) {
            $this->custom_csv_data[$current_patient->id]['right'][$type][] = $right_reading;
        }
        if (isset($left_reading)) {
            $this->custom_csv_data[$current_patient->id]['left'][$type][] = $left_reading;
        }
    }


    public function getCommonDisorders($subspecialty_id = null, $only_name = false)
    {
        $where = '';
        $queryConditions = array('and');
        $queryConditions[] = 'd.term IS NOT NULL';
        if ($subspecialty_id) {
            $where = "AND cod.subspecialty_id = " . $subspecialty_id;
            $queryConditions[] = 'cod.subspecialty_id = ' . $subspecialty_id;
        }

        if ($only_name) {
            $common_ophthalmic_disorders_command = Yii::app()->db->createCommand()
            ->select('d.term', 'DISTINCT')
            ->from('common_ophthalmic_disorder cod')
            ->leftJoin('disorder d', 'd.id = cod.disorder_id')
            ->where($queryConditions);
            $common_ophthalmic_disorders = $common_ophthalmic_disorders_command->queryAll();
        } else {
            $sql = "
                SELECT DISTINCT
                    cod.id,
                    cod.disorder_id
                FROM common_ophthalmic_disorder cod
                WHERE cod.disorder_id IS NOT NULL
            ";
            $sql .= $where;
            $common_ophthalmic_disorders = CommonOphthalmicDisorder::model()->findAllBySQL($sql);
        }
        return $common_ophthalmic_disorders;
    }

    public function getPatientsListByDiagnosisSurgeon($surgeon_id = null, $subspecialty = null)
    {
        $command = Yii::app()->db->createCommand()
            ->select('e2.patient_id as patient_id')
            ->from('et_ophciexamination_diagnoses eod')
            ->leftJoin('event e', 'e.id = eod.event_id')
            ->leftJoin('episode e2', 'e2.id = e.episode_id')
            ->leftJoin('firm', 'firm.id = e2.firm_id')
            ->leftJoin('service_subspecialty_assignment ssa', 'firm.service_subspecialty_assignment_id = ssa.id')
            ->group('e2.patient_id');
        if (isset($subspecialty)) {
            $command->andWhere('ssa.subspecialty_id = :subspecialty_id', array(':subspecialty_id'=>$subspecialty));
        }
        if (isset($surgeon_id)) {
            $command->andWhere('eod.created_user_id = :surgeon_id', array(':surgeon_id'=>$surgeon_id));
        }
        $query_list= $command->queryAll();
        $patients_list = array();
        foreach ($query_list as $patient) {
            $patients_list[] = $patient['patient_id'];
        }
        return $patients_list;
    }

    public function queryDiagnosis($subspecialty_id = null, $surgeon_id = null, $start_date = null, $end_date = null)
    {
        $command_principal = Yii::app()->db->createCommand()
            ->select('
                e.patient_id patient_id,
                e.disorder_id disorder_id,
                d.term term,
                d.fully_specified_name fully_specified_name,
                cod.id disorder_type
            ')
            ->from('episode e')
            ->leftJoin('disorder d', 'd.id = e.disorder_id')
            ->leftJoin('common_ophthalmic_disorder cod', 'd.id = cod.disorder_id')
            ->leftJoin('firm f', 'e.firm_id = f.id')
            ->leftJoin('service_subspecialty_assignment ssa', 'ssa.id = f.service_subspecialty_assignment_id')
            ->where('e.disorder_id IS NOT NULL')
            ->andWhere('e.deleted = 0');

        $command_secondary = Yii::app()->db->createCommand()
            ->select('
                sd.patient_id patient_id,
                sd.disorder_id disorder_id,
                d.term term,
                d.fully_specified_name fully_specified_name,
                cod.id disorder_type
            ')
            ->from('secondary_diagnosis sd')
            ->leftJoin('disorder d', 'd.id = sd.disorder_id')
            ->leftJoin('common_ophthalmic_disorder cod', 'sd.disorder_id = cod.disorder_id')
            ->leftJoin('episode e', 'e.patient_id = sd.patient_id')
            ->leftJoin('firm f', 'e.firm_id = f.id')
            ->leftJoin('service_subspecialty_assignment ssa', 'ssa.id = f.service_subspecialty_assignment_id')
            ->where('sd.disorder_id IS NOT NULL');
        if (isset($subspecialty_id)) {
            $command_principal->andWhere('ssa.subspecialty_id = ' . $subspecialty_id);
            $command_secondary->andWhere('ssa.subspecialty_id = ' . $subspecialty_id);
        }
        if (isset($surgeon_id)) {
            $command_principal->andWhere('e.created_user_id = ' . $surgeon_id);
            $command_secondary->andWhere('sd.created_user_id = ' . $surgeon_id);
        }
        if (isset($start_date) && $start_date !== 0 && $start_date) {
            $command_principal->andWhere('UNIX_TIMESTAMP(e.created_date) > '.$start_date);
            $command_secondary->andWhere('UNIX_TIMESTAMP(sd.created_date) > '.$start_date);
        }
        if (isset($end_date) && $end_date) {
            $command_principal->andWhere('UNIX_TIMESTAMP(e.created_date) < '.$end_date);
            $command_secondary->andWhere('UNIX_TIMESTAMP(sd.created_date) < '.$end_date);
        }
        $return_command = Yii::app()->db->createCommand()
            ->from('(' . $command_principal->getText() .
            ' UNION ALL ' . $command_secondary->getText() . ') t');
        return $return_command;
    }

    public function getPatientWithoutDisorders($subspecialty_id = null, $surgeon_id = null)
    {
        $queryConditions = array('and');
        $outterQueryConditions = array('and');
        $params = array();
        $secondary_diagnosis_command = Yii::app()->db->createCommand()
            ->select('
                sd.patient_id,
                sd.disorder_id,
                sd.created_user_id,
                ssa.subspecialty_id,
                sd.created_date
            ')
            ->from('secondary_diagnosis sd')
            ->leftJoin('episode ep', 'ep.patient_id = sd.patient_id')
            ->leftJoin('firm f', 'ep.firm_id = f.id')
            ->leftJoin('service_subspecialty_assignment ssa', 'ssa.id = f.service_subspecialty_assignment_id');
        $episode_diagnosis_command = Yii::app()->db->createCommand()
            ->select('
                ep2.patient_id,
                ep2.disorder_id,
                ep2.created_user_id,
                ssa.subspecialty_id,
                ep2.created_date
            ')
            ->from('episode ep2')
            ->leftJoin('firm f', 'ep2.firm_id = f.id')
            ->leftJoin('service_subspecialty_assignment ssa', 'ssa.id = f.service_subspecialty_assignment_id')
            ->where('ep2.disorder_id is not null');
        if ($subspecialty_id) {
            $queryConditions[] = 't.subspecialty_id = ' . $subspecialty_id;
            $outterQueryConditions[] = 'ssa.subspecialty_id = ' . $subspecialty_id;
        }
        if ($surgeon_id) {
            $queryConditions[] = 't.created_user_id = '. $surgeon_id;
            $outterQueryConditions[] = 'ep3.created_user_id = ' . $surgeon_id;
        }
        $patient_with_disorder_command = Yii::app()->db->createCommand()
            ->select('
                patient_id,
                disorder_id,
                created_user_id,
                subspecialty_id,
                created_date
            ')
            ->from('
                (' .
                $secondary_diagnosis_command->getText() .
                ' UNION ALL ' .
                $episode_diagnosis_command->getText() .
            ') t')
            ->where($queryConditions);
        $command_no_disorder_patient = Yii::app()->db->createCommand()
            ->from('patient p')
            ->leftJoin('episode ep3', 'p.id = ep3.patient_id')
            ->leftJoin('firm f', 'ep3.firm_id = f.id')
            ->leftJoin('service_subspecialty_assignment ssa', 'ssa.id = f.service_subspecialty_assignment_id')
            ->leftJoin('
                (' .
                    $patient_with_disorder_command->getText()
                . ') t2', 'p.id = t2.patient_id')
            ->where('t2.disorder_id is null')
            ->andWhere($outterQueryConditions);
        return $command_no_disorder_patient;
    }

    public function getDisorders($subspecialty_id = null, $surgeon_id = null, $start_date = null, $end_date = null)
    {
        $disorder_list = array(
            'x'=> array(),
            'y'=>array(),
            'text' => array(),
            'customdata' => array(),
          );
          $patient_without_disorder = $this->getPatientWithoutDisorders($subspecialty_id, $surgeon_id, $start_date, $end_date)
              ->select('COUNT(DISTINCT p.id) total_patients')
              ->queryAll();

          $other_disorder_total = $this->queryDiagnosis($subspecialty_id, $surgeon_id, $start_date, $end_date)
              ->select('COUNT(DISTINCT t.patient_id) total_patients')
              ->where('t.disorder_type IS NULL')
              ->queryAll();

          $other_disorders = $this->queryDiagnosis($subspecialty_id, $surgeon_id, $start_date, $end_date)
              ->select('
                    COUNT(DISTINCT t.patient_id) total_patients,
                    t.disorder_id disorder_id,
                    t.term term,
                    t.fully_specified_name fully_specified_name
              ')
              ->where('t.disorder_type IS NULL')
              ->group('
                    t.disorder_id,
                    t.term,
                    t.fully_specified_name
              ')
              ->queryAll();

          $common_disorders = $this->queryDiagnosis($subspecialty_id, $surgeon_id, $start_date, $end_date)
              ->select('
                    COUNT(DISTINCT t.patient_id) total_patients,
                    t.disorder_id disorder_id,
                    t.term term,
                    t.fully_specified_name fully_specified_name
              ')
              ->where('t.disorder_type IS NOT NULL')
              ->group('
                    t.disorder_id,
                    t.term,
                    t.fully_specified_name
              ')
              ->queryAll();
          // $i for y axis in first level of plot
          $i = 0;
          // $j for y axis in second level of plot
          $j = 0;
          $other_disorder = array(
              'x'=> array(),
              'y'=>array(),
              'text' => array(),
              'customdata' => array(),
          );
          foreach ($common_disorders as $common_disorder) {
              $disorder_list['y'][] = $i;
              $disorder_list['x'][] = $common_disorder['total_patients'];
              $disorder_list['text'][] = $common_disorder['term'];
              $disorder_list['customdata'][] = array($common_disorder['term']);
              $i++;
          }
          foreach ($other_disorders as $row) {
              $other_disorder['y'][] = $j;
              $other_disorder['x'][] = $row['total_patients'];
              $other_disorder['text'][] = $row['term'];
              $other_disorder['customdata'][] = array($row['term']);
              $j++;
          }
          if ($other_disorder_total[0]['total_patients'] != 0) {
              $disorder_list['y'][] = $i;
              $disorder_list['x'][] = $other_disorder_total[0]['total_patients'];
              $disorder_list['text'][] = 'Other';
              $disorder_list['customdata'][] = $other_disorder;
              $i++;
          }
          if ($patient_without_disorder[0]['total_patients'] != 0) {
              $disorder_list['y'][] = $i;
              $disorder_list['x'][] = $patient_without_disorder[0]['total_patients'];
              $disorder_list['text'][] = 'No Diagnoses';
              $disorder_list['customdata'][] = array('No Diagnoses');
              $i++;
          }

          return $disorder_list;
    }

    /**
     * Get all filters from sidebar, used together with actionUpdateData()
     */
    public function obtainFilters()
    {
        $form_data = Yii::app()->request->getParam('form_data');
        $specialty = Yii::app()->request->getParam('specialty');
        $dateFrom = Yii::app()->request->getParam('from');
        $dateTo = Yii::app()->request->getParam('to');
        $ageMin = Yii::app()->request->getParam('custom_age_min');
        $ageMax = Yii::app()->request->getParam('custom_age_max');
        $custom_diagnosis = Yii::app()->request->getParam('custom_diagnosis');
        $service_diagnosis = Yii::app()->request->getParam('service_diagnosis');
        $custom_treatment = Yii::app()->request->getParam('custom_treatment');
        $clinical_surgeon_id = Yii::app()->request->getParam('clinical_surgeon');
        $custom_surgeon_id = Yii::app()->request->getParam('custom_surgeon');
        $custom_procedure = Yii::app()->request->getParam('custom_procedure');
        $plot_va_change = Yii::app()->request->getParam('custom_plot');
        $time_interval_num = Yii::app()->request->getParam('time_interval_num');
        $time_interval_unit = Yii::app()->request->getParam('time_interval_unit');

        if (isset($custom_diagnosis)) {
            $diagnoses_MR = $this->getIdByName(array('age-related macular degeneration', 'Branch retinal vein occlusion', 'Central retinal vein occlusion', 'Diabetic macular oedema'), Disorder::class, 'term');
            $diagnoses_GL = $this->getIdByName(array('glaucoma', 'open-angle glaucoma', 'angle-closure glaucoma', 'Low tension glaucoma', 'Ocular hypertension'), Disorder::class, 'term');
            $custom_diagnosis_array = explode(",", $custom_diagnosis);
            $custom_diagnosis = array();
            foreach ($custom_diagnosis_array as $item) {
                if ($specialty === 'Medical Retina') {
                      $custom_diagnosis = array_merge_recursive($custom_diagnosis, $diagnoses_MR[$item]);
                } elseif ($specialty === 'Glaucoma') {
                      $custom_diagnosis = array_merge_recursive($custom_diagnosis, $diagnoses_GL[$item]);
                } else {
                    $custom_diagnosis = null;
                    break;
                }
            }
        }
        if (isset($custom_treatment)) {
            $treatments = $this->getIdByName(array('Lucentis', 'Eylea', 'Avastin', 'Triamcinolone', 'Ozurdex'), OphTrIntravitrealinjection_Treatment_Drug::class, 'name');
            $custom_treatment = $treatments[$custom_treatment];
            $custom_treatment = 'IN ('.implode(',', $custom_treatment).')';
        }
        if (isset($custom_procedure)) {
            $procedures = $this->getIdByName(array('cataract extraction','Trabeculectomy', 'aqueous shunt','Cypass Stent Insertion','Selective laser trabeculoplasty','Laser coagulation ciliary body'), Procedure::class, 'term');
            $custom_procedure = $procedures[$custom_procedure];
            $custom_procedure = 'IN ('.implode(',', $custom_procedure).')';
        }
        if (isset($plot_va_change) && $plot_va_change !== 'change') {
            $plot_va_change = true;
        } else {
            $plot_va_change = false;
        }
        if ($dateTo) {
            $dateTo = strtotime($dateTo);
        } else {
            $dateTo = strtotime(date("Y-m-d H:i:s"));
        }
        if ($dateFrom) {
            $dateFrom = strtotime($dateFrom);
        } else {
            $dateFrom = 0;
        }

        $time_interval = array(
            'unit' => !isset($time_interval_unit) || $time_interval_unit === 'Week(s)' ? 'WEEK' : 'MONTH',
            'num' => !isset($time_interval_num) ? 1 : $time_interval_num,
        );

        $this->filters = array(
          'specialty'=>$specialty,
          'date_from' => $dateFrom,
          'date_to' => $dateTo,
          'age_min'=>$ageMin,
          'age_max'=>$ageMax,
          'custom_diagnosis'=>$custom_diagnosis,
          'protocol'=>null,
          'plot-va'=>null,
          'treatment'=>$custom_treatment,
          'service_diagnosis'=>$service_diagnosis,
          'clinical_surgeon_id'=>$clinical_surgeon_id,
          'custom_surgeon_id'=>$custom_surgeon_id,
          'procedure'=>$custom_procedure,
          'plot_va_change'=> $plot_va_change,
          'time_interval' => $time_interval,
        );
    }
    public function getIdByName($name_array, $model, $name_attribute)
    {
        $return_array = array();
        foreach ($name_array as $name) {
            $items = $model::model()->findAll($name_attribute.' LIKE \'%'.$name.'%\'');
            if (isset($items)) {
                $item_array = array();
                foreach ($items as $item) {
                    $item_array[] = $item->id;
                }
                $return_array[] = $item_array;
            } else {
                $return_array[] = null;
            }
        }
        return $return_array;
    }
    /**
     * After "Update Chart" button is created, this function is called to get updated data based on current filters.
     */
    public function actionUpdateData()
    {
        $this->checkAuth();
        $this->obtainFilters(); // get current filters. Question: why not call validateFilters() in this function.
        $va_unit = VisualAcuityUnit::model()->getVAUnit(4);
        $va_init_ticks = VisualAcuityUnit::model()->getInitVaTicks($va_unit);
        $va_final_ticks = VisualAcuityUnit::model()->sliceVATicks($va_init_ticks, 20);
        $specialty = $this->filters['specialty'];

        if (!isset($this->surgeon)&&isset($surgeon_id)) {
            $this->surgeon = $surgeon_id;
        }
        if ($specialty === 'All') {
            $subspecialty_id = null;
            $custom_data = array();
        } else {
            $subspecialty_id = $this->getSubspecialtyID($specialty);
            $va_list_name = '_va_list';

            $second_list_name = $specialty === 'Glaucoma' ? '_iop_list' : '_crt_list';
            $plot_type = $specialty === 'Glaucoma' ? 'IOP' : 'CRT';

            list(${'left' . $va_list_name}, ${'right' . $va_list_name}) = $this->getCustomDataListQueryNew($specialty, 'VA');
            list(${'left' . $second_list_name}, ${'right' . $second_list_name}) = $this->getCustomDataListQueryNew($specialty, $plot_type);


            foreach (['left','right'] as $side) {
                if ($this->filters['plot_va_change']) {
                    $this->filters['plot_va_change_initial_va_value'] = empty(${$side.$va_list_name}) ? null: ${$side.$va_list_name}[-5]['average'];
                }
                $custom_data[] = array(
                  array(
                      'x' => array_keys(${$side.$va_list_name}),
                      'y' => array_map(
                            function ($item) {
                                if (isset($this->filters['plot_va_change_initial_va_value'])) {
                                    $item['average'] -= $this->filters['plot_va_change_initial_va_value'];
                                }
                                return $item['average'];
                            },
                          array_values(${$side.$va_list_name})
                      ),
                      'customdata'=>array_map(
                            function ($item) {
                                return $item['patients'];
                            },
                          array_values(${$side.$va_list_name})
                      ),
                      'error_y'=> array(
                          'type'=> 'data',
                          'array' => array_map(
                                function ($item) {
                                    return $item['SD'];
                                },
                              array_values(${$side.$va_list_name})
                          ),
                          'visible' => true,
                          'color' => '#aaa',
                          'thickness' => 1
                        ),
                        'hoverinfo' => 'text',
                        'hovertext' => array_map(
                            function ($item) {
                                $cVal = (float)$item['average'];
                                if (isset($this->filters['plot_va_change_initial_va_value'])) {
                                    $cVal -= (float)$this->filters['plot_va_change_initial_va_value'];
                                }
                                    return " Mean: " . $cVal . '<br> SD: ' . $item['SD'] . '<br> N: ' .count($item['patients']);
                            },
                            array_values(${$side . $va_list_name})
                        ),
                    ),
                  array(
                      'name' => $specialty === 'Glaucoma' ? 'IOP' : 'CRT',
                      'yaxis' => 'y2',
                      'x' => array_keys(${$side . $second_list_name}),
                      'y' => array_map(
                            function ($item) {
                                return $item['average'];
                            },
                          array_values(${$side . $second_list_name})
                      ),
                      'customdata'=>array_map(
                            function ($item) {
                                return $item['patients'];
                            },
                          array_values(${$side . $second_list_name})
                      ),
                      'error_y' => array(
                          'type' => 'data',
                          'array' => array_map(
                                function ($item) {
                                    return $item['SD'];
                                },
                              array_values(${$side . $second_list_name})
                          ),
                          'visible' => true,
                          'color' => '#aaa',
                          'thickness' => 1
                        ),
                        'hoverinfo' => 'text',
                        'hovertext' => array_map(
                            function ($item) {
                                return " Mean: " . $item['average'] . '<br> SD: ' . $item['SD'] . '<br> N: ' . count($item['patients']);
                            },
                            array_values(${$side . $second_list_name})
                        ),
                    )
                );
            }
        }
        $disorder_data = $this->getDisorders($subspecialty_id, $this->filters['clinical_surgeon_id'], $this->filters['date_from'], $this->filters['date_to']);
        $clinical_data = array(
          'x' => $disorder_data['x'],
          'y' => $disorder_data['y'],
          'text' => $disorder_data['text'],
          'customdata' =>$disorder_data['customdata'],
        );
        $service_data = $this->getFollowUps($subspecialty_id, $this->filters['date_from'], $this->filters['date_to'], $this->filters['service_diagnosis'], $this->surgeon);
        $this->renderJSON(array($clinical_data, $service_data, $custom_data, 'va_final_ticks'=>$va_final_ticks));
    }

    /**
     * @param $period_name
     * @return int
     * This function is used to transfer different period type from follow up into number of days
     * Called in getFollowUps() function
     */
    public function getPeriodDate($period_name)
    {
        switch ($period_name) {
            case 'days':
                $period = self::PERIOD_DAY;
                break;
            case 'weeks':
                $period = self::PERIOD_WEEK;
                break;
            case 'months':
                $period = self::PERIOD_MONTH;
                break;
            case 'years':
                $period = self::PERIOD_YEAR;
                break;
            default:
                $period = 0;
                break;
        }
        return $period;
    }
    private function queryDiagnosesFilteredPatientListCommand($eye_side, $caller = 'custom')
    {
        if ($caller === 'followup') {
            $diagnoses = isset($this->filters['service_diagnosis'])? $this->filters['service_diagnosis']: null;
        } else {
            $diagnoses = isset($this->filters['custom_diagnosis'])? $this->filters['custom_diagnosis']: null;
        }
        $command_principal = Yii::app()->db->createCommand()
            ->select('e.patient_id as patient_id', 'DISTINCT')
            ->from('episode e')
            ->where('e.disorder_id IS NOT NULL')
            ->leftJoin('patient p', 'p.id = e.patient_id')
            ->andWhere('e.deleted = 0')
            ->andWhere('p.deleted = 0');

        $command_secondary = Yii::app()->db->createCommand()
            ->select('sd.patient_id as patient_id', 'DISTINCT')
            ->from('secondary_diagnosis sd')
            ->leftJoin('patient p', 'p.id = sd.patient_id')
            ->where('sd.disorder_id IS NOT NULL')
            ->andWhere('p.deleted = 0');

        if ($eye_side == 'left') {
            $command_principal->andWhere('e.eye_id IN (1,3)');
            $command_secondary->andWhere('sd.eye_id IN (1,3)');
        } elseif ($eye_side == 'right') {
            $command_principal->andWhere('e.eye_id IN (2,3)');
            $command_secondary->andWhere('sd.eye_id IN (2,3)');
        }
        if (isset($diagnoses)) {
            if (is_array($diagnoses)) {
                $command_principal->andWhere('e.disorder_id IN ('.implode(",", $diagnoses).')');
                $command_secondary->andWhere('sd.disorder_id IN ('.implode(",", $diagnoses).')');
            } else {
                $command_principal->andWhere('e.disorder_id IN ('.$diagnoses.')');
                $command_secondary->andWhere('sd.disorder_id IN ('.$diagnoses.')');
            }
        }
        return $command_secondary->union($command_principal->getText());
    }
    public function getFollowUps($subspecialty_id, $start_date = null, $end_date = null, $diagnosis = null, $surgeon_id = null)
    {
        $followup_patient_list = array(
            'overdue' => array(),
            'coming' => array(),
            'waiting' => array(),
        );
        $followup_csv_data = array(
            'overdue' => array(),
            'coming' => array(),
            'waiting' => array(),
        );
        // extract out the query in the foreach loop
        // and integrate them into the following query
        // function call extracted: findByPK, checkPatientWorklist...
        // use the column value instead the object from findByPk within the loop
        $followup_elements_command = Yii::app()->db->createCommand()
            ->select("
                e.id as event_id,
                p.id as patient_id,
                UNIX_TIMESTAMP(e.event_date) as event_date,
                UNIX_TIMESTAMP(DATE_ADD(event_date, INTERVAL IF(period.name = 'weeks', 7 ,IF( period.name = 'months', 30, IF(period.name = 'years', 365, 1)))*eoc_entry.followup_quantity DAY)) as due_date,
                CAST(DATEDIFF(DATE_ADD(event_date, INTERVAL IF(period.name = 'weeks', 7 ,IF( period.name = 'months', 30, IF(period.name = 'years', 365, 1)))*eoc_entry.followup_quantity DAY),current_date())/7 AS INT) as weeks,
                MAX(UNIX_TIMESTAMP(w.start)) as start
            ")
            ->from("event e")
            ->leftjoin("episode e2", "e.episode_id = e2.id")
            ->leftjoin("patient p", "p.id = e2.patient_id")
            ->leftjoin("event_type e3", "e3.id = e.event_type_id")
            ->leftjoin("firm f", "e2.firm_id = f.id")
            ->leftjoin("service_subspecialty_assignment ssa", "ssa.id = f.service_subspecialty_assignment_id")
            ->leftjoin("et_ophciexamination_clinicoutcome eoc", "eoc.event_id = e.id")
            ->leftjoin("ophciexamination_clinicoutcome_entry eoc_entry", "eoc_entry.element_id = eoc.id")
            ->leftjoin("period", "period.id = eoc_entry.followup_period_id")
            ->leftjoin("worklist_patient wp", "p.id = wp.patient_id")
            ->leftjoin("worklist w", "wp.worklist_id = w.id")
            ->where("p.deleted <> 1 and e.deleted <> 1 and e2.deleted <> 1")
            ->andWhere("lower(e3.name) like lower('%examination%')")
            ->andWhere("
                e.event_date = (
                    select MAX(e4.event_date) from event e4
                    left join episode e5 on e4.episode_id = e5.id
                    left join patient p2 on e5.patient_id = p2.id
                    left join event_type e6 ON e6.id = e4.event_type_id
                    WHERE p2.id = p.id and e4.deleted = 0 and e5.deleted = 0 
                    and lower(e3.name) like lower('%examination%')
                )
            ")
            ->andWhere("eoc.id is not null")
            ->andWhere("eoc_entry.followup_period_id is not null")
            ->group("p.id");
        // extract out the query in the foreach loop
        // and integrate them into the following query
        // use the column value instead the object from findByPk within the loop
        $referral_document_command = Yii::app()->db->createCommand()
            ->select("
                e.id as event_id,
                p.id as patient_id,
                UNIX_TIMESTAMP(e.event_date) as event_date,
                MIN(UNIX_TIMESTAMP(wp.when)) as 'when'
            ")
            ->from("event e")
            ->leftjoin("episode e2", "e.episode_id = e2.id")
            ->leftjoin("patient p", "p.id = e2.patient_id")
            ->leftjoin("event_type e3", "e3.id = e.event_type_id")
            ->leftjoin("firm f", "e2.firm_id = f.id")
            ->leftjoin("service_subspecialty_assignment ssa", "ssa.id = f.service_subspecialty_assignment_id")
            ->leftjoin("et_ophcodocument_document eod", "e.id = eod.event_id")
            ->leftjoin("ophcodocument_sub_types ost", "eod.event_sub_type = ost.id")
            ->leftjoin("worklist_patient wp", "p.id = wp.patient_id")
            ->leftjoin("worklist w", "wp.worklist_id = w.id")
            ->where("ost.name = 'Referral Letter'")
            ->andWhere("p.deleted <> 1 and e.deleted <> 1 and e2.deleted <> 1")
            ->andWhere("lower(e3.name) like lower('%document%')")
            ->andWhere("
                e.event_date = (
                    select MAX(e4.event_date) from event e4
                    left join episode e5 on e4.episode_id = e5.id
                    left join patient p2 on e5.patient_id = p2.id
                    left join event_type e6 ON e6.id = e4.event_type_id
                WHERE p2.id = p.id and e4.deleted = 0 and e5.deleted = 0 
                and lower(e3.name) like lower('%document%')
                )
            ")
            ->group('p.id');
        $queryConditions = array('and');
        $params = array();
        if ($diagnosis) {
            $command_filtered_patients_by_diagnosis = Yii::app()->db->createCommand()
                ->select('dp.patient_id', 'distinct')
                ->from('('.$this->queryDiagnosesFilteredPatientListCommand(null, 'followup')->getText().') AS dp');
            $queryConditions[] = 'p.id IN ('.$command_filtered_patients_by_diagnosis->getText().')';
        }
        if ($surgeon_id) {
            $queryConditions[] = 'e.created_user_id = :surgeon_id';
            $params['surgeon_id'] = $surgeon_id;
        }
        if ($subspecialty_id) {
            $queryConditions[] = 'ssa.subspecialty_id = :subspecialty_id';
            $params['subspecialty_id'] = $subspecialty_id;
        }
        $followup_elements = $followup_elements_command->andWhere($queryConditions, $params)->queryAll();
        $current_time = time();
        foreach ($followup_elements as $followup_item) {
            /* Calculate the coming and overdue followups */
            $event_time = $followup_item['event_date'];
            if ( ($start_date && $event_time < $start_date) ||
            ($end_date && $event_time > $end_date)) {
                continue;
            }

            $latest_worklist_time = isset($followup_item['start']) ? $followup_item['start'] : null;

            $latest_time = isset($latest_worklist_time)? max($event_time, $latest_worklist_time):$event_time;

            $due_time = $followup_item['due_date'];

            if ( $followup_item['weeks'] <= 0) {
                if ($latest_time > $event_time) {
                    continue;
                }
                //Follow up is overdue
                $over_weeks = -$followup_item['weeks'];
                if ($over_weeks <= self::FOLLOWUP_WEEK_LIMITED) {
                    $followup_csv_data['overdue'][] =
                        array(
                            'patient_id'=>$followup_item['patient_id'],
                            'weeks'=>$over_weeks,
                        );
                    if (!array_key_exists($over_weeks, $followup_patient_list['overdue'])) {
                        $followup_patient_list['overdue'][$over_weeks][] = $followup_item['patient_id'];
                    } else {
                        $followup_patient_list['overdue'][$over_weeks][] = $followup_item['patient_id'];
                    }
                }
            } else {
                if ($latest_worklist_time >$current_time && $latest_worklist_time < $due_time) {
                    continue;
                }
                $coming_weeks = $followup_item['weeks'];
                if ($coming_weeks <= self::FOLLOWUP_WEEK_LIMITED) {
                    $followup_csv_data['coming'][] =
                        array(
                            'patient_id'=>$followup_item['patient_id'],
                            'weeks'=>$coming_weeks,
                        );
                    if (!array_key_exists($coming_weeks, $followup_patient_list['coming'])) {
                        $followup_patient_list['coming'][$coming_weeks] = array($followup_item['patient_id']);
                    } else {
                        $followup_patient_list['coming'][$coming_weeks][] = $followup_item['patient_id'];
                    }
                }
            }
        }
        // absolete active record and extracted the logic out into a couple of queries
        /*
            $ticket_assignments_command combined:
            * ticket active record
            * getfollowup function in patientticket_api
            * checkPatientWorklist() in this controller, which is intended to retrieve latest worklist start date
            * getLatestExaminationEvent() in this controller
        */
        $tickets_command = Yii::app()->db->createCommand()
            ->select('
                ptt.id ticket_id,
                ptt.patient_id patient_id,
                ptt.event_id event_id,
                e.created_user_id event_owner,
                MAX(e.event_date) event_date,
                MAX(w.start) worklist_date
            ')
            ->from('patientticketing_ticket ptt')
            ->join('event e', 'e.id = ptt.event_id')
            ->leftjoin("episode e2", "e.episode_id = e2.id")
            ->leftjoin("patient p", "p.id = e2.patient_id")
            ->leftjoin("event_type et", "et.id = e.event_type_id")
            ->leftjoin("firm f", "e2.firm_id = f.id")
            ->leftjoin("service_subspecialty_assignment ssa", "ssa.id = f.service_subspecialty_assignment_id")
            ->leftjoin('worklist_patient wp', 'ptt.patient_id = wp.patient_id')
            ->leftjoin('worklist w', 'wp.worklist_id = w.id')
            ->where('LOWER(et.name) = :examination', array('examination' => 'examination'))
            ->group('ptt.patient_id, ptt.event_id');
        $tickets = $tickets_command->andWhere($queryConditions, $params)->queryAll();
        $ticket_ids = array_column($tickets, 'ticket_id');
        $ticket_assignments_command = Yii::app()->db->createCommand()
            ->select('
                ptt.id ticket_id,
                pta.id assignment_id,
                pta.assignment_date assignment_date,
                pta.details details
            ')
            ->from('patientticketing_ticket ptt')
            ->join('patientticketing_ticketqueue_assignment pta', 'ptt.id = pta.ticket_id')
            ->where(array('IN', 'ptt.id', $ticket_ids));
        $ticket_assignments = $ticket_assignments_command->queryAll();
        // as the details field in database is an json array,
        // this needs to be convert to php associated array
        $ticket_assignments = array_map(function ($item) {
            $item['details'] = json_decode($item['details'], true);
            return $item;
        }, $ticket_assignments);

        // store value outcome and use ticket id as key
        $value_outcome = array();

        // retrieve all options out
        $ticket_assignments_outcome_opts_command = Yii::app()->db->createCommand()
            ->select('*')
            ->from('patientticketing_ticketassignoutcomeoption');
        $ticket_assignments_outcome_opts = $ticket_assignments_outcome_opts_command->queryAll();

        // store options and use options id as key
        $ticket_assignments_outcome_opts_dup = array();
        foreach ($ticket_assignments_outcome_opts as $opts) {
            $ticket_assignments_outcome_opts_dup[$opts['id']] = $opts;
        }

        foreach ($ticket_assignments as $ticket_assignment) {
            if (!isset($ticket_assignment['details'])) {
                continue;
            }
            foreach ($ticket_assignment['details'] as $item) {
                if (isset($item['widget_name']) && $item['widget_name'] === 'TicketAssignOutcome') {
                    if (isset($item['value']['outcome'])) {
                        $inner_val_outcome = $item['value']['outcome'];
                        if ($ticket_assignments_outcome_opts_dup[$inner_val_outcome]) {
                            if ((int)$ticket_assignments_outcome_opts_dup[$inner_val_outcome]['followup'] === 1) {
                                $item['value']['assignment_date'] = $ticket_assignment['assignment_date'];
                                $value_outcome[$ticket_assignment['ticket_id']] = $item['value'];
                            }
                        }
                    }
                }
            }
        }

        foreach ($tickets as $ticket) {
            $ticket_followup = isset($value_outcome[$ticket['ticket_id']]) ? $value_outcome[$ticket['ticket_id']] : false;

            if ($ticket_followup) {
                $current_event = $ticket['event_date'];
                $assignment_time = strtotime($ticket_followup['assignment_date']);
                if (($start_date && $assignment_time < $start_date) ||
                    ($end_date && $assignment_time > $end_date)) {
                    continue;
                }

                if (isset($this->surgeon, $current_event)) {
                    if ($this->surgeon !== $ticket['event_owner']) {
                        continue;
                    }
                }
                $current_patient_id = $ticket['patient_id'];
                if ($diagnosis) {
                    $current_patient_diagnoses = $this->queryAllDiagnosisForPatient($current_patient_id);
                    if (!in_array($diagnosis, $current_patient_diagnoses)) {
                        continue;
                    }
                }
                $latest_worklist_time = strtotime($ticket['worklist_date']);
                $latest_examination = strtotime($ticket['event_date']);
                if (isset($latest_examination)) {
                    $latest_examination_date = strtotime($latest_examination);
                } else {
                    $latest_examination_date = null;
                }

                $latest_time = null;

                if (isset($latest_worklist_time)) {
                    if (isset($latest_examination_date)) {
                        $latest_time = max($latest_examination_date, $latest_worklist_time);
                    } else {
                        $latest_time = $latest_worklist_time;
                    }
                } else {
                    if (isset($latest_examination_date)) {
                        $latest_time = $latest_examination_date;
                    }
                }

                $quantity = $ticket_followup['followup_quantity'];
                if ($quantity > 0) {
                    $period_date = $quantity * $this->getPeriodDate($ticket_followup['followup_period']);
                    $due_time = $assignment_time + $period_date * self::DAYTIME_ONE;
                    if ($due_time < $current_time) {
                        if (!isset($latest_time) || $latest_time > $assignment_time) {
                            continue;
                        }
                        //Follow up is overdue
                        $over_weeks = (int)(($current_time - $due_time) / self::DAYTIME_ONE / self::PERIOD_WEEK);
                        if ($over_weeks <= self::FOLLOWUP_WEEK_LIMITED) {
                            $followup_csv_data['overdue'][] =
                                array(
                                    'patient_id' => $current_patient_id,
                                    'weeks' => $over_weeks,
                                );
                            if (!array_key_exists($over_weeks, $followup_patient_list['overdue'])) {
                                $followup_patient_list['overdue'][$over_weeks][] = $current_patient_id;
                            } else {
                                $followup_patient_list['overdue'][$over_weeks][] = $current_patient_id;
                            }
                        }
                    } else {
                        if ($latest_worklist_time > $current_time && $latest_worklist_time < $due_time) {
                            continue;
                        }
                        $coming_weeks = (int)(($due_time - $current_time) / self::DAYTIME_ONE / self::PERIOD_WEEK);
                        if ($coming_weeks <= self::FOLLOWUP_WEEK_LIMITED) {
                            $followup_csv_data['coming'][] =
                                array(
                                    'patient_id' => $current_patient_id,
                                    'weeks' => $coming_weeks,
                                );
                            if (!array_key_exists($coming_weeks, $followup_patient_list['coming'])) {
                                $followup_patient_list['coming'][$coming_weeks] = array($current_patient_id);
                            } else {
                                $followup_patient_list['coming'][$coming_weeks][] = $current_patient_id;
                            }
                        }
                    }
                }
            }
        }
        /* Get the waiting follow up data, uses Document event with referral letter type and worklist time
        To calculate how long a patient will wait frm the date of referral to the date assigned in a worklist*/
        $referral_document_elements = $referral_document_command->andWhere($queryConditions, $params)->queryAll();
        foreach ($referral_document_elements as $referral_element) {
            $current_referral_date = $referral_element['event_date'];
            if ( ($start_date && $current_referral_date < $start_date) ||
                ($end_date && $current_referral_date > $end_date)) {
                continue;
            }
            $current_patient_on_worklist = $referral_element['when'];
            if (isset($current_patient_on_worklist) && !empty($current_patient_on_worklist)) {
                $appointment_time = $referral_element['when'];
                if ($appointment_time >= $current_referral_date) {
                    $waiting_time = ceil(($appointment_time - $current_referral_date) / self::WEEKTIME);
                }
            } else {
                $current_time = time();
                if ($current_time > $current_referral_date) {
                    $waiting_time = ceil(($current_time - $current_referral_date)/ self::WEEKTIME);
                }
            }
            if (isset($waiting_time) && $waiting_time <= self::FOLLOWUP_WEEK_LIMITED) {
                $followup_csv_data['waiting'][] =
                    array(
                        'patient_id'=>$referral_element['patient_id'],
                        'weeks'=>$waiting_time,
                    );
                if (! isset($followup_patient_list['waiting'][$waiting_time])) {
                    $followup_patient_list['waiting'][$waiting_time]= array();
                }
                $followup_patient_list['waiting'][$waiting_time][] = $referral_element['patient_id'];
            }
        }
        ksort($followup_patient_list['waiting']);
        ksort($followup_patient_list['overdue']);
        ksort($followup_patient_list['coming']);
        // to get total number
        $data_summary = array();
        $data_summary['waiting'] = array_reduce($followup_patient_list['waiting'], function ($a, $b) {
            $a += count($b);
            return $a;
        }, 0);
        $data_summary['overdue'] = array_reduce($followup_patient_list['overdue'], function ($a, $b) {
            $a += count($b);
            return $a;
        }, 0);
        $data_summary['coming'] = array_reduce($followup_patient_list['coming'], function ($a, $b) {
            $a += count($b);
            return $a;
        }, 0);
        $report_type = null;
        $report_type = $report_type === null ? 'overdue' : $report_type;

        if (Yii::app()->request->isAjaxRequest) {
            $report_type = Yii::app()->request->getParam('report');
        }
        $report_type = $report_type === null ? 'overdue' : $report_type;
        return array(
            'plot_data'=>$followup_patient_list[$report_type],
            'csv_data'=>$followup_csv_data[$report_type],
            'data_sum'=> $data_summary,
        );
    }

    protected function queryAllDiagnosisForPatient($patient_id)
    {
        $command = Yii::app()->db->createCommand()
            ->select('od.disorder_id AS disorder_id')
            ->from('episode e')
            ->leftJoin('event e2', 'e2.episode_id = e.id')
            ->leftJoin('et_ophciexamination_diagnoses eod', 'eod.event_id = e2.id')
            ->leftJoin('ophciexamination_diagnosis od', 'eod.id = od.element_diagnoses_id')
            ->where('od.id IS NOT NULL')
            ->andWhere('e.patient_id =:patient_id', array(':patient_id'=>$patient_id))
            ->group('od.id');
        $diagnoses = $command->queryAll();
        $return_data = array();
        foreach ($diagnoses as $diagnosis) {
            if (!in_array($diagnosis['disorder_id'], $return_data)) {
                $return_data[] = $diagnosis['disorder_id'];
            }
        }
        return $return_data;
    }
    /**
     * @param $events array
     * @return mixed
     * This function is writen for the use of php usort() function
     */
    protected function sortEventByEventDate($events)
    {
        for ($i=0; $i<count($events); $i++) {
            $val = $events[$i];
            $j = $i-1;
            while ($j>=0 && $events[$j]->event_date > $val->event_date) {
                $events[$j+1] = $events[$j];
                $j--;
            }
            $events[$j+1] = $val;
        }
        return $events;
    }

    protected function checkPatientWorklist($patient_id)
    {
        $latest_date = null;
        $PatientWorklists = WorklistPatient::model()->findAllByAttributes(array('patient_id' => $patient_id));
        foreach ($PatientWorklists as $item) {
            if ($latest_date < strtotime($item->worklist->start)) {
                $latest_date = strtotime($item->worklist->start);
            }
        }
        return $latest_date;
    }

    /**
     * Check user roles, user with "Service Manager" role can view all the data for all surgeons.
     * Otherwise, it can only view the data created by himself.
     */
    protected function checkAuth()
    {
        if (Yii::app()->authManager->isAssigned('Service Manager', Yii::app()->user->id)) {
            $this->surgeon = null;
        } else {
            $this->surgeon = Yii::app()->user->id;
        }
    }

    /**
     * @return array
     */
    protected function getClinicalSpecialityData($speciality_name)
    {
        // TODO
        // $this->checkAuth();
        $subspecialty_id = $this->getSubspecialtyID($speciality_name);
        $this->filters = array(
            'date_from' => 0,
            'date_to' => strtotime(date("Y-m-d H:i:s")),
        );
        $this->custom_csv_data = array();
        $follow_patient_list = $this->getFollowUps($subspecialty_id);
        $common_ophthalmic_disorders = $this->getCommonDisorders($subspecialty_id, true);
        $disorder_data = $this->getDisorders($subspecialty_id);

        $clinical_data = array(
            'title' => 'Disorders Section',
            'x' => $disorder_data['x'],
            'y' => $disorder_data['y'],
            'text' => $disorder_data['text'],
            'customdata' =>$disorder_data['customdata'],
            'csv_data'=>$disorder_data['csv_data'],
        );

        if ($speciality_name === 'Glaucoma') {
            list($left_iop_list, $right_iop_list) = $this->getCustomIOP($speciality_name, $this->surgeon);
            list($left_va_list, $right_va_list) = $this->getCustomVA($speciality_name, $this->surgeon);
            $second_list_name = '_iop_list';
        } else {
            list($left_va_list, $right_va_list) = $this->getCustomVA($speciality_name, $this->surgeon);
            list($left_crt_list, $right_crt_list) = $this->getCustomCRT($speciality_name, $this->surgeon);
            $second_list_name = '_crt_list';
        }


        if (!isset($this->current_user)) {
            $this->current_user = User::model()->findByPk(Yii::app()->user->id);
        }
        if (isset($this->surgeon)) {
            $user_list = null;
        } else {
            $user_list = User::model()->findAll();
        }
        $custom_data = array();
        foreach (['left', 'right'] as $side) {
            $custom_data[] = array(
                array(
                    'name' => 'VA',
                    'x' => array_keys(${$side . '_va_list'}),
                    'y' => array_map(
                        function ($item) {
                            return $item['average'];
                        },
                        array_values(${$side . '_va_list'})
                    ),
                    'customdata' => array_map(
                        function ($item) {
                            return $item['patients'];
                        },
                        array_values(${$side . '_va_list'})
                    ),
                    'error_y' => array(
                        'type' => 'data',
                        'array' => array_map(
                            function ($item) {
                                return $item['SD'];
                            },
                            array_values(${$side . '_va_list'})
                        ),
                        'visible' => true,
                        'color' => '#aaa',
                        'thickness' => 1
                    ),
                    'hoverinfo' => 'text',
                    'hovertext' => array_map(
                        function ($item) {
                            return " Mean: " . $item['average'] . "<br> SD: " . $item['SD'] ."<br> N: ".count($item['patients']);
                        },
                        array_values(${$side . '_va_list'})
                    ),
                ),
                array(
                    'name' => $speciality_name === 'Glaucoma' ? 'IOP' : 'CRT',
                    'yaxis' => 'y2',
                    'x' => array_keys(${$side . $second_list_name}) ,
                    'y' => array_map(
                        function ($item) {
                            return $item['average'];
                        },
                        array_values(${$side . $second_list_name})
                    ),
                    'customdata' => array_map(
                        function ($item) {
                            return $item['patients'];
                        },
                        array_values(${$side . $second_list_name})
                    ),
                    'error_y' => array(
                        'type' => 'data',
                        'array' => array_map(
                            function ($item) {
                                return $item['SD'];
                            },
                            array_values(${$side . $second_list_name})
                        ),
                        'visible' => true,
                        'color' => '#aaa',
                        'thickness' => 1
                    ),
                    'hoverinfo' => 'text',
                    'hovertext' => array_map(
                        function ($item) {
                            return " Mean: " . $item['average'] . "<br> SD: " . $item['SD'] ."<br> N: ".count($item['patients']);
                        },
                        array_values(${$side . $second_list_name})
                    ),
                )
            );
        }
        $custom_data['csv_data'] = $this->custom_csv_data;
        return array($follow_patient_list, $common_ophthalmic_disorders,$user_list, $custom_data,$clinical_data);
    }
}
