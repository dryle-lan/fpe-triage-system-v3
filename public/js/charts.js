// charts.js — shared KPI + Chart.js rendering, used by dashboard.js and report.js.
// Expects the element ids: kpi-cards, chart-triage, chart-sex, chart-age,
// chart-complaints, chart-pmh, chart-social. Needs stats.js and Chart.js loaded first.

function renderKpis(stats) {
  const el = document.getElementById('kpi-cards');
  el.innerHTML = `
    <div class="kpi-card"><div class="kpi-value">${stats.total}</div><div class="kpi-label">Total Encounters</div></div>
    <div class="kpi-card kpi-red"><div class="kpi-value">${stats.triageCounts.red}</div><div class="kpi-label">Red</div></div>
    <div class="kpi-card kpi-orange"><div class="kpi-value">${stats.triageCounts.orange}</div><div class="kpi-label">Orange</div></div>
    <div class="kpi-card kpi-yellow"><div class="kpi-value">${stats.triageCounts.yellow}</div><div class="kpi-label">Yellow</div></div>
    <div class="kpi-card kpi-green"><div class="kpi-value">${stats.triageCounts.green}</div><div class="kpi-label">Green</div></div>
  `;
}

function renderCharts(stats) {
  new Chart(document.getElementById('chart-triage'), {
    type: 'doughnut',
    data: {
      labels: ['Red', 'Orange', 'Yellow', 'Green'],
      datasets: [{
        data: [stats.triageCounts.red, stats.triageCounts.orange, stats.triageCounts.yellow, stats.triageCounts.green],
        backgroundColor: [TRIAGE_COLORS.red, TRIAGE_COLORS.orange, TRIAGE_COLORS.yellow, TRIAGE_COLORS.green],
      }],
    },
  });

  new Chart(document.getElementById('chart-sex'), {
    type: 'bar',
    data: {
      labels: Object.keys(stats.sexCounts),
      datasets: [{ label: 'Patients', data: Object.values(stats.sexCounts), backgroundColor: '#1F5FBF' }],
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('chart-age'), {
    type: 'bar',
    data: {
      labels: Object.keys(stats.ageCounts),
      datasets: [{ label: 'Patients', data: Object.values(stats.ageCounts), backgroundColor: '#1F5FBF' }],
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('chart-complaints'), {
    type: 'bar',
    data: {
      labels: stats.topComplaints.map((c) => truncate(c.label, 24)),
      datasets: [{ label: 'Count', data: stats.topComplaints.map((c) => c.count), backgroundColor: '#7c3aed' }],
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });

  new Chart(document.getElementById('chart-pmh'), {
    type: 'bar',
    data: {
      labels: Object.keys(PMH_LABELS).map((k) => PMH_LABELS[k]),
      datasets: [{
        label: '% of patients',
        data: Object.keys(PMH_LABELS).map((k) => stats.pmhPercent[k]),
        backgroundColor: '#0891b2',
      }],
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, max: 100 } },
    },
  });

  new Chart(document.getElementById('chart-social'), {
    type: 'bar',
    data: {
      labels: ['Smokes', 'Drinks Alcohol'],
      datasets: [{
        label: '% of patients',
        data: [stats.smokerPercent, stats.drinkerPercent],
        backgroundColor: '#b45309',
      }],
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } },
  });
}
