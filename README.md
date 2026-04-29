# Voice & Chat Agent

A dual-mode (chat + voice) AI ordering assistant for restaurants. Powered by Google Gemini, it lets customers browse the menu, build an order, and pay — all through natural conversation, whether they type or speak.

---

## What Is This?

**Voice & Chat Agent** is a self-contained restaurant AI assistant that runs as a lightweight PHP web app. It ships a persona called **Nuno** and is pre-configured for a demo restaurant called *Spice Garden*.

Two interaction modes are available:

| Mode | How it works |
|------|--------------|
| **Chat** | Type messages → PHP backend calls Gemini → structured reply rendered in browser |
| **Voice** | Speak → browser streams audio directly to Gemini Live API → Gemini speaks back in real time |

Both modes share the same tool set and business logic; only the transport and response format differ.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **LLM** | Google Gemini 2.5 Flash (chat) · Gemini Live API (voice) |
| **Backend** | PHP 8.2, no framework — plain classes and a router in `server.php` |
| **Frontend** | Vanilla JavaScript · Tailwind CSS (CDN) · PHP-rendered HTML |
| **Voice transport** | `@google/genai` ESM module loaded directly in the browser over WebSocket/WebRTC |
| **Containerisation** | Docker (PHP CLI built-in server, port 10000) |

---

## Code Architecture

### High-Level Flow

```
Browser (chat.php / voice.php)
        │
        │  POST /api/conversation  (chat)
        │  or WebSocket to Gemini Live (voice)
        ▼
  server.php  ──►  backend/index.php  (API router)
                         │
              ┌──────────┼──────────────┐
              ▼          ▼              ▼
        BrandService  IntentService  GeminiService
        (brands.php)  (intents.php)  (prompts.php)
                                         │
                              Gemini 2.5 Flash REST API
                                         │
                              Tool call returned by Gemini
                                         │
                                    MockBackend
                              ┌──────────┴──────────────┐
                         get_menu  get_restaurant_info  place_order  process_payment
```

### Key Design Decisions

#### 1. LLM Used Only for Tool Calling

Gemini is **never given hard-coded menu data or prices** in its prompt. Every time it needs real data it must call one of the four registered tools. This mirrors how a production integration would call a live POS/inventory API.

```
Gemini receives: user message + system prompt + conversation history
Gemini returns:  functionCall { name: "get_menu", args: {} }
Server executes: MockBackend::getMenu()
Server returns:  tool result back to Gemini
Gemini returns:  final conversational reply
```

Up to **five tool-call iterations** are allowed per turn to handle chained calls (e.g. get menu → place order).

#### 2. Mock APIs as Stand-ins for a Real Backend

`MockBackend.php` simulates a restaurant POS with in-memory data:

| Endpoint | What it does |
|----------|-------------|
| `GET  /api/tools/get_menu` | Returns items with variants and add-ons |
| `GET  /api/tools/get_restaurant_info` | Returns address and opening hours |
| `POST /api/tools/place_order` | Validates items/variants, generates an order ID, returns total |
| `POST /api/tools/process_payment` | Acknowledges payment (always succeeds in mock) |

Swap `MockBackend` for a real API client and nothing else in the stack needs to change.

#### 3. Conversation State Machine

The LLM is instructed to track and return one of nine explicit states in every reply:

```
IDLE → BROWSING_MENU → CHOOSING_ITEM → CHOOSING_VARIANT
     → SUGGESTING_ADDONS → ASKING_NOTES → ASKING_PHONE
     → AWAITING_CONFIRMATION → ORDER_PLACED
```

The frontend reads the current state to decide which quick-action buttons to render (e.g. *Confirm order*, *Skip for now*, *Pay now*), removing the need for the LLM to generate UI elements.

#### 4. Chat vs. Voice System Prompts

Two separate system prompts (`chat_system` / `voice_system` in `prompts.php`) tune Gemini's persona:

- **Chat prompt** — returns structured JSON `{ reply, state, actions }` so the frontend can parse state and drive the UI.
- **Voice prompt** — returns plain conversational speech; state is managed inside the Gemini Live session without JSON overhead.

#### 5. Multi-Brand Support

