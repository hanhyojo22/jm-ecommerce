---
paths:
  - '**/*'
---

# General

## All changes ship to Render — no local-only workflow
The mobile app's default API_BASE_URL points at the live Render backend (https://jmmart-marketplace.onrender.com), not localhost. Backend/API changes are invisible to the mobile app (and to anyone testing against it) until they are deployed to Render — committing/pushing to git master is not enough. After making a backend change the user wants to actually test or use (especially via the mobile app on a device), deploy it to Render as part of finishing the task; do not treat a local `php artisan serve` or local DB as the target environment, and do not stop at "pushed to master" when the user's workflow is cloud-only.
