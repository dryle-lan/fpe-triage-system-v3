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
  <title>FPE Triage System — Mission Workspace</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="has-sidebar">
  <?php
    $navActive = 'missions';
    $sidebarMissionId = $missionId;
    $sidebarMissionContext = 'workspace';
    require __DIR__ . '/partials/sidebar.php';
  ?>

  <main class="page page-wide">
    <div id="mission-banner" class="card mission-banner">Loading mission…</div>

    <div class="tabs">
      <button type="button" class="tab-btn is-active" data-tab="encounters">Encounters</button>
      <button type="button" class="tab-btn" data-tab="insights">Insights</button>
      <button type="button" class="tab-btn" data-tab="recommendations">Recommendations</button>
    </div>

    <!-- ENCOUNTERS TAB -->
    <section id="tab-encounters" class="tab-panel">
      <div id="encounters-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.75rem;">
        <div class="filter-chips" id="filter-chips">
          <button type="button" class="filter-chip is-active" data-filter="all">All</button>
          <button type="button" class="filter-chip" data-filter="red">Red</button>
          <button type="button" class="filter-chip" data-filter="orange">Orange</button>
          <button type="button" class="filter-chip" data-filter="yellow">Yellow</button>
          <button type="button" class="filter-chip" data-filter="green">Green</button>
        </div>
        <a id="log-encounter-link" href="intake.php?mission_id=<?= $missionId ?>" class="btn btn-primary" hidden>+ New encounter</a>
      </div>
      <div id="encounter-grid" class="encounter-grid">
        <p class="empty-state">Loading encounters…</p>
      </div>
    </section>

    <!-- INSIGHTS TAB -->
    <section id="tab-insights" class="tab-panel" hidden>
      <section id="kpi-cards" class="kpi-row"></section>

      <div class="chart-grid">
        <section class="card">
          <h2>Triage Distribution</h2>
          <canvas id="chart-triage"></canvas>
        </section>

        <section class="card">
          <h2>Sex</h2>
          <canvas id="chart-sex"></canvas>
        </section>

        <section class="card">
          <h2>Age Groups</h2>
          <canvas id="chart-age"></canvas>
        </section>

        <section class="card">
          <h2>Top Chief Complaints</h2>
          <canvas id="chart-complaints"></canvas>
        </section>

        <section class="card">
          <h2>Chronic Condition Prevalence</h2>
          <canvas id="chart-pmh"></canvas>
        </section>

        <section class="card">
          <h2>Smoking / Alcohol Use</h2>
          <canvas id="chart-social"></canvas>
        </section>
      </div>
    </section>

    <!-- RECOMMENDATIONS TAB -->
    <section id="tab-recommendations" class="tab-panel" hidden>
      <section class="card" id="ai-recommendations-section">
        <h2>AI Recommendations</h2>
        <button id="generate-recommendation-btn" class="btn btn-primary" hidden>Generate Recommendations</button>
        <p id="generate-recommendation-status" class="hint" hidden></p>
        <div id="recommendations-list">Loading…</div>
      </section>
    </section>
  </main>

  <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
    window.MISSION_ID = <?= json_encode($missionId) ?>;
  </script>
  <script src="js/stats.js"></script>
  <script src="js/charts.js"></script>
  <script src="js/patient-avatar.js"></script>
  <script src="js/dashboard.js"></script>
</body>
</html>
