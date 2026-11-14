<div align="center">

<img src="public/img/logo.png" alt="FatecPoll — FP Softwares" width="132">

# FatecPoll

### Plataforma de enquetes e avaliação de jogos acadêmicos

Uma solução web para organizar avaliações de projetos desenvolvidos por estudantes, coletar opiniões de participantes e transformar respostas em indicadores visuais para apoiar decisões.

<p>
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white" alt="MariaDB 11.4">
  <img src="https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?logo=javascript&logoColor=black" alt="JavaScript">
  <img src="https://img.shields.io/badge/CSS3-3.0-1572B6?logo=css3&logoColor=white" alt="CSS3">
  <img src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white" alt="Docker Compose">
  <img src="https://img.shields.io/badge/Status-Em%20desenvolvimento-orange" alt="Em desenvolvimento">
</p>

</div>

---

## Visão geral

O **FatecPoll** é uma aplicação web desenvolvida para apoiar a criação e a gestão de enquetes, com foco na avaliação de jogos e projetos acadêmicos. A plataforma permite que professores ou administradores publiquem enquetes, compartilhem o acesso por link ou QR Code e acompanhem os resultados em um painel administrativo.

A participação é simplificada: o público não precisa criar uma conta para votar. Na modalidade de avaliação de jogos, cada participante pode atribuir notas aos projetos apresentados. Na modalidade de enquete, pode escolher uma alternativa entre as opções disponíveis.

## Funcionalidades

### Para participantes
- Acesso às enquetes por link público, sem cadastro de participante.
- Avaliação de jogos em três critérios, com notas de 1 a 5:
  - O tempo de jogo foi adequado?
  - A experiência foi divertida?
  - Você jogaria novamente?
- Enquetes de alternativas com seleção única.
- Etapa de revisão antes da confirmação do voto.
- Tela de confirmação após o registro bem-sucedido.
- Consulta aos resultados quando a enquete permitir divulgação pública.
- Interface responsiva e opção de tema claro/escuro.

### Para administradores e professores
- Autenticação para acesso à área administrativa.
- Cadastro de professores com e-mail institucional `@cps.sp.gov.br` e verificação por código.
- Criação e edição de enquetes de catálogo de jogos ou de alternativas.
- Cadastro de jogos com capa, descrição e integrantes.
- Gerenciamento das alternativas de uma enquete.
- Publicação, encerramento e reabertura de enquetes.
- Dashboard com indicadores, rankings e gráficos.
- Controle de visibilidade dos resultados.
- Compartilhamento por link exclusivo e QR Code, com opções de copiar, compartilhar e baixar.

## Tecnologias

| Camada | Tecnologias |
|---|---|
| Back-end | PHP 8.3, arquitetura própria com roteador e autoload |
| Banco de dados | MariaDB, InnoDB, PDO e consultas parametrizadas |
| Front-end | HTML5, CSS3 e JavaScript ES6+ |
| Gráficos | Apache ECharts 5.5.1 |
| Infraestrutura | Docker, Docker Compose e Apache |
| E-mail | SMTP ou Mailpit para desenvolvimento |

O projeto utiliza CSS e JavaScript próprios para a interface e as interações. O Apache ECharts é utilizado na apresentação dos gráficos.

## Requisitos

Para executar com Docker:
- Docker Desktop (Windows/macOS) ou Docker Engine (Linux).
- Docker Compose v2.

Para execução sem Docker:
- PHP 8.3 ou superior.
- Extensão PHP `pdo_mysql`.
- MariaDB compatível.
- Servidor web configurado para apontar o document root para a pasta `public/`.

## Instalação e execução

### Opção 1 — Docker (recomendado)

1. Faça o download ou clone o repositório.
2. Entre na pasta do projeto:

```bash
cd fatecpoll
```

3. Crie o arquivo de configuração local:

```bash
cp .env.example .env
```

No Windows PowerShell, se necessário:

```powershell
Copy-Item .env.example .env
```

4. Revise as variáveis do `.env`, principalmente as credenciais administrativas e do banco de dados.
5. Construa as imagens e inicie os serviços:

```bash
docker compose up --build -d
```

6. Acesse a aplicação:

| Serviço | Endereço |
|---|---|
| Aplicação | http://localhost:8080 |
| Administração | http://localhost:8080/admin/login |
| Mailpit — caixa de e-mails de teste | http://localhost:8025 |
| Adminer — opcional | http://localhost:8081 |

Para iniciar também o Adminer:

```bash
docker compose --profile tools up -d
```

Na primeira inicialização, o MariaDB aplica o schema inicial. O processo de inicialização da aplicação executa as migrações incrementais e o seed, que cria o administrador inicial e, quando habilitado, dados demonstrativos.

**Atenção:** os valores padrão do `.env.example` são destinados ao ambiente local. Altere senhas e configurações antes de qualquer implantação acessível pela internet.

### Opção 2 — Execução local (sem Docker)

1. Configure um banco MariaDB e um usuário com permissões sobre o banco do projeto.
2. Aplique o schema inicial:

```bash
mariadb -u root -p < database/migrations/001_schema.sql
```

3. Configure as variáveis de ambiente utilizadas em `app/config.php` (consulte também `.env.example`).
4. Execute o seed pelo terminal:

```bash
php database/seed.php
```

O seed executa as migrações incrementais, cria o administrador inicial se ainda não existir e pode inserir dados de demonstração.

5. Inicie o servidor embutido do PHP para desenvolvimento:

```bash
php -S 0.0.0.0:8000 -t public public/index.php
```

6. Acesse http://localhost:8000.

> O servidor embutido do PHP é indicado para desenvolvimento e testes, não para produção.

## Configuração

As principais opções estão documentadas em `.env.example`.

| Variável | Finalidade |
|---|---|
| `WEB_PORT` | Porta local publicada pelo Docker |
| `DB_NAME` | Nome do banco de dados |
| `DB_USER` / `DB_PASS` | Usuário e senha da aplicação no banco |
| `DB_ROOT_PASSWORD` | Senha administrativa do MariaDB |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Credenciais do primeiro administrador criado |
| `SEED_DEMO` | Habilita a criação de dados demonstrativos |
| `APP_DEBUG` | Ativa ou desativa informações de depuração |
| `APP_URL` | URL pública base usada nos links compartilháveis |
| `APP_KEY` | Chave secreta usada para assinar e gerar hashes do identificador do dispositivo |
| `DUPLICATE_SIGNAL` | `flag` sinaliza possível duplicidade; `block` bloqueia quando o sinal técnico configurado é detectado |
| `MAIL_DRIVER` | Meio de envio de e-mail (`smtp` ou `log`, conforme configuração) |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE` | Configurações do servidor SMTP |
| `SMTP_USER`, `SMTP_PASS` | Credenciais SMTP |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Remetente dos e-mails |

### Chave de identificação do dispositivo

Em ambiente local, quando `APP_KEY` está vazia, a aplicação cria e utiliza `storage/app.key`. Em produção, defina uma chave forte em `APP_KEY` e mantenha-a estável. **Não troque nem descarte essa chave durante o uso do sistema**, pois isso invalida os identificadores existentes e pode permitir novas participações nos dispositivos já registrados.

### E-mail de verificação

No Docker, o envio é direcionado por padrão ao Mailpit. Consulte as mensagens em http://localhost:8025. Para envio real, configure um servidor SMTP no `.env`, utilizando uma senha de aplicativo quando o provedor exigir.

Sem SMTP, a aplicação também pode ser configurada para registrar mensagens localmente, conforme o `MAIL_DRIVER` e as opções de configuração.


