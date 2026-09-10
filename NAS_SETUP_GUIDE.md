# NAS Sync Setup — Synology DS223j

This turns your DS223j into the private backend for the Urban Roti Inventory
app. All your data (vendors, items, entries, notes) lives on your own NAS —
not on any third-party server — and every device that opens the app pulls
and pushes to it over the internet.

Your DS223j uses a Realtek chip and **does not support Container
Manager (Docker)**. That's fine — we don't need it. Everything below uses
**Web Station + PHP**, which DS223j supports natively.

Total setup time: roughly 20–30 minutes, once.

---

## Part A — Make your NAS reachable over the internet, securely

### A1. Turn on DDNS (gives your NAS a permanent web address)

Your home internet's IP address changes from time to time. DDNS gives you a
fixed hostname that always points to your current IP.

1. On your NAS, open **Control Panel → External Access → DDNS**
2. Click **Add**
3. Service provider: **Synology**
4. Hostname: pick something like `urbanroti` → becomes `urbanroti.synology.me`
5. Click **Test Connection** → should say OK → **Apply**

Write down your hostname — you'll need it below. Example used in this guide:
`urbanroti.synology.me`

### A2. Get a free trusted SSL certificate (required for HTTPS)

Your app is served over HTTPS, so the NAS must also answer over HTTPS with a
*trusted* certificate (not a self-signed one, which browsers will block).

1. **Control Panel → Security → Certificate**
2. Click **Add** → **Add a new certificate** → **Get a certificate from Let's Encrypt**
3. Domain name: enter your DDNS hostname from A1 (e.g. `urbanroti.synology.me`)
4. Email: your email (for renewal notices)
5. Click **Apply** — Synology auto-renews this certificate every 90 days, no action needed from you

### A3. Forward port 443 on your router to your NAS

Your NAS needs to be reachable from outside your home network.

1. Find your NAS's local IP: **Control Panel → Network → Network Interface**
   (something like `192.168.1.50`)
2. Log into your **home router's** admin page (usually `192.168.1.1` in a browser)
3. Find **Port Forwarding** (sometimes under "NAT" or "Virtual Server")
4. Add a rule: External port **443** → Internal IP `<your NAS IP>` → Internal port **443**, protocol TCP
5. Save

*(Router menus vary a lot by brand — search "[your router model] port forwarding" if you can't find it.)*

---

## Part B — Install Web Station + PHP

1. Open **Package Center** on your NAS
2. Search for **Web Station** → Install
3. Once installed, open **Web Station**
4. Go to **Script Language Settings** → make sure **PHP 8.x** is enabled (install it from Package Center if prompted)

---

## Part C — Create the website that will host the API

1. In **Web Station**, go to **Web Service** → **Create**
2. Name: `inventory-api`
3. **Document root**: this is important — set it to a **`public`** subfolder,
   e.g. `web/inventory-api/public` (Web Station will create the parent folder
   for you, or use File Station to create it first)
4. Backend server: **PHP 8.x**
5. Port: HTTPS, port 443, and select the Let's Encrypt certificate from Part A2 for this site
6. Save

Why a `public` subfolder specifically: the `config.php` file (holding your
secret key) and the `data/` folder (holding your actual data) both live
**one level above** `public/`, so they are physically outside anything a web
browser can reach — even if someone guesses the filename.

---

## Part D — Upload the backend files

1. Open **File Station** on your NAS
2. Navigate to `web/inventory-api/` (the folder Web Station just set up)
3. You should see a `public` folder already there. Upload these files from
   the package you downloaded, into this exact structure:

   ```
   web/inventory-api/
   ├── config.php              ← upload here (NOT inside public/)
   └── public/
       ├── api.php              ← upload here
       └── health.php           ← upload here
   ```

   The `data/` folder will be created automatically on first use — you don't
   need to upload it, just make sure the folder above is writable (next step).

4. **Edit `config.php`** (right-click → Open with → Text Editor, or download,
   edit, re-upload):
   - Change `API_KEY` to a long random string. Example way to generate one:
     open <https://www.random.org/strings/> → 32 characters, letters+digits,
     uncheck "unique" → generate → copy that string in.
   - Confirm `ALLOWED_ORIGIN` matches your GitHub Pages URL exactly, e.g.
     `https://swam90.github.io` (no trailing slash, no path after it).

5. **Set folder permissions** so PHP can create/write the data file:
   - Right-click the `inventory-api` folder → **Properties** → **Permission**
   - Make sure the user **http** (or **www-data**, depending on DSM version)
     has **Read/Write** access
   - Apply, and tick "Apply to this folder, sub-folders and files"

---

## Part E — Test it

1. In any browser, visit:
   `https://urbanroti.synology.me/health.php`

   *(replace `urbanroti.synology.me` with your actual DDNS hostname)*

2. You should see something like:
   ```json
   {
       "status": "ok",
       "php_version": "8.2.13",
       "data_dir_exists": true,
       "data_dir_writable": true,
       "api_key_still_default": false,
       "allowed_origin": "https://swam90.github.io",
       "message": "✅ Looks good. Point the app at api.php (not this file)."
   }
   ```

   - `data_dir_writable: false` → fix folder permissions (Part D, step 5)
   - `api_key_still_default: true` → you forgot to edit config.php (Part D, step 4)
   - Page doesn't load at all → check port forwarding (A3) and the Web Station site (Part C)

---

## Part F — Connect the app

1. Open your Urban Roti Inventory app
2. Tap the **Sync** icon in the bottom navigation
3. **NAS sync URL:** `https://urbanroti.synology.me/api.php`
   *(note: `api.php`, not `health.php` — health.php was just for testing)*
4. **Secret key:** paste the exact `API_KEY` value from `config.php`
5. Tap **Save & connect**

You should see the sync status turn to **"Up to date"**. From now on:
- Every change you make pushes to the NAS a moment later
- Opening the app on any other device (or reinstalling) pulls the latest copy
- If your phone loses signal, the app keeps working locally and catches up once reconnected

Repeat step 1–5 on every device you use the app on, using the same URL and key.

---

## Security notes

- **Never share your secret key** — anyone with it can read and overwrite your inventory data.
- The API only accepts requests carrying the exact key (constant-time comparison, resistant to timing attacks) and only from your app's exact origin.
- Consider enabling **Control Panel → Security → Auto Block** on your NAS, which locks out IPs after repeated failed access attempts — cheap extra protection since your NAS is now internet-facing.
- Your NAS admin login (DSM) is separate from this API and isn't exposed by anything in this setup — only the one `api.php` endpoint is reachable, and only with the correct key.
- If you ever suspect the key has leaked, just generate a new one in `config.php` and re-enter it in the app on all your devices — old copies of the key stop working immediately.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| App shows "Offline" / "Could not reach NAS" | Port forwarding not set up, or NAS is off / disconnected |
| App shows "Sync error" with a 401 message | Secret key doesn't match between app and `config.php` |
| `health.php` loads but `api.php` doesn't | Check the URL is exactly `.../api.php`, not `.../public/api.php` (Web Station's document root already points at `public/`) |
| Certificate warnings in browser | Let's Encrypt cert not applied to the Web Station site — go back to Part A2 and C, step 5 |
| Data not syncing between two phones | Make sure the exact same URL + key are entered on both — a typo creates two separate stores |
