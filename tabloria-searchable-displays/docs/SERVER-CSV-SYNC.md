# Refresh a Display from a server CSV

Tabloria can read a CSV that you place on the same server as WordPress. It reads the file into a private temporary copy, validates it, and replaces the saved source rows. The raw CSV stays in the directory you control; Tabloria does not copy it to public uploads or store SFTP credentials.

## Prepare a private directory

By default, Tabloria looks for `tabloria-imports` beside the WordPress installation directory. Create that directory through your hosting panel or SFTP and make it readable by the WordPress PHP process. Keep it outside the public website directory. Tabloria rejects a public import directory and files outside the private directory, including symlinks that point out of it.

If your hosting layout needs a different path, define an absolute private path in `wp-config.php`:

```php
define( 'TABLORIA_IMPORT_DIR', '/home/account/tabloria-imports' );
```

Place a CSV such as `catalog.csv` in that directory. For updates, upload to a temporary filename and rename it to `catalog.csv` after the transfer completes. This keeps an automatic refresh from reading an incomplete upload.

## Connect and refresh

1. Create a Display from an uploaded CSV or open an existing Display.
2. In **Data Source → CSV file on this server**, enter the filename relative to the private directory, such as `catalog.csv` or `supplier/catalog.csv`.
3. Click **Refresh now** to test the file and update every Display that uses this source.
4. Enable **Refresh automatically**, choose every 6 hours, 12 hours, day, or week, set the start time, and save. Weekly schedules also use the selected weekday.

The time uses the WordPress site timezone. The screen shows the last check, last success, next planned refresh, and latest error. Scheduled checks skip a file whose content has not changed; the manual button always checks it again.

Tabloria accepts the same CSV formats and size limit as its regular upload. A malformed CSV, missing old column key, or failed database write leaves the previous rows available. Refreshes run through WP-Cron, which starts when the site receives a visit; on a low-traffic site the job may start later than the selected time. For precise timing, configure your hosting scheduler to trigger WordPress cron.
