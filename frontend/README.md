# InnSync Frontend

Vue frontend for InnSync, a learning project for online room reservations and hotel stay management.

## Current Status

This folder contains the Vue starter interface with Home and About routes. Customer reservations, staff screens, authentication, and backend integration are planned but not implemented yet.

See the [project overview](../README.md) and [business rules](../documents/BUSINESS-RULES.md) for the intended scope.

## Tech Stack

- Vue 3 and Vue Router.
- TypeScript and Vite.
- Prettier for formatting and vue-tsc for type checking.

## Requirements

Node.js `^22.18.0` or `>=24.12.0`, as specified in `package.json`, and npm.

## Local Setup

From the repository root:

```sh
cd frontend
npm ci
npm run dev
```

Open the local URL printed by Vite, normally `http://localhost:5173`.

The current starter does not require frontend environment variables. API connection settings will be added when backend integration is implemented. See the [backend README](../backend/README.md) for backend setup.

## Available Commands

Run these from `frontend/`:

| Command | Purpose |
|---|---|
| `npm run dev` | Start the development server with hot reload |
| `npm run type-check` | Check Vue and TypeScript types |
| `npm run build` | Type-check and create a production build in `dist/` |
| `npm run build-only` | Build without type checking |
| `npm run preview` | Preview the production build locally after building |
| `npm run format` | Format files in `src/` |

## Structure

| Path | Purpose |
|---|---|
| `src/main.ts` | Application entry point |
| `src/App.vue` | Root component |
| `src/router/` | Client-side routes |
| `src/views/` | Page components |
| `src/components/` | Reusable components |
| `src/assets/` | Styles and imported assets |
| `public/` | Static files served directly |
| `vite.config.ts` | Vite plugins and the `@` alias for `src/` |

See the [root README](../README.md#disclaimer) for the learning-purpose disclaimer and ownership information.
