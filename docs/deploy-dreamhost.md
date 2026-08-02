# Deploy graffiti.moe on Dreamhost

Short checklist for shared hosting with Hover DNS.

## 1. Upload the app

- Rsync or SFTP the repo to the server (exclude `.git`, `vendor` if you run Composer on-server).
- On the server: `composer install --no-dev --optimize-autoloader`.

## 2. Web directory (two options)

**Preferred:** In the Dreamhost panel, set the domain **web directory** to `…/graffiti.moe/public`.

**If the web directory must stay at `~/graffiti.moe`:** keep the repo-root `.htaccess`. It rewrites all requests into `public/` (so URLs stay `/add`, `/random`) and returns 403 for `config/`, `data/`, `src/`, `vendor/`, etc. Confirm `mod_rewrite` is on (it is by default on Dreamhost).

Either way, confirm `public/.htaccess` is present so app routes hit `index.php`.

## 3. Config and data layout

Layout under `~/graffiti.moe/`:

```text
~/graffiti.moe/
  public/          # front controller + assets (web-facing)
  config/          # config.php (secrets) — not served
  data/            # graffiti.sqlite — not served
  src/ vendor/ …   # app code — not served
```

`db_path` defaults to `__DIR__ . '/../data/graffiti.sqlite'`, i.e. **`~/graffiti.moe/data/`** — a sibling of `public/` and `config/`, not inside `public/`.

- Copy `config/config.example.php` to `config/config.php`.
- Set production values: `admin_password`, `ip_hash_secret`, `base_url`, rate limits.

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

Confirm `config/` and `data/` are not reachable via HTTP (403/404), e.g.:

```bash
curl -sI https://graffiti.moe/config/config.php | head -1
curl -sI https://graffiti.moe/data/ | head -1
```
