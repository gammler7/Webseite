(function () {
  var canvas = document.getElementById('confetti-canvas');
  var ctx = canvas.getContext('2d');
  var particles = [];
  var colors = ['#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3', '#ff9f43', '#ee5a24', '#c8d6af'];
  var maxParticles = 60;      // Standard: Desktop
  var spawnInterval = 130;
  var birdAudio = document.getElementById('bird-sounds');

  function updateDensity() {
    var isSmall = window.innerWidth <= 640;
    if (isSmall) {
      maxParticles = 30;      // deutlich weniger Eier auf dem Handy
      spawnInterval = 220;    // langsamere Spawn-Rate
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
    var base = 14 + Math.random() * 12;
    return {
      x: Math.random() * canvas.width,
      y: -10,
      w: base,
      h: base * 1.35, // etwas länglicher für Ei-Form
      color: colors[Math.floor(Math.random() * colors.length)],
      vx: (Math.random() - 0.5) * 0.4,
      vy: 1.1 + Math.random() * 1.4,
      rot: Math.random() * 360,
      rotSpeed: (Math.random() - 0.5) * 3
    };
  }

  function drawEgg(p) {
    ctx.save();
    // In die Mitte des Eis verschieben
    ctx.translate(p.x + p.w / 2, p.y + p.h / 2);
    ctx.rotate((p.rot * Math.PI) / 180);
    // Ei-Form: unten etwas breiter als oben
    ctx.scale(1, 1.15);
    ctx.beginPath();
    ctx.ellipse(0, 0, p.w / 2, p.h / 2, 0, 0, Math.PI * 2);
    ctx.fillStyle = p.color;
    ctx.fill();
    ctx.restore();
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (var i = particles.length - 1; i >= 0; i--) {
      var p = particles[i];
      drawEgg(p);
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

  // Vogelgezwitscher möglichst sofort starten (Autoplay kann vom Browser blockiert werden)
  if (birdAudio) {
    birdAudio.autoplay = true;
    birdAudio.play().catch(function () {
      // Falls der Browser Autoplay verbietet, kann der Nutzer über die Controls starten.
    });
  }

  window.addEventListener('resize', resize);
  resize();
  spawn();
  draw();
})();
