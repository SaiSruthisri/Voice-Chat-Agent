<?php

$brandId = isset($_GET['brandId']) ? htmlspecialchars($_GET['brandId'], ENT_QUOTES, 'UTF-8') : 'default';
$geminiApiKey = getenv('GEMINI_API_KEY') ?: '';
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$assetBaseUrl = isset($_GET['assetBaseUrl']) && is_string($_GET['assetBaseUrl'])
  ? htmlspecialchars($_GET['assetBaseUrl'], ENT_QUOTES, 'UTF-8')
  : '/assets';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>UD Voice Agent Voice</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="<?php echo $isEmbedded ? 'h-screen bg-slate-200 flex items-center justify-center' : 'min-h-screen bg-slate-200 flex items-center justify-center p-4'; ?>">
    <div class="<?php echo $isEmbedded ? 'w-full h-full rounded-[28px] border border-slate-200 bg-white overflow-hidden flex flex-col' : 'w-full max-w-[440px] h-[min(620px,calc(100vh-2rem))] rounded-[28px] border border-slate-200 bg-white overflow-hidden flex flex-col'; ?>">
      <header class="h-20 px-6 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center">
            <span class="font-semibold text-lg leading-none">N</span>
          </div>
          <div>
            <div class="font-semibold text-slate-900 text-3xl leading-none">Restraunt X</div>
            <div class="text-xs text-slate-500">AI Voice Assistant</div>
          </div>
        </div>

        <div class="flex items-center gap-2 text-slate-600">
          <a
            href="chat.php?brandId=<?php echo urlencode($brandId); ?><?php echo $isEmbedded ? '&embed=1' : ''; ?>&assetBaseUrl=<?php echo urlencode($assetBaseUrl); ?>"
            class="px-3 py-1.5 rounded-lg border border-slate-300 bg-slate-50 text-slate-700 text-xs hover:bg-slate-100 transition"
          >
            Chat
          </a>
          <button
            type="button"
            class="w-9 h-9 rounded-full text-slate-600 hover:bg-slate-200 transition flex items-center justify-center"
            title="Reset"
            onclick="window.location.reload()"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
              <path d="M3 12a9 9 0 1 0 3-6.708"/>
              <path d="M3 3v6h6"/>
            </svg>
          </button>
          <button
            type="button"
            class="w-9 h-9 rounded-full text-slate-600 hover:bg-slate-200 transition flex items-center justify-center"
            title="Close"
            onclick="window.history.back()"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
              <path d="M18 6 6 18"/>
              <path d="m6 6 12 12"/>
            </svg>
          </button>
        </div>
      </header>

      <div class="flex-1 min-h-0 bg-white flex items-center justify-center relative overflow-hidden pt-2">
        <div class="relative z-10 flex flex-col items-center gap-3 -translate-y-8">
          <div class="relative w-44 h-44 flex items-center justify-center">
            <div
              id="voice-glow"
              class="absolute inset-0 rounded-full bg-teal-500/25 blur-xl transition-all duration-200 opacity-0 scale-95"
            ></div>
            <div class="relative w-36 h-36 rounded-full bg-teal-800 text-white flex items-center justify-center overflow-hidden">
              <img
                id="voice-logo"
                src="<?php echo $assetBaseUrl; ?>/voice-bot-logo.png"
                alt="Voice assistant"
                class="w-full h-full object-cover"
              />
              <span id="voice-logo-fallback" class="hidden font-semibold text-5xl leading-none">N</span>
            </div>
          </div>

          <div id="voice-timer" class="text-slate-800 text-xl font-mono tracking-wider hidden">
            00:00
          </div>

          <div id="voice-hint" class="text-slate-500 text-[11px] text-center">
            Tap Start to begin.
          </div>
        </div>

        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex items-center gap-4">
          <button
            id="voice-start"
            class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-emerald-400 text-slate-900 text-sm hover:bg-emerald-300 transition"
            type="button"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
              <path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
              <path d="M19 10v1a7 7 0 0 1-14 0v-1" />
              <path d="M12 18v4" />
              <path d="M8 22h8" />
            </svg>
            Start
          </button>
          <button
            id="voice-mic"
            class="hidden w-14 h-14 rounded-full flex items-center justify-center transition bg-emerald-400 text-slate-900 hover:bg-emerald-300"
            type="button"
            aria-label="Mute microphone"
            title="Mute"
          >
            <svg id="voice-mic-icon-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5">
              <path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
              <path d="M19 10v1a7 7 0 0 1-14 0v-1" />
              <path d="M12 18v4" />
              <path d="M8 22h8" />
            </svg>
            <svg id="voice-mic-icon-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 hidden">
              <path d="m2 2 20 20" />
              <path d="M9 5a3 3 0 0 1 5.12-2.12" />
              <path d="M15 10v1a3 3 0 0 1-.88 2.12" />
              <path d="M5 10v1a7 7 0 0 0 11.65 5" />
              <path d="M12 18v4" />
              <path d="M8 22h8" />
            </svg>
          </button>
          <button
            id="voice-end"
            class="hidden inline-flex items-center gap-2 px-8 py-3 rounded-full border-[3px] border-white/90 bg-red-500 text-white text-sm hover:bg-red-400 transition"
            type="button"
          >
            <span class="w-3 h-3 bg-white rounded-sm"></span>
            End
          </button>
        </div>

        <div
          id="voice-error"
          class="absolute bottom-28 left-1/2 -translate-x-1/2 z-10 text-rose-300 text-xs text-center max-w-sm px-4 hidden"
        ></div>
      </div>
    </div>

    <script type="module">
      import {
        GoogleGenAI,
        Modality,
        Type,
        StartSensitivity,
        EndSensitivity,
        ActivityHandling,
      } from 'https://esm.run/@google/genai';

      const brandId = '<?php echo $brandId; ?>';
      const apiKey = '<?php echo htmlspecialchars($geminiApiKey, ENT_QUOTES, 'UTF-8'); ?>';
      const assetBaseUrl = '<?php echo $assetBaseUrl; ?>';
      const logoElement = document.getElementById('voice-logo');
      const logoFallbackElement = document.getElementById('voice-logo-fallback');

      if (logoElement) {
        const normalizedAssetBaseUrl = assetBaseUrl.replace(/\/$/, '');
        const logoCandidates = [
          normalizedAssetBaseUrl + '/voice-bot-logo.png',
          '../assets/voice-bot-logo.png',
          'assets/voice-bot-logo.png',
          '../../assets/voice-bot-logo.png',
        ];
        let logoIndex = 0;

        const tryNextLogo = () => {
          if (logoIndex >= logoCandidates.length) {
            logoElement.classList.add('hidden');
            logoFallbackElement?.classList.remove('hidden');
            return;
          }
          logoElement.src = logoCandidates[logoIndex];
          logoIndex += 1;
        };

        logoElement.addEventListener('error', tryNextLogo);
        logoElement.addEventListener('load', () => {
          logoElement.classList.remove('hidden');
          logoFallbackElement?.classList.add('hidden');
        });

        tryNextLogo();
      }

      const VOICE_SYSTEM_INSTRUCTION = `# SPICE GARDEN VOICE ASSISTANT

You are a warm and natural voice assistant for Spice Garden.

## VOICE STYLE
- Speak naturally, like a helpful restaurant host.
- Keep each response short (1-2 sentences) but friendly not cut-shot speaking tone.
- Ask only one question at a time.
- Do not use JSON, labels, or internal metadata.
- Never say words like "state", "actions", or workflow names.

## TASK RULES
- Help with menu questions, restaurant info, order placement, and payment.
- Use tools for menu data, restaurant details, order creation, and payment.
- Before placing an order, collect details in this exact order and do not skip ahead.

## CONVERSATIONAL VIBE
1. Be Human: Be polite, warm, and proactive.
2. Don't Wait: If someone asks about the menu, naturally suggest what goes well with their choice.
3. Handle missing info gracefully: If you need a phone number, ask for it as part of the conversation.

## OUTPUT RULE
- Return plain conversational speech only.
`;

      const LIVE_TOOLS = [
        {
          name: 'get_menu',
          description: 'Retrieves the restaurant menu, including variants and add-ons.',
          parameters: { type: Type.OBJECT, properties: {} },
        },
        {
          name: 'get_restaurant_info',
          description: 'Returns restaurant details such as address and opening hours.',
          parameters: { type: Type.OBJECT, properties: {} },
        },
        {
          name: 'place_order',
          description: 'Finalizes order after user confirmation.',
          parameters: {
            type: Type.OBJECT,
            properties: {
              items: {
                type: Type.ARRAY,
                items: {
                  type: Type.OBJECT,
                  properties: {
                    name: { type: Type.STRING },
                    variant: { type: Type.STRING },
                    add_ons: { type: Type.ARRAY, items: { type: Type.STRING } },
                    notes: { type: Type.STRING },
                  },
                  required: ['name'],
                },
              },
              phone: { type: Type.STRING },
              global_notes: { type: Type.STRING },
            },
            required: ['items', 'phone'],
          },
        },
        {
          name: 'process_payment',
          description: 'Processes payment for a confirmed order.',
          parameters: {
            type: Type.OBJECT,
            properties: {
              order_id: { type: Type.STRING },
              payment_method: { type: Type.STRING },
            },
            required: ['order_id', 'payment_method'],
          },
        },
      ];

      function encode(bytes) {
        let binary = '';
        const length = bytes.byteLength;
        for (let index = 0; index < length; index++) {
          binary += String.fromCharCode(bytes[index]);
        }
        return btoa(binary);
      }

      function decode(base64) {
        const binaryString = atob(base64);
        const length = binaryString.length;
        const bytes = new Uint8Array(length);
        for (let index = 0; index < length; index++) {
          bytes[index] = binaryString.charCodeAt(index);
        }
        return bytes;
      }

      async function decodeAudioDataPcm(data, audioContext, sampleRate, numChannels) {
        const dataInt16 = new Int16Array(data.buffer);
        const frameCount = dataInt16.length / numChannels;
        const buffer = audioContext.createBuffer(numChannels, frameCount, sampleRate);

        for (let channel = 0; channel < numChannels; channel++) {
          const channelData = buffer.getChannelData(channel);
          for (let index = 0; index < frameCount; index++) {
            channelData[index] = dataInt16[index * numChannels + channel] / 32768.0;
          }
        }

        return buffer;
      }

      function createPcmBlob(data) {
        const length = data.length;
        const int16 = new Int16Array(length);
        for (let index = 0; index < length; index++) {
          int16[index] = data[index] * 32768;
        }
        return {
          data: encode(new Uint8Array(int16.buffer)),
          mimeType: 'audio/pcm;rate=16000',
        };
      }

      const glow = document.getElementById('voice-glow');
      const timerEl = document.getElementById('voice-timer');
      const hintEl = document.getElementById('voice-hint');
      const startBtn = document.getElementById('voice-start');
      const micBtn = document.getElementById('voice-mic');
      const micIconOn = document.getElementById('voice-mic-icon-on');
      const micIconOff = document.getElementById('voice-mic-icon-off');
      const endBtn = document.getElementById('voice-end');
      const errorEl = document.getElementById('voice-error');

      if (!startBtn || !micBtn || !endBtn || !glow || !timerEl || !hintEl || !micIconOn || !micIconOff || !errorEl) {
        console.error('Voice widget DOM not ready');
      } else {
        let isCalling = false;
        let isConnecting = false;
        let isBotSpeaking = false;
        let isMicMuted = false;
        let isUserSpeaking = false;
        let isThinking = false;
        let inputLevel = 0;
        let seconds = 0;
        let timerId = null;

        let session = null;
        let inputAudioContext = null;
        let outputAudioContext = null;
        let mediaStream = null;
        let mediaSource = null;
        let processor = null;
        let nextStartTime = 0;
        const activeSources = new Set();
        let hasFetchedMenu = false;
        let allowedMenuItemNames = new Set();
        let micUiUpdateTs = 0;
        let speakingDecayTimeout = null;
        let thinkingTimeout = null;

        function formatTime(totalSeconds) {
          const m = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
          const s = String(totalSeconds % 60).padStart(2, '0');
          return m + ':' + s;
        }

        function setError(message) {
          if (!message) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
          } else {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
          }
        }

        function friendlyErrorMessage(rawError) {
          if (!rawError) return null;
          const lower = rawError.toLowerCase();
          if (lower.includes('missing api key')) return 'Voice connection is not configured. Please add your API key and try again.';
          if (lower.includes('network')) return 'Network issue while on voice call. Please retry.';
          if (lower.includes('closed')) return 'Call disconnected. You can start the call again.';
          if (lower.includes('microphone')) return 'Microphone access failed. Please reconnect mic and try again.';
          return 'Voice call had an issue. Please try again.';
        }

        function currentStatus() {
          if (isConnecting) return 'Connecting...';
          if (isBotSpeaking) return 'Speaking...';
          if (isMicMuted && isCalling) return 'Muted';
          if (isUserSpeaking) return 'Listening...';
          if (isThinking && isCalling) return 'Thinking...';
          if (isCalling) return 'Ready';
          return 'Idle';
        }

        function statusHint() {
          if (isConnecting) return 'Setting up your voice session.';
          if (isBotSpeaking) return 'Assistant is responding.';
          if (isMicMuted && isCalling) return 'Microphone is muted. Tap mic to unmute.';
          if (isUserSpeaking) return 'You are being heard.';
          if (isThinking && isCalling) return 'Processing your last message.';
          if (isCalling) return 'Speak any time.';
          return 'Tap Start to begin.';
        }

        function updateUi() {
          if (isCalling) {
            glow.classList.remove('opacity-0', 'scale-95');
            glow.classList.add('opacity-100', 'scale-110');
            glow.style.transform = `scale(${1 + inputLevel * 0.4})`;
            timerEl.classList.remove('hidden');
            timerEl.textContent = formatTime(seconds);
            hintEl.textContent = statusHint();

            startBtn.classList.add('hidden');
            micBtn.classList.remove('hidden');
            endBtn.classList.remove('hidden');

            micBtn.classList.toggle('bg-slate-300', isMicMuted);
            micBtn.classList.toggle('text-slate-600', isMicMuted);
            micBtn.classList.toggle('bg-emerald-400', !isMicMuted);
            micBtn.classList.toggle('text-slate-900', !isMicMuted);
            micIconOn.classList.toggle('hidden', isMicMuted);
            micIconOff.classList.toggle('hidden', !isMicMuted);
          } else {
            glow.classList.add('opacity-0', 'scale-95');
            glow.classList.remove('opacity-100', 'scale-110');
            glow.style.transform = '';
            timerEl.classList.add('hidden');
            hintEl.textContent = 'Tap Start to begin.';

            startBtn.classList.remove('hidden');
            micBtn.classList.add('hidden');
            endBtn.classList.add('hidden');
          }
        }

        async function executeToolCall(functionCall) {
          try {
            switch (functionCall.name) {
              case 'get_menu': {
                const res = await fetch('/api/tools/get_menu');
                const response = await res.json();
                const menuItems = Array.isArray(response?.data) ? response.data : [];
                const names = menuItems
                  .map(item => (item && typeof item.name === 'string' ? item.name.trim().toLowerCase() : ''))
                  .filter(name => !!name);
                allowedMenuItemNames = new Set(names);
                hasFetchedMenu = true;
                return response;
              }
              case 'get_restaurant_info': {
                const res = await fetch('/api/tools/get_restaurant_info');
                return await res.json();
              }
              case 'place_order': {
                if (!hasFetchedMenu || allowedMenuItemNames.size === 0) {
                  return {
                    status: 'error',
                    message: 'Menu not loaded. Call get_menu first and only use exact menu item names.',
                  };
                }
                const items = Array.isArray(functionCall.args?.items) ? functionCall.args.items : [];
                const unknownItems = items
                  .map(item => (item && typeof item.name === 'string' ? item.name.trim() : ''))
                  .filter(name => !!name)
                  .filter(name => !allowedMenuItemNames.has(name.toLowerCase()));
                if (unknownItems.length > 0) {
                  return {
                    status: 'error',
                    message: `Unknown items in order: ${unknownItems.join(', ')}. Use only names returned by get_menu.`,
                  };
                }
                const res = await fetch('/api/tools/place_order', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    items: functionCall.args?.items,
                    phone: functionCall.args?.phone,
                    global_notes: functionCall.args?.global_notes,
                  }),
                });
                return await res.json();
              }
              case 'process_payment': {
                const res = await fetch('/api/tools/process_payment', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    order_id: functionCall.args?.order_id,
                    payment_method: functionCall.args?.payment_method,
                  }),
                });
                return await res.json();
              }
              default:
                return { status: 'error', message: 'Unknown tool.' };
            }
          } catch (e) {
            return { status: 'error', message: 'Backend error.' };
          }
        }

        async function stopCall() {
          isCalling = false;
          isConnecting = false;
          isBotSpeaking = false;
          isMicMuted = false;
          isUserSpeaking = false;
          isThinking = false;
          inputLevel = 0;
          seconds = 0;

          if (speakingDecayTimeout) {
            window.clearTimeout(speakingDecayTimeout);
            speakingDecayTimeout = null;
          }
          if (thinkingTimeout) {
            window.clearTimeout(thinkingTimeout);
            thinkingTimeout = null;
          }
          if (timerId) {
            window.clearInterval(timerId);
            timerId = null;
          }

          if (session) {
            try {
              session.close();
            } catch (e) {}
            session = null;
          }

          if (processor) {
            processor.disconnect();
            processor.onaudioprocess = null;
            processor = null;
          }
          if (mediaSource) {
            mediaSource.disconnect();
            mediaSource = null;
          }
          if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
          }
          if (inputAudioContext) {
            try {
              await inputAudioContext.close();
            } catch (e) {}
            inputAudioContext = null;
          }
          if (outputAudioContext) {
            try {
              await outputAudioContext.close();
            } catch (e) {}
            outputAudioContext = null;
          }
          activeSources.forEach(source => {
            try {
              source.stop();
            } catch (e) {}
          });
          activeSources.clear();
          nextStartTime = 0;
          hasFetchedMenu = false;
          allowedMenuItemNames = new Set();
          updateUi();
        }

        async function startCall() {
          if (isCalling || isConnecting) return;
          if (!apiKey) {
            setError('Missing API key for voice call.');
            return;
          }
          try {
            setError('');
            isConnecting = true;
            updateUi();

            const ai = new GoogleGenAI({ apiKey });
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaStream = stream;

            const InputContextCtor = window.AudioContext || window.webkitAudioContext;
            const inputCtx = new InputContextCtor({ sampleRate: 16000 });
            const outputCtx = new InputContextCtor({ sampleRate: 24000 });

            if (inputCtx.state === 'suspended') await inputCtx.resume();
            if (outputCtx.state === 'suspended') await outputCtx.resume();

            inputAudioContext = inputCtx;
            outputAudioContext = outputCtx;

            const sessionPromise = ai.live.connect({
              model: 'gemini-2.5-flash-native-audio-preview-12-2025',
              config: {
                responseModalities: [Modality.AUDIO],
                speechConfig: {
                  voiceConfig: { prebuiltVoiceConfig: { voiceName: 'Kore' } },
                },
                realtimeInputConfig: {
                  activityHandling: ActivityHandling.START_OF_ACTIVITY_INTERRUPTS,
                  automaticActivityDetection: {
                    startOfSpeechSensitivity: StartSensitivity.START_SENSITIVITY_HIGH,
                    endOfSpeechSensitivity: EndSensitivity.END_SENSITIVITY_HIGH,
                    prefixPaddingMs: 20,
                    silenceDurationMs: 90,
                  },
                },
                systemInstruction: VOICE_SYSTEM_INSTRUCTION,
                tools: [{ functionDeclarations: LIVE_TOOLS }],
              },
              callbacks: {
                onopen: () => {
                  isConnecting = false;
                  isCalling = true;
                  seconds = 0;
                  if (timerId) window.clearInterval(timerId);
                  timerId = window.setInterval(() => {
                    seconds += 1;
                    updateUi();
                  }, 1000);
                  updateUi();

                  let liveSession = null;
                  sessionPromise
                    .then(s => {
                      liveSession = s;
                    })
                    .catch(() => {});

                  const source = inputCtx.createMediaStreamSource(stream);
                  const proc = inputCtx.createScriptProcessor(256, 1, 1);
                  mediaSource = source;
                  processor = proc;

                  proc.onaudioprocess = event => {
                    if (!liveSession) return;

                    if (isMicMuted) {
                      inputLevel = inputLevel * 0.8;
                      isUserSpeaking = false;
                      return;
                    }

                    const inputData = event.inputBuffer.getChannelData(0);
                    let energy = 0;
                    for (let i = 0; i < inputData.length; i++) {
                      const sample = inputData[i];
                      energy += sample * sample;
                    }
                    const rms = Math.sqrt(energy / inputData.length);
                    const now = Date.now();
                    if (now - micUiUpdateTs > 70) {
                      inputLevel = inputLevel * 0.65 + Math.min(rms * 8, 1) * 0.35;
                      micUiUpdateTs = now;
                      updateUi();
                    }

                    if (rms > 0.012) {
                      isUserSpeaking = true;
                      isThinking = false;
                      if (speakingDecayTimeout) {
                        window.clearTimeout(speakingDecayTimeout);
                      }
                      speakingDecayTimeout = window.setTimeout(() => {
                        isUserSpeaking = false;
                        isThinking = true;
                        if (thinkingTimeout) {
                          window.clearTimeout(thinkingTimeout);
                        }
                        thinkingTimeout = window.setTimeout(() => {
                          isThinking = false;
                          updateUi();
                        }, 1800);
                        updateUi();
                      }, 200);
                      updateUi();
                    }

                    const pcmBlob = createPcmBlob(inputData);
                    try {
                      liveSession.sendRealtimeInput({ media: pcmBlob });
                    } catch (e) {
                      setError('Microphone stream interrupted. Please reconnect.');
                    }
                  };

                  source.connect(proc);
                  proc.connect(inputCtx.destination);
                },
                onmessage: async message => {
                  const audioData = message.serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
                  if (audioData && outputAudioContext) {
                    const outputCtxNow = outputAudioContext;
                    nextStartTime = Math.max(nextStartTime, outputCtxNow.currentTime);
                    isBotSpeaking = true;
                    isThinking = false;
                    const buffer = await decodeAudioDataPcm(decode(audioData), outputCtxNow, 24000, 1);
                    const audioSource = outputCtxNow.createBufferSource();
                    audioSource.buffer = buffer;
                    audioSource.connect(outputCtxNow.destination);
                    audioSource.onended = () => {
                      activeSources.delete(audioSource);
                      if (activeSources.size === 0) {
                        isBotSpeaking = false;
                        updateUi();
                      }
                    };
                    audioSource.start(nextStartTime);
                    nextStartTime += buffer.duration;
                    activeSources.add(audioSource);
                    updateUi();
                  }

                  if (message.toolCall?.functionCalls?.length && session) {
                    const functionCalls = message.toolCall.functionCalls;
                    const functionResponses = await Promise.all(
                      functionCalls.map(async functionCall => ({
                        id: functionCall.id,
                        name: functionCall.name,
                        response: await executeToolCall(functionCall),
                      })),
                    );
                    if (functionResponses.length > 0) {
                      session.sendToolResponse({ functionResponses });
                    }
                  }

                  if (message.serverContent?.interrupted) {
                    activeSources.forEach(sourceNode => {
                      try {
                        sourceNode.stop();
                      } catch (e) {}
                    });
                    activeSources.clear();
                    nextStartTime = 0;
                    isBotSpeaking = false;
                    updateUi();
                  }
                },
                onerror: event => {
                  const detail = event?.error?.message || event?.message || 'Network error during live call.';
                  setError(friendlyErrorMessage(detail));
                  stopCall();
                },
                onclose: event => {
                  if (isConnecting || isCalling) {
                    const code = event?.code ? `code ${event.code}` : 'unknown code';
                    const reason = event?.reason ? `: ${event.reason}` : '';
                    setError(`Live connection closed (${code}${reason})`);
                  }
                  stopCall();
                },
              },
            });

            session = await sessionPromise;
            try {
              session.sendRealtimeInput({
                text: 'Agent: Start the call now. Greet the customer professionally in English.',
              });
            } catch (e) {}
          } catch (err) {
            setError((err && err.message) || 'Failed to start voice call.');
            await stopCall();
          }
        }

        startBtn.addEventListener('click', () => {
          startCall().catch(e => {
            setError('Failed to start voice call.');
          });
        });

        micBtn.addEventListener('click', () => {
          if (!isCalling) return;
          isMicMuted = !isMicMuted;
          updateUi();
        });

        endBtn.addEventListener('click', () => {
          if (!isCalling && !isConnecting) return;
          stopCall().catch(() => {});
        });

        updateUi();
      }
    </script>
  </body>
  </html>

