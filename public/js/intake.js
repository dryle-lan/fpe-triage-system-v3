// intake.js — encounter intake wizard: Patient -> Screening -> History -> Exam -> Review -> Result

let selectedPatient = null; // { id, name, sex, dob }
let currentStep = 1;
const TOTAL_STEPS = 5;

// ---------------------------------------------------------------------
// Mission banner
// ---------------------------------------------------------------------
async function loadMissionBanner() {
  const banner = document.getElementById('mission-banner');
  try {
    const res = await fetch('api/missions.php');
    const missions = await res.json();
    const mission = missions.find((m) => Number(m.id) === Number(window.MISSION_ID));

    if (!mission) {
      banner.textContent = 'Mission not found.';
      return;
    }

    banner.className = `card mission-banner${mission.status === 'closed' ? ' is-closed' : ''}`;
    banner.innerHTML = `
      <div>
        <div class="mission-banner-title">${escapeHtml(mission.site_name)}</div>
        <div class="mission-banner-sub">${escapeHtml(mission.mission_date)}</div>
      </div>
      <span class="badge badge-${mission.status}">${mission.status}</span>
    `;

    const sidebarName = document.getElementById('sidebar-mission-name');
    if (sidebarName) sidebarName.textContent = mission.site_name;

    if (mission.status !== 'open') {
      document.getElementById('wizard').hidden = true;
      document.getElementById('closed-notice').hidden = false;
      const sidebarNewEncounterLink = document.getElementById('sidebar-new-encounter-link');
      if (sidebarNewEncounterLink) sidebarNewEncounterLink.hidden = true;
    }
  } catch (err) {
    banner.textContent = 'Could not load mission info.';
  }
}

// ---------------------------------------------------------------------
// Step navigation
// ---------------------------------------------------------------------
function goToStep(n) {
  currentStep = n;

  document.querySelectorAll('.step-panel').forEach((panel) => {
    panel.hidden = Number(panel.dataset.step) !== n;
  });

  document.querySelectorAll('.stepper-seg').forEach((seg) => {
    const segNum = Number(seg.dataset.seg);
    seg.classList.toggle('is-done', segNum < n);
    seg.classList.toggle('is-current', segNum === n);
  });

  document.querySelectorAll('#stepper-labels span').forEach((lbl) => {
    lbl.classList.toggle('is-current', Number(lbl.dataset.lbl) === n);
  });

  if (n === 5) {
    buildReview();
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('[data-back]').forEach((btn) => {
  btn.addEventListener('click', () => goToStep(Number(btn.dataset.back)));
});

document.querySelectorAll('[data-forward]').forEach((btn) => {
  btn.addEventListener('click', () => goToStep(Number(btn.dataset.forward)));
});

// ---------------------------------------------------------------------
// Patient search
// ---------------------------------------------------------------------
const searchInput = document.getElementById('patient-search');
const searchResults = document.getElementById('patient-search-results');
let searchTimer = null;

searchInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  const q = searchInput.value.trim();
  if (q.length < 2) {
    searchResults.innerHTML = '';
    return;
  }
  searchTimer = setTimeout(() => runPatientSearch(q), 300);
});

async function runPatientSearch(q) {
  try {
    const res = await fetch(`api/patients.php?q=${encodeURIComponent(q)}`);
    const patients = await res.json();

    if (!res.ok || patients.length === 0) {
      searchResults.innerHTML = '<li class="no-results">No matches</li>';
      return;
    }

    searchResults.innerHTML = patients
      .map(
        (p) => `
        <li data-id="${p.id}" data-name="${escapeHtml(fullName(p))}" data-sex="${p.sex || ''}" data-dob="${p.date_of_birth || ''}" data-case="${escapeHtml(p.case_number || '')}">
          ${avatarHtml(fullName(p), 'avatar-sm')}
          <span>${escapeHtml(fullName(p))} — ${escapeHtml(p.case_number || 'no case #')}</span>
        </li>`
      )
      .join('');

    searchResults.querySelectorAll('li[data-id]').forEach((li) => {
      li.addEventListener('click', () => {
        selectPatient(li.dataset.id, li.dataset.name, li.dataset.sex, li.dataset.dob, li.dataset.case);
      });
    });
  } catch (err) {
    searchResults.innerHTML = '<li class="no-results">Search failed</li>';
  }
}

