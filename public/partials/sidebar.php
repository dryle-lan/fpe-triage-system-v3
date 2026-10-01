<?php
/**
 * Shared sidebar navigation, included right after <body class="has-sidebar">
 * on every authenticated page.
 *
 * Expects, set by the including page before require:
 *   $user                   array   the logged-in user (from Auth::user())
 *   $navActive              string  'missions' or 'users' — highlights that top-level item
 *   $sidebarMissionId       int     optional — set on dashboard/intake/report to show
 *                                   the "Current mission" section
 *   $sidebarMissionContext  string  optional — 'workspace'|'intake'|'report', highlights
 *                                   the matching sub-link and, for 'report', adds the
 *                                   print button
 */
$navActive = $navActive ?? '';
$sidebarMissionId = $sidebarMissionId ?? null;
$sidebarMissionContext = $sidebarMissionContext ?? null;
?>
<button type="button" class="sidebar-toggle no-print" id="sidebar-toggle" aria-label="Open menu" aria-expanded="false">&#9776;</button>
<div class="sidebar-overlay no-print" id="sidebar-overlay"></div>

<aside class="sidebar no-print" id="sidebar">
  <div class="sidebar-brand"><a href="index.php">FPE Triage System</a></div>

  <nav class="sidebar-nav">
    <a href="index.php" class="sidebar-link <?= $navActive === 'missions' ? 'is-active' : '' ?>">
      <span class="sidebar-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="4" width="14" height="17" rx="2"/>
          <path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/>
          <path d="M9 11h6M9 15h6"/>
        </svg>
      </span>
      Missions
    </a>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="users.php" class="sidebar-link <?= $navActive === 'users' ? 'is-active' : '' ?>">
        <span class="sidebar-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="8" r="3"/>
            <path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/>
            <circle cx="17" cy="8.5" r="2.3"/>
            <path d="M15 14.3c2.3.4 4 1.8 4 4.2"/>
          </svg>
        </span>
        Users
      </a>
    <?php endif; ?>
  </nav>

  <?php if ($sidebarMissionId): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label">Current mission</div>
      <div class="sidebar-mission-name" id="sidebar-mission-name">Loading…</div>
      <a href="dashboard.php?mission_id=<?= $sidebarMissionId ?>"
         class="sidebar-sublink <?= $sidebarMissionContext === 'workspace' ? 'is-active' : '' ?>">Workspace</a>
      <a href="intake.php?mission_id=<?= $sidebarMissionId ?>" id="sidebar-new-encounter-link"
         class="sidebar-sublink <?= $sidebarMissionContext === 'intake' ? 'is-active' : '' ?>">New encounter</a>
      <a href="report.php?mission_id=<?= $sidebarMissionId ?>"
         class="sidebar-sublink <?= $sidebarMissionContext === 'report' ? 'is-active' : '' ?>">Report</a>
      <?php if ($sidebarMissionContext === 'report'): ?>
        <button type="button" id="print-btn" class="btn btn-secondary btn-block" style="margin-top:0.7rem;">Print / Save as PDF</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="sidebar-footer">
    <div class="sidebar-user"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</div>
    <button id="logout-btn" class="btn btn-secondary btn-block">Log out</button>
  </div>
</aside>

<script>
  (function () {
    var toggle = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (!toggle || !sidebar || !overlay) return;

    function setOpen(open) {
      sidebar.classList.toggle('is-open', open);
      overlay.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(!sidebar.classList.contains('is-open'));
    });
    overlay.addEventListener('click', function () { setOpen(false); });
  })();
</script>
