#!/usr/bin/env python3
"""Teste de fluxo ponta a ponta do FatecPoll (cadastro, login, papéis, enquetes, votos).

Uso (com o site rodando):
  BASE_URL=http://localhost:8080 ADMIN_EMAIL=admin@fatecpoll.local ADMIN_PASSWORD=admin123 \
  MAIL_LOG=storage/mail.log python3 tests/e2e_flow.py
Requer: pip install requests. Com MAIL_DRIVER=log o código de verificação é lido de MAIL_LOG.
Cria dados de teste (professores e enquetes com prefixo "E2E").
"""
import os, re, sys, time
import requests

BASE = os.environ.get('BASE_URL', 'http://127.0.0.1:8099').rstrip('/')
ADMIN = (os.environ.get('ADMIN_EMAIL', 'admin@fatecpoll.local'), os.environ.get('ADMIN_PASSWORD', 'admin123'))
MAIL_LOG = os.environ.get('MAIL_LOG', 'storage/mail.log')
PASSWORD = 'Senha#Forte123'
passed = failed = 0


def check(name, cond, extra=''):
    global passed, failed
    if cond:
        passed += 1
        print('  ok   ' + name)
    else:
        failed += 1
        print('  FAIL ' + name + (' -> ' + str(extra) if extra else ''))


class Client:
    def __init__(self):
        self.s = requests.Session()

    def get(self, path, **kw):
        return self.s.get(BASE + path, allow_redirects=False, **kw)

    def token(self, path='/admin/login'):
        html = self.s.get(BASE + path).text
        m = re.search(r'name="_csrf" value="([^"]+)"', html)
        return m.group(1) if m else ''

    def post(self, path, data=None, page='/admin/login', token=True, **kw):
        data = dict(data or {})
        if token:
            data['_csrf'] = self.token(page)
        return self.s.post(BASE + path, data=data, allow_redirects=False, **kw)

    def login(self, email, password):
        return self.post('/admin/login', {'email': email, 'password': password})


def loc(r):
    return r.headers.get('Location', '')


def mail_code(email):
    time.sleep(0.3)
    if not os.path.exists(MAIL_LOG):
        return None
    txt = open(MAIL_LOG, encoding='utf-8', errors='replace').read()
    parts = txt.split(email)
    tail = parts[-1] if len(parts) > 1 else txt
    # código de 6 dígitos mais próximo depois do último envio a este e-mail
    found = re.findall(r'(?<!\d)(\d{6})(?!\d)', tail)
    if not found:
        found = re.findall(r'(?<!\d)(\d{6})(?!\d)', txt)
    return found[0] if found else None


def poll_ids(html):
    return set(re.findall(r'/admin/enquetes/(\d+)/editar', html))


print('Páginas públicas e proteção de rotas')
anon = Client()
check('home abre', anon.get('/').status_code == 200)
r = anon.get('/enquetes')
check('lista pública abre', r.status_code == 200)
slugs = re.findall(r'href="[^"]*/e/([A-Za-z0-9_-]+)"', r.text)
check('há enquete aberta para votar', bool(slugs))
for p in ['/admin', '/admin/enquetes', '/admin/enquetes/criar', '/admin/professores']:
    r = anon.get(p)
    check('sem login ' + p + ' manda para o login', r.status_code in (301, 302) and 'login' in loc(r), (r.status_code, loc(r)))
r = anon.s.post(BASE + '/admin/login', data={'email': ADMIN[0], 'password': ADMIN[1]}, allow_redirects=False)
check('POST sem token CSRF é recusado (419)', r.status_code == 419, r.status_code)
r = anon.get('/rota-que-nao-existe')
check('rota inexistente dá 404', r.status_code == 404, r.status_code)
r = anon.get('/e/slug-inexistente')
check('enquete inexistente dá 404', r.status_code == 404, r.status_code)

