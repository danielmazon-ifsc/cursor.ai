const form = document.getElementById("note-form");
const input = document.getElementById("note-input");
const list = document.getElementById("notes");
const empty = document.getElementById("empty");
const status = document.getElementById("status");

function setStatus(text, kind) {
  status.textContent = text;
  status.className = `status status--${kind}`;
}

function formatTime(iso) {
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function render(notes) {
  list.innerHTML = "";
  if (!notes.length) {
    empty.classList.remove("notes__empty--hidden");
    return;
  }
  empty.classList.add("notes__empty--hidden");
  for (const note of notes) {
    const li = document.createElement("li");
    li.className = "note";
    const text = document.createElement("div");
    text.className = "note__text";
    text.textContent = note.text;
    const meta = document.createElement("div");
    meta.className = "note__meta";
    meta.textContent = formatTime(note.createdAt);
    li.append(text, meta);
    list.append(li);
  }
}

async function loadNotes() {
  const res = await fetch("/api/notes");
  const data = await res.json();
  render(data.notes);
}

async function checkHealth() {
  try {
    const res = await fetch("/api/health");
    if (!res.ok) throw new Error("bad status");
    setStatus("API healthy", "ok");
  } catch {
    setStatus("API unreachable", "err");
  }
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const text = input.value.trim();
  if (!text) return;
  const res = await fetch("/api/notes", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ text }),
  });
  if (res.ok) {
    input.value = "";
    await loadNotes();
  } else {
    setStatus("Failed to add note", "err");
  }
});

checkHealth();
loadNotes();
