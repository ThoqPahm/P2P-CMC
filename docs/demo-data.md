# Demo data deployment

The public snapshot in data/demo-snapshot.json includes content and settings exported from the local demonstration database. It is not a live database backup. AI provider configurations/keys, passwords, access tokens, AI request logs and audit logs are excluded. Never commit data/*.sqlite or data/.app_secret.

After pulling on cPanel, explicitly import the snapshot:

```sh
git pull origin main
php tools/demo-data.php import --apply
```

Import replaces the included tables, not merges them. It creates a private SQLite backup under tmp before writing, uses a transaction and checks foreign keys. AI provider keys/configuration remain on that installation. Passwords for matching email addresses are retained; other imported accounts receive random unusable passwords. Public conversation/appointment tokens are regenerated, so old browser links may stop working. Existing references incompatible with the snapshot cause rollback, not silent deletion of retained records.

Local content changes do not automatically reach Git. To refresh the snapshot, run php tools/demo-data.php export, review the JSON for personal content/secrets, then commit and push. Export stops if known API-key patterns are detected; this does not replace human review. For an isolated import check, use php tools/demo-data.php import --apply --check (writes only to the backup copy).

Images and other uploaded assets referenced by the data must also be tracked separately. This snapshot ships with the existing repository assets; it does not download external content URLs.
