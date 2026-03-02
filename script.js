(function () {
  var canvas = document.getElementById('confetti-canvas');
  var ctx = canvas.getContext('2d');
  var particles = [];
  var colors = ['#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3', '#ff9f43', '#ee5a24', '#c8d6af'];
  var maxParticles = 120;
  var spawnInterval = 80;

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
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
})();
