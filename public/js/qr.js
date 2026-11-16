// FatecPoll — gerador de QR Code (JS puro, sem dependências). Modo byte (UTF-8), correção de erros M, versões 1 a 10.
// Uso: FPQr.encode(texto) → { size, get(x, y) }   |   FPQr.svg(texto, { border, fg, bg })   |   FPQr.canvas(texto, { scale, border })
(function (root) {
  'use strict';

  // Por versão (índice = versão): codewords de correção por bloco e nº de blocos — nível M
  var ECC_PER_BLOCK = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
  var NUM_BLOCKS    = [0,  1,  1,  1,  2,  2,  4,  4,  4,  5,  5];
  var MAX_VERSION = 10;

  function rawModules(v) {
    var r = (16 * v + 128) * v + 64;
    if (v >= 2) { var a = Math.floor(v / 7) + 2; r -= (25 * a - 10) * a - 55; if (v >= 7) r -= 36; }
    return r;
  }
  function dataCodewords(v) { return Math.floor(rawModules(v) / 8) - ECC_PER_BLOCK[v] * NUM_BLOCKS[v]; }

  function utf8(str) {
    var out = [], s = unescape(encodeURIComponent(str));
    for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
    return out;
  }

  // ---- Reed-Solomon em GF(256), polinômio 0x11D ----
  function gfMul(x, y) {
    var z = 0;
    for (var i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; }
    return z & 0xFF;
  }
  function rsDivisor(deg) {
    var r = []; for (var i = 0; i < deg - 1; i++) r.push(0); r.push(1);
    var root = 1;
    for (var k = 0; k < deg; k++) {
      for (var j = 0; j < r.length; j++) { r[j] = gfMul(r[j], root); if (j + 1 < r.length) r[j] ^= r[j + 1]; }
      root = gfMul(root, 2);
    }
    return r;
  }
  function rsRemainder(data, div) {
    var r = div.map(function () { return 0; });
    data.forEach(function (b) {
      var f = b ^ r.shift(); r.push(0);
      div.forEach(function (c, i) { r[i] ^= gfMul(c, f); });
    });
    return r;
  }

  function buildCodewords(bytes, ver) {
    var cap = dataCodewords(ver), bits = [];
    function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    put(4, 4); put(bytes.length, ver >= 10 ? 16 : 8);
    bytes.forEach(function (b) { put(b, 8); });
    put(0, Math.min(4, cap * 8 - bits.length));
    put(0, (8 - bits.length % 8) % 8);
    for (var pad = 0xEC; bits.length < cap * 8; pad ^= 0xEC ^ 0x11) put(pad, 8);
    var data = [];
    for (var i = 0; i < bits.length; i += 8) {
      var v = 0; for (var j = 0; j < 8; j++) v = (v << 1) | bits[i + j]; data.push(v);
    }
    // blocos + correção de erros + intercalação
    var nb = NUM_BLOCKS[ver], eb = ECC_PER_BLOCK[ver], raw = Math.floor(rawModules(ver) / 8);
    var shortBlocks = nb - raw % nb, shortLen = Math.floor(raw / nb), div = rsDivisor(eb), blocks = [], k = 0;
    for (var b = 0; b < nb; b++) {
      var len = shortLen - eb + (b < shortBlocks ? 0 : 1);
      var d = data.slice(k, k + len); k += len;
      var ecc = rsRemainder(d, div);
      if (b < shortBlocks) d.push(0);
      blocks.push(d.concat(ecc));
    }
    var out = [];
    for (var col = 0; col < blocks[0].length; col++) {
      blocks.forEach(function (blk, bi) { if (col !== shortLen - eb || bi >= shortBlocks) out.push(blk[col]); });
    }
    return out;
  }

  // ---- Matriz ----
  function Matrix(ver) {
    this.ver = ver; this.size = ver * 4 + 17;
    this.m = []; this.fn = [];
    for (var y = 0; y < this.size; y++) { this.m.push(new Array(this.size).fill(false)); this.fn.push(new Array(this.size).fill(false)); }
  }
  Matrix.prototype.set = function (x, y, dark) { this.m[y][x] = dark; this.fn[y][x] = true; };
  Matrix.prototype.finder = function (cx, cy) {
    for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
      var d = Math.max(Math.abs(dx), Math.abs(dy)), x = cx + dx, y = cy + dy;
      if (x >= 0 && x < this.size && y >= 0 && y < this.size) this.set(x, y, d !== 2 && d !== 4);
    }
  };
  Matrix.prototype.align = function (cx, cy) {
    for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) this.set(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
  };
  Matrix.prototype.alignPositions = function () {
    var v = this.ver; if (v === 1) return [];
    var n = Math.floor(v / 7) + 2, step = v === 32 ? 26 : Math.ceil((v * 4 + 4) / (n * 2 - 2)) * 2, r = [6];
    for (var p = this.size - 7; r.length < n; p -= step) r.splice(1, 0, p);
    return r;
  };
  Matrix.prototype.drawFunctions = function () {
    var s = this.size, i;
    for (i = 0; i < s; i++) { this.set(6, i, i % 2 === 0); this.set(i, 6, i % 2 === 0); }
    this.finder(3, 3); this.finder(s - 4, 3); this.finder(3, s - 4);
    var pos = this.alignPositions(), n = pos.length;
    for (var a = 0; a < n; a++) for (var b = 0; b < n; b++) {
      if (!((a === 0 && b === 0) || (a === 0 && b === n - 1) || (a === n - 1 && b === 0))) this.align(pos[a], pos[b]);
    }
    this.format(0); // reserva a área (o valor final é desenhado depois da máscara)
    if (this.ver >= 7) {
      var rem = this.ver; for (i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
      var bits = (this.ver << 12) | rem;
      for (i = 0; i < 18; i++) {
        var bit = ((bits >>> i) & 1) === 1, x = s - 11 + i % 3, y = Math.floor(i / 3);
        this.set(x, y, bit); this.set(y, x, bit);
      }
    }
  };
  Matrix.prototype.format = function (mask) {
    var data = (0 << 3) | mask, rem = data, i, s = this.size; // 0 = nível M
    for (i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    var bits = ((data << 10) | rem) ^ 0x5412, bit = function (k) { return ((bits >>> k) & 1) === 1; };
    for (i = 0; i <= 5; i++) this.set(8, i, bit(i));
    this.set(8, 7, bit(6)); this.set(8, 8, bit(7)); this.set(7, 8, bit(8));
    for (i = 9; i < 15; i++) this.set(14 - i, 8, bit(i));
    for (i = 0; i < 8; i++) this.set(s - 1 - i, 8, bit(i));
    for (i = 8; i < 15; i++) this.set(8, s - 15 + i, bit(i));
    this.set(8, s - 8, true); // módulo escuro fixo
  };
  Matrix.prototype.place = function (cw) {
    var s = this.size, k = 0;
    for (var right = s - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var v = 0; v < s; v++) for (var j = 0; j < 2; j++) {
        var x = right - j, up = ((right + 1) & 2) === 0, y = up ? s - 1 - v : v;
        if (!this.fn[y][x] && k < cw.length * 8) { this.m[y][x] = ((cw[k >>> 3] >>> (7 - (k & 7))) & 1) === 1; k++; }
      }
    }
  };
  Matrix.prototype.mask = function (mask) {
    for (var y = 0; y < this.size; y++) for (var x = 0; x < this.size; x++) {
      var inv;
      switch (mask) {
        case 0: inv = (x + y) % 2 === 0; break;
        case 1: inv = y % 2 === 0; break;
        case 2: inv = x % 3 === 0; break;
        case 3: inv = (x + y) % 3 === 0; break;
        case 4: inv = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
        case 5: inv = x * y % 2 + x * y % 3 === 0; break;
        case 6: inv = (x * y % 2 + x * y % 3) % 2 === 0; break;
        default: inv = ((x + y) % 2 + x * y % 3) % 2 === 0;
      }
      if (!this.fn[y][x] && inv) this.m[y][x] = !this.m[y][x];
    }
  };
  Matrix.prototype.penalty = function () {
    var s = this.size, m = this.m, p = 0, x, y, run, i;
    function line(get) {
      var runC = false, runL = 0, hist = [0, 0, 0, 0, 0, 0, 0], pen = 0;
      function addHist(len) { if (hist[0] === 0) len += s; hist.pop(); hist.unshift(len); }
      function core() { var n = hist[1], c = n > 0 && hist[2] === n && hist[3] === n * 3 && hist[4] === n && hist[5] === n; return (c && hist[0] >= n * 4 && hist[6] >= n ? 1 : 0) + (c && hist[6] >= n * 4 && hist[0] >= n ? 1 : 0); }
      for (var i = 0; i < s; i++) {
        var c = get(i);
        if (c === runC) { runL++; if (runL === 5) pen += 3; else if (runL > 5) pen++; }
        else { addHist(runL); if (!runC) pen += core() * 40; runC = c; runL = 1; }
      }
      addHist(runL); if (runC) { pen += core() * 40; runC = false; runL = 0; }
      runL += s; addHist(runL); pen += core() * 40;
      return pen;
    }
    for (y = 0; y < s; y++) p += line(function (i) { return m[y][i]; });
    for (x = 0; x < s; x++) p += line(function (i) { return m[i][x]; });
    for (y = 0; y < s - 1; y++) for (x = 0; x < s - 1; x++) {
      var c = m[y][x]; if (c === m[y][x + 1] && c === m[y + 1][x] && c === m[y + 1][x + 1]) p += 3;
    }
    var dark = 0; for (y = 0; y < s; y++) for (x = 0; x < s; x++) if (m[y][x]) dark++;
    var total = s * s; p += (Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1) * 10;
    return p;
  };

  function encode(text, forceMask) { // forceMask: só para testes
    var bytes = utf8(String(text)), ver;
    for (ver = 1; ver <= MAX_VERSION; ver++) {
      var need = 4 + (ver >= 10 ? 16 : 8) + bytes.length * 8;
      if (need <= dataCodewords(ver) * 8) break;
    }
    if (ver > MAX_VERSION) throw new Error('Texto longo demais para o QR Code (máx. ' + (dataCodewords(MAX_VERSION) - 3) + ' bytes).');
    var cw = buildCodewords(bytes, ver), best = null, bestPen = Infinity;
    for (var mask = 0; mask < 8; mask++) {
      if (forceMask != null && mask !== forceMask) continue;
      var mx = new Matrix(ver); mx.drawFunctions(); mx.place(cw); mx.mask(mask); mx.format(mask);
      var pen = mx.penalty(); if (pen < bestPen) { bestPen = pen; best = mx; }
    }
    return { size: best.size, version: ver, get: function (x, y) { return x >= 0 && y >= 0 && x < best.size && y < best.size && best.m[y][x]; } };
  }

  function svg(text, o) {
    o = o || {}; var q = encode(text), b = o.border == null ? 4 : o.border, d = [];
    for (var y = 0; y < q.size; y++) for (var x = 0; x < q.size; x++) if (q.get(x, y)) d.push('M' + (x + b) + ',' + (y + b) + 'h1v1h-1z');
    var n = q.size + b * 2;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + n + ' ' + n + '" shape-rendering="crispEdges">'
      + '<rect width="100%" height="100%" fill="' + (o.bg || '#ffffff') + '"/>'
      + '<path d="' + d.join('') + '" fill="' + (o.fg || '#000000') + '"/></svg>';
  }

  function canvas(text, o) {
    o = o || {}; var q = encode(text), b = o.border == null ? 4 : o.border, sc = o.scale || 10, n = (q.size + b * 2) * sc;
    var c = document.createElement('canvas'); c.width = c.height = n;
    var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, n, n); g.fillStyle = '#000';
    for (var y = 0; y < q.size; y++) for (var x = 0; x < q.size; x++) if (q.get(x, y)) g.fillRect((x + b) * sc, (y + b) * sc, sc, sc);
    return c;
  }

  var api = { encode: encode, svg: svg, canvas: canvas };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else root.FPQr = api;
})(this);
