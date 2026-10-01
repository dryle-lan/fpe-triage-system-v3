<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

$user = Auth::user();
if (!$user) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FPE Triage System — Missions</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="has-sidebar">
  <?php $navActive = 'missions'; require __DIR__ . '/partials/sidebar.php'; ?>

  <main class="page page-wide">
    <div id="open-missions-label" class="section-label">Missions</div>
    <div id="open-missions-grid" class="mission-grid">
      <p class="empty-state">Loading missions…</p>
    </div>

    <div id="past-missions-wrap" hidden>
      <div class="section-label">Past missions</div>
      <div id="past-missions-grid" class="mission-grid"></div>
    </div>
  </main>

  <!-- New mission modal (admin only) -->
  <div id="new-mission-overlay" class="modal-overlay" hidden>
    <div class="modal-card">
      <h2>New mission</h2>
      <form id="new-mission-form">
        <label for="site_name">Site name</label>
        <input type="text" id="site_name" required>

        <label for="location">Location</label>
        <input type="text" id="location">

        <label for="mission_date">Mission date</label>
        <input type="date" id="mission_date" required>

        <p id="new-mission-error" class="error" hidden></p>
        <div class="modal-actions">
          <button type="button" id="cancel-new-mission-btn" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary">Create mission</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
  </script>
  <script src="js/missions.js"></script>
</body>
</html>
