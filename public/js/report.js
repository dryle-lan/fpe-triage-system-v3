// report.js — printable mission report.
// Uses stats.js (aggregation) and charts.js (KPI cards + charts), same as the dashboard.

const TRIAGE_MEANING = {
  red: 'Emergency — needs a doctor immediately',
  orange: 'Urgent — same-day priority',
  yellow: 'Non-urgent — needs a consult soon',
  green: 'Routine — no complaints',
};

async function loadMission() {
  try {
    const res = await fetch(`api/missions.php?id=${encodeURIComponent(window.MISSION_ID)}`);
    if (!res.ok) return null;
    return await res.json(); // an object, or null if the id doesn't exist
  } catch (err) {
    return null;
  }
}

async function loadRecommendations() {
  try {
    const res = await fetch(`api/recommendations.php?mission_id=${encodeURIComponent(window.MISSION_ID)}`);
    if (!res.ok) return [];
    return await res.json();
  } catch (err) {
    return [];
  }
}

function pct(n, total) {
  return total ? Math.round((n / total) * 100) : 0;
}

function tableHtml(headers, rows) {
  const head = `<thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>`;
  const body = rows.length
    ? rows.map((r) => `<tr>${r.map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('')
    : `<tr><td colspan="${headers.length}">No data</td></tr>`;
  return `${head}<tbody>${body}</tbody>`;
}

// ---------------------------------------------------------------------
// Header + tables
// ---------------------------------------------------------------------
function renderHeader(mission, stats) {
  const el = document.getElementById('report-mission');
  const meta = document.getElementById('report-meta');

  if (!mission) {
    el.textContent = 'Mission not found.';
    return;
  }

  el.innerHTML = `<strong>${escapeHtml(mission.site_name)}</strong>`;
  const sidebarName = document.getElementById('sidebar-mission-name');
  if (sidebarName) sidebarName.textContent = mission.site_name;
  const sidebarNewEncounterLink = document.getElementById('sidebar-new-encounter-link');
  if (sidebarNewEncounterLink) sidebarNewEncounterLink.hidden = mission.status !== 'open';
  meta.innerHTML = [
    mission.location ? `Location: ${escapeHtml(mission.location)}` : null,
    `Mission date: ${escapeHtml(mission.mission_date)}`,
    `Status: ${escapeHtml(mission.status)}`,
    `Encounters logged: ${stats.total}`,
  ]
    .filter(Boolean)
    .join(' &nbsp;·&nbsp; ');
}

function renderTables(stats) {
  const total = stats.total;

  document.getElementById('triage-table').innerHTML = tableHtml(
    ['Priority', 'Count', '%', 'Meaning'],
    ['red', 'orange', 'yellow', 'green'].map((c) => [
      `<span class="badge-triage badge-triage-${c}">${c}</span>`,
      stats.triageCounts[c],
      `${pct(stats.triageCounts[c], total)}%`,
      escapeHtml(TRIAGE_MEANING[c]),
    ])
  );

  document.getElementById('sex-table').innerHTML = tableHtml(
    ['Sex', 'Count', '%'],
    [['M', 'Male'], ['F', 'Female'], ['Unspecified', 'Unspecified']].map(([key, label]) => [
      label,
      stats.sexCounts[key],
      `${pct(stats.sexCounts[key], total)}%`,
    ])
  );

  document.getElementById('age-table').innerHTML = tableHtml(
    ['Age group', 'Count', '%'],
    Object.entries(stats.ageCounts).map(([label, n]) => [escapeHtml(label), n, `${pct(n, total)}%`])
  );

  document.getElementById('complaints-table').innerHTML = tableHtml(
    ['Chief complaint', 'Count'],
    stats.topComplaints.map((c) => [escapeHtml(c.label), c.count])
  );

  document.getElementById('pmh-table').innerHTML = tableHtml(
    ['Condition', 'Patients', '%'],
    Object.keys(PMH_LABELS)
      .filter((k) => stats.pmhCounts[k] > 0)
      .map((k) => [escapeHtml(PMH_LABELS[k]), stats.pmhCounts[k], `${stats.pmhPercent[k]}%`])
  );

  document.getElementById('social-line').textContent =
    `Smokes: ${stats.smokerPercent}% of patients · Drinks alcohol: ${stats.drinkerPercent}% of patients`;
}

function renderPriority(priorityList) {
  document.getElementById('priority-table').innerHTML = tableHtml(
    ['Case #', 'Chief complaint', 'Priority', 'Reason'],
    priorityList.map((enc) => [
      escapeHtml(enc.case_number || `#${enc.patient_id}`),
      escapeHtml(enc.chief_complaint || '—'),
      `<span class="badge-triage badge-triage-${enc.triage_color}">${enc.triage_color}</span>`,
      escapeHtml(enc.triage_reason || '—'),
    ])
  );
}

