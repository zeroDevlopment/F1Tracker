<?php
include 'includes/header.php';
include 'includes/functions.php';

$race_files = getRaceFiles("data/races");
$driver_stats = compileDriverStats($race_files);
$constructor_stats = compileConstructorStats($driver_stats);

$team_colors = [
  'Red Bull Racing' => '#1e41ff',
  'Mercedes' => '#00d2be',
  'Ferrari' => '#dc0000',
  'McLaren' => '#ff8700',
  'Aston Martin' => '#006f62',
  'Alpine' => '#0090ff',
  'Williams' => '#005aff',
  'RB' => '#2b4562',
  'Haas' => '#bd9e57',
  'Sauber' => '#52e252',
  'Unknown' => '#888888',
];

$driver_info = [
  'NOR' => ['country' => 'gb', 'team' => 'McLaren'],
  'VER' => ['country' => 'nl', 'team' => 'Red Bull Racing'],
  'RUS' => ['country' => 'gb', 'team' => 'Mercedes'],
  'ANT' => ['country' => 'it', 'team' => 'Mercedes'],
  'ALB' => ['country' => 'th', 'team' => 'Williams'],
  'PIA' => ['country' => 'au', 'team' => 'McLaren'],
  'HAM' => ['country' => 'gb', 'team' => 'Ferrari'],
  'STR' => ['country' => 'ca', 'team' => 'Aston Martin'],
  'LEC' => ['country' => 'mc', 'team' => 'Ferrari'],
  'HUL' => ['country' => 'de', 'team' => 'Kick Sauber'],
  'TSU' => ['country' => 'jp', 'team' => 'Racing Bulls'],
  'GAS' => ['country' => 'fr', 'team' => 'Alpine'],
  'OCO' => ['country' => 'fr', 'team' => 'Haas'],
  'BEA' => ['country' => 'gb', 'team' => 'Haas'],
  'DOO' => ['country' => 'au', 'team' => 'Alpine'],
  'ALO' => ['country' => 'es', 'team' => 'Aston Martin'],
  'BOR' => ['country' => 'br', 'team' => 'Kick Sauber'],
  'HAD' => ['country' => 'fr', 'team' => 'Racing Bulls'],
  'LAW' => ['country' => 'nz', 'team' => 'Red Bull Racing'],
  'SAI' => ['country' => 'es', 'team' => 'Williams'],
];


$driver_labels = [];
$driver_points = [];
$bar_colors = [];

foreach ($driver_stats as $driver => $data) {
  $driver_labels[] = $driver;
  $driver_points[] = $data['points'];
  $team = $data['constructor'];
  $bar_colors[] = $team_colors[$team] ?? $team_colors['Unknown'];
  $constructor = $data['constructor'] ?? 'Unknown';
  $constructors[$constructor] = true;
  $driver_points_map[$constructor][$driver] = $data['points'];
}
$wcc_labels = [];
$wcc_points = [];
$wcc_colors = [];

arsort($constructor_stats); // sort descending

foreach ($constructor_stats as $constructor => $points) {
  $wcc_labels[] = $constructor;
  $wcc_points[] = $points;
  $wcc_colors[] = $team_colors[$constructor] ?? '#888888';
}
?>

<div class="container mt-5">
<h1 class="text-light">World Drivers Championship</h1>
<canvas id="wdcChart" height="150"></canvas>
<script>
  const ctx = document.getElementById('wdcChart').getContext('2d');
  const wdcChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($driver_labels) ?>,
      datasets: [{
        label: 'Points',
        data: <?= json_encode($driver_points) ?>,
        backgroundColor: <?= json_encode($bar_colors) ?>,
        borderWidth: 1
      }]
    },
    options: {
      indexAxis: 'y',
      scales: {
        x: {
          ticks: { color: '#fff' },
          grid: { color: '#444' }
        },
        y: {
          ticks: { color: '#fff' },
          grid: { color: '#444' }
        }
      }
    }
  });
</script>


