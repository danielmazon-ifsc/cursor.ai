import { describe, it, expect, beforeEach } from "vitest";
import request from "supertest";
import { createApp } from "../src/app.js";
import { NotesStore } from "../src/notesStore.js";

describe("Notes API", () => {
  let app: ReturnType<typeof createApp>;
  let store: NotesStore;

  beforeEach(() => {
    store = new NotesStore();
    app = createApp(store);
  });

  it("reports a healthy status", async () => {
    const res = await request(app).get("/api/health");
    expect(res.status).toBe(200);
    expect(res.body.status).toBe("ok");
  });

  it("starts with an empty list of notes", async () => {
    const res = await request(app).get("/api/notes");
    expect(res.status).toBe(200);
    expect(res.body.notes).toEqual([]);
  });

  it("creates a note and returns it in the list", async () => {
    const create = await request(app).post("/api/notes").send({ text: "Buy milk" });
    expect(create.status).toBe(201);
    expect(create.body.note).toMatchObject({ text: "Buy milk" });
    expect(create.body.note.id).toBeTruthy();

    const list = await request(app).get("/api/notes");
    expect(list.body.notes).toHaveLength(1);
    expect(list.body.notes[0].text).toBe("Buy milk");
  });

  it("rejects an empty note", async () => {
    const res = await request(app).post("/api/notes").send({ text: "   " });
    expect(res.status).toBe(400);
    expect(res.body.error).toBeTruthy();
  });
});