// ---------------------------------------------------------------------
// AI insight (latest saved recommendation)
// ---------------------------------------------------------------------

// Gemini replies in light markdown (**bold**, "* " bullets, "#" headings);
// turn just those into HTML. Text is escaped first, so this is safe.
function renderInsightText(text) {
  const inline = (s) => s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  let html = '';
  let inList = false;

  escapeHtml(text)
    .split('\n')
    .forEach((raw) => {
      const line = raw.trim();
      const bullet = line.match(/^[*\-•]\s+(.*)$/);

      if (bullet) {
        if (!inList) {
          html += '<ul>';
          inList = true;
        }
        html += `<li>${inline(bullet[1])}</li>`;
        return;
      }

      if (inList) {
        html += '</ul>';
        inList = false;
      }
      if (!line) return;

      const heading = line.match(/^#{1,6}\s+(.*)$/);
      html += heading ? `<h4>${inline(heading[1])}</h4>` : `<p>${inline(line)}</p>`;
    });

  if (inList) html += '</ul>';
  return html;
}

function renderInsight(recs) {
  const el = document.getElementById('insight');

  if (!recs || recs.length === 0) {
    el.innerHTML =
      '<p class="hint">No AI recommendation has been generated for this mission yet. ' +
      'A doctor or admin can generate one from the mission dashboard, then reprint this report.</p>';
    return;
  }

  const latest = recs[0]; // the API returns newest first
  const when = new Date(latest.generated_at.replace(' ', 'T')).toLocaleString();

  let modelNote = '';
  try {
    const snapshot = JSON.parse(latest.prompt_snapshot_json);
    if (snapshot && snapshot.model_used) modelNote = ` · Model: ${escapeHtml(snapshot.model_used)}`;
  } catch (err) {
    /* older recommendations may not have a model recorded */
  }

  el.innerHTML = `
    <div class="insight-meta">Generated ${escapeHtml(when)}${modelNote}</div>
    <div class="insight-text">${renderInsightText(latest.output_text)}</div>
    <p class="hint">AI-generated suggestions based on summary counts only. They support, but do not replace, clinical and program judgment.</p>
  `;
}

// ---------------------------------------------------------------------
// Charts: render once, then swap each canvas for a static image so the
// printed layout is identical to what's on screen.
// ---------------------------------------------------------------------
function renderChartsForPrint(stats) {
  Chart.defaults.animation = false;
  Chart.defaults.responsive = false;

  renderCharts(stats);

  setTimeout(() => {
    document.querySelectorAll('.report canvas').forEach((canvas) => {
      const img = new Image();
      img.src = canvas.toDataURL('image/png');
      img.alt = canvas.dataset.alt || '';
      img.className = 'chart-img';
      canvas.replaceWith(img);
    });
  }, 100);
}

// ---------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------
(async function init() {
  const [mission, encounters, recs] = await Promise.all([loadMission(), loadEncounters(), loadRecommendations()]);
  const stats = aggregate(encounters, mission);

  document.getElementById('report-generated').textContent = new Date().toLocaleString();

  renderHeader(mission, stats);
  renderKpis(stats);
  renderTables(stats);
  renderPriority(stats.priorityList);
  renderInsight(recs);
  renderChartsForPrint(stats);
})();

document.getElementById('print-btn').addEventListener('click', () => window.print());

document.getElementById('logout-btn').addEventListener('click', async () => {
  await fetch('api/auth.php', { method: 'DELETE' });
  window.location.href = 'login.html';
});
