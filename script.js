(function () {
  function getCookie(name) {
    var key = encodeURIComponent(name) + '=';
    var jar = document.cookie;
    if (!jar) return null;
    var parts = jar.split(';');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].replace(/^\s+/, '');
      if (p.indexOf(key) === 0) {
        try {
          return decodeURIComponent(p.substring(key.length));
        } catch (e) {
          return p.substring(key.length);
        }
      }
    }
    return null;
  }

  function setCookie(name, value, maxAgeSeconds) {
    var segments = [
      encodeURIComponent(name) + '=' + encodeURIComponent(value),
      'max-age=' + String(maxAgeSeconds)
    ];
    if (typeof location !== 'undefined') {
      if (location.protocol !== 'file:') {
        segments.push('path=/');
        segments.push('SameSite=Lax');
      }
      if (location.protocol === 'https:') {
        segments.push('Secure');
      }
    } else {
      segments.push('path=/');
      segments.push('SameSite=Lax');
    }
    document.cookie = segments.join('; ');
  }

  function setBesucherCookie() {
    var value = new Date().toISOString();
    try {
      setCookie('webseite_besuch', value, 60 * 60 * 24 * 365);
    } catch (e) {
      /* document.cookie nicht verfügbar */
    }
    if (getCookie('webseite_besuch') === null) {
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem('webseite_besuch', value);
        }
      } catch (e2) {
        /* z. B. privates Fenster, Speicher voll */
      }
    }
  }

  setBesucherCookie();

  var COOKIE_HINWEIS_LS = 'webseite_cookie_hinweis_ok';

  function initCookieBanner() {
    if (!document.body) return;
    var dismissed = false;
    try {
      if (typeof localStorage !== 'undefined') {
        dismissed = localStorage.getItem(COOKIE_HINWEIS_LS) === '1';
      }
    } catch (e) {
      dismissed = false;
    }
    if (dismissed) return;

    var banner = document.createElement('div');
    banner.className = 'cookie-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Hinweis zu Cookies');

    var inner = document.createElement('div');
    inner.className = 'cookie-banner__inner';

    var p = document.createElement('p');
    p.className = 'cookie-banner__text';
    p.textContent =
      'Diese Website verwendet ein Cookie, um den Zeitpunkt Ihres Besuchs zu speichern. Sie können Cookies in Ihren Browsereinstellungen jederzeit löschen oder einschränken.';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cookie-banner__btn';
    btn.textContent = 'Verstanden';

    inner.appendChild(p);
    inner.appendChild(btn);
    banner.appendChild(inner);
    document.body.appendChild(banner);
    document.body.classList.add('cookie-banner-visible');

    btn.addEventListener('click', function () {
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem(COOKIE_HINWEIS_LS, '1');
        }
      } catch (e2) {
        /* ignorieren */
      }
      banner.remove();
      document.body.classList.remove('cookie-banner-visible');
    });
  }

  initCookieBanner();

  function updateLastModified() {
    var el = document.getElementById('last-updated');
    if (!el) return;
    var raw = document.lastModified;
    if (!raw) return;
    var d = new Date(raw);
    if (!isNaN(d.getTime())) {
      var options = { day: 'numeric', month: 'long', year: 'numeric' };
      el.textContent = 'Zuletzt geändert: ' + d.toLocaleDateString('de-DE', options);
    } else {
      el.textContent = 'Zuletzt geändert: ' + raw;
    }
  }

  var canvas = document.getElementById('confetti-canvas');
  if (!canvas) {
    updateLastModified();
    return;
  }
  var ctx = canvas.getContext('2d');
  var particles = [];
  var colors = ['#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3', '#ff9f43', '#ee5a24', '#c8d6af'];
  var maxParticles = 60;      // Standard: Desktop
  var spawnInterval = 130;

  function updateDensity() {
    var isSmall = window.innerWidth <= 640;
    if (isSmall) {
      maxParticles = 30;      // weniger Konfetti auf dem Handy
      spawnInterval = 220;
    } else {
      maxParticles = 60;
      spawnInterval = 130;
    }
  }

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    updateDensity();
  }

  function createParticle() {
    return {
      x: Math.random() * canvas.width,
      y: -10,
      w: 6 + Math.random() * 8,
      h: 4 + Math.random() * 6,
      color: colors[Math.floor(Math.random() * colors.length)],
      vx: (Math.random() - 0.5) * 0.8,
      vy: 1.2 + Math.random() * 1.5,
      rot: Math.random() * 360,
      rotSpeed: (Math.random() - 0.5) * 8
    };
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (var i = particles.length - 1; i >= 0; i--) {
      var p = particles[i];
      ctx.save();
      ctx.translate(p.x + p.w / 2, p.y + p.h / 2);
      ctx.rotate((p.rot * Math.PI) / 180);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
      p.x += p.vx;
      p.y += p.vy;
      p.rot += p.rotSpeed;
      if (p.y > canvas.height + 20) particles.splice(i, 1);
    }
    requestAnimationFrame(draw);
  }

  function spawn() {
    if (particles.length < maxParticles) {
      particles.push(createParticle());
    }
    setTimeout(spawn, spawnInterval);
  }

  window.addEventListener('resize', resize);
  resize();
  spawn();
  draw();
  updateLastModified();
})();
