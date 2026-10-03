# Vercel deployment — first pass

This is intentionally a **separate Vercel-only deployment setup**. Do not try to make the same repository configuration serve both local Docker Compose and Vercel.

## 1. Make a separate GitHub repository while preserving history

Start from the repository that contains the complete project history:

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_CURRENT_EVENTS_REPO.git events-portal-vercel
cd events-portal-vercel
```

Create a new empty GitHub repository, for example:

```text
events-portal-vercel
```

Then point this copy at the new repository:

```bash
git remote set-url origin https://github.com/YOUR_USERNAME/events-portal-vercel.git
git push -u origin main
```

If your branch is `master`, use `master` instead of `main`.

## 2. Remove the local-only deployment files from this copy

The Vercel repository does not need the Compose runtime:

```bash
git rm compose.yaml Dockerfile docker-entrypoint.sh crontab
```

If one of those files does not exist, simply omit it from the command.

Keep `db/init/` for now because its SQL files are useful for creating the hosted database.

## 3. Add the supplied Vercel files

Copy:

```text
Dockerfile.vercel
.dockerignore
```

to the repository root.

Replace:

```text
src/framework/Database.php
```

with the supplied `Database.php`.

The rest of the MVC application can remain as it is for the first deployment.

## 4. Commit the deployment conversion

```bash
git add .
git commit -m "Configure portfolio demo for Vercel deployment"
git push
```

## 5. Create the Vercel project

In Vercel:

1. Click **Add New → Project**.
2. Import the new `events-portal-vercel` GitHub repository.
3. Keep the project root as the repository root.
4. Deploy it.

The first deployment may build successfully but fail when you open it because the database is not connected yet. That is expected at this stage.

Vercel automatically detects `Dockerfile.vercel`, builds the image, stores it in Vercel Container Registry, and routes project traffic to the resulting container Function.

## 6. Add a free TiDB Cloud Starter database

Use the **TiDB Cloud integration from the Vercel Marketplace**.

When configuring it:

1. Select the Events Portal Vercel project.
2. Choose **Cluster**.
3. Create or select a **TiDB Cloud Starter** instance.
4. Create/select a database for the Events Portal.
5. For framework, choose **General**.
6. Finish the integration.

The integration should add these variables to Vercel automatically:

```text
TIDB_HOST
TIDB_PORT
TIDB_USER
TIDB_PASSWORD
TIDB_DATABASE
```

Those are the variables used by the supplied `Database.php`.

## 7. Import the application's schema and seed data

In TiDB Cloud, open the SQL editor for the database and run the contents of:

```text
db/init/001_schema.sql
db/init/002_seed.sql
```

Run the schema first, then the seed file.

For the public demo, update the seeded event dates to future dates before importing if you want the event cards to show as upcoming rather than closed.

## 8. Redeploy

After the TiDB integration/environment variables exist:

1. Open the Vercel project.
2. Go to **Deployments**.
3. Redeploy the latest deployment.

Test the generated `*.vercel.app` URL before adding your real subdomain.

Test at least:

```text
/
/home
/events
/events/show/1
/about
/blog
/account
/admin
```

## 9. Only after the demo URL works, add the custom domain

In the Events Portal Vercel project:

**Settings → Domains → Add Domain**

Add:

```text
events.sergiupopa.app
```

Follow the DNS record Vercel gives you.

Do not move `sergiupopa.app`; that stays attached to the React portfolio project.

## Why this setup is separate

The original assignment environment used Docker Compose to provide:

```text
PHP/Apache + MySQL + phpMyAdmin + cron
```

The Vercel demo instead uses:

```text
Vercel Docker container: PHP/Apache
TiDB Cloud Starter: database
/tmp: temporary PHP sessions and demo uploads
```

This keeps the portfolio deployment simple while preserving the original Docker Compose implementation separately.
