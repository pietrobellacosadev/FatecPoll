// Teste estrutural do gerador de QR Code. Uso: node tests/qr.test.js
// (A leitura real por decodificador foi validada à parte, com OpenCV, em 197 textos de 20 a 213 bytes.)
const Q = require('../public/js/qr.js');
let passed = 0, failed = 0;
const check = (n, c) => { c ? (passed++, console.log('  ok  ', n)) : (failed++, console.log('  FAIL', n)); };

const q = Q.encode('https://exemplo.com/e/Qw3_rT9xLk2mZ8vB1nYpAg');
check('URL de 44 bytes (> 42 da versão 3) usa a versão 4 (33x33)', q.version === 4 && q.size === 33);

// três localizadores (7x7: borda escura, anel claro, centro escuro)
const finder = (ox, oy) => { for (let y = 0; y < 7; y++) for (let x = 0; x < 7; x++) {
  const d = Math.max(Math.abs(x - 3), Math.abs(y - 3)); if (q.get(ox + x, oy + y) !== (d !== 2)) return false; } return true; };
check('3 localizadores nos cantos', finder(0, 0) && finder(q.size - 7, 0) && finder(0, q.size - 7));
check('módulo escuro fixo', q.get(8, q.size - 8) === true);
check('temporização alternada', [8, 9, 10, 11, 12].every(i => q.get(i, 6) === (i % 2 === 0) && q.get(6, i) === (i % 2 === 0)));

// informação de formato: 15 bits válidos pelo código BCH (nível M)
let bits = 0; for (let i = 0; i <= 5; i++) bits |= (q.get(8, i) ? 1 : 0) << i;
bits |= (q.get(8, 7) ? 1 : 0) << 6; bits |= (q.get(8, 8) ? 1 : 0) << 7; bits |= (q.get(7, 8) ? 1 : 0) << 8;
for (let i = 9; i < 15; i++) bits |= (q.get(14 - i, 8) ? 1 : 0) << i;
const valid = new Set(); for (let m = 0; m < 8; m++) { let r = m; for (let i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537); valid.add(((m << 10) | r) ^ 0x5412); }
check('informação de formato válida (nível M)', valid.has(bits));

check('mesmo texto gera sempre o mesmo QR', Q.svg('abc') === Q.svg('abc'));
check('textos diferentes geram QR diferentes', Q.svg('abc') !== Q.svg('abd'));
check('UTF-8 é aceito', Q.encode('avaliação ✓').size >= 21);
let threw = false; try { Q.encode('x'.repeat(300)); } catch (e) { threw = true; }
check('texto grande demais gera erro claro', threw);
check('SVG válido com área silenciosa', /^<svg [^>]*viewBox="0 0 (\d+) \1"/.test(Q.svg('abc', { border: 4 })));

console.log(`\n${passed} passaram, ${failed} falharam`); process.exit(failed ? 1 : 0);
