"""
PersonaX v3 — Python AI Microservice
Flask server: intent parsing, NLP, reminder extraction, memory search
Run: pip install flask requests openai anthropic && python app.py
"""

import os, re, json, hmac, hashlib
from datetime import datetime, timedelta
from flask import Flask, request, jsonify, abort

app = Flask(__name__)

INTERNAL_KEY = os.getenv("PX_PY_KEY", "internal_secret_key")


# ── AUTH MIDDLEWARE ────────────────────────────────────────

def require_internal_auth():
    key = request.headers.get("X-Internal-Key", "")
    if not hmac.compare_digest(key, INTERNAL_KEY):
        abort(403, "Forbidden")


# ── INTENT DETECTION ──────────────────────────────────────

INTENT_PATTERNS = {
    "reminder": [
        r"\bremind(er)?\b", r"\bremind me\b", r"\bschedule\b",
        r"\bset (an? )?alarm\b", r"\bdon.?t (let me )?forget\b",
    ],
    "memory": [
        r"\bremember (that|this|my)\b", r"\bsave (this|that)\b",
        r"\bkeep (a )?note\b", r"\bi (like|love|hate|prefer)\b",
    ],
    "query_memory": [
        r"\bdo (you )?remember\b", r"\bwhat (do you know|did i tell)\b",
        r"\bmy (name|age|birthday|job|preference)\b",
    ],
    "open_url": [
        r"\bopen\b.*(website|site|page|link|url)\b",
        r"\bgo to\b.*(\.com|\.org|\.net|http)\b",
    ],
    "create_report": [r"\b(generate|create|make|write) (a )?(report|summary)\b"],
    "search": [r"\b(search|look up|find|google)\b"],
    "greeting": [r"^(hi|hello|hey|good morning|good evening|good afternoon|howdy)\b"],
    "farewell": [r"^(bye|goodbye|see you|later|good night)\b"],
}


def detect_intent(text: str) -> str:
    lower = text.lower().strip()
    for intent, patterns in INTENT_PATTERNS.items():
        for pat in patterns:
            if re.search(pat, lower):
                return intent
    return "general"


# ── DATE/TIME EXTRACTION ──────────────────────────────────

TIME_PATTERNS = [
    (r"in (\d+) minute[s]?",    lambda m: datetime.now() + timedelta(minutes=int(m.group(1)))),
    (r"in (\d+) hour[s]?",      lambda m: datetime.now() + timedelta(hours=int(m.group(1)))),
    (r"in (\d+) day[s]?",       lambda m: datetime.now() + timedelta(days=int(m.group(1)))),
    (r"tomorrow (at )?(\d{1,2})(:\d{2})?\s*(am|pm)?",
     lambda m: (datetime.now() + timedelta(days=1)).replace(
         hour=int(m.group(2)) + (12 if m.group(4)=='pm' and int(m.group(2))<12 else 0),
         minute=int(m.group(3)[1:]) if m.group(3) else 0, second=0, microsecond=0)),
    (r"at (\d{1,2})(:\d{2})?\s*(am|pm)?",
     lambda m: datetime.now().replace(
         hour=int(m.group(1)) + (12 if m.group(3)=='pm' and int(m.group(1))<12 else 0),
         minute=int(m.group(2)[1:]) if m.group(2) else 0, second=0, microsecond=0)),
]


def extract_datetime(text: str) -> str | None:
    lower = text.lower()
    for pattern, resolver in TIME_PATTERNS:
        m = re.search(pattern, lower)
        if m:
            try:
                dt = resolver(m)
                return dt.strftime("%Y-%m-%d %H:%M:%S")
            except Exception:
                pass
    return None


def extract_reminder(text: str) -> dict:
    """Pull a structured reminder from natural language."""
    remind_at = extract_datetime(text)
    # Strip time expressions to get clean title
    title = re.sub(
        r"\b(remind me to|remind me|set (a )?reminder( to)?|schedule)\b", "", text, flags=re.I
    )
    title = re.sub(
        r"\b(in \d+ (minutes?|hours?|days?)|tomorrow( at \d+)?|at \d+(:\d+)?\s*(am|pm)?)\b",
        "", title, flags=re.I
    ).strip(" ,.")
    return {"title": title or text, "remind_at": remind_at, "notes": ""}


# ── MEMORY TAG EXTRACTION ─────────────────────────────────

TAG_RULES = [
    (r"\b(like|love|enjoy|prefer|favourite|favorite)\b", "preference"),
    (r"\b(goal|want to|planning to|aim to)\b",           "goal"),
    (r"\b(my (job|work|career|profession|company))\b",   "work"),
    (r"\b(my (name|age|birthday|born))\b",               "personal"),
    (r"\b(project|building|creating|working on)\b",       "project"),
    (r"\b(skill|know how|can|learn(ed|ing)?)\b",          "skill"),
]


def extract_tag(text: str) -> str:
    lower = text.lower()
    for pattern, tag in TAG_RULES:
        if re.search(pattern, lower):
            return tag
    return "note"


# ── API ROUTES ────────────────────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "PersonaX AI Microservice", "version": "3.0.0"})


@app.route("/intent", methods=["POST"])
def intent_route():
    require_internal_auth()
    data   = request.get_json(force=True)
    text   = data.get("text", "")
    intent = detect_intent(text)
    result = {"intent": intent, "text": text}

    if intent == "reminder":
        result["reminder"] = extract_reminder(text)
    elif intent == "memory":
        result["tag"] = extract_tag(text)

    return jsonify(result)


@app.route("/extract/reminder", methods=["POST"])
def extract_reminder_route():
    require_internal_auth()
    data = request.get_json(force=True)
    return jsonify(extract_reminder(data.get("text", "")))


@app.route("/extract/datetime", methods=["POST"])
def extract_datetime_route():
    require_internal_auth()
    data = request.get_json(force=True)
    dt   = extract_datetime(data.get("text", ""))
    return jsonify({"datetime": dt})


@app.route("/extract/tag", methods=["POST"])
def extract_tag_route():
    require_internal_auth()
    data = request.get_json(force=True)
    return jsonify({"tag": extract_tag(data.get("text", ""))})


@app.route("/sentiment", methods=["POST"])
def sentiment_route():
    """Simple rule-based sentiment for blob state selection."""
    require_internal_auth()
    data  = request.get_json(force=True)
    text  = data.get("text", "").lower()
    pos   = len(re.findall(r"\b(great|awesome|thanks|happy|love|perfect|excellent|wonderful)\b", text))
    neg   = len(re.findall(r"\b(sad|angry|hate|terrible|awful|frustrated|upset|bad)\b", text))
    score = pos - neg
    state = "happy" if score > 0 else ("thinking" if score < 0 else "idle")
    return jsonify({"sentiment": state, "positive": pos, "negative": neg})


if __name__ == "__main__":
    port = int(os.getenv("PX_PY_PORT", 5050))
    debug = os.getenv("PX_ENV", "production") == "development"
    print(f"[PersonaX AI Service] Starting on port {port}")
    app.run(host="127.0.0.1", port=port, debug=debug)
