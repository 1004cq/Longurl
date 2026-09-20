(() => {
  const canvas = document.getElementById('eeee-scene');
  if (!canvas || !window.THREE) return;
  const THREE = window.THREE;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(55, innerWidth / innerHeight, .1, 2000);
  camera.position.set(0, 1.5, 26);
  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  const viewport = () => ({ width: window.visualViewport?.width || innerWidth, height: window.visualViewport?.height || innerHeight });
  const mobile = () => Math.min(viewport().width, viewport().height) < 600;
  renderer.setPixelRatio(Math.min(devicePixelRatio, mobile() ? 1.25 : 1.8));
  renderer.setSize(viewport().width, viewport().height, false);
  const group = new THREE.Group(); scene.add(group);
  const flying = new THREE.Group(); scene.add(flying);
  const ambient = new THREE.Group(); scene.add(ambient);
  const clock = new THREE.Clock();
  const dots = [];
  const floaters = [];
  const textureCanvas = document.createElement('canvas'); textureCanvas.width = 128; textureCanvas.height = 128;
  const ctx = textureCanvas.getContext('2d'); ctx.font = '900 108px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#d5ff55'; ctx.shadowColor = '#d5ff55'; ctx.shadowBlur = 18; ctx.fillText('e', 64, 68);
  const eTexture = new THREE.CanvasTexture(textureCanvas);
  const material = new THREE.SpriteMaterial({ map: eTexture, transparent: true, opacity: .76, depthWrite: false });
  const ambientMaterial = new THREE.SpriteMaterial({ map: eTexture, transparent: true, opacity: .22, depthWrite: false });
  const rand = (n) => (Math.random() - .5) * n;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const pointer = new THREE.Vector2();
  const pointerTarget = new THREE.Vector2();
  let targetCount = 90, mode = 'home', seed = 0, lastMobile = mobile();

  function clear(groupRef) { while (groupRef.children.length) groupRef.remove(groupRef.children[0]); }

  function rebuildFloaters() {
    clear(ambient); floaters.length = 0;
    if (reducedMotion.matches) return;

    const total = mobile() ? 14 : 30;
    const spreadX = mobile() ? 20 : 34;
    const spreadY = mobile() ? 28 : 24;

    for (let i = 0; i < total; i++) {
      const sprite = new THREE.Sprite(ambientMaterial.clone());
      const depth = -8 + Math.random() * 18;
      const size = .22 + Math.random() * (mobile() ? .5 : .75);
      sprite.position.set(rand(spreadX), rand(spreadY), depth);
      sprite.scale.set(size, size, 1);
      sprite.material.opacity = .08 + Math.random() * .24;
      sprite.userData = {
        baseX: sprite.position.x,
        phase: Math.random() * Math.PI * 2,
        speed: .18 + Math.random() * .34,
        rise: .22 + Math.random() * .48,
        sway: .18 + Math.random() * .42,
        spin: (Math.random() - .5) * .22,
        parallax: .12 + ((depth + 8) / 18) * .34,
        pulse: .65 + Math.random() * .9,
        baseOpacity: .08 + Math.random() * .22,
      };
      ambient.add(sprite); floaters.push(sprite);
    }
  }

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
  function setLength(count) {
    const n = Number(count) || 100;
    const nextCount = Math.min(150, Math.max(12, Math.round(n / 18)));
    const nextSize = .45 + (n / 5000) * .8;
    if (mode === 'home' && nextCount === targetCount && dots.length === nextCount) {
      dots.forEach((sprite) => sprite.scale.set(nextSize, nextSize, 1));
      return;
    }
    seed += .08;
    mode = 'home';
    rebuild(n);
  }
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

    if (!reducedMotion.matches) {
      const top = mobile() ? 15 : 13;
      const bottom = -top;
      floaters.forEach((s) => {
        s.position.y += s.userData.rise * dt;
        const parallaxX = pointer.x * s.userData.parallax * (mobile() ? .55 : 1);
        const parallaxY = pointer.y * s.userData.parallax * .55;
        s.position.x = s.userData.baseX + Math.sin(t * s.userData.speed + s.userData.phase) * s.userData.sway + parallaxX;
        s.position.y += parallaxY * dt;
        s.material.rotation += s.userData.spin * dt;
        const shimmer = Math.sin(t * s.userData.pulse + s.userData.phase) * .08;
        s.material.opacity += (s.userData.baseOpacity + shimmer - s.material.opacity) * dt * 1.25;

        if (s.position.y > top) {
          s.position.y = bottom - Math.random() * 3;
          s.userData.baseX = rand(mobile() ? 20 : 34);
          s.position.x = s.userData.baseX;
        }
      });
    }
    pointer.lerp(pointerTarget, Math.min(1, dt * 2.8));
    group.rotation.y += ((pointer.x * .018) - group.rotation.y) * dt * 2;
    ambient.rotation.z += ((pointer.x * .006) - ambient.rotation.z) * dt * 1.5;

    if (mode === 'success') { flying.position.x += dt * 9; flying.rotation.z = Math.sin(t * 1.6) * .05; camera.position.z += (42 - camera.position.z) * dt * 1.5; if (flying.position.x > 18) { flying.position.x = -18; camera.position.z = 26; } }
    else { camera.position.z += (26 - camera.position.z) * dt * 2; }
    camera.position.x += ((pointer.x * (mobile() ? .18 : .34)) - camera.position.x) * dt * 1.6;
    camera.position.y += ((1.5 - pointer.y * .18) - camera.position.y) * dt * 1.6;
    camera.lookAt(0, 0, 0); renderer.render(scene, camera);
  }
  const resize = () => {
    const size = viewport();
    camera.aspect = size.width / Math.max(1, size.height);
    camera.fov = mobile() ? 62 : 55;
    camera.updateProjectionMatrix();
    renderer.setPixelRatio(Math.min(devicePixelRatio, mobile() ? 1.25 : 1.8));
    renderer.setSize(size.width, size.height, false);
    const nowMobile = mobile();
    if (nowMobile !== lastMobile) {
      lastMobile = nowMobile;
      rebuildFloaters();
    }
  };
  const updatePointer = (x, y) => {
    if (reducedMotion.matches) return;
    const size = viewport();
    pointerTarget.set(
      ((x / Math.max(1, size.width)) - .5) * 2,
      ((y / Math.max(1, size.height)) - .5) * 2
    );
  };
  addEventListener('pointermove', (event) => updatePointer(event.clientX, event.clientY), { passive: true });
  addEventListener('pointerleave', () => pointerTarget.set(0, 0), { passive: true });
  addEventListener('resize', resize, { passive: true });
  window.visualViewport?.addEventListener('resize', resize, { passive: true });
  reducedMotion.addEventListener?.('change', () => {
    pointer.set(0, 0); pointerTarget.set(0, 0); rebuildFloaters();
  });
  rebuildFloaters(); rebuild(100); animate();
  window.EEEEScene = { setLength, success, lost };
})();
