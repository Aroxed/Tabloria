# Contributing to Tabloria

Thanks for helping improve Tabloria Searchable Displays.

## Before you start

- Search existing issues before opening a new one.
- Keep changes focused on one problem or feature.
- For a larger feature, open an issue first so the workflow and compatibility impact can be discussed.
- Do not include credentials, private CSV data, customer information, or generated build archives in a contribution.

## Local setup

Start the WordPress and MySQL containers:

```bash
docker compose up -d
```

The plugin directory is mounted into the WordPress container at:

```text
/var/www/html/wp-content/plugins/tabloria-searchable-displays
```

Complete the local WordPress installation if needed, then activate **Tabloria Searchable Displays**.

## Tests

Run frontend behavior tests:

```bash
cd tabloria-searchable-displays
npm test
```

Run PHP syntax checks and WordPress integration tests:

```bash
docker compose exec -T wordpress sh -lc \
  "cd /var/www/html/wp-content/plugins/tabloria-searchable-displays && \
  find . -name '*.php' -print0 | xargs -0 -n1 php -l && \
  php tests/run-unit-tests.php && \
  php tests/wordpress-smoke.php"
```

## Pull requests

- Explain the user-facing problem and the chosen solution.
- Include tests for changed behavior.
- Update `readme.txt` and documentation when behavior or settings change.
- Keep the plugin version unchanged unless the pull request is specifically preparing a release.
- Confirm that `git diff --check` passes.

Runtime JavaScript and CSS live in `tabloria-searchable-displays/assets/` and are intentionally readable; this repository does not require a frontend build step.

## Security reports

Do not open a public issue for a potential vulnerability. Follow [SECURITY.md](SECURITY.md).
