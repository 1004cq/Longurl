(() => {
  const canvas = document.getElementById('eeee-scene');
  if (!canvas || !window.THREE) return;
  const THREE = window.THREE;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(55, innerWidth / innerHeight, .1, 2000);
  camera.position.set(0, 1.5, 26);
  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 1.8));
  renderer.setSize(innerWidth, innerHeight);
  const group = new THREE.Group(); scene.add(group);
  const flying = new THREE.Group(); scene.add(flying);
  const clock = new THREE.Clock();
  const dots = [];
  const textureCanvas = document.createElement('canvas'); textureCanvas.width = 128; textureCanvas.height = 128;
  const ctx = textureCanvas.getContext('2d'); ctx.font = '900 108px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#d5ff55'; ctx.shadowColor = '#d5ff55'; ctx.shadowBlur = 18; ctx.fillText('e', 64, 68);
  const eTexture = new THREE.CanvasTexture(textureCanvas);
  const material = new THREE.SpriteMaterial({ map: eTexture, transparent: true, opacity: .76, depthWrite: false });
  const rand = (n) => (Math.random() - .5) * n;
  let targetCount = 90, mode = 'home', seed = 0;

  function clear(groupRef) { while (groupRef.children.length) groupRef.remove(groupRef.children[0]); }
  function rebuild(count) {
    clear(group); dots.length = 0; targetCount = Math.min(150, Math.max(12, Math.round(count / 18)));
    for (let i = 0; i < targetCount; i++) {
      const sprite = new THREE.Sprite(material.clone());
      const t = i / Math.max(1, targetCount - 1);
      sprite.position.set((t - .5) * 18 + rand(1.1), Math.sin(t * Math.PI * 3 + seed) * 2.3 + rand(1.5), rand(7) - 1);
      const size = .45 + (count / 5000) * .8;
      sprite.scale.set(size, size, 1); sprite.userData = { base: sprite.position.clone(), phase: Math.random() * 6.28, speed: .4 + Math.random() * .8 };
      group.add(sprite); dots.push(sprite);
    }
  }
  function setLength(count) { seed += .08; mode = 'home'; rebuild(Number(count) || 100); }
  function lost() { mode = 'lost'; dots.forEach((s) => { s.userData.velocity = new THREE.Vector3(rand(1.8), 1 + Math.random() * 2.8, rand(1.4)); }); }
  function success(count) {
    mode = 'success'; clear(flying);
    const total = Math.min(80, Math.max(24, Math.round(Number(count) / 12)));
    for (let i = 0; i < total; i++) { const s = new THREE.Sprite(material.clone()); s.position.set(-14 + i * .36, rand(.25), rand(.7)); s.scale.set(.46,.46,1); flying.add(s); }
    camera.position.z = 26;
  }
  function animate() {
    requestAnimationFrame(animate); const dt = Math.min(clock.getDelta(), .05); const t = clock.elapsedTime;
    dots.forEach((s) => {
      if (mode === 'lost') { s.position.addScaledVector(s.userData.velocity, dt); s.userData.velocity.y -= dt * .8; s.material.opacity = Math.max(0, s.material.opacity - dt * .12); }
      else { s.position.y = s.userData.base.y + Math.sin(t * s.userData.speed + s.userData.phase) * .22; s.position.x = s.userData.base.x + Math.cos(t * .35 + s.userData.phase) * .12; s.material.opacity = .48 + Math.sin(t * 1.4 + s.userData.phase) * .2; }
    });
    if (mode === 'success') { flying.position.x += dt * 9; flying.rotation.z = Math.sin(t * 1.6) * .05; camera.position.z += (42 - camera.position.z) * dt * 1.5; if (flying.position.x > 18) { flying.position.x = -18; camera.position.z = 26; } }
    else { camera.position.z += (26 - camera.position.z) * dt * 2; }
    camera.lookAt(0, 0, 0); renderer.render(scene, camera);
  }
  addEventListener('resize', () => { camera.aspect = innerWidth / innerHeight; camera.updateProjectionMatrix(); renderer.setSize(innerWidth, innerHeight); });
  rebuild(100); animate();
  window.EEEEScene = { setLength, success, lost };
})();
