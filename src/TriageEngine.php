<?php
declare(strict_types=1);

/**
 * Deterministic triage classifier — Red / Orange / Yellow / Green.
 *
 * IMPORTANT: The thresholds below are a DRAFT working implementation of
 * PROJECT_PLAN.md Section 6, not a certified clinical rubric. Route exact
 * cutoffs through an actual clinician before relying on this for real
 * patient-safety decisions.
 *
 * Expected shapes (see public/js/intake.js for how these are built):
 *
 * $ros = [
 *   'general_symptoms'  => ['yes' => bool, 'explain' => string],  // Q2
 *   'cardiopulmonary'   => ['yes' => bool, 'explain' => string],  // Q3
 *   'gastrointestinal'  => ['yes' => bool, 'explain' => string],  // Q4
 *   'urinary_metabolic' => ['yes' => bool, 'explain' => string],  // Q5
 *   'genitourinary'     => ['yes' => bool, 'explain' => string],  // Q6
 *   'musculoskeletal'   => ['yes' => bool, 'explain' => string],  // Q8
 *   'female_health'     => ['last_menstrual_period' => ?string, 'first_menstrual_period' => ?string, 'number_of_pregnancies' => ?int], // Q7, informational only
 * ]
 *
 * $pmh = ['cancer' => bool, 'allergies' => bool, 'diabetes_mellitus' => bool,
 *         'hypertension' => bool, 'heart_disease' => bool, 'stroke' => bool,
 *         'bronchial_asthma' => bool, 'copd' => bool, 'tuberculosis' => bool,
 *         'others' => bool, 'others_specify' => string, 'none' => bool]
 *
 * $vitals = ['bp_systolic' => ?float, 'bp_diastolic' => ?float, 'heart_rate' => ?float,
 *            'resp_rate' => ?float, 'temperature_c' => ?float, 'weight_kg' => ?float,
 *            'height_in' => ?float, 'bmi' => ?float, 'visual_acuity' => ?string,
 *            'blood_type' => ?string, 'spo2' => ?float (optional, not on the paper form,
 *            supported here for future pulse-oximeter integration), 'pediatric' => ?array
 *            (length_in, head_circumference_in, muac_in, waist_in, hip_in, limbs_in —
 *            only present for clients under 24 months, informational only)]
 *
 * height_in/weight_kg/bmi are informational only and are not read by classify()
 * below; they don't affect the triage color.
 */
class TriageEngine
{
    public static function classify(
        array $ros,
        array $pmh,
        array $vitals,
        string $generalSurvey,
        string $chiefComplaint = ''
    ): array {
        // --- RED ------------------------------------------------------
        if ($generalSurvey === 'altered_sensorium') {
            return ['color' => 'red', 'reason' => 'Altered sensorium'];
        }

        if (self::hasDangerVitals($vitals)) {
            return ['color' => 'red', 'reason' => 'Vitals in danger range'];
        }

        $cardiopulmonary = $ros['cardiopulmonary']['yes'] ?? false;
        if ($cardiopulmonary && self::hasMildlyAbnormalVitals($vitals)) {
            return ['color' => 'red', 'reason' => 'Cardiopulmonary symptom with abnormal vitals'];
        }

        // --- ORANGE -----------------------------------------------------
        if (self::anyRosYes($ros)) {
            return ['color' => 'orange', 'reason' => 'Yes on one or more Review of Systems questions'];
        }

        if (self::hasChronicCondition($pmh) && trim($chiefComplaint) !== '') {
            return ['color' => 'orange', 'reason' => 'Known chronic condition with a new complaint'];
        }

        if (self::hasMildlyAbnormalVitals($vitals)) {
            return ['color' => 'orange', 'reason' => 'Mildly abnormal vitals'];
        }

        // --- YELLOW -------------------------------------------------------
        if (self::hasChronicCondition($pmh)) {
            return ['color' => 'yellow', 'reason' => 'Chronic condition, routine monitoring'];
        }

        // --- GREEN (default) -----------------------------------------------
        return ['color' => 'green', 'reason' => 'No red/orange/yellow criteria met'];
    }

    private static function hasDangerVitals(array $vitals): bool
    {
        $sys  = self::num($vitals, 'bp_systolic');
        $dia  = self::num($vitals, 'bp_diastolic');
        $hr   = self::num($vitals, 'heart_rate');
        $rr   = self::num($vitals, 'resp_rate');
        $temp = self::num($vitals, 'temperature_c');
        $spo2 = self::num($vitals, 'spo2');

        if ($sys !== null && ($sys < 90 || $sys > 180)) return true;
        if ($dia !== null && $dia > 110) return true;
        if ($hr !== null && ($hr < 50 || $hr > 120)) return true;
        if ($rr !== null && ($rr < 10 || $rr > 30)) return true;
        if ($temp !== null && ($temp < 35 || $temp > 39.5)) return true;
        if ($spo2 !== null && $spo2 < 90) return true;

        return false;
    }

    private static function hasMildlyAbnormalVitals(array $vitals): bool
    {
        $sys  = self::num($vitals, 'bp_systolic');
        $dia  = self::num($vitals, 'bp_diastolic');
        $hr   = self::num($vitals, 'heart_rate');
        $rr   = self::num($vitals, 'resp_rate');
        $temp = self::num($vitals, 'temperature_c');

        if ($sys !== null && $sys >= 140 && $sys <= 180) return true;
        if ($dia !== null && $dia >= 90 && $dia <= 110) return true;
        if ($hr !== null && $hr >= 100 && $hr <= 120) return true;
        if ($rr !== null && $rr >= 20 && $rr <= 30) return true;
        if ($temp !== null && $temp >= 37.8 && $temp <= 39.5) return true;

        return false;
    }

    private static function num(array $vitals, string $key): ?float
    {
        if (!isset($vitals[$key]) || $vitals[$key] === '' || $vitals[$key] === null) {
            return null;
        }
        return (float) $vitals[$key];
    }

    private static function anyRosYes(array $ros): bool
    {
        foreach ($ros as $key => $value) {
            if ($key === 'female_health') {
                continue; // informational, not a yes/no screening question
            }
            if (is_array($value) && !empty($value['yes'])) {
                return true;
            }
        }
        return false;
    }

    private static function hasChronicCondition(array $pmh): bool
    {
        $conditionKeys = [
            'cancer', 'allergies', 'diabetes_mellitus', 'hypertension',
            'heart_disease', 'stroke', 'bronchial_asthma', 'copd',
            'tuberculosis', 'others',
        ];
        foreach ($conditionKeys as $key) {
            if (!empty($pmh[$key])) {
                return true;
            }
        }
        return false;
    }
}