function fullName(p) {
  return [p.last_name, p.first_name].filter(Boolean).join(', ') || `Patient #${p.id}`;
}

// ---------------------------------------------------------------------
// New patient form
// ---------------------------------------------------------------------
const showNewPatientBtn = document.getElementById('show-new-patient-btn');
const newPatientForm = document.getElementById('new-patient-form');
const newPatientError = document.getElementById('new-patient-error');

showNewPatientBtn.addEventListener('click', () => {
  newPatientForm.hidden = false;
  showNewPatientBtn.hidden = true;
});

newPatientForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  newPatientError.hidden = true;

  const payload = {
    philhealth_id: document.getElementById('p_philhealth_id').value,
    last_name: document.getElementById('p_last_name').value,
    first_name: document.getElementById('p_first_name').value,
    middle_name: document.getElementById('p_middle_name').value,
    extension_name: document.getElementById('p_extension_name').value,
    date_of_birth: document.getElementById('p_dob').value,
    sex: document.getElementById('p_sex').value,
  };

  try {
    const res = await fetch('api/patients.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) {
      const message = data.errors ? Object.values(data.errors).join(', ') : data.error;
      newPatientError.textContent = message || 'Failed to save patient.';
      newPatientError.hidden = false;
      return;
    }

    const name = [payload.last_name, payload.first_name].filter(Boolean).join(', ') || `Patient #${data.id}`;
    selectPatient(data.id, name, payload.sex, payload.date_of_birth, data.case_number);
  } catch (err) {
    newPatientError.textContent = 'Could not reach the server.';
    newPatientError.hidden = false;
  }
});

function selectPatient(id, name, sex, dob, caseNumber) {
  selectedPatient = { id: Number(id), name, sex: sex || '', dob: dob || '', caseNumber: caseNumber || '' };

  document.getElementById('patient-search-block').hidden = true;
  newPatientForm.hidden = true;
  document.getElementById('selected-patient').hidden = false;
  document.getElementById('selected-patient-name').textContent = name;
  document.getElementById('selected-patient-case').textContent = caseNumber ? `Case # ${caseNumber}` : 'No case number on file';
  const avatarEl = document.getElementById('selected-patient-avatar');
  avatarEl.className = 'avatar avatar-sm';
  avatarEl.style.background = avatarColorFor(name);
  avatarEl.textContent = initialsFor(name);

  document.getElementById('female-health-block').hidden = sex !== 'F';
  updatePediatricVisibility();

  document.getElementById('step1-next').disabled = false;
}

document.getElementById('change-patient-btn').addEventListener('click', () => {
  selectedPatient = null;
  document.getElementById('selected-patient').hidden = true;
  document.getElementById('patient-search-block').hidden = false;
  showNewPatientBtn.hidden = false;
  searchInput.value = '';
  searchResults.innerHTML = '';
  document.getElementById('step1-next').disabled = true;
});

document.getElementById('step1-next').addEventListener('click', () => {
  if (!selectedPatient) return;
  goToStep(2);
});

// ---------------------------------------------------------------------
// ROS "yes" reveals the explain box; validation on Next
// ---------------------------------------------------------------------
const rosKeys = [
  'general_symptoms',
  'cardiopulmonary',
  'gastrointestinal',
  'urinary_metabolic',
  'genitourinary',
  'musculoskeletal',
];

document.querySelectorAll('.ros-item').forEach((item) => {
  const radios = item.querySelectorAll('input[type="radio"]');
  const explain = item.querySelector('.ros-explain');
  const inlineError = item.querySelector('.inline-error');
  radios.forEach((r) =>
    r.addEventListener('change', () => {
      explain.hidden = item.querySelector('input[value="yes"]:checked') === null;
      inlineError.hidden = true;
    })
  );
});

