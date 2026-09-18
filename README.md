# cursor.ai

A minimal full-stack **Notes** app used to bootstrap and demonstrate the development environment.

- **Backend:** TypeScript + [Express](https://expressjs.com/) REST API (`/api/health`, `/api/notes`).
- **Frontend:** static HTML/CSS/JS served by the same server (`public/`).
- **Storage:** in-memory (no external services required).

## Requirements

- Node.js >= 20 (the repo is developed against Node 22)
- npm

## Getting started

```bash
npm install        # install dependencies
npm run dev        # start the dev server with hot reload on http://localhost:3000
```

## Scripts

| Command             | Description                                        |
| ------------------- | -------------------------------------------------- |
| `npm run dev`       | Start the dev server with hot reload (`tsx watch`) |
| `npm run build`     | Compile TypeScript to `dist/`                      |
| `npm start`         | Run the compiled server from `dist/`               |
| `npm run typecheck` | Type-check with `tsc --noEmit`                     |
| `npm run lint`      | Lint with ESLint                                   |
| `npm test`          | Run the test suite with Vitest                     |

## API

| Method | Path          | Description                          |
| ------ | ------------- | ------------------------------------ |
| GET    | `/api/health` | Health check                         |
| GET    | `/api/notes`  | List notes (newest first)            |
| POST   | `/api/notes`  | Create a note — body `{ "text": … }` |

Example:

```bash
curl -s http://localhost:3000/api/health
curl -s -X POST http://localhost:3000/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"text":"Hello from cursor.ai"}'
curl -s http://localhost:3000/api/notes
```
