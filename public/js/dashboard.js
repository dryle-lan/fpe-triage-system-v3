// dashboard.js — mission workspace: tabs (Encounters / Insights / Recommendations).
// Shared logic: stats.js (aggregation) and charts.js (KPI cards + Chart.js charts).
// Load those before this file.

let allEncounters = [];
let currentFilter = 'all';

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
      return null;
    }

    banner.className = `card mission-banner${mission.status === 'closed' ? ' is-closed' : ''}`;
    banner.innerHTML = `
      <div>
        <div class="mission-banner-title">${escapeHtml(mission.site_name)}</div>
        <div class="mission-banner-sub">${escapeHtml(mission.location || 'No location set')} &middot; ${escapeHtml(mission.mission_date)}</div>
      </div>
      <span class="badge badge-${mission.status}">${mission.status}</span>
    `;

    const sidebarName = document.getElementById('sidebar-mission-name');
    if (sidebarName) sidebarName.textContent = mission.site_name;

    const newEncounterLink = document.getElementById('log-encounter-link');
    if (newEncounterLink) newEncounterLink.hidden = mission.status !== 'open';

    const sidebarNewEncounterLink = document.getElementById('sidebar-new-encounter-link');
    if (sidebarNewEncounterLink) sidebarNewEncounterLink.hidden = mission.status !== 'open';

    return mission;
  } catch (err) {
    banner.textContent = 'Could not load mission info.';
    return null;
  }
}

// ---------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------
document.querySelectorAll('.tab-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('is-active'));
    document.querySelectorAll('.tab-panel').forEach((p) => (p.hidden = true));
    btn.classList.add('is-active');
    document.getElementById(`tab-${btn.dataset.tab}`).hidden = false;
  });
});

// ---------------------------------------------------------------------
// Encounter cards + filter chips
// ---------------------------------------------------------------------
document.querySelectorAll('.filter-chip').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-chip').forEach((b) => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    currentFilter = btn.dataset.filter;
    renderEncounterCards();
  });
});

function renderEncounterCards() {
  const grid = document.getElementById('encounter-grid');
  const filtered =
    currentFilter === 'all' ? allEncounters : allEncounters.filter((e) => e.triage_color === currentFilter);

  if (filtered.length === 0) {
    grid.innerHTML = '<p class="empty-state">No encounters match this filter.</p>';
    return;
  }

  grid.innerHTML = filtered.map(renderEncounterCard).join('');
}

function renderEncounterCard(enc) {
  const name = [enc.last_name, enc.first_name].filter(Boolean).join(', ') || `Patient #${enc.patient_id}`;
  const age = computeAge(enc.date_of_birth);
  const sexLabel = enc.sex === 'M' || enc.sex === 'F' ? enc.sex : '—';
  const ageSex = age !== null ? `${age} ${sexLabel}` : sexLabel;
  const color = enc.triage_color || 'green';

  return `
    <div class="encounter-card">
      <div class="encounter-card-edge ${color}"></div>
      <div class="encounter-card-body">
        <div class="encounter-card-top">
          <span class="badge-triage badge-triage-${color}">${color}</span>
          <span class="encounter-card-time">${formatTimeAgo(enc.created_at)}</span>
        </div>
        <div class="encounter-card-person">
          ${avatarHtml(name)}
          <div>
            <div class="encounter-card-name">${escapeHtml(name)}</div>
            <div class="encounter-card-meta">${escapeHtml(enc.case_number || 'no case #')} &middot; ${escapeHtml(ageSex)}</div>
          </div>
        </div>
        <div class="encounter-card-complaint">${escapeHtml(enc.chief_complaint || '—')}</div>
        ${enc.triage_reason ? `<div class="encounter-card-reason">${escapeHtml(enc.triage_reason)}</div>` : ''}
      </div>
    </div>
  `;
}

function computeAge(dob) {
  if (!dob) return null;
  const birth = new Date(dob);
  const now = new Date();
  let age = now.getFullYear() - birth.getFullYear();
  const monthDiff = now.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) age--;
  return age;
}

function formatTimeAgo(dateStr) {
  if (!dateStr) return '';
  const then = new Date(String(dateStr).replace(' ', 'T'));
  const diffMs = Date.now() - then.getTime();
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins} min ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs} hr ago`;
  const days = Math.floor(hrs / 24);
  return `${days} day${days === 1 ? '' : 's'} ago`;
}

// ---------------------------------------------------------------------
// AI Recommendations
// ---------------------------------------------------------------------
const isDoctorOrAdmin = window.CURRENT_USER && ['doctor', 'admin'].includes(window.CURRENT_USER.role);
const generateBtn = document.getElementById('generate-recommendation-btn');
const generateStatus = document.getElementById('generate-recommendation-status');
const recommendationsList = document.getElementById('recommendations-list');

if (generateBtn && isDoctorOrAdmin) {
  generateBtn.hidden = false;
}

function setGenerateStatus(msg, isError) {
  if (!msg) {
    generateStatus.hidden = true;
    return;
  }
  generateStatus.hidden = false;
  generateStatus.textContent = msg;
  generateStatus.className = isError ? 'error' : 'hint';
}

async function loadRecommendations() {
  try {
    const res = await fetch(`api/recommendations.php?mission_id=${encodeURIComponent(window.MISSION_ID)}`);
    const recs = await res.json();
    renderRecommendations(recs);
  } catch (err) {
    recommendationsList.textContent = 'Could not load recommendations.';
  }
}

function renderRecommendations(recs) {
  if (!recs || recs.length === 0) {
    recommendationsList.innerHTML = '<p class="hint">No recommendations generated yet.</p>';
    return;
  }
  recommendationsList.innerHTML = recs.map(renderRecommendationCard).join('');
}

function renderRecommendationCard(rec) {
  const when = new Date(rec.generated_at.replace(' ', 'T')).toLocaleString();
  const text = escapeHtml(rec.output_text).replace(/\n/g, '<br>');
  return `
    <div class="recommendation-card">
      <div class="recommendation-meta">${escapeHtml(when)}</div>
      <div class="recommendation-text">${text}</div>
    </div>
  `;
}

if (generateBtn) {
  generateBtn.addEventListener('click', async () => {
    generateBtn.disabled = true;
    setGenerateStatus('Generating… if Gemini is busy this can take up to a minute while it retries.', false);

    try {
      const res = await fetch('api/recommendations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mission_id: window.MISSION_ID }),
      });
      const data = await res.json();

      if (!res.ok) {
        setGenerateStatus(data.error || 'Failed to generate recommendations.', true);
        return;
      }

      setGenerateStatus(null);
      loadRecommendations();
    } catch (err) {
      setGenerateStatus('Could not reach the server.', true);
    } finally {
      generateBtn.disabled = false;
    }
  });
}

// ---------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------
(async function init() {
  const [mission, encounters] = await Promise.all([loadMissionBanner(), loadEncounters(), loadRecommendations()]);
  allEncounters = encounters;

  const stats = aggregate(encounters, mission);
  renderKpis(stats);
  renderCharts(stats);
  renderEncounterCards();
})();

document.getElementById('logout-btn').addEventListener('click', async () => {
  await fetch('api/auth.php', { method: 'DELETE' });
  window.location.href = 'login.html';
});