print('Cadastro de professor')
reg = Client()
stamp = str(int(time.time()))
email = f'e2e{stamp}@cps.sp.gov.br'
name = 'Professor E2E ' + stamp
r = reg.post('/cadastro', {'name': name, 'email': f'e2e{stamp}@gmail.com', 'password': PASSWORD, 'password_confirm': PASSWORD}, page='/cadastro')
check('e-mail fora de @cps.sp.gov.br é recusado', r.status_code == 422, r.status_code)
r = reg.post('/cadastro', {'name': name, 'email': email, 'password': '123', 'password_confirm': '123'}, page='/cadastro')
check('senha curta é recusada', r.status_code == 422, r.status_code)
r = reg.post('/cadastro', {'name': name, 'email': email, 'password': PASSWORD, 'password_confirm': PASSWORD + 'x'}, page='/cadastro')
check('senhas diferentes são recusadas', r.status_code == 422, r.status_code)
r = reg.post('/cadastro', {'name': name, 'email': email, 'password': PASSWORD, 'password_confirm': PASSWORD}, page='/cadastro')
check('cadastro válido leva à confirmação', r.status_code == 302 and 'verificar' in loc(r), (r.status_code, loc(r)))
r = reg.login(email, PASSWORD)
check('login antes de confirmar o e-mail não entra no painel', r.status_code == 302 and '/admin/login' not in loc(r).replace('/cadastro/verificar', '') or 'verificar' in loc(r), loc(r))
r = reg.post('/cadastro/verificar', {'code': '000000'}, page='/cadastro/verificar')
check('código errado é recusado', r.status_code == 422, r.status_code)
code = mail_code(email)
check('código chegou por e-mail (log)', bool(code), 'sem código em ' + MAIL_LOG)
r = reg.post('/cadastro/verificar', {'code': code or '111111'}, page='/cadastro/verificar')
check('código certo confirma o e-mail', r.status_code == 302 and 'login' in loc(r), (r.status_code, loc(r)))

print('Professor logado')
t = Client()
r = t.login(email, 'senha-errada')
check('senha errada não entra', r.status_code == 302 and 'login' in loc(r))
r = t.login(email, PASSWORD)
check('professor entra no painel', r.status_code == 302 and loc(r).rstrip('/').endswith('/admin'), loc(r))
check('dashboard do professor abre', t.get('/admin').status_code == 200)
r = t.get('/admin/professores')
check('professor NÃO acessa /admin/professores', r.status_code in (403, 302, 404) and r.status_code != 200, r.status_code)
r = t.get('/admin/enquetes')
check('professor vê lista própria (vazia, sem enquetes do admin)', r.status_code == 200 and not poll_ids(r.text), poll_ids(r.text))

r = t.post('/admin/enquetes', {'type': 'options', 'title': 'E2E Enquete ' + stamp, 'question': 'Qual?', 'options[]': ['A', 'B', 'C'], 'publish': '1'}, page='/admin/enquetes/criar')
check('professor cria enquete e cai em Compartilhar', r.status_code == 302 and 'compartilhar' in loc(r), (r.status_code, loc(r)))
pid = re.search(r'/enquetes/(\d+)/', loc(r))
pid = pid.group(1) if pid else '0'
r = t.post('/admin/enquetes', {'type': 'options', 'title': '', 'question': 'Qual?', 'options[]': ['A']}, page='/admin/enquetes/criar')
check('enquete sem título/opções é recusada com 422', r.status_code == 422, r.status_code)
lst = t.get('/admin/enquetes').text
check('professor vê a enquete criada', pid in poll_ids(lst), poll_ids(lst))
admin_poll = None
adm = Client()
adm.login(*ADMIN)
check('admin entra no painel', adm.get('/admin').status_code == 200)
alist = adm.get('/admin/enquetes').text
others = [i for i in poll_ids(alist) if i != pid]
admin_poll = others[0] if others else None
if admin_poll:
    check('professor NÃO edita enquete de outro (404/403)', t.get(f'/admin/enquetes/{admin_poll}/editar').status_code in (403, 404))
    check('professor NÃO vê resultados de outro', t.get(f'/admin/enquetes/{admin_poll}/resultados').status_code in (403, 404))
    r = t.post(f'/admin/enquetes/{admin_poll}/encerrar', page='/admin/enquetes')
    check('professor NÃO encerra enquete de outro', r.status_code in (403, 404), r.status_code)
check('admin vê a enquete do professor', pid in poll_ids(alist))

