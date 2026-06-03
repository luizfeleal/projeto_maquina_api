# Docker e Monitoramento

## Subir ambiente

1. Crie o arquivo de ambiente:

```bash
cp .env.docker.example .env
```

2. Preencha `APP_KEY`, `DB_*` e `JWT_SECRET` no `.env`.

3. Gere uma chave se necessário:

```bash
docker compose run --rm app php artisan key:generate
```

4. Suba a aplicação e o monitoramento:

```bash
docker compose up -d --build
```

## URLs

- API: http://localhost:8081
- Health check: http://localhost:8081/api/health
- Grafana: http://localhost:3000
- Prometheus: http://localhost:9091

As credenciais iniciais do Grafana vêm de `GRAFANA_ADMIN_USER` e `GRAFANA_ADMIN_PASSWORD` no `.env`.

## O que é monitorado

- Disponibilidade HTTP da API via Blackbox Exporter.
- Conexões do Nginx via Nginx Prometheus Exporter.
- CPU e memória dos containers via cAdvisor.
- Métricas do host via Node Exporter.

O dashboard `Projeto Maquina - Operacao Laravel` é provisionado automaticamente no Grafana.
