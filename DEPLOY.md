# Deploy

Tres contenedores: `web` (Apache + PHP 8.2), `db` (MariaDB 11), `hermes` (Python FastAPI + DeepSeek).

## Local

```bash
cp .env.example .env
# editar .env con tu DEEPSEEK_API_KEY y contraseñas
# generar token: openssl rand -hex 32

docker compose up -d --build
```

App en http://localhost:8090. La BD se inicializa sola la primera vez con 25 libros, 9 usuarios y 7 admins. Login de prueba: `messi@barca.com` / `Test1234` (cliente) o alias `Lover` / `Test1234` (admin).

## Comandos

```bash
docker compose ps
docker compose logs -f hermes
docker compose restart hermes
docker compose down            # para; conserva BD
docker compose down -v         # para y borra BD
docker compose exec db mariadb -uroot -p$MYSQL_ROOT_PASSWORD powerletters
```

## VPS (Hetzner u otro)

VM Ubuntu 22.04+ con 2 vCPU / 4 GB. Abrir 22, 80, 443 en el firewall.

```bash
curl -fsSL https://get.docker.com | sh
git clone <repo> powerletters && cd powerletters
cp .env.example .env
nano .env                      # passwords fuertes, API key real
docker compose up -d --build
```

### Caddy con HTTPS automatico

```bash
apt install -y caddy
```

`/etc/caddy/Caddyfile`:

```caddy
tudominio.com, www.tudominio.com {
    reverse_proxy localhost:8090
    encode gzip zstd
    header Strict-Transport-Security "max-age=31536000; includeSubDomains"
}
```

```bash
systemctl reload caddy
```

El cert sale de Let's Encrypt en el primer request. Caddy lo renueva solo.

### Firewall

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

## Backup

```bash
docker exec powerletters_db mariadb-dump -uroot -p$MYSQL_ROOT_PASSWORD powerletters > backup-$(date +%F).sql
```

## Tools del agente

| Tool | Rol | Que hace |
|---|---|---|
| search_books | ambos | Busca por titulo/autor/genero |
| book_details | ambos | Ficha completa por id |
| top_books | ambos | Mas vendidos historico |
| recommend_for_user | cliente | Recomienda segun historial |
| my_orders | cliente | Historial de pedidos |
| add_to_cart | cliente | Suma al carrito |
| view_cart | cliente | Ve el carrito actual |
| remove_from_cart | cliente | Quita item |
| checkout | cliente | Finaliza pedido (estado FINALIZADO) |
| create_book | admin | Inserta libro |
| delete_books_no_stock | admin | Baja libros con stock 0 no referenciados |
| top_sold_month | admin | Mas vendidos del mes |
| low_stock_alert | admin | Libros bajo threshold |

## Costos

- VPS 2vCPU/4GB: ~5 USD/mes
- DeepSeek (~100 chats/dia): ~5-10 USD/mes

## Problemas comunes

- **Agente responde "Debes iniciar sesion"**: la cookie de sesion expiro, re-loguear.
- **Hermes no responde**: `docker compose logs hermes` y `docker compose restart hermes`.
- **"Unknown column" en una tool**: el schema cambio sin actualizar `agent/tools.py`. Editar y rebuild `hermes`.
- **HTTPS no levanta**: el DNS no propago todavia. `dig tudominio.com` tiene que devolver tu IP.
