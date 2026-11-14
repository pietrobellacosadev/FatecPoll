<?php
/** Espera: $poll, $hl. Gráficos interativos (Apache ECharts) desenhados por public/js/charts.js. */
use App\Services\RankingService;

$chartId = 'chart-' . (int) $poll['id'] . '-' . substr(md5((string) ($poll['slug'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')), 0, 6);
$isCatalogChart = $poll['type'] === 'catalog';
$modes = $isCatalogChart
    ? ['ranking' => 'Classificação', 'questions' => 'Por pergunta', 'radar' => 'Radar']
    : ['donut' => 'Pizza', 'bars' => 'Barras'];
$first = array_key_first($modes);
?>
<div class="chart-card" data-chart-wrap>
  <div class="chart-tabs" role="group" aria-label="Tipo de gráfico">
    <?php foreach ($modes as $key => $label): ?>
      <button type="button" class="chip-btn" data-chart-mode="<?= e($key) ?>" aria-pressed="<?= $key === $first ? 'true' : 'false' ?>"><span><?= e($label) ?></span></button>
    <?php endforeach; ?>
  </div>
  <div class="chart" data-chart data-chart-src="<?= e($chartId) ?>" data-chart-mode-initial="<?= e($first) ?>" role="img"
       aria-label="Gráfico de resultados de <?= e($poll['title']) ?>"></div>
  <p class="chart-fallback" hidden>Não foi possível carregar a biblioteca de gráficos (Apache ECharts). Confira a conexão com a internet; a classificação em barras abaixo continua disponível.</p>
  <script type="application/json" id="<?= e($chartId) ?>"><?= json_encode(RankingService::chartData($poll, $hl), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
