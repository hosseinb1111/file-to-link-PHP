```markdown
# file-to-link

A tiny single-file PHP uploader. Drop a file (or paste some text), get a short
random link back. No accounts, no folders, no frameworks — just one `index.php`
and a MySQL table.

Built because I kept needing a quick "put this file somewhere and give me a URL"
tool on shared hosting, and every existing solution wanted Composer, a build
step, or a whole admin panel. This one is 400-ish lines of plain PHP.

---

## What it does

- **Drag & drop upload** — or click to pick a file
- **Paste text** — saved as `snippet.txt`, previewed as plain text
- **Random short links** — 8-character codes from an unambiguous alphabet
  (`abc…xyz23456789`, no `0/O/l/I`) so links are easy to read out loud
- **Real upload progress bar** — powered by `XMLHttpRequest.upload.onprogress`
  (the `fetch()` API still can't report upload progress)
- **Inline previews** — images render in the browser, text shows in a `<pre>`,
  everything else gets a download button
- **Beautiful 404** — unknown route or dead code both land on the same page
- **Zero dependencies** — no Composer, no npm, no build step, no external fonts
  or scripts. The favicon is an inline `data:` SVG.

## Requirements

- PHP **7.4+** (uses `str_starts_with`? no — I kept it 7.4-safe; `random_int`,
  `PDO`, `LONGBLOB` are the only non-trivial requirements)
- MySQL or MariaDB
- Apache with `mod_rewrite`, or any server where you can route unknown paths
  to `index.php` (Nginx snippet below)

## Setup

### 1. Database

Run this in phpMyAdmin (or any MySQL client):

```sql
CREATE TABLE IF NOT EXISTS `drops` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(32)  NOT NULL,
  `filename`   VARCHAR(255) DEFAULT NULL,
  `mime`       VARCHAR(190) DEFAULT NULL,
  `size`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `is_text`    TINYINT(1)   NOT NULL DEFAULT 0,
  `content`    LONGBLOB,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The script will also create this table on first request if it's missing, so
strictly speaking you can skip this step — but creating it yourself is better
because you control the charset and collation.

### 2. Configure

Open `index.php` and edit the four constants at the top:

```php
const DB_HOST = 'sqlXXX.infinityfree.com';   // your MySQL host
const DB_NAME = 'if0_XXXXXXXX_drops';        // database name
const DB_USER = 'if0_XXXXXXXX';              // MySQL *user* — not the db name
const DB_PASS = 'your-password';

const MAX_UPLOAD = 50 * 1024 * 1024;         // 50 MB
const CODE_LEN   = 8;
```

On InfinityFree (and most shared hosts), the MySQL **user** is usually
`if0_XXXXXXXX` while the **database** is `if0_XXXXXXXX_something`. Don't mix
them up — that's the #1 cause of `SQLSTATE[HY000] [1045] Access denied`.

### 3. Upload and set permissions

Drop `index.php` and `.htaccess` into your web root (`htdocs/` on
InfinityFree). That's it.

### 4. `.htaccess` (Apache)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

**Nginx** equivalent:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Routes

| Method | Path              | Purpose                                       |
| ------ | ----------------- | --------------------------------------------- |
| `GET`  | `/`               | Upload UI                                     |
| `POST` | `/api/upload`     | Save file or text, returns `{ok, code, url}`  |
| `GET`  | `/{code}`         | View / preview a saved item                   |
| `GET`  | `/raw/{code}`     | Raw bytes, inline (`Content-Type` from row)   |
| `GET`  | `/dl/{code}`      | Force download with original filename         |
| `*`    | anything else     | 404 page                                      |

## About the 50 MB limit

The code itself accepts up to 50 MB. Whether your **host** does is a different
question:

- **PHP `upload_max_filesize` / `post_max_size`** — on InfinityFree's free tier
  this is capped around 10 MB and the panel often ignores overrides. Paid
  accounts honor them.
- **MySQL `max_allowed_packet`** — even if PHP accepts a 50 MB file, the
  `INSERT` into `LONGBLOB` can fail with `MySQL server has gone away` if the
  packet limit is lower. Free tier is usually 16 MB or less.

If you hit either wall, the fix is on the host side, not in this script. For
serious large-file hosting, use object storage (S3, R2, Backblaze B2) with
presigned uploads instead of storing blobs in MySQL.

## Security notes

This is a **personal / small-team tool**, not a public file host. Before opening
it to the internet at large, consider adding:

- Rate limiting (per IP, per minute)
- A storage quota / cleanup cron (delete rows older than N days)
- Authentication (basic auth via `.htaccess` is the lazy option)
- Antivirus scanning (ClamAV) for uploaded files
- `Content-Security-Policy` headers

Uploaded files are served with `X-Content-Type-Options: nosniff` and the MIME
type recorded at upload time, which mitigates — but doesn't eliminate — stored
XSS via crafted SVG/HTML. If you expect hostile uploads, serve `/raw/` from a
separate origin.

## License

MIT — see [LICENSE](LICENSE) below.

## Author

**Hossein Seyed Bagheri**

- GitHub: [@hosseinb1111](https://github.com/hosseinb1111)
- Project: [file-to-link-PHP](https://github.com/hosseinb1111/file-to-link-PHP)

---

## LICENSE

```
MIT License

Copyright (c) 2025 Hossein Seyed Bagheri

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
```

---

## Also create `LICENSE` (plain file, no markdown fences)

GitHub auto-detects `LICENSE` at the repo root and shows an "MIT" badge next to the repo name. Paste exactly this:

```
MIT License

Copyright (c) 2025 Hossein Seyed Bagheri

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## Suggested repo layout

```
file-to-link-PHP/
├── index.php
├── .htaccess
├── README.md
└── LICENSE
```

## Two small tweaks worth making before you push

1. **Add a screenshot** to the README — GitHub renders images and it makes the repo look 10× more legit. Save one as `screenshot.png` in the repo and add right under the first paragraph:
   ```markdown
   ![screenshot](screenshot.png)
   ```
2. **Repo description** on GitHub (the short line under the name) — keep it under ~120 chars:
   > Single-file PHP drag-and-drop uploader. Get a short random link for any file or text. MySQL, no dependencies.
