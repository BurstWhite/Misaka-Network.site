# GitHub Actions Production Deployment

The production workflow deploys only after `Docker Build and Publish` succeeds
on `master`. Automatic deployments use the full Git commit SHA as the GHCR
image tag, so every release is immutable and auditable.

## Server prerequisites

The production server needs:

- Docker and Docker Compose;
- Git access to this repository;
- `curl` for health checks;
- the repository checked out on `master` with no tracked local changes;
- GHCR authentication configured once with `docker login ghcr.io` when the
  package is private;
- `.backup.env` configured when R2 backups are enabled.

Before enabling the workflow, verify that the server checkout can fast-forward
from `origin/master`. An optional manual smoke deployment is:

```bash
cd /path/to/Misaka-Network.site
git pull --ff-only origin master
./scripts/deploy-container.sh ghcr.io/burstwhite/misaka-network.site:latest
```

## GitHub production environment

Create an environment named `production`, then add these environment secrets:

| Secret | Value |
| --- | --- |
| `PRODUCTION_SSH_HOST` | Production server hostname or IP |
| `PRODUCTION_SSH_PORT` | SSH port; use `22` when unchanged |
| `PRODUCTION_SSH_USER` | Restricted deployment user |
| `PRODUCTION_SSH_KEY` | Private key dedicated to deployment |
| `PRODUCTION_SSH_KNOWN_HOSTS` | Verified `known_hosts` entry for the server |
| `PRODUCTION_PATH` | Absolute repository path on the server, without spaces |

Optionally set the environment variable `PRODUCTION_BRANCH`; it defaults to
`master`.

Generate the host-key entry from a trusted machine and verify its fingerprint
out of band before saving it:

```bash
ssh-keyscan -p 22 your-server.example.com
```

Do not generate `known_hosts` inside the deployment job because doing so would
remove meaningful host identity verification.

## Deployment behavior

The deployment job:

1. refuses to continue when the production checkout has tracked changes;
2. fast-forwards the configured production branch;
3. optionally runs the existing R2 persistent-data backup;
4. pulls the immutable commit-SHA image from GHCR;
5. recreates only the `xboard` service;
6. checks the configured health URL;
7. restores the previous local image when the health check fails.

Database migrations performed by `xboard:update` cannot be automatically
reverted. Keep database backups enabled for production deployments.

Use **Actions → Production Deploy → Run workflow** to redeploy `latest` or a
specific historical SHA tag manually.
