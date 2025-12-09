(function () {
  const orbs = document.querySelectorAll('.orb');
  const lerp = (a, b, t) => a + (b - a) * t;
  let targetX = 0;
  let targetY = 0;
  let currentX = 0;
  let currentY = 0;

  const move = () => {
    currentX = lerp(currentX, targetX, 0.08);
    currentY = lerp(currentY, targetY, 0.08);
    orbs.forEach((orb, i) => {
      const intensity = (i + 1) * 8;
      orb.style.transform = `translate(${currentX / intensity}px, ${currentY / intensity}px)`;
    });
    requestAnimationFrame(move);
  };

  window.addEventListener('pointermove', (ev) => {
    const { innerWidth, innerHeight } = window;
    targetX = ev.clientX - innerWidth / 2;
    targetY = ev.clientY - innerHeight / 2;
  });

  move();
})();