<h2 class="text-light mt-5">World Constructors Championship</h2>
<canvas id="wccQuickChart" height="100"></canvas>
<script>
const wccQuickCtx = document.getElementById('wccQuickChart').getContext('2d');
new Chart(wccQuickCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($wcc_labels) ?>,
    datasets: [{
      label: 'Points',
      data: <?= json_encode($wcc_points) ?>,
      backgroundColor: <?= json_encode($wcc_colors) ?>,
      borderRadius: 4
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => `${ctx.dataset.label}: ${ctx.raw} pts`
        }
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: { color: '#fff' },
        grid: { color: '#444' }
      },
      y: {
        ticks: {
          color: '#fff',
          callback: function(value, index) {
            const teamName = this.getLabelForValue(value);
            const slug = teamName.toLowerCase().replaceAll(' ', '-');
            return '   ' + teamName; // space buffer for logo
          }
        },
        grid: { color: '#444' }
      }
    }
  }
});
</script>

  <ul class="nav nav-tabs mt-5" id="raceTabs" role="tablist">
    <?php foreach ($race_files as $i => $race): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $i === 0 ? 'active' : '' ?>" id="tab-<?= $i ?>" data-bs-toggle="tab" data-bs-target="#race-<?= $i ?>" type="button" role="tab">
          <?= htmlspecialchars($race['name']) ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content" id="raceTabsContent">
    <?php foreach ($race_files as $i => $race):
      $lap_stats = computeLapStats($race['path']); 
      $rows = array_map(fn($line) => str_getcsv($line, ",", '"', "\\"), file($race['path']));
      $headers = array_map('strtolower', array_map('trim', $rows[0]));
      $data_rows = array_slice($rows, 1);

      $top_driver_code = null;
      foreach ($data_rows as $row) {
          if (count($row) !== count($headers)) continue;
          $data = array_combine($headers, $row);
          if (isset($data['position']) && (int)$data['position'] === 1) {
              $top_driver_code = strtoupper($data['driver']);
              break;
          }
      }

      $best_lap_time = INF;
      $best_lap_driver = null;

      // loop through all best laps
      foreach ($lap_stats as $driver => $data) {
          $lap = strip_tags($data['best']); // in case "DNF" is wrapped in HTML
          if (!preg_match('/[0-9]+:[0-9.]+/', $lap)) continue; // skip invalid
      
          $seconds = lapTimeToSeconds($lap);
          if ($seconds < $best_lap_time) {
              $best_lap_time = $seconds;
              $best_lap_driver = strtoupper($driver);
          }
      }
      ?>
      <?php 
  $podium = getPodiumData($race['path'], $driver_info);
?>
      <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="race-<?= $i ?>" role="tabpanel">
        <h3 class="text-light mt-4">Lap Stats for <?= htmlspecialchars($race['name']) ?></h3>
        <div class="d-flex justify-content-center align-items-end gap-4 mt-4 mb-5 podium">
        <?php foreach ($podium as $pos => $info): 
          $isWinner = $info['position'] === 1;
        ?>
          <div class="text-center podium-spot podium-<?= $pos ?>">
            <div class="podium-photo-wrapper position-relative d-inline-block">
              <img src="<?= $info['headshot'] ?>" class="rounded-circle mb-2" style="height: 80px;">
              <?php if ($isWinner): ?>
                <span class="crown-emoji">👑</span>
              <?php endif; ?>
            </div>
              
            <h6 class="text-light mb-0"><?= $info['code'] ?></h6>
            <small class="text-muted">P<?= $info['position'] ?></small><br>
            <span class="text-info"><?= $info['final_lap'] ?></span><br>
              
            <?php if ($isWinner): ?>
              <small class="text-warning fw-bold">WINNER</small>
            <?php elseif (!empty($info['gap'])): ?>
              <small class="text-warning"><?= $info['gap'] ?></small>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        </div>
        <table class="table table-dark table-bordered">
          <thead><tr><th>Driver</th><th>Best Lap</th><th>Worst Lap</th><th>Final Time</th><th>Gap</th><th>Constructor</th></tr></thead>
          <tbody>
          <?php foreach ($lap_stats as $driver => $data): 
            $code = strtoupper($driver); // assuming 'NOR', 'VER', etc.
            // Use fallback values safely
            $default_info = [
              'name' => $driver,
              'country' => 'xx',
              'team' => 'Unknown'
            ];
            $info = array_merge($default_info, $driver_info[$code] ?? []);
          
            $flag_path = "assets/flags/{$info['country']}.png";
            $logo_path = "assets/logos/" . strtolower(str_replace(' ', '-', $info['team'])) . ".avif";
          ?>
              <tr class="<?= strtoupper($driver) === $top_driver_code ? 'table-success' : '' ?>">
                <td>
                  <img src="<?= $flag_path ?>" alt="<?= $info['country'] ?>" class="flag-icon me-1" style="height: 25px">
                  <?= $info['name'] ?>
                  <?php if (strtoupper($driver) === $best_lap_driver): ?>
                    <span title="Fastest Lap"> 🕑</span>
                  <?php endif; ?>
                  <?php if (strtoupper($driver) === $top_driver_code): ?>
                    <span title='Winner'> 🏆</span>
                  <?php endif; ?>
                </td>
                <td class="<?= strtoupper($driver) === $best_lap_driver ? 'best-lap' : '' ?>">
                  <?= $data['best'] ?>
                </td>
                <td><?= $data['worst'] ?></td>
                <td><?= $data['finalTime'] ?></td>
                <td>
                <?php
                  $isWinner = strtoupper($driver) === $top_driver_code;
                  $isDNF = strip_tags($data['finalTime']) === 'DNF';

                  echo ($isWinner || $isDNF) ? '~' : '+' . $data['gap'] . ' s';
                ?>
                </td>
                <td>
                  <img src="<?= $logo_path ?>" alt="<?= $info['team'] ?>" style="height: 25px">
                  <?= $info['team'] ?>
                </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>