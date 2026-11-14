// FatecPoll — gráficos interativos com Apache ECharts (carregado por CDN em layout.php).
// Lê os dados de <script type="application/json"> e acompanha o tema claro/escuro do site.
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-chart]'));
  if (!boxes.length) return;

  function css(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }

  function palette() {
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
      dark: dark,
      ink: css('--ink') || '#0b1220',
      soft: css('--ink-soft') || '#5a667a',
      line: css('--line') || '#dce4f0',
      surface: css('--surface') || '#ffffff',
      colors: dark
        ? ['#60a5fa', '#38bdf8', '#818cf8', '#2dd4bf', '#fbbf24', '#94a3b8']
        : ['#1d4ed8', '#0ea5e9', '#6366f1', '#14b8a6', '#f59e0b', '#64748b']
    };
  }

  // Opções comuns (texto, grade, tooltip) já no tema atual
  function base(t) {
    return {
      color: t.colors,
      backgroundColor: 'transparent',
      animation: !reduced,
      animationDuration: 800,
      textStyle: { fontFamily: css('--font-body') || 'sans-serif', color: t.ink },
      tooltip: { backgroundColor: t.surface, borderColor: t.line, textStyle: { color: t.ink }, confine: true },
      legend: { textStyle: { color: t.soft }, top: 0 }
    };
  }
  function axisStyle(t) {
    return {
      axisLine: { lineStyle: { color: t.line } }, axisTick: { show: false },
      axisLabel: { color: t.soft }, splitLine: { lineStyle: { color: t.line } }
    };
  }
  function short(s) { return s.length > 22 ? s.slice(0, 21) + '…' : s; }
  function merge(a, b) { for (var k in b) a[k] = b[k]; return a; }

  var builders = {
    // ----- Catálogo -----
    ranking: function (d, t) {
      var games = d.games.slice().reverse(); // maior nota no topo
      return merge(base(t), {
        grid: { left: 8, right: 56, top: 16, bottom: 8, containLabel: true },
        tooltip: merge(base(t).tooltip, { trigger: 'axis', axisPointer: { type: 'shadow' },
          formatter: function (p) { var g = games[p[0].dataIndex]; return '<b>' + g.name + '</b><br>Nota geral: ' + g.score.toFixed(2).replace('.', ',') + ' de 5<br>' + g.votes + ' avaliação(ões)'; } }),
        xAxis: merge({ type: 'value', min: 0, max: 5 }, axisStyle(t)),
        yAxis: merge({ type: 'category', data: games.map(function (g) { return short(g.name); }) }, axisStyle(t)),
        series: [{ type: 'bar', data: games.map(function (g) { return g.score; }), barMaxWidth: 28,
          itemStyle: { borderRadius: [0, 8, 8, 0] },
          label: { show: true, position: 'right', color: t.ink, formatter: function (p) { return p.value.toFixed(2).replace('.', ','); } } }]
      });
    },
    questions: function (d, t) {
      var keys = ['q_time', 'q_fun', 'q_replay'];
      return merge(base(t), {
        grid: { left: 8, right: 8, top: 44, bottom: 8, containLabel: true },
        tooltip: merge(base(t).tooltip, { trigger: 'axis', axisPointer: { type: 'shadow' } }),
        xAxis: merge({ type: 'category', data: d.games.map(function (g) { return short(g.name); }) }, axisStyle(t)),
        yAxis: merge({ type: 'value', min: 0, max: 5 }, axisStyle(t)),
        series: keys.map(function (k) {
          return { name: d.labels[k], type: 'bar', barMaxWidth: 26, itemStyle: { borderRadius: [6, 6, 0, 0] },
            data: d.games.map(function (g) { return g[k]; }) };
        })
      });
    },
    radar: function (d, t) {
      var keys = ['q_time', 'q_fun', 'q_replay'];
      var games = d.games.slice(0, 8);
      return merge(base(t), {
        tooltip: merge(base(t).tooltip, { trigger: 'item' }),
        legend: merge(base(t).legend, { type: 'scroll', data: games.map(function (g) { return g.name; }) }),
        radar: { center: ['50%', '56%'], radius: '62%', indicator: keys.map(function (k) { return { name: d.labels[k], max: 5 }; }),
          axisName: { color: t.ink }, splitLine: { lineStyle: { color: t.line } }, splitArea: { show: false }, axisLine: { lineStyle: { color: t.line } } },
        series: [{ type: 'radar', areaStyle: { opacity: .12 },
          data: games.map(function (g) { return { name: g.name, value: keys.map(function (k) { return g[k]; }) }; }) }]
      });
    },
    // ----- Enquete de opções -----
    donut: function (d, t) {
      return merge(base(t), {
        tooltip: merge(base(t).tooltip, { trigger: 'item', formatter: '{b}<br>{c} voto(s) · {d}%' }),
        legend: merge(base(t).legend, { type: 'scroll', bottom: 0, top: 'auto' }),
        series: [{ type: 'pie', radius: ['46%', '72%'], center: ['50%', '44%'], avoidLabelOverlap: true,
          itemStyle: { borderColor: t.surface, borderWidth: 3, borderRadius: 8 },
          label: { color: t.ink, formatter: '{d}%' },
          emphasis: { scaleSize: 8 },
          data: d.options.filter(function (o) { return o.votes > 0; }).map(function (o) { return { name: o.name, value: o.votes }; }) }]
      });
    },
    bars: function (d, t) {
      var opts = d.options.slice().reverse();
      return merge(base(t), {
        grid: { left: 8, right: 48, top: 16, bottom: 8, containLabel: true },
        tooltip: merge(base(t).tooltip, { trigger: 'axis', axisPointer: { type: 'shadow' },
          formatter: function (p) { var o = opts[p[0].dataIndex]; var pct = d.total ? Math.round(o.votes / d.total * 100) : 0; return '<b>' + o.name + '</b><br>' + o.votes + ' voto(s) · ' + pct + '%'; } }),
        xAxis: merge({ type: 'value', minInterval: 1 }, axisStyle(t)),
        yAxis: merge({ type: 'category', data: opts.map(function (o) { return short(o.name); }) }, axisStyle(t)),
        series: [{ type: 'bar', barMaxWidth: 28, data: opts.map(function (o) { return o.votes; }),
          itemStyle: { borderRadius: [0, 8, 8, 0] }, label: { show: true, position: 'right', color: t.ink } }]
      });
    }
  };

  boxes.forEach(function (box) {
    var wrap = box.closest('[data-chart-wrap]');
    var tabs = wrap ? Array.prototype.slice.call(wrap.querySelectorAll('[data-chart-mode]')) : [];
    var fallback = wrap ? wrap.querySelector('.chart-fallback') : null;
    var dataEl = document.getElementById(box.dataset.chartSrc);
    var data; try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
    var mode = box.dataset.chartModeInitial;
    var chart = null;

    function render() {
      if (!chart) return;
      var empty = data.type === 'catalog' ? !data.games.length : !data.total;
      if (empty) { chart.clear(); return; }
      chart.setOption(builders[mode](data, palette()), true);
    }

    function ensure() {
      if (chart || !box.clientWidth) return; // oculto (ex.: aba do dashboard) → espera ficar visível
      if (!window.echarts) {
        if (fallback) fallback.hidden = false;
        box.hidden = true;
        return;
      }
      chart = window.echarts.init(box, null, { renderer: 'canvas' });
      render();
    }

    tabs.forEach(function (b) {
      b.addEventListener('click', function () {
        mode = b.dataset.chartMode;
        tabs.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        render();
      });
    });

    // O ECharts chega por CDN (defer): tenta de novo no load da página.
    var ro = new ResizeObserver(function () { if (chart) chart.resize(); else ensure(); });
    ro.observe(box);
    window.addEventListener('load', ensure);
    document.addEventListener('fatecpoll:theme', render);
  });
})();
