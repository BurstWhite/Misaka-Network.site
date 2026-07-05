# Cloudflare R2 backup with rclone

This project can be backed up from the host with `scripts/backup-r2.sh`. The
script creates a database dump inside the running Docker Compose app container,
stages persistent files, creates a tarball, and uploads it to Cloudflare R2 with
rclone.

## 1. Configure rclone

Install rclone on the host:

```bash
brew install rclone
```

Create a Cloudflare R2 API token with object read/write permission for the
backup bucket, then run:

```bash
rclone config
```

Use an S3 remote with Cloudflare as the provider:

```ini
[r2]
type = s3
provider = Cloudflare
access_key_id = your-access-key-id
secret_access_key = your-secret-access-key
region = auto
endpoint = https://your-account-id.r2.cloudflarestorage.com
acl = private
no_check_bucket = true
```

For bucket-scoped R2 tokens, `rclone lsd r2:` can fail with `AccessDenied`
because it calls `ListBuckets`. Test with a concrete bucket path instead:

```bash
rclone lsf r2:your-bucket/
```

## 2. Configure this project

Copy the example config:

```bash
cp .backup.env.example .backup.env
```

Edit `.backup.env`:

```env
RCLONE_REMOTE=r2
RCLONE_DEST=your-bucket/backups/Misaka-Network.site
BACKUP_RETENTION_DAYS=30
BACKUP_LOCAL_DIR=storage/backup/r2
BACKUP_INCLUDE_LOGS=false
BACKUP_RCLONE_EXTRA_ARGS=--s3-no-check-bucket
```

Backups contain sensitive data, including `.env` and database contents. To
encrypt backups before they reach R2, create an rclone `crypt` remote and set
`RCLONE_REMOTE` to that remote name.

## 3. Run a manual backup

Make sure the Compose app is running:

```bash
docker compose up -d
```

Run:

```bash
./scripts/backup-r2.sh
```

The script will:

- prefer the `xboard` service, falling back to `web` for split deployments;
- run `php artisan backup:database` inside the app container;
- copy the generated database dump back to the host;
- include `.env`, `.docker/.data`, `storage/theme`, and `plugins`;
- include `storage/logs` only when `BACKUP_INCLUDE_LOGS=true`;
- try to include `/data/dump.rdb` from the `redis` service or single `xboard`
  container;
- upload the archive to `RCLONE_REMOTE:RCLONE_DEST/YYYY-MM-DD_HH-mm-ss/`;
- delete local and remote backups older than `BACKUP_RETENTION_DAYS`.

Local archives are stored under `storage/backup/r2` by default.

## 4. Install the daily cron job

The installer is explicit and idempotent. It only changes crontab when you run
it:

```bash
./scripts/install-r2-backup-cron.sh
```

Default schedule:

```text
30 3 * * *
```

To use another schedule:

```bash
R2_BACKUP_CRON_TIME="15 2 * * *" ./scripts/install-r2-backup-cron.sh
```

Cron logs are appended to:

```text
storage/logs/r2-backup.log
```

## 5. Restore

Download a backup from R2:

```bash
rclone copy r2:your-bucket/backups/Misaka-Network.site/2026-07-05_03-30-00 ./restore
```

Extract it:

```bash
tar -xzf ./restore/2026-07-05_03-30-00.tar.gz -C ./restore
```

Restore files by copying from `restore/<timestamp>/files/` into the project
root. Stop the Compose stack before replacing live files:

```bash
docker compose down
cp -a ./restore/2026-07-05_03-30-00/files/. ./
docker compose up -d
```

For SQLite, the raw database file is included in `.docker/.data` when present.
You can also use the SQL dump in `database/` with SQLite tools.

For MySQL, import the SQL dump into the target database after extracting it:

```bash
gzip -dc ./restore/2026-07-05_03-30-00/database/*_database_backup.sql.gz | mysql -h DB_HOST -P 3306 -u DB_USER -p DB_NAME
```

For Redis, stop the stack, copy `redis/dump.rdb` back to the Redis data volume
or bind mount used by your deployment, then start the stack again.
