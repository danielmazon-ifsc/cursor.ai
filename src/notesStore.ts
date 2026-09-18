import { randomUUID } from "node:crypto";

export interface Note {
  id: string;
  text: string;
  createdAt: string;
}

/**
 * In-memory notes store. Kept deliberately simple so the app has no external
 * infrastructure dependency while still exercising a real create/read flow.
 */
export class NotesStore {
  private notes: Note[] = [];

  list(): Note[] {
    return [...this.notes].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }

  add(text: string): Note {
    const note: Note = {
      id: randomUUID(),
      text,
      createdAt: new Date().toISOString(),
    };
    this.notes.push(note);
    return note;
  }

  clear(): void {
    this.notes = [];
  }
}
