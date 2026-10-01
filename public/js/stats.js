// stats.js — shared, DOM-free logic used by dashboard.js and report.js:
// loading a mission's encounters, aggregating them into summary stats, small helpers.

const TRIAGE_COLORS = {
  red: '#C62828',
  orange: '#E07B00',
  yellow: '#D4A017',
  green: '#2E8B57',
};

async function loadEncounters() {
  const res = await fetch(`api/encounters.php?mission_id=${encodeURIComponent(window.MISSION_ID)}`);
  const rows = await res.json();

  // JSON columns come back from MySQL/PDO as strings — parse them once here.
  return rows.map((row) => ({
    ...row,
    ros: safeParse(row.ros_json),
    pmh: safeParse(row.pmh_json),
    vitals: safeParse(row.vitals_json),
    personal_social_history: safeParse(row.personal_social_history_json),
  }));
}

function safeParse(str) {
  try {
    return JSON.parse(str) || {};
  } catch (err) {
    return {};
  }
}

// ---------------------------------------------------------------------
// Aggregation
// ---------------------------------------------------------------------
function computeAgeInYears(dob, asOfDate) {
  if (!dob) return null;
  const birth = new Date(dob);
  const asOf = new Date(asOfDate);
  let age = asOf.getFullYear() - birth.getFullYear();
  const monthDiff = asOf.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && asOf.getDate() < birth.getDate())) {
    age--;
  }
  return age;
}

function ageBracket(age) {
  if (age === null) return 'Unknown';
  if (age < 5) return '0-4';
  if (age < 18) return '5-17';
  if (age < 40) return '18-39';
  if (age < 60) return '40-59';
  return '60+';
}

const PMH_LABELS = {
  cancer: 'Cancer',
  allergies: 'Allergies',
  diabetes_mellitus: 'Diabetes Mellitus',
  hypertension: 'Hypertension',
  heart_disease: 'Heart Disease',
  stroke: 'Stroke',
  bronchial_asthma: 'Bronchial Asthma',
  copd: 'COPD/emphysema/bronchitis',
  tuberculosis: 'Tuberculosis',
};

function aggregate(encounters, mission) {
  const missionDate = mission ? mission.mission_date : new Date().toISOString().slice(0, 10);

  const triageCounts = { red: 0, orange: 0, yellow: 0, green: 0 };
  const sexCounts = { M: 0, F: 0, Unspecified: 0 };
  const ageCounts = { '0-4': 0, '5-17': 0, '18-39': 0, '40-59': 0, '60+': 0, Unknown: 0 };
  const complaintCounts = {};
  const pmhCounts = Object.fromEntries(Object.keys(PMH_LABELS).map((k) => [k, 0]));
  let smokerCount = 0;
  let drinkerCount = 0;
  const priorityList = [];

  encounters.forEach((enc) => {
    if (triageCounts[enc.triage_color] !== undefined) {
      triageCounts[enc.triage_color]++;
    }

    const sexKey = enc.sex === 'M' || enc.sex === 'F' ? enc.sex : 'Unspecified';
    sexCounts[sexKey]++;

    const age = computeAgeInYears(enc.date_of_birth, missionDate);
    ageCounts[ageBracket(age)]++;

    const complaintKey = (enc.chief_complaint || '').trim().toLowerCase();
    if (complaintKey) {
      complaintCounts[complaintKey] = complaintCounts[complaintKey] || { label: enc.chief_complaint.trim(), count: 0 };
      complaintCounts[complaintKey].count++;
    }

    Object.keys(PMH_LABELS).forEach((key) => {
      if (enc.pmh && enc.pmh[key]) pmhCounts[key]++;
    });

    if (enc.personal_social_history && enc.personal_social_history.smokes) smokerCount++;
    if (enc.personal_social_history && enc.personal_social_history.drinks_alcohol) drinkerCount++;

    if (enc.triage_color === 'red' || enc.triage_color === 'orange') {
      priorityList.push(enc);
    }
  });

  priorityList.sort((a, b) => (a.triage_color === 'red' ? -1 : 1) - (b.triage_color === 'red' ? -1 : 1));

  const topComplaints = Object.values(complaintCounts)
    .sort((a, b) => b.count - a.count)
    .slice(0, 5);

  const total = encounters.length || 1;

  return {
    total: encounters.length,
    triageCounts,
    sexCounts,
    ageCounts,
    topComplaints,
    pmhCounts,
    pmhPercent: Object.fromEntries(Object.keys(pmhCounts).map((k) => [k, Math.round((pmhCounts[k] / total) * 100)])),
    smokerPercent: Math.round((smokerCount / total) * 100),
    drinkerPercent: Math.round((drinkerCount / total) * 100),
    priorityList,
  };
}

function truncate(str, len) {
  return str.length > len ? str.slice(0, len - 1) + '…' : str;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
