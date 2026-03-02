<?php

// Simple PHP wrapper to serve the embeddable widget container.
// In production you can include this file from any PHP app.
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>UD Voice Agent Widget</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="min-h-screen bg-slate-200 flex items-center justify-center">
    <div id="ud-voice-agent-root" class="w-full max-w-[440px]"></div>

    <script>
      (function () {
        const root = document.getElementById('ud-voice-agent-root');
        if (!root) return;

        const brandId = '<?php echo isset($_GET['brandId']) ? htmlspecialchars($_GET['brandId'], ENT_QUOTES, 'UTF-8') : 'default'; ?>';

        fetch('/api/brand-config?brandId=' + encodeURIComponent(brandId))
          .then(res => res.json())
          .then(config => {
            const mode = config.mode || 'chat_only';
            const title = (config.brand && config.brand.title) || 'Spice Garden';
            const subtitle = (config.brand && config.brand.subtitle) || 'AI Assistant';

            if (mode === 'voice_only') {
              root.innerHTML = '<iframe src="voice.php?brandId=' + encodeURIComponent(brandId) + '" class="w-full h-[560px] border-0 rounded-[28px]"></iframe>';
              return;
            }

            if (mode === 'chat_only') {
              root.innerHTML = '<iframe src="chat.php?brandId=' + encodeURIComponent(brandId) + '" class="w-full h-[560px] border-0 rounded-[28px]"></iframe>';
              return;
            }

            // hybrid: default to voice with optional switch UI inside the iframe/app
            root.innerHTML = '<iframe src="voice.php?brandId=' + encodeURIComponent(brandId) + '" class="w-full h-[560px] border-0 rounded-[28px]"></iframe>';
          })
          .catch(() => {
            root.innerHTML = '<div class="p-6 rounded-2xl bg-white border border-slate-200 text-sm text-slate-600">Failed to load widget configuration.</div>';
          });
      })();
    </script>
  </body>
  </html>

