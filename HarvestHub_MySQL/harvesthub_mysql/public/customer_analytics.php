<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Analytics</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=5">
<!-- Include Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-layout">
  
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">

      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 1.75rem; margin-bottom: 8px;">Community Analytics</h2>
        <p class="text-muted">Track the impact of the HarvestHub exchange and resource sharing network.</p>
      </div>

      <!-- Top Row: KPI Cards -->
      <div style="display: flex; gap: 24px; margin-bottom: 32px; flex-wrap: wrap;">
        
        <div class="board-panel" style="flex: 1; min-width: 200px; padding: 24px; text-align: center;">
          <p class="text-muted" style="margin-bottom: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.85rem;">Active Listings</p>
          <h3 id="stat-active" style="font-size: 2.5rem; color: var(--accent); margin: 0;">--</h3>
        </div>

        <div class="board-panel" style="flex: 1; min-width: 200px; padding: 24px; text-align: center;">
          <p class="text-muted" style="margin-bottom: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.85rem;">Completed Trades</p>
          <h3 id="stat-completed" style="font-size: 2.5rem; color: #475569; margin: 0;">--</h3>
        </div>

        <div class="board-panel" style="flex: 1; min-width: 200px; padding: 24px; text-align: center;">
          <p class="text-muted" style="margin-bottom: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.85rem;">Total Claims Made</p>
          <h3 id="stat-claims" style="font-size: 2.5rem; color: #475569; margin: 0;">--</h3>
        </div>

      </div>

      <!-- Bottom Row: Charts -->
      <div class="board-panel" style="padding: 24px; width: 100%;">
        <h3 style="margin-bottom: 24px; font-size: 1.25rem;">Most Exchanged Produce</h3>
        <!-- Chart.js needs a canvas element to draw on -->
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="produceChart"></canvas>
        </div>
      </div>

    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>

<script src="assets/app.js"></script>
<script src="assets/analytics.js"></script> 
</body>
</html>