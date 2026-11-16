# Alterações em relação à Documentação Funcional v1.2

Decisões tomadas em 09/11/2026. Onde esta lista divergir do PDF v1.2, vale esta lista.

| Tema | v1.2 | Decisão atual |
|---|---|---|
| Banco de dados | "MySQL 8 / InnoDB" | **MariaDB 10.6+ / InnoDB** (correção: o projeto sempre usou MariaDB) |
| Gráficos | "gráficos próprios em Canvas/SVG, sem bibliotecas" | **Apache ECharts 5.5.1** (open source, via CDN), com tema claro/escuro |
| QR Code | geração própria em JS | **Adiado** (fora desta entrega) |
| Credenciais individuais de uso único | fase 5 | **Adiado**. A tabela `participation_tokens` já existe no schema, sem uso |
| Rotas | ver seção 6 | Alinhadas à seção 6 (exceto `/admin/enquetes/{id}/qrcode` e `/acessos`, adiadas) |
| Prazo | 09/11/2026 | **16/11/2026** |

## Critério de desempate (seção 7.2)
Ranking de jogos: maior pontuação geral → mais avaliações → menor id (ordem de cadastro).

## Perfis de acesso (09/11/2026)
- **Professor**: vê, edita, publica/encerra e consulta resultados **só das enquetes que criou** (`polls.owner_id`). Enquete de outra pessoa responde 404, como se não existisse. O dashboard e os totais também contam só as dele.
- **Administrador**: vê e gerencia **todas** as enquetes, com a indicação de quem criou cada uma.
- Migração `003_poll_owner.sql` adiciona `owner_id`; enquetes antigas sem dono passam ao primeiro administrador no `seed.php`.

## Voto único, link exclusivo e QR Code (v1.4)
- **QR Code**: não está mais adiado. É gerado no navegador (`public/js/qr.js`, sem dependências) na tela `/admin/enquetes/{id}/compartilhar`, exibida logo após criar a enquete. Copiar, compartilhar e baixar (PNG/SVG).
- **Link**: enquetes novas usam código aleatório de 128 bits no lugar do título (`/e/{codigo}`). As antigas mantêm o link.
- **Voto único por dispositivo**: migração `004_one_vote_per_device.sql` (`participation_devices` com `UNIQUE (poll_id, device_hash)` e colunas `ip_hash`, `fp_hash`, `risk` em `participations`). Detalhes e limites no README.
- **Rotas novas**: `GET /e/{slug}/status`, `GET /autores` (reservada para a futura tela de autores), `GET /admin/enquetes/{id}/compartilhar`.
- **Footer**: copyright e link Autores; o texto de privacidade passou a informar o identificador anônimo do aparelho.
