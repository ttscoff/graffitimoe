# Deploy graffiti.moe on Dreamhost

Short checklist for shared hosting with Hover DNS.

## 1. Upload the app

- Rsync or SFTP the repo to the server (exclude `.git`, `vendor` if you run Composer on-server).
- On the server: `composer install --no-dev --optimize-autoloader`.

## 2. Point the domain at `public/`

- In Dreamhost panel: set the domain **web directory** to the repo's `public/` folder (not the repo root).
- Confirm `public/.htaccess` is present so Apache rewrites routes to `index.php`.

## 3. Config and data outside the web root

- Copy `config/config.example.php` to `config/config.php` (sibling of `public/`, not inside it).
- Set production values: `admin_password`, `ip_hash_secret`, `base_url`, rate limits.
- Ensure `db_path` in config points to a SQLite file **outside** `public/` (default: `data/graffiti.sqlite`).

```bash
mkdir -p data
chmod 700 data
```

The web server user must be able to read/write the database file.

## 4. TLS (Let's Encrypt)

- In Dreamhost: **Secure hosting** → enable **Let's Encrypt** for `graffiti.moe` (and `www` if used).
- Wait for certificate issuance; force HTTPS if desired in the panel.

## 5. Hover DNS

At Hover, point the domain to Dreamhost:

- **A record** `@` → Dreamhost IP (from Dreamhost DNS page), or
- **Nameservers** → Dreamhost nameservers if delegating DNS entirely.

Allow time for propagation before final verification.

## 6. Verify

```bash
# Plain random message
curl https://graffiti.moe/random

# Color (optional)
curl 'https://graffiti.moe/random?color=always'

# Post (replace secrets as needed)
curl -X POST https://graffiti.moe/add \
  -H 'Accept: text/plain' \
  --data-urlencode 'body=hello from prod' \
  --data-urlencode 'color=default'
```

Browser checks:

- `https://graffiti.moe/` → redirects to `/add` (form + recent 10).
- `https://graffiti.moe/admin` → login, list, delete.

Confirm `config/` and `data/` are not reachable via HTTP (404 or forbidden).
