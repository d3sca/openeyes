<?php
/**
 * (C) Apperta Foundation, 2023
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2023, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

namespace OE\factories\models;

use OE\factories\ModelFactory;

use Medication;
use MedicationSet;

class MedicationSetItemFactory extends ModelFactory
{
    public function definition(): array
    {
        return [
            'medication_id' => Medication::factory(),
            'medication_set_id' => MedicationSet::factory()
        ];
    }

    public function forMedication($medication)
    {
        return $this->state([
            'medication_id' => $medication
        ]);
    }

    public function forMedicationSet($medication_set)
    {
        return $this->state([
            'medication_set_id' => $medication_set
        ]);
    }
}