`brands.php` maps a `brandId` query parameter to a brand configuration (widget title, allowed modes, enabled intents). The `default` brand is chat-only; the `voice_first` brand enables the full hybrid (chat + voice) mode.

### Directory Layout

```
UD-Voice-Agent/
├── server.php                  # URL router — delegates to backend/ or serves frontend/
├── frontend/
│   ├── chat.php                # Chat widget (HTML + inline JS, no build step)
│   ├── voice.php               # Voice widget (Gemini Live, browser-side WebSocket)
│   └── widget.php              # Embeddable iframe entry point
├── backend/
│   ├── index.php               # API route handler
│   ├── config/
│   │   ├── brands.php          # Per-brand widget & mode config
│   │   ├── intents.php         # Intent → canonical message mapping
│   │   └── prompts.php         # System prompts for chat and voice
│   └── services/
│       ├── GeminiService.php   # Gemini REST calls + tool-call loop
│       ├── MockBackend.php     # In-memory restaurant data & order logic
│       ├── BrandService.php    # Brand config loader
│       └── IntentService.php   # Intent registry
assets/
└── voice-bot-logo.png
Dockerfile
```

---

## System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                        Browser                          │
│                                                         │
│  ┌──────────────┐          ┌──────────────────────────┐ │
│  │  chat.php    │          │       voice.php           │ │
│  │  (text UI)   │          │  (live audio streaming)   │ │
│  └──────┬───────┘          └────────────┬─────────────┘ │
│         │ POST /api/conversation         │ WebSocket      │
│         │                               │ (Gemini Live)  │
└─────────┼───────────────────────────────┼───────────────┘
          │                               │
          ▼                               ▼
┌─────────────────────┐      ┌────────────────────────────┐
│  PHP Backend        │      │   Google Gemini Live API   │
│  (server.php +      │ ◄──► │   (real-time audio/text    │
│   GeminiService)    │      │    + tool calls)           │
│                     │      └────────────────────────────┘
│  ┌───────────────┐  │
│  │ MockBackend   │  │      ┌────────────────────────────┐
│  │ ┌───────────┐ │  │ ◄──► │  Google Gemini 2.5 Flash  │
│  │ │ get_menu  │ │  │      │  REST API (chat turns)     │
│  │ │ place_ord │ │  │      └────────────────────────────┘
│  │ │ payment   │ │  │
│  │ └───────────┘ │  │
│  └───────────────┘  │
└─────────────────────┘
```

**Chat flow (numbered):**
1. User types a message → `POST /api/conversation`
2. `GeminiService` sends message + system prompt to Gemini 2.5 Flash
3. Gemini returns a tool call (e.g. `get_menu`)
4. `GeminiService` executes the tool via `MockBackend` and feeds the result back to Gemini
5. Gemini returns the final JSON reply `{ reply, state, actions }`
6. Browser renders the reply bubble and updates the quick-action buttons based on `state`

**Voice flow (numbered):**
1. User taps *Start* → browser requests mic access and opens a Gemini Live WebSocket session
2. Raw PCM audio is streamed to Gemini in real time
3. Gemini detects speech, transcribes, reasons, and may call tools (browser handles tool execution via `/api/tools/*`)
4. Gemini streams audio back; browser plays it through the Web Audio API
5. Visual glow on the avatar pulses in sync with the input level

---

## Running Locally

**Prerequisites:** PHP 8.2+ or Docker, and a [Gemini API key](https://aistudio.google.com/app/apikey).

### With PHP

```bash
cd UD-Voice-Agent
GEMINI_API_KEY=your_key_here php -S 0.0.0.0:10000 server.php
```

### With Docker

```bash
docker build -t ud-voice-agent .
docker run -e GEMINI_API_KEY=your_key_here -p 10000:10000 ud-voice-agent
```

Then open [http://localhost:10000](http://localhost:10000).

| URL | Description |
|-----|-------------|
| `/?brandId=default` | Chat-only assistant |
| `/?brandId=voice_first` | Hybrid chat + voice assistant |
| `/frontend/voice.php?brandId=voice_first` | Voice-only view |

> **Note:** The voice widget calls the Gemini Live API directly from the browser using the `GEMINI_API_KEY`. For production use, proxy the key through your backend.
