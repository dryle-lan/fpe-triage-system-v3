// missions.js — mission list page: card grid, create (admin), close (admin)

const role = window.CURRENT_USER && window.CURRENT_USER.role;
const isAdmin = role === 'admin';

const openGrid = document.getElementById('open-missions-grid');
const pastWrap = document.getElementById('past-missions-wrap');
const pastGrid = document.getElementById('past-missions-grid');
const openLabel = document.getElementById('open-missions-label');

const overlay = document.getElementById('new-mission-overlay');
const newMissionForm = document.getElementById('new-mission-form');
const newMissionError = document.getElementById('new-mission-error');
const cancelBtn = document.getElementById('cancel-new-mission-btn');
const logoutBtn = document.getElementById('logout-btn');

async function loadMissions() {
  openGrid.innerHTML = '<p class="empty-state">Loading missions…</p>';

  try {
    const res = await fetch('api/missions.php');
    const missions = await res.json();

    if (!res.ok) {
      openGrid.innerHTML = '<p class="empty-state">Failed to load missions.</p>';
      return;
    }

    const open = missions.filter((m) => m.status === 'open');
    const closed = missions.filter((m) => m.status !== 'open');

    renderOpenGrid(open);
    renderPastGrid(closed);
    wireCardEvents();
  } catch (err) {
    openGrid.innerHTML = '<p class="empty-state">Could not reach the server.</p>';
  }
}

function renderOpenGrid(missions) {
  const createCard = isAdmin
    ? `<button type="button" class="create-mission-card" id="open-new-mission-btn">
         <span class="plus">+</span>
         <span>Create mission</span>
       </button>`
    : '';

  if (missions.length === 0) {
    openGrid.innerHTML = createCard || '<p class="empty-state">No open missions right now.</p>';
    if (isAdmin) {
      document.getElementById('open-new-mission-btn').addEventListener('click', openNewMissionModal);
    }
    return;
  }

  openGrid.innerHTML = createCard + missions.map((m) => renderMissionCard(m, true)).join('');
  if (isAdmin) {
    document.getElementById('open-new-mission-btn').addEventListener('click', openNewMissionModal);
  }
}

function renderPastGrid(missions) {
  if (missions.length === 0) {
    pastWrap.hidden = true;
    return;
  }
  pastWrap.hidden = false;
  pastGrid.innerHTML = missions.map((m) => renderMissionCard(m, false)).join('');
}

function primaryAction(mission, isOpen) {
  if (role === 'health_worker') {
    if (!isOpen) return '';
    return `<a class="btn btn-primary" href="intake.php?mission_id=${mission.id}">Log encounters</a>`;
  }
  if (role === 'doctor') {
    return `<a class="btn btn-primary" href="dashboard.php?mission_id=${mission.id}">Open</a>`;
  }
  // admin
  return `<a class="btn btn-primary" href="dashboard.php?mission_id=${mission.id}">Manage</a>`;
}

function renderMissionCard(mission, isOpen) {
  const statusBadge = `<span class="badge badge-${mission.status}">${escapeHtml(mission.status)}</span>`;
  const closeItem =
    isAdmin && isOpen
      ? `<button type="button" class="danger" data-close-id="${mission.id}" data-encounter-count="${mission.encounter_count}">Close mission</button>`
      : '';

  return `
    <div class="mission-card ${isOpen ? '' : 'is-closed'}">
      <div class="mission-card-top">
        <div>
          <p class="mission-card-title">${escapeHtml(mission.site_name)}</p>
          <p class="mission-card-sub">${escapeHtml(mission.location || 'No location set')} · ${escapeHtml(mission.mission_date)}</p>
        </div>
        ${statusBadge}
      </div>
      <div class="mission-stats">
        <div class="mission-stat">
          <div class="mission-stat-value">${mission.encounter_count}</div>
          <div class="mission-stat-label">Encounters</div>
        </div>
        <div class="mission-stat is-red">
          <div class="mission-stat-value">${mission.red_count}</div>
          <div class="mission-stat-label">Red</div>
        </div>
        <div class="mission-stat is-orange">
          <div class="mission-stat-value">${mission.orange_count}</div>
          <div class="mission-stat-label">Orange</div>
        </div>
      </div>
      <div class="mission-card-actions">
        ${primaryAction(mission, isOpen)}
        <div class="mission-menu">
          <button type="button" class="btn-icon" aria-label="More actions" data-menu-toggle>&#8942;</button>
          <div class="mission-menu-list">
            <a href="report.php?mission_id=${mission.id}">Report</a>
            ${closeItem}
          </div>
        </div>
      </div>
    </div>
  `;
}

function wireCardEvents() {
  document.querySelectorAll('[data-menu-toggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = btn.closest('.mission-menu');
      document.querySelectorAll('.mission-menu.is-open').forEach((m) => {
        if (m !== menu) m.classList.remove('is-open');
      });
      menu.classList.toggle('is-open');
    });
  });

  document.querySelectorAll('[data-close-id]').forEach((btn) => {
    btn.addEventListener('click', () => closeMission(btn.dataset.closeId, btn.dataset.encounterCount));
  });
}

document.addEventListener('click', () => {
  document.querySelectorAll('.mission-menu.is-open').forEach((m) => m.classList.remove('is-open'));
});

async function closeMission(id, encounterCount) {
  const count = Number(encounterCount) || 0;
  const noun = count === 1 ? 'encounter' : 'encounters';
  const confirmed = confirm(
    `Close this mission?\n\nIntake will end. ${count} ${noun} will move to review. This cannot be undone from here.`
  );
  if (!confirmed) return;

  try {
    const res = await fetch(`api/missions.php?id=${encodeURIComponent(id)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'close' }),
    });

    if (!res.ok) {
      const data = await res.json();
      alert(data.error || 'Failed to close mission.');
      return;
    }

    loadMissions();
  } catch (err) {
    alert('Could not reach the server.');
  }
}

// ---------------------------------------------------------------------
// New mission modal
// ---------------------------------------------------------------------
function openNewMissionModal() {
  newMissionError.hidden = true;
  newMissionForm.reset();
  overlay.hidden = false;
  document.getElementById('site_name').focus();
}

function closeNewMissionModal() {
  overlay.hidden = true;
}

if (cancelBtn) cancelBtn.addEventListener('click', closeNewMissionModal);
if (overlay) {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeNewMissionModal();
  });
}

if (newMissionForm) {
  newMissionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    newMissionError.hidden = true;

    const payload = {
      site_name: document.getElementById('site_name').value,
      location: document.getElementById('location').value,
      mission_date: document.getElementById('mission_date').value,
    };

    try {
      const res = await fetch('api/missions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok) {
        const message = data.errors
          ? Object.values(data.errors).join(', ')
          : data.error || 'Failed to create mission.';
        newMissionError.textContent = message;
        newMissionError.hidden = false;
        return;
      }

      closeNewMissionModal();
      loadMissions();
    } catch (err) {
      newMissionError.textContent = 'Could not reach the server.';
      newMissionError.hidden = false;
    }
  });
}

if (logoutBtn) {
  logoutBtn.addEventListener('click', async () => {
    await fetch('api/auth.php', { method: 'DELETE' });
    window.location.href = 'login.html';
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

loadMissions();
