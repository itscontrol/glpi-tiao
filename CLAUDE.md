# GLPI Tião Plugin — Contexto para Claude Code

> **Handoff Codex ↔ Claude.** Repo operado alternadamente por Claude (`CLAUDE.md`)
> e Codex (`AGENTS.md`). Veja `AGENTS.md` para o enquadramento do plugin como
> **conector/espelho** do Tião (o `Case` pertence ao platform, não ao GLPI), os
> docs canônicos no `tiao-platform` e a frente LGPD aberta (issue #5).

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
