import express, { type Express } from "express";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { NotesStore } from "./notesStore.js";

const __dirname = dirname(fileURLToPath(import.meta.url));

export function createApp(store: NotesStore = new NotesStore()): Express {
  const app = express();
  app.use(express.json());

  app.get("/api/health", (_req, res) => {
    res.json({ status: "ok", uptime: process.uptime() });
  });

  app.get("/api/notes", (_req, res) => {
    res.json({ notes: store.list() });
  });

  app.post("/api/notes", (req, res) => {
    const text = typeof req.body?.text === "string" ? req.body.text.trim() : "";
    if (!text) {
      res.status(400).json({ error: "Field 'text' is required and must be a non-empty string." });
      return;
    }
    const note = store.add(text);
    res.status(201).json({ note });
  });

  // Serve the static frontend from ../public relative to the compiled file.
  app.use(express.static(join(__dirname, "..", "public")));

  return app;
}