document.getElementById('step2-next').addEventListener('click', () => {
  const step2Error = document.getElementById('step2-error');
  step2Error.hidden = true;

  const chiefComplaint = document.getElementById('chief_complaint').value.trim();
  if (!chiefComplaint) {
    step2Error.textContent = 'Chief complaint is required.';
    step2Error.hidden = false;
    document.getElementById('chief_complaint').focus();
    return;
  }

  let firstInvalid = null;
  rosKeys.forEach((key) => {
    const item = document.querySelector(`.ros-item[data-ros-key="${key}"]`);
    const checked = item.querySelector('input[type="radio"]:checked');
    const inlineError = item.querySelector('.inline-error');
    if (!checked) {
      inlineError.hidden = false;
      if (!firstInvalid) firstInvalid = item;
    } else {
      inlineError.hidden = true;
    }
  });

  if (firstInvalid) {
    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  goToStep(3);
});

// ---------------------------------------------------------------------
// PMH "none" vs. individual conditions are mutually exclusive
// ---------------------------------------------------------------------
const pmhNoneCb = document.getElementById('pmh_none_cb');
const pmhOthersCb = document.getElementById('pmh_others_cb');
const pmhOthersSpecify = document.getElementById('pmh_others_specify');
const pmhConditionCbs = document.querySelectorAll('.pmh-cb');

pmhNoneCb.addEventListener('change', () => {
  if (pmhNoneCb.checked) {
    pmhConditionCbs.forEach((cb) => (cb.checked = false));
    pmhOthersCb.checked = false;
    pmhOthersSpecify.hidden = true;
  }
});

[...pmhConditionCbs, pmhOthersCb].forEach((cb) =>
  cb.addEventListener('change', () => {
    if (cb.checked) pmhNoneCb.checked = false;
  })
);

pmhOthersCb.addEventListener('change', () => {
  pmhOthersSpecify.hidden = !pmhOthersCb.checked;
});

// ---------------------------------------------------------------------
// Smoking / alcohol years reveal
// ---------------------------------------------------------------------
document.getElementById('smokes').addEventListener('change', (e) => {
  document.getElementById('smoke-years-wrap').hidden = !e.target.checked;
});
document.getElementById('drinks_alcohol').addEventListener('change', (e) => {
  document.getElementById('alcohol-years-wrap').hidden = !e.target.checked;
});

// ---------------------------------------------------------------------
// BMI auto-calc (height in inches, weight in kg)
// ---------------------------------------------------------------------
const heightInput = document.getElementById('height_in');
const weightInput = document.getElementById('weight_kg');
const bmiInput = document.getElementById('bmi');

function autoCalcBmi() {
  const inches = parseFloat(heightInput.value);
  const kg = parseFloat(weightInput.value);
  if (inches > 0 && kg > 0) {
    const meters = inches * 0.0254;
    bmiInput.value = (kg / (meters * meters)).toFixed(1);
  } else {
    bmiInput.value = '';
  }
}
heightInput.addEventListener('input', autoCalcBmi);
weightInput.addEventListener('input', autoCalcBmi);

// ---------------------------------------------------------------------
// Pediatric section — shown automatically for clients under 24 months
// ---------------------------------------------------------------------
function ageInMonths(dob) {
  if (!dob) return null;
  const birth = new Date(dob);
  const now = new Date();
  return (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
}

function ageInYears(dob) {
  if (!dob) return null;
  const birth = new Date(dob);
  const now = new Date();
  let age = now.getFullYear() - birth.getFullYear();
  const m = now.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
  return age;
}

function updatePediatricVisibility() {
  const months = selectedPatient ? ageInMonths(selectedPatient.dob) : null;
  document.getElementById('pediatric-box').hidden = !(months !== null && months < 24);
}

// ---------------------------------------------------------------------
// Step 4 validation (general survey required)
// ---------------------------------------------------------------------
document.getElementById('step4-next').addEventListener('click', () => {
  const step4Error = document.getElementById('step4-error');
  const generalSurvey = document.querySelector('input[name="general_survey"]:checked');

  if (!generalSurvey) {
    step4Error.textContent = 'General survey must be answered.';
    step4Error.hidden = false;
    return;
  }

  step4Error.hidden = true;
  goToStep(5);
});

// ---------------------------------------------------------------------
// Step 5 — Review summary
// ---------------------------------------------------------------------
function buildReview() {
  const visitType = document.querySelector('input[name="visit_type"]:checked').value === 'walk_in' ? 'Walk-in' : 'With appointment';
  const chiefComplaint = document.getElementById('chief_complaint').value.trim() || '—';
  const generalSurvey = document.querySelector('input[name="general_survey"]:checked');
  const bloodType = document.querySelector('input[name="blood_type"]:checked');

  const rosSummary = rosKeys
    .map((key) => {
      const item = document.querySelector(`.ros-item[data-ros-key="${key}"]`);
      const yes = item.querySelector('input[value="yes"]:checked') !== null;
      const label = item.querySelector('.question-text').textContent;
      return `<tr><td>${escapeHtml(truncate(label, 60))}</td><td>${yes ? 'Yes' : 'No'}</td></tr>`;
    })
    .join('');

  const pmhLabels = [];
  pmhConditionCbs.forEach((cb) => {
    if (cb.checked) pmhLabels.push(cb.nextElementSibling.textContent);
  });
  if (pmhOthersCb.checked) pmhLabels.push(`Others (${pmhOthersSpecify.value || 'unspecified'})`);
  if (pmhNoneCb.checked || pmhLabels.length === 0) pmhLabels.push(pmhNoneCb.checked ? 'None' : 'Not specified');

  const html = `
    <div class="review-group">
      <div class="review-group-head"><h3>Patient</h3><button type="button" class="link-btn" data-edit="1">Edit</button></div>
      <table class="review-table">
        <tr><td>Patient</td><td>${escapeHtml(selectedPatient ? selectedPatient.name : '—')}</td></tr>
        <tr><td>Case number</td><td>${escapeHtml(selectedPatient && selectedPatient.caseNumber ? selectedPatient.caseNumber : '—')}</td></tr>
        <tr><td>Visit type</td><td>${visitType}</td></tr>
      </table>
    </div>

    <div class="review-group">
      <div class="review-group-head"><h3>Screening</h3><button type="button" class="link-btn" data-edit="2">Edit</button></div>
      <table class="review-table">
        <tr><td>Chief complaint</td><td>${escapeHtml(chiefComplaint)}</td></tr>
        ${rosSummary}
      </table>
    </div>

    <div class="review-group">
      <div class="review-group-head"><h3>History</h3><button type="button" class="link-btn" data-edit="3">Edit</button></div>
      <table class="review-table">
        <tr><td>Smokes</td><td>${document.getElementById('smokes').checked ? 'Yes' : 'No'}</td></tr>
        <tr><td>Drinks alcohol</td><td>${document.getElementById('drinks_alcohol').checked ? 'Yes' : 'No'}</td></tr>
        <tr><td>Past medical history</td><td>${escapeHtml(pmhLabels.join(', '))}</td></tr>
      </table>
    </div>

    <div class="review-group">
      <div class="review-group-head"><h3>Exam</h3><button type="button" class="link-btn" data-edit="4">Edit</button></div>
      <table class="review-table">
        <tr><td>Blood pressure</td><td>${document.getElementById('bp_systolic').value || '—'} / ${document.getElementById('bp_diastolic').value || '—'} mmHg</td></tr>
        <tr><td>Heart rate</td><td>${document.getElementById('heart_rate').value || '—'} /min</td></tr>
        <tr><td>Respiratory rate</td><td>${document.getElementById('resp_rate').value || '—'} /min</td></tr>
        <tr><td>Temperature</td><td>${document.getElementById('temperature_c').value || '—'} °C</td></tr>
        <tr><td>Height / Weight</td><td>${document.getElementById('height_in').value || '—'} in / ${document.getElementById('weight_kg').value || '—'} kg</td></tr>
        <tr><td>BMI</td><td>${bmiInput.value || '—'}</td></tr>
        <tr><td>Blood type</td><td>${bloodType ? bloodType.value : 'Not recorded'}</td></tr>
        <tr><td>General survey</td><td>${generalSurvey.value === 'awake_alert' ? 'Awake and alert' : 'Altered sensorium'}</td></tr>
      </table>
    </div>
  `;

  const container = document.getElementById('review-content');
  container.innerHTML = html;
  container.querySelectorAll('[data-edit]').forEach((btn) => {
    btn.addEventListener('click', () => goToStep(Number(btn.dataset.edit)));
  });
}

function truncate(str, len) {
  return str.length > len ? str.slice(0, len - 1) + '…' : str;
}

// ---------------------------------------------------------------------
// Submit
// ---------------------------------------------------------------------
document.getElementById('submit-btn').addEventListener('click', async () => {
  const submitError = document.getElementById('submit-error');
  submitError.hidden = true;

  if (!selectedPatient) {
    submitError.textContent = 'Select or register a patient first.';
    submitError.hidden = false;
    return;
  }

  const ros = {};
  rosKeys.forEach((key) => {
    const item = document.querySelector(`.ros-item[data-ros-key="${key}"]`);
    const yes = item.querySelector('input[value="yes"]').checked;
    const explain = item.querySelector('.ros-explain').value;
    ros[key] = { yes, explain };
  });

  if (selectedPatient.sex === 'F') {
    ros.female_health = {
      last_menstrual_period: document.getElementById('lmp').value || null,
      first_menstrual_period: document.getElementById('fmp').value || null,
      number_of_pregnancies: document.getElementById('num_pregnancies').value || null,
    };
  }

  const pmh = {};
  pmhConditionCbs.forEach((cb) => (pmh[cb.dataset.pmh] = cb.checked));
  pmh.others = pmhOthersCb.checked;
  pmh.others_specify = pmhOthersSpecify.value;
  pmh.none = pmhNoneCb.checked;

  const bloodTypeChecked = document.querySelector('input[name="blood_type"]:checked');

  const vitals = {
    bp_systolic: document.getElementById('bp_systolic').value || null,
    bp_diastolic: document.getElementById('bp_diastolic').value || null,
    heart_rate: document.getElementById('heart_rate').value || null,
    resp_rate: document.getElementById('resp_rate').value || null,
    temperature_c: document.getElementById('temperature_c').value || null,
    height_in: document.getElementById('height_in').value || null,
    weight_kg: document.getElementById('weight_kg').value || null,
    bmi: bmiInput.value || null,
    visual_acuity: document.getElementById('visual_acuity').value || null,
    blood_type: bloodTypeChecked ? bloodTypeChecked.value : null,
  };

  if (!document.getElementById('pediatric-box').hidden) {
    vitals.pediatric = {
      length_in: document.getElementById('ped_length_in').value || null,
      head_circumference_in: document.getElementById('ped_head_circumference_in').value || null,
      muac_in: document.getElementById('ped_muac_in').value || null,
      waist_in: document.getElementById('ped_waist_in').value || null,
      hip_in: document.getElementById('ped_hip_in').value || null,
      limbs_in: document.getElementById('ped_limbs_in').value || null,
    };
  }

  const payload = {
    mission_id: window.MISSION_ID,
    patient_id: selectedPatient.id,
    visit_type: document.querySelector('input[name="visit_type"]:checked').value,
    chief_complaint: document.getElementById('chief_complaint').value,
    general_survey: document.querySelector('input[name="general_survey"]:checked').value,
    ros,
    personal_social_history: {
      smokes: document.getElementById('smokes').checked,
      smoke_years: document.getElementById('smoke_years').value || null,
      drinks_alcohol: document.getElementById('drinks_alcohol').checked,
      alcohol_years: document.getElementById('alcohol_years').value || null,
    },
    pmh,
    vitals,
  };

  try {
    const res = await fetch('api/encounters.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) {
      const message = data.errors ? Object.values(data.errors).join(', ') : data.error;
      submitError.textContent = message || 'Failed to save encounter.';
      submitError.hidden = false;
      return;
    }

    showTriageResult(data.triage);
  } catch (err) {
    submitError.textContent = 'Could not reach the server.';
    submitError.hidden = false;
  }
});

const TRIAGE_LABELS = {
  red: 'Red · Emergency',
  orange: 'Orange · Urgent',
  yellow: 'Yellow · Non-urgent',
  green: 'Green · Routine',
};
const TRIAGE_ICONS = { red: '\u2B22', orange: '\u25B2', yellow: '\u25CF', green: '\u2713' };

function showTriageResult(triage) {
  document.getElementById('wizard').hidden = true;

  const card = document.getElementById('result-card');
  card.className = `result-card ${triage.color}`;
  document.getElementById('result-icon').innerHTML = TRIAGE_ICONS[triage.color] || '';
  document.getElementById('result-title').textContent = TRIAGE_LABELS[triage.color] || triage.color;
  document.getElementById('result-reason').textContent = triage.reason;
  document.getElementById('result-alert').hidden = triage.color !== 'red';

  document.getElementById('result-panel').hidden = false;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('log-another-btn').addEventListener('click', () => {
  window.location.reload();
});

// ---------------------------------------------------------------------
// Logout
// ---------------------------------------------------------------------
document.getElementById('logout-btn').addEventListener('click', async () => {
  await fetch('api/auth.php', { method: 'DELETE' });
  window.location.href = 'login.html';
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

loadMissionBanner();
