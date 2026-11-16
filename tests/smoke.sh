#!/bin/sh
# Teste de fumaça: sobe o servidor embutido do PHP e verifica páginas que não dependem do banco.
# Uso: sh tests/smoke.sh
cd "$(dirname "$0")/.." || exit 1
PORT=${PORT:-8099}
php -S 127.0.0.1:$PORT -t public public/index.php >/dev/null 2>&1 &
PID=$!
sleep 1
fail=0
check() { # descrição, URL, código esperado, texto esperado
  body=$(curl -s -o /tmp/fp_body -w "%{http_code}" "http://127.0.0.1:$PORT$2")
  if [ "$body" = "$3" ] && grep -q "$4" /tmp/fp_body; then echo "  ok   $1"; else echo "  FAIL $1 (HTTP $body)"; fail=1; fi
}
check "login admin"                 /admin/login 200 "Administração"
check "tema + gráficos no layout"   /admin/login 200 "data-theme-toggle"
check "CSS separados no layout"     /admin/login 200 "dashboard.css"
check "css servido"                 /css/animations.css 200 "@keyframes"
check "js servido"                  /js/voting.js 200 "FatecPoll"
check "footer: copyright"           /admin/login 200 "FP Softwares. Todos os direitos reservados."
check "footer: link Autores"        /admin/login 200 "Autores</a>"
check "rota /autores existe"        /autores 200 "Autores"
check "gerador de QR servido"       /js/qr.js 200 "FPQr"
check "compartilhar (js) servido"   /js/share.js 200 "data-share"
kill $PID
exit $fail
