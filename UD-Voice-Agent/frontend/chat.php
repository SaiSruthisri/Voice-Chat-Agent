<?php

$brandId = isset($_GET['brandId']) ? htmlspecialchars($_GET['brandId'], ENT_QUOTES, 'UTF-8') : 'default';
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$assetBaseUrl = isset($_GET['assetBaseUrl']) && is_string($_GET['assetBaseUrl'])
  ? htmlspecialchars($_GET['assetBaseUrl'], ENT_QUOTES, 'UTF-8')
  : '/assets';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>UD Voice Agent Chat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="<?php echo $isEmbedded ? 'h-screen bg-slate-200 flex items-center justify-center' : 'min-h-screen bg-slate-200 flex items-center justify-center p-4'; ?>">
    <div id="chat-root" class="<?php echo $isEmbedded ? 'w-full h-full' : 'w-full max-w-[440px] h-[min(620px,calc(100vh-2rem))]'; ?>"></div>

    <script>
      (function () {
        const root = document.getElementById('chat-root');
        if (!root) return;

        const brandId = '<?php echo $brandId; ?>';
        const isEmbedded = <?php echo $isEmbedded ? 'true' : 'false'; ?>;
        const assetBaseUrl = '<?php echo $assetBaseUrl; ?>';

        const state = {
          messages: [],
          isLoading: false,
          selectedAction: null,
          inputPlaceholder: 'Type your message…'
        };

        const ACTION_TO_MESSAGE = {
          'Show menu': 'Show me the menu.',
          'Restaurant info': 'Tell me about the restaurant, address, and opening hours.',
          'Start order': 'I want to place an order. If any order already exists in this chat, resume it and do not reset my items.',
          'Track order': 'Track my order status.',
          'Show popular items': 'Show popular items',
          'No add-ons': 'No add-ons',
          'Use my saved number': 'Use my saved number',
          'Share phone now': 'I will share my number now',
          'Skip for now': 'Skip for now. I do not want to share my phone number right now. Pause checkout and ask what else I want to do.',
          'Confirm order': 'Confirm order',
          'Edit items': 'Edit items',
          'Edit notes': 'Edit notes',
          'Cancel order': 'Cancel order',
          'Pay now': 'Pay now',
          'New order': 'Start a new order',
          'Call restaurant': 'Call restaurant'
        };

        const ACTION_TO_INTENT = {
          'Show menu': 'intent.menu.show',
          'Restaurant info': 'intent.restaurant.info',
          'Start order': 'intent.order.start',
          'Track order': 'intent.order.track',
          'Show popular items': 'intent.menu.popular',
          'No add-ons': 'intent.addon.none',
          'Use my saved number': 'intent.phone.use_saved',
          'Share phone now': 'intent.phone.share_now',
          'Skip for now': 'intent.phone.skip',
          'Confirm order': 'intent.order.confirm',
          'Edit items': 'intent.order.edit_items',
          'Edit notes': 'intent.order.edit_notes',
          'Cancel order': 'intent.order.cancel',
          'Pay now': 'intent.payment.pay_now',
          'New order': 'intent.order.new',
          'Call restaurant': 'intent.restaurant.call'
        };

        function getActionHint(chatState) {
          if (chatState === 'ASKING_PHONE') return 'What would you like to do next? You can tap an option below or type your request.';
          if (chatState === 'AWAITING_CONFIRMATION') return 'Choose how you want to proceed.';
          if (chatState === 'ORDER_PLACED') return 'You can continue with one of these options.';
          return 'Select an option below or type your message.';
        }

        function getActionsForState(chatState) {
          if (chatState === 'IDLE') {
            return [
              { label: 'Show menu', value: 'Show menu', type: 'normal' },
              { label: 'Restaurant info', value: 'Restaurant info', type: 'normal' },
              { label: 'Show popular items', value: 'Show popular items', type: 'normal' },
              { label: 'Start order', value: 'Start order', type: 'confirm' },
              { label: 'Track order', value: 'Track order', type: 'normal' },
              { label: 'Type your message', value: 'Type your message', type: 'normal' }
            ];
          }
          if (chatState === 'ASKING_PHONE') {
            return [
              { label: 'Use my saved number', value: 'Use my saved number', type: 'confirm' },
              { label: 'Share phone now', value: 'Share phone now', type: 'confirm' },
              { label: 'Skip for now', value: 'Skip for now', type: 'normal' },
              { label: 'Type your message', value: 'Type your message', type: 'normal' }
            ];
          }
          if (chatState === 'SUGGESTING_ADDONS') {
            return [
              { label: 'No add-ons', value: 'No add-ons', type: 'normal' },
              { label: 'Type your message', value: 'Type your message', type: 'normal' }
            ];
          }
          if (chatState === 'AWAITING_CONFIRMATION') {
            return [
              { label: 'Confirm order', value: 'Confirm order', type: 'confirm' },
              { label: 'Edit items', value: 'Edit items', type: 'normal' },
              { label: 'Edit notes', value: 'Edit notes', type: 'normal' },
              { label: 'Cancel order', value: 'Cancel order', type: 'normal' }
            ];
          }
          if (chatState === 'ORDER_PLACED') {
            return [
              { label: 'Pay now', value: 'Pay now', type: 'confirm' },
              { label: 'Track order', value: 'Track order', type: 'normal' },
              { label: 'New order', value: 'New order', type: 'confirm' },
              { label: 'Call restaurant', value: 'Call restaurant', type: 'normal' }
            ];
          }
          return [];
        }

        function getLatestModelState() {
          for (let index = state.messages.length - 1; index >= 0; index--) {
            const message = state.messages[index];
            if (message.role === 'model' && typeof message.state === 'string') {
              return message.state;
            }
          }
          return 'IDLE';
        }

        function getHistoryPayload() {
          return state.messages
            .slice(-12)
            .map(message => ({ role: message.role, content: message.content }))
            .filter(message => typeof message.content === 'string' && message.content.trim() !== '');
        }

        function normalizeAssistantResponse(data, fallbackState, actionValue) {
          const rawReply = typeof data?.reply === 'string' ? data.reply.trim() : '';
          const responseState = typeof data?.state === 'string' ? data.state : fallbackState;
          const fallback = { reply: '', state: responseState };

          if (rawReply) {
            try {
              const parsed = JSON.parse(rawReply);
              if (parsed && typeof parsed === 'object') {
                const parsedReply = typeof parsed.reply === 'string' ? parsed.reply.trim() : '';
                const parsedState = typeof parsed.state === 'string' ? parsed.state : responseState;
                if (parsedReply) {
                  fallback.reply = parsedReply;
                  fallback.state = parsedState;
                  if (fallback.state === 'IDLE' && actionValue === 'Show menu') fallback.state = 'BROWSING_MENU';
                  if (fallback.state === 'IDLE' && actionValue === 'Start order') fallback.state = 'CHOOSING_ITEM';
                  return fallback;
                }
              }
            } catch {
            }
            fallback.reply = rawReply;
            if (fallback.state === 'IDLE' && actionValue === 'Show menu') fallback.state = 'BROWSING_MENU';
            if (fallback.state === 'IDLE' && actionValue === 'Start order') fallback.state = 'CHOOSING_ITEM';
            return fallback;
          }
          const error = typeof data?.error === 'string' ? data.error.trim() : '';
          if (error) return { reply: 'I hit a small issue: ' + error, state: responseState };
          return { reply: 'I hit a small connection issue. Please try again.', state: responseState };
        }

        function render() {
          const latest = state.messages[state.messages.length - 1];
          const latestModelIndex = (() => {
            for (let i = state.messages.length - 1; i >= 0; i--) {
              if (state.messages[i].role === 'model') return i;
            }
            return -1;
          })();

          const historyHtml = state.messages.map((msg, index) => {
            const side = msg.role === 'user' ? 'justify-end' : 'justify-start';
            const bubble = 'rounded-2xl px-4 py-3 text-sm leading-6 border text-slate-800 ' + (msg.role === 'user' ? 'bg-slate-100 border-slate-200' : 'bg-blue-50 border-blue-100');
            const messageState = typeof msg.state === 'string' ? msg.state : 'IDLE';
            const stateActions = getActionsForState(messageState);
            const actionsHtml = (msg.role === 'model' && index === latestModelIndex && stateActions.length > 0)
              ? (
                '<div class="flex gap-2 mt-2 flex-wrap">' +
                  '<div class="w-full text-xs text-slate-500">' + getActionHint(messageState) + '</div>' +
                  stateActions.map(action => {
                    const isSelected = state.selectedAction === action.value;
                    const baseClass = action.type === 'confirm'
                      ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                      : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50';
                    const selectedClass = isSelected ? ' ring-2 ring-emerald-200 border-emerald-400' : '';
                    const disabledClass = state.isLoading ? ' opacity-70 cursor-not-allowed' : '';
                    const disabledAttr = state.isLoading ? ' disabled' : '';
                    return '<button' + disabledAttr + ' class="quick-action px-3 py-1.5 rounded-full border text-xs transition' + selectedClass + disabledClass + ' ' + baseClass + '" data-action="' + action.value.replace(/"/g, '&quot;') + '">' + action.label + '</button>';
                  }).join('') +
                '</div>'
              )
              : '';
            return (
              '<div class="flex ' + side + '">' +
                '<div class="max-w-[88%]">' +
                  '<div class="' + bubble + '">' +
                    '<div class="whitespace-pre-wrap">' + msg.content.replace(/</g, '&lt;') + '</div>' +
                  '</div>' +
                  actionsHtml +
                '</div>' +
              '</div>'
            );
          }).join('');

          root.innerHTML =
            '<div class="w-full h-full rounded-[28px] border border-slate-200 bg-white overflow-hidden flex flex-col">' +
              '<header class="h-20 px-6 bg-slate-100 border-b border-slate-200 flex items-center justify-between">' +
                '<div class="flex items-center gap-3">' +
                  '<div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center">' +
                    '<span class="font-semibold text-lg">N</span>' +
                  '</div>' +
                  '<div>' +
                    '<div class="font-semibold text-slate-900 text-3xl leading-none">Nuno</div>' +
                    '<div class="text-xs text-slate-500">AI Chat Assistant</div>' +
                  '</div>' +
                '</div>' +

                '<div class="flex items-center gap-2 text-slate-600">' +
                  '<a href="voice.php?brandId=' + encodeURIComponent(brandId) + (isEmbedded ? '&embed=1' : '') + '&assetBaseUrl=' + encodeURIComponent(assetBaseUrl) + '" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs hover:bg-slate-100 transition">Voice</a>' +
                  '<button id="chat-reset" class="w-9 h-9 rounded-full hover:bg-slate-200 transition flex items-center justify-center" title="Reset chat"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M3 12a9 9 0 1 0 3-6.708"/><path d="M3 3v6h6"/></svg></button>' +
                  '<button id="chat-close" class="w-9 h-9 rounded-full hover:bg-slate-200 transition flex items-center justify-center" title="Close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
                '</div>' +
              '</header>' +

              '<div id="chat-scroll" class="flex-1 min-h-0 overflow-y-auto px-6 py-4 space-y-4 bg-white">' +
                historyHtml +
                (state.isLoading
                  ? '<div class="flex justify-start">' +
                      '<div class="bg-white border border-slate-200 px-4 py-3 rounded-2xl flex gap-2 items-center">' +
                        '<div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay:0ms"></div>' +
                        '<div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay:150ms"></div>' +
                        '<div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay:300ms"></div>' +
                      '</div>' +
                    '</div>'
                  : '') +
              '</div>' +

              '<div class="p-5 bg-white border-t border-slate-100">' +
                '<div class="w-full h-14 px-3 rounded-2xl border border-slate-200 bg-white flex items-center gap-2">' +
                  '<input id="chat-input" type="text" class="flex-1 h-full bg-transparent border-none focus:outline-none text-sm text-slate-700 placeholder:text-slate-400" placeholder="' + state.inputPlaceholder.replace(/"/g, '&quot;') + '"/>' +
                    '<button id="chat-send" class="w-9 h-9 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition flex items-center justify-center" title="Send message">' +
                      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="m3 3 3 9-3 9 19-9Z"/><path d="M6 12h16"/></svg>' +
                    '</button>' +
                '</div>' +
              '</div>' +
            '</div>';

          const input = document.getElementById('chat-input');
          const sendBtn = document.getElementById('chat-send');
          const resetBtn = document.getElementById('chat-reset');
          const closeBtn = document.getElementById('chat-close');
          const scrollEl = document.getElementById('chat-scroll');
          if (scrollEl) {
            scrollEl.scrollTop = scrollEl.scrollHeight;
          }
          if (!input || !sendBtn) return;

          function doSend() {
            const text = input.value.trim();
            if (!text || state.isLoading) return;
            const currentState = getLatestModelState();
            state.selectedAction = null;
            state.inputPlaceholder = 'Type your message…';
            state.messages.push({ role: 'user', content: text });
            input.value = '';
            state.isLoading = true;
            render();

            fetch('/api/conversation', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ brandId, channel: 'chat', message: text, currentState, history: getHistoryPayload() })
            })
              .then(res => res.json())
              .then(data => {
                const model = normalizeAssistantResponse(data, getLatestModelState(), null);
                state.messages.push({ role: 'model', content: model.reply, state: model.state });
              })
              .catch(() => {
                state.messages.push({ role: 'model', content: 'I hit a small connection issue. Please try again.', state: 'IDLE' });
              })
              .finally(() => {
                state.isLoading = false;
                state.selectedAction = null;
                render();
              });
          }

          sendBtn.addEventListener('click', doSend);
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') doSend();
          });

          if (resetBtn) {
            resetBtn.addEventListener('click', function () {
              state.messages = [{ role: 'model', content: 'Fresh start. What would you like today?', state: 'IDLE' }];
              state.isLoading = false;
              state.selectedAction = null;
              state.inputPlaceholder = 'Type your message…';
              render();
            });
          }

          if (closeBtn) {
            closeBtn.addEventListener('click', function () {
              window.history.back();
            });
          }

          document.querySelectorAll('.quick-action').forEach(button => {
            button.addEventListener('click', function () {
              const text = button.getAttribute('data-action') || '';
              if (!text || state.isLoading) return;

              state.selectedAction = text;

              if (text === 'Type your message') {
                state.inputPlaceholder = 'Tell me what you need…';
                render();
                const nextInput = document.getElementById('chat-input');
                if (nextInput) {
                  nextInput.focus();
                }
                return;
              }

              state.inputPlaceholder = 'Type your message…';
              const payloadMessage = Object.prototype.hasOwnProperty.call(ACTION_TO_MESSAGE, text)
                ? ACTION_TO_MESSAGE[text]
                : text;
              const displayedUserMessage = text === 'Start order' ? payloadMessage : text;
              state.messages.push({ role: 'user', content: displayedUserMessage });
              state.isLoading = true;
              render();

              const intentKey = Object.prototype.hasOwnProperty.call(ACTION_TO_INTENT, text)
                ? ACTION_TO_INTENT[text]
                : null;
              const currentState = getLatestModelState();

              fetch('/api/conversation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ brandId, channel: 'chat', message: payloadMessage, intentKey, currentState, history: getHistoryPayload() })
              })
                .then(res => res.json())
                .then(data => {
                  const model = normalizeAssistantResponse(data, getLatestModelState(), text);
                  state.messages.push({ role: 'model', content: model.reply, state: model.state });
                })
                .catch(() => {
                  state.messages.push({ role: 'model', content: 'I hit a small connection issue. Please try again.', state: 'IDLE' });
                })
                .finally(() => {
                  state.isLoading = false;
                  state.selectedAction = null;
                  render();
                });
            });
          });

          if (!latest) {
            state.messages.push({
              role: 'model',
              content: 'Hi, welcome to Nuno. Tell me what you want to order.',
              state: 'IDLE'
            });
            render();
          }
        }

        render();
      })();
    </script>
  </body>
  </html>