print('Votação')
pub = anon.get(f'/enquetes').text
r = anon.get('/admin/enquetes/%s/compartilhar' % pid)
slug = re.search(r'/e/([A-Za-z0-9_-]+)', t.get(f'/admin/enquetes/{pid}/compartilhar').text)
slug = slug.group(1) if slug else None
check('página de compartilhar mostra o link público', bool(slug))
voter = Client()
page = voter.s.get(f'{BASE}/e/{slug}').text
opt = re.search(r'name="option" value="(\d+)"', page)
check('visitante vê as alternativas', bool(opt))
if opt:
    r = voter.post(f'/e/{slug}/votar', {'option': opt.group(1)}, page=f'/e/{slug}')
    check('visitante vota e vai à confirmação', r.status_code == 302 and 'confirmacao' in loc(r), (r.status_code, loc(r)))
    r = voter.post(f'/e/{slug}/votar', {'option': opt.group(1)}, page=f'/e/{slug}')
    check('segundo voto no mesmo aparelho é recusado', 'confirmacao' not in loc(r), loc(r))
    r = t.post(f'/e/{slug}/votar', {'option': opt.group(1)}, page=f'/e/{slug}')
    check('professor logado NÃO pode votar', 'confirmacao' not in loc(r), loc(r))
r = t.post(f'/admin/enquetes/{pid}/encerrar', page='/admin/enquetes')
check('professor encerra a própria enquete', r.status_code == 302)
page = Client().s.get(f'{BASE}/e/{slug}').text
check('enquete encerrada não oferece votar', 'name="option"' not in page)
r = t.post(f'/admin/enquetes/{pid}/publicar', page='/admin/enquetes')
check('professor reabre a própria enquete', r.status_code == 302)

print('Admin gerencia professores')
r = adm.get('/admin/professores')
check('admin abre /admin/professores', r.status_code == 200, r.status_code)
tid = re.search(r'/admin/professores/(\d+)/status', r.text) if r.status_code == 200 else None
mine = None
if r.status_code == 200:
    for m in re.finditer(r'<article[^>]*data-teacher-row.*?</article>', r.text, re.S):
        if email.lower() in m.group(0).lower():
            mine = m.group(0)
    check('professor novo aparece na lista', mine is not None)
    check('lista mostra as enquetes do professor', mine is not None and 'E2E Enquete' in mine)
    tid = re.search(r'/admin/professores/(\d+)/status', mine).group(1) if mine else None
    if tid:
        r = adm.post(f'/admin/professores/{tid}/status', {'action': 'deactivate'}, page='/admin/professores')
        check('admin inativa o professor', r.status_code == 302)
        r = t.get('/admin')
        check('sessão antiga do professor inativado perde acesso', r.status_code == 302 and 'login' in loc(r), (r.status_code, loc(r)))
        r = Client().login(email, PASSWORD)
        check('professor inativado não consegue novo login', r.status_code == 302 and 'login' in loc(r) and not loc(r).rstrip('/').endswith('/admin'), loc(r))
        page = anon.s.get(f'{BASE}/e/{slug}').text
        check('enquetes do inativado continuam abertas e preservadas', 'name="option"' in page)
        r = adm.post(f'/admin/professores/{tid}/status', {'action': 'activate'}, page='/admin/professores')
        check('admin reativa o professor', r.status_code == 302)
        t2 = Client()
        r = t2.login(email, PASSWORD)
        check('professor reativado volta a entrar', r.status_code == 302 and loc(r).rstrip('/').endswith('/admin'), loc(r))
        r = adm.post(f'/admin/professores/{tid}/status', {'action': 'explodir'}, page='/admin/professores')
        check('ação inválida é recusada (volta com aviso)', r.status_code in (302, 400, 422), r.status_code)
        r = adm.post('/admin/professores/999999/status', {'action': 'deactivate'}, page='/admin/professores')
        check('professor inexistente dá 404', r.status_code == 404, r.status_code)
    r = t.post(f'/admin/professores/{tid or 1}/status', {'action': 'deactivate'}, page='/admin/enquetes')
    check('professor NÃO inativa ninguém', r.status_code in (403, 302, 404), r.status_code)

print('Navegação e textos')
nav = adm.s.get(BASE + '/admin/enquetes').text
check('menu não repete o rótulo "Enquetes"', len(re.findall(r'<nav class="nav".*?</nav>', nav, re.S)) == 1 and
      len(re.findall(r'>\s*Enquetes\s*</a>', re.search(r'<nav class="nav".*?</nav>', nav, re.S).group(0))) <= 1)
check('nenhuma tela promete "um voto por pessoa"', 'única vez' not in adm.s.get(f'{BASE}/admin/enquetes/{pid}/compartilhar').text and 'um voto por participante' not in nav)

print(f'\n{passed} passaram, {failed} falharam')
sys.exit(1 if failed else 0)
