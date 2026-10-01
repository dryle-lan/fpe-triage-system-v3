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
  <title>Mission Report — FPE Triage System</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="report-body has-sidebar">
  <?php
    $navActive = 'missions';
    $sidebarMissionId = $missionId;
    $sidebarMissionContext = 'report';
    require __DIR__ . '/partials/sidebar.php';
  ?>

  <article class="report">
    <header class="report-header">
      <h1>Mission Report</h1>
      <div id="report-mission" class="report-mission">Loading…</div>
      <div id="report-meta" class="report-meta"></div>
    </header>

    <section class="report-section">
      <h2>1. Summary</h2>
      <div id="kpi-cards" class="kpi-row"></div>
      <table id="triage-table"></table>
    </section>

    <section class="report-section">
      <h2>2. Charts</h2>
      <div class="report-charts">
        <figure class="chart-figure">
          <figcaption>Triage distribution</figcaption>
          <canvas id="chart-triage" width="340" height="240" data-alt="Triage distribution chart"></canvas>
        </figure>
        <figure class="chart-figure">
          <figcaption>Sex</figcaption>
          <canvas id="chart-sex" width="340" height="240" data-alt="Sex distribution chart"></canvas>
        </figure>
        <figure class="chart-figure">
          <figcaption>Age groups</figcaption>
          <canvas id="chart-age" width="340" height="240" data-alt="Age group chart"></canvas>
        </figure>
        <figure class="chart-figure">
          <figcaption>Top chief complaints</figcaption>
          <canvas id="chart-complaints" width="340" height="240" data-alt="Top chief complaints chart"></canvas>
        </figure>
        <figure class="chart-figure">
          <figcaption>Past medical history (% of patients)</figcaption>
          <canvas id="chart-pmh" width="340" height="240" data-alt="Past medical history chart"></canvas>
        </figure>
        <figure class="chart-figure">
          <figcaption>Smoking &amp; alcohol use (% of patients)</figcaption>
          <canvas id="chart-social" width="340" height="240" data-alt="Smoking and alcohol chart"></canvas>
        </figure>
      </div>
    </section>

    <section class="report-section">
      <h2>3. Data Tables</h2>
      <div class="tables-grid">
        <div>
          <h3>Sex</h3>
          <table id="sex-table"></table>
        </div>
        <div>
          <h3>Age groups</h3>
          <table id="age-table"></table>
        </div>
        <div>
          <h3>Top chief complaints</h3>
          <table id="complaints-table"></table>
        </div>
        <div>
          <h3>Past medical history</h3>
          <table id="pmh-table"></table>
        </div>
      </div>
      <p id="social-line" class="hint"></p>
    </section>

    <section class="report-section">
      <h2>4. Priority Follow-up (Red &amp; Orange)</h2>
      <table id="priority-table"></table>
      <p class="hint">Patient names are intentionally left out of this printed report; use the case number to look them up.</p>
    </section>

    <section class="report-section">
      <h2>5. AI-Generated Insight</h2>
      <div id="insight"></div>
    </section>

    <footer class="report-footer">
      Generated <span id="report-generated"></span> by <?= htmlspecialchars($user['name']) ?> &middot; FPE Triage System
    </footer>
  </article>

  <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
    window.MISSION_ID = <?= json_encode($missionId) ?>;
  </script>
  <script src="js/stats.js"></script>
  <script src="js/charts.js"></script>
  <script src="js/report.js"></script>
</body>
</html>
