<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

$user = Auth::user();
if (!$user) {
    header('Location: login.html');
    exit;
}

$missionId = (int) ($_GET['mission_id'] ?? 0);
if (!$missionId) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FPE Triage System — Encounter Intake</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="has-sidebar">
  <?php
    $navActive = 'missions';
    $sidebarMissionId = $missionId;
    $sidebarMissionContext = 'intake';
    require __DIR__ . '/partials/sidebar.php';
  ?>

  <main class="page">
    <div id="mission-banner" class="card mission-banner">Loading mission…</div>

    <div id="closed-notice" class="card notice-card" hidden>
      <h2>This mission is closed</h2>
      <p>Intake has ended for this mission, so new encounters can no longer be logged here. Review Red and Orange cases from the mission workspace's Encounters tab, or open the printable report.</p>
      <a class="btn btn-primary" href="dashboard.php?mission_id=<?= $missionId ?>">Go to mission workspace</a>
    </div>

    <!-- Triage result, shown after a successful submit -->
    <section id="result-panel" hidden>
      <div id="result-card" class="result-card">
        <div id="result-icon" class="result-icon">&#9679;</div>
        <div class="result-body">
          <p id="result-title" class="result-title"></p>
          <p id="result-reason" class="result-reason"></p>
          <p id="result-alert" class="result-alert" hidden>Alert the doctor now.</p>
        </div>
        <button id="log-another-btn" class="btn btn-primary">Log another encounter</button>
      </div>
    </section>

    <div id="wizard">
      <div class="stepper" id="stepper-track">
        <div class="stepper-seg is-current" data-seg="1"></div>
        <div class="stepper-seg" data-seg="2"></div>
        <div class="stepper-seg" data-seg="3"></div>
        <div class="stepper-seg" data-seg="4"></div>
        <div class="stepper-seg" data-seg="5"></div>
      </div>
      <div class="stepper-labels" id="stepper-labels">
        <span class="is-current" data-lbl="1">Patient</span>
        <span data-lbl="2">Screening</span>
        <span data-lbl="3">History</span>
        <span data-lbl="4">Exam</span>
        <span data-lbl="5">Review</span>
      </div>

      <!-- STEP 1 — PATIENT -->
      <section class="step-panel card" id="step-1" data-step="1">
        <p class="step-title">Patient</p>
        <p class="step-subtitle">Client profile for this encounter</p>

        <label>Visit type</label>
        <div class="chip-toggle small">
          <input type="radio" id="visit_walk_in" name="visit_type" value="walk_in" checked>
          <label class="chip-label" for="visit_walk_in">Walk-in</label>
          <input type="radio" id="visit_appt" name="visit_type" value="appointment">
          <label class="chip-label" for="visit_appt">With appointment</label>
        </div>

        <div id="patient-search-block">
          <label for="patient-search">Search existing patient (case #, PhilHealth ID, or last name)</label>
          <input type="text" id="patient-search" placeholder="Start typing…">
          <ul id="patient-search-results" class="search-results"></ul>
          <p class="hint">Or <button type="button" id="show-new-patient-btn" class="link-btn">register a new patient</button></p>
        </div>

        <form id="new-patient-form" hidden>
          <label for="p_philhealth_id">PhilHealth ID</label>
          <input type="text" id="p_philhealth_id">

          <div class="grid-2">
            <div>
              <label for="p_last_name">Last name</label>
              <input type="text" id="p_last_name">
            </div>
            <div>
              <label for="p_first_name">First name</label>
              <input type="text" id="p_first_name">
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label for="p_middle_name">Middle name</label>
              <input type="text" id="p_middle_name">
            </div>
            <div>
              <label for="p_extension_name">Extension name</label>
              <input type="text" id="p_extension_name" placeholder="Jr., Sr., III">
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label for="p_dob">Date of birth</label>
              <input type="date" id="p_dob">
            </div>
            <div>
              <label for="p_sex">Sex</label>
              <select id="p_sex">
                <option value="">—</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Save patient</button>
          <p id="new-patient-error" class="error" hidden></p>
        </form>

        <div id="selected-patient" class="selected-patient" hidden>
          <div class="selected-patient-info">
            <span id="selected-patient-avatar"></span>
            <div>
              <div class="selected-patient-name" id="selected-patient-name"></div>
              <div class="selected-patient-case" id="selected-patient-case"></div>
            </div>
          </div>
          <button type="button" id="change-patient-btn" class="link-btn">Change</button>
        </div>

        <div class="step-nav">
          <span></span>
          <button type="button" class="btn btn-primary" id="step1-next" disabled>Next: screening</button>
        </div>
      </section>

      <!-- STEP 2 — SCREENING (Review of Systems) -->
      <section class="step-panel card" id="step-2" data-step="2" hidden>
        <p class="step-title">Screening</p>
        <p class="step-subtitle">If the answer is Yes to any question, the client needs to consult a doctor.</p>

        <label for="chief_complaint">Chief complaint <span class="required-mark">*</span></label>
        <textarea id="chief_complaint" rows="2"></textarea>

        <div class="ros-item question-card" data-ros-key="general_symptoms">
          <div class="question-row">
            <span class="question-number">1</span>
            <p class="question-text">Loss of appetite, lack of sleep, unexplained weight loss, feeling down/depressed, fever, headache, memory loss, blurring of vision, or hearing loss?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_general_symptoms" value="yes" id="ros_general_symptoms_yes"><label class="chip-label" for="ros_general_symptoms_yes">Yes</label>
            <input type="radio" name="ros_general_symptoms" value="no" id="ros_general_symptoms_no"><label class="chip-label" for="ros_general_symptoms_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <div class="ros-item question-card" data-ros-key="cardiopulmonary">
          <div class="question-row">
            <span class="question-number">2</span>
            <p class="question-text">Cough/colds, chest pain, palpitations, or difficulty breathing?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_cardiopulmonary" value="yes" id="ros_cardiopulmonary_yes"><label class="chip-label" for="ros_cardiopulmonary_yes">Yes</label>
            <input type="radio" name="ros_cardiopulmonary" value="no" id="ros_cardiopulmonary_no"><label class="chip-label" for="ros_cardiopulmonary_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <div class="ros-item question-card" data-ros-key="gastrointestinal">
          <div class="question-row">
            <span class="question-number">3</span>
            <p class="question-text">Abdominal pain, vomiting, change in bowel movement, rectal bleeding, or bloody/tarry stools?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_gastrointestinal" value="yes" id="ros_gastrointestinal_yes"><label class="chip-label" for="ros_gastrointestinal_yes">Yes</label>
            <input type="radio" name="ros_gastrointestinal" value="no" id="ros_gastrointestinal_no"><label class="chip-label" for="ros_gastrointestinal_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <div class="ros-item question-card" data-ros-key="urinary_metabolic">
          <div class="question-row">
            <span class="question-number">4</span>
            <p class="question-text">Frequent urination, frequent eating, or frequent intake of fluids?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_urinary_metabolic" value="yes" id="ros_urinary_metabolic_yes"><label class="chip-label" for="ros_urinary_metabolic_yes">Yes</label>
            <input type="radio" name="ros_urinary_metabolic" value="no" id="ros_urinary_metabolic_no"><label class="chip-label" for="ros_urinary_metabolic_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <div class="ros-item question-card" data-ros-key="genitourinary">
          <div class="question-row">
            <span class="question-number">5</span>
            <p class="question-text">Pain/discomfort on urination, dribbling, pain during/after sex, blood in urine, or foul-smelling genital discharge?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_genitourinary" value="yes" id="ros_genitourinary_yes"><label class="chip-label" for="ros_genitourinary_yes">Yes</label>
            <input type="radio" name="ros_genitourinary" value="no" id="ros_genitourinary_no"><label class="chip-label" for="ros_genitourinary_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <div id="female-health-block" hidden>
          <h4>For females only</h4>
          <div class="grid-2">
            <div>
              <label for="lmp">Last menstrual period</label>
              <input type="date" id="lmp">
            </div>
            <div>
              <label for="fmp">First menstrual period</label>
              <input type="date" id="fmp">
            </div>
          </div>
          <label for="num_pregnancies">Number of pregnancies</label>
          <input type="number" id="num_pregnancies" min="0">
        </div>

        <div class="ros-item question-card" data-ros-key="musculoskeletal">
          <div class="question-row">
            <span class="question-number">6</span>
            <p class="question-text">Muscle spasm, tremors, weakness; muscle/joint pain, stiffness, or limitation of movement?</p>
          </div>
          <div class="chip-toggle">
            <input type="radio" name="ros_musculoskeletal" value="yes" id="ros_musculoskeletal_yes"><label class="chip-label" for="ros_musculoskeletal_yes">Yes</label>
            <input type="radio" name="ros_musculoskeletal" value="no" id="ros_musculoskeletal_no"><label class="chip-label" for="ros_musculoskeletal_no">No</label>
          </div>
          <textarea class="ros-explain" placeholder="If yes, please explain" hidden></textarea>
          <p class="inline-error" hidden>Choose Yes or No to continue</p>
        </div>

        <p id="step2-error" class="error" hidden></p>
        <div class="step-nav">
          <button type="button" class="btn btn-secondary" data-back="1">Back</button>
          <button type="button" class="btn btn-primary" id="step2-next">Next: history</button>
        </div>
      </section>

      <!-- STEP 3 — HISTORY -->
      <section class="step-panel card" id="step-3" data-step="3" hidden>
        <p class="step-title">History</p>
        <p class="step-subtitle">Personal/social history and past medical history</p>

        <h3>Personal / Social History</h3>
        <div class="chip-toggle small">
          <input type="checkbox" id="smokes"><label class="chip-label" for="smokes">Smokes cigar/cigarette/e-cig/vape</label>
        </div>
        <div id="smoke-years-wrap" hidden>
          <label for="smoke_years" class="hint">Number of years smoking</label>
          <input type="number" id="smoke_years" min="0">
        </div>

        <div class="chip-toggle small">
          <input type="checkbox" id="drinks_alcohol"><label class="chip-label" for="drinks_alcohol">Drinks alcohol</label>
        </div>
        <div id="alcohol-years-wrap" hidden>
          <label for="alcohol_years" class="hint">Number of years drinking</label>
          <input type="number" id="alcohol_years" min="0">
        </div>

        <h3>Past Medical History</h3>
        <div class="pmh-grid">
          <input type="checkbox" class="pmh-cb" id="pmh_cancer" data-pmh="cancer"><label class="chip-label" for="pmh_cancer">Cancer</label>
          <input type="checkbox" class="pmh-cb" id="pmh_allergies" data-pmh="allergies"><label class="chip-label" for="pmh_allergies">Allergies</label>
          <input type="checkbox" class="pmh-cb" id="pmh_diabetes" data-pmh="diabetes_mellitus"><label class="chip-label" for="pmh_diabetes">Diabetes Mellitus</label>
          <input type="checkbox" class="pmh-cb" id="pmh_hypertension" data-pmh="hypertension"><label class="chip-label" for="pmh_hypertension">Hypertension</label>
          <input type="checkbox" class="pmh-cb" id="pmh_heart" data-pmh="heart_disease"><label class="chip-label" for="pmh_heart">Heart Disease</label>
          <input type="checkbox" class="pmh-cb" id="pmh_stroke" data-pmh="stroke"><label class="chip-label" for="pmh_stroke">Stroke</label>
          <input type="checkbox" class="pmh-cb" id="pmh_asthma" data-pmh="bronchial_asthma"><label class="chip-label" for="pmh_asthma">Bronchial Asthma</label>
          <input type="checkbox" class="pmh-cb" id="pmh_copd" data-pmh="copd"><label class="chip-label" for="pmh_copd">COPD/emphysema/bronchitis</label>
          <input type="checkbox" class="pmh-cb" id="pmh_tb" data-pmh="tuberculosis"><label class="chip-label" for="pmh_tb">Tuberculosis</label>
          <input type="checkbox" id="pmh_others_cb"><label class="chip-label" for="pmh_others_cb">Others</label>
          <input type="checkbox" id="pmh_none_cb"><label class="chip-label" for="pmh_none_cb">None</label>
        </div>
        <input type="text" id="pmh_others_specify" placeholder="If Others, please specify" hidden>

        <div class="step-nav">
          <button type="button" class="btn btn-secondary" data-back="2">Back</button>
          <button type="button" class="btn btn-primary" data-forward="4">Next: exam</button>
        </div>
      </section>

      <!-- STEP 4 — VITALS & GENERAL SURVEY -->
      <section class="step-panel card" id="step-4" data-step="4" hidden>
        <p class="step-title">Vitals &amp; general survey</p>
        <p class="step-subtitle">Pertinent physical examination findings</p>

        <div class="grid-2">
          <div>
            <label for="bp_systolic">Blood pressure — systolic (mmHg)</label>
            <input type="number" id="bp_systolic">
          </div>
          <div>
            <label for="bp_diastolic">Blood pressure — diastolic (mmHg)</label>
            <input type="number" id="bp_diastolic">
          </div>
        </div>
        <div class="grid-2">
          <div>
            <label for="heart_rate">Heart rate (/min)</label>
            <input type="number" id="heart_rate">
          </div>
          <div>
            <label for="resp_rate">Respiratory rate (/min)</label>
            <input type="number" id="resp_rate">
          </div>
        </div>
        <div class="grid-2">
          <div>
            <label for="temperature_c">Temperature (°C)</label>
            <input type="number" step="0.1" id="temperature_c">
          </div>
          <div>
            <label for="visual_acuity">Visual acuity</label>
            <input type="text" id="visual_acuity" placeholder="e.g. 20/20">
          </div>
        </div>
        <div class="grid-2">
          <div>
            <label for="height_in">Height (in)</label>
            <input type="number" step="0.1" id="height_in">
          </div>
          <div>
            <label for="weight_kg">Weight (kg)</label>
            <input type="number" step="0.1" id="weight_kg">
          </div>
        </div>
        <label for="bmi">BMI (auto-calculated)</label>
        <input type="text" id="bmi" readonly>

        <label>Blood type <span class="hint" style="display:inline;">(as available)</span></label>
        <div class="chip-toggle small">
          <input type="radio" name="blood_type" value="A+" id="bt_ap"><label class="chip-label" for="bt_ap">A+</label>
          <input type="radio" name="blood_type" value="B+" id="bt_bp"><label class="chip-label" for="bt_bp">B+</label>
          <input type="radio" name="blood_type" value="AB+" id="bt_abp"><label class="chip-label" for="bt_abp">AB+</label>
          <input type="radio" name="blood_type" value="O+" id="bt_op"><label class="chip-label" for="bt_op">O+</label>
          <input type="radio" name="blood_type" value="A-" id="bt_an"><label class="chip-label" for="bt_an">A-</label>
          <input type="radio" name="blood_type" value="B-" id="bt_bn"><label class="chip-label" for="bt_bn">B-</label>
          <input type="radio" name="blood_type" value="AB-" id="bt_abn"><label class="chip-label" for="bt_abn">AB-</label>
          <input type="radio" name="blood_type" value="O-" id="bt_on"><label class="chip-label" for="bt_on">O-</label>
        </div>

        <label>General survey <span class="required-mark">*</span></label>
        <div class="chip-toggle">
          <input type="radio" name="general_survey" value="awake_alert" id="gs_awake"><label class="chip-label" for="gs_awake">Awake and alert</label>
          <input type="radio" name="general_survey" value="altered_sensorium" id="gs_altered"><label class="chip-label" for="gs_altered">Altered sensorium</label>
        </div>
        <p class="hint">No option is pre-selected — this must be answered.</p>

        <div id="pediatric-box" class="pediatric-box" hidden>
          <p class="section-label">Pediatric client (under 2 years) — shown automatically from date of birth</p>
          <div class="grid-3">
            <div>
              <label for="ped_length_in">Length (in)</label>
              <input type="number" step="0.1" id="ped_length_in">
            </div>
            <div>
              <label for="ped_head_circumference_in">Head circ. (in)</label>
              <input type="number" step="0.1" id="ped_head_circumference_in">
            </div>
            <div>
              <label for="ped_muac_in">MUAC (in)</label>
              <input type="number" step="0.1" id="ped_muac_in">
            </div>
            <div>
              <label for="ped_waist_in">Waist (in)</label>
              <input type="number" step="0.1" id="ped_waist_in">
            </div>
            <div>
              <label for="ped_hip_in">Hip (in)</label>
              <input type="number" step="0.1" id="ped_hip_in">
            </div>
            <div>
              <label for="ped_limbs_in">Limbs (in)</label>
              <input type="number" step="0.1" id="ped_limbs_in">
            </div>
          </div>
        </div>

        <p id="step4-error" class="error" hidden></p>
        <div class="step-nav">
          <button type="button" class="btn btn-secondary" data-back="3">Back</button>
          <button type="button" class="btn btn-primary" id="step4-next">Next: review</button>
        </div>
      </section>

      <!-- STEP 5 — REVIEW -->
      <section class="step-panel card" id="step-5" data-step="5" hidden>
        <p class="step-title">Review</p>
        <p class="step-subtitle">Check the details below, then save to classify this encounter.</p>

        <div id="review-content"></div>

        <p id="submit-error" class="error" hidden></p>
        <div class="step-nav">
          <button type="button" class="btn btn-secondary" data-back="4">Back</button>
          <button type="button" class="btn btn-primary" id="submit-btn">Save &amp; classify</button>
        </div>
      </section>
    </div>
  </main>

  <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
    window.MISSION_ID = <?= json_encode($missionId) ?>;
  </script>
  <script src="js/patient-avatar.js"></script>
  <script src="js/intake.js"></script>
</body>
</html>
