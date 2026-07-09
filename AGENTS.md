# AGENTS.md — GLPI Tião Plugin

> **Handoff Codex ↔ Claude.** Este repo é operado alternadamente por Codex
> (`AGENTS.md`) e Claude (`CLAUDE.md`). O núcleo de contexto é mantido igual nos
> dois arquivos — ao mudar, replique.

## O que é este repositório

Plugin PHP do **GLPI 11** que funciona como **conector** do Tião. No modelo do
produto, o GLPI é um **espelho** (`ExternalRef`), nunca a fonte: a plataforma
(`itscontrol/tiao-platform`) é o núcleo, e o `Case` pertence ao Tião. Este plugin
sincroniza chamados, followups, tarefas, soluções, anexos e status entre GLPI e
Tião. Não roda standalone — precisa de uma instância GLPI.

Contexto de produto/nomenclatura vive no platform:
`tiao-platform/docs/nucleo-independente-e-multi-vertical.md` (§7 mapeia
`StageCategory` ↔ `glpiStatus`) e `tiao-platform/docs/funcionalidades.md`.

## Caminhos de produção

| Serviço | Caminho no servidor |
|---------|---------------------|
| Plugin GLPI | `/home/itscontrol-helpdesk/htdocs/helpdesk.itscontrol.com.br/plugins/tiao` |
| Plataforma Tião (Next.js) | `/home/ia-tiao/htdocs/tiao.ia.br` |

## Deploy do plugin

```bash
cd /home/itscontrol-helpdesk/htdocs/helpdesk.itscontrol.com.br/plugins/tiao
git pull origin master
```

## Frente aberta

- **Controles LGPD no plugin** (issue #5): minimização/transparência dos dados
  pessoais sincronizados (telefone, observadores, followups privados, anexos,
  logs de payload). Arquivos: `inc/notifier.class.php`, `inc/config.class.php`,
  `front/config.form.php`, `hook.php`.
