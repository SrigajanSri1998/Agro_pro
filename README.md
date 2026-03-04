# Track A Submission — Vanilla PHP + PDO + MySQL Task Manager

This repository implements **Track A** using **Vanilla PHP + PDO + MySQL**.

## Features
- User registration and login (token-based auth)
- Task CRUD API
- Soft deletes + restore endpoint
- Filtering by status
- Search by title/description
- Pagination metadata (`page`, `per_page`, `total`, `total_pages`)
- Basic browser frontend (`public/index.html`) for login + task management

## Project Structure
- `public/index.php` — API + simple route handling
- `public/index.html` — lightweight frontend
- `src/` — application logic (DB, auth, tasks)
- `database.sql` — schema bootstrap script
- `.env.example` — environment variable template

## Setup
1. Copy environment variables:
   ```bash
   cp .env.example .env
   ```
2. Create database and tables:
   ```bash
   mysql -u <user> -p < database.sql
   ```
3. Update `.env` with your local DB values.
4. Start the app:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
5. Open `http://127.0.0.1:8000`.

## Environment Variables
| Variable | Description |
|---|---|
| `APP_URL` | Application URL used for docs/reference |
| `DB_HOST` | MySQL host |
| `DB_PORT` | MySQL port |
| `DB_NAME` | MySQL database name |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |

## API Overview
### Auth
- `POST /api/register`
- `POST /api/login`

### Current User
- `GET /api/me`

### Tasks
- `GET /api/tasks?status=&search=&page=1&per_page=10&include_deleted=0`
- `POST /api/tasks`
- `GET /api/tasks/{id}`
- `PUT /api/tasks/{id}`
- `PATCH /api/tasks/{id}`
- `DELETE /api/tasks/{id}` (soft delete)
- `POST /api/tasks/{id}/restore`

> Authenticated endpoints require `Authorization: Bearer <token>`.

## Demo Video
Add your demo video link here (Loom/Drive/YouTube):
- `TODO: https://...`

## Assumptions
- Used a simple custom token table rather than JWT to keep dependencies at zero.
- Full-text capability is included via a MySQL `FULLTEXT` index; the API uses `LIKE` for straightforward compatibility.
- Frontend is intentionally minimal and only intended to prove API usability.
