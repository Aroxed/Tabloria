# CSV privacy after upgrading to 0.2.2

Version 0.2.2 imports CSV data from PHP's temporary upload and stores parsed rows in Tabloria's database. It no longer saves a raw CSV in `wp-content/uploads/`. Existing Displays keep using their database rows.

Older versions used WordPress's public uploads directory for the raw file. Updating the plugin does not delete those files because the plugin did not record their paths, and other plugins or site pages may use CSV files in the same directory.

After upgrading, use your hosting file manager or SFTP to review `.csv` files under `wp-content/uploads/`:

1. Compare filenames and upload dates with the original CSVs used for Tabloria sources. WordPress may have added a numeric suffix such as `catalog-1.csv`.
2. Check whether any page, attachment, or other plugin links to each candidate file.
3. Back up confirmed legacy Tabloria CSVs, then remove their public copies from `uploads/`.
4. Open the affected Tabloria Displays and confirm their data, search, and filters still work.

Do not delete all CSVs in `uploads/` blindly. If an old file contained private columns, treat its URL as previously public and rotate any exposed secrets or credentials.
