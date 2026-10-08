(function () {
  var panel = document.getElementById('site-chat');
  if (!panel) return;

  var toggle = document.getElementById('site-chat-toggle');
  var closeBtn = document.getElementById('site-chat-close');
  var form = document.getElementById('site-chat-form');
  var input = document.getElementById('site-chat-input');
  var log = document.getElementById('site-chat-log');
  var sendBtn = document.getElementById('site-chat-send');
  var history = [];
  var busy = false;

  function setOpen(open) {
    panel.classList.toggle('is-open', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && input) {
      setTimeout(function () { input.focus(); }, 50);
    }
  }

  function appendBubble(role, text) {
    var row = document.createElement('div');
    row.className = 'site-chat__msg site-chat__msg--' + role;
    var bubble = document.createElement('div');
    bubble.className = 'site-chat__bubble';
    bubble.textContent = text;
    row.appendChild(bubble);
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
    return bubble;
  }

  function setBusy(state) {
    busy = state;
    if (sendBtn) sendBtn.disabled = state;
    if (input) input.disabled = state;
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      setOpen(!panel.classList.contains('is-open'));
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setOpen(false);
      if (toggle) toggle.focus();
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (busy || !input) return;
      var text = String(input.value || '').trim();
      if (!text) return;

      input.value = '';
      appendBubble('user', text);
      setBusy(true);
      var thinking = appendBubble('assistant', '…');
      thinking.classList.add('is-thinking');

      fetch('chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: history }),
      })
        .then(function (r) {
          return r.json().then(function (data) {
            return { okHttp: r.ok, data: data };
          });
        })
        .then(function (result) {
          thinking.classList.remove('is-thinking');
          if (!result.data || !result.data.ok || !result.data.reply) {
            var err = result.data && result.data.error;
            var msg = 'Die KI antwortet gerade nicht.';
            if (err === 'rate') msg = 'Bitte kurz warten und erneut senden.';
            else if (err === 'model') msg = 'Kein Ollama-Modell gefunden.';
            else if (err === 'ollama') msg = 'Ollama auf dem Server ist nicht erreichbar.';
            thinking.textContent = msg;
            return;
          }
          thinking.textContent = result.data.reply;
          history.push({ role: 'user', content: text });
          history.push({ role: 'assistant', content: result.data.reply });
          if (history.length > 12) history = history.slice(-12);
          log.scrollTop = log.scrollHeight;
        })
        .catch(function () {
          thinking.classList.remove('is-thinking');
          thinking.textContent = 'Netzwerkfehler – bitte später erneut versuchen.';
        })
        .then(function () {
          setBusy(false);
          if (input) input.focus();
        });
    });
  }
})();
