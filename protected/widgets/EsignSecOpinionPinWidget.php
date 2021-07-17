<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2021
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2021, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class EsignSecOpinionPinWidget extends EsignField
{
    private const ROLE = 'second_opinion';
    private const FIELD = "consultant_id";
    public $unique_id;

    public $action = 'getSignatureByPin';

    public function getLabel($consent_type)
    {
        $labels = array(
            'second_opinion' => 'Second opinion',
        );

        return $labels[$consent_type];
    }

    public function isSigned()
    {
        return $this->element->{self::FIELD};
    }

    /**
     * Render the table-row
     */
    public function run()
    {
        $user = Yii::app()->session['user'];
        $data = array(
            'role' => self::ROLE,
            'logged_user_name' => $user->getFullName(),
        );
        $this->isSigned();
        $this->render('EsignPin', $data);
    }
}